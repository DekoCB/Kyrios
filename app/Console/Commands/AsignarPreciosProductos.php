<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\ProductoPrecio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AsignarPreciosProductos extends Command
{
    protected $signature = 'productos:asignar-precios
                            {--dry-run    : Mostrar qué se haría sin realizar cambios}
                            {--precio=    : Precio de venta por defecto a asignar (ej: 50.00)}
                            {--margen=30  : Porcentaje de margen sobre costo_promedio (defecto: 30%)}
                            {--id=*       : IDs específicos de productos a procesar}';

    protected $description = 'Asigna precios de venta a productos que no tienen precio configurado en producto_precios';

    public function handle(): int
    {
        $dryRun      = $this->option('dry-run');
        $precioFijo  = $this->option('precio');
        $margen      = (float) $this->option('margen');
        $idsFilter   = $this->option('id');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   Asignación de Precios a Productos              ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('  ⚠  MODO DRY-RUN: no se realizarán cambios.');
            $this->info('');
        }

        // ── Obtener productos sin precio de venta regular activo ─────────────
        $query = Producto::whereDoesntHave('precios', function ($q) {
            $q->where('tipo_precio', 'venta_regular')->where('activo', true);
        })->where('estado', 'activo');

        if (!empty($idsFilter)) {
            $query->whereIn('id', $idsFilter);
        }

        $productos = $query->orderBy('nombre')->get();

        if ($productos->isEmpty()) {
            $this->info('  ✓ Todos los productos activos ya tienen precio de venta configurado.');
            return self::SUCCESS;
        }

        $this->info("  Productos sin precio de venta: <fg=yellow>{$productos->count()}</>");
        $this->info('');

        $headers = ['ID', 'Código', 'Nombre', 'Costo Promedio', 'Precio a Asignar'];
        $rows    = [];
        $asignados = 0;
        $errores   = 0;

        foreach ($productos as $producto) {
            // Determinar el precio a asignar
            if ($precioFijo !== null) {
                $precio = (float) $precioFijo;
            } elseif ($producto->costo_promedio > 0) {
                $precio = round($producto->costo_promedio * (1 + $margen / 100), 2);
            } else {
                $precio = 0.00;
            }

            $rows[] = [
                $producto->id,
                $producto->codigo,
                $producto->nombre,
                'S/ ' . number_format($producto->costo_promedio ?? 0, 2),
                'S/ ' . number_format($precio, 2),
            ];

            if (!$dryRun) {
                try {
                    DB::transaction(function () use ($producto, $precio) {
                        ProductoPrecio::create([
                            'producto_id' => $producto->id,
                            'tipo_precio' => 'venta_regular',
                            'precio'      => $precio,
                            'activo'      => true,
                            'prioridad'   => 0,
                            'creado_por'  => 1, // admin por defecto
                        ]);
                    });
                    $asignados++;
                } catch (\Throwable $e) {
                    $this->error("  ERROR en producto ID:{$producto->id}: {$e->getMessage()}");
                    $errores++;
                }
            } else {
                $asignados++;
            }
        }

        $this->table($headers, $rows);
        $this->info('');

        // ── Resumen ──────────────────────────────────────────────────────────
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  RESUMEN' . ($dryRun ? ' (DRY-RUN)' : ''));
        $this->table(
            ['Métrica', 'Resultado'],
            [
                ['Precios asignados', $asignados],
                ['Errores',           $errores],
            ]
        );

        if ($dryRun) {
            $this->info('');
            $this->warn('  Ejecuta sin --dry-run para aplicar los cambios.');
            $this->warn('  Usa --precio=X para fijar un precio específico.');
            $this->warn("  Usa --margen=X para calcular precio sobre costo (actual: {$margen}%).");
        } else {
            $this->info('  ✓ Proceso completado.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
