<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clientes: usuario de WhatsApp (junto al celular), edad (junto a la fecha de nacimiento) y varias redes sociales con ícono. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('telefono');
            $table->unsignedSmallInteger('edad')->nullable()->after('fecha_cumpleanos');
            $table->json('redes')->nullable()->after('redes_sociales');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'edad', 'redes']);
        });
    }
};
