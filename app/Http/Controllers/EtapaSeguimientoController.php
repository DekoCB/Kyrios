<?php

namespace App\Http\Controllers;

use App\Models\EtapaSeguimiento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de etapas por las que pasa una orden de compra. Lo edita Administración;
 * lo que cambie aplica a las órdenes nuevas (las ya iniciadas conservan sus etapas).
 */
class EtapaSeguimientoController extends Controller
{
    private function reglas(): array
    {
        return [
            'nombre'      => 'required|string|min:2|max:80',
            'tipo_compra' => ['required', Rule::in(array_keys(EtapaSeguimiento::TIPOS))],
            'orden'       => 'required|integer|min:0|max:9999',
        ];
    }

    public function index()
    {
        return view('seguimiento.etapas', [
            'etapas' => EtapaSeguimiento::orderBy('orden')->orderBy('id')->get(),
            'tipos'  => EtapaSeguimiento::TIPOS,
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());

        EtapaSeguimiento::create($datos + ['activo' => true]);

        return redirect()->route('seguimiento.etapas.index')->with('success', 'Etapa agregada. Aplica a las órdenes de compra que se creen desde ahora.');
    }

    public function update(Request $request, EtapaSeguimiento $etapa)
    {
        $datos = $request->validate($this->reglas());

        $etapa->update($datos + ['activo' => $request->boolean('activo')]);

        return redirect()->route('seguimiento.etapas.index')->with('success', 'Etapa actualizada.');
    }

    public function destroy(EtapaSeguimiento $etapa)
    {
        $etapa->delete();

        return redirect()->route('seguimiento.etapas.index')->with('success', 'Etapa eliminada. Las órdenes ya iniciadas conservan la suya.');
    }
}
