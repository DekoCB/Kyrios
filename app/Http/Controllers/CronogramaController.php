<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CronogramaEvento;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Venta;
use App\Models\User;
use App\Notifications\AvisoSistema;
use App\Services\AlertaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cronograma de instalaciones y entregas. Administración y Logística lo programan;
 * Operaciones ve lo que tiene asignado y marca lo que ya realizó.
 */
class CronogramaController extends Controller
{
    private const PROGRAMAN = [Role::ADMINISTRACION, Role::LOGISTICA];

    private function programa(): bool
    {
        return in_array(auth()->user()->role?->nombre, self::PROGRAMAN, true);
    }

    private function visibles()
    {
        $q = CronogramaEvento::query();

        return $this->programa() ? $q : $q->where('responsable_id', auth()->id());
    }

    private function autorizarVer(CronogramaEvento $evento): void
    {
        abort_unless($this->programa() || $evento->responsable_id === auth()->id(), 403);
    }

    private function reglas(): array
    {
        return [
            'tipo'           => ['required', Rule::in(array_keys(CronogramaEvento::TIPOS))],
            'titulo'         => 'required|string|min:3|max:150',
            'cliente_id'     => 'nullable|exists:clientes,id',
            'pedido_id'      => 'nullable|exists:pedidos,id',
            'venta_id'       => 'nullable|exists:ventas,id',
            'direccion'      => 'nullable|string|max:255',
            'fecha'          => 'required|date',
            'hora'           => 'nullable|date_format:H:i',
            'responsable_id' => 'nullable|exists:users,id',
            'notas'          => 'nullable|string|max:2000',
        ];
    }

    public function index(Request $request)
    {
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('mes')) ? Carbon::createFromFormat('Y-m-d', $request->query('mes') . '-01') : today()->startOfMonth();

        $consulta = $this->visibles()->with(['cliente', 'responsable', 'pedido', 'venta'])->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()]);
        if (in_array($request->tipo, array_keys(CronogramaEvento::TIPOS), true)) {
            $consulta->where('tipo', $request->tipo);
        }
        if (in_array($request->estado, ['programado', 'realizado', 'cancelado'], true)) {
            $consulta->where('estado', $request->estado);
        }

        $base = $this->visibles()->programados();

        return view('cronograma.index', [
            'mes'          => $mes,
            'porDia'       => $consulta->orderBy('fecha')->orderBy('hora')->get()->groupBy(fn ($e) => $e->fecha->format('Y-m-d')),
            'atrasados'    => (clone $base)->whereDate('fecha', '<', today())->count(),
            'estaSemana'   => (clone $base)->whereBetween('fecha', [today(), today()->addDays(7)])->count(),
            'programa'     => $this->programa(),
            'vista'        => $request->query('vista') === 'calendario' ? 'calendario' : 'lista',
            'ventasPorProgramar' => $this->programa() ? $this->ventasPorProgramar() : collect(),
        ]);
    }

    /** Ventas vigentes que todavía no tienen una instalación programada (o realizada). */
    private function ventasPorProgramar()
    {
        return Venta::with('cliente:id,nombre')
            ->where('estado_pago', '!=', 'anulado')
            ->whereNotExists(fn ($q) => $q->from('cronograma_eventos')->whereColumn('cronograma_eventos.venta_id', 'ventas.id')
                ->where('cronograma_eventos.tipo', 'instalacion')->where('cronograma_eventos.estado', '!=', 'cancelado'))
            ->latest('id')->limit(50)->get(['id', 'codigo', 'cliente_id', 'fecha', 'total']);
    }

    private function formulario(?CronogramaEvento $evento = null, ?Venta $venta = null)
    {
        // Al programar desde una venta, el formulario ya llega con sus datos
        $defectos = $venta ? [
            'venta_id'   => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'titulo'     => 'Instalación ' . $venta->codigo . ($venta->cliente ? ' — ' . $venta->cliente->nombre : ''),
            'direccion'  => $venta->cliente?->direccion,
        ] : [];

        return view('cronograma.form', [
            'evento'       => $evento,
            'defectos'     => $defectos,
            'ventas'       => Venta::with('cliente:id,nombre')->where('estado_pago', '!=', 'anulado')->latest('id')->limit(100)->get(['id', 'codigo', 'cliente_id']),
            'clientes'     => Cliente::orderBy('nombre')->limit(500)->get(['id', 'nombre']),
            'responsables' => User::where('estado', 'activo')->whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::ADMINISTRACION, Role::LOGISTICA, Role::OPERACIONES]))->orderBy('name')->get(['id', 'name']),
            'ordenes'      => Pedido::whereIn('estado', ['pendiente', 'aprobado'])->latest('id')->limit(100)->get(['id', 'codigo']),
        ]);
    }

    public function create(Request $request)
    {
        $venta = $request->filled('venta_id') ? Venta::with('cliente')->find($request->integer('venta_id')) : null;

        return $this->formulario(null, $venta);
    }

    public function store(Request $request)
    {
        $evento = CronogramaEvento::create($request->validate($this->reglas()) + ['estado' => 'programado', 'creado_por' => $request->user()->id]);

        // Quien va a instalar o entregar se entera apenas se le asigna
        $this->avisarAsignacion($evento);

        return redirect()->route('cronograma.index', ['mes' => $evento->fecha->format('Y-m')])->with('success', 'Programado: ' . $evento->titulo);
    }

    public function edit(CronogramaEvento $evento)
    {
        return $this->formulario($evento);
    }

    public function update(Request $request, CronogramaEvento $evento)
    {
        $anterior = $evento->responsable_id;
        $evento->update($request->validate($this->reglas()));

        if ($evento->responsable_id !== $anterior) {
            $this->avisarAsignacion($evento);
        }

        return redirect()->route('cronograma.index', ['mes' => $evento->fecha->format('Y-m')])->with('success', 'Actualizado: ' . $evento->titulo);
    }

    /** Marca como realizado: lo puede hacer quien lo programa o el responsable asignado. */
    public function realizar(CronogramaEvento $evento)
    {
        $this->autorizarVer($evento);

        $filas = CronogramaEvento::whereKey($evento->id)->where('estado', 'programado')->update(['estado' => 'realizado', 'updated_at' => now()]);

        return back()->with($filas ? 'success' : 'error', $filas ? 'Marcado como realizado.' : 'Solo se pueden marcar los eventos programados.');
    }

    public function cancelar(CronogramaEvento $evento)
    {
        $filas = CronogramaEvento::whereKey($evento->id)->where('estado', 'programado')->update(['estado' => 'cancelado', 'updated_at' => now()]);

        return back()->with($filas ? 'success' : 'error', $filas ? 'Cancelado.' : 'Solo se pueden cancelar los eventos programados.');
    }

    private function avisarAsignacion(CronogramaEvento $evento): void
    {
        if (!$evento->responsable || $evento->responsable_id === auth()->id()) {
            return;
        }

        app(AlertaService::class)->avisar([], new AvisoSistema(
            "cron-asignado:{$evento->id}:{$evento->responsable_id}",
            'Tienes una ' . ($evento->tipo === 'instalacion' ? 'instalación' : 'entrega') . ' asignada',
            "{$evento->titulo}: {$evento->fecha->format('d/m/Y')}" . ($evento->hora_corta ? " a las {$evento->hora_corta}" : '') . '.',
            route('cronograma.index', ['mes' => $evento->fecha->format('Y-m')], false),
            'info'
        ), [$evento->responsable], unaVez: true);
    }
}
