<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Imei;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Catalogo\Modelo;
use App\Models\Catalogo\Color;
use App\Models\CuentaPorPagar;
use App\Services\VarianteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CompraService
{
    /**
     * Registrar una nueva compra con todos sus detalles
     */
    public function registrarCompra(array $datosCompra, array $detalles): Compra
    {
        return DB::transaction(function () use ($datosCompra, $detalles) {
            
            // 1. Validaciones adicionales antes de crear
            $this->validarDetalles($detalles);
            
            // 2. Crear la compra
            $compra = Compra::create($datosCompra);
            
            $subtotalGeneral = 0;
            
            // 3. Procesar cada detalle
            foreach ($detalles as $detalle) {
                $productoBase = Producto::findOrFail($detalle['producto_id']);

                // Resolver variante de producto si se especificó variante_id o color/capacidad
                $variante = null;
                if (!empty($detalle['variante_id'])) {
                    $variante = ProductoVariante::findOrFail($detalle['variante_id']);
                } elseif (!empty($detalle['color_id']) || !empty($detalle['capacidad'])) {
                    $varianteService = app(VarianteService::class);
                    $variante = $varianteService->obtenerOCrearVariante(
                        $productoBase,
                        $detalle['color_id'] ?? null,
                        $detalle['capacidad'] ?? null,
                        0
                    );
                }

                // Calcular subtotal del detalle con descuento si existe
                $precioConDescuento = $detalle['precio_unitario'];
                if (isset($detalle['descuento']) && $detalle['descuento'] > 0) {
                    $precioConDescuento = $detalle['precio_unitario'] * (1 - $detalle['descuento'] / 100);
                }

                $subtotalDetalle = $detalle['cantidad'] * $precioConDescuento;
                $subtotalGeneral += $subtotalDetalle;

                // 3.1 Crear detalle de compra (con variante_id si existe)
                $detalleCompra = DetalleCompra::create([
                    'compra_id'       => $compra->id,
                    'producto_id'     => $productoBase->id,
                    'variante_id'     => $variante?->id,
                    'modelo_id'       => $detalle['modelo_id'] ?? null,
                    'color_id'        => $detalle['color_id'] ?? ($variante?->color_id),
                    'cantidad'        => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento'       => $detalle['descuento'] ?? 0,
                    'subtotal'        => $subtotalDetalle,
                ]);

                // 3.2 Actualizar stock (variante o producto base)
                $this->actualizarStock($productoBase, $compra, $detalle, $variante);

                // 3.3 Registrar IMEIs si es serie/IMEI y re-sincronizar stock desde IMEI count
                if ($productoBase->tipo_inventario === 'serie') {
                    $this->registrarIMEIs($detalle, $productoBase, $compra, $variante, $detalleCompra);

                    // Para productos serie la fuente de verdad es el conteo de IMEIs en_stock,
                    // no StockAlmacen. Re-sincronizamos para corregir lo que actualizarStock() calculó.
                    $imeiStock = \App\Models\Imei::where('producto_id', $productoBase->id)
                        ->where('estado_imei', 'en_stock')
                        ->count();
                    $productoBase->update(['stock_actual' => $imeiStock]);

                    if ($variante) {
                        $varianteStock = \App\Models\Imei::where('producto_id', $productoBase->id)
                            ->where('variante_id', $variante->id)
                            ->where('estado_imei', 'en_stock')
                            ->count();
                        $variante->update(['stock_actual' => $varianteStock]);
                    }
                }

                // 3.4 Registrar código de barras generado si existe
                if (isset($detalle['codigo_barras']) && $detalle['codigo_barras']) {
                    $this->actualizarCodigoBarras($productoBase, $detalle['codigo_barras']);
                }

                // 3.5 Actualizar precio de compra del producto (y variante si aplica)
                $this->actualizarPrecioProducto($productoBase, $detalle['precio_unitario']);
                if ($variante) {
                    $this->actualizarCostoVariante($variante, (float) $detalle['precio_unitario']);
                }
            }

            // 🔴 CREAR CUENTA POR PAGAR
            $fechaVencimiento = $compra->fecha;

            if ($compra->forma_pago === 'credito' && !is_null($compra->condicion_pago) && $compra->condicion_pago > 0) {
                $dias = (int)$compra->condicion_pago;
                $fechaVencimiento = $compra->fecha->copy()->addDays($dias);
            }

            $estadoInicial = $compra->forma_pago === 'contado' ? 'pagado' : 'pendiente';
            $montoPagadoInicial = $compra->forma_pago === 'contado' ? $compra->total : 0;

            try {
                CuentaPorPagar::create([
                    'compra_id' => $compra->id,
                    'proveedor_id' => $compra->proveedor_id,
                    'numero_factura' => $compra->numero_factura,
                    'fecha_emision' => $compra->fecha,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'monto_total' => $compra->total,
                    'monto_pagado' => $montoPagadoInicial,
                    'moneda' => $compra->tipo_moneda,
                    'tipo_cambio' => $compra->tipo_cambio,
                    'estado' => $estadoInicial,
                    'dias_credito' => $compra->condicion_pago,
                ]);
            } catch (\Exception $e) {
                Log::error('Error al crear cuenta por pagar', [
                    'compra_id' => $compra->id,
                    'error' => $e->getMessage()
                ]);
            }
            // 4. Registrar movimiento de caja si aplica
            if ($compra->forma_pago === 'contado' && $compra->estado === 'completado') {
                $this->registrarMovimientoCaja($compra);
            }
            
            // 5. Calcular prorrateo de gastos de importación
            $this->aplicarProrrateo($compra->fresh(['detalles.producto', 'detalles.variante']));

            // Si esta compra viene de "Recibir mercadería" de un Pedido a Proveedor,
            // cerrar el ciclo marcándolo como recibido.
            if ($compra->pedido_id) {
                \App\Models\Pedido::where('id', $compra->pedido_id)->update(['estado' => 'recibido']);
            }

            // 6. Registrar en log para auditoría
            Log::info('Compra registrada', [
                'compra_id' => $compra->id,
                'user_id' => $compra->user_id,
                'total' => $compra->total,
                'productos' => count($detalles)
            ]);

            return $compra->fresh([
                'detalles.producto',
                'proveedor',
                'almacen',
                'usuario',
            ]);
        });
    }

    /**
     * Distribuir los gastos de importación proporcionalmente entre las líneas
     * de detalle y calcular el costo unitario final en soles.
     * Se puede llamar desde el controller para recalcular compras existentes.
     */
    public function aplicarProrrateo(Compra $compra): void
    {
        $tc = (float)($compra->tipo_cambio ?? 1);
        if ($tc <= 0) $tc = 1;

        // Total gastos de importación convertidos a PEN
        $gastosPen = 0;
        if ($compra->tipo_compra === 'importacion') {
            $gastosPen =
                (((float)($compra->flete_usd    ?? 0))
                + ((float)($compra->seguro_usd   ?? 0))
                + ((float)($compra->otros_usd    ?? 0))
                + ((float)($compra->impuestos_usd ?? 0))) * $tc
                + (float)($compra->transporte_local_pen ?? 0)
                + (float)($compra->impuestos_pen        ?? 0)
                + (float)($compra->percepcion_pen       ?? 0);
        }

        // Valor total de productos en PEN (base del prorrateo)
        $esUsd = $compra->tipo_moneda === 'USD';
        $totalSubtotalPen = $compra->detalles->sum(
            fn($d) => (float)$d->subtotal * ($esUsd ? $tc : 1)
        );

        if ($totalSubtotalPen <= 0) return;

        foreach ($compra->detalles as $detalle) {
            $subtotalPen  = (float)$detalle->subtotal * ($esUsd ? $tc : 1);
            $proporcion   = $subtotalPen / $totalSubtotalPen;
            $gastoAsignado = $proporcion * $gastosPen;
            $cantidad      = max(1, (int)$detalle->cantidad);

            $costoProrrateadoUnitario = $gastoAsignado / $cantidad;
            $precioUnitarioPen        = (float)$detalle->precio_unitario * ($esUsd ? $tc : 1);
            $costoFinalPen            = $precioUnitarioPen + $costoProrrateadoUnitario;

            $detalle->update([
                'costo_prorateado_pen'    => round($costoProrrateadoUnitario, 4),
                'costo_unitario_final_pen' => round($costoFinalPen, 4),
            ]);

            // Actualizar costo del producto/variante con el costo real en PEN
            if ($detalle->producto) {
                $this->actualizarPrecioProducto($detalle->producto, $costoFinalPen);
            }
            if ($detalle->variante) {
                $this->actualizarCostoVariante($detalle->variante, $costoFinalPen);
            }
        }
    }
    
    /**
     * Validar detalles antes de procesar.
     * El mismo producto base puede aparecer varias veces si tiene diferente variante.
     */
    private function validarDetalles(array $detalles): void
    {
        $combinacionesVistas = [];

        foreach ($detalles as $detalle) {
            $producto = Producto::find($detalle['producto_id']);
            if (!$producto || $producto->estado !== 'activo') {
                throw new \Exception("El producto ID {$detalle['producto_id']} no está activo");
            }

            // Clave única: producto + variante (o modelo + color para retrocompatibilidad)
            $clave = implode('-', [
                $detalle['producto_id'],
                $detalle['variante_id'] ?? ($detalle['modelo_id'] ?? 'null') . '-' . ($detalle['color_id'] ?? 'null'),
            ]);

            if (in_array($clave, $combinacionesVistas)) {
                throw new \Exception("El producto \"{$producto->nombre}\" con la misma variante está duplicado en el detalle");
            }
            $combinacionesVistas[] = $clave;

            if ($producto->tipo_inventario === 'serie' && !empty($detalle['imeis'])) {
                $this->validarIMEIsUnicos($detalle['imeis']);
            }
        }
    }
    
    /**
     * Validar que los IMEIs no existan ya en el sistema
     */
    private function validarIMEIsUnicos(array $imeis): void
    {
         // Extraer todos los códigos IMEI
        $codigos = array_column($imeis, 'codigo_imei');
        
        // Buscar existentes en una sola consulta
        $existentes = Imei::whereIn('codigo_imei', $codigos)
            ->get(['codigo_imei', 'producto_id', 'estado_imei']);

        if ($existentes->isNotEmpty()) {
            $mensaje = "Los siguientes IMEI ya están registrados:\n";
            foreach ($existentes as $imei) {
                $producto = Producto::find($imei->producto_id);
                $mensaje .= "- {$imei->codigo_imei} (Producto: {$producto->nombre}, Estado: {$imei->estado_imei})\n";
            }
            throw new \Exception($mensaje);
        }
    }
    
    /**
     * Actualizar stock del producto y la variante (si existe) en el almacén
     */
    private function actualizarStock(Producto $producto, Compra $compra, array $detalle, ?ProductoVariante $variante = null): void
    {
        $stock = StockAlmacen::firstOrCreate(
            [
                'producto_id' => $producto->id,
                'almacen_id'  => $compra->almacen_id,
            ],
            ['cantidad' => 0]
        );

        $stockAnterior = $stock->cantidad;
        $stock->increment('cantidad', $detalle['cantidad']);

        // Si hay variante, actualizar su stock también
        if ($variante) {
            $variante->increment('stock_actual', $detalle['cantidad']);
        }

        // Sincronizar stock_actual del producto base (suma de todos los almacenes)
        $totalStock = StockAlmacen::where('producto_id', $producto->id)->sum('cantidad');
        $producto->update(['stock_actual' => $totalStock]);

        // Registrar movimiento de inventario
        MovimientoInventario::create([
            'producto_id'          => $producto->id,
            'variante_id'          => $variante?->id,
            'almacen_id'           => $compra->almacen_id,
            'user_id'              => $compra->user_id,
            'tipo_movimiento'      => 'ingreso',
            'cantidad'             => $detalle['cantidad'],
            'stock_anterior'       => $stockAnterior,
            'stock_nuevo'          => $stock->cantidad,
            'numero_factura'       => $compra->numero_factura,
            'documento_referencia' => $compra->numero_factura,
            'motivo'               => 'Compra #' . $compra->id,
            'estado'               => 'completado',
        ]);

        if ($stock->cantidad <= $producto->stock_minimo) {
            Log::warning('Producto con stock mínimo', [
                'producto'    => $producto->nombre,
                'stock_actual'=> $stock->cantidad,
                'stock_minimo'=> $producto->stock_minimo,
            ]);
        }
    }
    
    /**
     * Registrar IMEIs para productos celulares
     */
    private function registrarIMEIs(array $detalle, Producto $producto, Compra $compra, ?ProductoVariante $variante = null, ?\App\Models\DetalleCompra $detalleCompra = null): void
    {
        if (!isset($detalle['imeis']) || !is_array($detalle['imeis'])) {
            return;
        }

        foreach ($detalle['imeis'] as $imeiData) {
            Imei::create([
                'codigo_imei'       => $imeiData['codigo_imei'],
                'serie'             => $imeiData['serie'] ?? null,
                'color_id'          => $detalle['color_id'] ?? ($variante?->color_id),
                'producto_id'       => $producto->id,
                'variante_id'       => $variante?->id,
                'modelo_id'         => $detalle['modelo_id'] ?? null,
                'almacen_id'        => $compra->almacen_id,
                'compra_id'         => $compra->id,
                'detalle_compra_id' => $detalleCompra?->id,
                'estado_imei'       => 'en_stock',
            ]);
        }
    }
    
    /**
     * Actualizar precio de compra del producto
     */
    private function actualizarPrecioProducto(Producto $producto, float $precio): void
    {
        // Guardar historial de precios (opcional)
        // PrecioHistorico::create([...]);
        
        $producto->update([
            'ultimo_costo_compra' => $precio,
            'costo_promedio'      => $precio,
            'fecha_ultima_compra' => now(),
        ]);
    }
    
    /**
     * Actualizar Costo Promedio Ponderado de la variante
     * CPP = SUM(qty * precio) / SUM(qty) sobre todo el historial de compras
     */
    private function actualizarCostoVariante(ProductoVariante $variante, float $ultimoPrecio): void
    {
        $cpp = DetalleCompra::where('variante_id', $variante->id)
            ->selectRaw('SUM(cantidad * precio_unitario) / NULLIF(SUM(cantidad), 0) AS cpp')
            ->value('cpp');

        $variante->update([
            'ultimo_costo_compra' => $ultimoPrecio,
            'costo_promedio'      => $cpp ? round((float) $cpp, 2) : $ultimoPrecio,
        ]);
    }

    /**
     * Actualizar código de barras del producto
     */
    private function actualizarCodigoBarras(Producto $producto, string $codigoBarras): void
    {
        if (empty($producto->codigo_barras)) {
            $producto->update(['codigo_barras' => $codigoBarras]);
        }
    }
    
    /**
     * Registrar movimiento en caja (si la compra es al contado)
     */
    private function registrarMovimientoCaja(Compra $compra): void
    {
        // Verificar si hay una caja abierta
        $cajaService = app(CajaService::class);
        $cajaAbierta = \App\Models\Caja::where('user_id', $compra->user_id)
            ->where('estado', 'abierta')
            ->first();
            
        if ($cajaAbierta) {
            $cajaService->registrarMovimiento(
                $cajaAbierta->id,
                'egreso',
                $compra->total,
                'Compra #' . $compra->id . ' - ' . $compra->proveedor->nombre,
                null, // venta_id
                $compra->id // compra_id
            );
        }
    }
    
    /**
     * Anular una compra (revertir stock y movimientos)
     */
    public function anularCompra(Compra $compra, ?string $motivo = null): void
    {
        DB::transaction(function () use ($compra, $motivo) {

            if ($compra->estado === 'anulado') {
                throw new \Exception('La compra ya está anulada.');
            }

            // ── Guardia 1: IMEIs ya vendidos ────────────────────────────────
            $imeisVendidos = Imei::where('compra_id', $compra->id)
                ->where('estado_imei', Imei::ESTADO_VENDIDO)
                ->count();

            if ($imeisVendidos > 0) {
                throw new \Exception(
                    "No se puede anular esta compra: {$imeisVendidos} IMEI(s) ya fueron vendidos. " .
                    "Gestione las devoluciones correspondientes antes de anular."
                );
            }

            // ── Guardia 2: productos por cantidad ya consumidos parcialmente ─
            foreach ($compra->detalles as $detalle) {
                if ($detalle->producto->tipo_inventario !== 'serie') {
                    $stockDisponible = StockAlmacen::where([
                        'producto_id' => $detalle->producto_id,
                        'almacen_id'  => $compra->almacen_id,
                    ])->value('cantidad') ?? 0;

                    if ($stockDisponible < $detalle->cantidad) {
                        throw new \Exception(
                            "No se puede anular: «{$detalle->producto->nombre}» tiene {$stockDisponible} unidad(es) " .
                            "disponibles pero la compra registró {$detalle->cantidad}. " .
                            "Parte del stock ya fue vendida o trasladada."
                        );
                    }
                }
            }

            $motivoTexto = $motivo ? 'Anulación: ' . $motivo : 'Anulación de compra';

            // ── Revertir stock de cada detalle ───────────────────────────────
            foreach ($compra->detalles as $detalle) {
                // Productos por cantidad
                if ($detalle->producto->tipo_inventario !== 'serie') {
                    $stock = StockAlmacen::where([
                        'producto_id' => $detalle->producto_id,
                        'almacen_id'  => $compra->almacen_id,
                    ])->first();

                    if ($stock) {
                        $stockAnterior = $stock->cantidad;
                        $stock->decrement('cantidad', $detalle->cantidad);

                        MovimientoInventario::create([
                            'producto_id'          => $detalle->producto_id,
                            'almacen_id'           => $compra->almacen_id,
                            'user_id'              => auth()->id(),
                            'tipo_movimiento'      => 'salida',
                            'cantidad'             => $detalle->cantidad,
                            'stock_anterior'       => $stockAnterior,
                            'stock_nuevo'          => $stock->cantidad,
                            'numero_factura'       => $compra->numero_factura,
                            'documento_referencia' => 'ANUL-' . $compra->id,
                            'motivo'               => $motivoTexto,
                            'estado'               => 'completado',
                        ]);

                        // Sincronizar stock: variante primero (sincroniza producto), o directo si no hay variante
                        if ($detalle->variante_id && $detalle->variante) {
                            $detalle->variante->decrementarStock($detalle->cantidad);
                        } else {
                            $totalStock = StockAlmacen::where('producto_id', $detalle->producto_id)->sum('cantidad');
                            $detalle->producto->update(['stock_actual' => $totalStock]);
                        }
                    }
                } else {
                    // Productos tipo serie: solo marcar los IMEIs NO vendidos como devueltos
                    Imei::where('compra_id', $compra->id)
                        ->where('producto_id', $detalle->producto_id)
                        ->whereNotIn('estado_imei', [Imei::ESTADO_VENDIDO, Imei::ESTADO_REEMPLAZADO])
                        ->update(['estado_imei' => Imei::ESTADO_DEVUELTO]);

                    // Recalcular stock del producto y variante desde conteo real de IMEIs en stock
                    $totalStock = Imei::where('producto_id', $detalle->producto_id)
                        ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                        ->count();
                    $detalle->producto->update(['stock_actual' => $totalStock]);

                    if ($detalle->variante_id && $detalle->variante) {
                        $varianteStock = Imei::where('producto_id', $detalle->producto_id)
                            ->where('variante_id', $detalle->variante_id)
                            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                            ->count();
                        $detalle->variante->update(['stock_actual' => $varianteStock]);
                    }
                }
            }

            $compra->update([
                'estado'           => 'anulado',
                'fecha_anulacion'  => now(),
                'motivo_anulacion' => $motivo,
            ]);

            if ($compra->cuentaPorPagar) {
                $compra->cuentaPorPagar->update(['estado' => 'anulado']);
            }

            Log::info('Compra anulada', ['compra_id' => $compra->id, 'motivo' => $motivo]);
        });
    }

    /**
     * Actualizar datos de cabecera y detalles (cantidades/precios) de una compra registrada.
     *
     * @param  array $datos         Campos de cabecera (proveedor_id, numero_factura, fecha, etc.)
     * @param  array $detalles      Lista de ['id' => detalle_id, 'cantidad' => int, 'precio_unitario' => float]
     */
    public function actualizarCabecera(Compra $compra, array $datos, array $detalles = []): void
    {
        DB::transaction(function () use ($compra, $datos, $detalles) {

            // Al crear la compra con "el precio ya incluye IGV" marcado, el
            // subtotal de CADA detalle se guarda tal cual se ingresó (con IGV
            // incluido), mientras que la cabecera (compra.subtotal) sí queda
            // separada en base+IGV — es decir, sum(detalles.subtotal) puede
            // coincidir con compra.total en vez de con compra.subtotal. Antes
            // de tocar nada, detectamos cuál es el caso de ESTA compra
            // comparando su estado actual, así el recálculo de abajo aplica el
            // mismo criterio con el que se guardó originalmente (si no, se
            // vuelve a sumar el 18% sobre un monto que ya lo tenía incluido).
            $tipoOp    = $compra->tipo_operacion ?? '01';
            $incluyeIgv = array_key_exists('incluye_igv', $datos)
                ? filter_var($datos['incluye_igv'], FILTER_VALIDATE_BOOLEAN)
                : (bool) $compra->incluye_igv;

            $sumaDetalleOriginal = $compra->detalles->sum('subtotal');
            $detalleGuardaConIgv = $tipoOp === '01' && $incluyeIgv
                && abs($sumaDetalleOriginal - (float) $compra->total) < 0.05
                && abs($sumaDetalleOriginal - (float) $compra->subtotal) >= 0.05;

            // ── 1. Actualizar detalles (cantidad / precio) ──────────────────
            foreach ($detalles as $item) {
                $detalle = DetalleCompra::find($item['id']);
                if (!$detalle || $detalle->compra_id !== $compra->id) {
                    continue;
                }

                $cantidadAntigua  = $detalle->cantidad;
                $cantidadNueva    = (int) $item['cantidad'];
                $precioNuevo      = round((float) $item['precio_unitario'], 2);
                $diff             = $cantidadNueva - $cantidadAntigua;

                // Ajustar stock si la cantidad cambió
                if ($diff !== 0) {
                    $stock = StockAlmacen::firstOrCreate(
                        ['producto_id' => $detalle->producto_id, 'almacen_id' => $compra->almacen_id],
                        ['cantidad' => 0]
                    );

                    $stockAnterior = $stock->cantidad;

                    if ($diff > 0) {
                        $stock->increment('cantidad', $diff);
                        $tipoMov = 'ingreso';
                        $motivoMov = "Ajuste de compra #{$compra->id}: cantidad +{$diff}";
                    } else {
                        $stock->decrement('cantidad', abs($diff));
                        $tipoMov = 'salida';
                        $motivoMov = "Ajuste de compra #{$compra->id}: cantidad {$diff}";
                    }

                    // Sincronizar stock_actual del producto
                    $totalStock = StockAlmacen::where('producto_id', $detalle->producto_id)->sum('cantidad');
                    $detalle->producto->update(['stock_actual' => $totalStock]);

                    // Actualizar variante si existe
                    if ($detalle->variante_id) {
                        $detalle->variante?->increment('stock_actual', $diff);
                    }

                    MovimientoInventario::create([
                        'producto_id'          => $detalle->producto_id,
                        'almacen_id'           => $compra->almacen_id,
                        'user_id'              => auth()->id(),
                        'tipo_movimiento'      => $tipoMov,
                        'cantidad'             => abs($diff),
                        'stock_anterior'       => $stockAnterior,
                        'stock_nuevo'          => $stock->cantidad,
                        'numero_factura'       => $compra->numero_factura,
                        'documento_referencia' => 'EDIT-' . $compra->id,
                        'motivo'               => $motivoMov,
                        'estado'               => 'completado',
                    ]);
                }

                // Guardar cambios en el detalle
                $detalle->update([
                    'cantidad'        => $cantidadNueva,
                    'precio_unitario' => $precioNuevo,
                    'subtotal'        => $cantidadNueva * $precioNuevo,
                ]);
            }

            // ── 2. Recalcular totales de la compra ──────────────────────────
            $compra->load('detalles'); // refrescar detalles tras los updates

            $sumaDetalleNueva = $compra->detalles->sum('subtotal');

            if ($tipoOp !== '01' || !$incluyeIgv) {
                $subtotalNuevo = $sumaDetalleNueva;
                $igvNuevo      = 0;
                $totalNuevo    = $sumaDetalleNueva;
            } elseif ($detalleGuardaConIgv) {
                // Los detalles guardan precio CON IGV incluido: se extrae la base.
                $subtotalNuevo = round($sumaDetalleNueva / 1.18, 2);
                $igvNuevo      = round($sumaDetalleNueva - $subtotalNuevo, 2);
                $totalNuevo    = $sumaDetalleNueva;
            } else {
                // Los detalles guardan precio SIN IGV: se agrega el 18% encima.
                $subtotalNuevo = $sumaDetalleNueva;
                $igvNuevo      = round($sumaDetalleNueva * 0.18, 2);
                $totalNuevo    = round($subtotalNuevo + $igvNuevo, 2);
            }

            // ── 3. Actualizar cabecera ──────────────────────────────────────
            $camposCabecera = array_merge(
                array_intersect_key($datos, array_flip([
                    'proveedor_id', 'numero_factura', 'almacen_id',
                    'fecha', 'forma_pago', 'condicion_pago', 'observaciones',
                ])),
                [
                    'incluye_igv' => $incluyeIgv,
                    'subtotal'    => $subtotalNuevo,
                    'igv'         => $igvNuevo,
                    'total'       => $totalNuevo,
                ]
            );

            $compra->update($camposCabecera);

            // ── 4. Sincronizar CuentaPorPagar ───────────────────────────────
            if ($compra->cuentaPorPagar) {
                $syncCuenta = [];

                if (isset($datos['proveedor_id']))   $syncCuenta['proveedor_id']  = $datos['proveedor_id'];
                if (isset($datos['numero_factura'])) $syncCuenta['numero_factura'] = $datos['numero_factura'];
                if (isset($datos['fecha']))          $syncCuenta['fecha_emision']  = $datos['fecha'];

                // Si el total cambió, ajustar monto_total (sin tocar monto_pagado)
                if ($totalNuevo != $compra->cuentaPorPagar->monto_total) {
                    $syncCuenta['monto_total'] = $totalNuevo;
                    // Recalcular estado según lo que se ha pagado
                    $saldo = $totalNuevo - $compra->cuentaPorPagar->monto_pagado;
                    if ($saldo <= 0) {
                        $syncCuenta['estado'] = 'pagado';
                    } elseif ($compra->cuentaPorPagar->monto_pagado > 0) {
                        $syncCuenta['estado'] = 'parcial';
                    }
                }

                if (!empty($syncCuenta)) {
                    $compra->cuentaPorPagar->update($syncCuenta);
                }
            }

            Log::info('Compra actualizada (cabecera + detalles)', ['compra_id' => $compra->id]);
        });
    }

    public function eliminarDetalle(Compra $compra, DetalleCompra $detalle): void
    {
        DB::transaction(function () use ($compra, $detalle) {
            // Verificar que no haya IMEIs vendidos
            if ($detalle->producto->tipo_inventario === 'serie') {
                $vendidos = Imei::where('detalle_compra_id', $detalle->id)
                    ->where('estado_imei', Imei::ESTADO_VENDIDO)
                    ->count();
                if ($vendidos > 0) {
                    throw new \Exception("No se puede eliminar: {$vendidos} IMEI(s) de este producto ya fueron vendidos.");
                }
                Imei::where('detalle_compra_id', $detalle->id)
                    ->whereIn('estado_imei', [Imei::ESTADO_EN_STOCK, Imei::ESTADO_EN_TRANSITO, Imei::ESTADO_RESERVADO])
                    ->delete();
            }

            // Revertir stock en almacén
            $stock = StockAlmacen::where('producto_id', $detalle->producto_id)
                ->where('almacen_id', $compra->almacen_id)
                ->first();
            if ($stock) {
                $stockAnterior = $stock->cantidad;
                $stock->decrement('cantidad', $detalle->cantidad);

                $totalStock = StockAlmacen::where('producto_id', $detalle->producto_id)->sum('cantidad');
                $detalle->producto->update(['stock_actual' => $totalStock]);

                if ($detalle->variante_id) {
                    $detalle->variante?->decrement('stock_actual', $detalle->cantidad);
                }

                MovimientoInventario::create([
                    'producto_id'          => $detalle->producto_id,
                    'almacen_id'           => $compra->almacen_id,
                    'user_id'              => auth()->id(),
                    'tipo_movimiento'      => 'salida',
                    'cantidad'             => $detalle->cantidad,
                    'stock_anterior'       => $stockAnterior,
                    'stock_nuevo'          => $stock->cantidad,
                    'numero_factura'       => $compra->numero_factura,
                    'documento_referencia' => 'DEL-DET-' . $detalle->id,
                    'motivo'               => "Eliminación de producto en compra #{$compra->id}",
                    'estado'               => 'completado',
                ]);
            }

            $detalle->delete();

            // Recalcular totales de la compra
            $compra->load('detalles');
            $tipoOp  = $compra->tipo_operacion ?? '01';
            $subtotal = $compra->detalles->sum('subtotal');
            $igv      = $tipoOp === '01' ? round($subtotal * 0.18, 2) : 0;
            $total    = round($subtotal + $igv, 2);
            $compra->update(['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total]);

            if ($compra->cuentaPorPagar) {
                $saldo = $total - $compra->cuentaPorPagar->monto_pagado;
                $estadoCuenta = $saldo <= 0 ? 'pagado' : ($compra->cuentaPorPagar->monto_pagado > 0 ? 'parcial' : $compra->cuentaPorPagar->estado);
                $compra->cuentaPorPagar->update(['monto_total' => $total, 'estado' => $estadoCuenta]);
            }

            Log::info('Detalle de compra eliminado', ['compra_id' => $compra->id, 'detalle_id' => $detalle->id]);
        });
    }

    public function eliminarImei(Compra $compra, Imei $imei): void
    {
        DB::transaction(function () use ($compra, $imei) {
            if ($imei->estado_imei === Imei::ESTADO_VENDIDO) {
                throw new \Exception('No se puede eliminar un IMEI ya vendido.');
            }

            $detalle = $imei->detalle_compra_id
                ? DetalleCompra::find($imei->detalle_compra_id)
                : null;

            // Revertir stock
            $stock = StockAlmacen::where('producto_id', $imei->producto_id)
                ->where('almacen_id', $compra->almacen_id)
                ->first();
            if ($stock && $stock->cantidad > 0) {
                $stockAnterior = $stock->cantidad;
                $stock->decrement('cantidad', 1);

                $totalStock = StockAlmacen::where('producto_id', $imei->producto_id)->sum('cantidad');
                $imei->producto->update(['stock_actual' => $totalStock]);

                if ($imei->variante_id) {
                    $imei->variante?->decrement('stock_actual', 1);
                }

                MovimientoInventario::create([
                    'producto_id'          => $imei->producto_id,
                    'almacen_id'           => $compra->almacen_id,
                    'user_id'              => auth()->id(),
                    'tipo_movimiento'      => 'salida',
                    'cantidad'             => 1,
                    'stock_anterior'       => $stockAnterior,
                    'stock_nuevo'          => $stock->cantidad,
                    'numero_factura'       => $compra->numero_factura,
                    'documento_referencia' => 'DEL-IMEI-' . $imei->id,
                    'motivo'               => "Eliminación de IMEI {$imei->codigo_imei} en compra #{$compra->id}",
                    'estado'               => 'completado',
                ]);
            }

            // Actualizar cantidad del detalle si existe
            if ($detalle) {
                $detalle->decrement('cantidad', 1);
                $detalle->update(['subtotal' => $detalle->cantidad * $detalle->precio_unitario]);
            }

            $imei->delete();

            // Recalcular totales de la compra
            $compra->load('detalles');
            $tipoOp  = $compra->tipo_operacion ?? '01';
            $subtotal = $compra->detalles->sum('subtotal');
            $igv      = $tipoOp === '01' ? round($subtotal * 0.18, 2) : 0;
            $total    = round($subtotal + $igv, 2);
            $compra->update(['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total]);

            if ($compra->cuentaPorPagar) {
                $compra->cuentaPorPagar->update(['monto_total' => $total]);
            }

            Log::info('IMEI eliminado de compra', ['compra_id' => $compra->id, 'imei' => $imei->codigo_imei]);
        });
    }

    /**
     * Eliminar una compra (revierte stock si está registrada, luego elimina)
     */
    public function eliminarCompra(Compra $compra): void
    {
        DB::transaction(function () use ($compra) {

            if ($compra->estado === 'anulado') {
                throw new \Exception('No se puede eliminar una compra ya anulada');
            }

            // Si tiene stock registrado, revertirlo antes de eliminar
            if ($compra->estado === 'registrado') {
                $this->anularCompra($compra, 'Eliminación de compra');
            }

            // Eliminar cuenta por pagar
            if ($compra->cuentaPorPagar) {
                $compra->cuentaPorPagar->delete();
            }

            // Eliminar detalles e IMEIs
            $compra->detalles()->delete();
            Imei::where('compra_id', $compra->id)->delete();

            // Eliminar la compra
            $compra->delete();

            Log::info('Compra eliminada', ['compra_id' => $compra->id]);
        });
    }
    
    /**
     * Obtener estadísticas de compras
     */
    public function getEstadisticas(array $filtros = []): array
    {
        $query = Compra::query();
        
        if (isset($filtros['proveedor_id'])) {
            $query->where('proveedor_id', $filtros['proveedor_id']);
        }
        
        if (isset($filtros['fecha_inicio'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_inicio']);
        }
        
        if (isset($filtros['fecha_fin'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_fin']);
        }
        
        return [
            'total_compras' => $query->count(),
            'monto_total' => $query->sum('total'),
            'promedio_compra' => $query->avg('total'),
            'por_proveedor' => $query->selectRaw('proveedor_id, count(*) as total, sum(total) as monto')
                ->groupBy('proveedor_id')
                ->with('proveedor')
                ->get(),
            'por_mes' => $query->selectRaw('DATE_FORMAT(fecha, "%Y-%m") as mes, count(*) as total, sum(total) as monto')
                ->groupBy('mes')
                ->orderBy('mes', 'desc')
                ->get(),
        ];
    }
        /**
     * Busca o crea la variante de producto correcta para un combo (modelo, color).
     *
     * Si el producto base YA tiene esos modelo/color, lo devuelve tal cual.
     * Si existe un producto con esa combinación, lo reutiliza.
     * Si no existe, crea un nuevo producto variante heredando datos del base.
     */
    private function resolverVarianteProducto(Producto $productoBase, ?int $modeloId, ?int $colorId): Producto
    {
        // Sin variación: devolver el mismo producto
        if (!$modeloId && !$colorId) {
            return $productoBase;
        }

        // El producto base ya tiene exactamente esta combinación
        if ($productoBase->modelo_id == $modeloId && $productoBase->color_id == $colorId) {
            return $productoBase;
        }

        // Buscar variante existente con la misma categoría, tipo y combo modelo+color
        $query = Producto::where('categoria_id', $productoBase->categoria_id)
                         ->where('tipo_inventario', $productoBase->tipo_inventario)
                         ->where('estado', 'activo');

        $query->when($modeloId, fn($q) => $q->where('modelo_id', $modeloId),
                                fn($q) => $q->whereNull('modelo_id'));

        $query->when($colorId, fn($q) => $q->where('color_id', $colorId),
                               fn($q) => $q->whereNull('color_id'));

        $variante = $query->first();
        if ($variante) {
            return $variante;
        }

        // No existe → crear nueva variante
        $modelo = $modeloId ? Modelo::find($modeloId) : null;
        $color  = $colorId  ? Color::find($colorId)   : null;

        $partes = array_filter([$modelo?->nombre, $color?->nombre]);
        $sufijo = implode(' - ', $partes);
        $nombreVariante = $productoBase->nombre . ($sufijo ? ' — ' . $sufijo : '');

        $nuevaVariante = Producto::create([
            'codigo'           => Producto::generarCodigo(),
            'nombre'           => $nombreVariante,
            'descripcion'      => $productoBase->descripcion,
            'categoria_id'     => $productoBase->categoria_id,
            'marca_id'         => $modelo?->marca_id ?? $productoBase->marca_id,
            'modelo_id'        => $modeloId,
            'color_id'         => $colorId,
            'unidad_medida_id' => $productoBase->unidad_medida_id,
            'tipo_inventario'  => $productoBase->tipo_inventario,
            'dias_garantia'    => $productoBase->dias_garantia,
            'tipo_garantia'    => $productoBase->tipo_garantia,
            'stock_actual'     => 0,
            'stock_minimo'     => $productoBase->stock_minimo ?? 0,
            'stock_maximo'     => $productoBase->stock_maximo ?? 0,
            'estado'           => 'activo',
            'creado_por'       => auth()->id(),
        ]);

        Log::info('Variante de producto creada automáticamente', [
            'base_id'   => $productoBase->id,
            'nuevo_id'  => $nuevaVariante->id,
            'nombre'    => $nombreVariante,
            'modelo_id' => $modeloId,
            'color_id'  => $colorId,
        ]);

        return $nuevaVariante;
    }

    /**
     * Procesar IMEI desde archivo Excel/CSV
     */
public function procesarArchivoIMEI($archivo, int $productoId, int $cantidadEsperada): array
{
    $imeis = [];
    $errores = [];
    $linea = 1;
    
    try {
        // Abrir archivo
        $handle = fopen($archivo->getRealPath(), 'r');
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $linea++;
            
            // Saltar encabezados si existen
            if ($linea == 2 && preg_match('/imei|código|serial/i', $data[0])) {
                continue;
            }
            
            $codigoImei = trim($data[0] ?? '');
            $serie = trim($data[1] ?? '');
            
            // Validar formato
            if (empty($codigoImei)) {
                $errores[] = "Línea {$linea}: IMEI vacío";
                continue;
            }
            
            if (!preg_match('/^\d{15}$/', $codigoImei)) {
                $errores[] = "Línea {$linea}: IMEI '{$codigoImei}' no tiene 15 dígitos";
                continue;
            }
            
            $imeis[] = [
                'codigo_imei' => $codigoImei,
                'serie' => $serie ?: null,
            ];
        }
        
        fclose($handle);
        
        // Validar cantidad
        if (count($imeis) != $cantidadEsperada) {
            throw new \Exception("El archivo debe contener exactamente {$cantidadEsperada} IMEI(s). Se encontraron " . count($imeis));
        }
        
        // Validar duplicados internos
        $codigos = array_column($imeis, 'codigo_imei');
        if (count($codigos) !== count(array_unique($codigos))) {
            $duplicados = array_diff_assoc($codigos, array_unique($codigos));
            throw new \Exception("Hay IMEI duplicados en el archivo: " . implode(', ', array_unique($duplicados)));
        }
        
        // Validar contra base de datos
        $existentes = Imei::whereIn('codigo_imei', $codigos)->pluck('codigo_imei')->toArray();
        if (!empty($existentes)) {
            throw new \Exception("Los siguientes IMEI ya existen: " . implode(', ', $existentes));
        }
        
    } catch (\Exception $e) {
        throw new \Exception("Error procesando archivo: " . $e->getMessage());
    }
    
    return [
        'success' => empty($errores),
        'imeis' => $imeis,
        'errores' => $errores
    ];
}
}