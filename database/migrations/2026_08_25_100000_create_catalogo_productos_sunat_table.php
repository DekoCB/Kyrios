<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalogo_productos_sunat', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique();
            $table->string('producto', 255);
            $table->string('codigo_clase', 8)->nullable();
            $table->string('clase', 255)->nullable();
            $table->string('codigo_familia', 8)->nullable();
            $table->string('familia', 255)->nullable();
            $table->string('codigo_segmento', 8)->nullable();
            $table->string('segmento', 255)->nullable();
            $table->timestamps();

            $table->index('producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_productos_sunat');
    }
};
