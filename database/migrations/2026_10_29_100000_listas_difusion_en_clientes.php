<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas de difusión de WhatsApp: un cliente puede estar en varias a la vez
 * (por ejemplo arquitecta + mamá + mujer), así que no cabe en el campo único «segmento».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'listas_difusion')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->json('listas_difusion')->nullable()->after('segmento')
                    ->comment('Claves de las listas de difusión: arquitecto, mama, mujer…');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'listas_difusion')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('listas_difusion');
            });
        }
    }
};
