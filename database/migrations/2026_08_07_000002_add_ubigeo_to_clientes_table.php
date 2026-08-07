<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Código INEI de 6 dígitos: es lo único que SUNAT exige realmente para
        // el punto de llegada de una guía (distrito/provincia/departamento son
        // solo texto, no viajan en el XML). Sin esto, ubigeo_llegada quedaba
        // vacío salvo que el usuario lo tipeara a mano.
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('ubigeo', 6)->nullable()->after('departamento');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('ubigeo');
        });
    }
};
