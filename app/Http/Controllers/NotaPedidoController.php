<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\NotaPedido;
use App\Models\StockAlmacen;
use App\Services\NotaPedidoService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;

/**
 * Notas de pedido. Las genera quien maneja las cotizaciones (Administración, Contador) y las atiende almacén (Logística).
 */
class NotaPedidoController extends Controller
{
    public function __construct(private NotaPedidoService $servicio)
    {
    }

    public function index(Request $request)
    {
        $estado = array_key_exists($request->query('estado'), NotaPedido::ESTADOS) || $request->query('estado') === 'abiertas' ? $request->query('estado') : 'abiertas';

        $notas = NotaPedido::with(['cliente', 'almacen', 'cotizacion'])->withCount('detalles')
            ->when($estado === 'abiertas', fn ($q) => $q->whereIn('estado', ['pendiente', 'en_preparacion', 'lista']))
            ->when($estado !== 'abiertas', fn ($q) => $q->where('estado', $estado))
            ->orderByRaw('fecha_entrega is null')->orderBy('fecha_entrega')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('notas-pedido.index', [
            'notas'     => $notas,
            'estado'    => $estado,
            'abiertas'  => NotaPedido::whereIn('estado', ['pendiente', 'en_preparacion', 'lista'])->count(),
            'puedeAtender' => $this->puedeAtender(),
        ]);
    }

    public function show(NotaPedido $nota)
    {
        $nota->load(['cliente', 'almacen', 'cotizacion', 'creador', 'atendedor', 'detalles.producto', 'detalles.variante']);

        // Stock disponible de cada línea en el almacén de preparación
        $stock = [];
        foreach ($nota->detalles as $d) {
            $stock[$d->id] = $nota->almacen_id && $d->producto_id ? StockAlmacen::cantidadEn($d->producto_id, $nota->almacen_id, $d->variante_id) : null;
        }

        return view('notas-pedido.show', ['nota' => $nota, 'stock' => $stock, 'puedeAtender' => $this->puedeAtender()]);
    }

    /** Genera la nota de pedido de una cotización aceptada. */
    public function store(Request $request, Cotizacion $cotizacion)
    {
        $datos = $request->validate([
            'fecha_entrega' => 'nullable|date|after_or_equal:today',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            $nota = $this->servicio->crearDesdeCotizacion($cotizacion, $request->user(), $datos['fecha_entrega'] ?? null, $datos['observaciones'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('notas-pedido.show', $nota)->with('success', "Nota de pedido {$nota->codigo} generada: almacén ya fue avisado.");
    }

    public function preparar(Request $request, NotaPedido $nota)
    {
        $this->exigirAtender();
        $datos = $request->validate(['preparado' => 'required|array', 'preparado.*' => 'nullable|integer|min:0|max:100000']);

        try {
            $this->servicio->registrarPreparado($nota, collect($datos['preparado'])->map(fn ($v) => (int) $v)->all(), $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Preparación actualizada.');
    }

    public function estado(Request $request, NotaPedido $nota)
    {
        $this->exigirAtender();
        $datos = $request->validate(['estado' => 'required|in:en_preparacion,lista,entregada,anulada']);

        try {
            $this->servicio->cambiarEstado($nota, $datos['estado'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nota de pedido: ' . NotaPedido::ESTADOS[$datos['estado']][0] . '.');
    }

    /** Hoja de preparación para imprimir y llevar al almacén. */
    public function pdf(NotaPedido $nota)
    {
        $nota->load(['cliente', 'almacen', 'cotizacion', 'creador', 'detalles.producto', 'detalles.variante']);
        $empresa = Empresa::first() ?? new Empresa();
        foreach (['razon_social' => 'KYRIOS LUCES S.A.C', 'ruc' => '20606247746'] as $campo => $valor) {
            $empresa->{$campo} = $empresa->{$campo} ?: $valor;
        }

        return Pdf::setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif', 'chroot' => public_path()])
            ->loadView('pdf.nota-pedido', compact('nota', 'empresa'))
            ->setPaper('a4')
            ->stream("nota-pedido-{$nota->codigo}.pdf");
    }

    private function puedeAtender(): bool
    {
        return in_array(auth()->user()->role?->nombre, ['Administración', 'Logística'], true);
    }

    private function exigirAtender(): void
    {
        abort_unless($this->puedeAtender(), 403, 'Solo almacén (Logística) o el administrador atienden las notas de pedido.');
    }
}
