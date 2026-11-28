<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\LugarTrabajo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lugares donde se puede marcar asistencia (oficina, tiendas, obras): contra ellos se valida la ubicación de cada marcación. */
class LugarTrabajoController extends Controller
{
    private function datos(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:120',
            'tipo' => ['required', Rule::in(array_keys(LugarTrabajo::TIPOS))],
            'direccion' => 'nullable|string|max:255',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'radio_m' => 'required|integer|min:20|max:5000',
        ], [
            'latitud.required' => 'Indique la latitud (puede copiarla de Google Maps o usar «Usar mi ubicación actual»).',
            'longitud.required' => 'Indique la longitud (puede copiarla de Google Maps o usar «Usar mi ubicación actual»).',
            'radio_m.min' => 'El radio mínimo es de 20 metros.', 'radio_m.max' => 'El radio máximo es de 5,000 metros.',
        ]);
    }

    public function index()
    {
        return view('rrhh.lugares.index', ['lugares' => LugarTrabajo::orderByDesc('activo')->orderBy('nombre')->get()]);
    }

    public function store(Request $request)
    {
        LugarTrabajo::create($this->datos($request) + ['activo' => true]);

        return redirect()->route('rrhh.lugares.index')->with('success', 'Lugar de trabajo agregado.');
    }

    public function update(Request $request, LugarTrabajo $lugar)
    {
        $lugar->update($this->datos($request) + ['activo' => $request->boolean('activo', $lugar->activo)]);

        return redirect()->route('rrhh.lugares.index')->with('success', 'Lugar de trabajo actualizado.');
    }

    public function destroy(LugarTrabajo $lugar)
    {
        // Las marcaciones ya hechas conservan su resultado; el lugar solo deja de existir para las siguientes
        $lugar->delete();

        return redirect()->route('rrhh.lugares.index')->with('success', 'Lugar de trabajo eliminado.');
    }
}
