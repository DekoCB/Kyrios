<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Almacen;
use App\Models\StockAlmacen;
use App\Models\Imei;
use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        
        $query = Producto::with(['categoria', 'marca', 'modelo']);

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

        // Stock por almacén (productos de cantidad)
        $stocksPorProducto = StockAlmacen::whereIn('producto_id', $productoIds)
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->keyBy('almacen_id'));

        // Conteo de IMEIs en_stock por producto y almacén (productos de serie)
        $imeisPorProducto = Imei::whereIn('producto_id', $productoIds)
            ->where('estado_imei', 'en_stock')
            ->selectRaw('producto_id, almacen_id, COUNT(*) as total')
            ->groupBy('producto_id', 'almacen_id')
            ->get()
            ->groupBy('producto_id')
            ->map(fn($rows) => $rows->pluck('total', 'almacen_id'));

        foreach ($productos as $producto) {
            if ($producto->tipo_inventario === 'serie') {
                // Para serie: construir pseudo-stocks desde el conteo de IMEIs
                $imeiMap = $imeisPorProducto[$producto->id] ?? collect();
                $producto->stocks = $imeiMap->mapWithKeys(fn($total, $almacenId) => [
                    $almacenId => (object)['cantidad' => $total, 'almacen_id' => $almacenId],
                ]);
                $producto->es_serie = true;
            } else {
                $producto->stocks = $stocksPorProducto[$producto->id] ?? collect();
                $producto->es_serie = false;
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

        $query = MovimientoInventario::with(['producto', 'almacen', 'almacenDestino', 'usuario'])
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
            'almacen_origen_id' => 'required|exists:almacenes,id',
            'cantidad' => 'required|integer|min:1',
            'motivo' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Verificar que el almacén origen sea diferente al destino
            if ($validated['almacen_origen_id'] == auth()->user()->almacen_id) {
                throw new \Exception('No puedes solicitar traslado desde tu propio almacén');
            }

            // Verificar stock en origen
            $stockOrigen = StockAlmacen::where([
                'producto_id' => $validated['producto_id'],
                'almacen_id' => $validated['almacen_origen_id']
            ])->first();

            if (!$stockOrigen || $stockOrigen->cantidad < $validated['cantidad']) {
                throw new \Exception('Stock insuficiente en el almacén de origen');
            }

            // Crear solicitud de traslado como MovimientoInventario pendiente
            $traslado = MovimientoInventario::create([
                'producto_id'       => $validated['producto_id'],
                'almacen_id'        => $validated['almacen_origen_id'],
                'almacen_destino_id'=> auth()->user()->almacen_id,
                'user_id'           => auth()->id(),
                'tipo_movimiento'   => 'transferencia',
                'cantidad'          => $validated['cantidad'],
                'stock_anterior'    => $stockOrigen->cantidad,
                'stock_nuevo'       => $stockOrigen->cantidad - $validated['cantidad'],
                'motivo'            => $validated['motivo'] ?? 'Solicitud desde tienda',
                'estado'            => 'pendiente',
                'documento_referencia' => 'SOL-' . date('Ymd') . '-' . rand(1000, 9999),
            ]);

            // Reservar (descontar) el stock en origen inmediatamente, igual que
            // TrasladoService::crearLineaAccesorio — TrasladoService::confirmarRecepcion
            // asume que el origen ya fue descontado y solo acredita el destino; sin este
            // descuento, confirmar la solicitud duplicaba el stock (aparecía en destino
            // sin haber salido de origen).
            $stockOrigen->decrement('cantidad', $validated['cantidad']);

            $totalStock = StockAlmacen::where('producto_id', $validated['producto_id'])->sum('cantidad');
            Producto::where('id', $validated['producto_id'])->update(['stock_actual' => $totalStock]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de traslado creada correctamente',
                'traslado' => $traslado
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
     * Cancelar una solicitud de traslado (solo si está pendiente)
     */
    public function cancelarSolicitud(MovimientoInventario $traslado)
    {
        try {
            DB::beginTransaction();

            if ($traslado->almacen_destino_id != auth()->user()->almacen_id) {
                throw new \Exception('No tienes permiso para cancelar esta solicitud');
            }

            if ($traslado->estado != 'pendiente') {
                throw new \Exception('Solo se pueden cancelar solicitudes pendientes');
            }

            // Devolver al origen la cantidad reservada al crear la solicitud
            // (ver solicitarTraslado, que ahora descuenta de inmediato).
            $stockOrigen = StockAlmacen::firstOrCreate(
                ['producto_id' => $traslado->producto_id, 'almacen_id' => $traslado->almacen_id],
                ['cantidad' => 0]
            );
            $stockOrigen->increment('cantidad', $traslado->cantidad);

            $totalStock = StockAlmacen::where('producto_id', $traslado->producto_id)->sum('cantidad');
            Producto::where('id', $traslado->producto_id)->update(['stock_actual' => $totalStock]);

            $traslado->update(['estado' => 'cancelado']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud cancelada correctamente'
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