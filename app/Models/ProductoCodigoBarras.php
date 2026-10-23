<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoCodigoBarras extends Model
{
    protected $table = 'productos_codigos_barras';

    protected $fillable = [
        'producto_id', 'variante_id', 'codigo_barras', 'descripcion', 'es_principal'
    ];

    protected $casts = [
        'es_principal' => 'boolean'
    ];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}