<?php

namespace App\Models\Marketing;

use App\Models\User;
use App\Support\Marketing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comentario o mensaje recibido en una red, con su responsable y la respuesta dada. */
class Interaccion extends Model
{
    protected $table = 'mkt_interacciones';

    public const TIPOS = ['comentario' => 'Comentario', 'mensaje' => 'Mensaje directo', 'mencion' => 'Mención'];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'atendida' => 'Atendida'];

    protected $fillable = ['red', 'cuenta_id', 'tipo', 'autor', 'texto', 'recibida_en', 'estado', 'asignado_a', 'respuesta', 'respondida_por',
        'respondida_en', 'publicacion_id', 'enlace'];

    protected $casts = ['recibida_en' => 'datetime', 'respondida_en' => 'datetime'];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function respondedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondida_por');
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    public function getRedNombreAttribute(): string
    {
        return Marketing::nombreRed($this->red);
    }
}
