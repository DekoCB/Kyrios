<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // ID de cliente correlativo (KYR001, KYR002…)
            $table->string('codigo', 12)->nullable()->unique()->after('id');
            // El documento principal sigue siendo numero_documento; aquí va el otro (DNI del titular o RUC del negocio)
            $table->string('dni_complementario', 8)->nullable()->after('numero_documento');
            $table->string('ruc_complementario', 11)->nullable()->after('dni_complementario');
        });

        $n = 0;
        DB::table('clientes')->orderBy('id')->select('id')->chunkById(500, function ($lote) use (&$n) {
            foreach ($lote as $c) {
                DB::table('clientes')->where('id', $c->id)->update(['codigo' => 'KYR' . str_pad((string) ++$n, 3, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'dni_complementario', 'ruc_complementario']);
        });
    }
};
