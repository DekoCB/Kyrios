<?php

namespace App\Models\Marketing;

use App\Models\User;
use App\Support\Marketing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Campaña publicitaria digital con su presupuesto, inversión y resultados. */
class Campana extends Model
{
    protected $table = 'mkt_campanas';

    public const ESTADOS = ['planificada' => 'Planificada', 'activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'];
    public const COLORES = ['planificada' => 'bg-gray-100 text-gray-700', 'activa' => 'bg-green-100 text-green-800', 'pausada' => 'bg-yellow-100 text-yellow-800', 'finalizada' => 'bg-blue-100 text-blue-800'];
    public const MONEDAS = ['PEN' => 'S/', 'USD' => 'US$'];

    protected $fillable = ['nombre', 'red', 'objetivo', 'estado', 'fecha_inicio', 'fecha_fin', 'moneda', 'presupuesto', 'inversion', 'impresiones',
        'alcance', 'clics', 'conversiones', 'ingresos', 'notas', 'creado_por'];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'presupuesto' => 'float', 'inversion' => 'float', 'ingresos' => 'float'];

    public function publicaciones(): HasMany
    {
        return $this->hasMany(Publicacion::class, 'campana_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function getRedNombreAttribute(): string
    {
        return $this->red ? Marketing::nombreRed($this->red) : 'Varias redes';
    }

    public function getSimboloAttribute(): string
    {
        return self::MONEDAS[$this->moneda] ?? $this->moneda;
    }

    /** Costo por clic: inversión ÷ clics. */
    public function getCpcAttribute(): ?float
    {
        return $this->clics ? round($this->inversion / $this->clics, 2) : null;
    }

    /** Porcentaje de clics sobre impresiones. */
    public function getCtrAttribute(): ?float
    {
        return $this->impresiones ? round($this->clics / $this->impresiones * 100, 2) : null;
    }

    /** Retorno de la inversión publicitaria: ingresos atribuidos ÷ inversión (solo si se cargaron los ingresos). */
    public function getRoasAttribute(): ?float
    {
        return $this->ingresos !== null && $this->inversion > 0 ? round($this->ingresos / $this->inversion, 2) : null;
    }

    public function getCostoPorConversionAttribute(): ?float
    {
        return $this->conversiones ? round($this->inversion / $this->conversiones, 2) : null;
    }

    /** Porcentaje del presupuesto ya invertido. */
    public function getAvancePresupuestoAttribute(): ?float
    {
        return $this->presupuesto > 0 ? round($this->inversion / $this->presupuesto * 100, 1) : null;
    }
}
