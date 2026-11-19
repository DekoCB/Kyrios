<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campana;
use App\Support\Marketing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Campañas publicitarias: presupuesto, inversión y resultados (impresiones, alcance, clics, CPC, conversiones y ROAS). */
class CampanaController extends Controller
{
    private function reglas(): array
    {
        return [
            'nombre'       => 'required|string|max:150',
            'red'          => ['nullable', Rule::in(array_keys(Marketing::REDES))],
            'objetivo'     => 'nullable|string|max:120',
            'estado'       => ['required', Rule::in(array_keys(Campana::ESTADOS))],
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'moneda'       => ['required', Rule::in(array_keys(Campana::MONEDAS))],
            'presupuesto'  => 'required|numeric|min:0|max:999999999',
            'inversion'    => 'nullable|numeric|min:0|max:999999999',
            'impresiones'  => 'nullable|integer|min:0|max:4000000000',
            'alcance'      => 'nullable|integer|min:0|max:4000000000',
            'clics'        => 'nullable|integer|min:0|max:4000000000',
            'conversiones' => 'nullable|integer|min:0|max:4000000000',
            'ingresos'     => 'nullable|numeric|min:0|max:999999999',
            'notas'        => 'nullable|string|max:3000',
        ];
    }

    public function index(Request $request)
    {
        $campanas = Campana::withCount('publicaciones')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('red'), fn ($q) => $q->where('red', $request->red))
            ->orderByRaw("FIELD(estado, 'activa', 'pausada', 'planificada', 'finalizada')")->orderByDesc('fecha_inicio')->get();

        // Los totales se separan por moneda: no se mezclan soles con dólares
        $porMoneda = $campanas->groupBy('moneda')->map(fn ($c) => [
            'presupuesto' => $c->sum('presupuesto'),
            'inversion'   => $c->sum('inversion'),
            'clics'       => $c->sum('clics'),
            'ingresos'    => $c->whereNotNull('ingresos')->sum('ingresos'),
        ]);

        return view('marketing.campanas.index', compact('campanas', 'porMoneda'));
    }

    public function create()
    {
        return view('marketing.campanas.form', ['campana' => new Campana(['estado' => 'planificada', 'moneda' => 'PEN'])]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        $datos['inversion'] = $datos['inversion'] ?? 0;
        $campana = Campana::create($datos + ['creado_por' => auth()->id()]);

        return redirect()->route('marketing.campanas.edit', $campana)->with('success', 'Campaña registrada.');
    }

    public function edit(Campana $campana)
    {
        return view('marketing.campanas.form', ['campana' => $campana->load('publicaciones')]);
    }

    public function update(Request $request, Campana $campana)
    {
        $datos = $request->validate($this->reglas());
        $datos['inversion'] = $datos['inversion'] ?? 0;
        $campana->update($datos);

        return back()->with('success', 'Campaña guardada.');
    }

    public function destroy(Campana $campana)
    {
        $campana->delete(); // sus publicaciones se conservan sin campaña

        return redirect()->route('marketing.campanas.index')->with('success', 'Campaña eliminada.');
    }
}
