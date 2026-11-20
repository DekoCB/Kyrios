<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AvisoSistema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cumpleaños del equipo (usuarios y fichas de colaborador) y de los clientes: calendario del dashboard y avisos
 * unos días antes. Los avisos se generan al abrir un dashboard y también con `alertas:fechas`, sin repetirse.
 */
class CumpleanosService
{
    /** Con cuántos días de anticipación avisa. */
    public const DIAS_ANTES = 2;

    public const EQUIPO = 'equipo';
    public const CLIENTES = 'clientes';

    /** Quién recibe el aviso del cumpleaños de un cliente. */
    public const ROLES_AVISO_CLIENTES = [Role::ADMINISTRACION, Role::MARKETING];
    public const ROLES_AVISO_EQUIPO = [Role::ADMINISTRACION, Role::CONTADOR, Role::LOGISTICA, Role::OPERACIONES, Role::MARKETING];

    /** Roles que pueden ver los cumpleaños de clientes (los mismos que ven la lista de clientes). */
    public const ROLES_VEN_CLIENTES = [Role::ADMINISTRACION, Role::CONTADOR, Role::MARKETING];

    /**
     * Personas con cumpleaños, ya normalizadas: [tipo, id, nombre, nacimiento (Carbon), user_id].
     * Del equipo: usuarios activos con fecha y colaboradores activos con fecha que no tienen usuario con fecha propia.
     */
    public function personas(?string $tipo = null): Collection
    {
        $lista = collect();

        if ($tipo !== self::CLIENTES) {
            $conFecha = [];
            foreach (User::where('estado', 'activo')->whereNotNull('fecha_nacimiento')->get() as $u) {
                $conFecha[$u->id] = true;
                $lista->push(['tipo' => self::EQUIPO, 'id' => 'u' . $u->id, 'nombre' => $u->name, 'nacimiento' => $u->fecha_nacimiento, 'user_id' => $u->id]);
            }
            foreach (Empleado::where('estado', '!=', 'cesado')->whereNotNull('fecha_nacimiento')->get() as $e) {
                if ($e->user_id && isset($conFecha[$e->user_id])) {
                    continue;
                }
                $lista->push(['tipo' => self::EQUIPO, 'id' => 'e' . $e->id, 'nombre' => trim($e->nombres . ' ' . $e->apellidos), 'nacimiento' => $e->fecha_nacimiento, 'user_id' => $e->user_id]);
            }
        }

        if ($tipo !== self::EQUIPO) {
            foreach (Cliente::where('estado', 'activo')->whereNotNull('fecha_cumpleanos')->get(['id', 'nombre', 'contacto', 'fecha_cumpleanos', 'codigo']) as $c) {
                $lista->push(['tipo' => self::CLIENTES, 'id' => 'c' . $c->id, 'nombre' => $c->contacto ?: $c->nombre, 'nacimiento' => $c->fecha_cumpleanos, 'user_id' => null, 'cliente_id' => $c->id]);
            }
        }

        return $lista;
    }

    /** Fecha en que cae el cumpleaños dentro de $anio (el 29 de febrero se celebra el 28 en años no bisiestos). */
    public function enAnio(Carbon|Empleado $quien, int $anio): Carbon
    {
        $n = $quien instanceof Empleado ? $quien->fecha_nacimiento : $quien;
        $dia = ($n->month === 2 && $n->day === 29 && !Carbon::create($anio, 1, 1)->isLeapYear()) ? 28 : $n->day;

        return Carbon::create($anio, $n->month, $dia)->startOfDay();
    }

    /** Cumpleaños de un mes, agrupados por día: [día => [personas]]. */
    public function delMes(int $anio, int $mes, ?string $tipo = null): array
    {
        $porDia = [];
        foreach ($this->personas($tipo) as $p) {
            $f = $this->enAnio($p['nacimiento'], $anio);
            if ($f->month === $mes) {
                $porDia[$f->day][] = $p + ['fecha' => $f];
            }
        }
        ksort($porDia);

        return $porDia;
    }

    /** Cumpleaños desde hoy hasta dentro de $dias días, ordenados: cada ítem trae la persona, la fecha y los días que faltan. */
    public function proximos(int $dias = 7, ?string $tipo = null): array
    {
        $hoy = today();
        $lista = [];
        foreach ($this->personas($tipo) as $p) {
            foreach ([$hoy->year, $hoy->year + 1] as $anio) {
                $f = $this->enAnio($p['nacimiento'], $anio);
                $falta = (int) $hoy->diffInDays($f, false);
                if ($falta >= 0 && $falta <= $dias) {
                    $lista[] = $p + ['fecha' => $f, 'faltan' => $falta];
                    break;
                }
            }
        }
        usort($lista, fn ($a, $b) => $a['faltan'] <=> $b['faltan'] ?: strcmp($a['nombre'], $b['nombre']));

        return $lista;
    }

    /** Crea los avisos de los cumpleaños que están a DIAS_ANTES o menos (una sola vez por persona y año). Devuelve cuántos avisos nuevos. */
    public function avisar(): int
    {
        $creados = 0;
        foreach ($this->proximos(self::DIAS_ANTES) as $c) {
            $cuando = $c['faltan'] === 0 ? 'hoy' : ($c['faltan'] === 1 ? 'mañana' : 'en 2 días (' . $c['fecha']->format('d/m') . ')');
            $esCliente = $c['tipo'] === self::CLIENTES;
            $aviso = new AvisoSistema(
                clave: 'cumple:' . $c['id'] . ':' . $c['fecha']->year,
                titulo: ($esCliente ? 'Cumpleaños de cliente: ' : 'Cumpleaños de ') . $c['nombre'],
                mensaje: $c['nombre'] . ' cumple años ' . $cuando . ($esCliente ? '. Buen momento para saludarlo.' : '.'),
                url: $esCliente ? route('clientes.show', $c['cliente_id'], false) : '/dashboard',
                nivel: 'info',
            );
            // El propio cumpleañero no recibe el aviso de su cumpleaños
            $destinatarios = User::where('estado', 'activo')
                ->whereHas('role', fn ($q) => $q->whereIn('nombre', $esCliente ? self::ROLES_AVISO_CLIENTES : self::ROLES_AVISO_EQUIPO))
                ->when($c['user_id'], fn ($q) => $q->where('id', '!=', $c['user_id']))->get();
            $creados += app(AlertaService::class)->avisar([], $aviso, $destinatarios, unaVez: true);
        }

        return $creados;
    }
}
