<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Catalogo\TipoProducto;
use App\Models\Producto;
use App\Support\LectorBaseProductos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Los productos cuya fila del Excel no traía «Tipo de producto» se importaron como Luminaria.
 * Este comando los pasa a «Sin tipo» (y a su categoría) SIN tocar sus códigos Kyrios.
 * Idempotente: una segunda corrida no encuentra nada que cambiar.
 */
class MarcarProductosSinTipo extends Command
{
    protected $signature = 'productos:marcar-sin-tipo
                            {archivo=C:\Users\Deko\Downloads\Productos.xlsx : Productos.xlsx original}
                            {--dry-run : Solo muestra lo que cambiaría}';

    protected $description = 'Re-etiqueta como «Sin tipo» los productos cuyo tipo estaba vacío en el Excel (no cambia códigos)';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        ini_set('memory_limit', '3G');
        $this->info('Leyendo «Base de Productos»...');
        try {
            $filas = LectorBaseProductos::filas($archivo, 12);
        } catch (\Throwable $e) {
            $this->error('No se pudo leer el Excel: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Código de fabricante → ¿alguna fila con tipo? ¿alguna sin tipo?
        $conTipo = $sinTipo = [];
        foreach ($filas as $f) {
            $codigo = LectorBaseProductos::texto($f[LectorBaseProductos::COL_CODIGO] ?? null);
            if (!$codigo) {
                continue;
            }
            if (LectorBaseProductos::texto($f[LectorBaseProductos::COL_TIPO_PRODUCTO] ?? null)) {
                $conTipo[$codigo] = true;
            } else {
                $sinTipo[$codigo] = true;
            }
        }
        // Un código repetido con y sin tipo es ambiguo: no se toca
        $ambiguos = array_intersect_key($sinTipo, $conTipo);
        $sinTipo = array_diff_key($sinTipo, $conTipo);

        $st = TipoProducto::firstOrCreate(['codigo' => 'ST'], ['nombre' => 'Sin tipo', 'caso_codigo' => 'sin_luminaria']);
        $categoria = Categoria::firstOrCreate(['codigo' => 'CAT-ST'], ['nombre' => 'Sin tipo', 'estado' => 'activo']);

        $cambiar = [];
        $mixtos = 0;
        Producto::with('variantes:id,producto_id,codigo_fabricante')
            ->where(fn ($q) => $q->whereNull('tipo_producto_id')->orWhere('tipo_producto_id', '!=', $st->id))
            ->chunkById(200, function ($lote) use ($sinTipo, &$cambiar, &$mixtos) {
                foreach ($lote as $p) {
                    $codigos = $p->variantes->pluck('codigo_fabricante')->filter()->unique();
                    if ($codigos->isEmpty()) {
                        continue; // sin código de fabricante no se puede cruzar con el Excel
                    }
                    $enSinTipo = $codigos->filter(fn ($c) => isset($sinTipo[$c]))->count();
                    if ($enSinTipo === 0) {
                        continue;
                    }
                    if ($enSinTipo < $codigos->count()) {
                        $mixtos++; // mezcla de variantes con y sin tipo en el Excel: se deja como está
                        continue;
                    }
                    $cambiar[] = $p->id;
                }
            });

        $this->line('Códigos del Excel sin tipo: ' . count($sinTipo) . ' (ambiguos, no se tocan: ' . count($ambiguos) . ')');
        $this->line('Productos a pasar a «Sin tipo»: ' . count($cambiar) . ' | productos mezclados que se dejan: ' . $mixtos);

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se cambió nada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambiar, $st, $categoria) {
            foreach (array_chunk($cambiar, 200) as $ids) {
                // Solo tipo y categoría: el código Kyrios y el tipo de luminaria quedan como estaban
                Producto::whereIn('id', $ids)->update(['tipo_producto_id' => $st->id, 'categoria_id' => $categoria->id]);
            }
        });
        $this->info('Listo: ' . count($cambiar) . ' productos marcados como «Sin tipo».');

        return self::SUCCESS;
    }
}
