<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movimientos_inventario';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'producto_id',
        'almacen_id',
        'user_id',
        'tipo_movimiento',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
        'motivo_movimiento_id',
        'observaciones',
        'documento_referencia',
        'numero_factura',
        'numero_guia',
        'estado',
        'almacen_destino_id',
        'transportista',
        'fecha_traslado',
        'fecha_recepcion',
        'usuario_confirma_id',
        'fecha_confirmacion',
        'variante_id',
        'detalle_venta_id',
        'eliminado_en',
        'eliminado_por',
        'motivo_eliminacion',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
        'tipo_movimiento' => 'string',
        'eliminado_en' => 'datetime',
    ];

    /**
     * Relación: Un movimiento pertenece a un producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación: Un movimiento pertenece a un almacén
     */
    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * Relación: Un movimiento pertenece a un almacén destino (transferencias)
     */
    public function almacenDestino()
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * Relación: Un movimiento es registrado por un usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalleVenta()
    {
        return $this->belongsTo(DetalleVenta::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    /**
     * Scope para movimientos de ingreso
     */
    public function scopeIngresos($query)
    {
        return $query->where('tipo_movimiento', 'ingreso');
    }

    /**
     * Scope para movimientos de salida
     */
    public function scopeSalidas($query)
    {
        return $query->where('tipo_movimiento', 'salida');
    }

    /**
     * Scope para movimientos de ajuste
     */
    public function scopeAjustes($query)
    {
        return $query->where('tipo_movimiento', 'ajuste');
    }

    /**
     * Scope para transferencias
     */
    public function scopeTransferencias($query)
    {
        return $query->where('tipo_movimiento', 'transferencia');
    }

    /**
     * Scope para movimientos de hoy
     */
    public function scopeHoy($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope para movimientos por producto
     */
    public function scopeDeProducto($query, $productoId)
    {
        return $query->where('producto_id', $productoId);
    }

    /**
     * Scope para movimientos por almacén
     */
    public function scopeDeAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    /**
     * Scope para movimientos por usuario
     */
    public function scopeDeUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para movimientos entre fechas
     */
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
    }

    /**
     * Accessor: Nombre del tipo de movimiento en español
     */
    public function getTipoMovimientoNombreAttribute()
    {
        return match($this->tipo_movimiento) {
            'ingreso' => 'Ingreso',
            'salida' => 'Salida',
            'ajuste' => 'Ajuste',
            'transferencia' => 'Transferencia',
            'devolucion' => 'Devolución',
            'merma' => 'Merma',
            default => 'Desconocido',
        };
    }

    /**
     * Accessor: Color del tipo de movimiento para UI
     */
    public function getColorTipoMovimientoAttribute()
    {
        return match($this->tipo_movimiento) {
            'ingreso' => 'green',
            'salida' => 'red',
            'ajuste' => 'blue',
            'transferencia' => 'purple',
            'devolucion' => 'orange',
            'merma' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Accessor: Icono del tipo de movimiento
     */
    public function getIconoTipoMovimientoAttribute()
    {
        return match($this->tipo_movimiento) {
            'ingreso' => 'fa-arrow-down',
            'salida' => 'fa-arrow-up',
            'ajuste' => 'fa-sliders-h',
            'transferencia' => 'fa-exchange-alt',
            'devolucion' => 'fa-undo',
            'merma' => 'fa-trash',
            default => 'fa-question',
        };
    }

    /**
     * Accessor: Nombre del usuario que registró el movimiento
     */
    public function getNombreUsuarioAttribute()
    {
        return $this->usuario ? $this->usuario->name : 'Sistema';
    }

    /**
     * Accessor: Nombre del almacén
     */
    public function getNombreAlmacenAttribute()
    {
        return $this->almacen ? $this->almacen->nombre : 'N/A';
    }

    /**
     * Accessor: Diferencia de stock
     */
    public function getDiferenciaStockAttribute()
    {
        return $this->stock_nuevo - $this->stock_anterior;
    }

    /**
     * Verificar si es un ingreso
     */
    public function esIngreso()
    {
        return $this->tipo_movimiento === 'ingreso';
    }

    /**
     * Verificar si es una salida
     */
    public function esSalida()
    {
        return $this->tipo_movimiento === 'salida';
    }

    /**
     * Verificar si es una transferencia
     */
    public function esTransferencia()
    {
        return $this->tipo_movimiento === 'transferencia';
    }
    public function usuarioConfirma()
    {
        return $this->belongsTo(User::class, 'usuario_confirma_id');
    }

    public function usuarioElimina()
    {
        return $this->belongsTo(User::class, 'eliminado_por');
    }

    /**
     * Scope: excluye movimientos ocultados desde el historial (ver eliminado_en).
     * No usa Eloquent SoftDeletes a propósito: el modelo prohíbe delete() por
     * trazabilidad (ver boot() abajo), así que "eliminar" solo oculta la fila
     * de listados puntuales, nunca la excluye globalmente de reportes/Kardex.
     */
    public function scopeNoEliminados($query)
    {
        return $query->whereNull('eliminado_en');
    }
    public function motivoMovimiento()
    {
        return $this->belongsTo(\App\Models\Catalogo\MotivoMovimiento::class, 'motivo_movimiento_id');
    }


    /**
     * Crear un movimiento de inventario y actualizar el stock del producto
     */
public static function registrarMovimiento($datos)
{
    \Log::info('🔵 registrarMovimiento INICIADO', $datos);
    if (!isset($datos['user_id'])) {
        $datos['user_id'] = auth()->id() ?? 1; // Fallback al admin si no hay auth
        \Log::warning('⚠️ user_id no proporcionado, usando: ' . $datos['user_id']);
    }
    
    return \DB::transaction(function () use ($datos) {
        $producto = Producto::findOrFail($datos['producto_id']);
        
        \Log::info('🟢 Producto encontrado', [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'tipo' => $producto->tipo_inventario
        ]);

        $userId = $datos['user_id'] ?? auth()->id() ?? 1;


        // Fuente real de stock para productos "cantidad": stock_almacen por almacén.
        // producto.stock_actual es solo un espejo = SUM(stock_almacen.cantidad) de ese
        // producto — antes este método escribía stock_actual directo sin tocar
        // stock_almacen, así que cualquier reconciliación posterior (o cualquier otra
        // pantalla que sí lea stock_almacen) deshacía silenciosamente el ajuste manual.
        // Stock por variante y almacén: si el producto tiene variantes, el movimiento debe indicar cuál.
        $varianteId = !empty($datos['variante_id']) ? (int) $datos['variante_id'] : null;
        if (!$varianteId && $producto->variantes()->where('estado', 'activo')->exists()) {
            throw new \Exception('Seleccione la variante del producto (acabado, tamaño…).');
        }
        if ($varianteId && !$producto->variantes()->where('id', $varianteId)->exists()) {
            throw new \Exception('La variante no pertenece al producto seleccionado.');
        }

        $stockAlmacen = \App\Models\StockAlmacen::fila($datos['producto_id'], $datos['almacen_id'], $varianteId);
        // Bloqueo de la fila: dos movimientos simultáneos no pueden leer la misma existencia
        $stockAlmacen = \App\Models\StockAlmacen::whereKey($stockAlmacen->id)->lockForUpdate()->first();

        $stockAnterior = $stockAlmacen->cantidad;
        $cantidad = $datos['cantidad'];
        $stockNuevo = $stockAnterior;

        switch ($datos['tipo_movimiento']) {
            case 'ingreso':
            case 'devolucion':
                $stockNuevo += $cantidad;
                break;
            case 'salida':
            case 'merma':
            case 'transferencia':
                $stockNuevo -= $cantidad;
                break;
            case 'ajuste':
                $stockNuevo = $datos['stock_nuevo'] ?? $stockAnterior;
                break;
        }

        if ($stockNuevo < 0) {
            throw new \Exception('No hay suficiente stock para realizar este movimiento.');
        }

        $stockAlmacen->update(['cantidad' => $stockNuevo]);

        // En transferencia directa, acreditar de inmediato el almacén destino
        // (antes el stock solo se descontaba de origen y desaparecía del sistema).
        if ($datos['tipo_movimiento'] === 'transferencia' && !empty($datos['almacen_destino_id'])) {
            $stockDestino = \App\Models\StockAlmacen::fila($datos['producto_id'], $datos['almacen_destino_id'], $varianteId);
            $stockDestino->increment('cantidad', $cantidad);
        }

        $movimiento = self::create([
            'producto_id' => $datos['producto_id'],
            'variante_id' => $varianteId,
            'almacen_id' => $datos['almacen_id'],
            'user_id' => $userId,
            'tipo_movimiento' => $datos['tipo_movimiento'],
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'motivo' => $datos['motivo'] ?? null,
            'motivo_movimiento_id' => $datos['motivo_movimiento_id'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'documento_referencia' => $datos['documento_referencia'] ?? null,
            'almacen_destino_id' => $datos['almacen_destino_id'] ?? null,
            'numero_guia' => $datos['numero_guia'] ?? null,
        ]);

        $totalStock = \App\Models\StockAlmacen::where('producto_id', $datos['producto_id'])->sum('cantidad');
        $producto->update(['stock_actual' => $totalStock]);

        // El stock total de la variante es la suma de sus filas por almacén
        if ($varianteId) {
            \App\Models\ProductoVariante::where('id', $varianteId)
                ->update(['stock_actual' => \App\Models\StockAlmacen::where('variante_id', $varianteId)->sum('cantidad')]);
        }

        \Log::info('✅ Movimiento de accesorio creado', [
            'id' => $movimiento->id
        ]);

        return $movimiento;
    });
}
    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Los movimientos NO se pueden eliminar, solo se pueden compensar con nuevos movimientos
        static::deleting(function ($movimiento) {
            throw new \Exception('Los movimientos de inventario no se pueden eliminar por trazabilidad. Realice un movimiento de ajuste.');
        });
    }
}