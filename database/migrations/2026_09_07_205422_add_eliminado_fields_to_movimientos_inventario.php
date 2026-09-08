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
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->timestamp('eliminado_en')->nullable()->after('estado');
            $table->foreignId('eliminado_por')->nullable()->after('eliminado_en')
                ->constrained('users')->nullOnDelete();
            $table->string('motivo_eliminacion', 500)->nullable()->after('eliminado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eliminado_por');
            $table->dropColumn(['eliminado_en', 'motivo_eliminacion']);
        });
    }
};
