<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vacaciones, permisos, licencias y descansos médicos que el empleado pide y el administrador resuelve. */
class SolicitudRrhh extends Model
{
    protected $table = 'solicitudes_rrhh';

    public const TIPOS = ['vacaciones' => 'Vacaciones', 'permiso' => 'Permiso', 'licencia' => 'Licencia', 'descanso_medico' => 'Descanso médico'];

    protected $fillable = ['empleado_id', 'tipo', 'desde', 'hasta', 'motivo', 'estado', 'resuelta_por', 'resuelta_en', 'motivo_rechazo'];

    protected $casts = ['desde' => 'date', 'hasta' => 'date', 'resuelta_en' => 'datetime'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Días corridos que cubre la solicitud. */
    public function getDiasAttribute(): int
    {
        return $this->desde->diffInDays($this->hasta) + 1;
    }
}
