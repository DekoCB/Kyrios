<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Permitir reglas combinadas "categoría + vendedor" en comision_reglas
        // (ya existía "producto_usuario"; esta es la misma idea a nivel categoría).
        DB::statement("ALTER TABLE comision_reglas MODIFY tipo_aplicacion ENUM('usuario','categoria','producto','producto_usuario','categoria_usuario') NOT NULL");
    }

    public function down(): void
    {
        DB::table('comision_reglas')->where('tipo_aplicacion', 'categoria_usuario')->update(['tipo_aplicacion' => 'categoria']);
        DB::statement("ALTER TABLE comision_reglas MODIFY tipo_aplicacion ENUM('usuario','categoria','producto','producto_usuario') NOT NULL");
    }
};
