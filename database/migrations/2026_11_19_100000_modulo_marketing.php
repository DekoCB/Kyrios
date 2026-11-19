<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Marketing y medios: rol Marketing, cuentas de redes con sus métricas, publicaciones con calendario y
 * aprobación, biblioteca multimedia, bandeja de interacciones, competencia y campañas publicitarias.
 * Los datos de las redes se cargan a mano o se importan (por ejemplo desde Metricool); no hay conexión por API todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['nombre' => Role::MARKETING], ['descripcion' => Role::DESCRIPCIONES[Role::MARKETING]]);

        Schema::create('mkt_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('red', 20);
            $table->string('nombre', 120);
            $table->string('usuario', 120)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('estado', 20)->default('conectada');
            $table->string('origen', 40)->default('Metricool');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('mkt_metricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('mkt_cuentas')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('seguidores')->nullable();
            $table->unsignedInteger('alcance')->nullable();
            $table->unsignedInteger('impresiones')->nullable();
            $table->unsignedInteger('interacciones')->nullable();
            $table->unsignedInteger('reproducciones')->nullable();
            $table->unsignedInteger('clics')->nullable();
            $table->unsignedInteger('publicaciones')->nullable();
            $table->timestamps();
            $table->unique(['cuenta_id', 'fecha']);
        });

        Schema::create('mkt_campanas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('red', 20)->nullable();
            $table->string('objetivo', 120)->nullable();
            $table->string('estado', 20)->default('planificada');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('presupuesto', 12, 2)->default(0);
            $table->decimal('inversion', 12, 2)->default(0);
            $table->unsignedInteger('impresiones')->nullable();
            $table->unsignedInteger('alcance')->nullable();
            $table->unsignedInteger('clics')->nullable();
            $table->unsignedInteger('conversiones')->nullable();
            $table->decimal('ingresos', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mkt_medios', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->string('tipo', 20);
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->string('etiquetas', 255)->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mkt_publicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->text('texto')->nullable();
            $table->string('red', 20);
            $table->string('tipo', 20)->default('publicacion');
            $table->string('estado', 20)->default('pendiente');
            $table->dateTime('programada_para')->nullable();
            $table->dateTime('publicada_en')->nullable();
            $table->string('enlace', 255)->nullable();
            $table->foreignId('campana_id')->nullable()->constrained('mkt_campanas')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprobada_en')->nullable();
            $table->string('motivo_rechazo', 255)->nullable();
            $table->unsignedInteger('alcance')->nullable();
            $table->unsignedInteger('impresiones')->nullable();
            $table->unsignedInteger('interacciones')->nullable();
            $table->unsignedInteger('reproducciones')->nullable();
            $table->unsignedInteger('clics')->nullable();
            $table->timestamps();
            $table->index(['estado', 'programada_para']);
        });

        Schema::create('mkt_publicacion_medio', function (Blueprint $table) {
            $table->foreignId('publicacion_id')->constrained('mkt_publicaciones')->cascadeOnDelete();
            $table->foreignId('medio_id')->constrained('mkt_medios')->cascadeOnDelete();
            $table->primary(['publicacion_id', 'medio_id']);
        });

        Schema::create('mkt_interacciones', function (Blueprint $table) {
            $table->id();
            $table->string('red', 20);
            $table->foreignId('cuenta_id')->nullable()->constrained('mkt_cuentas')->nullOnDelete();
            $table->string('tipo', 20)->default('comentario');
            $table->string('autor', 120);
            $table->text('texto');
            $table->dateTime('recibida_en');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('respuesta')->nullable();
            $table->foreignId('respondida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('respondida_en')->nullable();
            $table->foreignId('publicacion_id')->nullable()->constrained('mkt_publicaciones')->nullOnDelete();
            $table->string('enlace', 255)->nullable();
            $table->timestamps();
            $table->index(['estado', 'recibida_en']);
        });

        Schema::create('mkt_competidores', function (Blueprint $table) {
            $table->id();
            $table->string('red', 20);
            $table->string('nombre', 120);
            $table->string('usuario', 120)->nullable();
            $table->string('url', 255)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('mkt_competidor_metricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competidor_id')->constrained('mkt_competidores')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('seguidores')->nullable();
            $table->unsignedInteger('publicaciones')->nullable();
            $table->unsignedInteger('interacciones')->nullable();
            $table->string('destacada', 255)->nullable();
            $table->string('enlace_destacada', 255)->nullable();
            $table->timestamps();
            $table->unique(['competidor_id', 'fecha']);
        });
    }

    public function down(): void
    {
        foreach (['mkt_competidor_metricas', 'mkt_competidores', 'mkt_interacciones', 'mkt_publicacion_medio', 'mkt_publicaciones', 'mkt_medios', 'mkt_campanas', 'mkt_metricas', 'mkt_cuentas'] as $t) {
            Schema::dropIfExists($t);
        }
        Role::where('nombre', Role::MARKETING)->delete();
    }
};
