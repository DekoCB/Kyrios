<?php

namespace App\Services;

use App\Models\BonusRegla;
use App\Models\BonusLiquidacion;
use App\Models\DetalleVenta;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BonusService
{
    /**
     * Punto de entrada principal: calcula bonos fijos y verifica metas al crear una venta.
     */
    public function calcularParaVenta(Venta $venta): void
    {
        $vendedorId = $venta->user_id;
        if (!$vendedorId) return;

        foreach ($venta->detalles as $detalle) {
            $productoId  = $detalle->producto_id;
            $categoriaId = $detalle->producto?->categoria_id;

            $reglasFijas = $this->resolverReglasFijas($productoId, $categoriaId, $vendedorId);
            foreach ($reglasFijas as $regla) {
                $this->crearBonusFijo($regla, $detalle, $vendedorId);
            }

            $reglasMeta = $this->resolverReglasMeta($productoId, $categoriaId, $vendedorId);
            foreach ($reglasMeta as $regla) {
                $this->verificarMeta($regla, $vendedorId, $productoId, $categoriaId, $venta->created_at ?? now());
            }
        }
    }

    // ── Bonos Fijos ──────────────────────────────────────────────────────────

    private function crearBonusFijo(BonusRegla $regla, DetalleVenta $detalle, int $vendedorId): void
    {
        $qty    = (int) $detalle->cantidad;
        $monto  = $regla->tipo_calculo === 'monto_fijo'
            ? round((float) $regla->valor * $qty, 2)
            : round((float) $detalle->subtotal_con_igv * (float) $regla->valor / 100, 2);

        if ($monto <= 0) return;

        BonusLiquidacion::create([
            'user_id'          => $vendedorId,
            'bonus_regla_id'   => $regla->id,
            'tipo_origen'      => 'fijo',
            'detalle_venta_id' => $detalle->id,
            'tipo_calculo'     => $regla->tipo_calculo,
            'valor_configurado'=> $regla->valor,
            'monto_bonus'      => $monto,
            'estado'           => 'pendiente',
        ]);
    }

    // ── Bonos de Meta ─────────────────────────────────────────────────────────

    private function verificarMeta(BonusRegla $regla, int $vendedorId, int $productoId, ?int $categoriaId, $fecha): void
    {
        [$inicio, $fin] = $this->periodoActual($regla->meta_periodo, Carbon::parse($fecha));

        // Si ya existe un bonus de meta para este período, no duplicar
        $existe = BonusLiquidacion::where('user_id', $vendedorId)
            ->where('bonus_regla_id', $regla->id)
            ->where('periodo_inicio', $inicio->toDateString())
            ->exists();

        if ($existe) return;

        // Contar unidades vendidas en el período por el vendedor
        $unidades = $this->contarUnidadesPeriodo($vendedorId, $productoId, $categoriaId, $regla->tipo_aplicacion, $inicio, $fin);

        if ($unidades < $regla->meta_unidades) return;

        // Calcular monto del bono de meta
        $monto = $regla->tipo_calculo === 'monto_fijo'
            ? round((float) $regla->valor, 2)
            : round($this->totalVentasPeriodo($vendedorId, $productoId, $categoriaId, $regla->tipo_aplicacion, $inicio, $fin) * (float) $regla->valor / 100, 2);

        if ($monto <= 0) return;

        BonusLiquidacion::create([
            'user_id'          => $vendedorId,
            'bonus_regla_id'   => $regla->id,
            'tipo_origen'      => 'meta',
            'periodo_inicio'   => $inicio->toDateString(),
            'periodo_fin'      => $fin->toDateString(),
            'unidades_periodo' => $unidades,
            'tipo_calculo'     => $regla->tipo_calculo,
            'valor_configurado'=> $regla->valor,
            'monto_bonus'      => $monto,
            'estado'           => 'pendiente',
        ]);
    }

    private function contarUnidadesPeriodo(int $vendedorId, int $productoId, ?int $categoriaId, string $tipoAplicacion, Carbon $inicio, Carbon $fin): int
    {
        $query = DetalleVenta::join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.user_id', $vendedorId)
            ->whereBetween('ventas.created_at', [$inicio->startOfDay(), $fin->copy()->endOfDay()])
            ->whereIn('ventas.estado_pago', ['pagado', 'parcial', 'pendiente']);

        if (in_array($tipoAplicacion, ['producto', 'producto_usuario'], true)) {
            $query->where('detalle_ventas.producto_id', $productoId);
        } elseif ($tipoAplicacion === 'categoria' && $categoriaId) {
            $query->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
                  ->where('productos.categoria_id', $categoriaId);
        }

        return (int) $query->sum('detalle_ventas.cantidad');
    }

    private function totalVentasPeriodo(int $vendedorId, int $productoId, ?int $categoriaId, string $tipoAplicacion, Carbon $inicio, Carbon $fin): float
    {
        $query = DetalleVenta::join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.user_id', $vendedorId)
            ->whereBetween('ventas.created_at', [$inicio->startOfDay(), $fin->copy()->endOfDay()])
            ->whereIn('ventas.estado_pago', ['pagado', 'parcial', 'pendiente']);

        if (in_array($tipoAplicacion, ['producto', 'producto_usuario'], true)) {
            $query->where('detalle_ventas.producto_id', $productoId);
        } elseif ($tipoAplicacion === 'categoria' && $categoriaId) {
            $query->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
                  ->where('productos.categoria_id', $categoriaId);
        }

        return (float) $query->sum('detalle_ventas.subtotal_con_igv');
    }

    // ── Resolvers ─────────────────────────────────────────────────────────────

    private function resolverReglasFijas(int $productoId, ?int $categoriaId, int $vendedorId): \Illuminate\Support\Collection
    {
        return BonusRegla::where('activo', true)
            ->where('tipo_bonus', 'fijo')
            ->where(function ($q) use ($productoId, $categoriaId, $vendedorId) {
                $q->where(fn($q2) => $q2->where('tipo_aplicacion', 'producto')->where('producto_id', $productoId));
                if ($categoriaId) {
                    $q->orWhere(fn($q2) => $q2->where('tipo_aplicacion', 'categoria')->where('categoria_id', $categoriaId));
                }
                $q->orWhere(fn($q2) => $q2->where('tipo_aplicacion', 'producto_usuario')
                    ->where('producto_id', $productoId)->where('user_id', $vendedorId));
            })
            ->get();
    }

    private function resolverReglasMeta(int $productoId, ?int $categoriaId, int $vendedorId): \Illuminate\Support\Collection
    {
        return BonusRegla::where('activo', true)
            ->where('tipo_bonus', 'meta')
            ->where(function ($q) use ($productoId, $categoriaId, $vendedorId) {
                $q->where(fn($q2) => $q2->where('tipo_aplicacion', 'producto')->where('producto_id', $productoId));
                if ($categoriaId) {
                    $q->orWhere(fn($q2) => $q2->where('tipo_aplicacion', 'categoria')->where('categoria_id', $categoriaId));
                }
                $q->orWhere(fn($q2) => $q2->where('tipo_aplicacion', 'producto_usuario')
                    ->where('producto_id', $productoId)->where('user_id', $vendedorId));
            })
            ->get();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function periodoActual(string $periodo, ?Carbon $fecha = null): array
    {
        $fecha = $fecha ?? now();

        return match($periodo) {
            'semanal'    => [$fecha->copy()->startOfWeek(), $fecha->copy()->endOfWeek()],
            'quincenal'  => $fecha->day <= 15
                ? [$fecha->copy()->startOfMonth(), $fecha->copy()->startOfMonth()->addDays(14)]
                : [$fecha->copy()->startOfMonth()->addDays(15), $fecha->copy()->endOfMonth()],
            default      => [$fecha->copy()->startOfMonth(), $fecha->copy()->endOfMonth()], // mensual
        };
    }

    /**
     * Progreso de cada vendedor hacia cada regla de bono "por meta" activa, en el
     * período actual. A diferencia de verificarMeta() (que solo actúa al confirmar
     * una venta y recién crea el registro cuando ya se cruzó el umbral), esto se
     * puede consultar en cualquier momento — incluye a quienes todavía no llegan
     * a la meta, para un dashboard de "logrado / en progreso".
     */
    public function progresoMetas(): \Illuminate\Support\Collection
    {
        $reglas    = BonusRegla::where('activo', true)->where('tipo_bonus', 'meta')->get();
        $resultado = collect();

        foreach ($reglas as $regla) {
            [$inicio, $fin] = $this->periodoActual($regla->meta_periodo);

            // A quién aplica: producto_usuario -> solo ese vendedor; producto/categoria -> todos.
            $vendedores = $regla->tipo_aplicacion === 'producto_usuario'
                ? User::where('id', $regla->user_id)->get()
                : User::whereHas('role', fn($q) => $q->whereIn('nombre', ['Vendedor', 'Tienda', 'Cajero']))->get();

            foreach ($vendedores as $vendedor) {
                $unidades = $this->contarUnidadesPeriodo(
                    $vendedor->id, $regla->producto_id, $regla->categoria_id, $regla->tipo_aplicacion, $inicio, $fin
                );

                if ($unidades <= 0) continue;

                $liquidacion = BonusLiquidacion::where('user_id', $vendedor->id)
                    ->where('bonus_regla_id', $regla->id)
                    ->where('periodo_inicio', $inicio->toDateString())
                    ->first();

                $logrado = $unidades >= $regla->meta_unidades;

                $resultado->push([
                    'regla'          => $regla,
                    'vendedor'       => $vendedor,
                    'unidades'       => $unidades,
                    'meta'           => $regla->meta_unidades,
                    'periodo_inicio' => $inicio,
                    'periodo_fin'    => $fin,
                    'pct'            => $regla->meta_unidades > 0 ? min(100, round($unidades / $regla->meta_unidades * 100)) : 0,
                    'estado'         => match(true) {
                        !$logrado                                    => 'en_progreso',
                        $liquidacion && $liquidacion->estado === 'pagado' => 'pagado',
                        default                                       => 'logrado',
                    },
                    'liquidacion' => $liquidacion,
                ]);
            }
        }

        // Logrado/pagado primero, luego por % de avance descendente dentro de cada grupo.
        return $resultado->sortByDesc(fn($r) => ($r['estado'] !== 'en_progreso' ? 1000 : 0) + $r['pct'])->values();
    }

    /**
     * Resumen de bonos pendientes y pagados por vendedor.
     */
    public function resumenPorVendedor(): \Illuminate\Support\Collection
    {
        return BonusLiquidacion::with('vendedor')
            ->selectRaw('user_id, estado, SUM(monto_bonus) as total')
            ->groupBy('user_id', 'estado')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows) {
                $pendiente = $rows->firstWhere('estado', 'pendiente');
                $pagado    = $rows->firstWhere('estado', 'pagado');
                return [
                    'vendedor'  => $rows->first()->vendedor,
                    'pendiente' => (float) ($pendiente->total ?? 0),
                    'pagado'    => (float) ($pagado->total   ?? 0),
                ];
            });
    }
}
