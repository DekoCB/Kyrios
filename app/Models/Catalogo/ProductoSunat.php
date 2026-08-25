<?php
// app/Models/Catalogo/ProductoSunat.php

namespace App\Models\Catalogo;

use Illuminate\Database\Eloquent\Model;

class ProductoSunat extends Model
{
    protected $table = 'catalogo_productos_sunat';

    protected $fillable = [
        'codigo', 'producto',
        'codigo_clase', 'clase',
        'codigo_familia', 'familia',
        'codigo_segmento', 'segmento',
    ];

    public function scopeBuscar($query, string $termino)
    {
        $termino = trim($termino);

        return $query->where(function ($q) use ($termino) {
            $q->where('codigo', 'like', "{$termino}%")
              ->orWhere('producto', 'like', "%{$termino}%");
        })->orderByRaw("CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END", [$termino, "{$termino}%"]);
    }
}
