<?php

if (!function_exists('precio_dual')) {
    /**
     * Precio que se muestra en dólares (principal) y se puede alternar a soles con el selector de moneda.
     * $monto está expresado en $moneda (USD o PEN); el tipo de cambio es el vigente.
     */
    function precio_dual(float|int|string|null $monto, ?string $moneda = 'PEN'): \Illuminate\Support\HtmlString
    {
        $tc = \App\Models\TipoCambio::actual();
        $usd = \App\Support\Moneda::aDolares((float) $monto, $moneda, $tc);
        $pen = \App\Support\Moneda::aSoles((float) $monto, $moneda, $tc);

        return new \Illuminate\Support\HtmlString(
            '<span class="precio-dual" data-usd="' . number_format($usd, 2, '.', '') . '" data-pen="' . number_format($pen, 2, '.', '') . '">US$ ' . number_format($usd, 2) . '</span>'
        );
    }
}

if (!function_exists('montoEnLetras')) {
    /**
     * Convierte un monto numérico a texto en español (formato SUNAT).
     * Ej: 1234.56 → "MIL DOSCIENTOS TREINTA Y CUATRO CON 56/100 SOLES"
     */
    function montoEnLetras(float $monto, string $moneda = 'SOLES'): string
    {
        $monto    = round($monto, 2);
        $entero   = (int) floor($monto);
        $decimal  = (int) round(($monto - $entero) * 100);

        return strtoupper(numeroEnLetras($entero)) . ' CON ' . str_pad($decimal, 2, '0', STR_PAD_LEFT) . '/100 ' . $moneda;
    }
}

if (!function_exists('numeroEnLetras')) {
    function numeroEnLetras(int $n): string
    {
        if ($n === 0) return 'CERO';

        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                     'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS',
                     'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
        $decenas  = ['', '', 'VEINTI', 'TREINTA', 'CUARENTA', 'CINCUENTA',
                     'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                     'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        if ($n < 0)    return 'MENOS ' . numeroEnLetras(abs($n));
        if ($n <= 20)  return $unidades[$n];
        if ($n < 30)   return 'VEINTI' . $unidades[$n - 20];
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            return $decenas[$d] . ($u > 0 ? ' Y ' . $unidades[$u] : '');
        }
        if ($n === 100) return 'CIEN';
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;
            return $centenas[$c] . ($r > 0 ? ' ' . numeroEnLetras($r) : '');
        }
        if ($n < 2000)  return 'MIL'          . ($n % 1000 > 0 ? ' ' . numeroEnLetras($n % 1000) : '');
        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            return numeroEnLetras($miles) . ' MIL' . ($resto > 0 ? ' ' . numeroEnLetras($resto) : '');
        }
        if ($n < 2000000)  return 'UN MILLÓN'    . ($n % 1000000 > 0 ? ' ' . numeroEnLetras($n % 1000000) : '');
        if ($n < 1000000000) {
            $mill = intdiv($n, 1000000);
            $resto = $n % 1000000;
            return numeroEnLetras($mill) . ' MILLONES' . ($resto > 0 ? ' ' . numeroEnLetras($resto) : '');
        }
        return (string) $n;
    }
}
