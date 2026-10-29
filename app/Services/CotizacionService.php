<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Cotizaciones: documento comercial previo a la venta.
 *
 * No mueve stock ni caja. Al convertirla se crea una venta real (nota de venta, boleta o factura) con
 * VentaService::crearVenta, que valida el stock, abre la numeración y registra el cobro igual que en el POS.
 */
class CotizacionService
{
    public const IGV = 1.18;
    public const COMPROBANTES = ['nota_venta', 'boleta', 'factura'];

    public function __construct(private NumeracionService $numeracion, private VentaService $ventas)
    {
    }

    // ── Alta y edición ────────────────────────────────────────────────────

    /**
     * @param array $datos     cliente_id, almacen_id, user_id, [fecha], [validez_dias], [condiciones], [observaciones]
     * @param array $detalles  [{producto_id, variante_id?, cantidad, precio_unitario, incluye_igv?}]
     */
    public function crear(array $datos, array $detalles): Cotizacion
    {
        return DB::transaction(function () use ($datos, $detalles) {
            Cliente::findOrFail($datos['cliente_id']);
            $almacen = Almacen::findOrFail($datos['almacen_id']);

            $sucursalId = Sucursal::where('almacen_id', $almacen->id)->value('id');
            $numero = $this->numeracion->siguiente('CT', $sucursalId);

            $fecha = isset($datos['fecha']) ? \Carbon\Carbon::parse($datos['fecha'])->startOfDay() : today();
            $validez = max(1, (int) ($datos['validez_dias'] ?? config('kyrios.cotizacion.validez_dias', 7)));

            $cotizacion = Cotizacion::create([
                'codigo'               => $numero['numero'] ?? ('COT-' . str_pad((string) ((int) Cotizacion::max('id') + 1), 6, '0', STR_PAD_LEFT)),
                'serie_comprobante_id' => $numero['serie_id'] ?? null,
                'correlativo'          => $numero['correlativo'] ?? null,
                'user_id'              => $datos['user_id'],
                'cliente_id'           => $datos['cliente_id'],
                'almacen_id'           => $almacen->id,
                'sucursal_id'          => $sucursalId,
                'fecha'                => $fecha->toDateString(),
                'validez_dias'         => $validez,
                'fecha_vencimiento'    => $fecha->copy()->addDays($validez)->toDateString(),
                'condiciones'          => $datos['condiciones'] ?? null,
                'observaciones'        => $datos['observaciones'] ?? null,
                'estado'               => 'pendiente',
            ]);

            $this->guardarDetalles($cotizacion, $detalles);

            return $cotizacion->fresh(['detalles', 'cliente']);
        });
    }

    public function actualizar(Cotizacion $cotizacion, array $datos, array $detalles): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $datos, $detalles) {
            $cotizacion = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            if (!$cotizacion->puede_editarse) {
                throw new \DomainException('Solo se puede editar una cotización pendiente.');
            }

            Cliente::findOrFail($datos['cliente_id']);
            $almacen = Almacen::findOrFail($datos['almacen_id']);

            $validez = max(1, (int) ($datos['validez_dias'] ?? $cotizacion->validez_dias));
            $cotizacion->update([
                'cliente_id'        => $datos['cliente_id'],
                'almacen_id'        => $almacen->id,
                'sucursal_id'       => Sucursal::where('almacen_id', $almacen->id)->value('id'),
                'validez_dias'      => $validez,
                'fecha_vencimiento' => $cotizacion->fecha->copy()->addDays($validez)->toDateString(),
                'condiciones'       => $datos['condiciones'] ?? null,
                'observaciones'     => $datos['observaciones'] ?? null,
            ]);

            $cotizacion->detalles()->delete();
            $this->guardarDetalles($cotizacion, $detalles);

            return $cotizacion->fresh(['detalles', 'cliente']);
        });
    }

    /** Valida y guarda las líneas; recalcula subtotal, IGV y total (precios con IGV incluido). */
    private function guardarDetalles(Cotizacion $cotizacion, array $detalles): void
    {
        if (empty($detalles)) {
            throw new \DomainException('Agregue al menos un producto a la cotización.');
        }

        $subtotal = 0.0;
        $totalExacto = 0.0;

        foreach ($detalles as $d) {
            $producto = Producto::findOrFail($d['producto_id']);

            $variante = !empty($d['variante_id']) ? ProductoVariante::findOrFail($d['variante_id']) : null;
            if ($variante && $variante->producto_id !== $producto->id) {
                throw new \DomainException('La variante no pertenece al producto indicado.');
            }
            if (!$variante && $producto->variantes()->where('estado', 'activo')->exists()) {
                throw new \DomainException("«{$producto->nombre}» tiene variantes: elija una.");
            }

            $cantidad = (int) $d['cantidad'];
            $precio = (float) $d['precio_unitario'];
            if ($cantidad < 1 || $precio <= 0) {
                throw new \DomainException("«{$producto->nombre}»: cantidad y precio deben ser mayores que cero.");
            }

            $incluyeIgv = (bool) ($d['incluye_igv'] ?? true);
            $sinIgv = $incluyeIgv ? round($precio / self::IGV, 4) : $precio;
            $conIgv = $incluyeIgv ? $precio : round($precio * self::IGV, 4);
            $subtotalLinea = round($sinIgv * $cantidad, 2);
            $subtotal += $subtotalLinea;
            $totalExacto += round($conIgv * $cantidad, 4);

            $cotizacion->detalles()->create([
                'producto_id'      => $producto->id,
                'variante_id'      => $variante?->id,
                'codigo'           => $variante?->codigo_kyrios ?? $producto->codigo,
                'descripcion'      => mb_substr(\App\Support\EtiquetaProducto::nombre($producto) . ($variante && $variante->nombre_completo !== 'Variante base' ? ' — ' . $variante->nombre_completo : ''), 0, 255),
                'cantidad'         => $cantidad,
                'precio_unitario'  => $sinIgv,
                'precio_con_igv'   => $conIgv,
                'subtotal'         => $subtotalLinea,
                'subtotal_con_igv' => round($conIgv * $cantidad, 2),
            ]);
        }

        $total = round($totalExacto, 2);
        $subtotal = round($subtotal, 2);
        $cotizacion->update(['subtotal' => $subtotal, 'igv' => round($total - $subtotal, 2), 'total' => $total]);
    }

    // ── Estado ────────────────────────────────────────────────────────────

    private const TRANSICIONES = [
        'pendiente'  => ['aceptada', 'rechazada', 'anulada'],
        'aceptada'   => ['pendiente', 'rechazada', 'anulada'],
        'rechazada'  => ['pendiente'],
        'convertida' => [],
        'anulada'    => [],
    ];

    public function cambiarEstado(Cotizacion $cotizacion, string $estado, ?string $motivo = null): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $estado, $motivo) {
            $cotizacion = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            if (!in_array($estado, self::TRANSICIONES[$cotizacion->estado] ?? [], true)) {
                throw new \DomainException("No se puede pasar una cotización {$cotizacion->estado} a {$estado}.");
            }
            if (in_array($estado, ['rechazada', 'anulada'], true) && !filled($motivo)) {
                throw new \DomainException('Indique el motivo.');
            }

            $cotizacion->update(['estado' => $estado, 'motivo_estado' => $motivo ?: null]);

            return $cotizacion;
        });
    }

    /** Extiende la validez desde hoy (cotizaciones pendientes o aceptadas, también las vencidas). */
    public function renovar(Cotizacion $cotizacion, int $dias): Cotizacion
    {
        if (!in_array($cotizacion->estado, ['pendiente', 'aceptada'], true)) {
            throw new \DomainException('Solo se renuevan cotizaciones pendientes o aceptadas.');
        }
        $dias = max(1, $dias);
        $cotizacion->update(['validez_dias' => $dias, 'fecha_vencimiento' => today()->addDays($dias)->toDateString()]);

        return $cotizacion;
    }

    /** Copia como cotización nueva (otro número, fecha de hoy, pendiente). */
    public function duplicar(Cotizacion $original, User $usuario): Cotizacion
    {
        $detalles = $original->detalles->map(fn ($d) => [
            'producto_id'     => $d->producto_id,
            'variante_id'     => $d->variante_id,
            'cantidad'        => $d->cantidad,
            'precio_unitario' => (float) $d->precio_con_igv,
            'incluye_igv'     => true,
        ])->all();

        return $this->crear([
            'cliente_id'    => $original->cliente_id,
            'almacen_id'    => $original->almacen_id,
            'user_id'       => $usuario->id,
            'validez_dias'  => $original->validez_dias,
            'condiciones'   => $original->condiciones,
            'observaciones' => $original->observaciones,
        ], $detalles);
    }

    // ── Conversión a venta ────────────────────────────────────────────────

    /**
     * @param string $comprobante 'nota_venta' | 'boleta' | 'factura'
     * @param array  $opciones    condicion ('contado'|'pendiente_cobro'), metodo_pago (si contado)
     */
    public function convertir(Cotizacion $cotizacion, string $comprobante, array $opciones, User $usuario): Venta
    {
        if (!in_array($comprobante, self::COMPROBANTES, true)) {
            throw new \DomainException('Elija nota de venta, boleta o factura.');
        }

        return DB::transaction(function () use ($cotizacion, $comprobante, $opciones, $usuario) {
            // Se bloquea para que dos clics (o dos usuarios) no conviertan la misma cotización dos veces
            $cotizacion = Cotizacion::lockForUpdate()->with(['detalles', 'cliente'])->findOrFail($cotizacion->id);

            if ($cotizacion->estado === 'convertida') {
                throw new \DomainException('Esta cotización ya fue convertida.');
            }
            if (in_array($cotizacion->estado, ['rechazada', 'anulada'], true)) {
                throw new \DomainException("No se puede convertir una cotización {$cotizacion->estado}.");
            }
            if ($cotizacion->esta_vencida) {
                throw new \DomainException('La cotización está vencida: renueve su validez antes de convertirla.');
            }

            $this->validarClienteParaComprobante($cotizacion->cliente, $comprobante);

            // Igual que en el POS: solo se vende desde una tienda activa (una cotización puede hacerse desde un depósito)
            $almacenVenta = \App\Models\Almacen::find($cotizacion->almacen_id);
            if (!$almacenVenta || $almacenVenta->tipo !== 'tienda' || $almacenVenta->estado !== 'activo') {
                throw new \DomainException('Solo se vende desde una tienda activa: edite la cotización y elija una tienda antes de convertirla.');
            }

            // Un vendedor no cobra: su venta queda pendiente para el cajero (misma regla que el POS)
            $condicion = $usuario->role?->nombre === 'Vendedor' ? 'pendiente_cobro' : ($opciones['condicion'] ?? 'contado');
            $pendiente = $condicion === 'pendiente_cobro';
            $metodo = $opciones['metodo_pago'] ?? null;
            if (!$pendiente && !in_array($metodo, ['efectivo', 'transferencia', 'yape', 'plin', 'mixto'], true)) {
                throw new \DomainException('Indique cómo se cobra (efectivo, transferencia, yape, plin) o deje la venta pendiente de cobro.');
            }

            $datosVenta = [
                'user_id'          => $usuario->id,
                'cliente_id'       => $cotizacion->cliente_id,
                'almacen_id'       => $cotizacion->almacen_id,
                'sucursal_id'      => $cotizacion->sucursal_id,
                'fecha'            => now()->toDateString(),
                'subtotal'         => (float) $cotizacion->total,
                'igv'              => 0,
                'total'            => (float) $cotizacion->total,
                'observaciones'    => 'Cotización ' . $cotizacion->codigo,
                'tipo_comprobante' => $comprobante,
                'condicion_pago'   => 'contado',
            ];
            if ($pendiente) {
                $datosVenta['estado_pago'] = 'pendiente';
            }

            $detalles = $cotizacion->detalles->map(fn ($d) => [
                'producto_id'     => $d->producto_id,
                'variante_id'     => $d->variante_id,
                'cantidad'        => $d->cantidad,
                'precio_unitario' => (float) $d->precio_con_igv,
                'incluye_igv'     => true,
            ])->all();

            $venta = $this->ventas->crearVenta($datosVenta, $detalles, $pendiente ? null : ['metodo_pago' => $metodo]);

            $cotizacion->update([
                'estado'                      => 'convertida',
                'venta_id'                    => $venta->id,
                'tipo_comprobante_convertido' => $comprobante,
                'convertida_en'               => now(),
                'convertida_por'              => $usuario->id,
            ]);

            return $venta;
        });
    }

    /** Misma regla que el POS: la factura exige un cliente con RUC de 11 dígitos. */
    public function validarClienteParaComprobante(Cliente $cliente, string $comprobante): void
    {
        if ($comprobante !== 'factura') {
            return;
        }
        if ($cliente->tipo_documento !== 'RUC') {
            throw new \DomainException('Para emitir factura, el cliente debe tener RUC (actualmente ' . ($cliente->tipo_documento ? "tiene {$cliente->tipo_documento}" : 'no tiene documento') . ').');
        }
        if (strlen($cliente->numero_documento) !== 11) {
            throw new \DomainException('Para emitir factura, el RUC debe tener exactamente 11 dígitos.');
        }
    }
}
