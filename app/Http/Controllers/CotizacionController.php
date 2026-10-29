<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Services\CotizacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Módulo de cotizaciones (propio, separado de ventas). Se convierten a nota de venta, boleta o factura.
 */
class CotizacionController extends Controller
{
    public function __construct(private CotizacionService $service)
    {
    }

    /** El vendedor solo ve y trabaja sus cotizaciones; el resto de roles con acceso, todas. */
    private function autorizar(Cotizacion $cotizacion): void
    {
        $user = auth()->user();
        abort_if($user->role?->nombre === 'Vendedor' && $cotizacion->user_id !== $user->id, 403, 'Esta cotización es de otro vendedor.');
    }

    private function reglas(): array
    {
        return [
            'cliente_id'                  => 'required|exists:clientes,id',
            'almacen_id'                  => 'required|exists:almacenes,id',
            'validez_dias'                => 'required|integer|min:1|max:365',
            'condiciones'                 => 'nullable|string|max:3000',
            'observaciones'               => 'nullable|string|max:2000',
            'detalles'                    => 'required|array|min:1|max:200',
            'detalles.*.producto_id'      => 'required|exists:productos,id',
            'detalles.*.variante_id'      => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad'         => 'required|integer|min:1|max:100000',
            'detalles.*.precio_unitario'  => 'required|numeric|min:0.01|max:9999999',
            'detalles.*.incluye_igv'      => 'nullable|boolean',
        ];
    }

    // ── Listado ───────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();
        $base = Cotizacion::query()->when($user->role?->nombre === 'Vendedor', fn ($q) => $q->where('user_id', $user->id));

        $query = (clone $base)->with(['cliente', 'vendedor', 'almacen'])->latest('fecha')->latest('id');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(fn ($w) => $w->where('codigo', 'like', "%{$q}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%")->orWhere('numero_documento', 'like', "%{$q}%")));
        }
        if ($request->filled('estado')) {
            match ($request->estado) {
                'vencida'  => $query->vencidas(),
                'pendiente', 'aceptada' => $query->where('estado', $request->estado)->whereDate('fecha_vencimiento', '>=', today()),
                default    => $query->where('estado', $request->estado),
            };
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $stats = [
            'total'       => (clone $base)->count(),
            'vigentes'    => (clone $base)->vigentes()->count(),
            'monto_vigente' => (float) (clone $base)->vigentes()->sum('total'),
            'vencidas'    => (clone $base)->vencidas()->count(),
            'convertidas' => (clone $base)->where('estado', 'convertida')->count(),
            'monto_convertido' => (float) (clone $base)->where('estado', 'convertida')->sum('total'),
        ];

        $cotizaciones = $query->paginate(20)->withQueryString();

        return view('cotizaciones.index', compact('cotizaciones', 'stats'));
    }

    // ── Alta y edición ────────────────────────────────────────────────────

    public function create(Request $request)
    {
        return view('cotizaciones.form', $this->datosFormulario(null, $request->integer('cliente_id') ?: null));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglas());

        try {
            $cotizacion = $this->service->crear(
                ['user_id' => auth()->id()] + collect($validated)->except('detalles')->all(),
                $validated['detalles']
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cotizaciones.show', $cotizacion)->with('success', 'Cotización ' . $cotizacion->codigo . ' creada.');
    }

    public function edit(Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);

        if (!$cotizacion->puede_editarse) {
            return redirect()->route('cotizaciones.show', $cotizacion)->with('error', 'Solo se puede editar una cotización pendiente.');
        }

        return view('cotizaciones.form', $this->datosFormulario($cotizacion->load(['detalles.producto', 'detalles.variante', 'cliente'])));
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $validated = $request->validate($this->reglas());

        try {
            $this->service->actualizar($cotizacion, collect($validated)->except('detalles')->all(), $validated['detalles']);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cotizaciones.show', $cotizacion)->with('success', 'Cotización actualizada.');
    }

    private function datosFormulario(?Cotizacion $cotizacion, ?int $clienteId = null): array
    {
        $user = auth()->user();
        $almacenes = Almacen::activos()->orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        $almacenPredeterminado = $cotizacion?->almacen_id
            ?? ($user->almacen_id ?: ($almacenes->count() === 1 ? $almacenes->first()->id : null));

        // Líneas iniciales: las de la cotización (edición) o las que se enviaron antes de un error de validación
        if ($enviadas = old('detalles')) {
            $lineas = collect($enviadas)->map(function ($l) {
                $producto = Producto::find($l['producto_id'] ?? 0);
                $variante = !empty($l['variante_id']) ? ProductoVariante::find($l['variante_id']) : null;

                return [
                    'producto_id' => $l['producto_id'] ?? null, 'variante_id' => $l['variante_id'] ?? null,
                    'codigo' => $producto ? \App\Support\EtiquetaProducto::codigos($producto, $variante) : '',
                    'descripcion' => ($producto ? \App\Support\EtiquetaProducto::nombre($producto) : '') . ($variante && $variante->nombre_completo !== 'Variante base' ? ' — ' . $variante->nombre_completo : ''),
                    'cantidad' => $l['cantidad'] ?? 1, 'precio_unitario' => $l['precio_unitario'] ?? 0,
                ];
            })->values();
        } else {
            $lineas = ($cotizacion?->detalles ?? collect())->map(fn ($d) => [
                'producto_id' => $d->producto_id, 'variante_id' => $d->variante_id, 'codigo' => $d->codigos_mostrar, 'descripcion' => $d->nombre_mostrar,
                'cantidad' => $d->cantidad, 'precio_unitario' => (float) $d->precio_con_igv,
            ])->values();
        }

        $cliente = $cotizacion?->cliente ?? ($clienteId ? Cliente::find($clienteId) : null);

        return [
            'cotizacion'  => $cotizacion,
            'almacenes'   => $almacenes,
            'almacenId'   => old('almacen_id', $almacenPredeterminado),
            'lineas'      => $lineas,
            'cliente'     => old('cliente_id') ? Cliente::find(old('cliente_id')) : $cliente,
            'validez'     => old('validez_dias', $cotizacion?->validez_dias ?? config('kyrios.cotizacion.validez_dias', 7)),
            'condiciones' => old('condiciones', $cotizacion?->condiciones ?? config('kyrios.cotizacion.condiciones')),
        ];
    }

    // ── Ver / PDF ─────────────────────────────────────────────────────────

    public function show(Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $cotizacion->load(['cliente', 'vendedor', 'almacen', 'detalles.producto', 'detalles.variante', 'venta']);

        // Stock disponible por línea en el almacén de la cotización (aviso antes de convertir)
        $stock = $cotizacion->detalles->mapWithKeys(fn ($d) => [
            $d->id => $d->variante_id
                ? StockAlmacen::cantidadEn($d->producto_id, $cotizacion->almacen_id, $d->variante_id)
                : StockAlmacen::totalEn($d->producto_id, $cotizacion->almacen_id),
        ]);

        $user = auth()->user();
        $esVendedor = $user->role?->nombre === 'Vendedor';

        return view('cotizaciones.show', compact('cotizacion', 'stock', 'esVendedor'));
    }

    public function pdf(Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $cotizacion->load(['cliente', 'vendedor', 'almacen', 'sucursal', 'detalles.producto', 'detalles.variante']);

        $empresa = Empresa::first() ?? new Empresa(['razon_social' => config('app.name'), 'ruc' => '']);
        $sucursal = $cotizacion->sucursal ?? Sucursal::where('almacen_id', $cotizacion->almacen_id)->first();
        $pagos = $sucursal ? $sucursal->pagos()->where('activo', true)->get()->keyBy('tipo_pago') : collect();

        return Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
            'chroot'               => public_path(),
        ])->loadView('pdf.cotizacion', compact('cotizacion', 'empresa', 'sucursal', 'pagos'))
            ->setPaper('A4', 'portrait')
            ->stream('cotizacion-' . $cotizacion->codigo . '.pdf');
    }

    // ── Acciones ──────────────────────────────────────────────────────────

    public function estado(Request $request, Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $datos = $request->validate([
            'estado' => 'required|in:pendiente,aceptada,rechazada,anulada',
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            $this->service->cambiarEstado($cotizacion, $datos['estado'], $datos['motivo'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cotización marcada como ' . $datos['estado'] . '.');
    }

    public function renovar(Request $request, Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $datos = $request->validate(['validez_dias' => 'required|integer|min:1|max:365']);

        try {
            $this->service->renovar($cotizacion, (int) $datos['validez_dias']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Validez renovada hasta el ' . $cotizacion->fresh()->fecha_vencimiento->format('d/m/Y') . '.');
    }

    public function duplicar(Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $copia = $this->service->duplicar($cotizacion->load('detalles'), auth()->user());

        return redirect()->route('cotizaciones.show', $copia)->with('success', 'Copia creada: ' . $copia->codigo . '.');
    }

    public function convertir(Request $request, Cotizacion $cotizacion)
    {
        $this->autorizar($cotizacion);
        $datos = $request->validate([
            'tipo_comprobante' => 'required|in:nota_venta,boleta,factura',
            'condicion'        => 'nullable|in:contado,pendiente_cobro',
            'metodo_pago'      => 'nullable|in:efectivo,transferencia,yape,plin,mixto',
        ]);

        try {
            $venta = $this->service->convertir($cotizacion, $datos['tipo_comprobante'], $datos, auth()->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            // Errores del flujo de venta: stock insuficiente, caja cerrada, etc.
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('ventas.show', $venta)
            ->with('success', 'Cotización ' . $cotizacion->codigo . ' convertida en ' . str_replace('_', ' ', $datos['tipo_comprobante']) . '.');
    }

    // ── Búsquedas (JSON) para el formulario ───────────────────────────────

    public function clientes(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Cliente::where('estado', 'activo')
                ->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('numero_documento', 'like', "%{$q}%"))
                ->orderBy('nombre')->limit(15)
                ->get(['id', 'nombre', 'tipo_documento', 'numero_documento', 'telefono', 'email'])
        );
    }

    /** Variantes (y productos sin variantes) que se pueden cotizar, con precio sugerido y stock en el almacén. */
    public function productos(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $almacenId = (int) $request->query('almacen_id');
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $variantes = ProductoVariante::activas()
            ->whereHas('producto', fn ($p) => $p->where('estado', 'activo'))
            ->where(function ($w) use ($q) {
                $w->where('codigo_kyrios', 'like', "%{$q}%")->orWhere('codigo_fabricante', 'like', "%{$q}%")->orWhere('codigo_excel', 'like', "%{$q}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$q}%")->orWhere('nombre_fabrica', 'like', "%{$q}%")
                        ->orWhere('nombre_kyrios', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"));
            })
            ->with(['producto:id,codigo,nombre,nombre_fabrica,nombre_kyrios,marca_id', 'producto.marca:id,nombre', 'imagenPrincipal'])
            ->orderBy('producto_id')->limit(20)->get();

        $sinVariantes = Producto::where('estado', 'activo')
            ->whereDoesntHave('variantesActivas')
            ->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('nombre_fabrica', 'like', "%{$q}%")
                ->orWhere('nombre_kyrios', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->with('marca:id,nombre')->limit(10)->get();

        $precios = ProductoPrecio::where('tipo_precio', 'venta_regular')->where('activo', true)->whereNull('almacen_id')
            ->whereIn('producto_id', $variantes->pluck('producto_id')->merge($sinVariantes->pluck('id'))->unique())
            ->orderByDesc('prioridad')->get();

        $precioDe = function (int $productoId, ?int $varianteId) use ($precios): ?float {
            $p = $precios->first(fn ($x) => $x->producto_id === $productoId && $x->variante_id === $varianteId)
                ?? $precios->first(fn ($x) => $x->producto_id === $productoId && $x->variante_id === null);

            return $p ? round((float) $p->precio * ($p->incluye_igv ? 1 : CotizacionService::IGV), 2) : null;
        };

        $items = $variantes->map(fn ($v) => [
            'producto_id' => $v->producto_id,
            'variante_id' => $v->id,
            'codigo'      => \App\Support\EtiquetaProducto::codigos($v->producto, $v),
            'nombre'      => \App\Support\EtiquetaProducto::nombre($v->producto),
            'detalle'     => $v->nombre_completo !== 'Variante base' ? $v->nombre_completo : null,
            'marca'       => $v->producto->marca?->nombre,
            'imagen'      => $v->imagenPrincipal?->url,
            'precio'      => $precioDe($v->producto_id, $v->id),
            'stock'       => $almacenId ? StockAlmacen::cantidadEn($v->producto_id, $almacenId, $v->id) : (int) $v->stock_actual,
            'dano'        => $v->estado_dano !== 'ninguno' ? $v->estado_dano : null,
        ]);

        $items = $items->merge($sinVariantes->map(fn ($p) => [
            'producto_id' => $p->id,
            'variante_id' => null,
            'codigo'      => $p->codigo,
            'nombre'      => \App\Support\EtiquetaProducto::nombre($p),
            'detalle'     => null,
            'marca'       => $p->marca?->nombre,
            'imagen'      => $p->imagen_url,
            'precio'      => $precioDe($p->id, null),
            'stock'       => $almacenId ? StockAlmacen::totalEn($p->id, $almacenId) : (int) $p->stock_actual,
            'dano'        => null,
        ]));

        return response()->json($items->values());
    }
}
