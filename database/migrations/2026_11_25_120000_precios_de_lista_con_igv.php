<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La primera importación de la lista oficial guardó el precio regular SIN IGV (incluye_igv = 0), pero el punto de venta,
 * las comisiones y los reportes dan por hecho que el precio regular ya incluye IGV: habrían cobrado 15 % menos.
 * Se guardan con IGV (precio × 1.18) y el margen sobre ese precio, como el formulario de Precios.
 */
return new class extends Migration {
    private const NOTA = 'Lista de precios KYRIOS (nov-2026)';

    public function up(): void
    {
        DB::table('producto_precios')
            ->where('tipo_precio', 'venta_regular')->where('incluye_igv', 0)->where('observaciones', self::NOTA)
            ->orderBy('id')->each(function ($p) {
                $con = round($p->precio * 1.18, 2);
                $compra = (float) $p->precio_compra;
                DB::table('producto_precios')->where('id', $p->id)->update([
                    'precio' => $con,
                    'incluye_igv' => 1,
                    'margen' => $compra > 0 ? min(999.99, round(($con - $compra) / $compra * 100, 2)) : $p->margen,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('producto_precios')
            ->where('tipo_precio', 'venta_regular')->where('incluye_igv', 1)->where('observaciones', self::NOTA)
            ->orderBy('id')->each(function ($p) {
                $sin = round($p->precio / 1.18, 2);
                $compra = (float) $p->precio_compra;
                DB::table('producto_precios')->where('id', $p->id)->update([
                    'precio' => $sin,
                    'incluye_igv' => 0,
                    'margen' => $compra > 0 ? min(999.99, round(($sin - $compra) / $compra * 100, 2)) : $p->margen,
                ]);
            });
    }
};
