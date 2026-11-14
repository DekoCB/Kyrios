<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota de pedido: la cotización confirmada llega a almacén para dejar todo preparado antes de la venta. */
class NotaPedido extends Model
{
    protected $table = 'notas_pedido';

    public const ESTADOS = [
        'pendiente'      => ['Pendiente', 'bg-amber-100 text-amber-800'],
        'en_preparacion' => ['En preparación', 'bg-blue-100 text-blue-800'],
        'lista'          => ['Lista para entregar', 'bg-green-100 text-green-800'],
        'entregada'      => ['Entregada', 'bg-gray-200 text-gray-700'],
        'anulada'        => ['Anulada', 'bg-red-100 text-red-700'],
    ];

    protected $fillable = ['codigo', 'cotizacion_id', 'cliente_id', 'almacen_id', 'user_id', 'estado', 'fecha_entrega', 'observaciones', 'atendido_por', 'lista_en'];

    protected $casts = ['fecha_entrega' => 'date', 'lista_en' => 'datetime'];

    public function detalles(): HasMany
    {
        return $this->hasMany(NotaPedidoDetalle::class)->orderBy('id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    /** Solo se puede cambiar mientras no esté entregada ni anulada. */
    public function getEstaAbiertaAttribute(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_preparacion', 'lista'], true);
    }
}
