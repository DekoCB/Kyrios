<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Empleado;
use App\Services\AsistenciaService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control de asistencia. Cada usuario marca la suya (Mi asistencia); Administración ve y corrige a todos.
 */
class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $servicio)
    {
    }

    // ── Cada persona ─────────────────────────────────────────────────────────

    public function mi(Request $request)
    {
        $empleado = Empleado::where('user_id', $request->user()->id)->first();
        $hoy = $empleado?->asistencias()->whereDate('fecha', today())->first();
        $historial = $empleado?->asistencias()->orderByDesc('fecha')->limit(31)->get() ?? collect();

        return view('rrhh.asistencia.mi', compact('empleado', 'hoy', 'historial'));
    }

    public function marcar(Request $request)
    {
        $datos = $request->validate(['accion' => 'required|in:entrada,salida']);
        $empleado = Empleado::where('user_id', $request->user()->id)->first();
        if (!$empleado) {
            return back()->with('error', 'Tu usuario aún no está vinculado a una ficha de personal. Avisa a Administración.');
        }

        try {
            $a = $datos['accion'] === 'entrada'
                ? $this->servicio->marcarEntrada($empleado, null, $request->user())
                : $this->servicio->marcarSalida($empleado, null, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $hora = substr($datos['accion'] === 'entrada' ? $a->hora_entrada : $a->hora_salida, 0, 5);

        return back()->with('success', ($datos['accion'] === 'entrada' ? 'Entrada' : 'Salida') . " registrada a las {$hora}" . ($datos['accion'] === 'entrada' && $a->minutos_tarde ? " ({$a->minutos_tarde} min de tardanza)." : '.'));
    }

    // ── Administración ───────────────────────────────────────────────────────

    /** Control del día: quién llegó, quién falta y quién no marcó. */
    public function control(Request $request)
    {
        $fecha = $this->fecha($request->query('fecha'));
        $empleados = Empleado::where('estado', '!=', 'cesado')->whereDate('fecha_ingreso', '<=', $fecha)->orderBy('apellidos')->orderBy('nombres')->get();
        $registros = Asistencia::whereDate('fecha', $fecha)->get()->keyBy('empleado_id');

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

    /** Resumen mensual por empleado. */
    public function reporte(Request $request)
    {
        [$anio, $mes] = $this->mes($request->query('mes'));
        $resumen = $this->servicio->resumenMes($anio, $mes);

        return view('rrhh.asistencia.reporte', ['resumen' => $resumen, 'anio' => $anio, 'mes' => $mes, 'mesTexto' => sprintf('%04d-%02d', $anio, $mes)]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        [$anio, $mes] = $this->mes($request->query('mes'));
        $resumen = $this->servicio->resumenMes($anio, $mes);

        return response()->streamDownload(function () use ($resumen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Empleado', 'DNI', 'Cargo', 'Días asistidos', 'Tardanzas', 'Minutos de atraso', 'Faltas', 'Permisos/licencias', 'Vacaciones', 'Descanso médico', 'Horas trabajadas'], ';');
            foreach ($resumen as $r) {
                $e = $r['empleado'];
                fputcsv($out, [$e->codigo, $e->nombre_completo, $e->dni, $e->cargo, $r['asistidos'], $r['tardanzas'], $r['min_tarde'], $r['faltas'], $r['permisos'], $r['vacaciones'], $r['descanso'], $r['horas']], ';');
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
