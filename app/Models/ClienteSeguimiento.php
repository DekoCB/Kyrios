<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Seguimiento comercial de un cliente: qué busca, qué se le presupuestó y cómo va. */
class ClienteSeguimiento extends Model
{
    protected $table = 'cliente_seguimientos';

    protected $guarded = [];

    protected $casts = ['fecha' => 'date', 'monto_presupuestado' => 'decimal:2', 'monto_comprado' => 'decimal:2'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function getSimboloAttribute(): string
    {
        return $this->moneda === 'PEN' ? 'S/' : '$';
    }
}
