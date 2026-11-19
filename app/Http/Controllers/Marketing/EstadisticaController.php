<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Cuenta;
use App\Services\MarketingEstadisticas;
use Illuminate\Http\Request;

/** Estadísticas y rendimiento de las cuentas: seguidores, crecimiento, alcance, interacciones y engagement. */
class EstadisticaController extends Controller
{
    public function index(Request $request, MarketingEstadisticas $est)
    {
        [$desde, $hasta, $etiqueta, $tipo] = $est->periodo($request);
        $red = $request->filled('red') ? $request->red : null;
        $cuentaId = $request->filled('cuenta') ? (int) $request->cuenta : null;

        $filas = $est->porCuenta($desde, $hasta, $red, $cuentaId);
        $totales = $est->totales($filas);

        $anterior = $anteriorTotales = null;
        if ($request->boolean('comparar')) {
            [$d2, $h2] = $est->periodoAnterior($desde, $hasta, $tipo);
            $anterior = ['desde' => $d2, 'hasta' => $h2];
            $anteriorTotales = $est->totales($est->porCuenta($d2, $h2, $red, $cuentaId));
        }

        return view('marketing.estadisticas.index', [
            'etiqueta'        => $etiqueta,
            'tipo'            => $tipo,
            'desde'           => $desde,
            'hasta'           => $hasta,
            'filas'           => $filas,
            'totales'         => $totales,
            'anterior'        => $anterior,
            'anteriorTotales' => $anteriorTotales,
            'est'             => $est,
            'serie'           => $est->serieSeguidores($desde, $hasta, $red, $cuentaId),
            'mejores'         => $est->mejoresPublicaciones($desde, $hasta, 5, $red),
            'cuentas'         => Cuenta::activas()->orderBy('red')->get(['id', 'red', 'nombre']),
        ]);
    }
}
