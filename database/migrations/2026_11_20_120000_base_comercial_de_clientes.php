<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Último ID de cliente que trae el Excel comercial (KYR912). */
    private const ULTIMO_ID_EXCEL = 912;

    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // true = base comercial (los del Excel y los que se registren en el sistema); false = solo la lista de difusión
            $table->boolean('en_base_comercial')->default(true)->after('codigo')->index();
        });

        // Contactos que solo existen en la lista de difusión de WhatsApp (solo nombre, importados el 29/10/2026)
        DB::table('clientes')
            ->whereNotNull('listas_difusion')
            ->whereNull('telefono')->whereNull('email')->whereNull('numero_documento')
            ->where('created_at', '>=', '2026-10-29 00:00:00')->where('created_at', '<', '2026-10-30 00:00:00')
            // quien ya tiene un ID del Excel (KYR001 a KYR912) es de la base comercial aunque también esté en las listas
            ->where(fn ($q) => $q->whereNull('codigo')->orWhereRaw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED) > ?', [self::ULTIMO_ID_EXCEL]))
            ->update(['en_base_comercial' => false]);

        // El ID solo existe para quien lo tenía en el Excel: KYR001 a KYR912. Los demás no llevan ID.
        DB::table('clientes')
            ->whereNotNull('codigo')
            ->whereRaw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED) > ?', [self::ULTIMO_ID_EXCEL])
            ->update(['codigo' => null]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('en_base_comercial');
        });
    }
};
