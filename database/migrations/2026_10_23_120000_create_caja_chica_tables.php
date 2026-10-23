<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas_chicas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->enum('tipo', ['general', 'asignada'])->default('asignada');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['activa', 'cerrada'])->default('activa');
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();

            $table->index(['responsable_id', 'estado']);
        });

        Schema::create('caja_chica_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_chica_id')->constrained('cajas_chicas')->cascadeOnDelete();
            $table->enum('tipo', ['ingreso', 'gasto']);
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('categoria', 40)->nullable();
            $table->string('concepto', 255);
            $table->string('emisor', 160)->nullable();
            $table->string('tipo_comprobante', 30)->nullable();
            $table->string('numero_comprobante', 40)->nullable();
            $table->enum('estado', ['pendiente', 'aprobado', 'observado', 'rechazado'])->default('aprobado');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->string('motivo_revision', 255)->nullable();
            $table->timestamps();

            $table->index(['caja_chica_id', 'fecha']);
            $table->index(['tipo_comprobante', 'numero_comprobante']);
        });

        Schema::create('caja_chica_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('caja_chica_movimientos')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre_original', 200);
            $table->string('mime', 80);
            $table->unsignedInteger('tamano')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_chica_adjuntos');
        Schema::dropIfExists('caja_chica_movimientos');
        Schema::dropIfExists('cajas_chicas');
    }
};
