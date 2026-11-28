<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuánto se descuenta o se bonifica al sueldo básico de un mes por tardanzas, faltas y horas extra, según las reglas de
 * cada trabajador. Es una estimación para Administración: no genera la boleta de pago.
 */
class PlanillaService
{
    /**
     * @param Collection<int, Asistencia>|null $registros asistencias del mes de ese empleado (si no, se leen)
     *
     * @return array<string, mixed>
     */
    public function ajuste(Empleado $e, int $anio, int $mes, ?Collection $registros = null): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfMonth();
        $registros ??= Asistencia::where('empleado_id', $e->id)->whereBetween('fecha', [$desde->toDateString(), $desde->copy()->endOfMonth()->toDateString()])->get();

        $sueldo = (float) $e->sueldo_basico;
        $horasJornada = max(1.0, (float) ($e->horas_jornada ?: 8));
        $valorDia = $sueldo / 30;
        $valorHora = $valorDia / $horasJornada;
        $valorMinuto = $valorHora / 60;

        $tardanzas = $registros->where('estado', 'tardanza');
        $nTardanzas = $tardanzas->count();
        $minTarde = (int) $tardanzas->sum('minutos_tarde');
        $nFaltas = $registros->where('estado', 'falta')->count();
        $minExtra = (int) $registros->sum('minutos_extra');
        $horasExtra = round($minExtra / 60, 2);

        // Un empleado recién creado en memoria aún no trae los valores por defecto de la base: se completan aquí
        $extraValor = (float) ($e->extra_valor ?? 1.25);

        $descTardanza = match ($e->tardanza_modo ?? 'proporcional') {
            'proporcional' => $minTarde * $valorMinuto,
            'por_minuto' => $minTarde * (float) $e->tardanza_valor,
            'por_tardanza' => $nTardanzas * (float) $e->tardanza_valor,
            default => 0.0,
        };
        $descFalta = match ($e->falta_modo ?? 'dia') {
            'dia' => $nFaltas * $valorDia,
            'monto' => $nFaltas * (float) $e->falta_valor,
            default => 0.0,
        };
        $bono = match ($e->extra_modo ?? 'factor') {
            'factor' => $horasExtra * $valorHora * $extraValor,
            'por_hora' => $horasExtra * $extraValor,
            default => 0.0,
        };

        [$descTardanza, $descFalta, $bono] = [round($descTardanza, 2), round($descFalta, 2), round($bono, 2)];

        return [
            'sueldo' => round($sueldo, 2), 'valor_dia' => round($valorDia, 2), 'valor_hora' => round($valorHora, 2),
            'tardanzas' => $nTardanzas, 'min_tarde' => $minTarde, 'faltas' => $nFaltas, 'min_extra' => $minExtra, 'horas_extra' => $horasExtra,
            'desc_tardanzas' => $descTardanza, 'desc_faltas' => $descFalta, 'bono_extra' => $bono,
            'descuentos' => round($descTardanza + $descFalta, 2), 'neto' => round($bono - $descTardanza - $descFalta, 2),
            'sueldo_estimado' => round($sueldo + $bono - $descTardanza - $descFalta, 2),
        ];
    }
}
