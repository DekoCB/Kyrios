<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id', 'producto_id', 'variante_id',
        'codigo', 'descripcion',
        'cantidad', 'precio_unitario', 'precio_con_igv', 'subtotal', 'subtotal_con_igv',
    ];

    protected $casts = [
        'cantidad'         => 'integer',
        'precio_unitario'  => 'decimal:4',
        'precio_con_igv'   => 'decimal:4',
        'subtotal'         => 'decimal:2',
        'subtotal_con_igv' => 'decimal:2',
    ];

    /** Nombre K y de fábrica de la línea (en vivo desde el producto; si no hay, el texto guardado al cotizar). */
    public function getNombreMostrarAttribute(): string
    {
        if (!$this->relationLoaded('producto') || !$this->producto) {
            return (string) $this->descripcion;
        }
        $variante = $this->relationLoaded('variante') ? $this->variante : null;
        $detalle = $variante && $variante->nombre_completo !== 'Variante base' ? ' — ' . $variante->nombre_completo : '';

        return \App\Support\EtiquetaProducto::nombre($this->producto) . $detalle;
    }

    /** Código Kyrios y de fábrica de la línea. */
    public function getCodigosMostrarAttribute(): string
    {
        if (!$this->relationLoaded('producto') || !$this->producto) {
            return (string) $this->codigo;
        }

        return \App\Support\EtiquetaProducto::codigos($this->producto, $this->relationLoaded('variante') ? $this->variante : null) ?: (string) $this->codigo;
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
