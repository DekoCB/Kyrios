<?php

namespace App\Http\Controllers;

use App\Models\TipoCambio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TipoCambioAdminController extends Controller
{
    public function index()
    {
        return view('tipo-cambio.index', [
            'vigente'   => TipoCambio::vigente(),
            'actual'    => TipoCambio::actual(),
            'historial' => TipoCambio::with('usuario')->orderByDesc('fecha')->orderByDesc('id')->limit(60)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'fecha'  => 'required|date|before_or_equal:today',
            'compra' => 'required|numeric|min:0.5|max:20',
            'venta'  => 'required|numeric|min:0.5|max:20|gte:compra',
        ], ['venta.gte' => 'El tipo de cambio de venta no puede ser menor que el de compra.']);

        TipoCambio::updateOrCreate(['fecha' => $datos['fecha']], $datos + ['fuente' => 'manual', 'user_id' => $request->user()->id]);

        return redirect()->route('tipo-cambio.index')->with('success', 'Tipo de cambio actualizado.');
    }

    /** Trae el tipo de cambio del día publicado por SUNAT. */
    public function sincronizar(Request $request)
    {
        $compra = $venta = 0.0;
        try {
            $r = Http::timeout(8)->get('https://api.apis.net.pe/v1/tipo-cambio-sunat');
            if ($r->successful()) {
                $compra = (float) ($r->json('compra') ?? 0);
                $venta = (float) ($r->json('venta') ?? 0);
            }
        } catch (\Throwable) {
            // sin conexión: se avisa abajo
        }

        if ($compra <= 0 || $venta <= 0) {
            return redirect()->route('tipo-cambio.index')->with('error', 'No se pudo consultar a SUNAT. Ingresa el tipo de cambio manualmente.');
        }

        TipoCambio::updateOrCreate(['fecha' => today()->toDateString()], ['compra' => $compra, 'venta' => $venta, 'fuente' => 'sunat', 'user_id' => $request->user()->id]);

        return redirect()->route('tipo-cambio.index')->with('success', "Tipo de cambio de SUNAT: compra {$compra}, venta {$venta}.");
    }
}
