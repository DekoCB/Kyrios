<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    public const ESTADOS = ['pendiente', 'aceptada', 'rechazada', 'convertida', 'anulada'];

    protected $fillable = [
        'codigo', 'serie_comprobante_id', 'correlativo',
        'user_id', 'cliente_id', 'almacen_id', 'sucursal_id',
        'fecha', 'fecha_vencimiento', 'validez_dias',
        'subtotal', 'igv', 'total',
        'condiciones', 'observaciones',
        'estado', 'motivo_estado',
        'venta_id', 'tipo_comprobante_convertido', 'convertida_en', 'convertida_por',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'convertida_en'     => 'datetime',
        'subtotal'          => 'decimal:2',
        'igv'               => 'decimal:2',
        'total'             => 'decimal:2',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class)->orderBy('id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function serieComprobante(): BelongsTo
    {
        return $this->belongsTo(SerieComprobante::class, 'serie_comprobante_id');
    }

    // ── Estado ────────────────────────────────────────────────────────────

    /** Una cotización pendiente o aceptada cuya fecha de validez ya pasó. */
    public function getEstaVencidaAttribute(): bool
    {
        return in_array($this->estado, ['pendiente', 'aceptada'], true)
            && $this->fecha_vencimiento->lt(today());
    }

    /** Estado para mostrar: «vencida» no se guarda, se deduce de la fecha. */
    public function getEstadoVisibleAttribute(): string
    {
        return $this->esta_vencida ? 'vencida' : $this->estado;
    }

    public function getPuedeEditarseAttribute(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function getPuedeConvertirseAttribute(): bool
    {
        return in_array($this->estado, ['pendiente', 'aceptada'], true) && !$this->esta_vencida;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeVigentes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'aceptada'])->whereDate('fecha_vencimiento', '>=', today());
    }

    public function scopeVencidas($query)
    {
        return $query->whereIn('estado', ['pendiente', 'aceptada'])->whereDate('fecha_vencimiento', '<', today());
    }
}
