<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoLibroPagina extends Model
{
    protected $table = 'catalogo_libro_paginas';

    protected $guarded = [];

    /** Cuántos productos admite cada diseño de página (la portada de sección usa uno, solo por su foto). */
    public const CAPACIDAD = ['seccion' => 1, 'uno' => 1, 'dos' => 2, 'cuatro' => 4];

    public function catalogo()
    {
        return $this->belongsTo(CatalogoLibro::class, 'catalogo_id');
    }

    public function items()
    {
        return $this->hasMany(CatalogoLibroItem::class, 'pagina_id')->orderBy('orden');
    }
}
