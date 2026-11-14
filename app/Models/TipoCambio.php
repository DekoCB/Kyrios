<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tipo de cambio USD → PEN por día. El vigente es el último registrado. */
class TipoCambio extends Model
{
    protected $table = 'tipos_cambio';

    /** Se usa mientras nadie haya registrado un tipo de cambio. */
    public const POR_DEFECTO_COMPRA = 3.70;
    public const POR_DEFECTO_VENTA = 3.75;

    protected $fillable = ['fecha', 'compra', 'venta', 'fuente', 'user_id'];

    protected $casts = ['fecha' => 'date', 'compra' => 'decimal:4', 'venta' => 'decimal:4'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function vigente(): ?self
    {
        return self::orderByDesc('fecha')->orderByDesc('id')->first();
    }

    /** Soles por dólar para mostrar y vender (tipo de cambio de venta). */
    public static function actual(): float
    {
        return (float) (self::vigente()?->venta ?? self::POR_DEFECTO_VENTA);
    }
}
