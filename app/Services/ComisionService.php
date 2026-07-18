<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\ComisionRegla;
use App\Models\ComisionDetalleVenta;

class ComisionService
{
    /**
     * Calcula y persiste comisiones para todos los detalles de una venta.
     * Prioridad de regla: producto+vendedor > producto > categoría > usuario.
     */
    public function calcularParaVenta(Venta $venta): void
    {
        $vendedorId = $venta->user_id;
        if (!$vendedorId) {
            return;
        }

        foreach ($venta->detalles as $detalle) {
            $producto    = $detalle->producto;
            $productoId  = $detalle->producto_id;
            $categoriaId = $producto?->categoria_id;

            $regla = $this->resolverRegla($vendedorId, $productoId, $categoriaId);
            if (!$regla) {
                continue;
            }

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
