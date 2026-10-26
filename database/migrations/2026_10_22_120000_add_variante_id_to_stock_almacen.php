<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock por variante y almacén.
 *
 * Antes: una fila por (producto, almacén). Con variantes (acabado, tamaño…) no se podía saber cuántas
 * unidades de cada variante había en cada local. Ahora: una fila por (producto, variante, almacén);
 * los productos sin variantes siguen usando una fila con variante_id NULL.
 *
 * El stock total de un producto en un almacén es la SUMA de sus filas, así que las consultas que ya
 * sumaban por producto siguen siendo correctas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_almacen', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->after('producto_id')
                ->constrained('producto_variantes')->cascadeOnDelete();
            // Los NULL no cuentan como iguales en un índice único: esta columna los convierte en 0.
            // Virtual (no almacenada): MySQL 8 no admite una columna almacenada sobre una FK con ON DELETE CASCADE.
            $table->unsignedBigInteger('variante_key')->virtualAs('COALESCE(variante_id, 0)');
        });

        Schema::table('stock_almacen', function (Blueprint $table) {
            // La FK de producto usa el índice único viejo: se deja el índice simple y se reemplaza el único
            $table->dropUnique('stock_almacen_producto_id_almacen_id_unique');
            $table->unique(['producto_id', 'variante_key', 'almacen_id'], 'stock_almacen_producto_variante_almacen_unique');
            $table->index(['variante_id', 'almacen_id'], 'stock_almacen_variante_almacen_index');
        });

        // Datos existentes: el stock de un producto con UNA sola variante activa pasa a esa variante.
        // Con varias variantes no se puede saber el reparto: se deja a nivel producto y debe regularizarse
        // con un conteo (php artisan stock:reconciliar --variantes lo informa).
        DB::statement('
            UPDATE stock_almacen sa
            JOIN (
                SELECT producto_id, MIN(id) AS variante_id
                FROM producto_variantes
                WHERE estado = "activo"
                GROUP BY producto_id
                HAVING COUNT(*) = 1
            ) v ON v.producto_id = sa.producto_id
            SET sa.variante_id = v.variante_id
            WHERE sa.variante_id IS NULL
        ');
    }

    public function down(): void
    {
        // Vuelve a una fila por (producto, almacén): se suman las filas por variante
        DB::statement('
            CREATE TEMPORARY TABLE tmp_stock AS
            SELECT producto_id, almacen_id, SUM(cantidad) AS cantidad, MIN(created_at) AS created_at
            FROM stock_almacen GROUP BY producto_id, almacen_id
        ');
        DB::table('stock_almacen')->truncate();
        DB::statement('INSERT INTO stock_almacen (producto_id, almacen_id, cantidad, created_at, updated_at)
            SELECT producto_id, almacen_id, cantidad, created_at, NOW() FROM tmp_stock');

        // La FK de variante_id usa su índice: primero se quita la FK, después los índices y las columnas
        Schema::table('stock_almacen', function (Blueprint $table) {
            $table->dropForeign(['variante_id']);
        });
        Schema::table('stock_almacen', function (Blueprint $table) {
            $table->dropUnique('stock_almacen_producto_variante_almacen_unique');
            $table->dropIndex('stock_almacen_variante_almacen_index');
            $table->dropColumn(['variante_key', 'variante_id']);
            $table->unique(['producto_id', 'almacen_id'], 'stock_almacen_producto_id_almacen_id_unique');
        });
    }
};
