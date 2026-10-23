<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kyrios no maneja IMEI ni productos por número de serie: todo el inventario es por cantidad.
 * Se eliminan las tablas y columnas de IMEI heredadas de Adivon.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['detalle_ventas', 'movimientos_inventario'] as $tabla) {
            if (Schema::hasColumn($tabla, 'imei_id')) {
                Schema::table($tabla, function (Blueprint $t) use ($tabla) {
                    $t->dropForeign($tabla . '_imei_id_foreign');
                    $t->dropColumn('imei_id');
                });
            }
        }

        Schema::dropIfExists('traslado_imeis');
        Schema::dropIfExists('imeis');

        DB::statement("UPDATE productos SET tipo_inventario = 'cantidad' WHERE tipo_inventario <> 'cantidad'");
        DB::statement("ALTER TABLE productos MODIFY tipo_inventario ENUM('cantidad') NOT NULL DEFAULT 'cantidad'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE productos MODIFY tipo_inventario ENUM('cantidad','serie') NOT NULL DEFAULT 'cantidad'");
        // Las tablas de IMEI no se recrean: eran datos de otro rubro.
    }
};
