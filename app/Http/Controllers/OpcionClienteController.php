<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\OpcionCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Administra las listas de opciones de los filtros de clientes. */
class OpcionClienteController extends Controller
{
    public function index()
    {
        $opciones = OpcionCliente::orderBy('orden')->orderBy('id')->get()->groupBy('grupo');
        $usos = [];
        foreach (array_keys(OpcionCliente::GRUPOS) as $grupo) {
            $usos[$grupo] = Cliente::whereNotNull($grupo)->selectRaw("$grupo as nombre, count(*) as total")->groupBy($grupo)->pluck('total', 'nombre')->all();
        }

        return view('clientes.opciones', ['grupos' => OpcionCliente::GRUPOS, 'opciones' => $opciones, 'usos' => $usos]);
    }

    private function reglaNombre(string $grupo, ?OpcionCliente $actual = null): array
    {
        return ['required', 'string', 'max:80', Rule::unique('opciones_cliente', 'nombre')->where('grupo', $grupo)->ignore($actual?->id)];
    }

    public function store(Request $request)
    {
        $grupo = $request->validate(['grupo' => ['required', Rule::in(array_keys(OpcionCliente::GRUPOS))]])['grupo'];
        $datos = $request->validate(['nombre' => $this->reglaNombre($grupo)], ['nombre.unique' => 'Esa opción ya existe en la lista.']);

        OpcionCliente::create([
            'grupo'  => $grupo,
            'nombre' => trim($datos['nombre']),
            'orden'  => (int) OpcionCliente::where('grupo', $grupo)->max('orden') + 1,
        ]);

        return back()->with('success', 'Opción agregada.');
    }

    /** Renombra (y actualiza a los clientes que la tenían) o activa/desactiva. */
    public function update(Request $request, OpcionCliente $opcion)
    {
        $request->validate([
            'nombre' => $this->reglaNombre($opcion->grupo, $opcion),
            'activo' => 'sometimes|boolean',
        ], ['nombre.unique' => 'Esa opción ya existe en la lista.']);
        $nuevo = trim($request->input('nombre', $opcion->nombre));

        DB::transaction(function () use ($opcion, $nuevo, $request) {
            if ($nuevo !== $opcion->nombre) {
                Cliente::where($opcion->grupo, $opcion->nombre)->update([$opcion->grupo => $nuevo]);
            }
            $opcion->update(['nombre' => $nuevo, 'activo' => $request->has('activo') ? $request->boolean('activo') : $opcion->activo]);
        });

        return back()->with('success', 'Opción actualizada.');
    }

    /** Con clientes asignados no se elimina: se puede desactivar para que ya no se ofrezca. */
    public function destroy(OpcionCliente $opcion)
    {
        $enUso = $opcion->enUso();
        if ($enUso > 0) {
            return back()->with('error', "«{$opcion->nombre}» está asignada a {$enUso} cliente(s). Desactívala para que ya no se ofrezca, o cámbiala en esos clientes.");
        }
        $opcion->delete();

        return back()->with('success', 'Opción eliminada.');
    }
}
