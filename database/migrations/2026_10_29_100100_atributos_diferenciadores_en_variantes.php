<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atributos que diferencian una variante de otra: tipo de lámpara, ángulo de haz, protocolo de regulación,
 * eficiencia lumínica, garantía, vida útil, IP, CRI y otros (acabado y tonalidad de luz ya existían).
 * Las variantes existentes heredan como valor inicial lo que tenía su producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->string('tipo_lampara', 60)->nullable()->after('tonalidad_luz')->comment('LED, halógena, etc.');
            $table->string('angulo_haz', 40)->nullable()->after('tipo_lampara');
            $table->string('protocolo_regulacion', 120)->nullable()->after('angulo_haz');
            $table->decimal('eficiencia_luminica', 8, 2)->nullable()->after('protocolo_regulacion')->comment('lm/W');
            $table->unsignedSmallInteger('garantia_meses')->nullable()->after('eficiencia_luminica');
            $table->unsignedInteger('vida_util_horas')->nullable()->after('garantia_meses');
            $table->string('ip', 10)->nullable()->after('vida_util_horas');
            $table->string('cri', 10)->nullable()->after('ip');
            $table->text('otros')->nullable()->after('cri');
        });

        // Valor inicial desde el producto (UPDATE con JOIN: MariaDB y MySQL 8)
        DB::statement('
            UPDATE producto_variantes v
            JOIN productos p ON p.id = v.producto_id
            SET v.tipo_lampara = p.fuente_luz,
                v.ip = p.ip,
                v.cri = p.cri,
                v.angulo_haz = p.angulo_apertura,
                v.protocolo_regulacion = p.protocolo_regulacion
        ');
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn(['tipo_lampara', 'angulo_haz', 'protocolo_regulacion', 'eficiencia_luminica', 'garantia_meses', 'vida_util_horas', 'ip', 'cri', 'otros']);
        });
    }
};
