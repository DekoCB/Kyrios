<?php

namespace App\Http\Controllers;

use App\Models\CuentaPorPagar;
use App\Models\Role;
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
        $rol = auth()->user()->role->nombre;

        return match ($rol) {
            Role::ADMINISTRACION => redirect()->route('admin.dashboard'),
            Role::CONTADOR       => redirect()->route('contador.dashboard'),
            Role::LOGISTICA      => redirect()->route('logistica.dashboard'),
            Role::OPERACIONES    => redirect()->route('operaciones.dashboard'),
            Role::CLIENTE        => redirect()->route('cliente.dashboard'),
            default              => abort(403, 'Rol no autorizado'),
        };
    }

    /**
     * Dashboard de Administración
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
            ->selectRaw('productos.id, productos.codigo, productos.nombre, productos.nombre_fabrica, productos.nombre_kyrios, SUM(detalle_ventas.cantidad) as total_vendido')
            ->groupBy('productos.id', 'productos.codigo', 'productos.nombre', 'productos.nombre_fabrica', 'productos.nombre_kyrios')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get()
            ->map(function ($fila) {
                $fila->nombre = \App\Support\EtiquetaProducto::nombre($fila);

                return $fila;
            });

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
     * Dashboard del Contador: lo que debe revisar a diario en la parte administrativa.
     */
    public function contador(): View
    {
        $hoy = now()->toDateString();

        $pendientesSunat = Venta::whereIn('tipo_comprobante', ['boleta', 'factura', 'nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'])
            ->whereIn('estado_sunat', ['pendiente_envio', 'rechazado']);

        $ventasMes = Venta::where('estado_pago', 'pagado')
            ->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year);

        $porCobrarVencidas = CuentaPorCobrar::where('estado', 'vencido');
        $porPagarVencidas  = CuentaPorPagar::where(function ($q) use ($hoy) {
            $q->where('estado', 'vencido')
              ->orWhere(fn ($w) => $w->whereIn('estado', ['pendiente', 'parcial'])->whereDate('fecha_vencimiento', '<', $hoy));
        });

        return view('dashboards.contador', [
            'pendientes_sunat'      => (clone $pendientesSunat)->count(),
            'rechazados_sunat'      => (clone $pendientesSunat)->where('estado_sunat', 'rechazado')->count(),
            'ultimos_pendientes'    => (clone $pendientesSunat)->with('cliente', 'serieComprobante')->latest('fecha')->limit(8)->get(),
            'ventas_mes_total'      => (float) (clone $ventasMes)->sum('total'),
            'ventas_mes_cantidad'   => (clone $ventasMes)->count(),
            'por_cobrar_vencidas'   => (clone $porCobrarVencidas)->count(),
            'por_cobrar_monto'      => (float) (clone $porCobrarVencidas)->selectRaw('COALESCE(SUM(monto_total - monto_pagado), 0) as saldo')->value('saldo'),
            'por_pagar_vencidas'    => (clone $porPagarVencidas)->count(),
            'por_pagar_monto'       => (float) (clone $porPagarVencidas)->selectRaw('COALESCE(SUM(monto_total - monto_pagado), 0) as saldo')->value('saldo'),
        ]);
    }

    /**
     * Dashboard de Logística: compras, proveedores e inventario.
     */
    public function logistica(): View
    {
        return view('dashboards.logistica', [
            'productos_activos'    => Producto::where('estado', 'activo')->count(),
            'proveedores_activos'  => Proveedor::where('estado', 'activo')->count(),
            'compras_mes'          => Compra::whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->where('estado', '!=', 'anulado')->count(),
            'movimientos_hoy'      => MovimientoInventario::whereDate('created_at', today())->count(),
            'almacenes_activos'    => Almacen::where('estado', 'activo')->count(),
        ]);
    }

    /**
     * Dashboard de Operaciones: la programación de tareas e instalaciones aún no existe.
     */
    public function operaciones(): View
    {
        return view('dashboards.operaciones');
    }

    /**
     * Dashboard del Cliente: el portal de pedidos y seguimiento aún no existe.
     */
    public function cliente(): View
    {
        return view('dashboards.cliente');
    }
}
