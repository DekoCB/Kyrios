<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\GuiaRemision;
use App\Models\Empresa;
use App\Services\TrasladoService;
use App\Services\SunatService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrasladoController extends Controller
{
    public function index()
    {
        // Agrupar por numero_guia para mostrar un traslado por grupo
        $todos = MovimientoInventario::with('producto', 'variante', 'almacen', 'almacenDestino', 'usuario')
            ->where('tipo_movimiento', 'transferencia')
            ->noEliminados()
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar: key = numero_guia o "id:{id}" si no tiene guía
        $traslados = $todos->groupBy(fn($m) => $m->numero_guia ?: "id:{$m->id}");

        return view('traslados.index', compact('traslados'));
    }

    public function create(Request $request)
    {
        $almacenes = Almacen::where('estado', 'activo')
            ->with(['sucursal.series' => fn($q) => $q->where('tipo_comprobante', '09')->where('activo', true)])
            ->orderBy('nombre')
            ->get();

        $guiaSeriesMap = $almacenes->mapWithKeys(function ($alm) {
            $serie = $alm->sucursal?->series->first();
            if (!$serie) return [$alm->id => null];
            return [$alm->id => [
                'serie_id' => $serie->id,
                'numero'   => $serie->serie . '-' . str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT),
            ]];
        })->filter();

        return view('traslados.create', compact(
            'almacenes', 'guiaSeriesMap'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'almacen_id'              => 'required|exists:almacenes,id',
            'almacen_destino_id'      => 'required|exists:almacenes,id|different:almacen_id',
            'numero_guia'             => 'nullable|string|max:50',
            'guia_serie_id'           => 'nullable|exists:series_comprobantes,id',
            'observaciones'           => 'nullable|string',
            'productos'               => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad'    => 'nullable|integer|min:1',
            'productos.*.variante_id' => 'nullable|exists:producto_variantes,id',
        ], [
            'almacen_destino_id.different' => 'El almacén destino debe ser diferente al origen.',
            'productos.required'           => 'Debe agregar al menos un producto.',
            'productos.min'                => 'Debe agregar al menos un producto.',
        ]);

        try {
            $numeroGuia = app(TrasladoService::class)->crearTraslado(
                array_merge($validated, ['user_id' => auth()->id()])
            );

            return redirect()
                ->route('guias-remision.create', [
                    'from_traslado'      => $numeroGuia,
                    'almacen_id'         => $validated['almacen_id'],
                    'almacen_destino_id' => $validated['almacen_destino_id'],
                ])
                ->with('info', "Traslado {$numeroGuia} registrado. Ahora completa los datos de transporte para emitir la guía.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function guiaPdf(MovimientoInventario $traslado)
    {
        $guia = GuiaRemision::with(['proveedor', 'cliente'])->where('numero_guia', $traslado->numero_guia)->first();

        if (!$guia) {
            abort(404, 'Este traslado no tiene guía de remisión.');
        }

        $todosProductos = MovimientoInventario::with(['producto.unidadMedida', 'variante', 'almacen', 'almacenDestino'])
            ->where('numero_guia', $traslado->numero_guia)
            ->where('tipo_movimiento', 'transferencia')
            ->orderBy('id')
            ->get();

        $empresa = Empresa::first();

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
            'chroot'               => public_path(),
        ])->loadView('pdf.guia-remision-traslado', compact('traslado', 'todosProductos', 'guia', 'empresa'));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('guia-' . $traslado->numero_guia . '.pdf');
    }

    public function show(MovimientoInventario $traslado)
    {
        $traslado->load('producto', 'variante', 'almacen', 'almacenDestino', 'usuario', 'usuarioConfirma');

        // Cargar todos los productos del mismo traslado (mismo numero_guia)
        $todosProductos = $traslado->numero_guia
            ? MovimientoInventario::with('producto', 'variante')
                ->where('numero_guia', $traslado->numero_guia)
                ->where('tipo_movimiento', 'transferencia')
                ->orderBy('id')
                ->get()
            : collect([$traslado]);

        $guia = $traslado->numero_guia
            ? GuiaRemision::where('numero_guia', $traslado->numero_guia)->first()
            : null;

        return view('traslados.show', compact('traslado', 'todosProductos', 'guia'));
    }

    public function pendientes()
    {
        $movimientos = MovimientoInventario::with([
                'producto',
                'variante',
                'almacen',
                'almacenDestino',
                'usuario',
            ])
            ->where('tipo_movimiento', 'transferencia')
            ->where('estado', 'pendiente')
            ->noEliminados()
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por numero_guia
        $grupos = $movimientos->groupBy(fn($m) => $m->numero_guia ?: "id:{$m->id}");

        return view('traslados.pendientes', compact('grupos'));
    }

    public function stock(Request $request)
    {
        $query = Producto::with(['categoria', 'variantesActivas'])->where('estado', 'activo');

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }
        if ($request->filled('buscar')) {
            \App\Support\EtiquetaProducto::buscar($query, (string) $request->buscar);
        }

        $productos    = $query->orderBy('nombre')->paginate(25)->withQueryString();
        $productoIds  = $productos->pluck('id');

        // Una fila por (producto, variante, almacén): aquí se muestra el total del producto por almacén
        $stocksPorProducto = StockAlmacen::whereIn('producto_id', $productoIds)
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->groupBy('almacen_id')
                ->map(fn($r) => (object) ['cantidad' => (int) $r->sum('cantidad')]));

        foreach ($productos as $producto) {
            $producto->stocks = $stocksPorProducto[$producto->id] ?? collect();
        }

        $almacenes  = Almacen::where('estado', 'activo')->orderBy('nombre')->get();
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();

        return view('traslados.stock', compact('productos', 'almacenes', 'categorias'));
    }

    public function confirmar(Request $request, MovimientoInventario $traslado)
    {
        try {
            app(TrasladoService::class)->confirmarRecepcion(
                $traslado->id,
                auth()->id()
            );

            return redirect()
                ->route('traslados.pendientes')
                ->with('success', 'Traslado confirmado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function anular(Request $request, MovimientoInventario $traslado)
    {
        $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            app(TrasladoService::class)->anularTraslado(
                $traslado->id,
                auth()->id(),
                $request->input('motivo')
            );

            return redirect()
                ->route('traslados.index')
                ->with('success', 'Traslado anulado correctamente. El stock fue devuelto al almacén origen.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function eliminar(Request $request, MovimientoInventario $traslado)
    {
        $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            app(TrasladoService::class)->eliminarTraslado(
                $traslado->id,
                auth()->id(),
                $request->input('motivo')
            );

            return redirect()
                ->route('traslados.index')
                ->with('success', 'Traslado eliminado del historial correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $q        = trim($request->get('q', ''));
        $almacenId = (int) $request->get('almacen_id', 0);

        $productos = \App\Support\EtiquetaProducto::buscar(
                Producto::with(['variantesActivas.color'])->where('estado', 'activo'), $q
            )
            ->limit(15)
            ->get(['id', 'nombre', 'nombre_fabrica', 'nombre_kyrios', 'codigo']);

        $productoIds = $productos->pluck('id');

        $filasStock = StockAlmacen::whereIn('producto_id', $productoIds)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->get();

        // Total por producto y almacén (suma de sus variantes)
        $stocksMap = $filasStock->groupBy('producto_id')
            ->map(fn($rows) => $rows->groupBy('almacen_id')->map(fn($r) => (int) $r->sum('cantidad')));

        // Stock por variante y almacén (productos por cantidad)
        $stocksVariante = $filasStock->whereNotNull('variante_id')
            ->groupBy('variante_id')
            ->map(fn($rows) => $rows->pluck('cantidad', 'almacen_id'));

        $result = $productos->map(function ($p) use ($stocksMap, $stocksVariante, $almacenId) {
            $stock = $almacenId ? (int) ($stocksMap[$p->id][$almacenId] ?? 0) : null;

            return [
                'id'              => $p->id,
                'nombre'          => \App\Support\EtiquetaProducto::nombre($p),
                'codigo'          => \App\Support\EtiquetaProducto::codigos(['codigo' => $p->codigo, 'variantes' => $p->variantesActivas]),
                'stock_origen'    => $stock,
                'tiene_variantes' => $p->variantesActivas->isNotEmpty(),
                'variantes'       => $p->variantesActivas->map(fn($v) => [
                    'id'           => $v->id,
                    'nombre'       => $v->nombre_completo,
                    'sku'          => \App\Support\EtiquetaProducto::codigos($p, $v) ?: ($v->sku ?? ''),
                    'color_nombre' => $v->color?->nombre ?? null,
                    'color_hex'    => $v->color?->codigo_hex ?? null,
                    'capacidad'    => $v->capacidad ?? null,
                    'stock'        => $almacenId
                        ? (int) ($stocksVariante[$v->id][$almacenId] ?? 0)   // stock de la variante en el almacén de origen
                        : (int) ($v->stock_actual ?? 0),
                ])->values(),
            ];
        });

        if ($almacenId) {
            $result = $result->filter(fn($item) => $item['stock_origen'] > 0)->values();
        }

        return response()->json($result);
    }

    public function buscarDestinatario(Request $request): JsonResponse
    {
        $termino = trim($request->input('buscar', ''));
        $tipo    = $request->input('tipo', 'proveedor');

        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        if ($tipo === 'proveedor') {
            $results = Proveedor::where('ruc', 'like', "%{$termino}%")
                ->orWhere('razon_social', 'like', "%{$termino}%")
                ->limit(8)
                ->get(['id', 'ruc', 'razon_social as nombre']);
        } else {
            // La tabla clientes guarda el documento en numero_documento y el nombre completo en nombre
            $results = Cliente::where('numero_documento', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->limit(8)
                ->get(['id', 'numero_documento as documento', 'nombre']);
        }

        return response()->json($results);
    }

    public function buscarRuc(string $ruc): JsonResponse
    {
        $result = app(SunatService::class)->consultarRuc($ruc);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'error' => $result['message'] ?? 'RUC no encontrado. Ingrese el nombre manualmente.',
            ], 404);
        }

        // Se devuelve el detalle completo que entrega la API (razón social,
        // dirección desglosada, ubigeo, estado, condición, etc.), no solo el
        // nombre: el front lo usa para mostrar toda la info del transportista.
        $data           = $result['data'];
        $data['nombre'] = $data['razon_social'] ?? null;

        return response()->json($data);
    }
}
