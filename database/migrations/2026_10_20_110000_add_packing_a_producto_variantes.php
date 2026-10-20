<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hoja "Packing list" del Excel de Kyrios: precio de fábrica (EUR) y datos de embalaje por variante. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->decimal('precio_fabrica', 12, 2)->nullable()->comment('EUR, precio de fábrica del Excel');
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('volumen', 10, 4)->nullable();
            $table->string('embalado', 60)->nullable();
            $table->string('medida_embalaje', 80)->nullable();
            $table->unsignedInteger('cantidad_por_caja')->nullable();
            $table->text('codigos_relacionados')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn(['precio_fabrica', 'peso', 'volumen', 'embalado', 'medida_embalaje', 'cantidad_por_caja', 'codigos_relacionados']);
        });
    }
};
