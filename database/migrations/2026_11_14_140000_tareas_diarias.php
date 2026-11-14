<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tablero de tareas diarias del equipo (una columna por persona y por día)
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();
            $table->foreignId('asignado_a')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->string('prioridad', 10)->default('media'); // urgente | alta | media | baja
            $table->string('estado', 12)->default('pendiente'); // pendiente | en_progreso | hecha
            $table->timestamp('completada_en')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['fecha', 'asignado_a']);
            $table->index(['estado', 'fecha']);
        });

        Schema::create('tarea_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->string('texto', 200);
            $table->boolean('hecho')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_items');
        Schema::dropIfExists('tareas');
    }
};
