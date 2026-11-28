<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una marcación (entrada o salida) con su selfie y ubicación, y si cae dentro de un lugar de trabajo. */
class AsistenciaMarcacion extends Model
{
    protected $table = 'asistencia_marcaciones';

    protected $fillable = ['asistencia_id', 'empleado_id', 'tipo', 'foto', 'latitud', 'longitud', 'precision_m', 'lugar_trabajo_id', 'distancia_m', 'ubicacion', 'revision', 'revisada_por', 'revisada_at'];

    protected $casts = ['latitud' => 'float', 'longitud' => 'float', 'revisada_at' => 'datetime'];

    public const UBICACIONES = [
        'valida' => ['En su lugar de trabajo', 'bg-green-100 text-green-800', 'fa-location-dot'],
        'fuera' => ['Fuera de los lugares registrados', 'bg-amber-100 text-amber-800', 'fa-triangle-exclamation'],
        'sin_dato' => ['Sin ubicación', 'bg-gray-200 text-gray-700', 'fa-location-crosshairs'],
    ];

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function lugar(): BelongsTo
    {
        return $this->belongsTo(LugarTrabajo::class, 'lugar_trabajo_id');
    }

    /** Estado final: lo que decidió Administración tiene prioridad sobre el cálculo automático. */
    public function getEstadoFinalAttribute(): string
    {
        return $this->revision ?: $this->ubicacion;
    }

    public function getMapaUrlAttribute(): ?string
    {
        return $this->latitud !== null ? 'https://www.google.com/maps?q=' . $this->latitud . ',' . $this->longitud : null;
    }
}
