<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoInternoDetalle extends Model
{
    protected $table = 'pedido_interno_detalles';

    protected $fillable = ['pedido_interno_id', 'producto_id', 'variante_id', 'cantidad'];

    public function pedido()
    {
        return $this->belongsTo(PedidoInterno::class, 'pedido_interno_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
