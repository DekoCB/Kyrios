<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductoVariante;
use App\Models\Almacen;

class ProductoPrecio extends Model
{
    protected $table = 'producto_precios';

    protected $fillable = [
        'producto_id',
        'variante_id',
        'almacen_id',
        'tipo_precio',
        'precio',
        'precio_compra',
        'precio_mayorista',
        'margen',
        'incluye_igv',
        'observaciones',
        'fecha_inicio',
        'fecha_fin',
        'proveedor_id',
        'prioridad',
        'activo',
        'creado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activo' => 'boolean',
        'incluye_igv' => 'boolean'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    // Scope para precios vigentes
    public function scopeVigente($query)
    {
        return $query->where('activo', true)
            ->where(function($q) {
                $q->whereNull('fecha_inicio')
                  ->orWhere('fecha_inicio', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', now());
            });
    }

    // Scope por tipo
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo_precio', $tipo);
    }
}