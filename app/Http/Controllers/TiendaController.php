<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Almacen;
use App\Models\StockAlmacen;
use App\Models\Imei;
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

        // Stock por almacén (productos de cantidad; el stock por variante NO está
        // desglosado por almacén en el esquema actual — producto_variantes.stock_actual
        // es global, así que aquí solo se puede mostrar a nivel de producto).
        $stocksPorProducto = StockAlmacen::whereIn('producto_id', $productoIds)
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->keyBy('almacen_id'));

        // Conteo de IMEIs en_stock por variante y almacén (productos de serie:
        // aquí sí existe el dato por tienda vía imeis.almacen_id + imeis.variante_id).
        $imeisPorVarianteAlmacen = Imei::whereIn('producto_id', $productoIds)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->whereNotNull('variante_id')
            ->selectRaw('variante_id, almacen_id, COUNT(*) as total')
            ->groupBy('variante_id', 'almacen_id')
            ->get()
            ->groupBy('variante_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id')
                ->mapWithKeys(fn($total, $almacenId) => [$almacenId => (object) ['cantidad' => $total]]));

        // Red de seguridad: IMEIs de serie sin variante asignada (datos legados).
        $imeisSinVariantePorProducto = Imei::whereIn('producto_id', $productoIds)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->whereNull('variante_id')
            ->selectRaw('producto_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'almacen_id')
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id')
                ->mapWithKeys(fn($total, $almacenId) => [$almacenId => (object) ['cantidad' => $total]]));

        foreach ($productos as $producto) {
            if ($producto->tipo_inventario === 'serie') {
                $producto->es_serie = true;

                $filas = $producto->variantesActivas->map(fn($variante) => (object) [
                    'variante' => $variante,
                    'stocks'   => $imeisPorVarianteAlmacen[$variante->id] ?? collect(),
                ]);

                $sinVariante = $imeisSinVariantePorProducto[$producto->id] ?? collect();
                if ($sinVariante->isNotEmpty() || $filas->isEmpty()) {
                    $filas->push((object) ['variante' => null, 'stocks' => $sinVariante]);
                }

                $producto->filas = $filas;
            } else {
                $producto->es_serie = false;
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

            if ($producto->tipo_inventario === 'serie') {
                // Reservar IMEIs concretos en_stock del almacén origen (y de la
                // variante elegida, si el usuario filtró por color/capacidad).
                $imeiIds = Imei::where('producto_id', $producto->id)
                    ->where('almacen_id', $validated['almacen_origen_id'])
                    ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                    ->when($validated['variante_id'] ?? null, fn($q, $vid) => $q->where('variante_id', $vid))
                    ->orderBy('codigo_imei')
                    ->limit($validated['cantidad'])
                    ->pluck('id');

                if ($imeiIds->count() < $validated['cantidad']) {
                    throw new \Exception('Stock insuficiente en el almacén de origen');
                }

                $linea['imei_ids'] = $imeiIds->all();
            } else {
                $linea['cantidad'] = $validated['cantidad'];
            }

            // Reutiliza TrasladoService: es la misma lógica (y las mismas tablas,
            // traslado_imeis incluida) que usa el flujo de traslados de almacén.
            // Antes esta acción duplicaba la reserva de stock a mano y nunca creaba
            // los registros de traslado_imeis, así que confirmar una solicitud de
            // celular desde el panel de traslados fallaba con "no tiene IMEIs
            // asignados".
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

            // TrasladoService::anularTraslado ya valida el estado 'pendiente' y
            // revierte correctamente tanto IMEIs (vuelven a en_stock) como stock
            // por cantidad, según el tipo de producto.
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
            ->with('almacen')
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

        $stock = StockAlmacen::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();

        return response()->json([
            'success' => true,
            'stock' => $stock ? $stock->cantidad : 0
        ]);
    }
}