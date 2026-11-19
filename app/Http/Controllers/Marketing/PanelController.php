<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campana;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Interaccion;
use App\Models\Marketing\Publicacion;
use App\Services\MarketingEstadisticas;

/** Panel de control de Marketing: redes, interacciones, publicaciones y campañas de un vistazo. */
class PanelController extends Controller
{
    public function index(MarketingEstadisticas $est)
    {
        $cuentas = Cuenta::activas()->orderBy('red')->orderBy('nombre')->get();
        $mes = $est->porCuenta(now()->startOfMonth(), now()->endOfMonth());

        return view('marketing.panel', [
            'cuentas'              => $cuentas,
            'seguidoresTotales'    => $cuentas->sum(fn ($c) => (int) $c->seguidores_actuales),
            'mes'                  => $est->totales($mes),
            'interaccionesPend'    => Interaccion::where('estado', 'pendiente')->count(),
            'misInteracciones'     => Interaccion::where('estado', 'pendiente')->where('asignado_a', auth()->id())->count(),
            'antiguas'             => Interaccion::where('estado', 'pendiente')->where('recibida_en', '<', now()->subDay())->count(),
            'porAprobar'           => Publicacion::where('estado', 'pendiente')->count(),
            'proximas'             => Publicacion::whereIn('estado', ['aprobada', 'programada'])->where('programada_para', '>=', now())->orderBy('programada_para')->limit(6)->get(),
            'atrasadas'            => Publicacion::whereIn('estado', ['aprobada', 'programada'])->where('programada_para', '<', now())->count(),
            'campanasActivas'      => Campana::where('estado', 'activa')->get(),
            'publicadasMes'        => Publicacion::where('estado', 'publicada')->whereBetween('publicada_en', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ]);
    }
}
