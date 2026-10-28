<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Almacen;
use App\Models\Caja;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\Compra;
use App\Models\DetalleVenta;
use App\Models\Cuota;
use App\Models\CuotaCobro;
use App\Models\CuentaPorCobrar;
use App\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Notas de crédito/débito: ajustan una venta, no son ventas (si se sumaran, una venta anulada seguiría contando). */
    private const TIPOS_NOTA = ['nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'];

    public function __construct(private CajaService $cajaService) {}
    public function index()
{
    $user = auth()->user();
    $rol = $user->role->nombre;
    
    return match($rol) {
        'Administrador' => redirect()->route('admin.dashboard'),
        'Almacenero' => redirect()->route('almacenero.dashboard'),
        'Tienda' => redirect()->route('tienda.dashboard'),
        'Cajero' => redirect()->route('cajero.dashboard'),
        'Vendedor' => redirect()->route('vendedor.dashboard'),
        'Proveedor' => redirect()->route('proveedor.dashboard'),
        'Técnico' => redirect()->route('caja-chica.index'),
        default => abort(403, 'Rol no autorizado'),
    };
}
    /**
     * Dashboard del Administrador
     */
    public function admin(): View
    {
        $mesActual  = now()->month;
        $anioActual = now()->year;
        $mesAnterior  = now()->subMonth()->month;
        $anioAnterior = now()->subMonth()->year;

        // ── Ventas ────────────────────────────────────────────────────────────
        $ventasMesActual = Venta::where('estado_pago', 'pagado')->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
            ->whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('total');

        $ventasMesAnterior = Venta::where('estado_pago', 'pagado')->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
            ->whereMonth('fecha', $mesAnterior)
            ->whereYear('fecha', $anioAnterior)
            ->sum('total');

        $variacionVentas = $ventasMesAnterior > 0
            ? round((($ventasMesActual - $ventasMesAnterior) / $ventasMesAnterior) * 100, 1)
            : 0;

        // ── Boletas y Facturas ────────────────────────────────────────────────
        $boletasTotal = Venta::where('tipo_comprobante', 'boleta')
            ->where('estado_pago', 'pagado')
            ->count();
        $boletasMes = Venta::where('tipo_comprobante', 'boleta')
            ->where('estado_pago', 'pagado')
            ->whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->count();

        $facturasTotal = Venta::where('tipo_comprobante', 'factura')
            ->where('estado_pago', 'pagado')
            ->count();
        $facturasMes = Venta::where('tipo_comprobante', 'factura')
            ->where('estado_pago', 'pagado')
            ->whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->count();

        // Ventas por mes del año actual (para el gráfico)
        $ventasPorMes = Venta::where('estado_pago', 'pagado')->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
            ->whereYear('fecha', $anioActual)
            ->selectRaw('MONTH(fecha) as mes, SUM(total) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $ventasMensualesChart = [];
        for ($m = 1; $m <= 12; $m++) {
            $ventasMensualesChart[] = $ventasPorMes[$m] ?? 0;
        }

        // ── Compras ───────────────────────────────────────────────────────────
        $comprasMesActual = Compra::where('estado', '!=', 'anulado')
            ->whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('total_pen');

        // ── Clientes ──────────────────────────────────────────────────────────
        $totalClientes = Cliente::count();

        // ── Inventario ────────────────────────────────────────────────────────
        $stockTotal     = StockAlmacen::sum('cantidad');
        $stockAccesorios = Producto::where('tipo_inventario', 'cantidad')
            ->sum('stock_actual');

        // Productos bajo stock con nombre (top 5)
        $productosBajoStockLista = Producto::where('tipo_inventario', 'cantidad')
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->where('estado', 'activo')
            ->orderBy('stock_actual')
            ->limit(5)
            ->get(['nombre', 'stock_actual', 'stock_minimo']);

        $productosBajoStock = $productosBajoStockLista->count();

        // ── Top productos más vendidos (mes actual) ───────────────────────────
        $topProductos = DetalleVenta::join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.estado_pago', 'pagado')
            ->whereNotIn('ventas.tipo_comprobante', self::TIPOS_NOTA)
            ->whereMonth('ventas.fecha', $mesActual)
            ->whereYear('ventas.fecha', $anioActual)
            ->selectRaw('productos.nombre, SUM(detalle_ventas.cantidad) as total_vendido')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();

        $maxVendido = $topProductos->max('total_vendido') ?: 1;

        // ── Notificaciones ────────────────────────────────────────────────────
        // Cuotas de proveedor vencidas o por vencer en los próximos 7 días
        $notifCuotas = Cuota::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<=', now()->addDays(7))
            ->with('cuentaPorPagar.proveedor')
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        // Cuotas de crédito a clientes vencidas o por vencer en los próximos 7 días
        $notifCuotasCobro = CuotaCobro::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<=', now()->addDays(7))
            ->with('cuentaPorCobrar.cliente')
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        // Todos los productos bajo stock (para notificaciones)
        $notifStockBajo = Producto::where('tipo_inventario', 'cantidad')
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->where('estado', 'activo')
            ->orderBy('stock_actual')
            ->limit(10)
            ->get(['id', 'nombre', 'stock_actual', 'stock_minimo']);

        $totalNotificaciones = $notifCuotas->count() + $notifCuotasCobro->count() + $notifStockBajo->count();

        // ── Últimos movimientos ───────────────────────────────────────────────
        $ultimosMovimientos = MovimientoInventario::with('producto', 'usuario', 'almacen', 'almacenDestino')
            ->latest()
            ->limit(8)
            ->get();

        // Cajas de días anteriores sin cerrar (todas las del sistema)
        $cajasAtrasadas = $this->cajaService->cajasAtrasadasTodas();

        $data = [
            // Cajas atrasadas
            'cajas_atrasadas'   => $cajasAtrasadas,

            // Usuarios
            'total_usuarios'    => User::count(),
            'usuarios_activos'  => User::where('estado', 'activo')->count(),
            'usuarios_inactivos'=> User::where('estado', 'inactivo')->count(),
            'usuarios_por_rol'  => User::join('roles', 'users.role_id', '=', 'roles.id')
                ->selectRaw('roles.nombre, COUNT(*) as total')
                ->groupBy('roles.nombre')
                ->get(),

            // Ventas
            'ventas_totales'        => Venta::where('estado_pago', 'pagado')->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)->sum('total'),
            'ventas_mes_actual'     => $ventasMesActual,
            'ventas_mes_anterior'   => $ventasMesAnterior,
            'variacion_ventas'      => $variacionVentas,
            'ventas_mensuales_chart'=> $ventasMensualesChart,
            'anio_chart'            => $anioActual,

            // Boletas y Facturas
            'boletas_total'    => $boletasTotal,
            'boletas_mes'      => $boletasMes,
            'facturas_total'   => $facturasTotal,
            'facturas_mes'     => $facturasMes,

            // Compras
            'compras_mes_actual' => $comprasMesActual,

            // Clientes
            'total_clientes' => $totalClientes,

            // Inventario
            'stock_total'      => $stockTotal,
            'stock_accesorios' => $stockAccesorios,

            // Stock bajo
            'productos_bajo_stock'       => $productosBajoStock,
            'productos_bajo_stock_lista' => $productosBajoStockLista,

            // Top productos
            'top_productos' => $topProductos,
            'max_vendido'   => $maxVendido,

            // Infraestructura
            'total_sucursales'     => \App\Models\Sucursal::count(),
            'total_almacenes'      => Almacen::count(),
            'total_proveedores'    => Proveedor::count(),
            'traslados_pendientes' => MovimientoInventario::where('tipo_movimiento', 'transferencia')
                ->where('estado', 'pendiente')
                ->count(),

            // Movimientos
            'ultimos_movimientos' => $ultimosMovimientos,

            // Notificaciones
            'notif_cuotas'         => $notifCuotas,
            'notif_cuotas_cobro'   => $notifCuotasCobro,
            'notif_stock_bajo'     => $notifStockBajo,
            'total_notificaciones' => $totalNotificaciones,
        ];

        return view('dashboards.admin', $data);
    }

    /**
     * Dashboard del Vendedor
     */
    public function vendedor(): View
    {
        $user = auth()->user();

        $baseQuery = fn() => Venta::where('user_id', $user->id)
            ->whereNotIn('tipo_comprobante', array_merge(['cotizacion', 'nota_venta'], self::TIPOS_NOTA));

        // Lo vendido no incluye ventas anuladas ni canceladas
        $vigentes = fn() => $baseQuery()->whereNotIn('estado_pago', ['anulado', 'cancelado']);

        // Ventas del día (todas del usuario)
        $ventas_dia = $vigentes()->whereDate('fecha', today())->sum('total');

        // Ventas del mes
        $ventas_mes = $vigentes()
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->sum('total');

        // Ventas pendientes de cobro
        $ventas_pendientes = $baseQuery()
            ->where('estado_pago', 'pendiente')
            ->with('cliente')
            ->orderBy('created_at', 'desc')
            ->get();

        $total_por_cobrar = $ventas_pendientes->sum('total');

        // Clientes atendidos hoy (distintos)
        $clientes_atendidos = $baseQuery()
            ->whereDate('fecha', today())
            ->whereNotNull('cliente_id')
            ->distinct('cliente_id')
            ->count('cliente_id');

        // Productos vendidos hoy (suma de cantidades)
        $productos_vendidos = \App\Models\DetalleVenta::whereHas('venta', fn($q) =>
            $q->where('user_id', $user->id)->whereDate('fecha', today())
                ->whereNotIn('estado_pago', ['anulado', 'cancelado', 'cotizacion'])
                ->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
        )->sum('cantidad');

        // Últimas ventas
        $ultimas_ventas = $baseQuery()
            ->with('cliente')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $cajaAtrasada = $this->cajaService->cajaAtrasada($user->id);

        $data = [
            'ventas_hoy'         => $ventas_dia,
            'ventas_mes'         => $ventas_mes,
            'clientes_atendidos' => $clientes_atendidos,
            'productos_vendidos' => $productos_vendidos,
            'ventas_pendientes'  => $ventas_pendientes,
            'total_por_cobrar'   => $total_por_cobrar,
            'tiendas'            => collect(),
            'ultimas_ventas'     => $ultimas_ventas,
            'caja_atrasada'      => $cajaAtrasada,
        ];

        return view('dashboards.vendedor', $data);
    }

    /**
     * Dashboard del Almacenero
     */
    public function almacenero(): View
    {
        $data = [
            'productos_stock' => 0, // Placeholder - se implementará en el módulo de inventario
            'productos_bajo_stock' => 0,
            'movimientos_hoy' => 0,
            'almacenes_activos' => 0,
        ];

        return view('dashboards.almacenero', $data);
    }

    /**
     * Dashboard del Proveedor
     */
    public function proveedor(): View
    {
        $data = [
            'ordenes_pendientes' => 0, // Placeholder - se implementará en el módulo de compras
            'ordenes_completadas' => 0,
            'productos_catalogo' => 0,
            'monto_total' => 0,
        ];

        return view('dashboards.proveedor', $data);
    }

    /**
     * Dashboard del Tienda
     */
    
public function tienda()
{
    $user = auth()->user();
    $hoy = now()->toDateString();
    
    // Calcular todas las variables que necesita la vista
    $ventas_dia = Venta::where('user_id', $user->id)
        ->whereDate('fecha', $hoy)
        ->where('estado_pago', 'pagado')
        ->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
        ->sum('total');
    
    $transacciones_dia = Venta::where('user_id', $user->id)
        ->whereDate('fecha', $hoy)
        ->where('estado_pago', 'pagado')
        ->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
        ->count();
    
    $clientes_atendidos = Venta::where('user_id', $user->id)
        ->whereDate('fecha', $hoy)
        ->where('estado_pago', 'pagado')
        ->whereNotNull('cliente_id')
        ->distinct('cliente_id')
        ->count('cliente_id');
    
    // Buscar caja abierta del usuario (de hoy)
    $caja = Caja::where('user_id', $user->id)
        ->where('estado', 'abierta')
        ->whereDate('fecha', $hoy)
        ->first();

    // Caja abierta de un día anterior (sin cerrar)
    $cajaAtrasada = $this->cajaService->cajaAtrasada($user->id);

    $caja_actual = $caja ? $caja->monto_final : ($cajaAtrasada ? $cajaAtrasada->monto_final : 0);

    // Últimas ventas del día
    $ultimas_ventas = Venta::where('user_id', $user->id)
        ->whereDate('fecha', $hoy)
        ->with('cliente')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    // Cuotas de crédito vencidas o por vencer hoy (alertas para el cajero)
    $creditosVencidos = CuotaCobro::where('estado', 'pendiente')
        ->where('fecha_vencimiento', '<=', now()->addDays(3))
        ->whereHas('cuentaPorCobrar', function ($q) use ($user) {
            if ($user->almacen_id) {
                $q->whereHas('venta', fn($v) => $v->where('almacen_id', $user->almacen_id));
            }
        })
        ->with('cuentaPorCobrar.cliente')
        ->orderBy('fecha_vencimiento')
        ->limit(5)
        ->get();

    return view('dashboards.tienda', [
        'ventas_dia'          => $ventas_dia,
        'caja_actual'         => $caja_actual,
        'caja'                => $caja,
        'caja_atrasada'       => $cajaAtrasada,
        'transacciones_dia'   => $transacciones_dia,
        'clientes_atendidos'  => $clientes_atendidos,
        'ultimas_ventas'      => $ultimas_ventas,
        'creditos_vencidos'   => $creditosVencidos,
    ]);
}

    /**
     * Dashboard del Cajero
     */
    public function cajero(): View
    {
        $user = auth()->user();
        $hoy  = now()->toDateString();

        $caja = Caja::where('user_id', $user->id)
            ->where('estado', 'abierta')
            ->whereDate('fecha', $hoy)
            ->first();

        $cajaAtrasada = $this->cajaService->cajaAtrasada($user->id);

        $ventasHoy = Venta::where('estado_pago', 'pagado')->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
            ->whereDate('fecha', $hoy)
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
            ->sum('total');

        $ventasPendientesCount = Venta::where('estado_pago', 'pendiente')
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
            ->count();

        return view('dashboards.cajero', compact('caja', 'cajaAtrasada', 'ventasHoy', 'ventasPendientesCount'));
    }
}