<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones con tres formatos de proforma: simple, por ambiente (baño, dormitorio, sala…) y por piso
 * (primer nivel, segundo nivel…, y dentro del piso también se puede indicar el ambiente).
 * La cotización también recibe el número oficial de proforma («IL-00001/26») y una referencia que se imprime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('formato', 10)->default('simple')->after('estado');
            $table->string('referencia', 160)->nullable()->after('formato');
            $table->string('proforma_numero', 20)->nullable()->after('referencia');
            $table->unsignedInteger('proforma_correlativo')->nullable()->after('proforma_numero');
            $table->index('proforma_correlativo');
        });

        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->string('piso', 60)->nullable()->after('descripcion');
            $table->string('ambiente', 60)->nullable()->after('piso');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', fn (Blueprint $table) => $table->dropColumn(['piso', 'ambiente']));
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex(['proforma_correlativo']);
            $table->dropColumn(['formato', 'referencia', 'proforma_numero', 'proforma_correlativo']);
        });
    }
};
