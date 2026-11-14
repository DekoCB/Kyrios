<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaPedidoDetalle extends Model
{
    protected $table = 'nota_pedido_detalles';

    protected $fillable = ['nota_pedido_id', 'producto_id', 'variante_id', 'descripcion', 'cantidad', 'preparado'];

    protected $casts = ['cantidad' => 'integer', 'preparado' => 'integer'];

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaPedido::class, 'nota_pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function getCompletoAttribute(): bool
    {
        return $this->preparado >= $this->cantidad;
    }
}
