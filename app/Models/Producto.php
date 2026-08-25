<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Categoria;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\Modelo;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Catalogo\Color;


class Producto extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'categoria_id',
        'marca_id', 'modelo_id', 'color_id', 'unidad_medida_id',
        'tipo_inventario', 'dias_garantia', 'tipo_garantia',
        'stock_actual', 'stock_minimo', 'stock_maximo', 'ubicacion',
        'costo_promedio', 'ultimo_costo_compra', 'fecha_ultima_compra',
        'estado', 'imagen', 'creado_por', 'modificado_por',
        'codigo_barras', 'codigo_sunat'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stock_actual'        => 'integer',
        'stock_minimo'        => 'integer',
        'stock_maximo'        => 'integer',
        'estado'              => 'string',
        'tipo_inventario'     => 'string',
        'tipo_garantia'       => 'string',
        'fecha_ultima_compra' => 'date',
        'ultimo_costo_compra' => 'decimal:2',
        'costo_promedio'      => 'decimal:2',
    ];

    /**
     * Relación: Un producto pertenece a una categoría
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'productos_proveedor')
                    ->withPivot('codigo_proveedor', 'ultimo_precio_compra', 
                               'ultima_fecha_compra', 'plazo_entrega_dias', 
                               'es_preferente', 'observaciones')
                    ->withTimestamps();
    }

    /**
     * Relación: Un producto tiene muchos movimientos de inventario
     */
    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    // En app/Models/Producto.php

    // Relación con precios
    public function precios()
    {
        return $this->hasMany(ProductoPrecio::class);
    }

    // Obtener precio de venta actual
    public function getPrecioVentaAttribute(): float
    {
        try {
            return (float) ($this->precios()
                ->where('tipo_precio', 'venta_regular')
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereNull('fecha_inicio')
                      ->orWhere('fecha_inicio', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', now());
                })
                ->orderBy('prioridad', 'desc')
                ->value('precio') ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    // Obtener precio mayorista
    public function getPrecioMayoristaAttribute(): ?float
    {
        try {
            $precio = $this->precios()
                ->where('tipo_precio', 'venta_mayorista')
                ->where('activo', true)
                ->orderBy('prioridad', 'desc')
                ->value('precio');

            return $precio !== null ? (float) $precio : null;
        } catch (\Throwable) {
            return null;
        }
    }
    /**
     * Scope para productos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para productos con stock bajo
     */
    public function scopeStockBajo($query)
    {
        return $query->whereRaw('stock_actual <= stock_minimo');
    }

    /**
     * Scope para productos sin stock
     */
    public function scopeSinStock($query)
    {
        return $query->where('stock_actual', 0);
    }

    /**
     * Scope para productos por categoría
     */
    public function scopeDeCategoria($query, $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }

    /**
     * Scope para búsqueda por código o nombre
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where(function($q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->orWhere('codigo_barras', 'like', "%{$termino}%");
        });
    }
    protected $appends = ['imagen_url'];

    /**
     * Accessor: URL completa de la imagen
     */
    public function getImagenUrlAttribute()
    {
        if ($this->imagen) {
            return asset('storage/' . $this->imagen);
        }

        return null;
    }

    /**
     * Accessor: Nombre de categoría
     */
    public function getNombreCategoriaAttribute()
    {
        return $this->categoria ? $this->categoria->nombre : 'Sin categoría';
    }


    /**
     * Accessor: Estado del stock
     */
    public function getEstadoStockAttribute()
    {
        if ($this->stock_actual == 0) {
            return 'sin_stock';
        } elseif ($this->stock_actual <= $this->stock_minimo) {
            return 'bajo';
        } elseif ($this->stock_actual >= $this->stock_maximo) {
            return 'exceso';
        }
        return 'normal';
    }

    /**
     * Accessor: Color del estado de stock para UI
     */
    public function colores()
    {
        return $this->belongsToMany(Color::class, 'producto_color')
                    ->withTimestamps();
    }
    public function getColorEstadoStockAttribute()
    {
        return match($this->estado_stock) {
            'sin_stock' => 'red',
            'bajo' => 'yellow',
            'exceso' => 'blue',
            default => 'green',
        };
    }

    /**
     * Verificar si el producto está activo
     */
    public function estaActivo()
    {
        return $this->estado === 'activo';
    }

    /**
     * Verificar si tiene stock disponible
     */
    public function tieneStock($cantidad = 1)
    {
        return $this->stock_actual >= $cantidad;
    }

    /**
     * Verificar si está en stock bajo
     */
    public function stockBajo()
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    /**
     * Calcular valor total del inventario de este producto
     */
    public function getValorInventarioAttribute()
    {
        return $this->stock_actual * ($this->costo_promedio ?? 0);
    }

    /**
     * Generar código automático para nuevo producto
     */
    public static function generarCodigo()
    {
        $ultimoProducto = self::latest('id')->first();
        $numero = $ultimoProducto ? $ultimoProducto->id + 1 : 1;
        return 'PROD-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener productos más vendidos (placeholder para cuando tengamos ventas)
     */
    public static function masVendidos($limite = 10)
    {
        // TODO: Implementar cuando tengamos el módulo de ventas
        return self::activos()->limit($limite)->get();
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de eliminar, verificar que no tenga movimientos
        static::deleting(function ($producto) {
            if ($producto->movimientos()->count() > 0) {
                throw new \Exception('No se puede eliminar un producto que tiene movimientos registrados.');
            }
        });
    }
    
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'modelo_id');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class);
    }
    public function codigosBarras()
    {
        return $this->hasMany(ProductoCodigoBarras::class);
    }

    /**
     * Variantes del producto (color + capacidad)
     */
    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class);
    }

    public function variantesActivas()
    {
        return $this->hasMany(ProductoVariante::class)->where('estado', 'activo');
    }

    public function imeis()
    {
        return $this->hasMany(\App\Models\Imei::class);
    }

    public function imeisEnStock()
    {
        return $this->hasMany(\App\Models\Imei::class)->where('estado_imei', 'en_stock');
    }

    public function stockAlmacenes()
    {
        return $this->hasMany(StockAlmacen::class);
    }

    /**
     * ¿Este producto tiene variantes definidas?
     */
    public function tieneVariantes(): bool
    {
        return $this->variantes()->exists();
    }

    /**
     * Stock total sumado de todas las variantes activas
     */
    public function getStockVariantesAttribute(): int
    {
        return $this->variantesActivas()->sum('stock_actual');
    }
}