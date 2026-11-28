<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'telefono',
        'email',
        'contacto_nombre',
        'observaciones',
        'pais',
        'ciudad_origen',
        'estado',
    ];

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
    
}
