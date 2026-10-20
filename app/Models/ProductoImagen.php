<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductoImagen extends Model
{
    public const MAX_POR_VARIANTE = 6;

    protected $table = 'producto_imagenes';

    protected $fillable = ['producto_id', 'variante_id', 'ruta', 'orden', 'es_principal'];

    protected $casts = ['es_principal' => 'boolean', 'orden' => 'integer'];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }
}
