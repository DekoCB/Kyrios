<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\MovimientoInventario;
use App\Models\SerieComprobante;
use App\Models\StockAlmacen;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class TrasladoService
{
    /**
     * Crear un traslado con múltiples productos.
     *
     * Descuenta el stock del origen al crear (reserva); se acredita al confirmar la recepción.
     *
     * @param array $datos  Incluye: almacen_id, almacen_destino_id, productos[],
     *                      numero_guia?, transportista?, observaciones?, user_id
     * @return string  El numero_guia generado o provisto
     */
    public function crearTraslado(array $datos): string
    {
        return DB::transaction(function () use ($datos) {

            $almacenOrigenId  = $datos['almacen_id'];
            $almacenDestinoId = $datos['almacen_destino_id'];
            $userId           = $datos['user_id'];
            $productos        = $datos['productos'] ?? [];

            if ($almacenOrigenId == $almacenDestinoId) {
                throw new \Exception('Almacén origen y destino no pueden ser iguales.');
            }

            if (empty($productos)) {
                throw new \Exception('Debe incluir al menos un producto en el traslado.');
            }

            $numeroGuia = $this->resolverNumeroGuiaAutomatico(
                $almacenOrigenId,
                $datos['guia_serie_id'] ?? null
            );

            if (MovimientoInventario::where('numero_guia', $numeroGuia)->exists()) {
                throw new \Exception("La guía '{$numeroGuia}' ya existe.");
            }

            $claves = array_map(fn($p) => $p['producto_id'] . '-' . ($p['variante_id'] ?? ''), $productos);
            if (count($claves) !== count(array_unique($claves))) {
                throw new \Exception('Hay productos con la misma variante duplicados en el traslado.');
            }

            foreach ($productos as $linea) {
                $producto = Producto::findOrFail($linea['producto_id']);
                $this->crearLinea($linea, $producto, $almacenOrigenId, $almacenDestinoId, $userId, $numeroGuia, $datos);
            }

            return $numeroGuia;
        });
    }

    // ── Línea (cantidad por variante) ──────────────────────────────────────────

    private function crearLinea(array $linea, Producto $producto, int $origenId, int $destinoId, int $userId, string $guia, array $cabecera): void
    {
        $cantidad = (int) ($linea['cantidad'] ?? 0);

        if ($cantidad < 1) {
            throw new \Exception("Producto «" . \App\Support\EtiquetaProducto::rotulo($producto) . "»: la cantidad debe ser al menos 1.");
        }

        $varianteId = !empty($linea['variante_id']) ? (int) $linea['variante_id'] : null;
        if (!$varianteId && $producto->variantes()->where('estado', 'activo')->exists()) {
            throw new \Exception("Producto «" . \App\Support\EtiquetaProducto::rotulo($producto) . "»: seleccione la variante que va a trasladar.");
        }

        // Bloqueo de la fila: dos traslados simultáneos no pueden usar la misma existencia
        $stockOrigen = StockAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $origenId)
            ->deVariante($varianteId)
            ->lockForUpdate()
            ->first();

        if (!$stockOrigen || $stockOrigen->cantidad < $cantidad) {
            $variante = $varianteId ? \App\Models\ProductoVariante::find($varianteId) : null;
            $nombre = \App\Support\EtiquetaProducto::rotulo($producto) . ($variante ? ' (' . ($variante->codigo_kyrios ?? $variante->sku) . ')' : '');
            throw new \Exception(
                "Producto «{$nombre}»: stock insuficiente en almacén origen (disponible: " . ($stockOrigen->cantidad ?? 0) . ")."
            );
        }

        MovimientoInventario::create([
            'producto_id'        => $producto->id,
            'almacen_id'         => $origenId,
            'almacen_destino_id' => $destinoId,
            'user_id'            => $userId,
            'tipo_movimiento'    => 'transferencia',
            'cantidad'           => $cantidad,
            'variante_id'        => $linea['variante_id'] ?? null,
            'stock_anterior'     => $stockOrigen->cantidad,
            'stock_nuevo'        => $stockOrigen->cantidad - $cantidad,
            'numero_guia'        => $guia,
            'fecha_traslado'     => now()->toDateString(),
            'observaciones'      => $cabecera['observaciones'] ?? null,
            'estado'             => 'pendiente',
        ]);

        // Descontar stock en origen inmediatamente (reserva)
        $stockOrigen->decrement('cantidad', $cantidad);

        // Sincronizar stock_actual del producto y de la variante con la suma real de almacenes
        $this->sincronizarStock($producto->id, $varianteId);
    }

    /** stock_actual del producto y de la variante = suma de sus filas en stock_almacen. */
    private function sincronizarStock(int $productoId, ?int $varianteId): void
    {
        Producto::where('id', $productoId)->update(['stock_actual' => StockAlmacen::where('producto_id', $productoId)->sum('cantidad')]);

        if ($varianteId) {
            \App\Models\ProductoVariante::where('id', $varianteId)
                ->update(['stock_actual' => StockAlmacen::where('variante_id', $varianteId)->sum('cantidad')]);
        }
    }

    // ───────────────────────────────────────────────────────────────────────

    /**
     * Confirmar recepción de un traslado (confirma TODOS los productos del mismo numero_guia).
     */
    public function confirmarRecepcion(int $movimientoId, int $usuarioConfirmaId): void
    {
        DB::transaction(function () use ($movimientoId, $usuarioConfirmaId) {

            $representante = MovimientoInventario::with('producto')->findOrFail($movimientoId);

            if ($representante->estado !== 'pendiente') {
                throw new \Exception('Este traslado ya fue procesado.');
            }

            // Obtener todos los movimientos del mismo grupo (mismo numero_guia)
            $grupo = $representante->numero_guia
                ? MovimientoInventario::with('producto')
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->where('estado', 'pendiente')
                    ->get()
                : collect([$representante]);

            if ($grupo->isEmpty()) {
                throw new \Exception('No se encontraron movimientos pendientes para este traslado.');
            }

            $confirmadoEn = now();

            foreach ($grupo as $movimiento) {
                // Acreditar stock en destino (ya fue descontado en origen al crear)
                $stockDestino = StockAlmacen::fila($movimiento->producto_id, $movimiento->almacen_destino_id, $movimiento->variante_id);
                $stockDestino->increment('cantidad', $movimiento->cantidad);

                // Sincronizar stock_actual con la suma real de todos los almacenes
                $this->sincronizarStock($movimiento->producto_id, $movimiento->variante_id);

                $movimiento->update([
                    'estado'              => 'confirmado',
                    'usuario_confirma_id' => $usuarioConfirmaId,
                    'fecha_confirmacion'  => $confirmadoEn,
                    'fecha_recepcion'     => $confirmadoEn->toDateString(),
                ]);
            }
        });
    }

    // ── Anular traslado ────────────────────────────────────────────────────

    public function anularTraslado(int $movimientoId, int $usuarioId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($movimientoId, $usuarioId, $motivo) {

            $representante = MovimientoInventario::with('producto')->findOrFail($movimientoId);

            if ($representante->estado !== 'pendiente') {
                throw new \Exception('Solo se pueden anular traslados en estado pendiente.');
            }

            $grupo = $representante->numero_guia
                ? MovimientoInventario::with('producto')
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->where('estado', 'pendiente')
                    ->get()
                : collect([$representante]);

            if ($grupo->isEmpty()) {
                throw new \Exception('No se encontraron movimientos pendientes para este traslado.');
            }

            $observacionAnulacion = 'ANULADO' . ($motivo ? ": {$motivo}" : '') . ' — por ' . auth()->user()?->name . ' el ' . now()->format('d/m/Y H:i');

            foreach ($grupo as $movimiento) {
                $stockOrigen = StockAlmacen::fila($movimiento->producto_id, $movimiento->almacen_id, $movimiento->variante_id);
                $stockOrigen->increment('cantidad', $movimiento->cantidad);

                $this->sincronizarStock($movimiento->producto_id, $movimiento->variante_id);

                $obsActual = $movimiento->observaciones;
                $movimiento->update([
                    'estado'        => 'anulado',
                    'observaciones' => $obsActual ? "{$obsActual}\n{$observacionAnulacion}" : $observacionAnulacion,
                ]);
            }

            // Anular la guía de remisión asociada si existe
            if ($representante->numero_guia) {
                \App\Models\GuiaRemision::where('numero_guia', $representante->numero_guia)
                    ->where('estado', '!=', 'anulada')
                    ->update(['estado' => 'anulada']);
            }
        });
    }

    /**
     * Elimina (oculta) un traslado del historial y devuelve el stock a origen,
     * sin importar si estaba pendiente o ya confirmado. No hace un delete()
     * real — MovimientoInventario::boot() lo bloquea a propósito por
     * trazabilidad — solo marca eliminado_en/eliminado_por para que
     * desaparezca de los listados.
     *
     * - pendiente: revierte la reserva en origen (igual que anularTraslado()).
     * - confirmado: revierte trayendo el stock de vuelta desde el
     *   almacén destino hacia el origen. Si alguna unidad ya no está
     *   disponible en destino (se vendió, se trasladó de nuevo, etc.) se
     *   aborta con un error explícito — no se puede devolver lo que ya no
     *   está ahí.
     * - anulado: el stock ya se devolvió cuando se anuló; no se vuelve a
     *   tocar, solo se oculta.
     */
    public function eliminarTraslado(int $movimientoId, int $usuarioId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($movimientoId, $usuarioId, $motivo) {

            $representante = MovimientoInventario::with('producto')->findOrFail($movimientoId);

            $grupo = $representante->numero_guia
                ? MovimientoInventario::with('producto')
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->noEliminados()
                    ->get()
                : collect([$representante]);

            if ($grupo->isEmpty()) {
                throw new \Exception('No se encontraron movimientos para este traslado.');
            }

            // 1) Validar TODO antes de mover nada: si un producto ya no puede
            //    devolverse, se aborta completo en vez de dejar el traslado
            //    revertido a medias.
            foreach ($grupo as $movimiento) {
                if ($movimiento->estado !== 'confirmado') {
                    continue;
                }

                $stockDestino = StockAlmacen::where('producto_id', $movimiento->producto_id)
                    ->where('almacen_id', $movimiento->almacen_destino_id)
                    ->deVariante($movimiento->variante_id)
                    ->first();

                if (!$stockDestino || $stockDestino->cantidad < $movimiento->cantidad) {
                    throw new \Exception(
                        "Producto «" . \App\Support\EtiquetaProducto::rotulo($movimiento->producto) . "»: no hay suficiente stock en el almacén destino para devolver (disponible: " . ($stockDestino->cantidad ?? 0) . "). Probablemente ya se vendió."
                    );
                }
            }

            // 2) Aplicar la reversión ya validada.
            $huboReversion = false;

            foreach ($grupo as $movimiento) {
                if ($movimiento->estado === 'pendiente') {
                    $huboReversion = true;

                    $stockOrigen = StockAlmacen::fila($movimiento->producto_id, $movimiento->almacen_id, $movimiento->variante_id);
                    $stockOrigen->increment('cantidad', $movimiento->cantidad);
                } elseif ($movimiento->estado === 'confirmado') {
                    $huboReversion = true;

                    StockAlmacen::where('producto_id', $movimiento->producto_id)
                        ->where('almacen_id', $movimiento->almacen_destino_id)
                        ->deVariante($movimiento->variante_id)
                        ->decrement('cantidad', $movimiento->cantidad);

                    $stockOrigen = StockAlmacen::fila($movimiento->producto_id, $movimiento->almacen_id, $movimiento->variante_id);
                    $stockOrigen->increment('cantidad', $movimiento->cantidad);
                }
                // estado === 'anulado': el stock ya se devolvió antes, no se toca.

                $this->sincronizarStock($movimiento->producto_id, $movimiento->variante_id);
            }

            if ($huboReversion && $representante->numero_guia) {
                \App\Models\GuiaRemision::where('numero_guia', $representante->numero_guia)
                    ->where('estado', '!=', 'anulada')
                    ->update(['estado' => 'anulada']);
            }

            $observacionEliminacion = 'ELIMINADO DEL HISTORIAL' . ($motivo ? ": {$motivo}" : '')
                . ' — por ' . auth()->user()?->name . ' el ' . now()->format('d/m/Y H:i');

            foreach ($grupo as $movimiento) {
                $obsActual = $movimiento->observaciones;
                $movimiento->update([
                    'estado'             => 'anulado',
                    'eliminado_en'       => now(),
                    'eliminado_por'      => $usuarioId,
                    'motivo_eliminacion' => $motivo,
                    'observaciones'      => $obsActual ? "{$obsActual}\n{$observacionEliminacion}" : $observacionEliminacion,
                ]);
            }
        });
    }

    // ───────────────────────────────────────────────────────────────────────

    private function resolverNumeroGuiaAutomatico(int $almacenId, ?int $serieId = null): string
    {
        if ($serieId) {
            return $this->consumirCorrelativo($serieId);
        }

        $almacen = Almacen::with('sucursal')->find($almacenId);
        if ($almacen?->sucursal) {
            $serie = SerieComprobante::where('sucursal_id', $almacen->sucursal->id)
                ->where('tipo_comprobante', '09')
                ->where('activo', true)
                ->first();
            if ($serie) {
                return $this->consumirCorrelativo($serie->id);
            }
        }

        $ultimo = MovimientoInventario::where('tipo_movimiento', 'transferencia')
            ->where('numero_guia', 'like', 'GR-%')
            ->latest('id')
            ->value('numero_guia');

        $numero = $ultimo ? ((int) substr($ultimo, 3) + 1) : 1;

        return 'GR-' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }

    private function consumirCorrelativo(int $serieId): string
    {
        $serie = SerieComprobante::lockForUpdate()->findOrFail($serieId);
        $numero = $serie->serie . '-' . str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT);
        $serie->increment('correlativo_actual');
        return $numero;
    }
}
