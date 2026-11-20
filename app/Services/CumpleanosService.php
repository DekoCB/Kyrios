<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AvisoSistema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cumpleaños del personal (fecha de nacimiento de la ficha del colaborador): calendario del dashboard y avisos
 * unos días antes. Los avisos se generan al abrir un dashboard y también con `alertas:fechas`, sin repetirse.
 */
class CumpleanosService
{
    /** Con cuántos días de anticipación avisa. */
    public const DIAS_ANTES = 2;

    public const ROLES_AVISO = [Role::ADMINISTRACION, Role::CONTADOR, Role::LOGISTICA, Role::OPERACIONES, Role::MARKETING];

    /** Personal activo con fecha de nacimiento. */
    private function personal(): Collection
    {
        return Empleado::with('usuario')->where('estado', '!=', 'cesado')->whereNotNull('fecha_nacimiento')->get();
    }

    /** Fecha en que cae el cumpleaños dentro de $anio (el 29 de febrero se celebra el 28 en años no bisiestos). */
    public function enAnio(Empleado $e, int $anio): Carbon
    {
        $n = $e->fecha_nacimiento;
        $dia = ($n->month === 2 && $n->day === 29 && !Carbon::create($anio, 1, 1)->isLeapYear()) ? 28 : $n->day;

        return Carbon::create($anio, $n->month, $dia)->startOfDay();
    }

    /** Cumpleaños de un mes, agrupados por día: [día => [empleados]]. */
    public function delMes(int $anio, int $mes): array
    {
        $porDia = [];
        foreach ($this->personal() as $e) {
            $f = $this->enAnio($e, $anio);
            if ($f->month === $mes) {
                $porDia[$f->day][] = $e;
            }
        }
        ksort($porDia);

        return $porDia;
    }

    /** Cumpleaños desde hoy hasta dentro de $dias días: lista de [empleado, fecha, días que faltan]. */
    public function proximos(int $dias = 7): array
    {
        $hoy = today();
        $lista = [];
        foreach ($this->personal() as $e) {
            foreach ([$hoy->year, $hoy->year + 1] as $anio) {
                $f = $this->enAnio($e, $anio);
                $falta = (int) $hoy->diffInDays($f, false);
                if ($falta >= 0 && $falta <= $dias) {
                    $lista[] = ['empleado' => $e, 'fecha' => $f, 'faltan' => $falta];
                    break;
                }
            }
        }
        usort($lista, fn ($a, $b) => $a['faltan'] <=> $b['faltan'] ?: strcmp($a['empleado']->apellidos, $b['empleado']->apellidos));

        return $lista;
    }

    /** Crea los avisos de los cumpleaños que están a $dias o menos (una sola vez por persona y año). Devuelve cuántos avisos nuevos. */
    public function avisar(): int
    {
        $creados = 0;
        foreach ($this->proximos(self::DIAS_ANTES) as $c) {
            /** @var Empleado $e */
            $e = $c['empleado'];
            $cuando = $c['faltan'] === 0 ? 'hoy' : ($c['faltan'] === 1 ? 'mañana' : 'en 2 días (' . $c['fecha']->format('d/m') . ')');
            $aviso = new AvisoSistema(
                clave: 'cumple:' . $e->id . ':' . $c['fecha']->year,
                titulo: 'Cumpleaños de ' . $e->nombres,
                mensaje: trim($e->nombres . ' ' . $e->apellidos) . ' cumple años ' . $cuando . '.',
                url: '/dashboard',
                nivel: 'info',
            );
            // El propio cumpleañero no recibe el aviso de su cumpleaños
            $destinatarios = User::where('estado', 'activo')->whereHas('role', fn ($q) => $q->whereIn('nombre', self::ROLES_AVISO))
                ->when($e->user_id, fn ($q) => $q->where('id', '!=', $e->user_id))->get();
            $creados += app(AlertaService::class)->avisar([], $aviso, $destinatarios, unaVez: true);
        }

        return $creados;
    }
}
