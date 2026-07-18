<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Permitir reglas combinadas "producto + vendedor" en comision_reglas
        // (antes solo usuario XOR categoria XOR producto).
        DB::statement("ALTER TABLE comision_reglas MODIFY tipo_aplicacion ENUM('usuario','categoria','producto','producto_usuario') NOT NULL");

        // bonus_reglas no tenía columna de vendedor: los bonos aplicaban igual
        // a todos los vendedores. Se agrega para permitir "vendedor X + producto Z".
        Schema::table('bonus_reglas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('categoria_id')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE bonus_reglas MODIFY tipo_aplicacion ENUM('producto','categoria','producto_usuario') NOT NULL");
    }

    public function down(): void
    {
        DB::table('comision_reglas')->where('tipo_aplicacion', 'producto_usuario')->update(['tipo_aplicacion' => 'producto']);
        DB::statement("ALTER TABLE comision_reglas MODIFY tipo_aplicacion ENUM('usuario','categoria','producto') NOT NULL");

        DB::table('bonus_reglas')->where('tipo_aplicacion', 'producto_usuario')->update(['tipo_aplicacion' => 'producto']);
        DB::statement("ALTER TABLE bonus_reglas MODIFY tipo_aplicacion ENUM('producto','categoria') NOT NULL");

        Schema::table('bonus_reglas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
