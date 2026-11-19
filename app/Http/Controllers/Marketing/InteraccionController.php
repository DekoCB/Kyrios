<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Interaccion;
use App\Models\Marketing\Publicacion;
use App\Models\Role;
use App\Models\User;
use App\Support\Marketing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Bandeja de entrada: comentarios y mensajes recibidos, quién los atiende y qué se respondió. */
class InteraccionController extends Controller
{
    private function equipo()
    {
        return User::where('estado', 'activo')->whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::MARKETING, Role::ADMINISTRACION]))->orderBy('name')->get(['id', 'name']);
    }

    public function index(Request $request)
    {
        $consulta = Interaccion::with(['asignado', 'cuenta'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('red'), fn ($q) => $q->where('red', $request->red))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->asignado === 'yo', fn ($q) => $q->where('asignado_a', auth()->id()))
            ->when($request->asignado === 'nadie', fn ($q) => $q->whereNull('asignado_a'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('autor', 'like', '%' . $request->q . '%')->orWhere('texto', 'like', '%' . $request->q . '%')));

        return view('marketing.interacciones.index', [
            'interacciones' => $consulta->orderByRaw("estado = 'pendiente' DESC")->orderByDesc('recibida_en')->paginate(20)->withQueryString(),
            'conteo'        => Interaccion::selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado'),
            'mias'          => Interaccion::where('estado', 'pendiente')->where('asignado_a', auth()->id())->count(),
            'cuentas'       => Cuenta::activas()->orderBy('red')->get(['id', 'red', 'nombre']),
            'equipo'        => $this->equipo(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'red'            => ['required', Rule::in(array_keys(Marketing::REDES))],
            'cuenta_id'      => 'nullable|exists:mkt_cuentas,id',
            'tipo'           => ['required', Rule::in(array_keys(Interaccion::TIPOS))],
            'autor'          => 'required|string|max:120',
            'texto'          => 'required|string|max:3000',
            'recibida_en'    => 'nullable|date|before_or_equal:now',
            'enlace'         => 'nullable|url|max:255',
            'asignado_a'     => 'nullable|exists:users,id',
            'publicacion_id' => 'nullable|exists:mkt_publicaciones,id',
        ]);
        $datos['recibida_en'] = $datos['recibida_en'] ?? now();
        $interaccion = Interaccion::create($datos + ['estado' => 'pendiente']);

        return redirect()->route('marketing.interacciones.show', $interaccion)->with('success', 'Interacción registrada.');
    }

    public function show(Interaccion $interaccion)
    {
        return view('marketing.interacciones.show', [
            'interaccion' => $interaccion->load(['asignado', 'respondedor', 'cuenta', 'publicacion']),
            'historial'   => Interaccion::where('autor', $interaccion->autor)->where('red', $interaccion->red)->where('id', '!=', $interaccion->id)->orderByDesc('recibida_en')->limit(10)->get(),
            'equipo'      => $this->equipo(),
            'publicaciones' => Publicacion::where('red', $interaccion->red)->orderByDesc('id')->limit(100)->get(['id', 'titulo']),
        ]);
    }

    public function asignar(Request $request, Interaccion $interaccion)
    {
        $datos = $request->validate(['asignado_a' => 'nullable|exists:users,id']);
        $interaccion->update(['asignado_a' => $datos['asignado_a'] ?? null]);

        return back()->with('success', $interaccion->asignado_a ? 'Responsable asignado.' : 'Se quitó el responsable.');
    }

    public function responder(Request $request, Interaccion $interaccion)
    {
        $datos = $request->validate(['respuesta' => 'required|string|max:3000'], ['respuesta.required' => 'Escribe la respuesta que diste.']);
        $interaccion->update($datos + ['estado' => 'atendida', 'respondida_por' => auth()->id(), 'respondida_en' => now()]);

        return back()->with('success', 'Respuesta registrada: la interacción quedó atendida.');
    }

    public function reabrir(Interaccion $interaccion)
    {
        $interaccion->update(['estado' => 'pendiente']);

        return back()->with('success', 'La interacción volvió a pendientes.');
    }

    public function destroy(Interaccion $interaccion)
    {
        $interaccion->delete();

        return redirect()->route('marketing.interacciones.index')->with('success', 'Interacción eliminada.');
    }
}
