<?php

namespace App\Services;

use App\Models\Marketing\Campana;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Metrica;
use App\Models\Marketing\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Cálculos de estadísticas y reportes de Marketing a partir de lo cargado: métricas por cuenta, publicaciones y campañas.
 * Los acumulables (alcance, impresiones, interacciones…) se suman en el período; los seguidores toman el último valor.
 */
class MarketingEstadisticas
{
    /**
     * Período pedido por la pantalla: semana o mes que contiene a «fecha», o un rango libre.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string} desde, hasta, etiqueta, tipo
     */
    public function periodo(Request $request): array
    {
        $tipo = in_array($request->input('periodo'), ['semana', 'mes', 'rango'], true) ? $request->input('periodo') : 'mes';
        $base = $this->fecha($request->input('fecha')) ?? now();

        if ($tipo === 'rango') {
            $desde = $this->fecha($request->input('desde')) ?? now()->startOfMonth();
            $hasta = $this->fecha($request->input('hasta')) ?? now();
            if ($hasta->lt($desde)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            $desde = $desde->copy()->startOfDay();
            $hasta = $hasta->copy()->endOfDay();
            $etiqueta = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
        } elseif ($tipo === 'semana') {
            $desde = $base->copy()->startOfWeek();
            $hasta = $base->copy()->endOfWeek();
            $etiqueta = 'Semana del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
        } else {
            $desde = $base->copy()->startOfMonth();
            $hasta = $base->copy()->endOfMonth();
            $etiqueta = ucfirst($desde->locale('es')->translatedFormat('F Y'));
        }

        return [$desde, $hasta, $etiqueta, $tipo];
    }

    /** El período inmediatamente anterior, de la misma duración (o la semana/el mes anterior). */
    public function periodoAnterior(Carbon $desde, Carbon $hasta, string $tipo): array
    {
        if ($tipo === 'semana') {
            return [$desde->copy()->subWeek()->startOfWeek(), $desde->copy()->subWeek()->endOfWeek()];
        }
        if ($tipo === 'mes') {
            return [$desde->copy()->subMonthNoOverflow()->startOfMonth(), $desde->copy()->subMonthNoOverflow()->endOfMonth()];
        }
        $dias = $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay()) + 1;

        return [$desde->copy()->subDays($dias)->startOfDay(), $desde->copy()->subDay()->endOfDay()];
    }

    private function fecha(?string $valor): ?Carbon
    {
        try {
            return $valor ? Carbon::parse($valor) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resultados por cuenta en el período.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function porCuenta(Carbon $desde, Carbon $hasta, ?string $red = null, ?int $cuentaId = null): Collection
    {
        $cuentas = Cuenta::activas()->with('responsable')
            ->when($red, fn ($q) => $q->where('red', $red))
            ->when($cuentaId, fn ($q) => $q->where('id', $cuentaId))
            ->orderBy('red')->orderBy('nombre')->get();

        $metricas = Metrica::whereIn('cuenta_id', $cuentas->pluck('id'))
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')->get()->groupBy('cuenta_id');

        return $cuentas->map(function (Cuenta $c) use ($metricas) {
            $filas = $metricas->get($c->id, collect());
            $conSeguidores = $filas->whereNotNull('seguidores');
            $fila = ['cuenta' => $c, 'tiene_datos' => $filas->isNotEmpty(), 'dias' => $filas->count()];

            foreach (Metrica::ACUMULABLES as $campo) {
                $fila[$campo] = $filas->whereNotNull($campo)->isEmpty() ? null : (int) $filas->sum($campo);
            }
            $fila['seguidores'] = $conSeguidores->isEmpty() ? null : (int) $conSeguidores->last()->seguidores;
            $fila['crecimiento'] = $conSeguidores->count() >= 2 ? (int) $conSeguidores->last()->seguidores - (int) $conSeguidores->first()->seguidores : null;
            $fila['engagement'] = $this->engagement($fila['interacciones'], $fila['alcance'], $fila['impresiones']);

            return $fila;
        });
    }

    /** Totales de varias cuentas (suma de acumulables y de seguidores). */
    public function totales(Collection $filas): array
    {
        $t = ['seguidores' => null, 'crecimiento' => null];
        foreach (Metrica::ACUMULABLES as $campo) {
            $t[$campo] = $filas->whereNotNull($campo)->isEmpty() ? null : (int) $filas->sum($campo);
        }
        foreach (['seguidores', 'crecimiento'] as $campo) {
            $t[$campo] = $filas->whereNotNull($campo)->isEmpty() ? null : (int) $filas->sum($campo);
        }
        $t['engagement'] = $this->engagement($t['interacciones'], $t['alcance'], $t['impresiones']);

        return $t;
    }

    /** Interacciones sobre el alcance (o sobre las impresiones si no hay alcance), en porcentaje. */
    public function engagement(?int $interacciones, ?int $alcance, ?int $impresiones): ?float
    {
        $base = $alcance ?: $impresiones;

        return $interacciones !== null && $base ? round($interacciones / $base * 100, 2) : null;
    }

    /**
     * Serie diaria de seguidores por cuenta para el gráfico de crecimiento.
     *
     * @return array{etiquetas: array<int, string>, series: array<int, array{red: string, nombre: string, datos: array<int, ?int>}>}
     */
    public function serieSeguidores(Carbon $desde, Carbon $hasta, ?string $red = null, ?int $cuentaId = null): array
    {
        $cuentas = Cuenta::activas()->when($red, fn ($q) => $q->where('red', $red))->when($cuentaId, fn ($q) => $q->where('id', $cuentaId))->orderBy('red')->get();
        $metricas = Metrica::whereIn('cuenta_id', $cuentas->pluck('id'))->whereNotNull('seguidores')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->orderBy('fecha')->get();

        $fechas = $metricas->pluck('fecha')->map(fn ($f) => $f->toDateString())->unique()->sort()->values();
        $series = $cuentas->map(function (Cuenta $c) use ($metricas, $fechas) {
            $propias = $metricas->where('cuenta_id', $c->id)->keyBy(fn ($m) => $m->fecha->toDateString());

            return ['red' => $c->red, 'nombre' => $c->nombre, 'datos' => $fechas->map(fn ($f) => $propias->has($f) ? (int) $propias[$f]->seguidores : null)->all()];
        })->filter(fn ($s) => collect($s['datos'])->contains(fn ($v) => $v !== null))->values()->all();

        return ['etiquetas' => $fechas->map(fn ($f) => Carbon::parse($f)->format('d/m'))->all(), 'series' => $series];
    }

    /** Publicaciones ya publicadas en el período con sus resultados, de mejor a peor (por interacciones). */
    public function mejoresPublicaciones(Carbon $desde, Carbon $hasta, int $limite = 5, ?string $red = null): Collection
    {
        return Publicacion::where('estado', 'publicada')
            ->whereBetween('publicada_en', [$desde, $hasta])
            ->whereNotNull('interacciones')
            ->when($red, fn ($q) => $q->where('red', $red))
            ->orderByDesc('interacciones')->limit($limite)->get();
    }

    /** Cuántas publicaciones hubo por estado en el período (según la fecha que las ubica en el calendario). */
    public function publicacionesPorEstado(Carbon $desde, Carbon $hasta): array
    {
        $filas = Publicacion::whereBetween('programada_para', [$desde, $hasta])->orWhereBetween('publicada_en', [$desde, $hasta])->get();

        return $filas->groupBy('estado')->map->count()->all();
    }

    /** Campañas que se cruzan con el período, con sus indicadores. */
    public function campanas(Carbon $desde, Carbon $hasta, ?string $red = null): Collection
    {
        return Campana::where(function ($q) use ($desde, $hasta) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hasta->toDateString());
            })
            ->where(function ($q) use ($desde) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $desde->toDateString());
            })
            ->when($red, fn ($q) => $q->where('red', $red))
            ->orderByDesc('fecha_inicio')->get();
    }

    /** Variación porcentual entre dos valores (null si no se puede calcular). */
    public function variacion($actual, $anterior): ?float
    {
        if ($actual === null || $anterior === null || (float) $anterior == 0.0) {
            return null;
        }

        return round(((float) $actual - (float) $anterior) / abs((float) $anterior) * 100, 1);
    }
}
