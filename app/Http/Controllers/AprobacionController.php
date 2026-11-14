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

    /** Bandeja del administrador: productos e inventario. */
    public function index(Request $request)
    {
        return $this->bandeja($request, 'productos');
    }

    /** Bandeja del administrador: clientes. */
    public function clientes(Request $request)
    {
        return $this->bandeja($request, 'clientes');
    }

    private function bandeja(Request $request, string $tipo)
    {
        $estado = in_array($request->query('estado'), ['pendiente', 'aprobada', 'rechazada', 'todas'], true) ? $request->query('estado') : 'pendiente';
        $de = $tipo === 'clientes' ? 'deClientes' : 'deProductos';

        $consulta = Aprobacion::with(['solicitante', 'resolutor'])->{$de}()->latest();
        if ($estado !== 'todas') {
            $consulta->where('estado', $estado);
        }

        return view('aprobaciones.index', [
            'aprobaciones'        => $consulta->paginate(20)->withQueryString(),
            'estado'              => $estado,
            'tipo'                => $tipo,
            'pendientes'          => Aprobacion::pendientes()->{$de}()->count(),
            'pendientesProductos' => Aprobacion::pendientes()->deProductos()->count(),
            'pendientesClientes'  => Aprobacion::pendientes()->deClientes()->count(),
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
            return redirect()->route($aprobacion->ruta_bandeja)->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }

        return redirect()->route($aprobacion->ruta_bandeja)->with('success', "Aprobado y aplicado: {$aprobacion->titulo}");
    }

    public function rechazar(Request $request, Aprobacion $aprobacion)
    {
        $datos = $request->validate(['motivo' => 'required|string|min:3|max:500'], ['motivo.required' => 'Escribe el motivo del rechazo.']);

        try {
            $this->servicio->rechazar($aprobacion, $request->user(), $datos['motivo']);
        } catch (RuntimeException $e) {
            return redirect()->route($aprobacion->ruta_bandeja)->with('error', $e->getMessage());
        }

        return redirect()->route($aprobacion->ruta_bandeja)->with('success', "Rechazado: {$aprobacion->titulo}");
    }
}
