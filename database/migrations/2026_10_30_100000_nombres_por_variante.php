<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada fila del Excel (variante) puede traer su propio nombre de fábrica y nombre K ("Cuadro" -> Calma, Cálido, Centro);
 * el producto solo guardaba el de la primera fila y los demás se perdían.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->string('nombre_fabrica', 150)->nullable()->after('codigo_excel');
            $table->string('nombre_kyrios', 150)->nullable()->after('nombre_fabrica');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn(['nombre_fabrica', 'nombre_kyrios']);
        });
    }
};
