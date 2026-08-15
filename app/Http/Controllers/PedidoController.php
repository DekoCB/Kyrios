<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Services\VarianteService;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $query = Pedido::with('proveedor', 'usuario', 'almacen', 'compra')
            ->orderBy('created_at', 'desc');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('codigo', 'like', "%{$q}%")
                  ->orWhereHas('proveedor', fn($p) => $p->where('razon_social', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        // Estadísticas sobre el total sin filtrar (no solo la página actual)
        $stats = [
            'total'     => Pedido::count(),
            'pendiente' => Pedido::where('estado', 'pendiente')->count(),
            'aprobado'  => Pedido::where('estado', 'aprobado')->count(),
            'recibido'  => Pedido::where('estado', 'recibido')->count(),
        ];

        $pedidos = $query->paginate(15)->withQueryString();

        return view('pedidos.index', compact('pedidos', 'stats'));
    }

    public function create()
    {
        $proveedores = Proveedor::activos()->orderBy('razon_social')->get();
        $productos   = app(VarianteService::class)->getProductosParaSelectorCompra();

        $almacenesCentral = Almacen::where('estado', 'activo')
            ->whereIn('tipo', ['principal', 'deposito', 'temporal'])
            ->orderBy('nombre')
            ->get();

        $almacenesTienda = Almacen::where('estado', 'activo')
            ->where('tipo', 'tienda')
            ->orderBy('nombre')
            ->get();

        return view('pedidos.create', compact('proveedores', 'productos', 'almacenesCentral', 'almacenesTienda'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'fecha' => 'required|date',
            'fecha_esperada' => 'nullable|date|after_or_equal:fecha',
            'observaciones' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.variante_id' => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_referencial' => 'nullable|numeric|min:0',
        ]);

        $pedido = Pedido::create([
            'proveedor_id' => $validated['proveedor_id'],
            'almacen_id' => $validated['almacen_id'],
            'user_id' => auth()->id(),
            'fecha' => $validated['fecha'],
            'fecha_esperada' => $validated['fecha_esperada'],
            'observaciones' => $validated['observaciones'],
            'estado' => 'pendiente',
        ]);

        foreach ($validated['detalles'] as $detalle) {
            DetallePedido::create([
                'pedido_id' => $pedido->id,
                'producto_id' => $detalle['producto_id'],
                'variante_id' => $detalle['variante_id'] ?? null,
                'cantidad' => $detalle['cantidad'],
                'precio_referencial' => $detalle['precio_referencial'] ?? null,
            ]);
        }

        return redirect()
            ->route('pedidos.index')
            ->with('success', 'Pedido creado exitosamente');
    }

    public function show(Pedido $pedido)
    {
        $pedido->load('proveedor', 'almacen', 'usuario', 'compra', 'detalles.producto', 'detalles.variante');

        return view('pedidos.show', compact('pedido'));
    }

    /**
     * Cambiar estado del pedido. "recibido" NO se asigna aquí — solo se
     * alcanza generando la Compra real desde compras/create?pedido_id=X
     * (ver CompraService::registrarCompra), para que la mercadería quede
     * efectivamente registrada en stock/IMEIs/cuenta por pagar.
     */
    public function cambiarEstado(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'estado' => 'required|in:pendiente,aprobado,cancelado',
        ]);

        $pedido->update(['estado' => $validated['estado']]);

        return redirect()
            ->back()
            ->with('success', 'Estado del pedido actualizado');
    }

    public function pedidosProveedor()
    {
        $user = auth()->user();

        $proveedor = Proveedor::where('email', $user->email)
            ->orWhere('ruc', $user->dni)
            ->first();

        $pedidos = collect();

        if ($proveedor) {
            $pedidos = Pedido::with('usuario', 'detalles.producto')
                ->where('proveedor_id', $proveedor->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('pedidos.proveedor', compact('pedidos'));
    }
}
