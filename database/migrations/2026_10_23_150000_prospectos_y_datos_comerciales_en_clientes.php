<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prospectos (clientes sin documento todavía) y datos comerciales de la base de contactos:
 * segmento, rubro, cargo, empresa, cumpleaños, redes, comisión, asesor y verificación SUNAT/RENIEC.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Documento opcional: MySQL permite varios NULL en el índice único.
        DB::statement("ALTER TABLE clientes MODIFY tipo_documento ENUM('DNI','RUC','CE') NULL DEFAULT NULL");
        DB::statement('ALTER TABLE clientes MODIFY numero_documento VARCHAR(11) NULL');

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('segmento', 20)->nullable()->after('estado')->comment('ARQ, ING, DIS, DEC, PN, PJ, CF…');
            $table->string('especialidad', 150)->nullable()->after('segmento');
            $table->string('cargo', 120)->nullable()->after('contacto');
            $table->string('empresa', 190)->nullable()->after('cargo')->comment('Empresa donde trabaja (cuando el cliente es una persona)');
            $table->date('fecha_cumpleanos')->nullable()->after('empresa');
            $table->string('telefono_fijo', 30)->nullable()->after('telefono');
            $table->string('anexo', 15)->nullable()->after('telefono_fijo');
            $table->string('email_empresa', 255)->nullable()->after('email');
            $table->text('redes_sociales')->nullable()->after('email_empresa');
            $table->decimal('comision', 5, 2)->nullable()->after('redes_sociales')->comment('% de comisión pactado (aliados)');
            $table->text('preferencias')->nullable()->after('comision')->comment('Preferencias de producto');
            $table->string('asesor', 80)->nullable()->after('preferencias')->comment('Quién lo registró / atiende');
            $table->string('origen', 150)->nullable()->after('asesor')->comment('Medio de contacto o referido por');
            $table->string('verificacion', 20)->nullable()->after('origen')->comment('ok, discrepancia, no_encontrado');
            $table->timestamp('verificado_at')->nullable()->after('verificacion');

            $table->index('segmento');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['segmento']);
            $table->dropColumn([
                'segmento', 'especialidad', 'cargo', 'empresa', 'fecha_cumpleanos', 'telefono_fijo', 'anexo',
                'email_empresa', 'redes_sociales', 'comision', 'preferencias', 'asesor', 'origen', 'verificacion', 'verificado_at',
            ]);
        });

        DB::statement("UPDATE clientes SET tipo_documento = 'DNI' WHERE tipo_documento IS NULL");
        DB::statement("UPDATE clientes SET numero_documento = CONCAT('P', id) WHERE numero_documento IS NULL");
        DB::statement("ALTER TABLE clientes MODIFY tipo_documento ENUM('DNI','RUC','CE') NOT NULL DEFAULT 'DNI'");
        DB::statement('ALTER TABLE clientes MODIFY numero_documento VARCHAR(11) NOT NULL');
    }
};
