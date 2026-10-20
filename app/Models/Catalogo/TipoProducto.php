<?php

namespace App\Models\Catalogo;

use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    protected $table = 'tipos_producto';

    protected $fillable = ['codigo', 'nombre', 'caso_codigo', 'estado'];

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /** Caso 1 del PDF: el código incluye el tipo de luminaria (variable). */
    public function usaTipoLuminaria(): bool
    {
        return $this->caso_codigo === 'con_luminaria';
    }
}
