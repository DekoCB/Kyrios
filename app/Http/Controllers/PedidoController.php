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
        $query = Pedido::with('proveedor', 'usuario', 'almacen', 'compra', 'etapas')
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

        if (in_array($request->tipo_compra, ['nacional', 'internacional'], true)) {
            $query->where('tipo_compra', $request->tipo_compra);
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
        $productos   = \App\Support\EtiquetaProducto::enriquecerSelector(app(VarianteService::class)->getProductosParaSelectorCompra());

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
            'tipo_compra' => 'nullable|in:nacional,internacional',
            'moneda' => 'nullable|in:PEN,USD',
            'tipo_cambio' => 'nullable|numeric|min:0.001|max:99',
            'incoterm' => 'nullable|in:' . implode(',', Pedido::INCOTERMS),
            'condicion_pago' => 'nullable|string|max:120',
            'lugar_entrega' => 'nullable|string|max:255',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.variante_id' => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_referencial' => 'nullable|numeric|min:0',
        ]);

        // La variante de cada línea tiene que ser del producto de esa línea
        foreach ($validated['detalles'] as $i => $linea) {
            if (!empty($linea['variante_id'])
                && !\App\Models\ProductoVariante::where('id', $linea['variante_id'])->where('producto_id', $linea['producto_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(["detalles.$i.variante_id" => 'La variante seleccionada no pertenece al producto de esa línea.']);
            }
        }

        $tipo = $validated['tipo_compra'] ?? 'nacional';
        $moneda = $validated['moneda'] ?? ($tipo === 'internacional' ? 'USD' : 'PEN');

        // En dólares sobre una orden nacional hace falta el tipo de cambio para convertir a soles
        if ($moneda === 'USD' && $tipo === 'nacional' && empty($validated['tipo_cambio'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tipo_cambio' => 'Indica el tipo de cambio: la orden está en dólares.']);
        }

        $totales = Pedido::calcularTotales($validated['detalles'], $tipo);

        $pedido = Pedido::create($totales + [
            'tipo_compra' => $tipo,
            'moneda' => $moneda,
            'tipo_cambio' => $validated['tipo_cambio'] ?? null,
            'incoterm' => $tipo === 'internacional' ? ($validated['incoterm'] ?? null) : null,
            'condicion_pago' => $validated['condicion_pago'] ?? null,
            'lugar_entrega' => $validated['lugar_entrega'] ?? null,
            'proveedor_id' => $validated['proveedor_id'],
            'almacen_id' => $validated['almacen_id'],
            'user_id' => auth()->id(),
            'fecha' => $validated['fecha'],
            'fecha_esperada' => $validated['fecha_esperada'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
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

        $pedido->asegurarSeguimiento();

        return redirect()
            ->route('pedidos.show', $pedido)
            ->with('success', "Orden de compra {$pedido->codigo} creada exitosamente");
    }

    public function show(Pedido $pedido)
    {
        // Las órdenes anteriores al seguimiento reciben sus etapas la primera vez que se abren
        if ($pedido->estado !== 'cancelado') {
            $pedido->asegurarSeguimiento();
        }
        $pedido->load('proveedor', 'almacen', 'usuario', 'compra', 'detalles.producto', 'detalles.variante', 'etapas');

        return view('pedidos.show', compact('pedido'));
    }

    /** Orden de compra lista para imprimir o enviar al proveedor. */
    public function pdf(Pedido $pedido)
    {
        $pedido->load('proveedor', 'almacen', 'usuario', 'detalles.producto', 'detalles.variante');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden-compra', [
            'pedido' => $pedido,
            'empresa' => \App\Models\Empresa::instancia(),
        ])->setPaper('a4')->stream("{$pedido->codigo}.pdf");
    }

    /**
     * Cambiar estado del pedido. "recibido" NO se asigna aquí — solo se
     * alcanza generando la Compra real desde compras/create?pedido_id=X
     * (ver CompraService::registrarCompra), para que la mercadería quede
     * efectivamente registrada en stock/cuenta por pagar.
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
}
