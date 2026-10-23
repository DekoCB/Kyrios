<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de cotizaciones: documento propio (no es una venta). Se convierte a nota de venta, boleta o factura,
 * y recién ahí se descuenta stock y se cobra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();                 // CT01-00000001
            $table->foreignId('serie_comprobante_id')->nullable()->constrained('series_comprobantes')->nullOnDelete();
            $table->unsignedInteger('correlativo')->nullable();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();       // quien la elaboró
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete(); // de dónde saldría el stock
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            $table->date('fecha');
            $table->date('fecha_vencimiento');
            $table->unsignedSmallInteger('validez_dias')->default(7);

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('condiciones')->nullable();   // se imprime: forma de pago, entrega, garantía…
            $table->text('observaciones')->nullable(); // interna

            $table->enum('estado', ['pendiente', 'aceptada', 'rechazada', 'convertida', 'anulada'])->default('pendiente');
            $table->text('motivo_estado')->nullable();

            // Conversión
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('tipo_comprobante_convertido', 20)->nullable();
            $table->timestamp('convertida_en')->nullable();
            $table->foreignId('convertida_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['estado', 'fecha_vencimiento']);
            $table->index('fecha');
        });

        Schema::create('cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();

            // Lo que se imprimió, aunque después cambie el producto
            $table->string('codigo', 40)->nullable();
            $table->string('descripcion', 255);

            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 4);    // sin IGV
            $table->decimal('precio_con_igv', 12, 4);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('subtotal_con_igv', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalles');
        Schema::dropIfExists('cotizaciones');
    }
};
