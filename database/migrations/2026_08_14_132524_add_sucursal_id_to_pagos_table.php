<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('cuenta_por_pagar_id')
                ->constrained('sucursales')->nullOnDelete();
            $table->foreignId('movimiento_caja_id')->nullable()->after('sucursal_id')
                ->constrained('movimientos_caja')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['movimiento_caja_id']);
            $table->dropColumn(['sucursal_id', 'movimiento_caja_id']);
        });
    }
};
