<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\MovimientoInventario;
use App\Models\SerieComprobante;
use App\Models\StockAlmacen;
use App\Models\Imei;
use App\Models\TrasladoImei;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class TrasladoService
{
    /**
     * Crear un traslado con múltiples productos.
     *
     * Para productos serie (IMEI): marca IMEIs como en_transito al crear.
     * Para accesorios: registra la intención; el stock se mueve al confirmar.
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
                $productoId = $linea['producto_id'];
                $producto   = Producto::findOrFail($productoId);
                $esSerie    = $producto->tipo_inventario === 'serie';

                if ($esSerie) {
                    $this->crearLineaImei($linea, $producto, $almacenOrigenId, $almacenDestinoId, $userId, $numeroGuia, $datos);
                } else {
                    $this->crearLineaAccesorio($linea, $producto, $almacenOrigenId, $almacenDestinoId, $userId, $numeroGuia, $datos);
                }
            }

            return $numeroGuia;
        });
    }

    // ── Línea serie (IMEI) ──────────────────────────────────────────────────

    private function crearLineaImei(array $linea, Producto $producto, int $origenId, int $destinoId, int $userId, string $guia, array $cabecera): void
    {
        $imeiIds = array_values(array_unique((array) ($linea['imei_ids'] ?? [])));
        $cantidad = count($imeiIds);

        if ($cantidad === 0) {
            throw new \Exception("Producto «{$producto->nombre}»: debe seleccionar al menos un IMEI.");
        }

        // Validar que todos los IMEIs pertenecen al producto + almacén + en_stock
        $validos = Imei::whereIn('id', $imeiIds)
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $origenId)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->count();

        if ($validos !== $cantidad) {
            throw new \Exception(
                "Producto «{$producto->nombre}»: uno o más IMEIs no están disponibles en el almacén origen o ya están en tránsito."
            );
        }

        // Verificar que ningún IMEI esté en otro traslado pendiente (doble seguridad)
        $enOtroTraslado = TrasladoImei::whereIn('imei_id', $imeiIds)
            ->whereHas('movimiento', fn($q) => $q->where('estado', 'pendiente'))
            ->exists();

        if ($enOtroTraslado) {
            throw new \Exception("Producto «{$producto->nombre}»: uno o más IMEIs ya están asignados a otro traslado pendiente.");
        }

        $stockAnterior = Imei::where('producto_id', $producto->id)
            ->where('almacen_id', $origenId)
            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
            ->count();

        $movimiento = MovimientoInventario::create([
            'producto_id'        => $producto->id,
            'variante_id'        => $linea['variante_id'] ?? null,
            'almacen_id'         => $origenId,
            'almacen_destino_id' => $destinoId,
            'user_id'            => $userId,
            'tipo_movimiento'    => 'transferencia',
            'cantidad'           => $cantidad,
            'stock_anterior'     => $stockAnterior,
            'stock_nuevo'        => $stockAnterior - $cantidad,
            'numero_guia'        => $guia,
            'fecha_traslado'     => now()->toDateString(),
            'observaciones'      => $cabecera['observaciones'] ?? null,
            'estado'             => 'pendiente',
        ]);

        // Registrar IMEIs del traslado y marcarlos en_transito
        foreach ($imeiIds as $imeiId) {
            TrasladoImei::create([
                'movimiento_id' => $movimiento->id,
                'imei_id'       => $imeiId,
            ]);
        }

        Imei::whereIn('id', $imeiIds)
            ->update(['estado_imei' => Imei::ESTADO_EN_TRANSITO]);
    }

    // ── Línea accesorio (cantidad) ──────────────────────────────────────────

    private function crearLineaAccesorio(array $linea, Producto $producto, int $origenId, int $destinoId, int $userId, string $guia, array $cabecera): void
    {
        $cantidad = (int) ($linea['cantidad'] ?? 0);

        if ($cantidad < 1) {
            throw new \Exception("Producto «{$producto->nombre}»: la cantidad debe ser al menos 1.");
        }

        $stockOrigen = StockAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $origenId)
            ->first();

        if (!$stockOrigen || $stockOrigen->cantidad < $cantidad) {
            throw new \Exception(
                "Producto «{$producto->nombre}»: stock insuficiente en almacén origen (disponible: " . ($stockOrigen->cantidad ?? 0) . ")."
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

        // Sincronizar stock_actual del producto con la suma real de almacenes
        $totalStock = StockAlmacen::where('producto_id', $producto->id)->sum('cantidad');
        $producto->update(['stock_actual' => $totalStock]);
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
                ? MovimientoInventario::with(['producto', 'imeisTrasladados'])
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->where('estado', 'pendiente')
                    ->get()
                : collect([$representante->load('imeisTrasladados')]);

            if ($grupo->isEmpty()) {
                throw new \Exception('No se encontraron movimientos pendientes para este traslado.');
            }

            $confirmadoEn = now();

            foreach ($grupo as $movimiento) {
                $esSerie = $movimiento->producto->tipo_inventario === 'serie';

                if ($esSerie) {
                    // Mover IMEIs al almacén destino y devolverles en_stock
                    $imeiIds = $movimiento->imeisTrasladados->pluck('imei_id')->toArray();

                    if (empty($imeiIds)) {
                        throw new \Exception(
                            "Producto «{$movimiento->producto->nombre}»: no tiene IMEIs asignados. Contacte al administrador."
                        );
                    }

                    Imei::whereIn('id', $imeiIds)->update([
                        'almacen_id'  => $movimiento->almacen_destino_id,
                        'estado_imei' => Imei::ESTADO_EN_STOCK,
                    ]);

                    // Recalcular stock_actual desde conteo real de IMEIs en_stock
                    $totalStock = Imei::where('producto_id', $movimiento->producto_id)
                        ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                        ->count();
                    $movimiento->producto->update(['stock_actual' => $totalStock]);

                } else {
                    // Acreditar stock en destino (ya fue descontado en origen al crear)
                    $stockDestino = StockAlmacen::firstOrCreate(
                        [
                            'producto_id' => $movimiento->producto_id,
                            'almacen_id'  => $movimiento->almacen_destino_id,
                        ],
                        ['cantidad' => 0]
                    );
                    $stockDestino->increment('cantidad', $movimiento->cantidad);

                    // Sincronizar stock_actual con la suma real de todos los almacenes
                    $totalStock = StockAlmacen::where('producto_id', $movimiento->producto_id)->sum('cantidad');
                    $movimiento->producto->update(['stock_actual' => $totalStock]);
                }

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
                ? MovimientoInventario::with(['producto', 'imeisTrasladados'])
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->where('estado', 'pendiente')
                    ->get()
                : collect([$representante->load('imeisTrasladados')]);

            if ($grupo->isEmpty()) {
                throw new \Exception('No se encontraron movimientos pendientes para este traslado.');
            }

            $observacionAnulacion = 'ANULADO' . ($motivo ? ": {$motivo}" : '') . ' — por ' . auth()->user()?->name . ' el ' . now()->format('d/m/Y H:i');

            foreach ($grupo as $movimiento) {
                $esSerie = $movimiento->producto->tipo_inventario === 'serie';

                if ($esSerie) {
                    $imeiIds = $movimiento->imeisTrasladados->pluck('imei_id')->toArray();
                    if (!empty($imeiIds)) {
                        Imei::whereIn('id', $imeiIds)
                            ->where('estado_imei', Imei::ESTADO_EN_TRANSITO)
                            ->update(['estado_imei' => Imei::ESTADO_EN_STOCK]);
                    }

                    $totalStock = Imei::where('producto_id', $movimiento->producto_id)
                        ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                        ->count();
                    $movimiento->producto->update(['stock_actual' => $totalStock]);
                } else {
                    $stockOrigen = StockAlmacen::firstOrCreate(
                        ['producto_id' => $movimiento->producto_id, 'almacen_id' => $movimiento->almacen_id],
                        ['cantidad' => 0]
                    );
                    $stockOrigen->increment('cantidad', $movimiento->cantidad);

                    $totalStock = StockAlmacen::where('producto_id', $movimiento->producto_id)->sum('cantidad');
                    $movimiento->producto->update(['stock_actual' => $totalStock]);
                }

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
     * - confirmado: revierte trayendo el stock/IMEIs de vuelta desde el
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
                ? MovimientoInventario::with(['producto', 'imeisTrasladados'])
                    ->where('numero_guia', $representante->numero_guia)
                    ->where('tipo_movimiento', 'transferencia')
                    ->noEliminados()
                    ->get()
                : collect([$representante->load('imeisTrasladados')]);

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

                $esSerie = $movimiento->producto->tipo_inventario === 'serie';

                if ($esSerie) {
                    $imeiIds = $movimiento->imeisTrasladados->pluck('imei_id')->toArray();
                    $disponibles = Imei::whereIn('id', $imeiIds)
                        ->where('almacen_id', $movimiento->almacen_destino_id)
                        ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                        ->count();

                    if ($disponibles !== count($imeiIds)) {
                        throw new \Exception(
                            "Producto «{$movimiento->producto->nombre}»: uno o más IMEIs ya no están disponibles en el almacén destino (vendidos o trasladados de nuevo). No se puede eliminar ni devolver el stock."
                        );
                    }
                } else {
                    $stockDestino = StockAlmacen::where('producto_id', $movimiento->producto_id)
                        ->where('almacen_id', $movimiento->almacen_destino_id)
                        ->first();

                    if (!$stockDestino || $stockDestino->cantidad < $movimiento->cantidad) {
                        throw new \Exception(
                            "Producto «{$movimiento->producto->nombre}»: no hay suficiente stock en el almacén destino para devolver (disponible: " . ($stockDestino->cantidad ?? 0) . "). Probablemente ya se vendió."
                        );
                    }
                }
            }

            // 2) Aplicar la reversión ya validada.
            $huboReversion = false;

            foreach ($grupo as $movimiento) {
                $esSerie = $movimiento->producto->tipo_inventario === 'serie';

                if ($movimiento->estado === 'pendiente') {
                    $huboReversion = true;

                    if ($esSerie) {
                        $imeiIds = $movimiento->imeisTrasladados->pluck('imei_id')->toArray();
                        if (!empty($imeiIds)) {
                            Imei::whereIn('id', $imeiIds)
                                ->where('estado_imei', Imei::ESTADO_EN_TRANSITO)
                                ->update(['estado_imei' => Imei::ESTADO_EN_STOCK]);
                        }
                    } else {
                        $stockOrigen = StockAlmacen::firstOrCreate(
                            ['producto_id' => $movimiento->producto_id, 'almacen_id' => $movimiento->almacen_id],
                            ['cantidad' => 0]
                        );
                        $stockOrigen->increment('cantidad', $movimiento->cantidad);
                    }
                } elseif ($movimiento->estado === 'confirmado') {
                    $huboReversion = true;

                    if ($esSerie) {
                        $imeiIds = $movimiento->imeisTrasladados->pluck('imei_id')->toArray();
                        Imei::whereIn('id', $imeiIds)->update([
                            'almacen_id'  => $movimiento->almacen_id,
                            'estado_imei' => Imei::ESTADO_EN_STOCK,
                        ]);
                    } else {
                        StockAlmacen::where('producto_id', $movimiento->producto_id)
                            ->where('almacen_id', $movimiento->almacen_destino_id)
                            ->decrement('cantidad', $movimiento->cantidad);

                        $stockOrigen = StockAlmacen::firstOrCreate(
                            ['producto_id' => $movimiento->producto_id, 'almacen_id' => $movimiento->almacen_id],
                            ['cantidad' => 0]
                        );
                        $stockOrigen->increment('cantidad', $movimiento->cantidad);
                    }
                }
                // estado === 'anulado': el stock ya se devolvió antes, no se toca.

                if ($esSerie) {
                    $totalStock = Imei::where('producto_id', $movimiento->producto_id)
                        ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                        ->count();
                } else {
                    $totalStock = StockAlmacen::where('producto_id', $movimiento->producto_id)->sum('cantidad');
                }
                $movimiento->producto->update(['stock_actual' => $totalStock]);
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
