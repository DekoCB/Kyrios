<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoLibroItem extends Model
{
    protected $table = 'catalogo_libro_items';

    public $timestamps = false;

    protected $guarded = [];

    public function pagina()
    {
        return $this->belongsTo(CatalogoLibroPagina::class, 'pagina_id');
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
