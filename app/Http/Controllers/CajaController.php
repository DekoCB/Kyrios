<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function __construct(private CajaService $cajaService) {}

    /**
     * Historial de cajas (admin ve todo, tienda ve solo las suyas).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->role->nombre === 'Administrador';

        $query = Caja::with(['usuario', 'almacen', 'sucursal'])
            // Movimientos de devolución/anulación son egresos ligados a una venta;
            // se cuentan aparte para poder avisar en el listado que esa caja tuvo
            // alguno, sin obligar a entrar al detalle para enterarse.
            ->withCount(['movimientos as devoluciones_count' => function ($q) {
                $q->where('tipo', 'egreso')->whereNotNull('venta_id');
            }])
            ->orderBy('created_at', 'desc');

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        // Filtros (solo admin)
        if ($isAdmin) {
            if ($request->filled('sucursal_id')) {
                $query->where('sucursal_id', $request->sucursal_id);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha', '<=', $request->fecha_hasta);
            }
        }

        $cajas = $query->paginate(20)->withQueryString();

        $usuarios = $isAdmin ? User::orderBy('name')->get() : collect();
        $sucursales = $isAdmin ? \App\Models\Sucursal::where('estado', 'activo')->orderBy('nombre')->get() : collect();

        return view('caja.index', compact('cajas', 'isAdmin', 'usuarios', 'sucursales'));
    }

    /**
     * Formulario de apertura de caja.
     */
    public function abrir()
    {
        $cajaActiva = $this->cajaService->cajaActiva();

        if ($cajaActiva) {
            // Si la caja activa es de hoy, redirigir a la vista actual
            if ($cajaActiva->fecha >= now()->toDateString()) {
                return redirect()->route('caja.actual')
                    ->with('info', 'Ya tienes una caja abierta hoy.');
            }
        }

        $user = auth()->user();
        $almacen = $user->almacen ?? null;
        $almacenes = collect();

        // Caja atrasada (de un día anterior sin cerrar)
        $cajaAtrasada = $this->cajaService->cajaAtrasada();

        // Admin sin almacén fijo: puede elegir en qué almacén trabajar
        if (!$almacen && $user->role->nombre === 'Administrador') {
            $almacenes = \App\Models\Almacen::where('estado', 'activo')
                ->orderBy('nombre')
                ->get();
        }

        return view('caja.abrir', compact('almacen', 'almacenes', 'cajaAtrasada'));
    }

    /**
     * Abrir nueva caja.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'monto_inicial'    => 'required|numeric|min:0',
            'almacen_id'       => 'nullable|exists:almacenes,id',
            'observaciones'    => 'nullable|string|max:500',
        ]);

        try {
            $this->cajaService->abrirCaja(
                auth()->id(),
                isset($validated['almacen_id']) ? (int) $validated['almacen_id'] : null,
                (float) $validated['monto_inicial'],
                $validated['observaciones'] ?? null
            );

            return redirect()->route('caja.actual')
                ->with('success', 'Caja abierta exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Vista de caja actual del usuario (con arqueo en tiempo real).
     */
    public function actual()
    {
        $caja = $this->cajaService->cajaActiva();

        if (!$caja) {
            return redirect()->route('caja.abrir')
                ->with('error', 'No tienes una caja abierta. Abre una para continuar.');
        }

        $arqueo = $this->cajaService->getArqueo($caja);

        return view('caja.actual', compact('caja', 'arqueo'));
    }

    /**
     * Detalle de una caja cerrada (admin o propietario).
     */
    public function show(Caja $caja)
    {
        $user = auth()->user();
        $isAdmin = $user->role->nombre === 'Administrador';

        if (!$isAdmin && $caja->user_id !== $user->id) {
            abort(403, 'No tienes acceso a esta caja.');
        }

        $caja->load(['usuario', 'almacen', 'sucursal', 'movimientos.venta', 'movimientos.usuario']);
        $arqueo = $this->cajaService->getArqueo($caja);

        return view('caja.show', compact('caja', 'arqueo', 'isAdmin'));
    }

    /**
     * Cerrar caja con arqueo.
     */
    public function cerrar(Request $request)
    {
        $validated = $request->validate([
            'caja_id'             => 'required|exists:caja,id',
            'monto_real_cierre'   => 'required|numeric|min:0',
            'observaciones_cierre'=> 'nullable|string|max:500',
        ]);

        $caja = Caja::findOrFail($validated['caja_id']);

        if ($caja->user_id !== auth()->id() && auth()->user()->role->nombre !== 'Administrador') {
            abort(403);
        }

        try {
            $this->cajaService->cerrarCaja(
                $validated['caja_id'],
                (float) $validated['monto_real_cierre'],
                $validated['observaciones_cierre'] ?? null
            );

            return redirect()->route('caja.index')
                ->with('success', 'Caja cerrada correctamente. El arqueo fue registrado.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Registrar ingreso manual.
     */
    public function registrarIngreso(Request $request)
    {
        $validated = $request->validate([
            'caja_id'     => 'required|exists:caja,id',
            'monto'       => 'required|numeric|min:0.01',
            'concepto'    => 'required|string|max:255',
            'metodo_pago' => 'required|in:efectivo,yape,plin,transferencia',
            'referencia'  => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $this->cajaService->registrarMovimiento(
                (int) $validated['caja_id'],
                'ingreso',
                (float) $validated['monto'],
                $validated['concepto'],
                null,
                null,
                $validated['observaciones'] ?? null,
                $validated['metodo_pago'],
                $validated['referencia'] ?? null
            );

            return redirect()->route('caja.actual')
                ->with('success', 'Ingreso registrado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Registrar gasto / egreso.
     */
    public function registrarGasto(Request $request)
    {
        $validated = $request->validate([
            'caja_id'        => 'required|exists:caja,id',
            'monto'          => 'required|numeric|min:0.01',
            'concepto'       => 'required|string|max:255',
            'categoria_gasto'=> 'required|in:operativo,limpieza,transporte,alimentacion,otros',
            'observaciones'  => 'nullable|string|max:500',
        ]);

        $conceptoCompleto = '[' . ucfirst($validated['categoria_gasto']) . '] ' . $validated['concepto'];

        try {
            $this->cajaService->registrarMovimiento(
                (int) $validated['caja_id'],
                'egreso',
                (float) $validated['monto'],
                $conceptoCompleto,
                null,
                null,
                $validated['observaciones'] ?? null,
                'efectivo',
                null
            );

            return redirect()->route('caja.actual')
                ->with('success', 'Gasto registrado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Listado de movimientos del usuario paginados.
     */
    public function movimientos()
    {
        $movimientos = MovimientoCaja::with(['caja.almacen', 'venta'])
            ->whereHas('caja', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('caja.movimientos', compact('movimientos'));
    }
}
