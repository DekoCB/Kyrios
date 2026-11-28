<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota de desempeño de 0 a 20. El aumento no sale de la nota: lo decide Administración, libremente. */
class EvaluacionEmpleado extends Model
{
    protected $table = 'evaluaciones_empleado';

    protected $fillable = ['empleado_id', 'periodo', 'nota', 'comentario', 'aumento', 'sueldo_anterior', 'sueldo_nuevo', 'evaluado_por'];

    protected $casts = ['nota' => 'float', 'aumento' => 'float', 'sueldo_anterior' => 'float', 'sueldo_nuevo' => 'float'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_por');
    }

    /** Clases de color según la nota (solo orientativas): 17+ sobresaliente, 14+ bueno, 11+ regular, menos deficiente. */
    public static function nivel(float $nota): array
    {
        return match (true) {
            $nota >= 17 => ['Sobresaliente', 'bg-emerald-100 text-emerald-800'],
            $nota >= 14 => ['Bueno', 'bg-green-100 text-green-800'],
            $nota >= 11 => ['Regular', 'bg-amber-100 text-amber-800'],
            default => ['Deficiente', 'bg-red-100 text-red-800'],
        };
    }
}
