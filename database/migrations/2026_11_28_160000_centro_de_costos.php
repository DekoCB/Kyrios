<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de costos: el costo de cada producto vendido se guarda en la línea de venta (para que la utilidad no cambie
 * cuando cambia el costo promedio) y cada proveedor lleva su país y ciudad de origen (costos desde el sitio de proveniencia).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 4)->nullable();   // soles, sin IGV; null en las ventas anteriores a este cambio
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('pais', 60)->nullable();
            $table->string('ciudad_origen', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', fn (Blueprint $t) => $t->dropColumn(['pais', 'ciudad_origen']));
        Schema::table('detalle_ventas', fn (Blueprint $t) => $t->dropColumn('costo_unitario'));
    }
};
