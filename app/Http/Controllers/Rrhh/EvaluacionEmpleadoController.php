<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\EvaluacionEmpleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notas de desempeño de 0 a 20. La nota ayuda a decidir un aumento, pero nunca lo calcula: Administración escribe
 * cuánto sube (si sube) y, si lo desea, lo aplica al sueldo básico en el mismo paso.
 */
class EvaluacionEmpleadoController extends Controller
{
    /** Todo el personal con su última nota, su promedio y cuántas evaluaciones tiene. */
    public function index()
    {
        $empleados = Empleado::where('estado', '!=', 'cesado')->with(['evaluaciones'])->orderBy('apellidos')->orderBy('nombres')->get();

        return view('rrhh.evaluaciones.index', ['empleados' => $empleados]);
    }

    public function show(Empleado $empleado)
    {
        return view('rrhh.evaluaciones.show', ['empleado' => $empleado, 'evaluaciones' => $empleado->evaluaciones()->with('evaluador')->get()]);
    }

    public function store(Request $request, Empleado $empleado)
    {
        $d = $request->validate([
            'periodo' => 'required|string|max:40',
            'nota' => 'required|numeric|min:0|max:20',
            'comentario' => 'nullable|string|max:2000',
            'aumento' => 'nullable|numeric|min:0|max:99999',
            'aplicar' => 'nullable|boolean',
        ], ['nota.min' => 'La nota va de 0 a 20.', 'nota.max' => 'La nota va de 0 a 20.', 'periodo.required' => 'Indique el periodo evaluado (por ejemplo 2026-11 o «Segundo trimestre»).']);

        DB::transaction(function () use ($empleado, $d, $request) {
            $aumento = round((float) ($d['aumento'] ?? 0), 2);
            $aplicar = $request->boolean('aplicar') && $aumento > 0;
            $anterior = (float) $empleado->sueldo_basico;

            EvaluacionEmpleado::create([
                'empleado_id' => $empleado->id, 'periodo' => trim($d['periodo']), 'nota' => round((float) $d['nota'], 2), 'comentario' => $d['comentario'] ?? null,
                'aumento' => $aumento, 'sueldo_anterior' => $aplicar ? $anterior : null, 'sueldo_nuevo' => $aplicar ? round($anterior + $aumento, 2) : null,
                'evaluado_por' => $request->user()->id,
            ]);
            if ($aplicar) {
                $empleado->update(['sueldo_basico' => round($anterior + $aumento, 2)]);
            }
        });

        return redirect()->route('rrhh.evaluaciones.show', $empleado)->with('success', 'Evaluación registrada.' . ($request->boolean('aplicar') && (float) ($d['aumento'] ?? 0) > 0 ? ' El aumento ya está en el sueldo básico.' : ''));
    }

    public function destroy(Empleado $empleado, EvaluacionEmpleado $evaluacion)
    {
        abort_unless($evaluacion->empleado_id === $empleado->id, 404);
        // Borrar la nota no deshace un aumento ya aplicado: ese cambio de sueldo se corrige en la ficha del personal
        $evaluacion->delete();

        return redirect()->route('rrhh.evaluaciones.show', $empleado)->with('success', 'Evaluación eliminada.');
    }
}
