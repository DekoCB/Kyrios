<?php

namespace App\Console\Commands;

use App\Models\Imei;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\StockAlmacen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconciliarStock extends Command
{
    protected $signature = 'inventario:reconciliar
                            {--dry-run : Solo muestra divergencias sin corregirlas}
                            {--producto= : ID de un producto específico a verificar}';

    protected $description = 'Detecta y corrige divergencias entre stock_actual (producto y variante) y los registros reales (StockAlmacen por variante / IMEIs)';

    private int $divergenciasProductos  = 0;
    private int $divergenciasVariantes  = 0;
    private int $correccionesProductos  = 0;
    private int $correccionesVariantes  = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $productoId = $this->option('producto');

        if ($dryRun) {
            $this->warn('Modo DRY-RUN: solo lectura, no se realizarán cambios.');
        }

        $this->info('Iniciando reconciliación de stock...');
        $this->newLine();

        $query = Producto::query()->where('estado', 'activo');
        if ($productoId) {
            $query->where('id', $productoId);
        }

        $productos = $query->get();
        $bar = $this->output->createProgressBar($productos->count());
        $bar->start();

        DB::transaction(function () use ($productos, $dryRun, $bar) {
            foreach ($productos as $producto) {
                $this->reconciliarProducto($producto, $dryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Concepto', 'Divergencias', 'Corregidas'],
            [
                ['Productos',  $this->divergenciasProductos, $dryRun ? '—' : $this->correccionesProductos],
                ['Variantes', $this->divergenciasVariantes,  $dryRun ? '—' : $this->correccionesVariantes],
            ]
        );

        if ($this->divergenciasProductos === 0 && $this->divergenciasVariantes === 0) {
            $this->info('✔ Stock en perfecto estado. Sin divergencias.');
        } elseif ($dryRun) {
            $this->warn("Se encontraron {$this->divergenciasProductos} producto(s) y {$this->divergenciasVariantes} variante(s) con divergencias.");
            $this->line('Ejecute sin --dry-run para corregir.');
        } else {
            $this->info("Reconciliación completada: {$this->correccionesProductos} producto(s) y {$this->correccionesVariantes} variante(s) corregidos.");
        }

        return 0;
    }

    private function reconciliarProducto(Producto $producto, bool $dryRun): void
    {
        if ($producto->tipo_inventario === 'serie') {
            $this->reconciliarSerie($producto, $dryRun);
        } else {
            $this->reconciliarCantidad($producto, $dryRun);
        }
    }

    private function reconciliarSerie(Producto $producto, bool $dryRun): void
    {
        // Producto.stock_actual debe ser COUNT(Imei WHERE estado='en_stock')
        $stockReal = Imei::where('producto_id', $producto->id)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->count();

        if ((int) $producto->stock_actual !== $stockReal) {
            $this->divergenciasProductos++;
            $this->reportarDivergencia(
                "Producto [{$producto->id}] {$producto->nombre}",
                "stock_actual={$producto->stock_actual}, IMEIs en_stock={$stockReal}"
            );

            if (!$dryRun) {
                $producto->update(['stock_actual' => $stockReal]);
                $this->correccionesProductos++;
            }
        }

        // Variantes: stock_actual debe ser COUNT(Imei WHERE variante_id=X AND estado='en_stock')
        foreach ($producto->variantes()->where('estado', 'activo')->get() as $variante) {
            $stockVarianteReal = Imei::where('variante_id', $variante->id)
                ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                ->count();

            if ((int) $variante->stock_actual !== $stockVarianteReal) {
                $this->divergenciasVariantes++;
                $this->reportarDivergencia(
                    "  Variante [{$variante->id}] {$variante->nombre_completo}",
                    "stock_actual={$variante->stock_actual}, IMEIs en_stock={$stockVarianteReal}"
                );

                if (!$dryRun) {
                    $variante->update(['stock_actual' => $stockVarianteReal]);
                    $this->correccionesVariantes++;
                }
            }
        }
    }

    private function reconciliarCantidad(Producto $producto, bool $dryRun): void
    {
        // Producto.stock_actual debe ser SUM(StockAlmacen.cantidad)
        $stockReal = (int) StockAlmacen::where('producto_id', $producto->id)->sum('cantidad');

        if ((int) $producto->stock_actual !== $stockReal) {
            $this->divergenciasProductos++;
            $this->reportarDivergencia(
                "Producto [{$producto->id}] {$producto->nombre}",
                "stock_actual={$producto->stock_actual}, SUM(StockAlmacen)={$stockReal}"
            );

            if (!$dryRun) {
                $producto->update(['stock_actual' => $stockReal]);
                $this->correccionesProductos++;
            }
        }

        // Variantes: stock_actual de cada variante debe ser SUM(StockAlmacen.cantidad) de sus filas
        // (stock_almacen guarda una fila por producto + variante + almacén).
        $variantes = $producto->variantes()->where('estado', 'activo')->get();
        foreach ($variantes as $variante) {
            $stockVarianteReal = (int) StockAlmacen::where('variante_id', $variante->id)->sum('cantidad');

            if ((int) $variante->stock_actual !== $stockVarianteReal) {
                $this->divergenciasVariantes++;
                $this->reportarDivergencia(
                    "  Variante [{$variante->id}] " . ($variante->codigo_kyrios ?? $variante->sku),
                    "stock_actual={$variante->stock_actual}, SUM(StockAlmacen)={$stockVarianteReal}"
                );

                if (!$dryRun) {
                    $variante->update(['stock_actual' => $stockVarianteReal]);
                    $this->correccionesVariantes++;
                }
            }
        }

        // Stock "sin variante" en un producto que ya tiene variantes: no se puede repartir solo,
        // hay que asignarlo a la variante correcta (por ejemplo con un conteo físico).
        if ($variantes->isNotEmpty()) {
            $sinVariante = (int) StockAlmacen::where('producto_id', $producto->id)->whereNull('variante_id')->sum('cantidad');
            if ($sinVariante > 0) {
                $this->divergenciasVariantes++;
                $this->reportarDivergencia(
                    "  Producto [{$producto->id}] {$producto->nombre}",
                    "{$sinVariante} unidad(es) en stock_almacen sin variante asignada. Revisar y asignar manualmente."
                );
            }
        }
    }

    private function reportarDivergencia(string $descripcion, string $detalle): void
    {
        $this->newLine();
        $this->warn("⚠ Divergencia: {$descripcion}");
        $this->line("  {$detalle}");
    }
}
