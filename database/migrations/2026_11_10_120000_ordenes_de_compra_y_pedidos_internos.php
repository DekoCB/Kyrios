<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orden de compra: el pedido a proveedor pasa a ser nacional o internacional, con moneda y totales
        if (!Schema::hasColumn('pedidos', 'tipo_compra')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->enum('tipo_compra', ['nacional', 'internacional'])->default('nacional')->after('proveedor_id');
                $table->enum('moneda', ['PEN', 'USD'])->default('PEN')->after('tipo_compra');
                $table->decimal('tipo_cambio', 8, 3)->nullable()->after('moneda');
                $table->string('incoterm', 10)->nullable()->after('tipo_cambio');
                $table->string('condicion_pago', 120)->nullable()->after('incoterm');
                $table->string('lugar_entrega', 255)->nullable()->after('condicion_pago');
                $table->decimal('subtotal', 14, 2)->default(0)->after('observaciones');
                $table->decimal('igv', 14, 2)->default(0)->after('subtotal');
                $table->decimal('total', 14, 2)->default(0)->after('igv');
                $table->index('tipo_compra');
            });
        }

        if (!Schema::hasTable('pedidos_internos')) {
            Schema::create('pedidos_internos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20)->unique();
                $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('almacen_origen_id')->constrained('almacenes')->restrictOnDelete();   // quien debe atender
                $table->foreignId('almacen_destino_id')->constrained('almacenes')->restrictOnDelete();  // quien lo necesita
                $table->string('area', 100)->nullable();
                $table->date('fecha');
                $table->date('fecha_requerida')->nullable();
                $table->enum('estado', ['pendiente', 'atendido', 'cancelado'])->default('pendiente');
                $table->text('observaciones')->nullable();
                $table->string('traslado_guia', 50)->nullable();          // guía del traslado con el que se atendió
                $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('atendido_at')->nullable();
                $table->timestamps();

                $table->index(['estado', 'fecha']);
            });
        }

        if (!Schema::hasTable('pedido_interno_detalles')) {
            Schema::create('pedido_interno_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_interno_id')->constrained('pedidos_internos')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
                $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->restrictOnDelete();
                $table->unsignedInteger('cantidad');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_interno_detalles');
        Schema::dropIfExists('pedidos_internos');

        if (Schema::hasColumn('pedidos', 'tipo_compra')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropIndex(['tipo_compra']);
                $table->dropColumn(['tipo_compra', 'moneda', 'tipo_cambio', 'incoterm', 'condicion_pago', 'lugar_entrega', 'subtotal', 'igv', 'total']);
            });
        }
    }
};
