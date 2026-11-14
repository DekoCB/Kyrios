<?php

namespace App\Support;

use App\Models\TipoCambio;

/** Conversión entre dólares y soles con el tipo de cambio vigente. */
class Moneda
{
    public const USD = 'USD';
    public const PEN = 'PEN';

    public static function aSoles(float $monto, ?string $moneda, ?float $tc = null): float
    {
        return $moneda === self::USD ? round($monto * ($tc ?? TipoCambio::actual()), 2) : $monto;
    }

    public static function aDolares(float $monto, ?string $moneda, ?float $tc = null): float
    {
        return $moneda === self::USD ? $monto : round($monto / ($tc ?? TipoCambio::actual()), 2);
    }
}
