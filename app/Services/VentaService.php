<?php
// app/Services/VentaService.php

namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\GuiaRemision;
use App\Models\SerieComprobante;
use App\Models\Imei;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use App\Models\CuentaPorCobrar;
use App\Models\CuotaCobro;
use App\Models\PagoCredito;
use App\Models\AuditoriaVenta;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\ComisionDetalleVenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaService
{
    protected $precioRotativoService;

    public function __construct(PrecioRotativoService $precioRotativoService)
    {
        $this->precioRotativoService = $precioRotativoService;
    }

    private function calcularComisiones(Venta $venta): void
    {
        $ventaCargada = $venta->load('detalles.producto');
        try {
            app(\App\Services\ComisionService::class)->calcularParaVenta($ventaCargada);
        } catch (\Throwable $e) {
            Log::warning('Comisiones no calculadas para venta #' . $venta->id . ': ' . $e->getMessage());
        }
        try {
            app(\App\Services\BonusService::class)->calcularParaVenta($ventaCargada);
        } catch (\Throwable $e) {
            Log::warning('Bonos no calculados para venta #' . $venta->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Si alguna comisión de esta venta ya fue pagada al vendedor, no se puede
     * anular/eliminar la venta ni generar NC de anulación desde el sistema —
     * ese dinero ya salió, hay que resolverlo manualmente con el vendedor
     * antes de tocar el comprobante.
     */
    private function bloquearSiComisionPagada(Venta $venta): void
    {
        $venta->load('detalles');
        $detalleIds = $venta->detalles->pluck('id');

        if (ComisionDetalleVenta::whereIn('detalle_venta_id', $detalleIds)->where('estado', 'pagado')->exists()) {
            throw new \Exception(
                'No se puede anular ni eliminar esta venta: ya se pagó una comisión sobre ella. ' .
                'Resuelve el ajuste con el vendedor antes de continuar.'
            );
        }
    }

    /**
     * Borra las comisiones pendientes (no pagadas) ligadas a los detalles de
     * esta venta. Se asume que bloquearSiComisionPagada() ya se llamó antes
     * en la misma operación, así que cualquier comisión que quede aquí es
     * segura de eliminar (no hay dinero de por medio).
     */
    private function eliminarComisionesPendientes(Venta $venta): void
    {
        $detalleIds = $venta->detalles->pluck('id');
        ComisionDetalleVenta::whereIn('detalle_venta_id', $detalleIds)->where('estado', 'pendiente')->delete();
    }

    /**
     * Crear una nueva venta
     */
    public function crearVenta(array $datosVenta, array $detalles, ?array $pago = null, ?array $creditoData = null)
    {
        $esContado = ($datosVenta['condicion_pago'] ?? 'contado') !== 'credito' && empty($creditoData);
        $estaPagado = ($datosVenta['estado_pago'] ?? 'pagado') === 'pagado';

        if ($esContado && $estaPagado && auth()->check()) {
            $cajaAbierta = Caja::where('user_id', auth()->id())
                ->where('estado', 'abierta')
                ->exists();

            if (!$cajaAbierta) {
                throw new \Exception('Debe abrir caja antes de registrar una venta al contado. Vaya a Caja → Abrir Caja.');
            }
        }

        return DB::transaction(function () use ($datosVenta, $detalles, $pago, $creditoData) {

            // Extraer guia_data antes de crear la venta (no es columna de ventas)
            $guiaData = $datosVenta['guia_data'] ?? null;
            unset($datosVenta['guia_data']);

            $esCredito = ($datosVenta['condicion_pago'] ?? 'contado') === 'credito' || !empty($creditoData);

            // Validar stock antes de procesar
            $this->validarStockDisponible($detalles, $datosVenta['almacen_id']);

            // Para ventas a crédito, el estado inicial es 'credito' (sin pago inmediato)
            if ($esCredito) {
                $datosVenta['estado_pago']   = 'credito';
                $datosVenta['es_credito']    = true;
                $datosVenta['condicion_pago']= 'credito';
                $datosVenta['metodo_pago']   = null;
            }

            // Asignar serie SUNAT y correlativo real (boleta/factura) antes de crear.
            // Sin esto, Venta::numero_documento queda null y SunatComprobanteService
            // cae en un fallback fijo ("B001"/correlativo 1) en vez de la numeración
            // configurada — el comprobante nunca "jala" su serie real.
            if (in_array($datosVenta['tipo_comprobante'] ?? null, ['boleta', 'factura'], true)) {
                $datosVenta = array_merge(
                    $datosVenta,
                    $this->asignarSerieYCorrelativo($datosVenta['sucursal_id'] ?? null, $datosVenta['tipo_comprobante'])
                );
            }

            // Crear la venta
            $venta = Venta::create($datosVenta);

            $subtotal   = 0;
            $totalExact = 0;

            // Procesar cada detalle
            foreach ($detalles as $detalle) {
                $precioRecibido = (float) $detalle['precio_unitario'];
                $incluyeIgv     = (bool) ($detalle['incluye_igv'] ?? false);
                $qty            = (int)   $detalle['cantidad'];

                // precio_unitario en detalle_ventas siempre sin IGV (base imponible)
                $precioSinIgv   = $incluyeIgv ? round($precioRecibido / 1.18, 4) : $precioRecibido;
                $precioConIgv   = $incluyeIgv ? $precioRecibido : round($precioRecibido * 1.18, 4);

                $subtotalDetalle = round($precioSinIgv * $qty, 4);
                $subtotal       += $subtotalDetalle;
                $totalExact     += round($precioConIgv * $qty, 4);

                $detalleVenta = DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $detalle['producto_id'],
                    'variante_id'     => $detalle['variante_id'] ?? null,
                    'cantidad'        => $qty,
                    'precio_unitario' => $precioSinIgv,
                    'precio_con_igv'  => $precioConIgv,
                    'subtotal'        => $subtotalDetalle,
                    'subtotal_con_igv'=> round($precioConIgv * $qty, 4),
                ]);

                // Si es producto con IMEI, marcar los IMEIs como vendidos
                if (!empty($detalle['imeis'])) {
                    $this->marcarImeisVendidos($detalle['imeis'], $venta->id, $detalleVenta->id);
                }

                // Descontar del stock
                $this->descontarStock(
                    $detalle['producto_id'],
                    $datosVenta['almacen_id'],
                    $detalle['cantidad'],
                    $detalle['imeis'] ?? [],
                    $detalle['variante_id'] ?? null
                );
            }

            $total    = round($totalExact, 4);
            $subtotal = round($subtotal, 4);
            $igv      = round($total - $subtotal, 4);

            $venta->update([
                'subtotal' => $subtotal,
                'igv'      => $igv,
                'total'    => $total,
            ]);

            // Crear guía de remisión si se proporcionaron datos
            if ($guiaData) {
                $guiaService = app(\App\Services\GuiaRemisionService::class);

                // Mismo resolutor de numeración que usa el módulo de Guías de Remisión
                // y los traslados: evita duplicar la lógica (y que se le olvide
                // guia_serie_id, como pasaba antes aquí).
                $resuelto = $guiaService->resolverNumeroGuia($venta->almacen_id);

                // El formulario del POS manda ambos bloques (conductor y
                // transportista) siempre, y puede traer datos precargados de
                // la modalidad contraria (último conductor usado, transportista
                // guardado en localStorage) — se descarta el que no aplica.
                GuiaRemision::create(array_merge(
                    [
                        'venta_id'      => $venta->id,
                        'numero_guia'   => $resuelto['numero'],
                        'guia_serie_id' => $resuelto['serie_id'],
                    ],
                    $guiaService->sanitizarPorModalidad($guiaData)
                ));
            }

            if ($esCredito) {
                // Crear cuenta por cobrar con las cuotas
                $this->crearCuentaPorCobrar($venta, $creditoData ?? []);
            } else {
                // Si hay pago, procesarlo y registrar en caja
                if ($pago) {
                    $this->procesarPago($venta, $pago);
                    $this->registrarEnCaja($venta, 'venta');
                }
            }

            // Calcular comisiones solo si la venta queda pagada de inmediato
            // (ventas pendientes las calculamos cuando el cajero confirme el cobro,
            // ver confirmarPago). Debe ir DESPUÉS de procesarPago: la venta se crea
            // con estado_pago='pendiente' (default de BD) cuando el controlador no lo
            // fija explícitamente, y procesarPago es lo que recién la deja 'pagado'.
            // Antes esta comprobación iba antes de procesarPago y por eso ninguna
            // venta al contado (no "pendiente_cobro") generaba comisión.
            if ($venta->estado_pago === 'pagado') {
                $this->calcularComisiones($venta);
            }

            Log::info('Venta creada', [
                'venta_id'   => $venta->id,
                'user_id'    => $venta->user_id,
                'total'      => $venta->total,
                'es_credito' => $esCredito,
            ]);

            return $venta->fresh(['detalles.producto', 'cliente']);
        });
    }

    /**
     * Asigna la serie SUNAT activa y el siguiente correlativo para boleta/factura
     * en la sucursal dada, e incrementa el contador. Devuelve un array vacío
     * (serie_comprobante_id/correlativo en null) si el tipo no aplica (p. ej.
     * cotización) o si no hay una serie activa configurada para esa sucursal —
     * en ese caso se registra un warning mas no se bloquea la venta.
     */
    private function asignarSerieYCorrelativo(?int $sucursalId, string $tipoComprobante): array
    {
        $codigoSunat = match ($tipoComprobante) {
            'factura' => '01',
            'boleta'  => '03',
            default   => null,
        };

        if (!$codigoSunat || !$sucursalId) {
            return [];
        }

        $serie = SerieComprobante::where('sucursal_id', $sucursalId)
            ->where('tipo_comprobante', $codigoSunat)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (!$serie) {
            Log::warning('No hay serie de comprobante activa configurada', [
                'sucursal_id'      => $sucursalId,
                'tipo_comprobante' => $tipoComprobante,
            ]);
            return [];
        }

        $correlativo = $serie->correlativo_actual;
        $serie->increment('correlativo_actual');

        return ['serie_comprobante_id' => $serie->id, 'correlativo' => $correlativo];
    }

    /**
     * Crear la cuenta por cobrar y sus cuotas para una venta a crédito
     */
    private function crearCuentaPorCobrar(Venta $venta, array $creditoData): CuentaPorCobrar
    {
        $numeroCuotas    = (int) ($creditoData['numero_cuotas'] ?? 1);
        $diasEntreCuotas = (int) ($creditoData['dias_entre_cuotas'] ?? 30);
        $fechaInicio     = Carbon::parse($creditoData['fecha_inicio'] ?? now()->toDateString());

        $fechaVencimientoFinal = $fechaInicio->copy()->addDays($diasEntreCuotas * $numeroCuotas);

        $cuenta = CuentaPorCobrar::create([
            'venta_id'                => $venta->id,
            'cliente_id'              => $venta->cliente_id,
            'user_id'                 => auth()->id(),
            'monto_total'             => $venta->total,
            'monto_pagado'            => 0,
            'numero_cuotas'           => $numeroCuotas,
            'dias_entre_cuotas'       => $diasEntreCuotas,
            'fecha_inicio'            => $fechaInicio,
            'fecha_vencimiento_final' => $fechaVencimientoFinal,
            'estado'                  => 'vigente',
        ]);

        // Calcular monto por cuota (la primera absorbe el redondeo)
        $montoPorCuota  = round($venta->total / $numeroCuotas, 2);
        $montoTotal     = $montoPorCuota * $numeroCuotas;
        $diferencia     = round($venta->total - $montoTotal, 2);

        for ($i = 1; $i <= $numeroCuotas; $i++) {
            $monto = $montoPorCuota;
            if ($i === 1) {
                $monto = round($monto + $diferencia, 2);
            }

            CuotaCobro::create([
                'cuenta_por_cobrar_id' => $cuenta->id,
                'numero_cuota'         => $i,
                'total_cuotas'         => $numeroCuotas,
                'monto'                => $monto,
                'fecha_vencimiento'    => $fechaInicio->copy()->addDays($diasEntreCuotas * $i),
                'estado'               => 'pendiente',
            ]);
        }

        return $cuenta;
    }

    /**
     * Registrar un pago de crédito contra una cuenta por cobrar
     */
    public function registrarPagoCredito(CuentaPorCobrar $cuenta, array $pagoData): PagoCredito
    {
        // Verificar caja abierta antes de registrar el pago
        if (auth()->check()) {
            $cajaAbierta = Caja::where('user_id', auth()->id())
                ->where('estado', 'abierta')
                ->exists();

            if (!$cajaAbierta) {
                throw new \Exception('Debe abrir caja antes de registrar un pago. Vaya a Caja → Abrir Caja.');
            }
        }

        return DB::transaction(function () use ($cuenta, $pagoData) {
            $monto = (float) $pagoData['monto'];

            // Crear registro de pago
            $pago = PagoCredito::create([
                'cuenta_por_cobrar_id' => $cuenta->id,
                'cuota_cobro_id'       => $pagoData['cuota_cobro_id'] ?? null,
                'usuario_id'           => auth()->id(),
                'monto'                => $monto,
                'fecha_pago'           => $pagoData['fecha_pago'] ?? now()->toDateString(),
                'metodo_pago'          => $pagoData['metodo_pago'],
                'referencia'           => $pagoData['referencia'] ?? null,
                'observaciones'        => $pagoData['observaciones'] ?? null,
            ]);

            // Actualizar monto pagado y fecha último pago en la cuenta
            $nuevoPagado = round((float) $cuenta->monto_pagado + $monto, 2);
            $cuenta->update([
                'monto_pagado'    => $nuevoPagado,
                'fecha_ultimo_pago'=> now()->toDateString(),
            ]);

            // Marcar la cuota asociada como pagada (si se especificó)
            if (!empty($pagoData['cuota_cobro_id'])) {
                $cuota = CuotaCobro::find($pagoData['cuota_cobro_id']);
                if ($cuota && $cuota->estado === 'pendiente') {
                    $cuota->update([
                        'estado'         => 'pagado',
                        'fecha_pago_real'=> now()->toDateString(),
                    ]);
                }
            }

            // Si el monto pagado cubre el total, cerrar la cuenta y actualizar la venta
            if ($nuevoPagado >= (float) $cuenta->monto_total) {
                $cuenta->update(['estado' => 'pagado']);
                $cuenta->venta->update([
                    'estado_pago'         => 'pagado',
                    'fecha_confirmacion'  => now(),
                    'usuario_confirma_id' => auth()->id(),
                ]);
                // Registrar en caja el ingreso total (monto de este pago)
                $this->registrarEnCajaCredito($cuenta->venta, $monto, $pagoData['metodo_pago']);
            } else {
                // Registrar el pago parcial en caja
                $this->registrarEnCajaCredito($cuenta->venta, $monto, $pagoData['metodo_pago']);
            }

            Log::info('Pago de crédito registrado', [
                'cuenta_id' => $cuenta->id,
                'monto'     => $monto,
                'pagado'    => $nuevoPagado,
                'total'     => $cuenta->monto_total,
            ]);

            return $pago;
        });
    }

    /**
     * Editar campos no contables de una venta (tiempo limitado)
     */
    public function editarVenta(Venta $venta, array $datos, bool $requirioClave = false): Venta
    {
        if ($venta->estado_pago === 'anulado') {
            throw new \Exception('No se puede editar una venta anulada.');
        }

        // Si es crédito con pagos registrados, no permitir edición
        if ($venta->es_credito && $venta->cuentaPorCobrar && $venta->cuentaPorCobrar->pagos()->count() > 0) {
            throw new \Exception('No se puede editar una venta a crédito que ya tiene pagos registrados.');
        }

        $horasTranscurridas = $venta->created_at->diffInHours(now());
        $ventanaMaxima      = config('ventas.edit_window_hours', 24);

        if ($horasTranscurridas > $ventanaMaxima) {
            throw new \Exception("Solo se pueden editar comprobantes dentro de las {$ventanaMaxima} horas de su emisión.");
        }

        $camposPermitidos = ['observaciones', 'metodo_pago', 'fecha', 'guia_remision', 'transportista', 'placa_vehiculo'];

        // Permitir cambio de tipo_comprobante solo si aún no fue enviado a SUNAT
        if (!in_array($venta->estado_sunat, ['aceptado', 'enviado']) && isset($datos['tipo_comprobante'])) {
            $camposPermitidos[] = 'tipo_comprobante';
        }

        $actualizacion   = array_intersect_key($datos, array_flip($camposPermitidos));
        $datosAnteriores = $venta->only($camposPermitidos);

        $venta->update($actualizacion);

        // Actualizar guía de remisión si se enviaron datos
        if (!empty($datos['guia'])) {
            $guiaData = array_filter($datos['guia'], fn($v) => $v !== null && $v !== '');
            if (!empty($guiaData)) {
                // Si se está tocando la modalidad, descartar el bloque contrario
                // explícitamente — el form de edición solo expone los campos de
                // transportista, así que si una guía privada pasa a pública acá
                // nunca se manda conductor_dni/nombre/licencia/placa_vehiculo, y
                // sin esto ->update() (parcial) dejaba esos datos viejos intactos
                // en una guía que ya dice "pública".
                $guiaData = app(\App\Services\GuiaRemisionService::class)->sanitizarPorModalidad($guiaData);

                if ($venta->guiaRemision) {
                    $venta->guiaRemision->update($guiaData);
                } else {
                    $venta->guiaRemision()->create(array_merge($guiaData, ['venta_id' => $venta->id]));
                }
            }
        }

        $this->registrarAuditoria($venta, 'editar', $datosAnteriores, $venta->fresh()->only($camposPermitidos), $requirioClave);

        return $venta->fresh();
    }

    /**
     * Eliminar (soft-delete) una venta — revierte stock y oculta de las listas normales
     */
    /**
     * Eliminar es "como si la venta nunca hubiera existido": si estaba
     * pagada, se revierte (borra) el ingreso original en su caja — sin
     * dejar un movimiento nuevo de compensación. Es distinto de anular, que
     * conserva la venta como registro visible y sí deja un egreso nuevo
     * (ver anularVenta / registrarEnCaja).
     *
     * @return bool true si el ingreso de caja quedó revertido (o no aplicaba
     *              porque la venta no estaba pagada); false si estaba pagada
     *              pero no se encontró el movimiento de ingreso original.
     */
    public function eliminarVenta(Venta $venta, bool $requirioClave = false): bool
    {
        if ($venta->estado_pago === 'anulado') {
            throw new \Exception('No se puede eliminar una venta ya anulada. Use la papelera directamente si lo necesita.');
        }

        // Si tiene cuenta por cobrar con pagos, no se puede eliminar
        if ($venta->es_credito && $venta->cuentaPorCobrar) {
            $cuenta = $venta->cuentaPorCobrar;
            if ($cuenta->pagos()->count() > 0) {
                throw new \Exception('No se puede eliminar esta venta: tiene pagos de crédito registrados. Anule los pagos primero.');
            }
        }

        $this->bloquearSiComisionPagada($venta);

        return DB::transaction(function () use ($venta, $requirioClave) {
            $datosAnteriores = $venta->only([
                'codigo', 'estado_pago', 'estado_sunat', 'total', 'cliente_id', 'fecha', 'tipo_comprobante',
            ]);

            $this->revertirStock($venta, 'Eliminación de venta #' . $venta->codigo);
            $this->eliminarComisionesPendientes($venta);

            // Si es crédito sin pagos, anular la cuenta por cobrar y sus cuotas
            if ($venta->es_credito && $venta->cuentaPorCobrar) {
                $venta->cuentaPorCobrar->cuotas()->delete();
                $venta->cuentaPorCobrar->update(['estado' => 'anulado']);
            }

            // Si estaba pagada, revertir el ingreso original en su caja
            $cajaActualizada = true;
            if ($venta->estado_pago === 'pagado') {
                $cajaActualizada = $this->revertirIngresoEnCaja($venta);
            }

            $this->registrarAuditoria($venta, 'eliminar', $datosAnteriores, null, $requirioClave);

            // SoftDelete — la venta desaparece de las listas normales
            $venta->delete();

            return $cajaActualizada;
        });
    }

    // ══════════════════════════════════════════════════════════════════
    // SUNAT — Motivos de Nota de Crédito (tabla 10 UBL 2.1)
    // ══════════════════════════════════════════════════════════════════
    public const MOTIVOS_NC = [
        '01' => 'Anulación de la operación',
        '02' => 'Anulación por error en el RUC',
        '03' => 'Corrección por error en la descripción',
        '04' => 'Descuento global',
        '05' => 'Descuento por ítem',
        '06' => 'Devolución total',
        '07' => 'Devolución por ítem',
        '08' => 'Bonificación',
        '09' => 'Disminución en el valor',
        '10' => 'Otros conceptos',
    ];

    /**
     * Generar Nota de Crédito para un comprobante ya aceptado por SUNAT.
     * Esta es la ÚNICA forma válida de cancelar facturas/boletas aceptadas.
     */
    public function generarNotaCredito(Venta $venta, string $motivoCodigo, bool $requirioClave = false): Venta
    {
        if (!array_key_exists($motivoCodigo, self::MOTIVOS_NC)) {
            throw new \Exception("Motivo de nota de crédito inválido: {$motivoCodigo}");
        }

        if ($venta->es_nota_credito) {
            throw new \Exception('No se puede generar una nota de crédito sobre otra nota de crédito.');
        }

        if ($venta->estado_pago === 'cotizacion') {
            throw new \Exception('Las cotizaciones no requieren nota de crédito.');
        }

        if ($venta->estado_pago === 'anulado') {
            throw new \Exception('Esta venta ya está anulada. No se puede generar otra nota de crédito sobre ella.');
        }

        // Verificar que no tenga ya una NC de anulación activa
        $ncExistente = $venta->notasCredito()
            ->where('motivo_nc_codigo', '01')
            ->where('estado_pago', '!=', 'anulado')
            ->first();
        if ($ncExistente) {
            throw new \Exception("Ya existe una nota de crédito de anulación para este comprobante ({$ncExistente->codigo}).");
        }

        // Si tiene cuenta por cobrar con pagos, bloquear
        if ($venta->es_credito && $venta->cuentaPorCobrar && $venta->cuentaPorCobrar->pagos()->count() > 0) {
            throw new \Exception('No se puede generar NC: la venta tiene pagos de crédito registrados. Gestione la devolución manualmente.');
        }

        // Si la NC revertirá stock (anulación/devolución total) y ya se pagó
        // una comisión sobre esta venta, bloquear igual que anularVenta/eliminarVenta.
        if (in_array($motivoCodigo, ['01', '02', '06'])) {
            $this->bloquearSiComisionPagada($venta);
        }

        return DB::transaction(function () use ($venta, $motivoCodigo, $requirioClave) {
            $tipoNc      = $venta->tipo_comprobante === 'factura' ? 'nc_factura' : 'nc_boleta';
            $motivoDesc  = self::MOTIVOS_NC[$motivoCodigo];

            // Buscar serie de NC (código SUNAT '07' - Nota de Crédito). SUNAT exige series
            // distintas según el documento que se afecta: prefijo "F" para Notas de Factura,
            // prefijo "B" para Notas de Boleta (rechaza con error 2116 si no se respeta esto,
            // aunque el código de tipo_comprobante '07' sea el mismo en ambos casos).
            $prefijoSerie = $venta->tipo_comprobante === 'factura' ? 'F' : 'B';
            $serieNc = \App\Models\SerieComprobante::where('sucursal_id', $venta->sucursal_id)
                ->where('tipo_comprobante', '07')
                ->where('serie', 'like', $prefijoSerie . '%')
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if (!$serieNc) {
                throw new \Exception(
                    "No existe una serie activa de Nota de Crédito ({$prefijoSerie}..) para esta sucursal. " .
                    "Configure la serie en Administración → Series de Comprobantes."
                );
            }

            $correlativo = $serieNc->correlativo_actual;
            $serieNc->increment('correlativo_actual');

            $datosAnteriores = $venta->only(['codigo', 'estado_pago', 'estado_sunat', 'total']);

            // Crear el registro de la Nota de Crédito.
            // El código incluye la serie porque el correlativo reinicia en 1 por cada
            // serie (FC01, BC01, …) — usar solo el correlativo colisionaría (NC-00001
            // se repetiría en la primera NC de Factura y la primera de Boleta).
            $nc = Venta::create([
                'codigo'                 => 'NC-' . $serieNc->serie . '-' . str_pad($correlativo, 5, '0', STR_PAD_LEFT),
                'user_id'                => auth()->id(),
                'cliente_id'             => $venta->cliente_id,
                'almacen_id'             => $venta->almacen_id,
                'sucursal_id'            => $venta->sucursal_id,
                'serie_comprobante_id'   => $serieNc->id,
                'correlativo'            => $correlativo,
                'fecha'                  => now()->toDateString(),
                'subtotal'               => $venta->subtotal,
                'igv'                    => $venta->igv,
                'total'                  => $venta->total,
                'tipo_comprobante'       => $tipoNc,
                'estado_pago'            => 'pagado',
                'estado_sunat'           => 'pendiente_envio',
                'condicion_pago'         => 'contado',
                'venta_origen_id'        => $venta->id,
                'motivo_nc_codigo'       => $motivoCodigo,
                'motivo_nc_descripcion'  => $motivoDesc,
                'observaciones'          => "NC {$motivoDesc} — Ref: {$venta->numero_documento}",
            ]);

            // Copiar los detalles del comprobante original
            $venta->load('detalles');
            foreach ($venta->detalles as $det) {
                \App\Models\DetalleVenta::create([
                    'venta_id'        => $nc->id,
                    'producto_id'     => $det->producto_id,
                    'variante_id'     => $det->variante_id,
                    'cantidad'        => $det->cantidad,
                    'precio_unitario' => $det->precio_unitario,
                    'precio_con_igv'  => $det->precio_con_igv,
                    'subtotal'        => $det->subtotal,
                    'subtotal_con_igv'=> $det->subtotal_con_igv,
                ]);
            }

            // Si la NC es por anulación (motivo 01 o 06 = devolución total) → revertir stock
            $cajaActualizada = true;
            if (in_array($motivoCodigo, ['01', '02', '06'])) {
                $this->revertirStock($venta, 'Nota de Crédito #' . $nc->codigo);
                $this->eliminarComisionesPendientes($venta);

                // Estado ANTES de marcar el comprobante como anulado — el update()
                // de abajo muta $venta->estado_pago en memoria, así que si se
                // comprobara después de esa llamada el "pagado" nunca se detectaría
                // y el egreso de caja jamás se registraría.
                $estabaPagada = $datosAnteriores['estado_pago'] === 'pagado';

                // Marcar comprobante original como anulado
                $venta->update([
                    'estado_pago'  => 'anulado',
                    'estado_sunat' => 'anulado_baja', // marcar que fue resuelto vía NC
                ]);

                // Si es crédito sin pagos, cerrar la cuenta
                if ($venta->es_credito && $venta->cuentaPorCobrar) {
                    $venta->cuentaPorCobrar->cuotas()->delete();
                    $venta->cuentaPorCobrar->update(['estado' => 'anulado']);
                }

                // Si estaba pagada → registrar egreso en caja
                if ($estabaPagada) {
                    $cajaActualizada = $this->registrarEnCaja($venta, 'nota_credito');
                }
            }

            $this->registrarAuditoria($venta, 'anular', $datosAnteriores, ['nc_generada' => $nc->codigo], $requirioClave);

            Log::info('Nota de Crédito generada', [
                'nc_id'          => $nc->id,
                'nc_codigo'      => $nc->codigo,
                'venta_origen'   => $venta->codigo,
                'motivo_codigo'  => $motivoCodigo,
                'motivo_desc'    => $motivoDesc,
                'user_id'        => auth()->id(),
            ]);

            $ncFresca = $nc->fresh(['ventaOrigen', 'serieComprobante', 'detalles.producto']);
            $ncFresca->setAttribute('caja_actualizada', $cajaActualizada);

            return $ncFresca;
        });
    }

    /**
     * Anular una venta (revertir stock)
     * ⚠ Solo válido si el comprobante NO ha sido aceptado por SUNAT.
     * Si ya fue aceptado, usar generarNotaCredito().
     */
    /**
     * A diferencia de eliminar, aquí la venta queda visible (marcada
     * "Anulado") y, si estaba pagada, se deja un egreso NUEVO en su caja
     * original (aunque ya esté cerrada) en vez de borrar el ingreso —
     * queda rastro visible de la corrección.
     *
     * @return bool true si el egreso de caja quedó registrado (o no aplicaba
     *              porque la venta no estaba pagada); false si estaba pagada
     *              pero no se encontró ninguna caja (ni la original de la
     *              venta ni una abierta del usuario actual) para registrarlo.
     */
    public function anularVenta(Venta $venta, bool $requirioClave = false): bool
    {
        if (in_array($venta->estado_pago, ['anulado', 'cotizacion'])) {
            throw new \Exception('Esta venta ya está anulada o es una cotización.');
        }

        // Bloquear si ya fue aceptada por SUNAT — debe usarse Nota de Crédito
        if ($venta->es_aceptado_sunat) {
            throw new \Exception(
                'Este comprobante fue aceptado por SUNAT y no puede anularse directamente. ' .
                'Debes generar una Nota de Crédito (motivo 01 - Anulación).'
            );
        }

        // Si tiene cuenta por cobrar con pagos, no se puede anular
        if ($venta->es_credito && $venta->cuentaPorCobrar) {
            $cuenta = $venta->cuentaPorCobrar;
            if ($cuenta->pagos()->count() > 0) {
                throw new \Exception('No se puede anular esta venta: tiene pagos de crédito registrados. Gestione la devolución manualmente.');
            }
        }

        $this->bloquearSiComisionPagada($venta);

        return DB::transaction(function () use ($venta, $requirioClave) {
            $datosAnteriores = $venta->only([
                'codigo', 'estado_pago', 'estado_sunat', 'total', 'cliente_id', 'fecha', 'tipo_comprobante',
            ]);

            $this->revertirStock($venta, 'Anulación de venta #' . $venta->codigo);
            $this->eliminarComisionesPendientes($venta);

            // Si es crédito sin pagos, anular la cuenta por cobrar y sus cuotas
            if ($venta->es_credito && $venta->cuentaPorCobrar) {
                $venta->cuentaPorCobrar->cuotas()->delete();
                $venta->cuentaPorCobrar->update(['estado' => 'anulado']);
            }

            // Si estaba pagada, registrar egreso en caja (devolución)
            $cajaActualizada = true;
            if ($venta->estado_pago === 'pagado') {
                $cajaActualizada = $this->registrarEnCaja($venta, 'anulacion');
            }

            $venta->update(['estado_pago' => 'anulado']);

            $this->registrarAuditoria($venta, 'anular', $datosAnteriores, $venta->fresh()->only(['estado_pago']), $requirioClave);

            return $cajaActualizada;
        });
    }

    /**
     * Revertir stock de todos los detalles de una venta.
     * Reutilizado por anularVenta, eliminarVenta y generarNotaCredito.
     */
    private function revertirStock(Venta $venta, string $motivo): void
    {
        $venta->load('detalles.producto');
        foreach ($venta->detalles as $detalle) {
            $esSerie = $detalle->producto?->tipo_inventario === 'serie';

            // NOTA: detalle_ventas.imei_id nunca se llena al crear la venta (bug
            // histórico), así que NO se puede usar para ubicar el IMEI vendido.
            // En su lugar se busca por venta_id + producto_id (+ variante_id),
            // tomando como máximo la cantidad de esta línea — así, si la venta
            // tiene varias líneas del mismo producto, cada una reclama solo los
            // IMEIs que le corresponden y no se pisan entre sí.
            if ($esSerie) {
                // Producto tipo "serie": el stock real vive en imeis (conteo por estado_imei),
                // no en stock_almacen — esa tabla es solo para productos tipo "cantidad".
                // Tocarla aquí para productos serie la contamina con filas que nadie
                // mantiene y que reportes futuros podrían sumar por error.
                $stockAnterior = Imei::where('producto_id', $detalle->producto_id)
                    ->where('estado_imei', 'en_stock')
                    ->count();

                $imeisVendidos = Imei::where('venta_id', $venta->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->when($detalle->variante_id, fn($q) => $q->where('variante_id', $detalle->variante_id))
                    ->where('estado_imei', 'vendido')
                    ->limit($detalle->cantidad)
                    ->pluck('id');

                Imei::whereIn('id', $imeisVendidos)->update([
                    'estado_imei' => 'en_stock',
                    'venta_id'    => null,
                    'fecha_venta' => null,
                ]);

                // Recontar desde la fuente real (imeis) en vez de incrementar/decrementar:
                // así producto.stock_actual y producto_variante.stock_actual quedan exactos
                // sin importar el estado previo de esas columnas (evita el arrastre de
                // desincronizaciones de otros flujos).
                $stockNuevo = Imei::where('producto_id', $detalle->producto_id)
                    ->where('estado_imei', 'en_stock')
                    ->count();
                \App\Models\Producto::where('id', $detalle->producto_id)
                    ->update(['stock_actual' => $stockNuevo]);

                if ($detalle->variante_id) {
                    $totalVariante = Imei::where('variante_id', $detalle->variante_id)
                        ->where('estado_imei', 'en_stock')
                        ->count();
                    \App\Models\ProductoVariante::where('id', $detalle->variante_id)
                        ->update(['stock_actual' => $totalVariante]);
                }
            } else {
                $stock = StockAlmacen::firstOrCreate(
                    ['producto_id' => $detalle->producto_id, 'almacen_id' => $venta->almacen_id],
                    ['cantidad' => 0]
                );

                $stockAnterior = $stock->cantidad;
                $stock->increment('cantidad', $detalle->cantidad);
                $stockNuevo = $stock->cantidad;

                // Igual que descontarStock() al vender: mantener sincronizados
                // producto.stock_actual y producto_variante.stock_actual con el
                // total real de stock_almacen, si no quedan desfasados tras cada
                // anulación/eliminación/NC.
                if ($detalle->variante_id) {
                    $detalle->variante?->incrementarStock($detalle->cantidad);
                } else {
                    $totalStock = StockAlmacen::where('producto_id', $detalle->producto_id)->sum('cantidad');
                    \App\Models\Producto::where('id', $detalle->producto_id)->update(['stock_actual' => $totalStock]);
                }
            }

            MovimientoInventario::create([
                'producto_id'     => $detalle->producto_id,
                'almacen_id'      => $venta->almacen_id,
                'user_id'         => auth()->id(),
                'tipo_movimiento' => 'ingreso',
                'cantidad'        => $detalle->cantidad,
                'stock_anterior'  => $stockAnterior,
                'stock_nuevo'     => $stockNuevo,
                'motivo'          => $motivo,
                'estado'          => 'completado',
            ]);
        }
    }

    /**
     * Registrar una entrada en la bitácora de auditoría
     */
    private function registrarAuditoria(
        Venta $venta,
        string $accion,
        array $datosAnteriores,
        ?array $datosNuevos,
        bool $requirioClave
    ): void {
        AuditoriaVenta::create([
            'venta_id'         => $venta->id,
            'usuario_id'       => auth()->id(),
            'accion'           => $accion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos'     => $datosNuevos,
            'requirio_clave'   => $requirioClave,
            'ip_address'       => request()->ip(),
        ]);
    }

    /**
     * Registrar ingreso en caja por pago parcial de crédito
     */
    private function registrarEnCajaCredito(Venta $venta, float $monto, string $metodoPago): void
    {
        $caja = \App\Models\Caja::where('user_id', auth()->id())
            ->where('estado', 'abierta')
            ->first();

        if ($caja) {
            $cajaService = app(CajaService::class);
            $cajaService->registrarMovimiento(
                $caja->id,
                'ingreso',
                $monto,
                'Pago crédito venta #' . $venta->codigo,
                $venta->id,
                null,
                null,
                $metodoPago,
                null
            );
        }
    }

    /**
     * Validar stock disponible antes de la venta
     */
    private function validarStockDisponible(array $detalles, int $almacenId)
    {
        foreach ($detalles as $detalle) {
            // Productos serie/IMEI: el stock se controla por unidad individual
            if (!empty($detalle['imeis'])) {
                $this->validarImeisDisponibles($detalle['imeis'], $almacenId);
                continue;
            }

            $stock = StockAlmacen::where('producto_id', $detalle['producto_id'])
                ->where('almacen_id', $almacenId)
                ->first();

            if (!$stock || $stock->cantidad < $detalle['cantidad']) {
                $producto = \App\Models\Producto::find($detalle['producto_id']);
                throw new \Exception("Stock insuficiente para {$producto->nombre}. Disponible: " . ($stock->cantidad ?? 0));
            }
        }
    }

    /**
     * Validar que los IMEIs estén disponibles
     */
    private function validarImeisDisponibles(array $imeis, int $almacenId)
    {
        $codigosImei = array_column($imeis, 'codigo_imei');

        $existentes = Imei::whereIn('codigo_imei', $codigosImei)
            ->where('almacen_id', $almacenId)
            ->where('estado_imei', 'en_stock')
            ->get();

        if ($existentes->count() !== count($codigosImei)) {
            $encontrados = $existentes->pluck('codigo_imei')->toArray();
            $faltantes   = array_diff($codigosImei, $encontrados);
            throw new \Exception("Los siguientes IMEIs no están disponibles: " . implode(', ', $faltantes));
        }
    }

    /**
     * Marcar IMEIs como vendidos
     */
    private function marcarImeisVendidos(array $imeis, int $ventaId, int $detalleVentaId)
    {
        foreach ($imeis as $imeiData) {
            Imei::where('codigo_imei', $imeiData['codigo_imei'])
                ->update([
                    'estado_imei' => 'vendido',
                    'fecha_venta'  => now(),
                    'venta_id'     => $ventaId,
                ]);
        }
    }

    /**
     * Descontar stock del almacén
     */
    private function descontarStock(int $productoId, int $almacenId, int $cantidad, array $imeis = [], ?int $varianteId = null)
    {
        $producto      = \App\Models\Producto::find($productoId);
        $stockAnterior = 0;
        $stockNuevo    = 0;

        if (!empty($imeis)) {
            $stockNuevo    = Imei::where('producto_id', $productoId)
                                 ->where('almacen_id', $almacenId)
                                 ->where('estado_imei', 'en_stock')
                                 ->count();
            $stockAnterior = $stockNuevo + $cantidad;

            $totalStock = Imei::where('producto_id', $productoId)
                               ->where('estado_imei', 'en_stock')
                               ->count();
            $producto->update(['stock_actual' => $totalStock]);

            if ($varianteId) {
                $stockVariante = Imei::where('variante_id', $varianteId)
                                     ->where('estado_imei', 'en_stock')
                                     ->count();
                \App\Models\ProductoVariante::where('id', $varianteId)
                    ->update(['stock_actual' => $stockVariante]);
            }
        } else {
            $stock = StockAlmacen::where('producto_id', $productoId)
                ->where('almacen_id', $almacenId)
                ->first();

            if ($stock) {
                $stockAnterior = $stock->cantidad;
                $stock->decrement('cantidad', $cantidad);
                $stockNuevo = $stock->cantidad;

                $totalStock = StockAlmacen::where('producto_id', $productoId)->sum('cantidad');
                $producto->update(['stock_actual' => $totalStock]);
            }

            if ($varianteId) {
                $variante = \App\Models\ProductoVariante::find($varianteId);
                if ($variante) {
                    $variante->decrementarStock($cantidad);
                }
            }
        }

        MovimientoInventario::create([
            'producto_id'     => $productoId,
            'almacen_id'      => $almacenId,
            'user_id'         => auth()->id(),
            'tipo_movimiento' => 'salida',
            'cantidad'        => $cantidad,
            'stock_anterior'  => $stockAnterior,
            'stock_nuevo'     => $stockNuevo,
            'motivo'          => 'Venta',
            'estado'          => 'completado',
            'imeis'           => !empty($imeis) ? json_encode($imeis) : null,
        ]);
    }

    /**
     * Procesar pago de la venta
     */
    private function procesarPago(Venta $venta, array $pago)
    {
        $venta->update([
            'estado_pago'         => 'pagado',
            'metodo_pago'         => $pago['metodo_pago'],
            'fecha_confirmacion'  => now(),
            'usuario_confirma_id' => auth()->id(),
        ]);
    }

    /**
     * Registrar en caja.
     * $contexto: 'venta' (ingreso) | 'anulacion' | 'eliminacion' | 'nota_credito' (egreso/devolución)
     *
     * @return bool true si el movimiento quedó registrado; false si no hay
     *              ninguna caja a la que atribuirlo (ni la caja original de
     *              la venta, ni una caja abierta del usuario actual).
     */
    private function registrarEnCaja(Venta $venta, string $contexto): bool
    {
        $cajaService = app(CajaService::class);

        // Para devoluciones (anular/eliminar/NC) el movimiento debe caer en
        // la caja donde se registró originalmente el ingreso de esta venta
        // — no en "la que esté abierta ahora", que puede ser otra sesión de
        // caja distinta (u otro día). Para una venta nueva ('venta') todavía
        // no existe ese ingreso, así que cae directo al fallback.
        $caja = $cajaService->cajaDeVenta($venta->id)
            ?? Caja::where('user_id', auth()->id())->where('estado', 'abierta')->first();

        if (!$caja) {
            return false;
        }

        $esDevolucion = in_array($contexto, ['anulacion', 'eliminacion', 'nota_credito']);
        $tipo         = $esDevolucion ? 'egreso' : 'ingreso';

        $metodosValidos = ['efectivo', 'yape', 'plin', 'transferencia', 'mixto'];
        $metodoPago     = in_array($venta->metodo_pago, $metodosValidos)
            ? $venta->metodo_pago
            : 'efectivo';

        $conceptos = [
            'venta'       => 'Venta #' . $venta->codigo,
            'anulacion'   => 'Anulación venta #' . $venta->codigo,
            'eliminacion' => 'Eliminación venta #' . $venta->codigo,
            'nota_credito'=> 'Nota Crédito venta #' . $venta->codigo,
        ];
        $concepto = $conceptos[$contexto] ?? 'Venta #' . $venta->codigo;

        // Para pagos mixtos: un movimiento por cada método con su referencia
        $pagosDetalle = $venta->pagos_detalle ?? [];
        if ($tipo === 'ingreso' && count($pagosDetalle) > 1) {
            foreach ($pagosDetalle as $pd) {
                $mp  = in_array($pd['metodo'] ?? '', $metodosValidos) ? $pd['metodo'] : 'efectivo';
                $ref = $pd['referencia'] ?? null;
                $cajaService->registrarCorreccion($caja, $tipo, (float) $pd['monto'], $concepto, $venta->id, $mp, $ref);
            }
            return true;
        }

        // Pago único: extraer referencia si existe
        $referencia = null;
        if ($tipo === 'ingreso' && count($pagosDetalle) === 1) {
            $referencia = $pagosDetalle[0]['referencia'] ?? null;
        }

        $cajaService->registrarCorreccion($caja, $tipo, (float) $venta->total, $concepto, $venta->id, $metodoPago, $referencia);

        return true;
    }

    /**
     * Revierte (borra) el/los movimiento(s) de ingreso original de una venta
     * en su caja, dejando la caja como si esa venta nunca se hubiera pagado
     * — sin crear ningún movimiento nuevo. Usado por eliminarVenta(); a
     * diferencia de anular/NC, aquí no debe quedar rastro de compensación.
     *
     * @return bool true si se encontró y revirtió el ingreso; false si la
     *              venta no tenía ningún ingreso de caja que revertir.
     */
    private function revertirIngresoEnCaja(Venta $venta): bool
    {
        $ingresos = MovimientoCaja::where('venta_id', $venta->id)
            ->where('tipo', 'ingreso')
            ->get();

        if ($ingresos->isEmpty()) {
            return false;
        }

        $caja = Caja::find($ingresos->first()->caja_id);
        if (!$caja) {
            return false;
        }

        $total = (float) $ingresos->sum('monto');

        MovimientoCaja::whereIn('id', $ingresos->pluck('id'))->delete();

        $caja->decrement('monto_final', $total);

        if ($caja->estado === 'cerrada') {
            app(CajaService::class)->recalcularDiferenciaCierre($caja);
        }

        return true;
    }

    /**
     * Crear una cotización (no descuenta stock, no registra pago)
     */
    public function crearCotizacion(array $datosVenta, array $detalles)
    {
        return DB::transaction(function () use ($datosVenta, $detalles) {
            $datosVenta['tipo_comprobante'] = 'cotizacion';
            $datosVenta['estado_pago']      = 'cotizacion';

            $venta      = Venta::create($datosVenta);
            $subtotal   = 0;
            $totalExact = 0;

            foreach ($detalles as $detalle) {
                $precioRecibido  = (float) $detalle['precio_unitario'];
                $incluyeIgv      = (bool)  ($detalle['incluye_igv'] ?? false);
                $qty             = (int)   $detalle['cantidad'];

                $precioSinIgv    = $incluyeIgv ? round($precioRecibido / 1.18, 4) : $precioRecibido;
                $precioConIgv    = $incluyeIgv ? $precioRecibido : round($precioRecibido * 1.18, 4);
                $subtotalDetalle = round($precioSinIgv * $qty, 4);
                $subtotal       += $subtotalDetalle;
                $totalExact     += round($precioConIgv * $qty, 4);

                DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $detalle['producto_id'],
                    'variante_id'     => $detalle['variante_id'] ?? null,
                    'cantidad'        => $qty,
                    'precio_unitario' => $precioSinIgv,
                    'precio_con_igv'  => $precioConIgv,
                    'subtotal'        => $subtotalDetalle,
                    'subtotal_con_igv'=> round($precioConIgv * $qty, 4),
                ]);
            }

            $total    = round($totalExact, 4);
            $subtotal = round($subtotal, 4);
            $igv      = round($total - $subtotal, 4);

            $venta->update(['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total]);

            Log::info('Cotización creada', ['venta_id' => $venta->id, 'user_id' => $venta->user_id]);

            return $venta->fresh(['detalles.producto', 'cliente']);
        });
    }

    /**
     * Convertir una cotización a boleta o factura (descuenta stock y registra pago)
     *
     * $imeisPorDetalle: [detalle_venta_id => [codigo_imei, ...]] — obligatorio para
     * cada línea de producto tipo "serie"; sin esto nunca se elegía qué unidad física
     * se vendía y ni el comprobante ni la guía podían mostrar la serie/IMEI.
     */
    public function convertirAVenta(Venta $venta, string $tipoComprobante, string $metodoPago, array $imeisPorDetalle = [])
    {
        if ($venta->tipo_comprobante !== 'cotizacion') {
            throw new \Exception('Solo se pueden convertir cotizaciones');
        }

        return DB::transaction(function () use ($venta, $tipoComprobante, $metodoPago, $imeisPorDetalle) {
            $venta->load('detalles.producto');
            $detallesVenta = $venta->detalles->values();

            $detalles = $detallesVenta->map(function ($d) use ($imeisPorDetalle) {
                $esSerie = $d->producto?->tipo_inventario === 'serie';
                $codigos = $esSerie ? array_values($imeisPorDetalle[$d->id] ?? []) : [];

                if ($esSerie && count($codigos) !== $d->cantidad) {
                    throw new \Exception(
                        "Debe seleccionar exactamente {$d->cantidad} IMEI(s) para «{$d->producto->nombre}»."
                    );
                }

                return [
                    'producto_id'     => $d->producto_id,
                    'variante_id'     => $d->variante_id,
                    'cantidad'        => $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'imeis'           => array_map(fn($codigo) => ['codigo_imei' => $codigo], $codigos),
                ];
            })->toArray();

            $this->validarStockDisponible($detalles, $venta->almacen_id);

            foreach ($detalles as $i => $detalle) {
                if (!empty($detalle['imeis'])) {
                    $this->marcarImeisVendidos($detalle['imeis'], $venta->id, $detallesVenta[$i]->id);
                }

                $this->descontarStock(
                    $detalle['producto_id'],
                    $venta->almacen_id,
                    $detalle['cantidad'],
                    $detalle['imeis'],
                    $detalle['variante_id'] ?? null
                );
            }

            $venta->update(array_merge([
                'tipo_comprobante'    => $tipoComprobante,
                'estado_pago'         => 'pagado',
                'metodo_pago'         => $metodoPago,
                'fecha_confirmacion'  => now(),
                'usuario_confirma_id' => auth()->id(),
            ], $this->asignarSerieYCorrelativo($venta->sucursal_id, $tipoComprobante)));

            $this->registrarEnCaja($venta, 'venta');

            Log::info('Cotización convertida a venta', [
                'venta_id'         => $venta->id,
                'tipo_comprobante' => $tipoComprobante,
            ]);

            return $venta->fresh();
        });
    }

    /**
     * Confirmar pago de una venta pendiente
     */
    public function confirmarPago(int $ventaId, string $metodoPago, int $usuarioId, array $extra = [])
    {
        $venta = Venta::findOrFail($ventaId);

        if ($venta->estado_pago !== 'pendiente') {
            throw new \Exception('La venta ya ha sido procesada');
        }

        DB::transaction(function () use ($venta, $metodoPago, $usuarioId, $extra) {
            $update = [
                'estado_pago'         => 'pagado',
                'metodo_pago'         => $metodoPago,
                'usuario_confirma_id' => $usuarioId,
                'fecha_confirmacion'  => now(),
            ];

            if (!empty($extra['pagos_detalle'])) {
                $update['pagos_detalle'] = $extra['pagos_detalle'];
            }

            $venta->update($update);
            $this->registrarEnCaja($venta, 'venta');
        });

        $ventaFresh = $venta->fresh();
        $this->calcularComisiones($ventaFresh);

        return $ventaFresh;
    }
}
