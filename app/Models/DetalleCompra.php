<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Catalogo\Modelo;
use App\Models\Catalogo\Color;

class DetalleCompra extends Model
{
    use HasFactory; 
    protected $table = 'detalle_compras';


    protected $fillable = [
        'compra_id',
        'producto_id',
        'variante_id',
        'modelo_id',
        'color_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
        'codigo_barras',
        'costo_prorateado_pen',
        'costo_unitario_final_pen',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'costo_prorateado_pen' => 'decimal:4',
        'costo_unitario_final_pen' => 'decimal:4',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }


}
