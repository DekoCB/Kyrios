<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusRegla extends Model
{
    protected $table = 'bonus_reglas';

    protected $fillable = [
        'nombre', 'tipo_aplicacion', 'producto_id', 'categoria_id', 'user_id',
        'tipo_bonus', 'tipo_calculo', 'valor',
        'meta_unidades', 'meta_periodo', 'activo',
    ];

    protected $casts = [
        'valor'         => 'decimal:4',
        'meta_unidades' => 'integer',
        'activo'        => 'boolean',
    ];

    public function producto()  { return $this->belongsTo(Producto::class); }
    public function categoria() { return $this->belongsTo(Categoria::class); }
    public function usuario()   { return $this->belongsTo(User::class, 'user_id'); }
    public function liquidaciones() { return $this->hasMany(BonusLiquidacion::class); }

    public function getTipoAplicacionLabelAttribute(): string
    {
        return match($this->tipo_aplicacion) {
            'producto'          => 'Producto',
            'categoria'         => 'Categoría',
            'producto_usuario'  => 'Producto + Vendedor',
            default             => ucfirst($this->tipo_aplicacion),
        };
    }

    public function getTipoBonusLabelAttribute(): string
    {
        return match($this->tipo_bonus) {
            'fijo' => 'Bono fijo',
            'meta' => 'Por meta',
            default => ucfirst($this->tipo_bonus),
        };
    }

    public function getTipoCalculoLabelAttribute(): string
    {
        return match($this->tipo_calculo) {
            'monto_fijo'       => 'S/ fijo',
            'porcentaje_venta' => '% sobre venta',
            default            => $this->tipo_calculo,
        };
    }

    public function getValorFormateadoAttribute(): string
    {
        return $this->tipo_calculo === 'monto_fijo'
            ? 'S/ ' . number_format($this->valor, 2)
            : number_format($this->valor, 2) . '%';
    }

    public function getDescripcionMetaAttribute(): string
    {
        if ($this->tipo_bonus !== 'meta') return '';
        $periodo = match($this->meta_periodo) {
            'mensual'    => 'mes',
            'quincenal'  => 'quincena',
            'semanal'    => 'semana',
            default      => $this->meta_periodo,
        };
        return "Vende {$this->meta_unidades}+ unidades por {$periodo}";
    }
}
