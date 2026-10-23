<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Almacen;
use App\Models\Catalogo\MotivoMovimiento;
use Illuminate\Http\Request;

class MovimientoInventarioController extends Controller
{
    /**
     * Constructor - Solo Admin y Almacenero
     */
    public function __construct()
    {
        $this->middleware('role:Administrador,Almacenero');
    }

    /**
     * Mostrar historial de movimientos con filtros
     */
    public function index(Request $request)
    {
        $query = MovimientoInventario::with(['producto', 'almacen', 'usuario']);
        
        if ($request->filled('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }
        
        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }
        
        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }
        
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        
        $movimientos = $query->latest()->paginate(20);
        
        $productos = Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
        $almacenes = Almacen::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'tipo']);

        $stats = [
            'total_movimientos'   => MovimientoInventario::count(),
            'movimientos_hoy'     => MovimientoInventario::hoy()->count(),
            'ingresos_hoy'        => MovimientoInventario::hoy()->ingresos()->count(),
            'salidas_hoy'         => MovimientoInventario::hoy()->salidas()->count(),
            'transferencias_hoy'  => MovimientoInventario::hoy()->transferencias()->count(),
        ];

        // Movimientos de hoy por almacén (para el panel de tiendas)
        $movHoyPorAlmacen = MovimientoInventario::hoy()
            ->selectRaw('almacen_id, COUNT(*) as total')
            ->groupBy('almacen_id')
            ->pluck('total', 'almacen_id');

        return view('inventario.movimientos.index', compact(
            'movimientos', 'productos', 'almacenes', 'stats', 'movHoyPorAlmacen'
        ));
    }

    /**
     * Mostrar formulario para crear movimiento
     */
    public function create()
    {
        $productos        = Producto::activos()->with('variantesActivas')->orderBy('nombre')->get();
        // Stock por variante y almacén: [variante_id => [almacen_id => cantidad]]
        $stockVariantes   = \App\Models\StockAlmacen::whereNotNull('variante_id')->get()
            ->groupBy('variante_id')->map(fn ($f) => $f->pluck('cantidad', 'almacen_id'));
        $almacenesCentral = Almacen::activos()->whereIn('tipo', ['principal','deposito','temporal'])->orderBy('nombre')->get();
        $almacenesTienda  = Almacen::activos()->where('tipo', 'tienda')->orderBy('nombre')->get();
        $motivos          = MotivoMovimiento::activos()->orderBy('nombre')->get()->groupBy('tipo');

        return view('inventario.movimientos.create', compact('productos', 'stockVariantes', 'almacenesCentral', 'almacenesTienda', 'motivos'));
    }

    /**
     * Guardar nuevo movimiento
     */
    public function store(Request $request)
    {
        \Log::info('📥 Datos recibidos del formulario:', $request->all());
        
        // =====================================================
        // FIX 1: Validación condicional según tipo de producto
        // =====================================================
        $rules = [
            'producto_id' => 'required|exists:productos,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'tipo_movimiento'      => 'required|in:ingreso,salida,ajuste,transferencia,devolucion,merma',
            'motivo_movimiento_id' => 'required|exists:motivos_movimiento,id',
            'observaciones'        => 'nullable|string',
            'almacen_destino_id' => 'nullable|exists:almacenes,id',
            'numero_guia' => 'nullable|string|max:50',
            'variante_id' => 'nullable|exists:producto_variantes,id',
            'cantidad' => 'required|integer|min:1',
        ];
        
        // FIX 3: Almacén destino solo obligatorio en transferencias
        if ($request->tipo_movimiento === 'transferencia') {
            $rules['almacen_destino_id'] = 'required|exists:almacenes,id|different:almacen_id';
            $rules['numero_guia'] = 'required|string|max:50';
        }

        $messages = [
            'producto_id.required' => 'Debe seleccionar un producto.',
            'almacen_id.required' => 'Debe seleccionar un almacén.',
            'tipo_movimiento.required' => 'Debe seleccionar el tipo de movimiento.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'motivo_movimiento_id.required' => 'Debes seleccionar un motivo.',
            'motivo_movimiento_id.exists'   => 'El motivo seleccionado no es válido.',
            'variante_id.exists' => 'La variante seleccionada no es válida.',
            'almacen_destino_id.required' => 'Debe seleccionar un almacén destino para transferencias.',
            'almacen_destino_id.different' => 'El almacén destino debe ser diferente al almacén origen.',
            'numero_guia.required' => 'El número de guía es obligatorio para transferencias.',
        ];

        $validated = $request->validate($rules, $messages);

        try {
            // Preparar datos para el modelo
            $datos = [
                'producto_id' => $request->producto_id,
                'variante_id' => $request->variante_id,
                'almacen_id' => $request->almacen_id,
                'tipo_movimiento'      => $request->tipo_movimiento,
                'cantidad'            => $request->cantidad,
                'motivo_movimiento_id'=> $request->motivo_movimiento_id,
                'motivo'              => MotivoMovimiento::find($request->motivo_movimiento_id)?->nombre ?? '',
                'observaciones'       => $request->observaciones,
                'almacen_destino_id' => $request->almacen_destino_id,
                'numero_guia' => $request->numero_guia,
                'user_id' => auth()->id(),
            ];
            
            MovimientoInventario::registrarMovimiento($datos);

            return redirect()
                ->route('inventario.movimientos.index')
                ->with('success', 'Movimiento registrado exitosamente.');

        } catch (\Exception $e) {
            \Log::error('❌ Error en store:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error al registrar movimiento: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalle de un movimiento
     */
    public function show(MovimientoInventario $movimiento)
    {
        $movimiento->load(['producto', 'almacen', 'almacenDestino', 'usuario']);
        return view('inventario.movimientos.show', compact('movimiento'));
    }

    /**
     * API: Obtener stock actual de un producto
     */
    public function getStockActual(Request $request)
    {
        $productoId = $request->get('producto_id');
        
        if (!$productoId) {
            return response()->json(['error' => 'Producto no especificado'], 400);
        }
        
        $producto = Producto::find($productoId);
        
        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }
        
        return response()->json([
            'stock_actual' => $producto->stock_actual,
            'unidad_medida' => $producto->unidadMedida?->abreviatura,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
        ]);
    }

}