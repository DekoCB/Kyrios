<?php

namespace App\Http\Controllers;

use App\Models\CronogramaEvento;
use App\Models\Herramienta;
use App\Models\HerramientaSalida;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de herramientas de instalación: catálogo, salidas a los técnicos y retornos.
 * Administración y Logística lo gestionan; Operaciones consulta lo que tiene a su nombre.
 */
class HerramientaController extends Controller
{
    private function gestiona(): bool
    {
        return in_array(auth()->user()->role?->nombre, [Role::ADMINISTRACION, Role::LOGISTICA], true);
    }

    // ── Catálogo ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $consulta = Herramienta::query()
            ->withSum(['salidas as en_uso' => fn ($q) => $q->where('estado', 'en_uso')], 'cantidad')
            ->orderBy('nombre');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $consulta->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%")->orWhere('categoria', 'like', "%{$q}%"));
        }
        if ($request->query('estado', 'activa') !== 'todas') {
            $consulta->where('estado', 'activa');
        }

        $herramientas = $consulta->paginate(25)->withQueryString();

        $fueraAhora = HerramientaSalida::where('estado', 'en_uso')->count();
        $atrasadas = HerramientaSalida::where('estado', 'en_uso')->whereDate('fecha_devolucion_esperada', '<', today())->count();

        return view('herramientas.index', ['herramientas' => $herramientas, 'gestiona' => $this->gestiona(), 'fueraAhora' => $fueraAhora, 'atrasadas' => $atrasadas]);
    }

    private function reglas(): array
    {
        return [
            'nombre'         => 'required|string|min:2|max:150',
            'categoria'      => 'nullable|string|max:80',
            'descripcion'    => 'nullable|string|max:2000',
            'ubicacion'      => 'nullable|string|max:120',
            'cantidad_total' => 'required|integer|min:1|max:100000',
        ];
    }

    public function create()
    {
        return view('herramientas.form', ['herramienta' => null]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        $herramienta = Herramienta::create($datos + ['codigo' => Herramienta::siguienteCodigo(), 'estado' => 'activa']);

        return redirect()->route('herramientas.index')->with('success', "Herramienta {$herramienta->codigo} registrada.");
    }

    public function edit(Herramienta $herramienta)
    {
        return view('herramientas.form', ['herramienta' => $herramienta]);
    }

    public function update(Request $request, Herramienta $herramienta)
    {
        $datos = $request->validate($this->reglas() + ['estado' => 'required|in:activa,baja']);

        $fuera = (int) $herramienta->salidas()->where('estado', 'en_uso')->sum('cantidad');
        if ($datos['cantidad_total'] < $fuera + $herramienta->cantidad_danada) {
            return back()->withInput()->with('error', "No puede quedar en menos de " . ($fuera + $herramienta->cantidad_danada) . " unidades: {$fuera} están fuera y {$herramienta->cantidad_danada} dañadas.");
        }
        if ($datos['estado'] === 'baja' && $fuera > 0) {
            return back()->withInput()->with('error', 'No se puede dar de baja mientras haya unidades fuera. Registra primero su retorno.');
        }

        $herramienta->update($datos);

        return redirect()->route('herramientas.index')->with('success', 'Herramienta actualizada.');
    }

    /** Las unidades dañadas ya se repararon y vuelven a estar disponibles. */
    public function reparar(Request $request, Herramienta $herramienta)
    {
        $n = (int) $request->validate(['cantidad' => 'required|integer|min:1'])['cantidad'];

        $ok = DB::transaction(function () use ($herramienta, $n) {
            $h = Herramienta::whereKey($herramienta->id)->lockForUpdate()->first();
            if ($n > $h->cantidad_danada) {
                return false;
            }
            $h->decrement('cantidad_danada', $n);

            return true;
        });

        return back()->with($ok ? 'success' : 'error', $ok ? "{$n} unidad(es) vuelven a estar disponibles." : 'No hay tantas unidades dañadas.');
    }

    // ── Salidas y retornos ────────────────────────────────────────────────

    public function salidas(Request $request)
    {
        $consulta = HerramientaSalida::with(['herramienta', 'tecnico', 'evento'])->latest('fecha_salida')->latest('id');

        if (!$this->gestiona()) {
            $consulta->where('tecnico_id', auth()->id());
        }
        $estado = $request->query('estado', 'en_uso');
        if (in_array($estado, ['en_uso', 'devuelta'], true)) {
            $consulta->where('estado', $estado);
        }
        if ($this->gestiona() && $request->filled('tecnico_id')) {
            $consulta->where('tecnico_id', $request->integer('tecnico_id'));
        }

        return view('herramientas.salidas', [
            'salidas'  => $consulta->paginate(25)->withQueryString(),
            'gestiona' => $this->gestiona(),
            'tecnicos' => $this->tecnicos(),
            'estado'   => $estado,
        ]);
    }

    private function tecnicos()
    {
        return User::where('estado', 'activo')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::OPERACIONES, Role::LOGISTICA, Role::ADMINISTRACION]))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function crearSalida(Request $request)
    {
        $herramientas = Herramienta::where('estado', 'activa')
            ->withSum(['salidas as en_uso' => fn ($q) => $q->where('estado', 'en_uso')], 'cantidad')
            ->orderBy('nombre')->get();

        return view('herramientas.salida-form', [
            'herramientas'      => $herramientas->filter(fn ($h) => $h->disponible > 0)->values(),
            'tecnicos'          => $this->tecnicos(),
            'eventos'           => CronogramaEvento::programados()->orderBy('fecha')->limit(100)->get(['id', 'titulo', 'fecha']),
            'herramientaId'     => $request->integer('herramienta_id') ?: null,
        ]);
    }

    public function storeSalida(Request $request)
    {
        $datos = $request->validate([
            'herramienta_id'            => 'required|exists:herramientas,id',
            'tecnico_id'                => 'required|exists:users,id',
            'cantidad'                  => 'required|integer|min:1|max:100000',
            'cronograma_evento_id'      => 'nullable|exists:cronograma_eventos,id',
            'destino'                   => 'nullable|string|max:200',
            'fecha_devolucion_esperada' => 'nullable|date|after_or_equal:today',
            'notas'                     => 'nullable|string|max:1000',
        ]);

        try {
            $salida = DB::transaction(function () use ($datos, $request) {
                // Bloquea la herramienta para que dos salidas a la vez no sobrepasen lo disponible
                $h = Herramienta::whereKey($datos['herramienta_id'])->lockForUpdate()->first();
                if ($h->estado !== 'activa') {
                    throw new \DomainException('La herramienta está dada de baja.');
                }
                $fuera = (int) $h->salidas()->where('estado', 'en_uso')->sum('cantidad');
                $disponible = $h->cantidad_total - $h->cantidad_danada - $fuera;
                if ($datos['cantidad'] > $disponible) {
                    throw new \DomainException("Solo hay {$disponible} unidad(es) disponible(s) de «{$h->nombre}».");
                }

                return HerramientaSalida::create($datos + ['fecha_salida' => now(), 'registrado_por' => $request->user()->id, 'estado' => 'en_uso']);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('herramientas.salidas')->with('success', "Salida registrada: {$salida->herramienta->nombre} ×{$salida->cantidad}.");
    }

    public function retorno(Request $request, HerramientaSalida $salida)
    {
        $datos = $request->validate([
            'cantidad_buena'   => 'required|integer|min:0',
            'cantidad_danada'  => 'required|integer|min:0',
            'cantidad_perdida' => 'required|integer|min:0',
            'notas_retorno'    => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($salida, $datos, $request) {
                $s = HerramientaSalida::whereKey($salida->id)->lockForUpdate()->first();
                if ($s->estado !== 'en_uso') {
                    throw new \DomainException('Esta salida ya fue devuelta.');
                }
                if ($datos['cantidad_buena'] + $datos['cantidad_danada'] + $datos['cantidad_perdida'] !== $s->cantidad) {
                    throw new \DomainException("Las cantidades devueltas deben sumar {$s->cantidad}.");
                }

                $h = Herramienta::whereKey($s->herramienta_id)->lockForUpdate()->first();
                $h->update([
                    'cantidad_danada' => $h->cantidad_danada + $datos['cantidad_danada'],
                    'cantidad_total'  => max(0, $h->cantidad_total - $datos['cantidad_perdida']),
                ]);

                $s->update($datos + ['estado' => 'devuelta', 'fecha_retorno' => now(), 'recibido_por' => $request->user()->id]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Retorno registrado.');
    }
}
