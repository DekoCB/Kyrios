<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Métricas de una cuenta en una fecha (cargadas a mano o importadas, por ejemplo desde Metricool). */
class Metrica extends Model
{
    protected $table = 'mkt_metricas';

    /** Campos que se suman en un período (seguidores no: se toma el último valor). */
    public const ACUMULABLES = ['alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics', 'publicaciones'];

    protected $fillable = ['cuenta_id', 'fecha', 'seguidores', 'alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics', 'publicaciones'];

    protected $casts = ['fecha' => 'date'];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }
}
