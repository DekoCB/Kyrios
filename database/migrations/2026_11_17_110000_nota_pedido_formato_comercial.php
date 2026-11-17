<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota de pedido con la estructura del formato comercial (Excel): moneda, forma de pago, condición, tipo de
 * comprobante, transporte, lugar de entrega, referencia y contacto; y en el detalle, código y unidad.
 * El formato no lleva precios ni importes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_pedido', function (Blueprint $table) {
            $table->string('moneda', 3)->default('USD')->after('estado');
            $table->string('forma_pago', 80)->nullable()->after('moneda');
            $table->string('condicion', 80)->nullable()->after('forma_pago');
            $table->string('tipo_comprobante', 30)->nullable()->after('condicion');
            $table->string('transporte', 120)->nullable()->after('tipo_comprobante');
            $table->string('lugar_entrega', 255)->nullable()->after('transporte');
            $table->string('referencia', 160)->nullable()->after('lugar_entrega');
            $table->string('contacto', 120)->nullable()->after('referencia');
        });

        Schema::table('nota_pedido_detalles', function (Blueprint $table) {
            $table->string('codigo', 60)->nullable()->after('variante_id');
            $table->string('unidad', 20)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('nota_pedido_detalles', fn (Blueprint $table) => $table->dropColumn(['codigo', 'unidad']));
        Schema::table('notas_pedido', fn (Blueprint $table) => $table->dropColumn(['moneda', 'forma_pago', 'condicion', 'tipo_comprobante', 'transporte', 'lugar_entrega', 'referencia', 'contacto']));
    }
};
