<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock de un producto en un almacén, por variante.
 *
 * Una fila por (producto, variante, almacén). Los productos sin variantes usan variante_id = NULL.
 * El total de un producto en un almacén es la suma de sus filas (por eso las consultas que suman
 * `where producto_id ... sum('cantidad')` siguen siendo correctas).
 */
class StockAlmacen extends Model
{
    use HasFactory;

    protected $table = 'stock_almacen';

    protected $fillable = [
        'producto_id',
        'variante_id',
        'almacen_id',
        'cantidad',
    ];

    /**
     * Relación con Producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    /**
     * Relación con Almacén
     */
    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * Scope para stock con cantidad mayor a 0
     */
    public function scopeConStock($query)
    {
        return $query->where('cantidad', '>', 0);
    }

    /**
     * Scope por almacén
     */
    public function scopePorAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    /**
     * Scope por producto
     */
    public function scopePorProducto($query, $productoId)
    {
        return $query->where('producto_id', $productoId);
    }

    /**
     * Scope por variante. Con null filtra las filas SIN variante (productos sin variantes).
     */
    public function scopeDeVariante($query, $varianteId)
    {
        return $varianteId ? $query->where('variante_id', $varianteId) : $query->whereNull('variante_id');
    }

    /**
     * Fila de stock de (producto, variante, almacén); la crea en 0 si no existe.
     * Sin variante (null) es la fila "a nivel producto".
     */
    public static function fila($productoId, $almacenId, $varianteId = null): self
    {
        return self::firstOrCreate(
            [
                'producto_id' => $productoId,
                'variante_id' => $varianteId ?: null,
                'almacen_id'  => $almacenId,
            ],
            ['cantidad' => 0]
        );
    }

    /**
     * Cantidad de (producto, variante, almacén) sin crear filas. Sin variante lee la fila a nivel producto.
     */
    public static function cantidadEn($productoId, $almacenId, $varianteId = null): int
    {
        return (int) (self::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->deVariante($varianteId)
            ->value('cantidad') ?? 0);
    }

    /**
     * Total de un producto en un almacén (suma de todas sus variantes).
     */
    public static function totalEn($productoId, $almacenId): int
    {
        return (int) self::where('producto_id', $productoId)->where('almacen_id', $almacenId)->sum('cantidad');
    }

    /**
     * Método estático para obtener o crear stock (compatibilidad: variante opcional)
     */
    public static function obtenerOCrear($productoId, $almacenId, $varianteId = null)
    {
        return self::fila($productoId, $almacenId, $varianteId);
    }

    /**
     * Método para incrementar stock
     */
    public function incrementar($cantidad)
    {
        $this->increment('cantidad', $cantidad);
        return $this;
    }

    /**
     * Método para decrementar stock
     */
    public function decrementar($cantidad)
    {
        if ($this->cantidad >= $cantidad) {
            $this->decrement('cantidad', $cantidad);
            return $this;
        }

        throw new \Exception('Stock insuficiente en almacén');
    }
}
