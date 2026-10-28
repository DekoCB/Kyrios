<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La serie estándar de guías de remisión se generaba con 5 caracteres (T0101) y SUNAT exige 4 (T001):
 * el servicio de guías la rechaza con «Error inesperado». Se renombran las series ya creadas con ese formato.
 */
return new class extends Migration
{
    public function up(): void
    {
        $series = DB::table('series_comprobantes')
            ->where('tipo_comprobante', '09')
            ->whereRaw("serie REGEXP '^T[0-9]{2}01$'")
            ->get(['id', 'sucursal_id', 'serie']);

        foreach ($series as $s) {
            $nueva = 'T0' . substr($s->serie, 1, 2);

            $ocupada = DB::table('series_comprobantes')->where('sucursal_id', $s->sucursal_id)->where('serie', $nueva)->exists();
            if (!$ocupada) {
                DB::table('series_comprobantes')->where('id', $s->id)->update(['serie' => $nueva]);
            }
        }
    }

    public function down(): void
    {
        // No se revierte: la serie de 5 caracteres no es válida en SUNAT.
    }
};
