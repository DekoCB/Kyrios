<?php

namespace App\Console\Commands;

use App\Models\Imei;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararImeisVendidos extends Command
{
    protected $signature = 'ventas:reparar-imeis-vendidos {--dry-run : Mostrar qué se haría sin hacer cambios}';

    protected $description = 'Libera los IMEIs que quedaron marcados "vendido" de ventas ya anuladas o eliminadas '
        . '(bug histórico: detalle_ventas.imei_id nunca se llenaba al vender, así que anular/eliminar la venta '
        . 'nunca revertía el IMEI de vuelta a "en_stock").';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   Reparación de IMEIs atascados en "vendido"     ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('  ⚠  MODO DRY-RUN: no se realizarán cambios en la base de datos.');
            $this->info('');
        }

        // IMEIs "vendido" cuya venta ya no es válida:
        //  - fue anulada (estado_pago = 'anulado'), o
        //  - fue eliminada (soft-delete, deleted_at no nulo)
        $ventasInvalidasIds = Venta::withTrashed()
            ->where(function ($q) {
                $q->where('estado_pago', 'anulado')
                  ->orWhereNotNull('deleted_at');
            })
            ->pluck('id');

        $imeisAfectados = Imei::with('producto', 'variante')
            ->where('estado_imei', Imei::ESTADO_VENDIDO)
            ->whereIn('venta_id', $ventasInvalidasIds)
            ->get();

        if ($imeisAfectados->isEmpty()) {
            $this->info('  ✓ No se encontraron IMEIs atascados. No hay nada que reparar.');
            return self::SUCCESS;
        }

        $this->table(
            ['IMEI', 'Producto', 'Variante', 'Venta ID', 'Fecha venta'],
            $imeisAfectados->map(fn($i) => [
                $i->codigo_imei,
                $i->producto?->nombre ?? "#{$i->producto_id}",
                $i->variante?->nombre_completo ?? '—',
                $i->venta_id,
                optional($i->fecha_venta)->format('Y-m-d') ?? '—',
            ])
        );

        $this->info('');
        $this->info("  Total de IMEIs a liberar: {$imeisAfectados->count()}");
        $this->info('');

        if ($dryRun) {
            $this->warn('  Dry-run: no se aplicó ningún cambio. Ejecuta sin --dry-run para aplicar.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Confirmas liberar estos IMEIs y recalcular el stock afectado?', true)) {
            $this->warn('  Operación cancelada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($imeisAfectados) {
            Imei::whereIn('id', $imeisAfectados->pluck('id'))->update([
                'estado_imei' => Imei::ESTADO_EN_STOCK,
                'venta_id'    => null,
                'fecha_venta' => null,
            ]);

            $productoIds  = $imeisAfectados->pluck('producto_id')->unique();
            $varianteIds  = $imeisAfectados->pluck('variante_id')->filter()->unique();

            foreach ($productoIds as $productoId) {
                $totalStock = Imei::where('producto_id', $productoId)
                    ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                    ->count();
                Producto::where('id', $productoId)->update(['stock_actual' => $totalStock]);
            }

            foreach ($varianteIds as $varianteId) {
                $totalVariante = Imei::where('variante_id', $varianteId)
                    ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                    ->count();
                ProductoVariante::where('id', $varianteId)->update(['stock_actual' => $totalVariante]);
            }
        });

        $this->info('  ✓ IMEIs liberados y stock recalculado correctamente.');
        $this->info('');

        return self::SUCCESS;
    }
}
