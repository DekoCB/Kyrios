<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\SolicitudRrhh;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Control de asistencia: marcación web, registro manual, faltas del día, solicitudes aprobadas y resumen mensual. */
class AsistenciaService
{
    /** Estado de asistencia que genera cada tipo de solicitud aprobada. */
    private const ESTADO_DE_SOLICITUD = ['vacaciones' => 'vacaciones', 'permiso' => 'permiso', 'licencia' => 'licencia', 'descanso_medico' => 'descanso_medico'];

    public static function tolerancia(): int
    {
        return (int) config('kyrios.rrhh.tolerancia_min', 10);
    }

    /** Días de la semana que se trabajan (1 = lunes … 7 = domingo). */
    public static function diasLaborables(): array
    {
        return (array) config('kyrios.rrhh.dias_laborables', [1, 2, 3, 4, 5]);
    }

    public function esLaborable(Carbon $fecha): bool
    {
        return in_array($fecha->dayOfWeekIso, self::diasLaborables(), true);
    }

    /** Minutos de atraso respecto de la hora de entrada del empleado, descontada la tolerancia. */
    public function minutosTarde(Empleado $e, string $horaReal): int
    {
        $programada = Carbon::createFromFormat('H:i:s', substr($e->hora_entrada, 0, 5) . ':00');
        $real = Carbon::createFromFormat('H:i:s', substr($horaReal, 0, 5) . ':00');
        $tarde = max(0, $programada->diffInMinutes($real, false));

        return $tarde > self::tolerancia() ? $tarde : 0;
    }

    public function marcarEntrada(Empleado $e, ?Carbon $ahora = null, ?User $por = null): Asistencia
    {
        $ahora ??= now();
        $this->exigirActivo($e);

        $a = Asistencia::where('empleado_id', $e->id)->whereDate('fecha', $ahora->toDateString())->first();
        if ($a && $a->hora_entrada) {
            throw new DomainException('Ya marcaste tu entrada hoy a las ' . substr($a->hora_entrada, 0, 5) . '.');
        }
        if ($a && in_array($a->estado, ['permiso', 'vacaciones', 'descanso_medico', 'licencia'], true)) {
            throw new DomainException('Hoy figuras con ' . mb_strtolower($a->estado_nombre) . ': no necesitas marcar asistencia.');
        }

        $hora = $ahora->format('H:i:s');
        $tarde = $this->minutosTarde($e, $hora);

        return Asistencia::updateOrCreate(['empleado_id' => $e->id, 'fecha' => $ahora->toDateString()], [
            'hora_entrada' => $hora, 'estado' => $tarde > 0 ? 'tardanza' : 'puntual', 'minutos_tarde' => $tarde,
            'origen' => 'web', 'registrado_por' => $por?->id,
        ]);
    }

    public function marcarSalida(Empleado $e, ?Carbon $ahora = null, ?User $por = null): Asistencia
    {
        $ahora ??= now();
        $this->exigirActivo($e);

        $a = Asistencia::where('empleado_id', $e->id)->whereDate('fecha', $ahora->toDateString())->first();
        if (!$a || !$a->hora_entrada) {
            throw new DomainException('Primero marca tu entrada.');
        }
        if ($a->hora_salida) {
            throw new DomainException('Ya marcaste tu salida hoy a las ' . substr($a->hora_salida, 0, 5) . '.');
        }

        $a->update(['hora_salida' => $ahora->format('H:i:s'), 'registrado_por' => $por?->id ?? $a->registrado_por]);

        return $a;
    }

    /** Registro o corrección manual del administrador. */
    public function registrarManual(Empleado $e, string $fecha, array $d, User $por): Asistencia
    {
        $estado = $d['estado'];
        $entrada = ($d['hora_entrada'] ?? null) ?: null;
        $salida = ($d['hora_salida'] ?? null) ?: null;

        if (in_array($estado, ['puntual', 'tardanza'], true)) {
            if (!$entrada) {
                throw new DomainException('Indique la hora de entrada.');
            }
            $entrada = substr($entrada, 0, 5) . ':00';
            $tarde = $this->minutosTarde($e, $entrada);
            $estado = $tarde > 0 ? 'tardanza' : 'puntual';
        } else {
            $entrada = $salida = null;
            $tarde = 0;
        }
        if ($entrada && $salida && substr($salida, 0, 5) < substr($entrada, 0, 5)) {
            throw new DomainException('La salida no puede ser anterior a la entrada.');
        }

        return Asistencia::updateOrCreate(['empleado_id' => $e->id, 'fecha' => $fecha], [
            'hora_entrada' => $entrada, 'hora_salida' => $salida ? substr($salida, 0, 5) . ':00' : null, 'estado' => $estado,
            'minutos_tarde' => $tarde, 'origen' => 'manual', 'observaciones' => $d['observaciones'] ?? null, 'registrado_por' => $por->id,
        ]);
    }

    /** Marca falta a quien no tiene ningún registro en un día laborable ya pasado o de hoy. Devuelve cuántas. */
    public function marcarFaltas(Carbon $fecha, User $por): int
    {
        if (!$this->esLaborable($fecha)) {
            throw new DomainException('Ese día no es laborable.');
        }

        $sinRegistro = Empleado::where('estado', 'activo')->whereDate('fecha_ingreso', '<=', $fecha)
            ->whereDoesntHave('asistencias', fn ($q) => $q->whereDate('fecha', $fecha))->get();

        foreach ($sinRegistro as $e) {
            Asistencia::create(['empleado_id' => $e->id, 'fecha' => $fecha->toDateString(), 'estado' => 'falta', 'origen' => 'manual', 'registrado_por' => $por->id]);
        }

        return $sinRegistro->count();
    }

    /** Al aprobar una solicitud, los días laborables del rango quedan registrados con el estado que corresponde. */
    public function aplicarSolicitud(SolicitudRrhh $s, User $por): int
    {
        $estado = self::ESTADO_DE_SOLICITUD[$s->tipo];
        $n = 0;
        for ($d = $s->desde->copy(); $d->lte($s->hasta); $d->addDay()) {
            if (!$this->esLaborable($d)) {
                continue;
            }
            Asistencia::updateOrCreate(['empleado_id' => $s->empleado_id, 'fecha' => $d->toDateString()], [
                'hora_entrada' => null, 'hora_salida' => null, 'estado' => $estado, 'minutos_tarde' => 0, 'origen' => 'manual',
                'observaciones' => $s->tipo_nombre . ($s->motivo ? ': ' . $s->motivo : ''), 'registrado_por' => $por->id,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Resumen del mes por empleado: días asistidos, tardanzas, minutos de atraso, faltas, permisos y horas.
     *
     * @return array<int, array<string, mixed>> indexado por empleado_id
     */
    public function resumenMes(int $anio, int $mes)
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $registros = Asistencia::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->get()->groupBy('empleado_id');

        return Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->orderBy('nombres')->get()->map(function (Empleado $e) use ($registros) {
            $r = $registros->get($e->id, collect());

            return [
                'empleado'   => $e,
                'asistidos'  => $r->whereIn('estado', ['puntual', 'tardanza'])->count(),
                'tardanzas'  => $r->where('estado', 'tardanza')->count(),
                'min_tarde'  => (int) $r->sum('minutos_tarde'),
                'faltas'     => $r->where('estado', 'falta')->count(),
                'permisos'   => $r->whereIn('estado', ['permiso', 'licencia'])->count(),
                'vacaciones' => $r->where('estado', 'vacaciones')->count(),
                'descanso'   => $r->where('estado', 'descanso_medico')->count(),
                'horas'      => round((float) $r->sum(fn ($a) => $a->horas ?? 0), 1),
            ];
        });
    }

    private function exigirActivo(Empleado $e): void
    {
        if ($e->estado === 'cesado') {
            throw new DomainException('Este empleado figura como cesado.');
        }
    }
}
