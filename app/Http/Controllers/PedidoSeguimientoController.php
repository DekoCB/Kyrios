<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoEtapa;
use Illuminate\Http\Request;

/** Seguimiento de una orden de compra: fecha prevista y cumplimiento de cada etapa. */
class PedidoSeguimientoController extends Controller
{
    private function etapaDe(Pedido $pedido, PedidoEtapa $etapa): PedidoEtapa
    {
        abort_unless($etapa->pedido_id === $pedido->id, 404);

        return $etapa;
    }

    /** Guarda las fechas previstas y notas de todas las etapas de una vez. */
    public function guardar(Request $request, Pedido $pedido)
    {
        $datos = $request->validate([
            'etapas'                  => 'required|array',
            'etapas.*.fecha_prevista' => 'nullable|date',
            'etapas.*.notas'          => 'nullable|string|max:255',
        ], ['etapas.*.fecha_prevista.date' => 'Hay una fecha prevista que no es válida.']);

        foreach ($pedido->etapas as $etapa) {
            if (isset($datos['etapas'][$etapa->id])) {
                $etapa->update([
                    'fecha_prevista' => $datos['etapas'][$etapa->id]['fecha_prevista'] ?? null,
                    'notas'          => $datos['etapas'][$etapa->id]['notas'] ?? null,
                ]);
            }
        }

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Seguimiento actualizado.');
    }

    public function completar(Request $request, Pedido $pedido, PedidoEtapa $etapa)
    {
        $etapa = $this->etapaDe($pedido, $etapa);
        $datos = $request->validate(['fecha_real' => 'nullable|date|before_or_equal:today']);

        $etapa->update(['fecha_real' => $datos['fecha_real'] ?? today(), 'completada_por' => $request->user()->id]);

        return redirect()->route('pedidos.show', $pedido)->with('success', "«{$etapa->nombre}» marcada como cumplida.");
    }

    public function reabrir(Pedido $pedido, PedidoEtapa $etapa)
    {
        $etapa = $this->etapaDe($pedido, $etapa);

        $etapa->update(['fecha_real' => null, 'completada_por' => null]);

        return redirect()->route('pedidos.show', $pedido)->with('success', "«{$etapa->nombre}» volvió a pendiente.");
    }
}
