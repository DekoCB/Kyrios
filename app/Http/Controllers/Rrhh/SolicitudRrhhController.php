<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\SolicitudRrhh;
use App\Notifications\AvisoSistema;
use App\Services\AlertaService;
use App\Services\AsistenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Vacaciones, permisos y licencias: los pide el empleado y los resuelve Administración. */
class SolicitudRrhhController extends Controller
{
    public function __construct(private AsistenciaService $asistencias, private AlertaService $alertas)
    {
    }

    /** Bandeja de Administración. */
    public function index(Request $request)
    {
        $estado = in_array($request->query('estado'), ['pendiente', 'aprobada', 'rechazada', 'todas'], true) ? $request->query('estado') : 'pendiente';
        $solicitudes = SolicitudRrhh::with(['empleado', 'resolutor'])
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('rrhh.solicitudes.index', [
            'solicitudes' => $solicitudes, 'estado' => $estado,
            'pendientes'  => SolicitudRrhh::where('estado', 'pendiente')->count(),
            'empleados'   => Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->get(),
        ]);
    }

    /** Las solicitudes de la propia persona. */
    public function mias(Request $request)
    {
        $empleado = Empleado::where('user_id', $request->user()->id)->first();

        return view('rrhh.solicitudes.mias', ['empleado' => $empleado, 'solicitudes' => $empleado?->solicitudes()->get() ?? collect()]);
    }

    public function store(Request $request)
    {
        $esAdmin = $request->user()->role?->nombre === Role::ADMINISTRACION;
        $datos = $request->validate([
            'empleado_id' => $esAdmin ? 'required|exists:empleados,id' : 'nullable',
            'tipo'        => 'required|in:' . implode(',', array_keys(SolicitudRrhh::TIPOS)),
            'desde'       => 'required|date',
            'hasta'       => 'required|date|after_or_equal:desde',
            'motivo'      => 'nullable|string|max:255',
        ], ['hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);

        $empleado = $esAdmin ? Empleado::findOrFail($datos['empleado_id']) : Empleado::where('user_id', $request->user()->id)->first();
        if (!$empleado) {
            return back()->with('error', 'Tu usuario aún no está vinculado a una ficha de personal. Avisa a Administración.');
        }

        $traslapa = SolicitudRrhh::where('empleado_id', $empleado->id)->whereIn('estado', ['pendiente', 'aprobada'])
            ->whereDate('desde', '<=', $datos['hasta'])->whereDate('hasta', '>=', $datos['desde'])->exists();
        if ($traslapa) {
            return back()->withInput()->with('error', 'Ya hay una solicitud pendiente o aprobada que se cruza con esas fechas.');
        }

        $s = SolicitudRrhh::create(['empleado_id' => $empleado->id, 'tipo' => $datos['tipo'], 'desde' => $datos['desde'], 'hasta' => $datos['hasta'], 'motivo' => $datos['motivo'] ?? null]);

        $this->alertas->avisar([Role::ADMINISTRACION], new AvisoSistema(
            "rrhh-solicitud:{$s->id}", 'Solicitud de personal',
            "{$empleado->nombre_completo} pide {$s->tipo_nombre} del {$s->desde->format('d/m')} al {$s->hasta->format('d/m')}.",
            route('rrhh.solicitudes.index', [], false), 'alerta'
        ));

        return back()->with('success', 'Solicitud enviada: queda pendiente de aprobación.');
    }

    public function aprobar(Request $request, SolicitudRrhh $solicitud)
    {
        if ($solicitud->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue resuelta.');
        }

        $dias = DB::transaction(function () use ($solicitud, $request) {
            $solicitud->update(['estado' => 'aprobada', 'resuelta_por' => $request->user()->id, 'resuelta_en' => now(), 'motivo_rechazo' => null]);

            return $this->asistencias->aplicarSolicitud($solicitud, $request->user());
        });
        $this->avisar($solicitud, true);

        return back()->with('success', "Solicitud aprobada: {$dias} día(s) laborable(s) quedaron registrados en la asistencia.");
    }

    public function rechazar(Request $request, SolicitudRrhh $solicitud)
    {
        $datos = $request->validate(['motivo' => 'required|string|min:3|max:255'], ['motivo.required' => 'Escribe el motivo del rechazo.']);
        if ($solicitud->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue resuelta.');
        }

        $solicitud->update(['estado' => 'rechazada', 'resuelta_por' => $request->user()->id, 'resuelta_en' => now(), 'motivo_rechazo' => $datos['motivo']]);
        $this->avisar($solicitud, false);

        return back()->with('success', 'Solicitud rechazada.');
    }

    private function avisar(SolicitudRrhh $s, bool $aprobada): void
    {
        $s->empleado?->usuario?->notify(new AvisoSistema(
            "rrhh-solicitud-resuelta:{$s->id}",
            $aprobada ? 'Solicitud aprobada' : 'Solicitud rechazada',
            "Tu solicitud de {$s->tipo_nombre} ({$s->desde->format('d/m')} al {$s->hasta->format('d/m')}) fue " . ($aprobada ? 'aprobada.' : "rechazada. Motivo: {$s->motivo_rechazo}"),
            route('rrhh.mis-solicitudes', [], false),
            $aprobada ? 'info' : 'alerta'
        ));
    }
}
