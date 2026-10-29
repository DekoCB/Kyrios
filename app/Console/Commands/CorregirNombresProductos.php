<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Support\LectorBaseProductos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa «nombre de fábrica» y «nombre K» de los productos con los del Excel original cuando
 * en la base están vacíos (el importador solo miraba la primera fila de cada modelo). Nunca pisa
 * un nombre ya escrito; los que difieren se informan. Idempotente.
 */
class CorregirNombresProductos extends Command
{
    protected $signature = 'productos:corregir-nombres
                            {archivo=C:\Users\Deko\Downloads\Productos.xlsx : Productos.xlsx original}
                            {--dry-run : Solo muestra lo que cambiaría}';

    protected $description = 'Completa nombre de fábrica y nombre K desde el Excel (solo donde están vacíos)';

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

        // Código de fabricante → nombres de sus filas (puede repetirse: se guardan todas)
        $porCodigo = [];
        foreach ($filas as $f) {
            $codigo = LectorBaseProductos::texto($f[LectorBaseProductos::COL_CODIGO] ?? null);
            if ($codigo) {
                $porCodigo[$codigo][] = [
                    'fab' => LectorBaseProductos::texto($f[LectorBaseProductos::COL_NOMBRE_FABRICA] ?? null),
                    'k'   => LectorBaseProductos::texto($f[LectorBaseProductos::COL_NOMBRE_K] ?? null),
                ];
            }
        }

        $stats = ['fab' => 0, 'k' => 0, 'nombre' => 0, 'distinto_fab' => 0, 'distinto_k' => 0];
        $cambios = [];

        Producto::with('variantes:id,producto_id,codigo_fabricante,codigo_excel,codigo_kyrios')->chunkById(200, function ($lote) use ($porCodigo, &$stats, &$cambios) {
            foreach ($lote as $p) {
                $fab = $k = [];
                foreach ($p->variantes as $v) {
                    foreach ($porCodigo[$v->codigo_fabricante] ?? [] as $n) {
                        $n['fab'] && $fab[] = $n['fab'];
                        $n['k'] && $k[] = $n['k'];
                    }
                }
                // Primer nombre no vacío del modelo (igual criterio que el importador, pero mirando todas sus filas)
                $nuevoFab = $fab[0] ?? null;
                $nuevoK = $k[0] ?? null;
                $cambio = [];

                if (filled($nuevoFab)) {
                    if (blank($p->nombre_fabrica)) {
                        $cambio['nombre_fabrica'] = $nuevoFab;
                        $stats['fab']++;
                    } elseif (mb_strtolower(trim($p->nombre_fabrica)) !== mb_strtolower(trim($nuevoFab))) {
                        $stats['distinto_fab']++;
                    }
                }
                if (filled($nuevoK)) {
                    if (blank($p->nombre_kyrios)) {
                        $cambio['nombre_kyrios'] = $nuevoK;
                        $stats['k']++;
                    } elseif (mb_strtolower(trim($p->nombre_kyrios)) !== mb_strtolower(trim($nuevoK))) {
                        $stats['distinto_k']++;
                    }
                }

                // El nombre principal solo se reemplaza si hoy es un código (como dejó el importador)
                if ($cambio) {
                    $codigos = $p->variantes->flatMap(fn ($v) => [$v->codigo_fabricante, $v->codigo_excel, $v->codigo_kyrios])->push($p->codigo)->filter()->map(fn ($c) => mb_strtolower(trim($c)));
                    $nombreK = $cambio['nombre_kyrios'] ?? $p->nombre_kyrios;
                    $nombreFab = $cambio['nombre_fabrica'] ?? $p->nombre_fabrica;
                    $nuevoNombre = $nombreK ?: $nombreFab;
                    if ($nuevoNombre && ($codigos->contains(mb_strtolower(trim((string) $p->nombre))) || blank($p->nombre))) {
                        $cambio['nombre'] = mb_substr($nuevoNombre, 0, 200);
                        $stats['nombre']++;
                    }
                    $cambios[$p->id] = $cambio;
                }
            }
        });

        $this->line("Nombre de fábrica a completar: {$stats['fab']} | nombre K a completar: {$stats['k']} | nombre principal (era un código): {$stats['nombre']}");
        $this->line("Con nombre distinto al del Excel (no se tocan): fábrica {$stats['distinto_fab']}, K {$stats['distinto_k']}");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se cambió nada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $id => $cambio) {
                DB::table('productos')->where('id', $id)->update($cambio + ['updated_at' => now()]);
            }
        });
        $this->info('Listo: ' . count($cambios) . ' productos actualizados.');

        return self::SUCCESS;
    }
}
