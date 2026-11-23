<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos_libro', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('temporada', 40);
            $table->unsignedSmallInteger('anio');
            $table->string('estado', 12)->default('borrador');   // borrador, publicado, archivado
            $table->date('vigencia')->nullable();
            $table->string('token', 12)->unique();                // va en el enlace público
            $table->boolean('enlace_activo')->default(false);
            $table->timestamp('enlace_expira_at')->nullable();    // null = sin vencimiento
            $table->string('precio_enlace', 4)->default('none');  // none, pen, usd
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['anio', 'temporada']);
        });

        Schema::create('catalogo_libro_paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('catalogos_libro')->cascadeOnDelete();
            $table->unsignedInteger('orden');
            $table->string('tipo', 10);                           // seccion, uno, dos, cuatro
            $table->string('titulo', 60)->nullable();             // solo las portadas de sección
            $table->timestamps();

            $table->index(['catalogo_id', 'orden']);
        });

        Schema::create('catalogo_libro_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('catalogo_libro_paginas')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden')->default(0);
        });

        Schema::create('catalogo_libro_vistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('catalogos_libro')->cascadeOnDelete();
            $table->string('visitante', 40);                      // huella del navegador, sin guardar la IP
            $table->timestamp('created_at')->useCurrent();

            $table->index(['catalogo_id', 'visitante']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_libro_vistas');
        Schema::dropIfExists('catalogo_libro_items');
        Schema::dropIfExists('catalogo_libro_paginas');
        Schema::dropIfExists('catalogos_libro');
    }
};
