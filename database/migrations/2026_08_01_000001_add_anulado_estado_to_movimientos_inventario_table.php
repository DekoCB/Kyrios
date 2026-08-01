<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TrasladoService::anularTraslado() y las vistas de traslados ya escriben/leen
        // el estado 'anulado', pero el enum nunca lo incluyó: anular un traslado
        // fallaba con "Data truncated for column 'estado'".
        DB::statement("ALTER TABLE movimientos_inventario MODIFY estado ENUM('completado','pendiente','confirmado','cancelado','anulado') NOT NULL DEFAULT 'completado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY estado ENUM('completado','pendiente','confirmado','cancelado') NOT NULL DEFAULT 'completado'");
    }
};
