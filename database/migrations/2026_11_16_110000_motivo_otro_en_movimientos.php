<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Motivo «Otro» para los movimientos de inventario (sirve para cualquier tipo; el detalle se escribe aparte). */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('motivos_movimiento')->where('codigo', 'OTRO01')->exists()) {
            return;
        }

        DB::table('motivos_movimiento')->insert([
            'nombre' => 'Otro', 'codigo' => 'OTRO01', 'tipo' => 'otros',
            'descripcion' => 'Cualquier motivo que no esté en la lista; se puede escribir cuál.',
            'requiere_aprobacion' => 0, 'afecta_stock' => 1, 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('motivos_movimiento')->where('codigo', 'OTRO01')->delete();
    }
};
