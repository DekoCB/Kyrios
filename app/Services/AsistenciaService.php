<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\AsistenciaMarcacion;
use App\Models\LugarTrabajo;
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

        return $tarde > ($e->tolerancia_min ?? self::tolerancia()) ? $tarde : 0;
    }

    /** Minutos trabajados después de la hora de salida del empleado; solo cuentan desde el mínimo que tenga fijado. */
    public function minutosExtra(Empleado $e, ?string $horaSalidaReal): int
    {
        if (!$horaSalidaReal) {
            return 0;
        }
        $programada = Carbon::createFromFormat('H:i:s', substr($e->hora_salida, 0, 5) . ':00');
        $real = Carbon::createFromFormat('H:i:s', substr($horaSalidaReal, 0, 5) . ':00');
        $extra = max(0, (int) $programada->diffInMinutes($real, false));

        return $extra >= max(1, (int) ($e->extra_minimo_min ?? 30)) ? $extra : 0;
    }

    /** El lugar de trabajo activo más cercano al punto y si queda dentro de su radio. @return array{lugar: ?LugarTrabajo, distancia: ?int, ubicacion: string} */
    public function ubicar(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['lugar' => null, 'distancia' => null, 'ubicacion' => 'sin_dato'];
        }
        $cercano = LugarTrabajo::where('activo', true)->get()->sortBy(fn ($l) => $l->distanciaA($lat, $lng))->first();
        if (!$cercano) {
            return ['lugar' => null, 'distancia' => null, 'ubicacion' => 'fuera'];
        }
        $d = $cercano->distanciaA($lat, $lng);

        return ['lugar' => $d <= $cercano->radio_m ? $cercano : null, 'distancia' => $d, 'ubicacion' => $d <= $cercano->radio_m ? 'valida' : 'fuera'];
    }

    /**
     * Guarda la selfie y el punto desde donde se marcó. `$datos`: foto (ruta ya guardada), latitud, longitud, precision.
     */
    public function registrarMarcacion(Asistencia $a, string $tipo, array $datos): AsistenciaMarcacion
    {
        $u = $this->ubicar($datos['latitud'] ?? null, $datos['longitud'] ?? null);

        return AsistenciaMarcacion::create([
            'asistencia_id' => $a->id, 'empleado_id' => $a->empleado_id, 'tipo' => $tipo, 'foto' => $datos['foto'] ?? null,
            'latitud' => $datos['latitud'] ?? null, 'longitud' => $datos['longitud'] ?? null, 'precision_m' => isset($datos['precision']) ? (int) round($datos['precision']) : null,
            'lugar_trabajo_id' => $u['lugar']?->id, 'distancia_m' => $u['distancia'], 'ubicacion' => $u['ubicacion'],
        ]);
    }

    public function marcarEntrada(Empleado $e, ?Carbon $ahora = null, ?User $por = null, array $marca = []): Asistencia
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

        $a = Asistencia::updateOrCreate(['empleado_id' => $e->id, 'fecha' => $ahora->toDateString()], [
            'hora_entrada' => $hora, 'estado' => $tarde > 0 ? 'tardanza' : 'puntual', 'minutos_tarde' => $tarde,
            'origen' => 'web', 'registrado_por' => $por?->id,
        ]);
        if ($marca) {
            $this->registrarMarcacion($a, 'entrada', $marca);
        }

        return $a;
    }

    public function marcarSalida(Empleado $e, ?Carbon $ahora = null, ?User $por = null, array $marca = []): Asistencia
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

        $a->update(['hora_salida' => $ahora->format('H:i:s'), 'minutos_extra' => $this->minutosExtra($e, $ahora->format('H:i:s')), 'registrado_por' => $por?->id ?? $a->registrado_por]);
        if ($marca) {
            $this->registrarMarcacion($a, 'salida', $marca);
        }

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
            'minutos_tarde' => $tarde, 'minutos_extra' => $this->minutosExtra($e, $salida), 'origen' => 'manual', 'observaciones' => $d['observaciones'] ?? null, 'registrado_por' => $por->id,
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

        $planilla = app(PlanillaService::class);

        return Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->orderBy('nombres')->get()->map(function (Empleado $e) use ($registros, $planilla, $anio, $mes) {
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
                'extra_min'  => (int) $r->sum('minutos_extra'),
                'planilla'   => $planilla->ajuste($e, $anio, $mes, $r),
            ];
        });
    }

    /** Calendario del mes: por empleado, el estado de cada día (null = sin registro). @return array<int, array<int, ?string>> empleado_id => [día => estado] */
    public function calendarioMes(int $anio, int $mes): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfMonth();
        $cal = [];
        foreach (Asistencia::whereBetween('fecha', [$desde->toDateString(), $desde->copy()->endOfMonth()->toDateString()])->get() as $a) {
            $cal[$a->empleado_id][$a->fecha->day] = $a->estado;
        }

        return $cal;
    }

    private function exigirActivo(Empleado $e): void
    {
        if ($e->estado === 'cesado') {
            throw new DomainException('Este empleado figura como cesado.');
        }
    }
}
