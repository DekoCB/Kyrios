<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tarea diaria de una persona del equipo (tablero de tareas). */
class Tarea extends Model
{
    protected $table = 'tareas';

    /** prioridad => [nombre, clases del distintivo, orden de importancia] */
    public const PRIORIDADES = [
        'urgente' => ['Urgente', 'bg-red-100 text-red-700', 0],
        'alta'    => ['Alta', 'bg-orange-100 text-orange-700', 1],
        'media'   => ['Media', 'bg-amber-100 text-amber-700', 2],
        'baja'    => ['Baja', 'bg-sky-100 text-sky-700', 3],
    ];

    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'hecha' => 'Hecha'];

    protected $fillable = ['titulo', 'descripcion', 'asignado_a', 'creado_por', 'fecha', 'prioridad', 'estado', 'completada_en', 'orden'];

    protected $casts = ['fecha' => 'date', 'completada_en' => 'datetime'];

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TareaItem::class)->orderBy('orden')->orderBy('id');
    }

    /** Quién puede cambiar la tarea: Administración, la persona asignada o quien la creó. */
    public function editablePor(User $u): bool
    {
        return $u->role?->nombre === Role::ADMINISTRACION || $this->asignado_a === $u->id || $this->creado_por === $u->id;
    }

    /** Formato que usa el tablero (JSON). */
    public function paraTablero(): array
    {
        return [
            'id'         => $this->id,
            'titulo'     => $this->titulo,
            'descripcion' => $this->descripcion,
            'asignado_a' => $this->asignado_a,
            'fecha'      => $this->fecha->toDateString(),
            'prioridad'  => $this->prioridad,
            'estado'     => $this->estado,
            'items'      => $this->items->map(fn ($i) => ['id' => $i->id, 'texto' => $i->texto, 'hecho' => (bool) $i->hecho])->values()->all(),
        ];
    }
}
