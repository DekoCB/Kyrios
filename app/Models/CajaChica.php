<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caja chica: la general de la empresa o una asignada a una persona (por ejemplo un técnico).
 * El saldo se calcula de sus movimientos; los gastos rechazados no descuentan.
 */
class CajaChica extends Model
{
    protected $table = 'cajas_chicas';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'responsable_id', 'observaciones', 'estado', 'creado_por', 'cerrada_en'];

    protected $casts = ['cerrada_en' => 'datetime'];

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaChicaMovimiento::class);
    }

    public function getEstaActivaAttribute(): bool
    {
        return $this->estado === 'activa';
    }

    public function getTotalIngresosAttribute(): float
    {
        return (float) $this->movimientos()->where('tipo', 'ingreso')->sum('monto');
    }

    /** Gastos que cuentan: todos menos los rechazados. */
    public function getTotalGastosAttribute(): float
    {
        return (float) $this->movimientos()->where('tipo', 'gasto')->where('estado', '!=', 'rechazado')->sum('monto');
    }

    public function getSaldoAttribute(): float
    {
        return round($this->total_ingresos - $this->total_gastos, 2);
    }

    public function getPorRevisarAttribute(): int
    {
        return $this->movimientos()->where('tipo', 'gasto')->whereIn('estado', ['pendiente', 'observado'])->count();
    }
}
