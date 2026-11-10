<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aprobacion extends Model
{
    protected $table = 'aprobaciones';

    protected $fillable = [
        'accion', 'ruta', 'parametros', 'datos', 'archivos', 'titulo', 'resumen', 'detalle',
        'solicitante_id', 'estado', 'resuelta_por', 'resuelta_at', 'motivo_rechazo', 'ultimo_error',
    ];

    protected $casts = [
        'parametros'  => 'array',
        'datos'       => 'array',
        'archivos'    => 'array',
        'detalle'     => 'array',
        'resuelta_at' => 'datetime',
    ];

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public function scopePendientes($q)
    {
        return $q->where('estado', 'pendiente');
    }

    public function getEstaPendienteAttribute(): bool
    {
        return $this->estado === 'pendiente';
    }
}
