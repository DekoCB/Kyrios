<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto o escaneo de un comprobante; el archivo vive en el disco privado. */
class CajaChicaAdjunto extends Model
{
    protected $table = 'caja_chica_adjuntos';

    protected $fillable = ['movimiento_id', 'ruta', 'nombre_original', 'mime', 'tamano'];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(CajaChicaMovimiento::class, 'movimiento_id');
    }

    public function getEsImagenAttribute(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
