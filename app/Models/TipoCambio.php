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

    /** Segundos que se reutiliza el tipo de cambio leído en procesos largos (colas); en una petición web dura lo que ella. */
    private const VIGENCIA_EN_MEMORIA = 60;

    protected static function booted(): void
    {
        $olvidar = fn () => app()->forgetInstance('tipo_cambio.vigente');
        static::saved($olvidar);
        static::deleted($olvidar);
    }

    /**
     * El vigente se lee una sola vez por petición: cada precio que se muestra lo pide para convertir a soles y dólares,
     * y pantallas como Nueva venta o Costos hacían cientos de consultas iguales.
     */
    public static function vigente(): ?self
    {
        $app = app();
        $guardado = $app->bound('tipo_cambio.vigente') ? $app->make('tipo_cambio.vigente') : null;
        if ($guardado === null || time() - $guardado[1] > self::VIGENCIA_EN_MEMORIA) {
            $guardado = [self::orderByDesc('fecha')->orderByDesc('id')->first(), time()];
            $app->instance('tipo_cambio.vigente', $guardado);
        }

        return $guardado[0];
    }

    /** Tipo de cambio de venta vigente en una fecha (el último registrado hasta ese día; si no hay, el actual). */
    public static function ventaDelDia($fecha): float
    {
        $t = self::whereDate('fecha', '<=', $fecha)->orderByDesc('fecha')->orderByDesc('id')->first();

        return (float) ($t?->venta ?? self::actual());
    }

    /** Soles por dólar para mostrar y vender (tipo de cambio de venta). */
    public static function actual(): float
    {
        return (float) (self::vigente()?->venta ?? self::POR_DEFECTO_VENTA);
    }
}
