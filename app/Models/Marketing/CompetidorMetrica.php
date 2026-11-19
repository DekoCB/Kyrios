<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de una cuenta competidora en una fecha: seguidores, publicaciones del período e interacciones. */
class CompetidorMetrica extends Model
{
    protected $table = 'mkt_competidor_metricas';

    protected $fillable = ['competidor_id', 'fecha', 'seguidores', 'publicaciones', 'interacciones', 'destacada', 'enlace_destacada'];

    protected $casts = ['fecha' => 'date'];

    public function competidor(): BelongsTo
    {
        return $this->belongsTo(Competidor::class, 'competidor_id');
    }
}
