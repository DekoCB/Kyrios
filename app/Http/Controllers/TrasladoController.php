<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\Imei;
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
        $todos = MovimientoInventario::with('producto', 'almacen', 'almacenDestino', 'usuario')
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
            'productos.*.imei_ids'    => 'nullable|array',
            'productos.*.imei_ids.*'  => 'nullable|exists:imeis,id',
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

        $todosProductos = MovimientoInventario::with(['producto.unidadMedida', 'imeisTrasladados.imei', 'almacen', 'almacenDestino'])
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
        $traslado->load('producto', 'almacen', 'almacenDestino', 'usuario', 'usuarioConfirma', 'imeisTrasladados.imei');

        // Cargar todos los productos del mismo traslado (mismo numero_guia)
        $todosProductos = $traslado->numero_guia
            ? MovimientoInventario::with(['producto', 'imeisTrasladados.imei'])
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
                'almacen',
                'almacenDestino',
                'usuario',
                'imeisTrasladados.imei',
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
        $query = Producto::with(['categoria'])->where('estado', 'activo');

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }
        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('codigo', 'like', '%' . $request->buscar . '%');
            });
        }

        $productos    = $query->orderBy('nombre')->paginate(25)->withQueryString();
        $productoIds  = $productos->pluck('id');

        $stocksPorProducto = StockAlmacen::whereIn('producto_id', $productoIds)
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->keyBy('almacen_id'));

        $imeisPorProducto = Imei::whereIn('producto_id', $productoIds)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->selectRaw('producto_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'almacen_id')
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        foreach ($productos as $producto) {
            if ($producto->tipo_inventario === 'serie') {
                $imeiMap         = $imeisPorProducto[$producto->id] ?? collect();
                $producto->stocks   = $imeiMap->mapWithKeys(fn($total, $aid) => [$aid => (object)['cantidad' => $total]]);
                $producto->es_serie = true;
            } else {
                $producto->stocks   = $stocksPorProducto[$producto->id] ?? collect();
                $producto->es_serie = false;
            }
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

    /**
     * AJAX: IMEIs en_stock para un producto + almacén origen.
     */
    public function imeisDisponibles(Request $request)
    {
        try {
            $request->validate([
                'producto_id' => 'required|exists:productos,id',
                'almacen_id'  => 'required|exists:almacenes,id',
                'variante_id' => 'nullable|exists:producto_variantes,id',
            ]);

            $imeis = Imei::where('producto_id', $request->producto_id)
                ->where('almacen_id', $request->almacen_id)
                ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                ->when($request->filled('variante_id'), fn($q) => $q->where('variante_id', $request->variante_id))
                ->orderBy('codigo_imei')
                ->get(['id', 'codigo_imei', 'serie', 'variante_id']);

            return response()->json($imeis);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error'   => 'Parámetros inválidos.',
                'details' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error al cargar IMEIs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $q        = trim($request->get('q', ''));
        $almacenId = (int) $request->get('almacen_id', 0);

        $productos = Producto::with(['variantesActivas.color'])
            ->where('estado', 'activo')
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                      ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['id', 'nombre', 'codigo', 'tipo_inventario']);

        $productoIds = $productos->pluck('id');

        $stocksMap = StockAlmacen::whereIn('producto_id', $productoIds)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('cantidad', 'almacen_id'));

        $imeisMap = Imei::whereIn('producto_id', $productoIds)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->when($almacenId, fn($q) => $q->where('almacen_id', $almacenId))
            ->selectRaw('producto_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'almacen_id')
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        $imeisPorVariante = collect();
        if ($almacenId) {
            $imeisPorVariante = Imei::whereIn('producto_id', $productoIds)
                ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                ->where('almacen_id', $almacenId)
                ->whereNotNull('variante_id')
                ->selectRaw('variante_id, COUNT(*) as total')
                ->groupBy('variante_id')
                ->pluck('total', 'variante_id');
        }

        $result = $productos->map(function ($p) use ($stocksMap, $imeisMap, $imeisPorVariante, $almacenId) {
            $esSerie = $p->tipo_inventario === 'serie';
            $stock   = null;

            if ($almacenId) {
                $stock = $esSerie
                    ? (int) ($imeisMap[$p->id][$almacenId] ?? 0)
                    : (int) ($stocksMap[$p->id][$almacenId] ?? 0);
            }

            return [
                'id'              => $p->id,
                'nombre'          => $p->nombre,
                'codigo'          => $p->codigo,
                'tipo_inventario' => $p->tipo_inventario,
                'es_serie'        => $esSerie,
                'stock_origen'    => $stock,
                'tiene_variantes' => $p->variantesActivas->isNotEmpty(),
                'variantes'       => $p->variantesActivas->map(fn($v) => [
                    'id'           => $v->id,
                    'nombre'       => $v->nombre_completo,
                    'sku'          => $v->sku ?? '',
                    'color_nombre' => $v->color?->nombre ?? null,
                    'color_hex'    => $v->color?->codigo_hex ?? null,
                    'capacidad'    => $v->capacidad ?? null,
                    'stock'        => $esSerie
                        ? (int) ($imeisPorVariante[$v->id] ?? 0)
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
            $results = Cliente::where('documento', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->orWhere('apellido', 'like', "%{$termino}%")
                ->limit(8)
                ->get(['id', 'documento', \Illuminate\Support\Facades\DB::raw("CONCAT(nombre, ' ', COALESCE(apellido,'')) as nombre")]);
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
