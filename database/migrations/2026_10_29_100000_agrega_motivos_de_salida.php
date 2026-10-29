<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Motivos de salida pedidos para la Salida con escáner. Idempotente: no repite los que ya existen por código.
 * («Venta» ya existe como «Venta a cliente», código VENT01.)
 */
return new class extends Migration
{
    private const MOTIVOS = [
        ['nombre' => 'Auspicios',            'codigo' => 'AUSP01'],
        ['nombre' => 'Consignación',         'codigo' => 'CONS01'],
        ['nombre' => 'Cambio por garantía',  'codigo' => 'GARA01'],
        ['nombre' => 'Cambio de producto',   'codigo' => 'CAMP01'],
        ['nombre' => 'Cambio de almacén',    'codigo' => 'CAMA01'],
    ];

    public function up(): void
    {
        foreach (self::MOTIVOS as $m) {
            if (DB::table('motivos_movimiento')->where('codigo', $m['codigo'])->exists()) {
                continue;
            }
            DB::table('motivos_movimiento')->insert($m + [
                'tipo' => 'salida', 'requiere_aprobacion' => 0, 'afecta_stock' => 1, 'estado' => 'activo',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('motivos_movimiento')->whereIn('codigo', array_column(self::MOTIVOS, 'codigo'))->delete();
    }
};
