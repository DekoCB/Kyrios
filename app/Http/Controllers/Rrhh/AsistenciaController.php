<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\AsistenciaMarcacion;
use App\Models\LugarTrabajo;
use App\Models\Empleado;
use App\Services\AsistenciaService;
use App\Services\PlanillaService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control de asistencia. Cada usuario marca la suya (Mi asistencia); Administración ve y corrige a todos.
 */
class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $servicio, private PlanillaService $planilla)
    {
    }

    // ── Cada persona ─────────────────────────────────────────────────────────

    public function mi(Request $request)
    {
        $empleado = Empleado::where('user_id', $request->user()->id)->first();
        $hoy = $empleado?->asistencias()->whereDate('fecha', today())->first();
        $historial = $empleado?->asistencias()->orderByDesc('fecha')->limit(31)->get() ?? collect();

        $exigir = (bool) config('kyrios.rrhh.exigir_foto_ubicacion');
        $lugares = LugarTrabajo::where('activo', true)->count();

        return view('rrhh.asistencia.mi', compact('empleado', 'hoy', 'historial', 'exigir', 'lugares'));
    }

    public function marcar(Request $request)
    {
        // Con la selfie y la ubicación se valida que la persona está en su lugar de trabajo (oficina, tienda u obra)
        $r = config('kyrios.rrhh.exigir_foto_ubicacion') ? 'required' : 'nullable';
        $datos = $request->validate([
            'accion' => 'required|in:entrada,salida',
            'foto' => $r . '|image|mimes:jpeg,jpg,png,webp|max:4096',
            'latitud' => $r . '|numeric|between:-90,90',
            'longitud' => $r . '|numeric|between:-180,180',
            'precision' => 'nullable|numeric|min:0',
        ], [
            'foto.required' => 'Para marcar necesitas tomarte una selfie.', 'latitud.required' => 'No pudimos saber dónde estás: activa la ubicación del teléfono y vuelve a intentar.',
            'longitud.required' => 'No pudimos saber dónde estás: activa la ubicación del teléfono y vuelve a intentar.', 'foto.image' => 'La selfie debe ser una imagen.', 'foto.max' => 'La selfie pesa demasiado.',
        ]);
        $empleado = Empleado::where('user_id', $request->user()->id)->first();
        if (!$empleado) {
            return back()->with('error', 'Tu usuario aún no está vinculado a una ficha de personal. Avisa a Administración.');
        }

        $ruta = $request->hasFile('foto') ? $request->file('foto')->store('asistencia/' . $empleado->id, 'local') : null;
        $marca = array_filter(['foto' => $ruta, 'latitud' => $datos['latitud'] ?? null, 'longitud' => $datos['longitud'] ?? null, 'precision' => $datos['precision'] ?? null], fn ($v) => $v !== null);

        try {
            $a = $datos['accion'] === 'entrada'
                ? $this->servicio->marcarEntrada($empleado, null, $request->user(), $marca)
                : $this->servicio->marcarSalida($empleado, null, $request->user(), $marca);
        } catch (DomainException $e) {
            if ($ruta) {
                Storage::disk('local')->delete($ruta);
            }

            return back()->with('error', $e->getMessage());
        }
        $m = $a->marcaciones()->where('tipo', $datos['accion'])->latest('id')->first();
        $aviso = match ($m?->ubicacion) {
            'valida' => ' Ubicación validada: ' . ($m->lugar?->nombre ?? 'tu lugar de trabajo') . '.',
            'fuera' => ' Estás fuera de los lugares de trabajo registrados (a ' . number_format((int) $m->distancia_m) . ' m del más cercano): Administración revisará tu marcación.',
            default => '',
        };

        $hora = substr($datos['accion'] === 'entrada' ? $a->hora_entrada : $a->hora_salida, 0, 5);

        return back()->with('success', ($datos['accion'] === 'entrada' ? 'Entrada' : 'Salida') . " registrada a las {$hora}" . ($datos['accion'] === 'entrada' && $a->minutos_tarde ? " ({$a->minutos_tarde} min de tardanza)." : '.') . $aviso);
    }

    // ── Administración ───────────────────────────────────────────────────────

    /** Control del día: quién llegó, quién falta y quién no marcó. */
    public function control(Request $request)
    {
        $fecha = $this->fecha($request->query('fecha'));
        $empleados = Empleado::where('estado', '!=', 'cesado')->whereDate('fecha_ingreso', '<=', $fecha)->orderBy('apellidos')->orderBy('nombres')->get();
        $registros = Asistencia::with('marcaciones.lugar')->whereDate('fecha', $fecha)->get()->keyBy('empleado_id');

        $conteo = [
            'presentes' => $registros->whereIn('estado', ['puntual', 'tardanza'])->count(),
            'tardanzas' => $registros->where('estado', 'tardanza')->count(),
            'ausentes'  => $registros->whereIn('estado', ['falta', 'permiso', 'vacaciones', 'descanso_medico', 'licencia'])->count(),
            'sin_marcar' => $empleados->filter(fn ($e) => !$registros->has($e->id))->count(),
        ];

        return view('rrhh.asistencia.control', ['fecha' => $fecha, 'empleados' => $empleados, 'registros' => $registros, 'conteo' => $conteo, 'laborable' => $this->servicio->esLaborable($fecha)]);
    }

    public function registrar(Request $request, Empleado $empleado)
    {
        $datos = $request->validate([
            'fecha'         => 'required|date|before_or_equal:today',
            'estado'        => 'required|in:' . implode(',', array_keys(Asistencia::ESTADOS)),
            'hora_entrada'  => 'nullable|date_format:H:i',
            'hora_salida'   => 'nullable|date_format:H:i',
            'observaciones' => 'nullable|string|max:255',
        ]);

        try {
            $this->servicio->registrarManual($empleado, $datos['fecha'], $datos, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('rrhh.asistencia.control', ['fecha' => $datos['fecha']])->with('success', "Asistencia de {$empleado->nombre_completo} registrada.");
    }

    public function faltas(Request $request)
    {
        $datos = $request->validate(['fecha' => 'required|date|before_or_equal:today']);

        try {
            $n = $this->servicio->marcarFaltas(Carbon::parse($datos['fecha']), $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rrhh.asistencia.control', ['fecha' => $datos['fecha']])->with('success', $n ? "{$n} empleado(s) sin marcación quedaron con falta." : 'Todos tienen ya un registro ese día.');
    }

    /** Resumen mensual por empleado, con el calendario del mes y lo que se descuenta o bonifica. */
    public function reporte(Request $request)
    {
        [$anio, $mes] = $this->mes($request->query('mes'));
        $resumen = $this->servicio->resumenMes($anio, $mes);

        return view('rrhh.asistencia.reporte', [
            'resumen' => $resumen, 'anio' => $anio, 'mes' => $mes, 'mesTexto' => sprintf('%04d-%02d', $anio, $mes),
            'calendario' => $this->servicio->calendarioMes($anio, $mes), 'diasMes' => Carbon::create($anio, $mes, 1)->daysInMonth,
        ]);
    }

    /** Un trabajador, mes por mes: cada día con su entrada, salida, tardanza, horas extra y ubicación, y el ajuste al sueldo. */
    public function trabajador(Request $request)
    {
        [$anio, $mes] = $this->mes($request->query('mes'));
        $empleados = Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->orderBy('nombres')->get();
        $empleado = $request->filled('empleado') ? $empleados->firstWhere('id', (int) $request->query('empleado')) : null;
        $registros = collect();
        $ajuste = null;
        if ($empleado) {
            $desde = Carbon::create($anio, $mes, 1);
            $registros = Asistencia::with('marcaciones.lugar')->where('empleado_id', $empleado->id)
                ->whereBetween('fecha', [$desde->toDateString(), $desde->copy()->endOfMonth()->toDateString()])->orderBy('fecha')->get();
            $ajuste = $this->planilla->ajuste($empleado, $anio, $mes, $registros);
        }

        return view('rrhh.asistencia.trabajador', ['empleados' => $empleados, 'empleado' => $empleado, 'registros' => $registros, 'ajuste' => $ajuste,
            'anio' => $anio, 'mes' => $mes, 'mesTexto' => sprintf('%04d-%02d', $anio, $mes)]);
    }

    /** La selfie de una marcación: la ve Administración y la propia persona. */
    public function foto(Request $request, AsistenciaMarcacion $marcacion)
    {
        $esDueno = $marcacion->empleado?->user_id === $request->user()->id;
        abort_unless($esDueno || $request->user()->role->nombre === \App\Models\Role::ADMINISTRACION, 403);
        abort_unless($marcacion->foto && Storage::disk('local')->exists($marcacion->foto), 404);

        return Storage::disk('local')->response($marcacion->foto, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Administración aprueba o rechaza una marcación hecha fuera de los lugares de trabajo; al rechazarla queda como falta. */
    public function revisar(Request $request, AsistenciaMarcacion $marcacion)
    {
        $d = $request->validate(['revision' => 'required|in:aprobada,rechazada']);
        $marcacion->update(['revision' => $d['revision'], 'revisada_por' => $request->user()->id, 'revisada_at' => now()]);
        if ($d['revision'] === 'rechazada' && $marcacion->asistencia) {
            $marcacion->asistencia->update(['estado' => 'falta', 'hora_entrada' => null, 'hora_salida' => null, 'minutos_tarde' => 0, 'minutos_extra' => 0,
                'observaciones' => 'Marcación rechazada: ubicación no válida.']);
        }

        return back()->with('success', $d['revision'] === 'aprobada' ? 'Marcación aprobada.' : 'Marcación rechazada: el día quedó como falta.');
    }

    public function exportar(Request $request): StreamedResponse
    {
        [$anio, $mes] = $this->mes($request->query('mes'));
        $resumen = $this->servicio->resumenMes($anio, $mes);

        return response()->streamDownload(function () use ($resumen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Empleado', 'DNI', 'Cargo', 'Días asistidos', 'Tardanzas', 'Minutos de atraso', 'Faltas', 'Permisos/licencias', 'Vacaciones', 'Descanso médico', 'Horas trabajadas',
                'Horas extra', 'Sueldo básico', 'Descuento por tardanzas', 'Descuento por faltas', 'Bono por horas extra', 'Ajuste neto', 'Sueldo estimado'], ';');
            foreach ($resumen as $r) {
                $e = $r['empleado'];
                $p = $r['planilla'];
                fputcsv($out, [$e->codigo, $e->nombre_completo, $e->dni, $e->cargo, $r['asistidos'], $r['tardanzas'], $r['min_tarde'], $r['faltas'], $r['permisos'], $r['vacaciones'], $r['descanso'], $r['horas'],
                    $p['horas_extra'], $p['sueldo'], $p['desc_tardanzas'], $p['desc_faltas'], $p['bono_extra'], $p['neto'], $p['sueldo_estimado']], ';');
            }
            fclose($out);
        }, sprintf('asistencia-%04d-%02d.csv', $anio, $mes), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function fecha(?string $valor): Carbon
    {
        try {
            $f = $valor ? Carbon::parse($valor) : today();
        } catch (\Throwable) {
            $f = today();
        }

        return $f->isFuture() ? today() : $f->startOfDay();
    }

    /** @return array{0:int,1:int} */
    private function mes(?string $valor): array
    {
        if ($valor && preg_match('/^(\d{4})-(\d{2})$/', $valor, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [today()->year, today()->month];
    }
}
