<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'producto_id',
        'variante_id',
        'imei_id',
        'cantidad',
        'precio_unitario',
        'precio_con_igv',
        'subtotal',
        'subtotal_con_igv',
    ];

    protected $casts = [
        'cantidad'        => 'integer',
        'precio_unitario' => 'decimal:2',
        'precio_con_igv'  => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'subtotal_con_igv'=> 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function imei()
    {
        return $this->belongsTo(Imei::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function comision()
    {
        return $this->hasOne(ComisionDetalleVenta::class, 'detalle_venta_id');
    }

    public function movimientosDevolucion()
    {
        return $this->hasMany(MovimientoInventario::class)
                    ->where('tipo_movimiento', 'devolucion')
                    ->where('estado', '!=', 'anulado');
    }

    public function getCantidadDevueltaAttribute(): int
    {
        return $this->movimientosDevolucion()->sum('cantidad');
    }

    public function getCantidadDisponibleAttribute(): int
    {
        return max(0, $this->cantidad - $this->cantidad_devuelta);
    }
}
