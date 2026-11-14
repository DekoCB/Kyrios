<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            // Usuario del sistema que corresponde a este empleado (para marcar su propia asistencia)
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            // Datos personales
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('dni', 12)->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email_personal', 150)->nullable();
            $table->string('contacto_emergencia', 120)->nullable();
            $table->string('telefono_emergencia', 20)->nullable();
            $table->string('parentesco_emergencia', 40)->nullable();

            // Datos laborales
            $table->string('cargo', 120);
            $table->string('area', 80)->nullable();
            $table->string('tipo_contrato', 30)->default('indefinido');
            $table->date('fecha_ingreso');
            $table->date('fecha_fin_contrato')->nullable();
            $table->date('fecha_cese')->nullable();
            $table->string('motivo_cese', 255)->nullable();
            $table->decimal('sueldo_basico', 10, 2)->nullable();
            $table->time('hora_entrada')->default('08:30:00');
            $table->time('hora_salida')->default('18:00:00');
            $table->string('estado', 12)->default('activo'); // activo | licencia | cesado

            // Datos de pago y previsión
            $table->string('sistema_pension', 10)->nullable(); // AFP | ONP
            $table->string('afp_nombre', 40)->nullable();
            $table->string('cuspp', 20)->nullable();
            $table->string('banco', 40)->nullable();
            $table->string('numero_cuenta', 40)->nullable();

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['estado', 'area']);
        });

        Schema::create('empleado_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 30); // contrato, dni, cv, certificado, otro
            $table->string('nombre', 160);
            $table->string('ruta');
            $table->string('mime', 80)->nullable();
            $table->date('vence')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            // puntual | tardanza | falta | permiso | vacaciones | descanso_medico | licencia
            $table->string('estado', 20)->default('puntual');
            $table->unsignedSmallInteger('minutos_tarde')->default(0);
            $table->string('origen', 10)->default('manual'); // manual | web
            $table->string('observaciones', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha']);
            $table->index('fecha');
        });

        Schema::create('solicitudes_rrhh', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 20); // vacaciones | permiso | licencia | descanso_medico
            $table->date('desde');
            $table->date('hasta');
            $table->string('motivo', 255)->nullable();
            $table->string('estado', 12)->default('pendiente'); // pendiente | aprobada | rechazada
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->string('motivo_rechazo', 255)->nullable();
            $table->timestamps();

            $table->index(['estado', 'desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_rrhh');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('empleado_documentos');
        Schema::dropIfExists('empleados');
    }
};
