<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha del colaborador (formato oficial de 13 páginas): datos personales ampliados, datos para nóminas, educación,
 * experiencia, familia, referencias, cuenta y pensiones, croquis/referencia domiciliaria y declaraciones juradas.
 * Las listas (idiomas, experiencia, familiares…) se guardan como JSON dentro de la ficha.
 * También el código del empleado en el huellero (reloj biométrico) para importar sus marcaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // Datos personales
            $table->string('nacionalidad', 40)->nullable()->after('dni');
            $table->string('grado_instruccion', 40)->nullable()->after('estado_civil');
            $table->string('lugar_nacimiento', 120)->nullable()->after('fecha_nacimiento');
            $table->string('tipo_sangre', 5)->nullable();
            $table->string('domicilio_alterno', 255)->nullable();
            $table->string('referencia_domiciliaria', 255)->nullable();
            $table->string('jefe_contrata', 120)->nullable();
            $table->string('foto', 255)->nullable();
            $table->string('codigo_huellero', 20)->nullable()->unique();

            // Datos para nóminas (los llena Recursos Humanos)
            $table->string('centro_costos', 60)->nullable();
            $table->string('periodo_prueba', 60)->nullable();
            $table->string('tipo_remuneracion', 20)->nullable();   // empleado | operario
            $table->string('modalidad_pago', 20)->nullable();      // tiempo_parcial | tiempo_completo | ejecutivo | practicante | otro
            $table->string('modalidad_pago_otro', 60)->nullable();
            $table->string('local_trabajo', 20)->nullable();       // san_isidro | la_molina | la_victoria | otros
            $table->string('local_otro', 60)->nullable();
            $table->string('calificacion', 40)->nullable();

            // Postulación
            $table->string('puesto_solicitado', 120)->nullable();
            $table->date('fecha_disponibilidad')->nullable();
            $table->decimal('sueldo_minimo', 10, 2)->nullable();
            $table->boolean('viajaria_provincias')->nullable();
            $table->boolean('trabajo_antes_kyrios')->nullable();
            $table->string('conocidos_kyrios', 255)->nullable();
            $table->string('familiares_kyrios', 255)->nullable();

            // Educación y habilidades
            $table->json('educacion')->nullable();          // [{nivel, centro, inicio, fin}] superior, técnico, secundaria
            $table->string('profesion', 160)->nullable();
            $table->string('profesion_condicion', 20)->nullable(); // egresado | bachiller | titulado
            $table->json('idiomas')->nullable();            // [{idioma, habla, lee, escribe}]
            $table->json('software')->nullable();           // [{nombre, nivel}]
            $table->json('otros_estudios')->nullable();     // [texto]

            // Experiencia, familia y referencias
            $table->json('experiencia')->nullable();        // [{empresa, telefono, cargo, jefe, ingreso, salida, remuneracion, funciones, motivo_cese, consultar}]
            $table->json('dependientes')->nullable();       // [{nombres, parentesco, dni, nacimiento, centro, ocupacion}]
            $table->json('familiares')->nullable();         // [{nombres, parentesco, dni, nacimiento, telefono, centro, ocupacion, direccion, otros}]
            $table->json('referencias')->nullable();        // [{nombres, empresa, cargo, telefono, tiempo}]

            // Pago de haberes y pensiones
            $table->boolean('posee_cuenta_sueldo')->nullable();
            $table->string('moneda_pago', 3)->nullable();    // PEN | USD
            $table->string('pension_situacion', 20)->nullable(); // jubilado | afiliado_afp | afiliado_onp | desea_onp | desea_afp
            $table->string('pension_regimen', 40)->nullable();   // régimen del jubilado

            // Declaraciones juradas
            $table->json('declaraciones')->nullable();      // {clave: fecha de firma}
            $table->boolean('vida_ley_extrabajador')->nullable();
            $table->json('vida_ley')->nullable();           // {compania, poliza, vigencia, remuneracion, beneficiarios:[{nombres, parentesco}]}
            $table->json('enfermedades')->nullable();       // [texto]
            $table->boolean('sin_enfermedades')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropUnique(['codigo_huellero']);
            $table->dropColumn([
                'nacionalidad', 'grado_instruccion', 'lugar_nacimiento', 'tipo_sangre', 'domicilio_alterno', 'referencia_domiciliaria', 'jefe_contrata', 'foto', 'codigo_huellero',
                'centro_costos', 'periodo_prueba', 'tipo_remuneracion', 'modalidad_pago', 'modalidad_pago_otro', 'local_trabajo', 'local_otro', 'calificacion',
                'puesto_solicitado', 'fecha_disponibilidad', 'sueldo_minimo', 'viajaria_provincias', 'trabajo_antes_kyrios', 'conocidos_kyrios', 'familiares_kyrios',
                'educacion', 'profesion', 'profesion_condicion', 'idiomas', 'software', 'otros_estudios', 'experiencia', 'dependientes', 'familiares', 'referencias',
                'posee_cuenta_sueldo', 'moneda_pago', 'pension_situacion', 'pension_regimen', 'declaraciones', 'vida_ley_extrabajador', 'vida_ley', 'enfermedades', 'sin_enfermedades',
            ]);
        });
    }
};
