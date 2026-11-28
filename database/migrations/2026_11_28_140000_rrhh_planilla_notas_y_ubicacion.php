<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recursos humanos: reglas de descuento y bonificación por trabajador (tardanzas, faltas, horas extra), horas extra
 * en cada asistencia, lugares de trabajo y marcaciones con selfie y ubicación, y evaluaciones con nota de 0 a 20.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->unsignedSmallInteger('tolerancia_min')->nullable();            // null = la general del sistema
            $table->decimal('horas_jornada', 4, 2)->default(8);                    // para el valor de la hora: sueldo / 30 / horas
            $table->string('tardanza_modo', 14)->default('proporcional');          // proporcional, por_minuto, por_tardanza, ninguno
            $table->decimal('tardanza_valor', 10, 2)->default(0);
            $table->string('falta_modo', 10)->default('dia');                      // dia, monto, ninguno
            $table->decimal('falta_valor', 10, 2)->default(0);
            $table->string('extra_modo', 10)->default('factor');                   // factor, por_hora, ninguno
            $table->decimal('extra_valor', 8, 2)->default(1.25);
            $table->unsignedSmallInteger('extra_minimo_min')->default(30);        // minutos después de la salida desde los que cuenta
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_extra')->default(0);
        });

        Schema::create('lugares_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('tipo', 12)->default('oficina');                        // oficina, tienda, obra, otro
            $table->string('direccion', 255)->nullable();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->unsignedSmallInteger('radio_m')->default(150);                 // distancia máxima para validar la asistencia
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('asistencia_marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->constrained('asistencias')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 8);                                             // entrada, salida
            $table->string('foto')->nullable();                                    // selfie, en el disco privado
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->unsignedInteger('precision_m')->nullable();
            $table->foreignId('lugar_trabajo_id')->nullable()->constrained('lugares_trabajo')->nullOnDelete();
            $table->unsignedInteger('distancia_m')->nullable();                    // al lugar de trabajo más cercano
            $table->string('ubicacion', 10)->default('sin_dato');                  // valida, fuera, sin_dato
            $table->string('revision', 10)->nullable();                            // aprobada, rechazada (la decide Administración)
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->timestamps();

            $table->index(['asistencia_id', 'tipo']);
        });

        Schema::create('evaluaciones_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('periodo', 40);                                         // «2026-11», «Segundo trimestre 2026»…
            $table->decimal('nota', 4, 2);                                         // 0 a 20
            $table->text('comentario')->nullable();
            $table->decimal('aumento', 10, 2)->default(0);                         // lo que Administración decide subir, a su criterio
            $table->decimal('sueldo_anterior', 10, 2)->nullable();
            $table->decimal('sueldo_nuevo', 10, 2)->nullable();                    // solo si el aumento se aplicó al sueldo básico
            $table->foreignId('evaluado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empleado_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_empleado');
        Schema::dropIfExists('asistencia_marcaciones');
        Schema::dropIfExists('lugares_trabajo');
        Schema::table('asistencias', fn (Blueprint $t) => $t->dropColumn('minutos_extra'));
        Schema::table('empleados', fn (Blueprint $t) => $t->dropColumn(['tolerancia_min', 'horas_jornada', 'tardanza_modo', 'tardanza_valor', 'falta_modo', 'falta_valor', 'extra_modo', 'extra_valor', 'extra_minimo_min']));
    }
};
