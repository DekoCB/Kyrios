<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Número de proforma con el formato oficial IL-00001/26; se asigna al emitir la proforma y no cambia
            $table->unsignedInteger('proforma_correlativo')->nullable()->after('correlativo');
            $table->string('proforma_numero', 20)->nullable()->unique()->after('proforma_correlativo');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['proforma_numero', 'proforma_correlativo']);
        });
    }
};
