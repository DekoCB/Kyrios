<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codificación KYRIOS (PDF "Codificacion KYRIOS - PRODUCTOS") y atributos de luminarias.
 *
 * Código: KY-{tipo producto 2 letras}{tipo luminaria 2}{marca 2}-{correlativo 4}
 *  - Caso 1 (luminaria, emergencia, solares, recargables): tipo luminaria variable.
 *  - Caso 2 (lámpara, cinta LED, accesorio, fuente, ...): tipo luminaria siempre "00".
 *  - El correlativo se reinicia por cada combinación tipo + luminaria + marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_producto', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->string('nombre', 100)->unique();
            // con_luminaria = Caso 1 | sin_luminaria = Caso 2 (usa "00")
            $table->enum('caso_codigo', ['con_luminaria', 'sin_luminaria'])->default('sin_luminaria');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        // El código NO es único: el PDF repite códigos a propósito (mesa/escritorio = 10, estaca/baliza = 17)
        Schema::create('tipos_luminaria', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->index();
            $table->string('nombre', 100)->unique();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        Schema::create('correlativos_codigo', function (Blueprint $table) {
            $table->id();
            $table->string('prefijo', 20)->unique(); // ej. LU2502
            $table->unsignedInteger('ultimo')->default(0);
            $table->timestamps();
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->string('codigo', 2)->nullable()->unique()->after('nombre');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('tipo_producto_id')->nullable()->after('categoria_id')->constrained('tipos_producto')->nullOnDelete();
            $table->foreignId('tipo_luminaria_id')->nullable()->after('tipo_producto_id')->constrained('tipos_luminaria')->nullOnDelete();
            $table->string('nombre_fabrica', 150)->nullable()->after('nombre');
            $table->string('nombre_kyrios', 150)->nullable()->after('nombre_fabrica');
            $table->string('linea', 60)->nullable();
            $table->string('procedencia', 40)->nullable();
            $table->string('fuente_luz', 40)->nullable();
            $table->string('uso', 40)->nullable();
            $table->string('aplicacion_2', 60)->nullable();
            $table->string('material_1', 60)->nullable();
            $table->string('material_2', 60)->nullable();
            $table->string('nivel_potencia', 40)->nullable();
            $table->string('voltaje', 40)->nullable();
            $table->string('ip', 10)->nullable();
            $table->string('ik', 10)->nullable();
            $table->string('cri', 10)->nullable();
            $table->string('angulo_apertura', 60)->nullable();
            $table->string('driver', 30)->nullable();
            $table->boolean('regulable')->nullable();
            $table->string('protocolo_regulacion', 80)->nullable();
            $table->string('ficha_tecnica', 255)->nullable();
            $table->text('observaciones')->nullable();
            // Filtros por matriz "x" del Excel
            $table->json('estilos')->nullable();
            $table->json('tipos_instalacion')->nullable();
            $table->json('tipos_proyecto')->nullable();

            $table->index('fuente_luz');
            $table->index('uso');
            $table->index('linea');
        });

        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->string('codigo_kyrios', 20)->nullable()->unique()->after('sku');
            $table->unsignedInteger('correlativo')->nullable()->after('codigo_kyrios');
            $table->string('codigo_fabricante', 60)->nullable()->index()->after('correlativo');
            $table->string('codigo_excel', 40)->nullable()->index()->after('codigo_fabricante'); // 2° código (hoja original)
            $table->string('acabado_1', 60)->nullable()->index();
            $table->string('acabado_2', 60)->nullable();
            $table->string('tamano', 20)->nullable()->index();
            $table->decimal('diametro', 10, 2)->nullable();
            $table->decimal('alto', 10, 2)->nullable();
            $table->decimal('ancho', 10, 2)->nullable();
            $table->decimal('profundidad', 10, 2)->nullable();
            $table->decimal('alto_suspendido', 10, 2)->nullable();
            $table->decimal('diametro_agujero', 10, 2)->nullable();
            $table->string('socket', 30)->nullable()->index();
            $table->string('num_lamparas', 10)->nullable();
            $table->decimal('potencia_w', 10, 2)->nullable();
            $table->decimal('lumenes', 10, 2)->nullable();
            $table->unsignedInteger('temperatura_color')->nullable();
            $table->string('tonalidad_luz', 40)->nullable();
            $table->enum('estado_dano', ['ninguno', 'leve', 'moderado', 'grave'])->default('ninguno')->index();
            $table->text('observacion_dano')->nullable();
        });

        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->string('ruta', 255);
            $table->unsignedTinyInteger('orden'); // 1..6
            $table->boolean('es_principal')->default(false);
            $table->timestamps();

            $table->unique(['variante_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');

        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_kyrios', 'correlativo', 'codigo_fabricante', 'codigo_excel', 'acabado_1', 'acabado_2',
                'tamano', 'diametro', 'alto', 'ancho', 'profundidad', 'alto_suspendido', 'diametro_agujero',
                'socket', 'num_lamparas', 'potencia_w', 'lumenes', 'temperatura_color', 'tonalidad_luz',
                'estado_dano', 'observacion_dano',
            ]);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['tipo_producto_id']);
            $table->dropForeign(['tipo_luminaria_id']);
            $table->dropColumn([
                'tipo_producto_id', 'tipo_luminaria_id', 'nombre_fabrica', 'nombre_kyrios', 'linea', 'procedencia',
                'fuente_luz', 'uso', 'aplicacion_2', 'material_1', 'material_2', 'nivel_potencia', 'voltaje', 'ip',
                'ik', 'cri', 'angulo_apertura', 'driver', 'regulable', 'protocolo_regulacion', 'ficha_tecnica',
                'observaciones', 'estilos', 'tipos_instalacion', 'tipos_proyecto',
            ]);
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });

        Schema::dropIfExists('correlativos_codigo');
        Schema::dropIfExists('tipos_luminaria');
        Schema::dropIfExists('tipos_producto');
    }
};
