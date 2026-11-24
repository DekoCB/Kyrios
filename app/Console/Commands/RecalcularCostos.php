<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\CostoService;
use Illuminate\Console\Command;

class RecalcularCostos extends Command
{
    protected $signature = 'costos:recalcular';

    protected $description = 'Recalcula el costo promedio ponderado (en soles, con prorrateo) y el último costo de todos los productos con compras';

    public function handle(CostoService $costos): int
    {
        $n = 0;
        Producto::whereIn('id', \App\Models\DetalleCompra::select('producto_id'))->orderBy('id')->chunkById(200, function ($productos) use ($costos, &$n) {
            foreach ($productos as $p) {
                $costos->recalcularProducto($p->id);
                $n++;
            }
        });
        $this->info("Productos recalculados: {$n}.");

        return self::SUCCESS;
    }
}
