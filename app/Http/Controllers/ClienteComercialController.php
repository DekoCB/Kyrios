<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteProyecto;
use App\Models\ClienteSeguimiento;
use Illuminate\Http\Request;

/** Proyectos y seguimiento comercial de un cliente, cargados a mano desde su ficha. */
class ClienteComercialController extends Controller
{
    private function datosProyecto(Request $request): array
    {
        return $request->validate([
            'codigo' => 'nullable|string|max:40',
            'nombre' => 'required|string|max:190',
            'persona_a_cargo' => 'nullable|string|max:80',
            'prioridad' => 'nullable|in:Alta,Media,Baja',
            'fecha_recepcion' => 'nullable|date',
            'fecha_entrega_aprox' => 'nullable|date',
            'fecha_entrega_real' => 'nullable|date',
            'moneda' => 'required|in:USD,PEN',
            'monto_presupuestado' => 'nullable|numeric|min:0|max:9999999999',
            'monto_vendido' => 'nullable|numeric|min:0|max:9999999999',
            'resultado' => 'nullable|in:' . implode(',', array_keys(ClienteProyecto::RESULTADOS)),
            'seguimiento' => 'nullable|string|max:2000',
        ]);
    }

    public function proyectoStore(Request $request, Cliente $cliente)
    {
        $cliente->proyectos()->create($this->datosProyecto($request));

        return redirect()->to(route('clientes.show', $cliente) . '#proyectos')->with('success', 'Proyecto agregado.');
    }

    public function proyectoUpdate(Request $request, Cliente $cliente, ClienteProyecto $proyecto)
    {
        abort_unless($proyecto->cliente_id === $cliente->id, 404);
        $proyecto->update($this->datosProyecto($request));

        return redirect()->to(route('clientes.show', $cliente) . '#proyectos')->with('success', 'Proyecto actualizado.');
    }

    public function proyectoDestroy(Cliente $cliente, ClienteProyecto $proyecto)
    {
        abort_unless($proyecto->cliente_id === $cliente->id, 404);
        $proyecto->delete();

        return redirect()->to(route('clientes.show', $cliente) . '#proyectos')->with('success', 'Proyecto eliminado.');
    }

    private function datosSeguimiento(Request $request): array
    {
        return $request->validate([
            'fecha' => 'nullable|date',
            'atendido_por' => 'nullable|string|max:80',
            'tipo' => 'nullable|string|max:40',
            'situacion' => 'nullable|string|max:60',
            'busca' => 'nullable|string|max:255',
            'ultimo_estado' => 'nullable|string|max:2000',
            'probabilidad' => 'nullable|integer|min:0|max:100',
            'moneda' => 'required|in:USD,PEN',
            'monto_presupuestado' => 'nullable|numeric|min:0|max:9999999999',
            'monto_comprado' => 'nullable|numeric|min:0|max:9999999999',
            'medio_contacto' => 'nullable|string|max:120',
            'observaciones' => 'nullable|string|max:2000',
        ]);
    }

    public function seguimientoStore(Request $request, Cliente $cliente)
    {
        $datos = $this->datosSeguimiento($request);
        $datos['fecha'] = $datos['fecha'] ?? now()->toDateString();
        $datos['atendido_por'] = $datos['atendido_por'] ?? $request->user()->name;
        $cliente->seguimientos()->create($datos);

        return redirect()->to(route('clientes.show', $cliente) . '#seguimiento')->with('success', 'Seguimiento agregado.');
    }

    public function seguimientoUpdate(Request $request, Cliente $cliente, ClienteSeguimiento $seguimiento)
    {
        abort_unless($seguimiento->cliente_id === $cliente->id, 404);
        $seguimiento->update($this->datosSeguimiento($request));

        return redirect()->to(route('clientes.show', $cliente) . '#seguimiento')->with('success', 'Seguimiento actualizado.');
    }

    public function seguimientoDestroy(Cliente $cliente, ClienteSeguimiento $seguimiento)
    {
        abort_unless($seguimiento->cliente_id === $cliente->id, 404);
        $seguimiento->delete();

        return redirect()->to(route('clientes.show', $cliente) . '#seguimiento')->with('success', 'Seguimiento eliminado.');
    }
}
