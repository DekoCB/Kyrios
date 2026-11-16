<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Huella del archivo original: las fotos se reducen al cargarlas, así que ya no basta comparar lo guardado
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->string('hash_origen', 32)->nullable()->after('ruta')->index();
        });
    }

    public function down(): void
    {
        Schema::table('producto_imagenes', fn (Blueprint $table) => $table->dropColumn('hash_origen'));
    }
};
