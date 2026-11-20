<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Proyecto de un cliente (arquitecto, diseñador, hotel…): quién lo lleva, montos y resultado. */
class ClienteProyecto extends Model
{
    protected $table = 'cliente_proyectos';

    protected $guarded = [];

    protected $casts = [
        'fecha_recepcion' => 'date', 'fecha_entrega_aprox' => 'date', 'fecha_entrega_real' => 'date',
        'monto_presupuestado' => 'decimal:2', 'monto_vendido' => 'decimal:2',
    ];

    public const RESULTADOS = [
        'Ganado' => 'bg-green-100 text-green-800', 'Entregado' => 'bg-emerald-100 text-emerald-800', 'Enviado' => 'bg-blue-100 text-blue-800',
        'En proceso' => 'bg-amber-100 text-amber-800', 'Perdido' => 'bg-red-100 text-red-800',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function getSimboloAttribute(): string
    {
        return $this->moneda === 'PEN' ? 'S/' : '$';
    }
}
