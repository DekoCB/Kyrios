<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aprobaciones')) {
            return;
        }

        Schema::create('aprobaciones', function (Blueprint $table) {
            $table->id();
            $table->string('accion', 40);              // producto_nuevo, compra, traslado…
            $table->string('ruta', 120);               // nombre de la ruta que se ejecuta al aprobar
            $table->json('parametros')->nullable();    // ids de la ruta (producto, variante…)
            $table->json('datos')->nullable();         // lo que envió el usuario
            $table->json('archivos')->nullable();      // archivos subidos, guardados aparte
            $table->string('titulo', 200);
            $table->text('resumen')->nullable();
            $table->json('detalle')->nullable();       // líneas legibles para quien aprueba
            $table->foreignId('solicitante_id')->constrained('users')->cascadeOnDelete();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('ultimo_error')->nullable();  // si al aprobar falló, queda pendiente con el motivo
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('solicitante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprobaciones');
    }
};
