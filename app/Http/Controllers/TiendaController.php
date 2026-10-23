<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Almacen;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use App\Services\TrasladoService;
use Illuminate\Http\Request;

class TiendaController extends Controller
{
    /**
     * Constructor - Solo rol Tienda puede acceder
     */
    public function __construct()
    {
        $this->middleware('role:Tienda');
    }

    /**
     * Ver inventario de todas las tiendas/almacenes
     */
    public function inventario(Request $request)
    {
        $tiendaActual = auth()->user()->almacen_id;
        
        $query = Producto::with(['categoria', 'marca', 'modelo', 'variantesActivas.color']);

        // Filtros
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('codigo', 'like', '%' . $request->buscar . '%');
            });
        }

        $productos = $query->paginate(20);

        $productoIds = $productos->pluck('id');

        // Stock por almacén de productos por cantidad. stock_almacen tiene una fila por
        // (producto, variante, almacén): se arma el total por producto y el desglose por variante.
        $filasStock = StockAlmacen::whereIn('producto_id', $productoIds)->get();

        $stocksPorProducto = $filasStock
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->groupBy('almacen_id')
                ->map(fn($r) => (object) ['cantidad' => (int) $r->sum('cantidad')]));

        $stocksPorVarianteCantidad = $filasStock->whereNotNull('variante_id')
            ->groupBy('variante_id')
            ->map(fn($rows) => $rows->keyBy('almacen_id'));

        $stocksSinVariante = $filasStock->whereNull('variante_id')
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->keyBy('almacen_id'));

        foreach ($productos as $producto) {
            if ($producto->variantesActivas->isNotEmpty()) {
                // Por cantidad CON variantes: una fila por variante con su stock en cada tienda
                $filas = $producto->variantesActivas->map(fn($variante) => (object) [
                    'variante' => $variante,
                    'stocks'   => $stocksPorVarianteCantidad[$variante->id] ?? collect(),
                ]);

                $sinVariante = $stocksSinVariante[$producto->id] ?? collect();
                if ($sinVariante->isNotEmpty()) {
                    $filas->push((object) ['variante' => null, 'stocks' => $sinVariante]);
                }
                $producto->filas = $filas;
            } else {
                $producto->filas = collect([(object) [
                    'variante' => null,
                    'stocks'   => $stocksPorProducto[$producto->id] ?? collect(),
                ]]);
            }
        }

        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        $almacenes = Almacen::where('estado', 'activo')->orderBy('nombre')->get();
        $tiendaActual = Almacen::find($tiendaActual);

        return view('tienda.inventario', compact('productos', 'categorias', 'almacenes', 'tiendaActual'));
    }

    /**
     * Ver solicitudes de traslado de la tienda actual
     */
    public function solicitudes(Request $request)
    {
        $tiendaActual = auth()->user()->almacen_id;

        $query = MovimientoInventario::with(['producto', 'variante.color', 'almacen', 'almacenDestino', 'usuario'])
            ->where('tipo_movimiento', 'transferencia')
            ->where('almacen_destino_id', $tiendaActual)
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $solicitudes = $query->paginate(20);

        return view('tienda.solicitudes', compact('solicitudes'));
    }

    /**
     * Crear una solicitud de traslado
     */
    public function solicitarTraslado(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'variante_id' => 'nullable|exists:producto_variantes,id',
            'almacen_origen_id' => 'required|exists:almacenes,id',
            'cantidad' => 'required|integer|min:1',
            'motivo' => 'nullable|string|max:255',
        ]);

        try {
            // Verificar que el almacén origen sea diferente al destino
            if ($validated['almacen_origen_id'] == auth()->user()->almacen_id) {
                throw new \Exception('No puedes solicitar traslado desde tu propio almacén');
            }

            $producto = Producto::findOrFail($validated['producto_id']);

            $linea = [
                'producto_id' => $producto->id,
                'variante_id' => $validated['variante_id'] ?? null,
            ];

            $linea['cantidad'] = $validated['cantidad'];

            // Reutiliza TrasladoService: la misma lógica de reserva de stock que el flujo de traslados de almacén.
            $numeroGuia = app(TrasladoService::class)->crearTraslado([
                'almacen_id'         => $validated['almacen_origen_id'],
                'almacen_destino_id' => auth()->user()->almacen_id,
                'user_id'            => auth()->id(),
                'observaciones'      => $validated['motivo'] ?? 'Solicitud desde tienda',
                'productos'          => [$linea],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Solicitud de traslado creada correctamente ({$numeroGuia})",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar una solicitud de traslado (solo si está pendiente)
     */
    public function cancelarSolicitud(MovimientoInventario $traslado)
    {
        try {
            if ($traslado->almacen_destino_id != auth()->user()->almacen_id) {
                throw new \Exception('No tienes permiso para cancelar esta solicitud');
            }

            // TrasladoService::anularTraslado ya valida el estado 'pendiente' y revierte el stock.
            app(TrasladoService::class)->anularTraslado(
                $traslado->id,
                auth()->id(),
                'Cancelado por la tienda destino'
            );

            return response()->json([
                'success' => true,
                'message' => 'Solicitud cancelada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de un producto (stock en todas las tiendas)
     */
    public function verProducto(Producto $producto)
    {
        $producto->load(['categoria', 'marca', 'modelo']);
        
        $stocks = StockAlmacen::where('producto_id', $producto->id)
            ->with(['almacen', 'variante'])
            ->get();

        return view('tienda.producto', compact('producto', 'stocks'));
    }

    /**
     * Obtener stock de un producto en tiempo real (AJAX)
     */
    public function getStockProducto(Request $request)
    {
        $productoId = $request->get('producto_id');
        $almacenId = $request->get('almacen_id');

        // Con variante: stock de esa variante en el almacén; sin variante: total del producto
        $varianteId = $request->get('variante_id');
        $cantidad = $varianteId
            ? StockAlmacen::cantidadEn($productoId, $almacenId, (int) $varianteId)
            : StockAlmacen::totalEn($productoId, $almacenId);

        return response()->json([
            'success' => true,
            'stock' => $cantidad
        ]);
    }
}