<?php

namespace App\Http\Controllers;

use App\Models\Aprobacion;
use App\Services\AprobacionService;
use Illuminate\Http\Request;
use RuntimeException;

class AprobacionController extends Controller
{
    public function __construct(private AprobacionService $servicio)
    {
    }

    /** Bandeja del administrador. */
    public function index(Request $request)
    {
        $estado = in_array($request->query('estado'), ['pendiente', 'aprobada', 'rechazada', 'todas'], true) ? $request->query('estado') : 'pendiente';

        $consulta = Aprobacion::with(['solicitante', 'resolutor'])->latest();
        if ($estado !== 'todas') {
            $consulta->where('estado', $estado);
        }

        return view('aprobaciones.index', [
            'aprobaciones' => $consulta->paginate(20)->withQueryString(),
            'estado'       => $estado,
            'pendientes'   => Aprobacion::pendientes()->count(),
        ]);
    }

    /** Las solicitudes que hizo el propio usuario. */
    public function mias(Request $request)
    {
        return view('aprobaciones.mias', [
            'aprobaciones' => Aprobacion::with('resolutor')->where('solicitante_id', $request->user()->id)->latest()->paginate(20),
        ]);
    }

    public function aprobar(Request $request, Aprobacion $aprobacion)
    {
        try {
            $this->servicio->aprobar($aprobacion, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('aprobaciones.index')->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }

        return redirect()->route('aprobaciones.index')->with('success', "Aprobado y aplicado: {$aprobacion->titulo}");
    }

    public function rechazar(Request $request, Aprobacion $aprobacion)
    {
        $datos = $request->validate(['motivo' => 'required|string|min:3|max:500'], ['motivo.required' => 'Escribe el motivo del rechazo.']);

        try {
            $this->servicio->rechazar($aprobacion, $request->user(), $datos['motivo']);
        } catch (RuntimeException $e) {
            return redirect()->route('aprobaciones.index')->with('error', $e->getMessage());
        }

        return redirect()->route('aprobaciones.index')->with('success', "Rechazado: {$aprobacion->titulo}");
    }
}
