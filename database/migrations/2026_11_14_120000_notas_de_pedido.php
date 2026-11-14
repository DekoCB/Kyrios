<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota de pedido: lo que almacén debe dejar preparado para entregar cuando una cotización se confirma
        Schema::create('notas_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'en_preparacion', 'lista', 'entregada', 'anulada'])->default('pendiente');
            $table->date('fecha_entrega')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lista_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'almacen_id']);
        });

        Schema::create('nota_pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_pedido_id')->constrained('notas_pedido')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->string('descripcion', 255);
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('preparado')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_pedido_detalles');
        Schema::dropIfExists('notas_pedido');
    }
};
