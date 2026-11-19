<?php

namespace App\Models\Marketing;

use App\Support\Marketing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cuenta de una marca de la competencia que se sigue a mano (las redes no entregan estos datos por API). */
class Competidor extends Model
{
    protected $table = 'mkt_competidores';

    protected $fillable = ['red', 'nombre', 'usuario', 'url', 'notas', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function metricas(): HasMany
    {
        return $this->hasMany(CompetidorMetrica::class, 'competidor_id');
    }

    public function getRedNombreAttribute(): string
    {
        return Marketing::nombreRed($this->red);
    }
}
