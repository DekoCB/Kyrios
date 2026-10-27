<?php

namespace App\Http\Controllers;

use App\Models\CuentaPorCobrar;
use App\Models\CuotaCobro;
use App\Models\PagoCredito;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorCobrarController extends Controller
{
    public function index(Request $request)
    {
        // Primero se actualizan los vencidos, para que la lista y los totales ya salgan al día.
        CuentaPorCobrar::where('estado', 'vigente')
            ->where('fecha_vencimiento_final', '<', now())
            ->update(['estado' => 'vencido']);

        $query = CuentaPorCobrar::with('cliente', 'venta', 'usuario')
            ->orderByRaw("FIELD(estado, 'vencido', 'vigente', 'pagado', 'anulado')")
            ->orderBy('fecha_vencimiento_final');

        // Filtros
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_inicio', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_vencimiento_final', '<=', $request->fecha_hasta);
        }

        $cuentas = $query->paginate(15)->withQueryString();

        // Estadísticas
        $stats = [
            'total_pendiente'  => CuentaPorCobrar::activas()->sum(DB::raw('monto_total - monto_pagado')),
            'total_vencido'    => CuentaPorCobrar::vencidas()->sum(DB::raw('monto_total - monto_pagado')),
            'cobrado_mes'      => PagoCredito::whereMonth('fecha_pago', now()->month)
                                    ->whereYear('fecha_pago', now()->year)
                                    ->sum('monto'),
            'por_vencer_7dias' => CuentaPorCobrar::porVencer(7)->count(),
            'cuotas_vencidas'  => CuotaCobro::vencidas()->count(),
        ];

        $clientes = Cliente::activos()->orderBy('nombre')->get(['id', 'nombre', 'numero_documento']);

        return view('ventas.cuentas-por-cobrar.index', compact('cuentas', 'stats', 'clientes'));
    }

    /** El detalle del crédito (cuotas, pagos y cobro) vive en la ficha de crédito de la venta. */
    public function show(CuentaPorCobrar $cuentaPorCobrar)
    {
        return redirect()->route('ventas.credito.show', $cuentaPorCobrar->venta_id);
    }
}
