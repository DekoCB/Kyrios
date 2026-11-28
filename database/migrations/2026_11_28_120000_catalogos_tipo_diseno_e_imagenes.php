<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos: tipo (técnico o brochure), diseño configurable (tipografía, colores, portada, características) y, por producto,
 * qué fotos usar, su tamaño y qué características mostrar.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('catalogos_libro', function (Blueprint $table) {
            $table->string('tipo', 10)->default('tecnico')->after('nombre');   // tecnico, brochure
            $table->json('diseno')->nullable()->after('precio_enlace');
        });

        Schema::table('catalogo_libro_items', function (Blueprint $table) {
            $table->json('imagenes')->nullable();                  // ids de producto_imagenes, en el orden en que se muestran
            $table->string('tamano', 10)->nullable();              // chico, medio, grande, completo
            $table->json('caracteristicas')->nullable();           // null = las del diseño del catálogo
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_libro_items', function (Blueprint $table) {
            $table->dropColumn(['imagenes', 'tamano', 'caracteristicas']);
        });
        Schema::table('catalogos_libro', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'diseno']);
        });
    }
};
