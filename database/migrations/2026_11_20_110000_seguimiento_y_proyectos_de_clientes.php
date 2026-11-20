<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('ultima_atencion_por', 80)->nullable()->after('asesor');
            $table->date('ultima_atencion_en')->nullable()->after('ultima_atencion_por');
        });

        // Seguimiento comercial: lo que pidió el cliente, qué se le ofreció y cómo va
        Schema::create('cliente_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedInteger('fila_excel')->nullable();
            $table->date('fecha')->nullable();
            $table->string('atendido_por', 80)->nullable();
            $table->string('tipo', 40)->nullable();          // Proyecto, Puntual - Decorativa, Puntual - Técnica, Casacor
            $table->string('situacion', 60)->nullable();     // En seguimiento, En proceso, Ganado, Perdido…
            $table->string('busca', 255)->nullable();        // qué busca (luminarias decorativas, cinta LED…)
            $table->text('ultimo_estado')->nullable();       // qué pasó la última vez
            $table->unsignedTinyInteger('nivel')->nullable(); // 1 a 4, del Excel
            $table->unsignedTinyInteger('probabilidad')->nullable(); // % de venta
            $table->char('moneda', 3)->default('USD');
            $table->decimal('monto_presupuestado', 12, 2)->nullable();
            $table->decimal('monto_comprado', 12, 2)->nullable();
            $table->string('resumen_visita', 60)->nullable();
            $table->string('medio_contacto', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'situacion']);
        });

        Schema::create('cliente_proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedInteger('fila_excel')->nullable();
            $table->string('codigo', 40)->nullable();        // N° de cotización/proyecto del sistema anterior (IL-xxxxx)
            $table->string('nombre', 190)->nullable();
            $table->string('persona_a_cargo', 80)->nullable();
            $table->string('prioridad', 10)->nullable();     // Alta, Media, Baja
            $table->date('fecha_recepcion')->nullable();
            $table->date('fecha_entrega_aprox')->nullable();
            $table->date('fecha_entrega_real')->nullable();
            $table->string('revisiones', 40)->nullable();
            $table->char('moneda', 3)->default('USD');
            $table->decimal('monto_presupuestado', 12, 2)->nullable();
            $table->decimal('monto_vendido', 12, 2)->nullable();
            $table->string('resultado', 30)->nullable();     // Ganado, Perdido, Enviado, En proceso, Entregado
            $table->text('seguimiento')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'resultado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_proyectos');
        Schema::dropIfExists('cliente_seguimientos');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['ultima_atencion_por', 'ultima_atencion_en']);
        });
    }
};
