<?php

namespace App\Http\Controllers;

use App\Models\OpcionUbicacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Administra las opciones de Piso y Ambiente que se ofrecen en las cotizaciones. */
class OpcionUbicacionController extends Controller
{
    public function index()
    {
        $opciones = OpcionUbicacion::orderBy('tipo')->orderBy('orden')->orderBy('nombre')->get()->groupBy('tipo');

        return view('cotizaciones.opciones-ubicacion', compact('opciones'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo'   => ['required', Rule::in(array_keys(OpcionUbicacion::TIPOS))],
            'nombre' => ['required', 'string', 'max:60'],
        ]);
        $datos['nombre'] = trim($datos['nombre']);

        if (OpcionUbicacion::where('tipo', $datos['tipo'])->where('nombre', $datos['nombre'])->exists()) {
            return back()->withErrors(['nombre' => 'Esa opción ya existe.'])->withInput();
        }
        OpcionUbicacion::create($datos + ['orden' => (int) OpcionUbicacion::where('tipo', $datos['tipo'])->max('orden') + 1, 'activo' => true]);

        return back()->with('success', 'Opción agregada.');
    }

    public function update(Request $request, OpcionUbicacion $opcion)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'orden'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $nombre = trim($datos['nombre']);

        if (OpcionUbicacion::where('tipo', $opcion->tipo)->where('nombre', $nombre)->whereKeyNot($opcion->id)->exists()) {
            return back()->withErrors(['nombre' => 'Ya hay otra opción con ese nombre.']);
        }
        $opcion->update(['nombre' => $nombre, 'orden' => $datos['orden'] ?? $opcion->orden, 'activo' => $request->boolean('activo')]);

        return back()->with('success', 'Opción actualizada.');
    }

    /** Las cotizaciones ya hechas guardan el texto, así que borrar una opción no las afecta. */
    public function destroy(OpcionUbicacion $opcion)
    {
        $opcion->delete();

        return back()->with('success', 'Opción eliminada.');
    }
}
