<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoLibroItem extends Model
{
    protected $table = 'catalogo_libro_items';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['imagenes' => 'array', 'caracteristicas' => 'array'];

    /** Tamaños de la foto del producto en la página: proporción de su caja (el «completo» la rellena). */
    public const TAMANOS = ['chico' => 0.6, 'medio' => 0.78, 'grande' => 0.92, 'completo' => 1.0];

    public function pagina()
    {
        return $this->belongsTo(CatalogoLibroPagina::class, 'pagina_id');
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
