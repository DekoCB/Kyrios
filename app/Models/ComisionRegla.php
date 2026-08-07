<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComisionRegla extends Model
{
    protected $table = 'comision_reglas';

    protected $fillable = [
        'nombre', 'tipo_aplicacion', 'user_id', 'categoria_id', 'producto_id',
        'tipo_calculo', 'valor', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'valor'  => 'decimal:4',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function getTipoAplicacionLabelAttribute(): string
    {
        return match($this->tipo_aplicacion) {
            'usuario'          => 'Usuario',
            'categoria'        => 'Categoría',
            'producto'         => 'Producto',
            'producto_usuario' => 'Producto + Vendedor',
            'categoria_usuario'=> 'Categoría + Vendedor',
            default            => ucfirst($this->tipo_aplicacion),
        };
    }

    public function getTipoCalculoLabelAttribute(): string
    {
        return match($this->tipo_calculo) {
            'porcentaje'        => 'Porcentaje (%) sobre venta',
            'porcentaje_margen' => 'Porcentaje (%) sobre margen',
            'monto_fijo'        => 'Monto Fijo (S/)',
            default             => $this->tipo_calculo,
        };
    }

    public function getValorFormateadoAttribute(): string
    {
        return in_array($this->tipo_calculo, ['porcentaje', 'porcentaje_margen'])
            ? number_format($this->valor, 2) . '%'
            : 'S/ ' . number_format($this->valor, 2);
    }
}
