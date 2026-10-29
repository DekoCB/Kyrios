<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de producto «Sin tipo» (código ST) para los productos cuyo tipo no se conoce.
 * Idempotente: si el código o el nombre ya existen, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('tipos_producto')->where('codigo', 'ST')->orWhere('nombre', 'Sin tipo')->exists();

        if (!$existe) {
            DB::table('tipos_producto')->insert([
                'codigo'      => 'ST',
                'nombre'      => 'Sin tipo',
                'caso_codigo' => 'sin_luminaria',
                'estado'      => 'activo',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Solo se quita si ningún producto lo usa
        $id = DB::table('tipos_producto')->where('codigo', 'ST')->value('id');
        if ($id && !DB::table('productos')->where('tipo_producto_id', $id)->exists()) {
            DB::table('tipos_producto')->where('id', $id)->delete();
        }
    }
};
