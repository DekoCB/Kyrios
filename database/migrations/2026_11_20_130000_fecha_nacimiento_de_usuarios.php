<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('dni');
        });

        // Quien ya tiene ficha de colaborador con fecha de nacimiento la trae al usuario
        DB::statement('UPDATE users u JOIN empleados e ON e.user_id = u.id SET u.fecha_nacimiento = e.fecha_nacimiento WHERE e.fecha_nacimiento IS NOT NULL AND u.fecha_nacimiento IS NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fecha_nacimiento');
        });
    }
};
