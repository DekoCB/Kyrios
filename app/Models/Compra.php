<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'pedido_id',
        'user_id',
        'almacen_id',
        'numero_factura',
        'guia_remision',
        'fecha',
        'fecha_vencimiento',
        'forma_pago',
        'condicion_pago',
        'tipo_moneda',
        'tipo_cambio',
        'incluye_igv',
        'subtotal',
        'igv',
        'tipo_operacion',
        'tipo_operacion_texto',
        'total',
        'total_pen',
        'descuento_global',
        'monto_adicional',
        'concepto_adicional',
        'transportista',
        'placa_vehiculo',
        'tipo_compra',
        'numero_dua',
        'numero_manifiesto',
        'agente_aduanas',
        // Campos USD de importación (renombrados desde flete/seguro/otros_gastos)
        'flete_usd',
        'seguro_usd',
        'otros_usd',
        'transporte_local_pen',
        'impuestos_usd',
        'impuestos_pen',
        'percepcion_pen',
        'estado',
        'fecha_anulacion',
        'motivo_anulacion',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_anulacion' => 'datetime',
        'incluye_igv' => 'boolean',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'total_pen' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
        'descuento_global' => 'decimal:2',
        'monto_adicional' => 'decimal:2',
        'flete_usd' => 'decimal:2',
        'seguro_usd' => 'decimal:2',
        'otros_usd' => 'decimal:2',
        'transporte_local_pen' => 'decimal:2',
        'impuestos_usd' => 'decimal:2',
        'impuestos_pen' => 'decimal:2',
        'percepcion_pen' => 'decimal:2',
    ];

    // Constantes para valores fijos
    const FORMAS_PAGO = [
        'contado' => 'Contado',
        'credito' => 'Crédito',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'cheque' => 'Cheque',
    ];

    const TIPOS_MONEDA = [
        'PEN' => 'Soles (PEN)',
        'USD' => 'Dólares (USD)',
    ];

    const ESTADOS = [
        'registrado' => 'Registrado',
        'anulado' => 'Anulado'
    ];

    const TIPOS_COMPRA = [
        'local'       => 'Compra Local',
        'importacion' => 'Importación',
    ];
    const TIPOS_OPERACION_SUNAT = [
    '01' => 'Gravado - Operación gravada (IGV 18%)',
    '02' => 'Exonerado - Operación exonerada',
    '03' => 'Inafecto - Operación inafecta',
    '04' => 'Exportación - Operación de exportación',
    ];

    // Relaciones
    public function getTipoOperacionTextoAttribute()
    {
        return self::TIPOS_OPERACION_SUNAT[$this->tipo_operacion] ?? $this->tipo_operacion;
    }
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function imeis()
    {
        return $this->hasMany(Imei::class, 'compra_id');
    }

    public function movimientos()
    {
        return $this->morphMany(MovimientoInventario::class, 'origen');
    }

    public function movimientosCaja()
    {
        return $this->morphMany(MovimientoCaja::class, 'origen');
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->whereIn('estado', ['pendiente', 'completado']);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completado');
    }

    public function scopeAnuladas($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopePorProveedor($query, $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    public function scopePorAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        }
        return $query->whereDate('fecha', $fechaInicio);
    }

    public function scopePorMes($query, $mes, $año = null)
    {
        $año = $año ?? now()->year;
        return $query->whereYear('fecha', $año)->whereMonth('fecha', $mes);
    }

    public function scopePorPeriodo($query, $año, $mes = null)
    {
        if ($mes) {
            return $query->whereYear('fecha', $año)->whereMonth('fecha', $mes);
        }
        return $query->whereYear('fecha', $año);
    }

    // Accessors
    public function getFormaPagoTextoAttribute()
    {
        return self::FORMAS_PAGO[$this->forma_pago] ?? $this->forma_pago;
    }

    public function getEstadoTextoAttribute()
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getMonedaSimboloAttribute()
    {
        return $this->tipo_moneda === 'PEN' ? 'S/' : '$';
    }

    public function getTotalFormateadoAttribute()
    {
        return $this->moneda_simbolo . ' ' . number_format($this->total, 2);
    }

    public function getSubtotalFormateadoAttribute()
    {
        return $this->moneda_simbolo . ' ' . number_format($this->subtotal, 2);
    }

    public function getIgvFormateadoAttribute()
    {
        return $this->moneda_simbolo . ' ' . number_format($this->igv, 2);
    }

    public function getDiasCreditoAttribute()
    {
        if ($this->forma_pago === 'credito' && $this->fecha_vencimiento) {
            return now()->diffInDays($this->fecha_vencimiento, false);
        }
        return null;
    }

    public function getVencidaAttribute()
    {
        if ($this->forma_pago === 'credito' && $this->fecha_vencimiento) {
            return now()->greaterThan($this->fecha_vencimiento) && $this->estado !== 'anulado';
        }
        return false;
    }

    public function getTotalProductosAttribute()
    {
        return $this->detalles->sum('cantidad');
    }

    // Mutators
    public function setNumeroFacturaAttribute($value)
    {
        $this->attributes['numero_factura'] = strtoupper(trim($value));
    }

    // Métodos de negocio
    public function puedeEditarse()
    {
        return in_array($this->estado, ['borrador', 'pendiente']);
    }

    public function puedeAnularse()
    {
        return $this->estado === 'completado';
    }

    public function anular(string $motivo = null)
    {
        if (!$this->puedeAnularse()) {
            throw new \Exception('Esta compra no puede ser anulada');
        }

        $this->update([
            'estado' => 'anulado',
            'fecha_anulacion' => now(),
            'motivo_anulacion' => $motivo,
        ]);
    }

    public function completar()
    {
        if ($this->estado !== 'pendiente') {
            throw new \Exception('Solo se pueden completar compras pendientes');
        }

        $this->update(['estado' => 'completado']);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($compra) {
            if (empty($compra->codigo)) {
                $compra->codigo = self::generarCodigo();
            }
            if (empty($compra->estado)) {
                $compra->estado = 'registrado';
            }
            if (empty($compra->tipo_moneda)) {
                $compra->tipo_moneda = 'PEN';
            }
            if (empty($compra->incluye_igv)) {
                $compra->incluye_igv = true;
            }
        });

        static::updating(function ($compra) {
            if ($compra->isDirty('estado') && $compra->estado === 'anulado') {
                $compra->fecha_anulacion = now();
            }
        });
    }

    // Generación de código
    public static function generarCodigo()
    {
        $ultimo = self::latest('id')->first();
        $numero = $ultimo ? $ultimo->id + 1 : 1;
        return 'COM-' . date('Y') . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    // Estadísticas
    public static function estadisticasPorProveedor($proveedorId, $año = null)
    {
        $año = $año ?? now()->year;
        
        return self::where('proveedor_id', $proveedorId)
            ->whereYear('fecha', $año)
            ->where('estado', 'completado')
            ->selectRaw('
                COUNT(*) as total_compras,
                SUM(total) as monto_total,
                AVG(total) as promedio,
                MIN(total) as compra_minima,
                MAX(total) as compra_maxima,
                COUNT(DISTINCT MONTH(fecha)) as meses_con_compras
            ')
            ->first();
    }

    public static function estadisticasMensuales($año = null)
    {
        $año = $año ?? now()->year;
        
        return self::whereYear('fecha', $año)
            ->where('estado', 'completado')
            ->selectRaw('
                MONTH(fecha) as mes,
                COUNT(*) as cantidad,
                SUM(total) as monto_total,
                AVG(total) as promedio
            ')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->mapWithKeys(function ($item) {
                $meses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                ];
                return [$meses[$item->mes] => $item];
            });
    }
    public function cuentaPorPagar()
    {
        return $this->hasOne(CuentaPorPagar::class);
    }
}