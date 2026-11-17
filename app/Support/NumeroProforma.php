<?php

namespace App\Support;

use App\Models\Cotizacion;
use App\Models\Venta;

/**
 * Numeración oficial de las proformas, «IL-00001/26» (correlativo y año de dos dígitos). Es una sola serie para las
 * proformas de cotizaciones y de ventas, así que nunca se repite un número.
 */
class NumeroProforma
{
    /** @return array{0:int,1:string} [correlativo, número] */
    public static function siguiente(): array
    {
        $inicio = (int) config('kyrios.proforma.inicio', 1);
        $ultimo = max((int) Venta::max('proforma_correlativo'), (int) Cotizacion::max('proforma_correlativo'));
        $correlativo = max($ultimo + 1, $inicio);

        return [$correlativo, sprintf('IL-%05d/%s', $correlativo, now()->format('y'))];
    }
}
