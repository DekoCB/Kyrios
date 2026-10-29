<?php

namespace App\Console\Commands;

use App\Models\ProductoVariante;
use App\Support\LectorBaseProductos;
use Illuminate\Console\Command;

/**
 * Copia a cada variante el nombre de fábrica y el nombre K de su propia fila del Excel. El producto solo guardaba
 * los de la primera fila de su grupo; así «Cuadro» conserva Calma, Cálido y Centro. Nunca pisa un nombre ya escrito.
 * Idempotente.
 */
class NombresPorVariante extends Command
{
    protected $signature = 'productos:nombres-por-variante
                            {archivo=C:\Users\Deko\Downloads\Productos.xlsx : Productos.xlsx original}
                            {--dry-run : Solo cuenta lo que cambiaría}';

    protected $description = 'Completa el nombre de fábrica y el nombre K de cada variante desde el Excel';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        ini_set('memory_limit', '3G');
        $filas = LectorBaseProductos::filas($archivo, 12);

        $porCodigo = [];
        foreach ($filas as $f) {
            $codigo = LectorBaseProductos::texto($f[LectorBaseProductos::COL_CODIGO] ?? null);
            if ($codigo) {
                $porCodigo[$codigo][] = [
                    'excel' => LectorBaseProductos::texto($f[LectorBaseProductos::COL_CODIGO_KYRIOS_EXCEL] ?? null),
                    'fab'   => LectorBaseProductos::texto($f[LectorBaseProductos::COL_NOMBRE_FABRICA] ?? null),
                    'k'     => LectorBaseProductos::texto($f[LectorBaseProductos::COL_NOMBRE_K] ?? null),
                ];
            }
        }

        $total = ['fab' => 0, 'k' => 0, 'sin_fila' => 0];
        $cambios = [];

        ProductoVariante::chunkById(300, function ($lote) use ($porCodigo, &$total, &$cambios) {
            foreach ($lote as $v) {
                $candidatas = $porCodigo[$v->codigo_fabricante] ?? [];
                if (!$candidatas) {
                    $total['sin_fila']++;
                    continue;
                }
                // Con códigos repetidos se prefiere la fila cuyo código del Excel coincide con el de la variante
                $fila = collect($candidatas)->first(fn ($c) => $c['excel'] && $c['excel'] === $v->codigo_excel) ?? $candidatas[0];

                $cambio = [];
                if (blank($v->nombre_fabrica) && $fila['fab']) {
                    $cambio['nombre_fabrica'] = mb_substr($fila['fab'], 0, 150);
                    $total['fab']++;
                }
                if (blank($v->nombre_kyrios) && $fila['k']) {
                    $cambio['nombre_kyrios'] = mb_substr($fila['k'], 0, 150);
                    $total['k']++;
                }
                if ($cambio) {
                    $cambios[$v->id] = $cambio;
                }
            }
        });

        $this->line("Variantes sin fila en el Excel: {$total['sin_fila']}. Nombres de fábrica a completar: {$total['fab']}; nombres K: {$total['k']}.");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se cambió nada.');
            return self::SUCCESS;
        }

        foreach ($cambios as $id => $cambio) {
            ProductoVariante::whereKey($id)->update($cambio);
        }
        $this->info(count($cambios) . ' variantes actualizadas.');

        return self::SUCCESS;
    }
}
