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
     * Unidades apartadas por notas de pedido abiertas (pendiente, en preparación o lista) cuya cotización todavía no se
     * convirtió en venta. La nota NO descuenta stock: lo aparta. Al convertirse la cotización, la venta descuenta una sola
     * vez y la reserva se libera sola (la cotización deja de estar sin convertir).
     *
     * @param int|null $varianteId       variante; null = solo las líneas sin variante
     * @param bool     $todasVariantes   true = todas las líneas del producto, tengan o no variante
     * @param int|null $exceptoCotizacionId reservas de esa cotización no cuentan (para convertirla sin bloquearse a sí misma)
     */
    public static function reservadoEn($productoId, $almacenId, $varianteId = null, ?int $exceptoCotizacionId = null, bool $todasVariantes = false): int
    {
        return (int) NotaPedidoDetalle::where('producto_id', $productoId)
            ->when(!$todasVariantes, fn ($q) => $varianteId ? $q->where('variante_id', $varianteId) : $q->whereNull('variante_id'))
            ->whereHas('nota', fn ($n) => $n->where('almacen_id', $almacenId)->whereIn('estado', NotaPedido::ESTADOS_QUE_RESERVAN)
                ->whereHas('cotizacion', fn ($c) => $c->where('estado', '!=', 'convertida')
                    ->when($exceptoCotizacionId, fn ($x) => $x->where('id', '!=', $exceptoCotizacionId))))
            ->sum('cantidad');
    }

    /** Lo que realmente se puede vender: stock físico menos lo apartado por notas de pedido. */
    public static function disponibleEn($productoId, $almacenId, $varianteId = null, ?int $exceptoCotizacionId = null): int
    {
        return max(0, self::cantidadEn($productoId, $almacenId, $varianteId) - self::reservadoEn($productoId, $almacenId, $varianteId, $exceptoCotizacionId));
    }

    /** Igual, pero para un producto sin elegir variante (suma de todas). */
    public static function disponibleTotalEn($productoId, $almacenId, ?int $exceptoCotizacionId = null): int
    {
        return max(0, self::totalEn($productoId, $almacenId) - self::reservadoEn($productoId, $almacenId, null, $exceptoCotizacionId, true));
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
