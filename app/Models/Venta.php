<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'user_id',
        'cliente_id',
        'almacen_id',
        'fecha',
        'subtotal',
        'igv',
        'total',
        'es_credito',
        'condicion_pago',
        'metodo_pago',
        'tipo_comprobante',
        'guia_remision',
        'transportista',
        'placa_vehiculo',
        'pagos_detalle',
        'estado_pago',
        'estado_sunat',
        'sunat_api_id',
        'sunat_ticket',
        'sunat_descripcion',
        'sunat_enviado_at',
        'venta_origen_id',
        'motivo_nc_codigo',
        'motivo_nc_descripcion',
        'usuario_confirma_id',
        'fecha_confirmacion',
        'observaciones',
        'tipo_venta',
        'tienda_destino_id',
        'sucursal_id',
        'serie_comprobante_id',
        'correlativo',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_confirmacion'=> 'datetime',
        'subtotal'          => 'decimal:2',
        'igv'               => 'decimal:2',
        'total'             => 'decimal:2',
        'pagos_detalle'     => 'array',
        'es_credito'        => 'boolean',
        'sunat_enviado_at'  => 'datetime',
    ];

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmador()
    {
        return $this->belongsTo(User::class, 'usuario_confirma_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function serieComprobante()
    {
        return $this->belongsTo(SerieComprobante::class, 'serie_comprobante_id');
    }

    /**
     * Número de documento completo: FA01-00000001
     */
    public function getNumeroDocumentoAttribute(): ?string
    {
        if ($this->serieComprobante && $this->correlativo) {
            return $this->serieComprobante->serie . '-' . str_pad($this->correlativo, 8, '0', STR_PAD_LEFT);
        }
        return null;
    }

    public function tiendaDestino()
    {
        return $this->belongsTo(User::class, 'tienda_destino_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }


    public function guiaRemision()
    {
        return $this->hasOne(GuiaRemision::class);
    }

    public function cuentaPorCobrar()
    {
        return $this->hasOne(CuentaPorCobrar::class);
    }

    public function auditoria()
    {
        return $this->hasMany(AuditoriaVenta::class)->orderByDesc('created_at');
    }

    /** Nota(s) de crédito emitidas contra este comprobante */
    public function notasCredito()
    {
        return $this->hasMany(Venta::class, 'venta_origen_id');
    }

    /** Comprobante de origen (para NC) */
    public function ventaOrigen()
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id')->withTrashed();
    }

    // ── Helpers de estado SUNAT ──

    /** ¿Ya fue aceptado por SUNAT? → solo se cancela con NC */
    public function getEsAceptadoSunatAttribute(): bool
    {
        return $this->estado_sunat === 'aceptado';
    }

    /** ¿Se puede anular directamente (no enviado a SUNAT aún)? */
    public function getPuedeAnularDirectoAttribute(): bool
    {
        return in_array($this->estado_sunat, ['pendiente_envio', 'rechazado', 'no_aplica']);
    }

    /** ¿Es una nota de crédito? */
    public function getEsNotaCreditoAttribute(): bool
    {
        return in_array($this->tipo_comprobante, ['nc_factura', 'nc_boleta']);
    }

    /** Número SUNAT de la NC: serie-correlativo */
    public function getNumeroNcAttribute(): ?string
    {
        if (!$this->es_nota_credito) return null;
        if ($this->serieComprobante && $this->correlativo) {
            return $this->serieComprobante->serie . '-' . str_pad($this->correlativo, 8, '0', STR_PAD_LEFT);
        }
        return null;
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_pago', 'pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('estado_pago', 'pagado');
    }

    public function scopeCredito($query)
    {
        return $query->where('estado_pago', 'credito');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($venta) {
            if (empty($venta->codigo)) {
                $venta->codigo = self::generarCodigo();
            }
        });
    }

    public static function generarCodigo(): string
    {
        // Incluir registros soft-deleted para evitar colisiones con códigos ya usados
        $maxNum = self::withTrashed()
            ->selectRaw("MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) as n")
            ->whereRaw("codigo REGEXP '^VEN-[0-9]+$'")
            ->value('n');

        $numero = ($maxNum ?? 0) + 1;
        return 'VEN-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }
}
