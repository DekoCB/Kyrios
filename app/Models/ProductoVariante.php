<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Catalogo\Color;

class ProductoVariante extends Model
{
    use HasFactory;

    protected $table = 'producto_variantes';

    protected $fillable = [
        'producto_id',
        'color_id',
        'capacidad',
        'sku',
        'sobreprecio',
        'costo_promedio',
        'ultimo_costo_compra',
        'stock_actual',
        'stock_minimo',
        'estado',
        'imagen',
        'creado_por',
        'codigo_kyrios', 'correlativo', 'codigo_fabricante', 'codigo_excel',
        'acabado_1', 'acabado_2', 'tamano', 'diametro', 'alto', 'ancho', 'profundidad',
        'alto_suspendido', 'diametro_agujero', 'socket', 'num_lamparas', 'potencia_w', 'lumenes',
        'temperatura_color', 'tonalidad_luz', 'estado_dano', 'observacion_dano',
        'tipo_lampara', 'angulo_haz', 'protocolo_regulacion', 'eficiencia_luminica', 'garantia_meses', 'vida_util_horas', 'ip', 'cri', 'otros',
        'precio_fabrica', 'peso', 'volumen', 'embalado', 'medida_embalaje', 'cantidad_por_caja', 'codigos_relacionados',
    ];

    protected $casts = [
        'stock_actual'       => 'integer',
        'stock_minimo'       => 'integer',
        'sobreprecio'        => 'decimal:2',
        'costo_promedio'     => 'decimal:2',
        'ultimo_costo_compra'=> 'decimal:2',
        'estado'             => 'string',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class, 'variante_id')->orderBy('orden');
    }

    public function imagenPrincipal()
    {
        return $this->hasOne(ProductoImagen::class, 'variante_id')->orderByDesc('es_principal')->orderBy('orden');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }


    public function detallesCompra()
    {
        return $this->hasMany(DetalleCompra::class, 'variante_id');
    }

    public function detallesVenta()
    {
        return $this->hasMany(DetalleVenta::class, 'variante_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'variante_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeConStock($query)
    {
        return $query->where('stock_actual', '>', 0);
    }

    // ─── Accessors ─────────────────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        $partes = [];
        foreach ([$this->acabado_1, $this->acabado_2 && $this->acabado_2 !== '-' ? $this->acabado_2 : null, $this->tamano] as $p) {
            if ($p && $p !== '-') {
                $partes[] = $p;
            }
        }
        if ($this->color) {
            $partes[] = $this->color->nombre;
        }
        if ($this->capacidad) {
            $partes[] = $this->capacidad;
        }
        return implode(' / ', $partes) ?: 'Variante base';
    }

    public function getDescripcionCorta(): string
    {
        $nombre = $this->producto ? \App\Support\EtiquetaProducto::nombre($this->producto) : 'Producto';
        $variante = $this->nombre_completo;
        return $variante !== 'Variante base' ? "{$nombre} — {$variante}" : $nombre;
    }

    // ─── Lógica de stock ───────────────────────────────────────────────────────

    public function tieneStock(int $cantidad = 1): bool
    {
        return $this->stock_actual >= $cantidad;
    }

    public function incrementarStock(int $cantidad): void
    {
        $this->increment('stock_actual', $cantidad);
        // Sincronizar con el producto base
        $this->sincronizarStockProductoBase();
    }

    public function decrementarStock(int $cantidad): void
    {
        if ($this->stock_actual < $cantidad) {
            throw new \Exception(
                "Stock insuficiente para la variante {$this->nombre_completo}. " .
                "Disponible: {$this->stock_actual}, solicitado: {$cantidad}"
            );
        }
        $this->decrement('stock_actual', $cantidad);
        $this->sincronizarStockProductoBase();
    }

    public function sincronizarStockProductoBase(): void
    {
        if ($this->producto) {
            // El stock del producto es la suma de stock_almacen (como en compras, ventas y la reconciliación),
            // también de las variantes desactivadas: sus unidades siguen en el almacén.
            $totalStock = StockAlmacen::where('producto_id', $this->producto_id)->sum('cantidad');

            $this->producto->update(['stock_actual' => $totalStock]);
        }
    }

    // ─── Generación de SKU ─────────────────────────────────────────────────────

    /**
     * Genera un SKU único para la variante.
     * Formato: [CODIGO_BASE]-[COL]-[CAP]
     * Ej: PROD-00001-AZU-128G
     */
    public static function generarSku(Producto $producto, ?Color $color, ?string $capacidad): string
    {
        $base = strtoupper($producto->codigo ?? 'PROD');

        $sufijos = [];

        if ($color) {
            // Tomar primeras 3 letras del color
            $sufijos[] = strtoupper(substr(preg_replace('/\s+/', '', $color->nombre), 0, 3));
        }

        if ($capacidad) {
            // Limpiar y abreviar la capacidad: "128 GB" → "128G"
            $cap = strtoupper(preg_replace('/\s+/', '', $capacidad));
            $cap = str_replace(['GB', 'TB', 'MB'], ['G', 'T', 'M'], $cap);
            $sufijos[] = $cap;
        }

        $sku = $base . (count($sufijos) ? '-' . implode('-', $sufijos) : '');

        // Asegurar unicidad
        $original = $sku;
        $contador = 1;
        while (static::where('sku', $sku)->exists()) {
            $sku = $original . '-' . $contador;
            $contador++;
        }

        return $sku;
    }
}
