<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombre',
        'contacto',
        'direccion',
        'distrito',
        'provincia',
        'departamento',
        'ubigeo',
        'telefono',
        'email',
        'notas',
        'estado',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function cuentasPorCobrar()
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /** Busca por nombre, documento, teléfono, correo o contacto. */
    public function scopeBuscar($query, ?string $termino)
    {
        $termino = trim((string) $termino);
        if ($termino === '') {
            return $query;
        }

        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
                ->orWhere('numero_documento', 'like', "%{$termino}%")
                ->orWhere('telefono', 'like', "%{$termino}%")
                ->orWhere('email', 'like', "%{$termino}%")
                ->orWhere('contacto', 'like', "%{$termino}%");
        });
    }

    /** Cliente con RUC (empresa o persona con negocio): puede recibir factura. */
    public function getEsEmpresaAttribute(): bool
    {
        return $this->tipo_documento === 'RUC';
    }

    /** Ventas que cuentan como compra (sin anuladas, canceladas ni cotizaciones antiguas). */
    public function scopeConTotalComprado($query)
    {
        return $query->withSum(
            ['ventas as total_comprado' => fn ($v) => $v->whereNotIn('estado_pago', ['anulado', 'cancelado', 'cotizacion'])],
            'total'
        );
    }
}
