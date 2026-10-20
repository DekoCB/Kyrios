<?php

namespace App\Models\Catalogo;

use Illuminate\Database\Eloquent\Model;

class TipoLuminaria extends Model
{
    protected $table = 'tipos_luminaria';

    protected $fillable = ['codigo', 'nombre', 'estado'];

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
}
