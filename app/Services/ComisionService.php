<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\ComisionRegla;
use App\Models\ComisionDetalleVenta;
use App\Models\MovimientoInventario;

class ComisionService
{
    /**
     * Calcula y persiste comisiones para todos los detalles de una venta.
     * Prioridad de regla: producto+vendedor > producto > categoría+vendedor > categoría > usuario.
     */
    public function calcularParaVenta(Venta $venta): void
    {
        $vendedorId = $venta->user_id;
        if (!$vendedorId) {
            return;
        }

        foreach ($venta->detalles as $detalle) {
            $productoId  = $detalle->producto_id;
            $categoriaId = $detalle->producto?->categoria_id;

            $regla = $this->resolverRegla($vendedorId, $productoId, $categoriaId);
            if (!$regla) {
                continue;
            }

            $this->crearComisionDetalle($detalle, $vendedorId, $regla);
        }
    }

    /**
     * Genera comisiones para ventas ya pagadas cuyos detalles todavía no tienen
     * comisión (p. ej. porque la regla de producto/categoría se creó después de
     * la venta, o no existía ninguna regla que aplicara en ese momento). No
     * toca detalles que ya tienen una comisión registrada (pendiente o pagada),
     * así que es seguro correrlo varias veces.
     *
     * @return int cantidad de comisiones nuevas generadas
     */
    public function recalcularHistorico(): int
    {
        $creadas = 0;

        DetalleVenta::whereHas('venta', fn($q) => $q->where('estado_pago', 'pagado')->whereNotNull('user_id'))
            ->whereDoesntHave('comision')
            ->with(['producto', 'venta'])
            ->chunkById(200, function ($detalles) use (&$creadas) {
                foreach ($detalles as $detalle) {
                    $vendedorId = $detalle->venta->user_id;
                    if (!$vendedorId) {
                        continue;
                    }

                    $categoriaId = $detalle->producto?->categoria_id;
                    $regla       = $this->resolverRegla($vendedorId, $detalle->producto_id, $categoriaId);
                    if (!$regla) {
                        continue;
                    }

                    $this->crearComisionDetalle($detalle, $vendedorId, $regla);
                    $creadas++;
                }
            });

        return $creadas;
    }

    /**
     * Ajusta la comisión de un detalle de venta a la cantidad que sigue
     * efectivamente vendida (original menos lo devuelto y no anulado).
     * Se recalcula desde cero cada vez a partir de la comisión ya
     * registrada, así que sirve tanto para una devolución nueva (baja el
     * monto) como para "Anular Devolución" (vuelve a subirlo). No toca
     * comisiones ya marcadas "pagado" — ese dinero ya salió, queda para
     * ajuste manual con el vendedor.
     */
    public function recalcularPorDevolucion(DetalleVenta $detalle): void
    {
        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();
        if (!$comision || $comision->estado === 'pagado') {
            return;
        }

        $cantidadOriginal = (int) $detalle->cantidad;
        if ($cantidadOriginal <= 0) {
            return;
        }

        $cantidadDevuelta = (int) MovimientoInventario::where('detalle_venta_id', $detalle->id)
            ->where('tipo_movimiento', 'devolucion')
            ->where('estado', '!=', 'anulado')
            ->sum('cantidad');

        $cantidadEfectiva = max(0, $cantidadOriginal - $cantidadDevuelta);

        if ($cantidadEfectiva <= 0) {
            $comision->delete();
            return;
        }

        $factor = $cantidadEfectiva / $cantidadOriginal;

        if ($comision->tipo_calculo === 'monto_fijo') {
            $nuevoMonto  = round((float) $comision->valor_configurado * $cantidadEfectiva, 2);
            $nuevoMargen = $comision->margen_calculado;
        } elseif (!is_null($comision->margen_calculado)) {
            $nuevoMargen = round((float) $comision->margen_calculado * $factor, 2);
            $nuevoMonto  = round($nuevoMargen * (float) $comision->valor_configurado / 100, 2);
        } else {
            $subtotalEfectivo = round((float) $detalle->subtotal_con_igv * $factor, 2);
            $nuevoMonto  = round($subtotalEfectivo * (float) $comision->valor_configurado / 100, 2);
            $nuevoMargen = null;
        }

        $comision->update([
            'monto_comision'   => $nuevoMonto,
            'margen_calculado' => $nuevoMargen,
        ]);
    }

    private function crearComisionDetalle(DetalleVenta $detalle, int $vendedorId, ComisionRegla $regla): void
    {
        $producto = $detalle->producto;
        $subtotal = (float) $detalle->subtotal_con_igv;
        $qty      = (int)   $detalle->cantidad;

        [$monto, $margen] = match($regla->tipo_calculo) {
            'porcentaje' => [
                round($subtotal * (float) $regla->valor / 100, 2),
                null,
            ],
            'porcentaje_margen' => (function () use ($subtotal, $qty, $producto, $regla) {
                $costo  = (float) ($producto?->costo_promedio ?? 0);
                $margen = $subtotal - ($costo * $qty);
                $margen = max(0, $margen);
                return [round($margen * (float) $regla->valor / 100, 2), round($margen, 2)];
            })(),
            default => [  // monto_fijo
                round((float) $regla->valor * $qty, 2),
                null,
            ],
        };

        ComisionDetalleVenta::create([
            'detalle_venta_id'  => $detalle->id,
            'user_id'           => $vendedorId,
            'regla_id'          => $regla->id,
            'tipo_calculo'      => $regla->tipo_calculo,
            'valor_configurado' => $regla->valor,
            'margen_calculado'  => $margen,
            'monto_comision'    => $monto,
            'estado'            => 'pendiente',
        ]);
    }

    private function resolverRegla(int $vendedorId, int $productoId, ?int $categoriaId): ?ComisionRegla
    {
        // Producto + Vendedor (prioridad máxima: la más específica)
        $regla = ComisionRegla::where('activo', true)
            ->where('tipo_aplicacion', 'producto_usuario')
            ->where('producto_id', $productoId)
            ->where('user_id', $vendedorId)
            ->first();

        if ($regla) return $regla;

        // Producto
        $regla = ComisionRegla::where('activo', true)
            ->where('tipo_aplicacion', 'producto')
            ->where('producto_id', $productoId)
            ->first();

        if ($regla) return $regla;

        // Categoría + Vendedor
        if ($categoriaId) {
            $regla = ComisionRegla::where('activo', true)
                ->where('tipo_aplicacion', 'categoria_usuario')
                ->where('categoria_id', $categoriaId)
                ->where('user_id', $vendedorId)
                ->first();
            if ($regla) return $regla;
        }

        // Categoría
        if ($categoriaId) {
            $regla = ComisionRegla::where('activo', true)
                ->where('tipo_aplicacion', 'categoria')
                ->where('categoria_id', $categoriaId)
                ->first();
            if ($regla) return $regla;
        }

        // Usuario (prioridad mínima)
        return ComisionRegla::where('activo', true)
            ->where('tipo_aplicacion', 'usuario')
            ->where('user_id', $vendedorId)
            ->first();
    }
}
