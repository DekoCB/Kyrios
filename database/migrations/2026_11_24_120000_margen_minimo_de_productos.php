<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control de costos y márgenes: margen mínimo general (30 % al inicio) y margen mínimo propio de cada producto, en %
 * sobre el costo (la misma convención del módulo de precios). El del producto manda; vacío = usa el general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('margen_minimo', 6, 2)->nullable()->after('ultimo_costo_compra');
        });

        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 80)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
        DB::table('ajustes')->insert(['clave' => 'margen_minimo_general', 'valor' => '30', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('margen_minimo');
        });
    }
};
