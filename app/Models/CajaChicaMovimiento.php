<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaChicaMovimiento extends Model
{
    protected $table = 'caja_chica_movimientos';

    public const CATEGORIAS = [
        'combustible' => 'Combustible', 'materiales' => 'Materiales', 'movilidad' => 'Movilidad / pasajes',
        'alimentacion' => 'Alimentación', 'herramientas' => 'Herramientas', 'servicios' => 'Servicios', 'otros' => 'Otros',
    ];

    public const COMPROBANTES = [
        'factura' => 'Factura', 'boleta' => 'Boleta', 'ticket' => 'Ticket', 'recibo' => 'Recibo', 'sin_comprobante' => 'Sin comprobante',
    ];

    protected $fillable = [
        'caja_chica_id', 'tipo', 'fecha', 'monto', 'categoria', 'concepto', 'emisor', 'tipo_comprobante', 'numero_comprobante',
        'estado', 'user_id', 'revisado_por', 'revisado_en', 'motivo_revision',
    ];

    protected $casts = ['fecha' => 'date', 'monto' => 'decimal:2', 'revisado_en' => 'datetime'];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaChica::class, 'caja_chica_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(CajaChicaAdjunto::class, 'movimiento_id');
    }

    public function getCategoriaNombreAttribute(): ?string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    public function getComprobanteNombreAttribute(): ?string
    {
        return self::COMPROBANTES[$this->tipo_comprobante] ?? $this->tipo_comprobante;
    }
}
