<?php

namespace App\Models\Marketing;

use App\Models\Producto;
use App\Models\User;
use App\Support\Marketing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Publicación planificada para una red. Recorrido: pendiente → aprobada → programada → publicada
 * (o rechazada, que vuelve a pendiente al corregirla).
 */
class Publicacion extends Model
{
    protected $table = 'mkt_publicaciones';

    public const ESTADOS = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'programada' => 'Programada', 'publicada' => 'Publicada', 'rechazada' => 'Rechazada'];
    public const TIPOS = ['publicacion' => 'Publicación', 'reel' => 'Reel / video corto', 'historia' => 'Historia', 'carrusel' => 'Carrusel', 'video' => 'Video', 'articulo' => 'Artículo'];
    public const COLORES = [
        'pendiente'  => 'bg-yellow-100 text-yellow-800',
        'aprobada'   => 'bg-blue-100 text-blue-800',
        'programada' => 'bg-indigo-100 text-indigo-800',
        'publicada'  => 'bg-green-100 text-green-800',
        'rechazada'  => 'bg-red-100 text-red-800',
    ];

    /** Pasos permitidos: estado actual => estados a los que puede pasar. */
    public const TRANSICIONES = [
        'pendiente'  => ['aprobada', 'rechazada'],
        'rechazada'  => ['pendiente'],
        'aprobada'   => ['programada', 'publicada', 'pendiente'],
        'programada' => ['publicada', 'aprobada'],
        'publicada'  => [],
    ];

    protected $fillable = ['titulo', 'texto', 'red', 'tipo', 'estado', 'programada_para', 'publicada_en', 'enlace', 'campana_id', 'producto_id',
        'creado_por', 'aprobado_por', 'aprobada_en', 'motivo_rechazo', 'alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics'];

    protected $casts = ['programada_para' => 'datetime', 'publicada_en' => 'datetime', 'aprobada_en' => 'datetime'];

    public function medios(): BelongsToMany
    {
        return $this->belongsToMany(Medio::class, 'mkt_publicacion_medio', 'publicacion_id', 'medio_id');
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'campana_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    /** Fecha que ubica la publicación en el calendario: la real si ya salió, si no la programada. */
    public function getFechaCalendarioAttribute()
    {
        return $this->publicada_en ?? $this->programada_para;
    }

    public function getRedNombreAttribute(): string
    {
        return Marketing::nombreRed($this->red);
    }

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getEngagementAttribute(): ?float
    {
        $base = $this->alcance ?: $this->impresiones;

        return $base && $this->interacciones !== null ? round($this->interacciones / $base * 100, 2) : null;
    }
}
