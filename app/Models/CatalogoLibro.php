<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Catálogo comercial de una temporada: un libro de páginas con productos, que se hojea, imprime y comparte. */
class CatalogoLibro extends Model
{
    protected $table = 'catalogos_libro';

    protected $guarded = [];

    protected $casts = ['vigencia' => 'date', 'enlace_activo' => 'boolean', 'enlace_expira_at' => 'datetime', 'anio' => 'integer'];

    public const TEMPORADAS = ['Primavera–Verano', 'Otoño–Invierno', 'Colección especial'];
    public const ESTADOS = ['borrador', 'publicado', 'archivado'];
    public const PRECIOS = ['none', 'pen', 'usd'];
    /** Vencimientos que se ofrecen para el enlace público (días; null = no vence). */
    public const VENCIMIENTOS = ['7' => '7 días', '30' => '30 días', '90' => '90 días', '' => 'Sin vencimiento'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->token = $c->token ?: self::nuevoToken();
        });
    }

    public static function nuevoToken(): string
    {
        do {
            $t = Str::lower(Str::random(8));
        } while (self::where('token', $t)->exists());

        return $t;
    }

    public function paginas()
    {
        return $this->hasMany(CatalogoLibroPagina::class, 'catalogo_id')->orderBy('orden');
    }

    public function vistas()
    {
        return $this->hasMany(CatalogoLibroVista::class, 'catalogo_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function getUrlPublicaAttribute(): string
    {
        return route('catalogos.publico', $this->token);
    }

    /** El enlace público abre solo si está activo y no venció. */
    public function enlaceDisponible(): bool
    {
        return $this->enlace_activo && ($this->enlace_expira_at === null || $this->enlace_expira_at->isFuture());
    }

    public function etiquetaTemporada(): string
    {
        return $this->temporada . ' ' . $this->anio;
    }
}
