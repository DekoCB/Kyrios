<?php

namespace App\Console\Commands;

use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Services\VarianteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarProductosAVariantes extends Command
{
    protected $signature = 'productos:migrar-variantes
                            {--dry-run      : Mostrar qué se haría sin hacer cambios}
                            {--solo-colores : Solo migrar productos que tengan color_id asignado}
                            {--id=*         : IDs específicos de productos a migrar}';

    protected $description = 'Migra productos con color_id a la estructura de ProductoVariante';

    public function handle(VarianteService $varianteService): int
    {
        $dryRun      = $this->option('dry-run');
        $soloColores = $this->option('solo-colores');
        $idsFilter   = $this->option('id');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   Migración de Productos → ProductoVariante      ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('  ⚠  MODO DRY-RUN: no se realizarán cambios en la base de datos.');
            $this->info('');
        }

        // ── 1. Obtener productos candidatos ─────────────────────────────────
        $query = Producto::with(['color', 'marca', 'modelo', 'variantes'])
            ->whereNotNull('color_id')
            ->orWhereHas('variantes', fn($q) => $q); // también los que ya tienen variantes (para resincronizar)

        if ($soloColores) {
            $query = Producto::with(['color', 'marca', 'modelo', 'variantes'])
                ->whereNotNull('color_id');
        }

        if (!empty($idsFilter)) {
            $query->whereIn('id', $idsFilter);
        }

        // Agrupar por (marca_id, modelo_id, categoria_id) para detectar variantes del mismo base
        $productosConColor = Producto::with(['color', 'marca', 'modelo', 'variantes'])
            ->whereNotNull('color_id')
            ->when(!empty($idsFilter), fn($q) => $q->whereIn('id', $idsFilter))
            ->orderBy('id')
            ->get();

        if ($productosConColor->isEmpty()) {
            $this->info('  ✓ No se encontraron productos con color_id para migrar.');
            return self::SUCCESS;
        }

        $this->info("  Productos con color asignado encontrados: <fg=yellow>{$productosConColor->count()}</>");

        // Agrupar por (marca_id, modelo_id, categoria_id)
        $grupos = $productosConColor->groupBy(fn($p) =>
            "{$p->marca_id}|{$p->modelo_id}|{$p->categoria_id}"
        );

        $this->info("  Grupos detectados: <fg=yellow>{$grupos->count()}</>");
        $this->info('');

        $totalVariantesCreadas  = 0;
        $totalProductosInactivos = 0;
        $errores                = 0;

        // ── 2. Procesar cada grupo ───────────────────────────────────────────
        foreach ($grupos as $claveGrupo => $productosGrupo) {

            // Encontrar el producto base: el que ya existe sin color (mismo marca+modelo+cat)
            [$marcaId, $modeloId, $catId] = explode('|', $claveGrupo);

            $productoBase = Producto::where('marca_id', $marcaId ?: null)
                ->where('modelo_id', $modeloId ?: null)
                ->where('categoria_id', $catId ?: null)
                ->whereNull('color_id')
                ->first();

            // Si no existe un producto base sin color, usamos el primero del grupo
            if (!$productoBase) {
                $productoBase = $productosGrupo->first();
            }

            $this->info("  <fg=cyan>Grupo:</> {$this->descripcionGrupo($productoBase)} ({$productosGrupo->count()} variante(s))");

            foreach ($productosGrupo as $producto) {
                $this->procesarProducto(
                    $producto,
                    $productoBase,
                    $varianteService,
                    $dryRun,
                    $totalVariantesCreadas,
                    $totalProductosInactivos,
                    $errores
                );
            }

            $this->info('');
        }

        // ── 3. Opcionalmente migrar productos sin color pero sin variantes ──
        if (!$soloColores) {
            $sinVariantes = Producto::whereNull('color_id')
                ->whereDoesntHave('variantes')
                ->where('estado', 'activo')
                ->when(!empty($idsFilter), fn($q) => $q->whereIn('id', $idsFilter))
                ->count();

            if ($sinVariantes > 0) {
                $this->warn("  {$sinVariantes} producto(s) sin color y sin variantes no fueron procesados.");
                $this->warn('  Usa --solo-colores o especifica --id=X para migrarlos individualmente.');
            }
        }

        // ── 4. Resumen ───────────────────────────────────────────────────────
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  RESUMEN' . ($dryRun ? ' (DRY-RUN)' : ''));
        $this->table(
            ['Métrica', 'Resultado'],
            [
                ['Variantes creadas',         $totalVariantesCreadas],
                ['Productos desactivados',    $totalProductosInactivos],
                ['Errores',                   $errores],
            ]
        );

        if ($dryRun) {
            $this->warn('  Ejecuta sin --dry-run para aplicar los cambios.');
        } else {
            $this->info('  ✓ Migración completada.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function procesarProducto(
        Producto $producto,
        Producto $productoBase,
        VarianteService $varianteService,
        bool $dryRun,
        int &$totalCreadas,
        int &$totalInactivos,
        int &$errores
    ): void {
        try {
            $colorNombre = $producto->color?->nombre ?? 'Sin color';
            $sku         = ProductoVariante::generarSku($productoBase, $producto->color, null);

            $this->line("    → <fg=white>{$producto->nombre}</> (ID:{$producto->id}) | Color: {$colorNombre} | Stock: {$producto->stock_actual}");

            // Verificar si ya existe una variante para este color en el producto base
            $varianteExistente = $productoBase->variantes()
                ->where('color_id', $producto->color_id)
                ->first();

            if ($varianteExistente) {
                $this->warn("      Variante ya existe (SKU: {$varianteExistente->sku}), omitiendo creación.");
            } else {
                if (!$dryRun) {
                    DB::transaction(function () use ($producto, $productoBase, &$totalCreadas) {

                        // Crear la variante en el producto base
                        $variante = ProductoVariante::create([
                            'producto_id'  => $productoBase->id,
                            'color_id'     => $producto->color_id,
                            'capacidad'    => null,
                            'sku'          => ProductoVariante::generarSku($productoBase, $producto->color, null),
                            'sobreprecio'  => 0,
                            'stock_actual' => $producto->stock_actual,
                            'stock_minimo' => $producto->stock_minimo ?? 0,
                            'estado'       => 'activo',
                            'imagen'       => $producto->imagen,
                            'creado_por'   => $producto->creado_por ?? 1,
                        ]);

                        $totalCreadas++;

                        // Actualizar detalle_compras
                        DB::table('detalle_compras')
                            ->where('producto_id', $producto->id)
                            ->whereNull('variante_id')
                            ->update(['variante_id' => $variante->id]);

                        // Actualizar detalle_ventas
                        DB::table('detalle_ventas')
                            ->where('producto_id', $producto->id)
                            ->whereNull('variante_id')
                            ->update(['variante_id' => $variante->id]);

                        // Actualizar movimientos_inventario
                        DB::table('movimientos_inventario')
                            ->where('producto_id', $producto->id)
                            ->whereNull('variante_id')
                            ->update(['variante_id' => $variante->id]);
                    });
                } else {
                    $this->line("      <fg=yellow>[DRY-RUN]</> Crearía variante SKU: {$sku} con stock {$producto->stock_actual}");
                    $totalCreadas++;
                }
            }

            // Si el producto NO es el base, desactivarlo
            if ($producto->id !== $productoBase->id) {
                if (!$dryRun) {
                    $producto->update(['estado' => 'inactivo']);
                } else {
                    $this->line("      <fg=yellow>[DRY-RUN]</> Desactivaría producto ID:{$producto->id}");
                }
                $totalInactivos++;
            }

            // Si ES el producto base y tenía color_id, limpiarlo
            if ($producto->id === $productoBase->id && $productoBase->color_id) {
                if (!$dryRun) {
                    $productoBase->update(['color_id' => null]);
                } else {
                    $this->line("      <fg=yellow>[DRY-RUN]</> Limpiaría color_id del producto base ID:{$productoBase->id}");
                }
            }

        } catch (\Throwable $e) {
            $this->error("      ERROR en producto ID:{$producto->id}: {$e->getMessage()}");
            $errores++;
        }
    }

    private function descripcionGrupo(Producto $base): string
    {
        $partes = [];
        if ($base->marca) $partes[] = $base->marca->nombre;
        if ($base->modelo) $partes[] = $base->modelo->nombre;
        if ($base->categoria) $partes[] = '(' . $base->categoria->nombre . ')';
        return implode(' ', $partes) ?: "Producto #{$base->id}";
    }
}
