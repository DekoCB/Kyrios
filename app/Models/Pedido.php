<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'almacen_id',
        'user_id',
        'fecha',
        'fecha_esperada',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_esperada' => 'date',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetallePedido::class);
    }

    /**
     * Compra generada al recibir la mercadería (registrarCompra() enlaza
     * pedido_id cuando la compra viene de "Recibir mercadería").
     */
    public function compra()
    {
        return $this->hasOne(Compra::class);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($pedido) {
            if (empty($pedido->codigo)) {
                $pedido->codigo = self::generarCodigo();
            }
        });
    }

    public static function generarCodigo()
    {
        $ultimo = self::latest('id')->first();
        $numero = $ultimo ? $ultimo->id + 1 : 1;
        return 'PED-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }
}
