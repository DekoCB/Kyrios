<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('compra', 8, 4);
            $table->decimal('venta', 8, 4);
            $table->string('fuente', 12)->default('manual'); // manual | sunat
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Moneda en que está expresado cada precio. Lo ya existente queda en soles salvo lo importado del Excel de Kyrios (USD).
        Schema::table('producto_precios', function (Blueprint $table) {
            $table->string('moneda', 3)->default('PEN')->after('precio_compra');
        });
        DB::table('producto_precios')->where('observaciones', 'like', 'Importado del Excel%')->update(['moneda' => 'USD']);
    }

    public function down(): void
    {
        Schema::table('producto_precios', fn (Blueprint $table) => $table->dropColumn('moneda'));
        Schema::dropIfExists('tipos_cambio');
    }
};
