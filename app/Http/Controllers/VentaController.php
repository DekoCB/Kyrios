<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\GuiaRemision;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Imei;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\CuentaPorCobrar;
use App\Models\AuditoriaVenta;
use App\Services\VentaService;
use App\Services\VarianteService;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Requests\UpdateVentaRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Base con restricciones de rol
        $base = Venta::query()
            ->when($user->role->nombre === 'Vendedor', fn($q) => $q->where('user_id', $user->id))
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id));

        // Aplicar filtros de búsqueda
        $query = (clone $base)->with('vendedor', 'cliente', 'almacen');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('codigo', 'like', "%{$q}%")
                  ->orWhereHas('cliente', fn($c) => $c->where('nombre', 'like', "%{$q}%")
                      ->orWhere('numero_documento', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->estado_pago);
        }

        if ($request->filled('tipo_comprobante')) {
            $query->where('tipo_comprobante', $request->tipo_comprobante);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $ventas = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Stats siempre sobre la base SIN filtros de búsqueda
        $statsBase = clone $base;
        $stats = [
            'hoy'        => (clone $statsBase)->whereDate('fecha', today())->sum('total'),
            'mes_total'  => (clone $statsBase)->whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->sum('total'),
            'mes_count'  => (clone $statsBase)->whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->count(),
            'pendientes' => (clone $statsBase)->where('estado_pago', 'pendiente')->count(),
        ];

        return view('ventas.index', compact('ventas', 'stats'));
    }

    public function create()
    {
        $user      = auth()->user();
        $clientes  = Cliente::activos()->orderBy('nombre')->get();
        $categorias = Categoria::activas()->orderBy('nombre')->get();

        // Ventas solo en tiendas (puntos de venta)
        // Admin: todas | usuario con tienda asignada: solo la suya | sin asignación: todas
        $rolNombre = $user->role->nombre;
        if ($rolNombre === 'Administrador') {
            $almacenes = Almacen::where('estado', 'activo')->where('tipo', 'tienda')->orderBy('nombre')->get();
        } elseif ($user->almacen_id) {
            $almacenes = Almacen::where('estado', 'activo')
                ->where('tipo', 'tienda')
                ->where('id', $user->almacen_id)
                ->orderBy('nombre')
                ->get();
        } else {
            // Vendedor/Cajero sin tienda asignada: ve todas las tiendas activas
            $almacenes = Almacen::where('estado', 'activo')->where('tipo', 'tienda')->orderBy('nombre')->get();
        }

        $esVendedor = $rolNombre === 'Vendedor';

        // Preseleccionar si solo hay un almacén disponible
        $almacenPredeterminado = $almacenes->count() === 1
            ? $almacenes->first()->id
            : ($user->almacen_id ?: null);

        $productos = Producto::where('estado', 'activo')
            ->with(['categoria', 'variantesActivas.color', 'precios' => function ($q) {
                $q->where('activo', true)
                  ->whereNull('almacen_id')
                  ->whereIn('tipo_precio', ['venta_regular', 'venta_mayorista'])
                  ->latest();
            }])
            ->orderBy('nombre')
            ->get();

        // Stock por almacén para productos de cantidad
        $stockPorAlmacen = StockAlmacen::whereIn('producto_id', $productos->pluck('id'))
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('cantidad', 'almacen_id'));

        // Stock por almacén para productos de serie (contar IMEIs en_stock) — nivel producto
        $imeisPorAlmacen = Imei::whereIn('producto_id', $productos->pluck('id'))
            ->where('estado_imei', 'en_stock')
            ->selectRaw('producto_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'almacen_id')
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        // IMEIs por variante_id (registros recientes con variante asignada)
        $imeisPorVarianteId = Imei::whereIn('producto_id', $productos->pluck('id'))
            ->where('estado_imei', 'en_stock')
            ->whereNotNull('variante_id')
            ->selectRaw('variante_id, almacen_id, COUNT(*) as total')
            ->groupBy('variante_id', 'almacen_id')
            ->get()
            ->groupBy('variante_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        // IMEIs sin variante_id: fallback por color (registros anteriores)
        $imeisPorColor = Imei::whereIn('producto_id', $productos->pluck('id'))
            ->where('estado_imei', 'en_stock')
            ->whereNull('variante_id')
            ->selectRaw('producto_id, color_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'color_id', 'almacen_id')
            ->get()
            ->groupBy(fn($row) => $row->producto_id . '_' . $row->color_id)
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        $productos = $productos->map(function($p) use ($stockPorAlmacen, $imeisPorAlmacen, $imeisPorVarianteId, $imeisPorColor) {
            $esSerie = $p->tipo_inventario === 'serie';

            // Separar precios por tipo
            $preciosRegulares  = $p->precios->where('tipo_precio', 'venta_regular');
            $preciosMayoristas = $p->precios->where('tipo_precio', 'venta_mayorista');
            // Indexar precios por variante_id para lookup O(1)
            $preciosPorVariante          = $preciosRegulares->whereNotNull('variante_id')->unique('variante_id')->keyBy('variante_id');
            $preciosMayoristaPorVariante = $preciosMayoristas->whereNotNull('variante_id')->unique('variante_id')->keyBy('variante_id');
            // Precio base del producto (sin variante) — fallback
            $precioBase          = $preciosRegulares->first(fn($pr) => is_null($pr->variante_id));
            $precioMayoristaBase = $preciosMayoristas->first(fn($pr) => is_null($pr->variante_id));
            // Para incluye_igv usamos el primer precio activo (configuración global del producto)
            $precioActivo = $preciosRegulares->first();

            $variantes = $p->variantesActivas->map(function($v) use ($esSerie, $imeisPorVarianteId, $imeisPorColor, $p, $preciosPorVariante, $preciosMayoristaPorVariante, $precioMayoristaBase) {
                // 1) precio manual en el campo nuevo del variante (override), 2) Gestión de Precios, 3) null
                $precioGestion   = $preciosPorVariante->get($v->id);
                $precioMayorista = $preciosMayoristaPorVariante->get($v->id) ?? $precioMayoristaBase;
                $precioVenta = $v->precio_venta !== null
                    ? (float)$v->precio_venta
                    : ($precioGestion ? (float)$precioGestion->precio : null);

                return [
                'id'                => $v->id,
                'sku'               => $v->sku,
                'color_id'          => $v->color_id,
                'color_nombre'      => $v->color?->nombre,
                'color_hex'         => $v->color?->codigo_hex,
                'capacidad'         => $v->capacidad,
                'sobreprecio'       => (float)$v->sobreprecio,
                'precio_venta'      => $precioVenta,
                'precio_mayorista'  => $precioMayorista ? (float)$precioMayorista->precio : null,
                'incluye_igv'       => $precioGestion ? (bool)$precioGestion->incluye_igv : null,
                'stock_actual'      => $esSerie
                    ? (function() use ($imeisPorVarianteId, $imeisPorColor, $v, $p) {
                        $byVariante = $imeisPorVarianteId[$v->id] ?? collect();
                        return $byVariante->isNotEmpty()
                            ? (int)$byVariante->sum()
                            : (int)($imeisPorColor[$p->id . '_' . $v->color_id] ?? collect())->sum();
                    })()
                    : (int)$v->stock_actual,
                'stock_por_almacen' => $esSerie
                    ? (function() use ($imeisPorVarianteId, $imeisPorColor, $v, $p) {
                        $byVariante = $imeisPorVarianteId[$v->id] ?? collect();
                        return $byVariante->isNotEmpty()
                            ? $byVariante->toArray()
                            : ($imeisPorColor[$p->id . '_' . $v->color_id] ?? collect())->toArray();
                    })()
                    : [],
                'nombre_completo'   => $v->nombre_completo,
                'tiene_stock'       => $v->tieneStock(),
                ];
            });

            $stockMap = $p->tipo_inventario === 'serie'
                ? ($imeisPorAlmacen[$p->id] ?? collect())->toArray()
                : ($stockPorAlmacen[$p->id] ?? collect())->toArray();

            return [
                'id'               => $p->id,
                'nombre'           => $p->nombre,
                'codigo'           => $p->codigo,
                'codigo_barras'    => $p->codigo_barras ?? null,
                'categoria_id'     => $p->categoria_id,
                'tipo_inventario'  => $p->tipo_inventario,
                // Suma de $stockMap (live, por almacén) en vez de productos.stock_actual:
                // esa columna se desincroniza en varios flujos (registro de IMEIs por compra,
                // reversión de ventas, etc.) y así se garantiza que coincida con lo que se ve por tienda.
                'stock_actual'     => (int) array_sum($stockMap),
                'stock_por_almacen'=> $stockMap,  // {almacen_id: qty}
                'precio_venta'     => (float) ($variantes->isNotEmpty()
                    ? ($variantes->filter(fn($v) => $v['precio_venta'] !== null)->min('precio_venta') ?? $precioBase?->precio ?? $p->precio_venta)
                    : ($precioBase?->precio ?? $precioActivo?->precio ?? $p->precio_venta)),
                'precio_mayorista' => $precioMayoristaBase ? (float)$precioMayoristaBase->precio : null,
                'incluye_igv'      => (bool) ($precioActivo?->incluye_igv ?? false),
                'imagen'           => $p->imagen_url ?? null,
                'tiene_variantes'  => $variantes->isNotEmpty(),
                'variantes'        => $variantes,
            ];
        });

        // Pagos digitales configurados para la sucursal del usuario
        $sucursal = Sucursal::where('almacen_id', $almacenPredeterminado)->first();
        $pagosConfig = $sucursal
            ? $sucursal->pagos()->where('activo', true)->get()
                ->mapWithKeys(fn($p) => [$p->tipo_pago => [
                    'titular' => $p->titular,
                    'numero'  => $p->numero,
                    'banco'   => $p->banco,
                    'cci'     => $p->cci,
                    'qr_url'  => $p->qr_imagen_path ? asset('storage/' . $p->qr_imagen_path) : null,
                ]])
            : collect();

        $empresa = Empresa::first();

        // Último conductor privado usado (mismo criterio que el módulo de Guías de
        // Remisión) para precargar el formulario de guía del POS. Antes el POS solo
        // guardaba esto en localStorage del navegador, así que no "jalaba" datos
        // guardados desde otro equipo/navegador ni desde el módulo de guías.
        $ultimoConductor = GuiaRemision::whereNotNull('conductor_dni')
            ->whereNotNull('conductor_nombre')
            ->latest()
            ->first();

        // Estado de la caja del usuario actual
        $cajaActual     = \App\Models\Caja::where('user_id', auth()->id())
            ->where('estado', 'abierta')
            ->latest()
            ->first();
        $cajaAbierta    = (bool) $cajaActual;
        $cajaDiaAnterior = $cajaActual && $cajaActual->fecha->lt(today());

        // Ventas pendientes de cobro para Vendedor
        $misPendientes = $esVendedor
            ? Venta::with(['cliente', 'detalles.producto', 'detalles.variante.color'])
                ->where('user_id', $user->id)
                ->where('estado_pago', 'pendiente')
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get()
            : collect();

        return view('ventas.create', compact(
            'clientes', 'productos', 'almacenes', 'categorias', 'almacenPredeterminado', 'pagosConfig', 'empresa',
            'cajaAbierta', 'cajaDiaAnterior', 'cajaActual', 'esVendedor', 'misPendientes', 'ultimoConductor'
        ));
    }

    public function store(StoreVentaRequest $request)
    {
        $validated = $request->validated();

        $subtotal        = collect($validated['detalles'])->sum(fn($d) => $d['cantidad'] * $d['precio_unitario']);
        $tipoComprobante = $validated['tipo_comprobante'] ?? 'boleta';
        $condicionPago   = $validated['condicion_pago'] ?? 'contado';

        // Vendedor siempre genera venta pendiente de cobro
        if (auth()->user()->role->nombre === 'Vendedor') {
            $condicionPago = 'pendiente_cobro';
        }

        $esPendienteCobro = $condicionPago === 'pendiente_cobro';
        $esCredito        = $condicionPago === 'credito';
        $metodoPago       = $validated['metodo_pago'] ?? null;
        $pago             = ($metodoPago && !$esCredito && !$esPendienteCobro) ? ['metodo_pago' => $metodoPago] : null;
        $creditoData      = $esCredito ? ($validated['credito'] ?? []) : null;

        // Resolver sucursal del almacén seleccionado
        $sucursalId = Sucursal::where('almacen_id', $validated['almacen_id'])->value('id');

        $datosVenta = [
            'user_id'          => auth()->id(),
            'cliente_id'       => $validated['cliente_id'] ?? null,
            'almacen_id'       => $validated['almacen_id'],
            'sucursal_id'      => $sucursalId,
            'fecha'            => now()->toDateString(),
            'subtotal'         => $subtotal,
            'igv'              => 0,
            'total'            => $subtotal,
            'observaciones'    => $validated['observaciones'] ?? null,
            'tipo_comprobante' => $tipoComprobante,
            'condicion_pago'   => $condicionPago,
            'pagos_detalle'    => $validated['pagos_detalle'] ?? null,
            // Guía de remisión (nueva estructura)
            'guia_data'        => $validated['guia_data'] ?? null,
        ];

        try {
            $service = app(VentaService::class);

            if ($tipoComprobante === 'cotizacion') {
                $venta = $service->crearCotizacion($datosVenta, $validated['detalles']);
                $msg   = 'Cotización guardada exitosamente.';
            } elseif ($esCredito) {
                $venta = $service->crearVenta($datosVenta, $validated['detalles'], null, $creditoData);
                $msg   = 'Venta a crédito registrada exitosamente. Se han generado las cuotas de pago.';
            } elseif ($esPendienteCobro) {
                $datosVenta['estado_pago']    = 'pendiente';
                $datosVenta['condicion_pago'] = 'contado';
                $venta = $service->crearVenta($datosVenta, $validated['detalles'], null);
                $msg   = 'Venta pendiente registrada. El cajero procesará el cobro.';
            } else {
                $venta = $service->crearVenta($datosVenta, $validated['detalles'], $pago);
                $msg   = 'Venta registrada exitosamente.';
            }

            if ($request->wantsJson()) {
                return response()->json(['venta_id' => $venta->id]);
            }

            // Vendedor y Cajero permanecen en el POS con modal de éxito
            if (in_array(auth()->user()->role->nombre, ['Vendedor', 'Cajero'])) {
                return redirect()->route('ventas.create')->with('venta_exitosa', [
                    'codigo' => $venta->codigo,
                    'total'  => number_format($venta->total, 2),
                    'id'     => $venta->id,
                    'es_cotizacion' => $tipoComprobante === 'cotizacion',
                ]);
            }

            return redirect()->route('ventas.show', $venta)->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cotizaciones()
    {
        $user = auth()->user();

        $cotizaciones = Venta::with('vendedor', 'cliente', 'almacen')
            ->where('tipo_comprobante', 'cotizacion')
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total'   => Venta::where('tipo_comprobante', 'cotizacion')
                ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
                ->count(),
            'hoy'     => Venta::where('tipo_comprobante', 'cotizacion')
                ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
                ->whereDate('fecha', today())->count(),
            'monto'   => Venta::where('tipo_comprobante', 'cotizacion')
                ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
                ->sum('total'),
        ];

        return view('ventas.cotizaciones', compact('cotizaciones', 'stats'));
    }

    public function convertir(Request $request, Venta $venta)
    {
        $validated = $request->validate([
            'tipo_comprobante' => 'required|in:boleta,factura',
            'metodo_pago'      => 'required|in:efectivo,transferencia,yape,plin,mixto',
            'imeis'            => 'nullable|array',
            'imeis.*'          => 'array',
            'imeis.*.*'        => 'string',
        ]);

        // Validar RUC si se emite factura
        if ($validated['tipo_comprobante'] === 'factura') {
            $cliente = $venta->cliente;
            if (!$cliente) {
                return back()->withErrors(['tipo_comprobante' => 'Para emitir factura, la cotización debe tener un cliente con RUC.']);
            }
            if ($cliente->tipo_documento !== 'RUC') {
                return back()->withErrors(['tipo_comprobante' => 'Para emitir factura, el cliente debe tener RUC (actualmente tiene ' . $cliente->tipo_documento . ').']);
            }
            if (strlen($cliente->numero_documento) !== 11) {
                return back()->withErrors(['tipo_comprobante' => 'Para emitir factura, el RUC debe tener exactamente 11 dígitos.']);
            }
        }

        try {
            app(VentaService::class)->convertirAVenta(
                $venta,
                $validated['tipo_comprobante'],
                $validated['metodo_pago'],
                $validated['imeis'] ?? []
            );

            return redirect()
                ->route('ventas.show', $venta)
                ->with('success', 'Cotización convertida a ' . $validated['tipo_comprobante'] . ' exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(Venta $venta)
    {
        $venta->load('vendedor', 'confirmador', 'cliente', 'almacen', 'sucursal', 'serieComprobante',
            'detalles.producto.categoria', 'detalles.producto.unidadMedida', 'detalles.variante.color',
            'detalles.imei', 'imeis', 'guiaRemision', 'cuentaPorCobrar.cuotas');

        return view('ventas.show', compact('venta'));
    }

    public function guiaPdf(Venta $venta)
    {
        $venta->load('cliente', 'almacen', 'detalles.producto.unidadMedida', 'detalles.variante', 'detalles.imei', 'imeis', 'guiaRemision');

        $guia = $venta->guiaRemision;

        if (!$guia) {
            abort(404, 'Esta venta no tiene guía de remisión.');
        }

        $empresa = Empresa::first();

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
            'chroot'               => public_path(),
        ])->loadView('pdf.guia-remision', compact('venta', 'guia', 'empresa'));

        $pdf->setPaper('A4', 'portrait');

        $filename = 'guia-' . $venta->codigo . '.pdf';
        return $pdf->stream($filename);
    }

    public function pdf(Request $request, Venta $venta)
    {
        $formato = $request->get('formato', 'a4'); // a4 | ticket

        $venta->load('vendedor', 'cliente', 'almacen', 'sucursal', 'serieComprobante', 'guiaRemision',
            'detalles.producto.unidadMedida', 'detalles.variante.color', 'detalles.imei', 'imeis');

        $empresa  = Empresa::first() ?? new Empresa(['razon_social' => config('app.name'), 'ruc' => '']);
        $sucursal = $venta->sucursal ?? Sucursal::where('almacen_id', $venta->almacen_id)->first();
        $pagos    = $sucursal
            ? $sucursal->pagos()->where('activo', true)->get()->keyBy('tipo_pago')
            : collect();

        $view = $formato === 'ticket' ? 'pdf.factura-ticket' : 'pdf.factura-a4';

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
            'chroot'               => public_path(),
        ])->loadView($view, compact('venta', 'empresa', 'sucursal', 'pagos'));

        if ($formato === 'ticket') {
            // 80mm = 226.77pt ancho, altura dinámica amplia
            $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        $filename = 'comprobante-' . ($venta->numero_documento ?? $venta->codigo) . '.pdf';
        return $pdf->stream($filename);
    }

    public function consultaPublica(string $codigo)
    {
        $venta = Venta::where('codigo', $codigo)
            ->whereIn('tipo_comprobante', ['boleta', 'factura', 'nota_venta'])
            ->firstOrFail();

        $venta->load('vendedor', 'cliente', 'almacen', 'sucursal', 'serieComprobante', 'guiaRemision',
            'detalles.producto.unidadMedida', 'detalles.variante.color', 'detalles.imei', 'imeis');

        $empresa  = Empresa::first() ?? new Empresa(['razon_social' => config('app.name'), 'ruc' => '']);
        $sucursal = $venta->sucursal ?? Sucursal::where('almacen_id', $venta->almacen_id)->first();
        $pagos    = $sucursal
            ? $sucursal->pagos()->where('activo', true)->get()->keyBy('tipo_pago')
            : collect();

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
            'chroot'               => public_path(),
        ])->loadView('pdf.factura-a4', compact('venta', 'empresa', 'sucursal', 'pagos'));

        $pdf->setPaper('A4', 'portrait');

        $filename = 'comprobante-' . $venta->codigo . '.pdf';
        return $pdf->stream($filename);
    }

    public function colaCaja(Request $request)
    {
        $user = auth()->user();

        $ventas = Venta::with(['cliente', 'detalles.producto', 'detalles.variante.color', 'vendedor'])
            ->where('estado_pago', 'pendiente')
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
            ->orderBy('created_at')
            ->get();

        return view('cajero.cola', compact('ventas'));
    }

    public function colaJson(Request $request)
    {
        $user = auth()->user();

        $ventas = Venta::with(['cliente', 'detalles.producto', 'detalles.variante.color', 'vendedor'])
            ->where('estado_pago', 'pendiente')
            ->when($user->almacen_id, fn($q) => $q->where('almacen_id', $user->almacen_id))
            ->orderBy('created_at')
            ->get();

        return response()->json($ventas->map(fn($v) => [
            'id'            => $v->id,
            'codigo'        => $v->codigo,
            'total'         => round((float) $v->total, 2),
            'cliente'       => $v->cliente ? trim($v->cliente->nombre . ' ' . ($v->cliente->apellido ?? '')) : null,
            'vendedor'      => $v->vendedor?->name ?? '—',
            'hora_creacion' => $v->created_at->format('H:i'),
            'detalles'      => $v->detalles->map(fn($d) => [
                'id'       => $d->id,
                'cantidad' => $d->cantidad,
                'producto' => trim(($d->producto?->nombre ?? '—') . ($d->variante ? ' · ' . $d->variante->nombre_completo : '')),
                'subtotal' => round((float) $d->subtotal_con_igv, 2),
            ])->values(),
        ])->values());
    }

    public function confirmarPago(Request $request, Venta $venta)
    {
        $validated = $request->validate([
            'metodo_pago'              => 'required|in:efectivo,transferencia,yape,plin,mixto',
            'pagos_detalle'            => 'nullable|array',
            'pagos_detalle.*.metodo'   => 'required_with:pagos_detalle|in:efectivo,transferencia,yape,plin',
            'pagos_detalle.*.monto'    => 'required_with:pagos_detalle|numeric|min:0.01',
            'pagos_detalle.*.referencia' => 'nullable|string|max:100',
            'formato_impresion'        => 'nullable|in:ticket,a4',
        ]);

        try {
            app(VentaService::class)->confirmarPago(
                $venta->id,
                $validated['metodo_pago'],
                auth()->id(),
                ['pagos_detalle' => $validated['pagos_detalle'] ?? null]
            );

            $formato = $validated['formato_impresion'] ?? 'ticket';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => true,
                    'venta_id'  => $venta->id,
                    'print_url' => route('ventas.pdf', ['venta' => $venta->id, 'formato' => $formato]),
                ]);
            }

            return redirect()->route('cajero.cola')->with('success', 'Pago confirmado — ' . $venta->codigo);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function misPendientes()
    {
        $ventas = Venta::with(['cliente', 'detalles.producto', 'detalles.variante.color'])
            ->where('user_id', auth()->id())
            ->where('estado_pago', 'pendiente')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        return response()->json($ventas->map(fn($v) => [
            'id'          => $v->id,
            'codigo'      => $v->codigo,
            'total'       => $v->total,
            'cliente'     => $v->cliente ? trim($v->cliente->nombre . ' ' . ($v->cliente->apellido ?? '')) : null,
            'hora'        => $v->created_at->format('H:i'),
            'hace'        => $v->created_at->diffForHumans(),
            'items_count' => $v->detalles->count(),
            'detalles'    => $v->detalles->map(fn($d) => [
                'id'       => $d->id,
                'producto' => trim(($d->producto?->nombre ?? '—') . ($d->variante ? ' · ' . $d->variante->nombre_completo : '')),
                'cantidad' => $d->cantidad,
                'subtotal' => (float) $d->subtotal_con_igv,
            ])->values(),
        ])->values());
    }

    public function imeisDisponibles(Request $request)
    {
        $productoId = $request->input('producto_id');
        $almacenId  = $request->input('almacen_id');
        $varianteId = $request->input('variante_id');

        $base = Imei::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('estado_imei', 'en_stock');

        if ($varianteId) {
            // Verificar si hay IMEIs con variante_id asignado para esta variante
            $tieneConVariante = (clone $base)->where('variante_id', $varianteId)->exists();

            if ($tieneConVariante) {
                // Filtrado estricto por variante_id
                $base->where('variante_id', $varianteId);
            } else {
                // Fallback: filtrar por color_id de la variante (registros sin variante_id aún)
                $variante = \App\Models\ProductoVariante::find($varianteId);
                if ($variante?->color_id) {
                    $base->where(function ($q) use ($varianteId, $variante) {
                        $q->where('variante_id', $varianteId)
                          ->orWhere(fn($s) => $s->whereNull('variante_id')->where('color_id', $variante->color_id));
                    });
                } else {
                    // Sin color_id en la variante, filtrar solo por variante_id
                    $base->where('variante_id', $varianteId);
                }
            }
        }

        return response()->json(
            $base->get(['id', 'codigo_imei', 'color_id', 'variante_id'])
        );
    }
    public function editVenta(Venta $venta)
    {
        $ventanaMaxima = config('ventas.edit_window_hours', 24);
        $horasTranscurridas = $venta->created_at->diffInHours(now());

        if ($venta->estado_pago === 'anulado') {
            return redirect()->route('ventas.show', $venta)->with('error', 'No se puede editar una venta anulada.');
        }

        if ($horasTranscurridas > $ventanaMaxima) {
            return redirect()->route('ventas.show', $venta)
                ->with('error', "Solo se pueden editar comprobantes dentro de las {$ventanaMaxima} horas de emisión.");
        }

        $venta->load('guiaRemision', 'cliente');

        return view('ventas.edit', compact('venta', 'ventanaMaxima'));
    }

    public function updateVenta(UpdateVentaRequest $request, Venta $venta)
    {
        $requirioClave = $this->claveTiendaVerificada($venta);

        try {
            app(VentaService::class)->editarVenta($venta, $request->validated(), $requirioClave);
            return redirect()->route('ventas.show', [$venta, 'actualizado' => 1]);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function anularVenta(Request $request, Venta $venta)
    {
        $requirioClave = $this->claveTiendaVerificada($venta);

        try {
            $cajaActualizada = app(VentaService::class)->anularVenta($venta, $requirioClave);
            $mensaje = 'Venta anulada correctamente. El stock ha sido revertido.';
            if (!$cajaActualizada) {
                $mensaje .= ' Aviso: no se encontró ninguna caja asociada a esta venta — registra manualmente el egreso de S/ '
                    . number_format($venta->total, 2) . ' cuando corresponda.';
            }
            return redirect()->route('ventas.show', $venta)->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function generarNotaCredito(Request $request, Venta $venta)
    {
        $request->validate([
            'motivo_codigo' => 'required|string|size:2',
        ]);

        if (!array_key_exists($request->motivo_codigo, \App\Services\VentaService::MOTIVOS_NC)) {
            return back()->with('error', 'Motivo de nota de crédito inválido.');
        }

        $requirioClave = $this->claveTiendaVerificada($venta);

        try {
            $nc = app(VentaService::class)->generarNotaCredito(
                $venta,
                $request->motivo_codigo,
                $requirioClave
            );

            $mensaje = "Nota de Crédito {$nc->codigo} generada correctamente. Envíala a SUNAT para completar el proceso.";
            if (!$nc->getAttribute('caja_actualizada')) {
                $mensaje .= ' Aviso: no se encontró ninguna caja asociada a esta venta — registra manualmente el egreso de S/ '
                    . number_format($nc->total, 2) . ' cuando corresponda.';
            }

            return redirect()
                ->route('ventas.show', $nc)
                ->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function eliminarVenta(Request $request, Venta $venta)
    {
        $requirioClave = $this->claveTiendaVerificada($venta);

        try {
            $cajaActualizada = app(VentaService::class)->eliminarVenta($venta, $requirioClave);
            $mensaje = 'Venta eliminada correctamente. El stock ha sido revertido.';
            if (!$cajaActualizada) {
                $mensaje .= ' Aviso: no se encontró el ingreso original en caja para revertirlo — verifica manualmente si corresponde restar S/ '
                    . number_format($venta->total, 2) . '.';
            }
            return redirect()->route('ventas.index')->with('success', $mensaje);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Verificar contraseña propia para rol Tienda antes de acción sensible.
     * POST /ventas/{venta}/verificar-clave — devuelve JSON
     */
    public function verificarClave(Request $request, Venta $venta)
    {
        $rol = auth()->user()->role->nombre;

        // Admin no necesita verificar clave
        if ($rol === 'Administrador') {
            return response()->json(['ok' => true]);
        }

        $request->validate(['clave' => 'required|string']);

        if (!Hash::check($request->clave, auth()->user()->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'Contraseña incorrecta.'], 422);
        }

        // Token válido por 3 minutos, de un solo uso
        session(['venta_clave_ok.' . $venta->id => now()->addMinutes(3)->timestamp]);

        return response()->json(['ok' => true]);
    }

    /**
     * Bitácora de auditoría — solo Administrador
     */
    public function bitacora(Request $request)
    {
        $query = AuditoriaVenta::with('venta', 'usuario')
            ->orderByDesc('created_at');

        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $registros = $query->paginate(20)->withQueryString();

        $usuarios = \App\Models\User::orderBy('name')->get(['id', 'name']);

        return view('ventas.auditoria.index', compact('registros', 'usuarios'));
    }

    /**
     * Helper: verifica si el token de clave ya fue validado para esta venta.
     * - Admin → siempre true (no necesita clave)
     * - Tienda → consume token de sesión (un solo uso)
     * Retorna true si requirió clave (Tienda), false si es Admin.
     */
    private function claveTiendaVerificada(Venta $venta): bool
    {
        $rol = auth()->user()->role->nombre;

        if ($rol === 'Administrador') {
            return false; // Admin no necesita clave
        }

        $key       = 'venta_clave_ok.' . $venta->id;
        $expiry    = session($key);

        if (!$expiry || now()->timestamp > $expiry) {
            abort(403, 'Se requiere verificación de contraseña para realizar esta acción.');
        }

        // Consumir token (un solo uso)
        session()->forget($key);

        return true; // Sí requirió clave (rol Tienda)
    }

    public function showCredito(Venta $venta)
    {
        if (!$venta->es_credito) {
            return redirect()->route('ventas.show', $venta)->with('error', 'Esta venta no es a crédito.');
        }

        $venta->load('cliente', 'vendedor', 'almacen', 'detalles.producto');
        $cuenta = $venta->cuentaPorCobrar()->with(['cuotas', 'pagos.usuario'])->firstOrFail();

        return view('ventas.credito.show', compact('venta', 'cuenta'));
    }

    public function registrarPagoCredito(Request $request, Venta $venta)
    {
        $validated = $request->validate([
            'monto'          => 'required|numeric|min:0.01',
            'metodo_pago'    => 'required|in:efectivo,transferencia,yape,plin',
            'fecha_pago'     => 'required|date|before_or_equal:today',
            'cuota_cobro_id' => 'nullable|exists:cuotas_cobro,id',
            'referencia'     => 'nullable|string|max:100',
            'observaciones'  => 'nullable|string|max:300',
        ]);

        $cuenta = $venta->cuentaPorCobrar;

        if (!$cuenta || !$venta->es_credito) {
            return back()->with('error', 'Esta venta no tiene cuenta por cobrar.');
        }

        if ($cuenta->estado === 'anulado') {
            return back()->with('error', 'La cuenta por cobrar está anulada.');
        }

        if ($cuenta->estado === 'pagado') {
            return back()->with('error', 'Esta cuenta ya fue pagada completamente.');
        }

        // Verificar que el monto no exceda el saldo pendiente
        $saldo = (float) $cuenta->monto_total - (float) $cuenta->monto_pagado;
        if ((float) $validated['monto'] > round($saldo, 2) + 0.01) {
            return back()->with('error', 'El monto excede el saldo pendiente de S/ ' . number_format($saldo, 2));
        }

        try {
            app(VentaService::class)->registrarPagoCredito($cuenta, $validated);
            return redirect()->route('ventas.credito.show', $venta)
                ->with('success', 'Pago de S/ ' . number_format($validated['monto'], 2) . ' registrado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function dashboardTienda()
{
    $user = auth()->user();
    
    // Ventas del día actual
    $ventas_dia = Venta::whereDate('fecha', today())
        ->when($user->role->nombre === 'Vendedor', function ($query) use ($user) {
            return $query->where('user_id', $user->id);
        })
        ->sum('total');
    
    // Otras estadísticas que puedas necesitar
    $ventas_pendientes = Venta::where('estado_pago', 'pendiente')
        ->when($user->role->nombre === 'Vendedor', function ($query) use ($user) {
            return $query->where('user_id', $user->id);
        })
        ->count();
    
    $ventas_mes = Venta::whereMonth('fecha', now()->month)
        ->whereYear('fecha', now()->year)
        ->when($user->role->nombre === 'Vendedor', function ($query) use ($user) {
            return $query->where('user_id', $user->id);
        })
        ->sum('total');
    
    $ultimas_ventas = Venta::with('cliente')
        ->when($user->role->nombre === 'Vendedor', function ($query) use ($user) {
            return $query->where('user_id', $user->id);
        })
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    return view('dashboards.tienda', compact(
        'ventas_dia', 
        'ventas_pendientes', 
        'ventas_mes', 
        'ultimas_ventas'
    ));
}
}
