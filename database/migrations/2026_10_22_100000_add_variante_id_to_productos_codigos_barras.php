<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada variante (acabado, tamaño…) viene en su propia caja con su propio código de barras:
 * el código escaneado debe identificar la variante exacta, no solo el producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_codigos_barras', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->after('producto_id')
                ->constrained('producto_variantes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos_codigos_barras', function (Blueprint $table) {
            $table->dropForeign(['variante_id']);
            $table->dropColumn('variante_id');
        });
    }
};
