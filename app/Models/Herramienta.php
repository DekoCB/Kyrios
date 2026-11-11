<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Herramienta extends Model
{
    protected $table = 'herramientas';

    protected $fillable = ['codigo', 'nombre', 'categoria', 'descripcion', 'ubicacion', 'cantidad_total', 'cantidad_danada', 'estado'];

    protected $casts = ['cantidad_total' => 'integer', 'cantidad_danada' => 'integer'];

    public function salidas()
    {
        return $this->hasMany(HerramientaSalida::class);
    }

    /** Siguiente código correlativo: HE-0001. */
    public static function siguienteCodigo(): string
    {
        $ultimo = (int) static::where('codigo', 'like', 'HE-%')->max(\DB::raw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED)'));

        return 'HE-' . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Unidades que hoy están fuera, en manos de los técnicos. */
    public function getEnUsoAttribute(): int
    {
        return (int) ($this->getAttributes()['en_uso'] ?? $this->salidas()->where('estado', 'en_uso')->sum('cantidad'));
    }

    public function getDisponibleAttribute(): int
    {
        return max(0, $this->cantidad_total - $this->cantidad_danada - $this->en_uso);
    }
}
