<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtapaSeguimiento extends Model
{
    protected $table = 'etapas_seguimiento';

    protected $fillable = ['nombre', 'tipo_compra', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public const TIPOS = ['ambos' => 'Todas las órdenes', 'nacional' => 'Solo nacionales', 'internacional' => 'Solo internacionales'];

    /** Etapas activas que corresponden a una orden de ese tipo, en orden. */
    public function scopeParaTipo($q, string $tipoCompra)
    {
        return $q->where('activo', true)->whereIn('tipo_compra', ['ambos', $tipoCompra])->orderBy('orden')->orderBy('id');
    }
}
