<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\CuentaPorPagar;
use App\Models\Cuota;
use App\Models\Proveedor;
use App\Models\Pago;
use App\Models\Sucursal;
use App\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorPagarController extends Controller
{
    /**
     * Constructor - Solo Admin y Almacenero pueden ver cuentas
     */
    public function __construct()
    {
        $this->middleware('role:Administrador,Almacenero');
    }

    /**
     * Dashboard de cuentas por pagar
     */
    public function index(Request $request)
    {
        $query = CuentaPorPagar::with(['proveedor', 'compra'])
            ->orderBy('fecha_vencimiento');

        // Filtros
        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->fecha_hasta);
        }

        $cuentas = $query->paginate(20);

        // Estadísticas
        $stats = [
            'total_pendiente' => CuentaPorPagar::whereIn('estado', ['pendiente', 'parcial'])->sum('monto_total'),
            'total_vencido' => CuentaPorPagar::where('estado', 'vencido')->sum('monto_total'),
            'total_pagado' => CuentaPorPagar::where('estado', 'pagado')->sum('monto_total'),
            'proximos_7_dias' => CuentaPorPagar::whereBetween('fecha_vencimiento', [now(), now()->addDays(7)])
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->sum('monto_total'),
        ];

        $proveedores = Proveedor::where('estado', 'activo')->orderBy('razon_social')->get();

        return view('compras.cuentas-por-pagar.index', compact('cuentas', 'stats', 'proveedores'));
    }

    /**
     * Mostrar detalle de una cuenta
     */
    public function show(CuentaPorPagar $cuenta)
    {
        $cuenta->load(['proveedor', 'compra', 'pagos.usuario', 'pagos.sucursal', 'cuotas.pago']);
        $sucursales = Sucursal::where('estado', 'activo')->orderBy('nombre')->get();
        return view('compras.cuentas-por-pagar.show', compact('cuenta', 'sucursales'));
    }

    /**
     * Registrar el egreso en la caja abierta de la sucursal elegida.
     *
     * @return Caja|null la caja donde quedó registrado, o null si esa sucursal
     *                    no tiene ninguna caja abierta (el llamador debe avisar
     *                    al usuario para que lo registre manualmente).
     */
    private function registrarEgresoCajaProveedor(CuentaPorPagar $cuenta, int $sucursalId, float $monto, string $concepto): ?\App\Models\MovimientoCaja
    {
        $caja = Caja::where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->first();

        if (!$caja) {
            return null;
        }

        return app(CajaService::class)->registrarMovimiento(
            $caja->id,
            'egreso',
            $monto,
            $concepto,
            null,
            $cuenta->compra_id
        );
    }

    /**
     * Generar cuadro de cuotas automático
     */
    public function generarCuotas(Request $request, CuentaPorPagar $cuenta)
    {
        $request->validate([
            'num_cuotas' => 'required|integer|min:1|max:48',
        ]);

        if ($cuenta->saldo_pendiente <= 0) {
            return response()->json(['success' => false, 'message' => 'La cuenta ya está completamente pagada.'], 422);
        }

        $numCuotas  = (int) $request->num_cuotas;
        $saldo      = (float) $cuenta->saldo_pendiente;
        $diasCredito = max(1, (int) ($cuenta->dias_credito ?? 30));
        $intervalo  = max(1, (int) round($diasCredito / $numCuotas));

        // Monto por cuota (la última absorbe el redondeo)
        $montoCuota = round($saldo / $numCuotas, 2);
        $montoUltima = round($saldo - $montoCuota * ($numCuotas - 1), 2);

        try {
            DB::beginTransaction();

            // Eliminar cuotas pendientes previas
            $cuenta->cuotas()->where('estado', 'pendiente')->delete();

            $fechaBase = $cuenta->fecha_emision;
            for ($i = 1; $i <= $numCuotas; $i++) {
                Cuota::create([
                    'cuenta_por_pagar_id' => $cuenta->id,
                    'numero_cuota'        => $i,
                    'total_cuotas'        => $numCuotas,
                    'monto'               => $i === $numCuotas ? $montoUltima : $montoCuota,
                    'fecha_vencimiento'   => $fechaBase->copy()->addDays($intervalo * $i),
                    'estado'              => 'pendiente',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Se generaron {$numCuotas} cuotas correctamente.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Registrar pago de una cuota específica con voucher
     */
    public function pagarCuota(Request $request, Cuota $cuota)
    {
        $cuenta = $cuota->cuentaPorPagar;

        $request->validate([
            'monto'         => 'required|numeric|min:0.01',
            'fecha_pago'    => 'required|date',
            'metodo_pago'   => 'required|in:transferencia,cheque,efectivo,tarjeta',
            'sucursal_id'   => 'required|exists:sucursales,id',
            'referencia'    => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:255',
            'comprobante'   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($cuota->estado === 'pagado') {
            return response()->json(['success' => false, 'message' => 'Esta cuota ya fue pagada.'], 422);
        }

        try {
            DB::beginTransaction();

            $comprobantePath = null;
            $comprobanteOriginalName = null;

            if ($request->hasFile('comprobante')) {
                $file = $request->file('comprobante');
                $comprobanteOriginalName = $file->getClientOriginalName();
                $comprobantePath = $file->store('comprobantes/pagos', 'public');
            }

            $sucursal = Sucursal::findOrFail($request->sucursal_id);
            $movimiento = $this->registrarEgresoCajaProveedor(
                $cuenta,
                $sucursal->id,
                (float) $request->monto,
                "Pago a proveedor {$cuenta->proveedor->razon_social} - Factura {$cuenta->numero_factura} - Cuota {$cuota->numero_cuota}/{$cuota->total_cuotas}"
            );

            $pago = Pago::create([
                'cuenta_por_pagar_id'       => $cuenta->id,
                'sucursal_id'               => $sucursal->id,
                'movimiento_caja_id'        => $movimiento?->id,
                'monto'                     => $request->monto,
                'fecha_pago'                => $request->fecha_pago,
                'metodo_pago'               => $request->metodo_pago,
                'referencia'                => $request->referencia,
                'usuario_id'                => auth()->id(),
                'estado'                    => 'procesado',
                'observaciones'             => $request->observaciones,
                'numero_cuota'              => $cuota->numero_cuota,
                'total_cuotas'              => $cuota->total_cuotas,
                'comprobante_path'          => $comprobantePath,
                'comprobante_original_name' => $comprobanteOriginalName,
            ]);

            // Marcar cuota como pagada
            $cuota->update(['estado' => 'pagado', 'pago_id' => $pago->id]);

            // Actualizar saldo de la cuenta
            $nuevoPagado    = $cuenta->monto_pagado + $request->monto;
            $saldoPendiente = $cuenta->monto_total - $nuevoPagado;
            $nuevoEstado    = $saldoPendiente <= 0 ? 'pagado' : 'parcial';

            if ($saldoPendiente > 0 && $cuenta->fecha_vencimiento && now()->greaterThan($cuenta->fecha_vencimiento)) {
                $nuevoEstado = 'vencido';
            }

            $cuenta->update([
                'monto_pagado'      => $nuevoPagado,
                'estado'            => $nuevoEstado,
                'fecha_ultimo_pago' => $request->fecha_pago,
            ]);

            DB::commit();

            $mensaje = 'Cuota registrada como pagada.';
            if (!$movimiento) {
                $mensaje .= " Aviso: {$sucursal->nombre} no tiene una caja abierta — registra manualmente el egreso de S/ "
                    . number_format($request->monto, 2) . ' cuando corresponda.';
            }

            return response()->json(['success' => true, 'message' => $mensaje]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Programar pago (modal)
     */
    public function programarPago(CuentaPorPagar $cuenta)
    {
        return view('cuentas-por-pagar.programar-pago', compact('cuenta'));
    }

    /**
     * Guardar programación de pago
     */
    public function guardarProgramacion(Request $request, CuentaPorPagar $cuenta)
    {
        $validated = $request->validate([
            'fecha_programada' => 'required|date|after_or_equal:today',
            'monto' => 'required|numeric|min:0.01|max:' . $cuenta->saldo_pendiente,
            'observaciones' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Crear pago programado
            Pago::create([
                'cuenta_por_pagar_id' => $cuenta->id,
                'monto' => $validated['monto'],
                'fecha_pago' => $validated['fecha_programada'],
                'metodo_pago' => 'programado',
                'estado' => 'programado',
                'usuario_id' => auth()->id(),
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago programado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar pago manual
     */
    public function registrarPago(Request $request, CuentaPorPagar $cuenta)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:' . $cuenta->saldo_pendiente,
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|in:transferencia,cheque,efectivo,tarjeta',
            'sucursal_id' => 'required|exists:sucursales,id',
            'referencia' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:255',
            'numero_cuota' => 'nullable|integer|min:1',
            'total_cuotas' => 'nullable|integer|min:1',
            'comprobante' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
        ]);

        try {
            DB::beginTransaction();

            // Subir comprobante si existe
            $comprobantePath = null;
            $comprobanteOriginalName = null;

            if ($request->hasFile('comprobante')) {
                $file = $request->file('comprobante');
                $comprobanteOriginalName = $file->getClientOriginalName();
                $comprobantePath = $file->store('comprobantes/pagos', 'public');
            }

            $sucursal = Sucursal::findOrFail($validated['sucursal_id']);
            $movimiento = $this->registrarEgresoCajaProveedor(
                $cuenta,
                $sucursal->id,
                (float) $validated['monto'],
                "Pago a proveedor {$cuenta->proveedor->razon_social} - Factura {$cuenta->numero_factura}"
            );

            // Crear el pago
            $pago = Pago::create([
                'cuenta_por_pagar_id' => $cuenta->id,
                'sucursal_id' => $sucursal->id,
                'movimiento_caja_id' => $movimiento?->id,
                'monto' => $validated['monto'],
                'fecha_pago' => $validated['fecha_pago'],
                'metodo_pago' => $validated['metodo_pago'],
                'referencia' => $validated['referencia'] ?? null,
                'usuario_id' => auth()->id(),
                'estado' => 'procesado',
                'observaciones' => $validated['observaciones'] ?? null,
                'numero_cuota' => $validated['numero_cuota'] ?? null,
                'total_cuotas' => $validated['total_cuotas'] ?? null,
                'comprobante_path' => $comprobantePath,
                'comprobante_original_name' => $comprobanteOriginalName,
            ]);

            // Actualizar cuenta
            $nuevoPagado = $cuenta->monto_pagado + $validated['monto'];
            $saldoPendiente = $cuenta->monto_total - $nuevoPagado;

            $nuevoEstado = $saldoPendiente <= 0 ? 'pagado' : 'parcial';

            if ($saldoPendiente > 0 && $cuenta->fecha_vencimiento && now()->greaterThan($cuenta->fecha_vencimiento)) {
                $nuevoEstado = 'vencido';
            }

            $cuenta->update([
                'monto_pagado' => $nuevoPagado,
                'estado' => $nuevoEstado,
                'fecha_ultimo_pago' => $validated['fecha_pago'],
            ]);

            DB::commit();

            $mensaje = 'Pago registrado correctamente.';
            if (!$movimiento) {
                $mensaje .= " Aviso: {$sucursal->nombre} no tiene una caja abierta — registra manualmente el egreso de S/ "
                    . number_format($validated['monto'], 2) . ' cuando corresponda.';
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de vencimientos
     */
    public function reporteVencimientos(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth());
        $fechaFin = $request->get('fecha_fin', now()->endOfMonth());

        $vencimientos = CuentaPorPagar::with('proveedor')
            ->whereBetween('fecha_vencimiento', [$fechaInicio, $fechaFin])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('fecha_vencimiento')
            ->get();

        $resumen = [
            'total' => $vencimientos->sum('monto_total'),
            'por_proveedor' => $vencimientos->groupBy('proveedor_id')
                ->map(function($items) {
                    return [
                        'proveedor' => $items->first()->proveedor->razon_social,
                        'cantidad' => $items->count(),
                        'monto' => $items->sum('monto_total'),
                    ];
                }),
        ];

        return view('cuentas-por-pagar.reporte-vencimientos', compact('vencimientos', 'resumen', 'fechaInicio', 'fechaFin'));
    }

    /**
     * Dashboard financiero general
     */
    public function dashboard()
    {
        $stats = [
            'por_pagar' => CuentaPorPagar::whereIn('estado', ['pendiente', 'parcial'])->sum('monto_total'),
            'vencido' => CuentaPorPagar::where('estado', 'vencido')->sum('monto_total'),
            'pagado_mes' => CuentaPorPagar::where('estado', 'pagado')
                ->whereMonth('fecha_ultimo_pago', now()->month)
                ->sum('monto_total'),
            'proyeccion_30_dias' => CuentaPorPagar::whereBetween('fecha_vencimiento', [now(), now()->addDays(30)])
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->sum('monto_total'),
        ];

        $proximosVencimientos = CuentaPorPagar::with('proveedor')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays(7)])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        return view('cuentas-por-pagar.dashboard', compact('stats', 'proximosVencimientos'));
    }
}