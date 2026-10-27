<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCajaController extends Controller
{
    public function __construct(protected CajaService $cajaService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-01: Dashboard Ejecutivo en Tiempo Real
    // ─────────────────────────────────────────────────────────────────────────
    public function dashboard()
    {
        $hoy = now()->toDateString();

        // Cajas abiertas ahora
        $cajasAbiertas = Caja::abiertas()->with(['usuario', 'sucursal'])->get();

        // Cajas cerradas hoy
        $cajasCerradasHoy = Caja::cerradas()->whereDate('fecha', $hoy)->count();

        // Total ventas hoy
        $totalVentasHoy = MovimientoCaja::whereHas('caja', fn($q) => $q->whereDate('fecha', $hoy))
            ->where('tipo', 'ingreso')->whereNotNull('venta_id')
            ->sum('monto');

        // Total efectivo hoy
        $totalEfectivoHoy = MovimientoCaja::whereHas('caja', fn($q) => $q->whereDate('fecha', $hoy))
            ->where('tipo', 'ingreso')->whereNotNull('venta_id')
            ->where('metodo_pago', 'efectivo')->sum('monto');

        // Diferencias acumuladas del mes
        $diferenciasMes = Caja::cerradas()
            ->whereYear('fecha', now()->year)->whereMonth('fecha', now()->month)
            ->sum('diferencia_cierre');

        // Estado por sucursal
        $sucursales = Sucursal::where('estado', true)->with('almacen')->get();
        $estadoSucursales = $sucursales->map(function ($suc) use ($cajasAbiertas) {
            $cajaAbierta = $cajasAbiertas->where('sucursal_id', $suc->id)->first();
            $ultimaCaja  = Caja::where('sucursal_id', $suc->id)->latest('fecha_apertura')->first();

            $tiempoAbierta = null;
            $totalMinutos  = 0;
            if ($cajaAbierta) {
                $apertura     = \Carbon\Carbon::parse($cajaAbierta->fecha_apertura ?? $cajaAbierta->fecha);
                $totalMinutos = (int) $apertura->diffInMinutes(now());
                $dias         = intdiv($totalMinutos, 1440);
                $horas        = intdiv($totalMinutos % 1440, 60);
                $minutos      = $totalMinutos % 60;

                if ($dias > 0) {
                    $tiempoAbierta = "{$dias}d {$horas}h {$minutos}m";
                } elseif ($horas > 0) {
                    $tiempoAbierta = "{$horas}h {$minutos}m";
                } else {
                    $tiempoAbierta = "{$minutos}m";
                }
            }

            $alerta = $cajaAbierta && $totalMinutos > 720; // > 12 horas

            return [
                'sucursal'      => $suc,
                'caja_abierta'  => $cajaAbierta,
                'ultima_caja'   => $ultimaCaja,
                'horas_abierta' => $tiempoAbierta,
                'alerta'        => $alerta,
                'estado_label'  => $cajaAbierta ? ($alerta ? 'warning' : 'open') : 'closed',
            ];
        });

        // Top 5 vendedores del día
        $topVendedores = DB::table('movimientos_caja as m')
            ->join('caja as c', 'c.id', '=', 'm.caja_id')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->whereDate('c.fecha', $hoy)
            ->where('m.tipo', 'ingreso')->whereNotNull('m.venta_id')
            ->groupBy('c.user_id', 'u.name')
            ->select('u.name', DB::raw('SUM(m.monto) as total'), DB::raw('COUNT(m.id) as ventas'))
            ->orderByDesc('total')->limit(5)->get();

        // Breakdown por método de pago
        $metodoPago = DB::table('movimientos_caja as m')
            ->join('caja as c', 'c.id', '=', 'm.caja_id')
            ->whereDate('c.fecha', $hoy)
            ->where('m.tipo', 'ingreso')->whereNotNull('m.venta_id')
            ->groupBy('m.metodo_pago')
            ->select('m.metodo_pago', DB::raw('SUM(m.monto) as total'))
            ->get()->keyBy('metodo_pago');

        $alertasCount = $this->contarAlertas();

        return view('admin.cajas.dashboard', compact(
            'cajasAbiertas', 'cajasCerradasHoy', 'totalVentasHoy', 'totalEfectivoHoy',
            'diferenciasMes', 'estadoSucursales', 'topVendedores', 'metodoPago', 'alertasCount'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-02: Listado con filtros avanzados y exportación
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Caja::with(['usuario', 'sucursal', 'almacen']);

        if ($request->filled('sucursal_id')) $query->where('sucursal_id', $request->sucursal_id);
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if ($request->filled('user_id'))     $query->where('user_id', $request->user_id);
        if ($request->filled('fecha_desde')) $query->whereDate('fecha', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('fecha', '<=', $request->fecha_hasta);

        // Export CSV
        if ($request->filled('export') && $request->export === 'csv') {
            return $this->exportarCsv($query->orderByDesc('fecha_apertura')->get());
        }
        // Export PDF
        if ($request->filled('export') && $request->export === 'pdf') {
            $cajas = $query->orderByDesc('fecha_apertura')->get();
            $pdf = Pdf::loadView('admin.cajas.export-pdf', compact('cajas'))->setPaper('a4', 'landscape');
            return $pdf->download('cajas_' . now()->format('Y-m-d') . '.pdf');
        }

        $cajas     = $query->orderByDesc('fecha_apertura')->paginate(25)->withQueryString();
        $sucursales = Sucursal::where('estado', true)->get();
        $usuarios  = User::orderBy('name')->get();
        $alertasCount = $this->contarAlertas();

        // Cargar movimientos y computar arqueo por caja para las columnas de método de pago
        $cajas->load('movimientos');
        $cajas->each(fn($c) => $c->arqueo = $this->cajaService->getArqueo($c));

        return view('admin.cajas.index', compact('cajas', 'sucursales', 'usuarios', 'alertasCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-03: Detalle con panel de supervisión
    // ─────────────────────────────────────────────────────────────────────────
    public function show(Caja $caja)
    {
        $caja->load(['usuario', 'sucursal', 'almacen', 'movimientos.usuario', 'movimientos.venta']);
        $arqueo = $this->cajaService->getArqueo($caja);
        $alertasCount = $this->contarAlertas();

        $movimientosPorFecha = $caja->movimientos
            ->sortBy('created_at')
            ->groupBy(fn($m) => $m->created_at->toDateString());

        return view('admin.cajas.show', compact('caja', 'arqueo', 'alertasCount', 'movimientosPorFecha'));
    }

    public function forzarCierre(Request $request, Caja $caja)
    {
        $request->validate(['observaciones' => 'required|string|min:10']);

        if ($caja->estado !== 'abierta') {
            return back()->with('error', 'Esta caja ya está cerrada.');
        }

        // Auditoría del cierre forzado
        MovimientoCaja::create([
            'caja_id'       => $caja->id,
            'user_id'       => auth()->id(),
            'tipo'          => 'egreso',
            'monto'         => 0.00,
            'concepto'      => 'CIERRE FORZADO POR ADMINISTRADOR',
            'observaciones' => $request->observaciones,
            'metodo_pago'   => 'efectivo',
        ]);

        // Se cierra con el efectivo esperado (monto_final también suma Yape, Plin y transferencias,
        // que no están en el cajón), por el mismo camino que el cierre manual y el automático.
        $esperado = $this->cajaService->getArqueo($caja->load('movimientos'))['saldo_esperado'];
        $this->cajaService->cerrarCaja(
            $caja->id,
            $esperado,
            '[FORZADO por ' . auth()->user()->name . '] ' . $request->observaciones
        );

        return redirect()->route('admin.cajas.show', $caja)->with('success', 'Caja cerrada forzosamente.');
    }

    public function ajustarDiferencia(Request $request, Caja $caja)
    {
        $request->validate([
            'monto_ajuste'  => 'required|numeric|not_in:0',
            'motivo_ajuste' => 'required|string|min:5',
        ]);

        $monto = abs((float) $request->monto_ajuste);
        $tipo  = (float) $request->monto_ajuste >= 0 ? 'ingreso' : 'egreso';

        // Actualiza también monto_final y, en una caja cerrada, recalcula la diferencia del cierre
        // a partir del arqueo (un ingreso sube lo esperado, así que la diferencia baja).
        $this->cajaService->registrarCorreccion(
            $caja, $tipo, $monto, 'AJUSTE ADMINISTRATIVO: ' . $request->motivo_ajuste, null, 'efectivo'
        );

        return back()->with('success', 'Ajuste de S/ ' . number_format($monto, 2) . ' registrado.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-04: Apertura remota
    // ─────────────────────────────────────────────────────────────────────────
    public function aperturaRemota()
    {
        $sucursales   = Sucursal::where('estado', true)->with('almacen')->get();
        $usuarios     = User::whereNotNull('almacen_id')->orderBy('name')->get();
        $alertasCount = $this->contarAlertas();

        return view('admin.cajas.apertura-remota', compact('sucursales', 'usuarios', 'alertasCount'));
    }

    public function storeAperturaRemota(Request $request)
    {
        $request->validate([
            'sucursal_id'   => 'required|exists:sucursales,id',
            'user_id'       => 'required|exists:users,id',
            'monto_inicial' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        $sucursal = Sucursal::findOrFail($request->sucursal_id);

        $yaAbierta = Caja::where('user_id', $request->user_id)->where('estado', 'abierta')->exists();
        if ($yaAbierta) {
            return back()->withInput()->with('error', 'Este usuario ya tiene una caja abierta.');
        }

        $obs = '[APERTURA REMOTA por ' . auth()->user()->name . ']';
        if ($request->filled('observaciones')) $obs .= ' ' . $request->observaciones;

        $caja = Caja::create([
            'user_id'                => $request->user_id,
            'almacen_id'             => $sucursal->almacen_id,
            'sucursal_id'            => $sucursal->id,
            'fecha'                  => now()->toDateString(),
            'fecha_apertura'         => now(),
            'monto_inicial'          => $request->monto_inicial,
            'monto_final'            => $request->monto_inicial,
            'estado'                 => 'abierta',
            'observaciones_apertura' => $obs,
        ]);

        return redirect()->route('admin.cajas.show', $caja)
            ->with('success', 'Caja abierta remotamente en ' . $sucursal->nombre . '.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-05: Alertas y notificaciones
    // ─────────────────────────────────────────────────────────────────────────
    public function alertas()
    {
        $ayer = now()->subDay()->toDateString();

        $cajasLargas = Caja::abiertas()
            ->where('fecha_apertura', '<', now()->subHours(12))
            ->with(['usuario', 'sucursal'])->get()
            ->map(fn($c) => [
                'tipo'    => 'caja_larga',
                'mensaje' => 'Lleva ' . now()->diffInHours($c->fecha_apertura) . 'h abierta — ' . ($c->usuario->name ?? '?') . ' · ' . ($c->sucursal?->nombre ?? 'Sin sucursal'),
                'caja'    => $c,
                'nivel'   => 'warning',
            ]);

        $diferencias = Caja::cerradas()
            ->whereRaw('ABS(diferencia_cierre) > 100')
            ->whereDate('fecha', '>=', now()->subDays(7))
            ->with(['usuario', 'sucursal'])->get()
            ->map(fn($c) => [
                'tipo'    => 'diferencia',
                'mensaje' => 'Diferencia S/ ' . number_format(abs($c->diferencia_cierre), 2) . ' — ' . ($c->usuario->name ?? '?') . ' · ' . ($c->sucursal?->nombre ?? 'Sin sucursal'),
                'caja'    => $c,
                'nivel'   => 'danger',
            ]);

        $sinCerrar = Caja::abiertas()
            ->whereDate('fecha', '<', now()->toDateString())
            ->with(['usuario', 'sucursal'])->get()
            ->map(fn($c) => [
                'tipo'    => 'sin_cerrar',
                'mensaje' => 'Caja del ' . $c->fecha->format('d/m/Y') . ' sin cerrar — ' . ($c->usuario->name ?? '?') . ' · ' . ($c->sucursal?->nombre ?? 'Sin sucursal'),
                'caja'    => $c,
                'nivel'   => 'danger',
            ]);

        $alertas      = collect()->merge($sinCerrar)->merge($cajasLargas)->merge($diferencias);
        $alertasCount = $alertas->count();

        return view('admin.cajas.alertas', compact('alertas', 'alertasCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────────
    private function exportarCsv($cajas)
    {
        $filename = 'cajas_' . now()->format('Y-m-d_Hi') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($cajas) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($handle, ['ID', 'Cajero', 'Sucursal', 'Fecha', 'Apertura', 'Cierre', 'M.Inicial', 'T.Ventas', 'T.Ingresos', 'T.Egresos', 'M.Final', 'Diferencia', 'Estado']);
            foreach ($cajas as $c) {
                fputcsv($handle, [
                    $c->id,
                    $c->usuario?->name ?? '',
                    $c->sucursal?->nombre ?? '',
                    $c->fecha,
                    $c->fecha_apertura,
                    $c->fecha_cierre ?? '',
                    $c->monto_inicial,
                    $c->total_ventas,
                    $c->total_ingresos,
                    $c->total_egresos,
                    $c->monto_final,
                    $c->diferencia_cierre ?? '',
                    $c->estado,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HU-ADMIN-CAJA-06: Reportes Comparativos
    // ─────────────────────────────────────────────────────────────────────────
    public function reportes(Request $request)
    {
        $desde = $request->filled('desde') ? $request->desde : now()->subDays(29)->toDateString();
        $hasta = $request->filled('hasta') ? $request->hasta : now()->toDateString();

        $sucursales = Sucursal::where('estado', true)->orderBy('nombre')->get();

        // ── Ventas por sucursal en el período ────────────────────────────────
        $ventasPorSucursal = DB::table('movimientos_caja as m')
            ->join('caja as c', 'c.id', '=', 'm.caja_id')
            ->join('sucursales as s', 's.id', '=', 'c.sucursal_id')
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where('m.tipo', 'ingreso')->whereNotNull('m.venta_id')
            ->groupBy('c.sucursal_id', 's.nombre')
            ->select('s.nombre', DB::raw('SUM(m.monto) as total'), DB::raw('COUNT(DISTINCT c.id) as cajas'))
            ->orderByDesc('total')->get();

        // ── Diferencias por sucursal ─────────────────────────────────────────
        $diferenciasPorSucursal = DB::table('caja as c')
            ->join('sucursales as s', 's.id', '=', 'c.sucursal_id')
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where('c.estado', 'cerrada')
            ->whereNotNull('c.diferencia_cierre')
            ->groupBy('c.sucursal_id', 's.nombre')
            ->select('s.nombre', DB::raw('SUM(c.diferencia_cierre) as total'), DB::raw('COUNT(*) as cajas'))
            ->orderBy('total')->get();

        // ── Tendencia diaria de ventas (todas las sucursales) ────────────────
        $tendenciaDiaria = DB::table('movimientos_caja as m')
            ->join('caja as c', 'c.id', '=', 'm.caja_id')
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where('m.tipo', 'ingreso')->whereNotNull('m.venta_id')
            ->groupBy('c.fecha')
            ->select('c.fecha', DB::raw('SUM(m.monto) as total'))
            ->orderBy('c.fecha')->get();

        // ── Métodos de pago por sucursal ─────────────────────────────────────
        $metodoPorSucursal = DB::table('movimientos_caja as m')
            ->join('caja as c', 'c.id', '=', 'm.caja_id')
            ->join('sucursales as s', 's.id', '=', 'c.sucursal_id')
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->where('m.tipo', 'ingreso')->whereNotNull('m.venta_id')
            ->groupBy('c.sucursal_id', 's.nombre', 'm.metodo_pago')
            ->select('s.nombre as sucursal', 'm.metodo_pago', DB::raw('SUM(m.monto) as total'))
            ->get()
            ->groupBy('sucursal');

        // ── KPIs del período ─────────────────────────────────────────────────
        $kpis = [
            'total_ventas'    => $ventasPorSucursal->sum('total'),
            'total_cajas'     => DB::table('caja')->whereBetween('fecha', [$desde, $hasta])->count(),
            'promedio_por_dia'=> $tendenciaDiaria->avg('total') ?? 0,
            'mejor_dia'       => $tendenciaDiaria->sortByDesc('total')->first(),
            'dif_total'       => $diferenciasPorSucursal->sum('total'),
        ];

        $alertasCount = $this->contarAlertas();

        return view('admin.cajas.reportes', compact(
            'desde', 'hasta', 'sucursales',
            'ventasPorSucursal', 'diferenciasPorSucursal',
            'tendenciaDiaria', 'metodoPorSucursal', 'kpis', 'alertasCount'
        ));
    }

    public function contarAlertas(): int
    {
        $largas    = Caja::abiertas()->where('fecha_apertura', '<', now()->subHours(12))->count();
        $difs      = Caja::cerradas()->whereRaw('ABS(diferencia_cierre) > 100')->whereDate('fecha', '>=', now()->subDays(7))->count();
        $sinCerrar = Caja::abiertas()->whereDate('fecha', '<', now()->toDateString())->count();
        return $largas + $difs + $sinCerrar;
    }
}
