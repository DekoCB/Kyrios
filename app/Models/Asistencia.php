<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    /** estado => [nombre, clases del distintivo, ¿cuenta como día asistido?] */
    public const ESTADOS = [
        'puntual'         => ['Puntual', 'bg-green-100 text-green-800', true],
        'tardanza'        => ['Tardanza', 'bg-amber-100 text-amber-800', true],
        'falta'           => ['Falta', 'bg-red-100 text-red-800', false],
        'permiso'         => ['Permiso', 'bg-sky-100 text-sky-800', false],
        'vacaciones'      => ['Vacaciones', 'bg-indigo-100 text-indigo-800', false],
        'descanso_medico' => ['Descanso médico', 'bg-purple-100 text-purple-800', false],
        'licencia'        => ['Licencia', 'bg-gray-200 text-gray-700', false],
    ];

    protected $fillable = ['empleado_id', 'fecha', 'hora_entrada', 'hora_salida', 'estado', 'minutos_tarde', 'origen', 'observaciones', 'registrado_por'];

    protected $casts = ['fecha' => 'date', 'minutos_tarde' => 'integer'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    /** Horas trabajadas entre entrada y salida (null si falta alguna). */
    public function getHorasAttribute(): ?float
    {
        if (!$this->hora_entrada || !$this->hora_salida) {
            return null;
        }
        [$h1, $m1] = array_map('intval', explode(':', $this->hora_entrada));
        [$h2, $m2] = array_map('intval', explode(':', $this->hora_salida));

        return max(0, round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60, 2));
    }
}
