<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_chica_movimientos', function (Blueprint $table) {
            // Gastos repartidos en varios conceptos con un mismo comprobante comparten el grupo
            $table->string('grupo', 36)->nullable()->after('caja_chica_id')->index();
            // Recibo de pago emitido a partir del gasto (RP-0001…)
            $table->string('recibo_numero', 20)->nullable()->unique()->after('motivo_revision');
            $table->timestamp('recibo_emitido_en')->nullable()->after('recibo_numero');
            $table->foreignId('recibo_emitido_por')->nullable()->after('recibo_emitido_en')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('caja_chica_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recibo_emitido_por');
            $table->dropColumn(['recibo_emitido_en', 'recibo_numero', 'grupo']);
        });
    }
};
