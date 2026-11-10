<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\PedidoInterno;
use App\Models\PedidoInternoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Notifications\AvisoSistema;
use App\Services\AlertaService;
use App\Services\TrasladoService;
use App\Services\VarianteService;
use App\Support\EtiquetaProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nota de pedido interno: un almacén o área pide mercadería a otro almacén de la empresa.
 * Atenderlo genera el traslado correspondiente.
 */
class PedidoInternoController extends Controller
{
    /** Roles que atienden pedidos (los demás solo piden y ven los suyos). */
    private const ATIENDEN = [Role::ADMINISTRACION, Role::LOGISTICA];

    private function atiende(): bool
    {
        return in_array(auth()->user()->role?->nombre, self::ATIENDEN, true);
    }

    /** Operaciones solo ve lo que pidió. */
    private function visibles()
    {
        $q = PedidoInterno::query();

        return $this->atiende() ? $q : $q->where('solicitante_id', auth()->id());
    }

    private function autorizarVer(PedidoInterno $pedido): void
    {
        abort_unless($this->atiende() || $pedido->solicitante_id === auth()->id(), 403);
    }

    public function index(Request $request)
    {
        $consulta = $this->visibles()->with(['solicitante', 'origen', 'destino'])->withCount('detalles')->latest('id');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $consulta->where(fn ($w) => $w->where('codigo', 'like', "%{$q}%")->orWhere('area', 'like', "%{$q}%")
                ->orWhereHas('solicitante', fn ($u) => $u->where('name', 'like', "%{$q}%")));
        }
        if (in_array($request->estado, ['pendiente', 'atendido', 'cancelado'], true)) {
            $consulta->where('estado', $request->estado);
        }

        $base = $this->visibles();

        return view('pedidos-internos.index', [
            'pedidos' => $consulta->paginate(15)->withQueryString(),
            'stats'   => [
                'pendiente' => (clone $base)->where('estado', 'pendiente')->count(),
                'atendido'  => (clone $base)->where('estado', 'atendido')->count(),
                'vencido'   => (clone $base)->where('estado', 'pendiente')->whereDate('fecha_requerida', '<', today())->count(),
            ],
            'atiende' => $this->atiende(),
        ]);
    }

    public function create()
    {
        $usuario = auth()->user();

        return view('pedidos-internos.create', [
            'productos' => EtiquetaProducto::enriquecerSelector(app(VarianteService::class)->getProductosParaSelectorCompra()),
            'almacenes' => Almacen::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'almacenUsuario' => $usuario->almacen_id,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'almacen_origen_id'  => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id|different:almacen_origen_id',
            'area'               => 'nullable|string|max:100',
            'fecha_requerida'    => 'nullable|date|after_or_equal:today',
            'observaciones'      => 'nullable|string|max:1000',
            'detalles'                => 'required|array|min:1',
            'detalles.*.producto_id'  => 'required|exists:productos,id',
            'detalles.*.variante_id'  => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad'     => 'required|integer|min:1|max:100000',
        ], [
            'almacen_destino_id.different' => 'El almacén que pide debe ser distinto del que atiende.',
            'detalles.required'            => 'Agrega al menos un producto.',
            'fecha_requerida.after_or_equal' => 'La fecha requerida no puede ser anterior a hoy.',
        ]);

        // Una línea por producto y variante: si se repite, se suman las cantidades
        $lineas = [];
        foreach ($validated['detalles'] as $i => $l) {
            $varianteId = $l['variante_id'] ?? null;
            $producto = Producto::findOrFail($l['producto_id']);

            if ($varianteId && !ProductoVariante::where('id', $varianteId)->where('producto_id', $producto->id)->exists()) {
                throw ValidationException::withMessages(["detalles.$i.variante_id" => 'La variante no pertenece al producto de esa línea.']);
            }
            if (!$varianteId && $producto->variantes()->where('estado', 'activo')->exists()) {
                throw ValidationException::withMessages(["detalles.$i.variante_id" => 'Elige la variante de «' . EtiquetaProducto::rotulo($producto) . '».']);
            }

            $clave = $producto->id . '-' . ($varianteId ?? '');
            $lineas[$clave] = ['producto_id' => $producto->id, 'variante_id' => $varianteId, 'cantidad' => ($lineas[$clave]['cantidad'] ?? 0) + (int) $l['cantidad']];
        }

        $pedido = DB::transaction(function () use ($validated, $lineas) {
            $pedido = PedidoInterno::create([
                'solicitante_id'     => auth()->id(),
                'almacen_origen_id'  => $validated['almacen_origen_id'],
                'almacen_destino_id' => $validated['almacen_destino_id'],
                'area'               => $validated['area'] ?? null,
                'fecha'              => today(),
                'fecha_requerida'    => $validated['fecha_requerida'] ?? null,
                'observaciones'      => $validated['observaciones'] ?? null,
                'estado'             => 'pendiente',
            ]);
            foreach ($lineas as $linea) {
                PedidoInternoDetalle::create($linea + ['pedido_interno_id' => $pedido->id]);
            }

            return $pedido;
        });

        app(AlertaService::class)->avisar(self::ATIENDEN, new AvisoSistema(
            "pedido-interno:{$pedido->id}",
            'Nuevo pedido interno',
            "{$pedido->codigo} de " . auth()->user()->name . ': ' . $pedido->origen->nombre . ' → ' . $pedido->destino->nombre . ' (' . count($lineas) . ' producto(s))',
            route('pedidos-internos.show', $pedido, false),
            'info'
        ));

        return redirect()->route('pedidos-internos.show', $pedido)->with('success', "Pedido interno {$pedido->codigo} registrado.");
    }

    public function show(PedidoInterno $pedidoInterno)
    {
        $this->autorizarVer($pedidoInterno);
        $pedidoInterno->load(['solicitante', 'atendidoPor', 'origen', 'destino', 'detalles.producto', 'detalles.variante']);

        // Lo que hay en el almacén que atiende, para saber si se puede cumplir completo
        $stock = $pedidoInterno->detalles->mapWithKeys(fn ($d) => [
            $d->id => StockAlmacen::cantidadEn($d->producto_id, $pedidoInterno->almacen_origen_id, $d->variante_id),
        ]);

        return view('pedidos-internos.show', ['pedido' => $pedidoInterno, 'stock' => $stock, 'atiende' => $this->atiende()]);
    }

    public function pdf(PedidoInterno $pedidoInterno)
    {
        $this->autorizarVer($pedidoInterno);
        $pedidoInterno->load(['solicitante', 'origen', 'destino', 'detalles.producto', 'detalles.variante']);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pedido-interno', [
            'pedido' => $pedidoInterno,
            'empresa' => \App\Models\Empresa::instancia(),
        ])->setPaper('a4')->stream("{$pedidoInterno->codigo}.pdf");
    }

    /** Atiende el pedido completo generando el traslado del almacén que atiende al que lo pidió. */
    public function atender(PedidoInterno $pedidoInterno)
    {
        try {
            $guia = DB::transaction(function () use ($pedidoInterno) {
                // Con el pedido bloqueado, dos clics o dos usuarios no generan dos traslados
                $pedido = PedidoInterno::whereKey($pedidoInterno->id)->lockForUpdate()->firstOrFail();
                if ($pedido->estado !== 'pendiente') {
                    throw new \RuntimeException('Este pedido ya fue ' . ($pedido->estado === 'atendido' ? 'atendido' : 'cancelado') . '.');
                }

                $guia = app(TrasladoService::class)->crearTraslado([
                    'almacen_id'         => $pedido->almacen_origen_id,
                    'almacen_destino_id' => $pedido->almacen_destino_id,
                    'user_id'            => auth()->id(),
                    'observaciones'      => "Atiende el pedido interno {$pedido->codigo}",
                    'productos'          => $pedido->detalles()->get(['producto_id', 'variante_id', 'cantidad'])->toArray(),
                ]);

                $pedido->update(['estado' => 'atendido', 'traslado_guia' => $guia, 'atendido_por' => auth()->id(), 'atendido_at' => now()]);

                return $guia;
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo atender: ' . $e->getMessage());
        }

        $pedidoInterno->refresh()->solicitante?->notify(new AvisoSistema(
            "pedido-interno-atendido:{$pedidoInterno->id}",
            'Pedido interno atendido',
            "{$pedidoInterno->codigo} salió en el traslado {$guia}.",
            route('pedidos-internos.show', $pedidoInterno, false),
            'info'
        ));

        return redirect()->route('pedidos-internos.show', $pedidoInterno)
            ->with('success', "Pedido atendido: traslado {$guia} registrado. Falta emitir la guía de remisión desde Traslados.");
    }

    public function cancelar(PedidoInterno $pedidoInterno)
    {
        $this->autorizarVer($pedidoInterno);

        $filas = PedidoInterno::whereKey($pedidoInterno->id)->where('estado', 'pendiente')->update(['estado' => 'cancelado', 'updated_at' => now()]);

        return redirect()->route('pedidos-internos.show', $pedidoInterno)
            ->with($filas ? 'success' : 'error', $filas ? 'Pedido cancelado.' : 'Solo se pueden cancelar los pedidos pendientes.');
    }
}
