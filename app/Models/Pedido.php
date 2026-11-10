<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'almacen_id',
        'user_id',
        'fecha',
        'fecha_esperada',
        'estado',
        'observaciones',
        'tipo_compra',
        'moneda',
        'tipo_cambio',
        'incoterm',
        'condicion_pago',
        'lugar_entrega',
        'subtotal',
        'igv',
        'total',
    ];

    /** IGV que se suma a las órdenes de compra nacionales. */
    public const TASA_IGV = 0.18;

    /** Términos de entrega (Incoterms 2020) para las órdenes internacionales. */
    public const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    protected $casts = [
        'fecha' => 'date',
        'fecha_esperada' => 'date',
        'tipo_cambio' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function getEsInternacionalAttribute(): bool
    {
        return $this->tipo_compra === 'internacional';
    }

    public function getSimboloMonedaAttribute(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    /**
     * Subtotal, IGV y total de una orden. Las nacionales suman IGV; las internacionales no
     * (el IGV de la importación se paga en aduana y se registra en la compra).
     *
     * @return array{subtotal:float,igv:float,total:float}
     */
    public static function calcularTotales(array $lineas, string $tipoCompra): array
    {
        $subtotal = round(collect($lineas)->sum(fn ($l) => (int) ($l['cantidad'] ?? 0) * (float) ($l['precio_referencial'] ?? 0)), 2);
        $igv = $tipoCompra === 'nacional' ? round($subtotal * self::TASA_IGV, 2) : 0.0;

        return ['subtotal' => $subtotal, 'igv' => $igv, 'total' => round($subtotal + $igv, 2)];
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetallePedido::class);
    }

    /**
     * Compra generada al recibir la mercadería (registrarCompra() enlaza
     * pedido_id cuando la compra viene de "Recibir mercadería").
     */
    public function compra()
    {
        return $this->hasOne(Compra::class);
    }

    public function etapas()
    {
        return $this->hasMany(PedidoEtapa::class)->orderBy('orden')->orderBy('id');
    }

    /** Copia las etapas del catálogo a la orden la primera vez; si ya las tiene, no toca nada. */
    public function asegurarSeguimiento(): void
    {
        if ($this->etapas()->exists()) {
            return;
        }

        foreach (EtapaSeguimiento::paraTipo($this->tipo_compra ?? 'nacional')->get() as $etapa) {
            PedidoEtapa::create(['pedido_id' => $this->id, 'etapa_id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden]);
        }
        $this->unsetRelation('etapas');
    }

    /** Primera etapa que todavía no se cumplió: dónde va la orden. */
    public function getEtapaActualAttribute(): ?PedidoEtapa
    {
        return $this->etapas->first(fn ($e) => !$e->completada);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($pedido) {
            if (empty($pedido->codigo)) {
                $pedido->codigo = self::generarCodigo();
            }
        });
    }

    public static function generarCodigo()
    {
        $ultimo = self::latest('id')->first();
        $numero = $ultimo ? $ultimo->id + 1 : 1;
        return 'OC-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }
}
