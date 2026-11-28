<?php

namespace App\Services;

use App\Models\Compra;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centro de costos con dos caras:
 *  - Importaciones: cuánto cuesta realmente traer cada producto desde su sitio de origen (mercadería, flete, seguro,
 *    impuestos, transporte, percepción) y cuánto pesa cada gasto sobre el precio de fábrica.
 *  - Ventas: ingresos, costo de lo vendido y utilidad, con su estadística por mes, categoría, producto, vendedor, tienda y cliente.
 * Todo en soles y sin IGV, salvo donde se indica.
 */
class CentroCostosService
{
    /** Notas de crédito y débito ajustan una venta: no cuentan como una venta más. */
    private const COMPROBANTES_AJUSTE = ['nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'];

    /** @return array{0: string, 1: string} desde y hasta (Y-m-d) a partir de los filtros o el mes en curso */
    public static function periodo(?string $desde, ?string $hasta): array
    {
        try {
            $d = $desde ? Carbon::parse($desde) : today()->startOfMonth();
            $h = $hasta ? Carbon::parse($hasta) : today();
        } catch (\Throwable) {
            $d = today()->startOfMonth();
            $h = today();
        }
        if ($h->lt($d)) {
            [$d, $h] = [$h, $d];
        }

        return [$d->toDateString(), $h->toDateString()];
    }

    // ═══════════════════════════════ IMPORTACIONES ═══════════════════════════════

    /**
     * @param array{proveedor_id?: ?int, pais?: ?string} $f
     *
     * @return array<string, mixed>
     */
    public function importaciones(string $desde, string $hasta, array $f = []): array
    {
        $compras = Compra::with('proveedor:id,razon_social,nombre_comercial,pais,ciudad_origen')
            ->where('tipo_compra', 'importacion')->where('estado', '!=', 'anulado')->whereBetween('fecha', [$desde, $hasta])
            ->when(!empty($f['proveedor_id']), fn ($q) => $q->where('proveedor_id', $f['proveedor_id']))
            ->when(!empty($f['pais']), fn ($q) => $q->whereHas('proveedor', fn ($p) => $p->where('pais', $f['pais'])))
            ->orderBy('fecha')->orderBy('id')->get();

        // Unidades y costo puesto en almacén de cada compra: lo que de verdad costó cada línea una vez prorrateados los gastos
        $lineas = DB::table('detalle_compras as d')->join('compras as c', 'c.id', '=', 'd.compra_id')->whereIn('d.compra_id', $compras->pluck('id'))
            ->groupBy('d.compra_id')
            ->selectRaw('d.compra_id, SUM(d.cantidad) as unidades, SUM(d.cantidad * COALESCE(NULLIF(d.costo_unitario_final_pen, 0), d.precio_unitario * c.tipo_cambio)) as costo_pen')
            ->get()->keyBy('compra_id');

        $filas = $compras->map(function (Compra $c) use ($lineas) {
            $tc = (float) $c->tipo_cambio ?: 1.0;
            $fobUsd = $c->tipo_moneda === 'USD' ? (float) $c->subtotal : (float) $c->subtotal / $tc;
            $fobPen = $fobUsd * $tc;
            $partes = [
                'flete' => (float) $c->flete_usd * $tc,
                'seguro' => (float) $c->seguro_usd * $tc,
                'impuestos' => (float) $c->impuestos_usd * $tc + (float) $c->impuestos_pen,
                'transporte' => (float) $c->transporte_local_pen,
                'percepcion' => (float) $c->percepcion_pen,
                'otros' => (float) $c->otros_usd * $tc,
            ];
            $l = $lineas->get($c->id);
            $costoTotal = $l && (float) $l->costo_pen > 0 ? (float) $l->costo_pen : $fobPen + array_sum($partes);
            $unidades = (int) ($l->unidades ?? 0);
            $p = $c->proveedor;

            return [
                'compra' => $c, 'fecha' => $c->fecha, 'proveedor' => $p?->nombre_comercial ?: $p?->razon_social ?: '—', 'proveedor_id' => $c->proveedor_id,
                'pais' => $p?->pais ?: 'Sin país', 'ciudad' => $p?->ciudad_origen, 'tc' => $tc, 'fob_usd' => round($fobUsd, 2), 'fob_pen' => round($fobPen, 2),
                'partes' => array_map(fn ($v) => round($v, 2), $partes), 'gastos' => round(max(0, $costoTotal - $fobPen), 2),
                'costo_total' => round($costoTotal, 2), 'unidades' => $unidades, 'costo_unitario' => $unidades ? round($costoTotal / $unidades, 2) : null,
                'factor' => $fobPen > 0 ? round($costoTotal / $fobPen, 3) : null,
            ];
        });

        $fob = $filas->sum('fob_pen');
        $total = $filas->sum('costo_total');
        $conceptos = ['mercaderia' => $fob];
        foreach (['flete', 'seguro', 'impuestos', 'transporte', 'percepcion', 'otros'] as $k) {
            $conceptos[$k] = round($filas->sum(fn ($r) => $r['partes'][$k]), 2);
        }

        return [
            'filas' => $filas,
            'totales' => [
                'compras' => $filas->count(), 'unidades' => $filas->sum('unidades'), 'fob_usd' => round($filas->sum('fob_usd'), 2), 'fob_pen' => round($fob, 2),
                'gastos' => round($filas->sum('gastos'), 2), 'costo_total' => round($total, 2), 'factor' => $fob > 0 ? round($total / $fob, 3) : null,
                'costo_unitario' => $filas->sum('unidades') ? round($total / $filas->sum('unidades'), 2) : null,
                'tc_promedio' => $filas->sum('fob_usd') > 0 ? round($filas->sum('fob_pen') / $filas->sum('fob_usd'), 4) : null,
            ],
            'conceptos' => $conceptos,
            'por_proveedor' => $this->agrupar($filas, fn ($r) => $r['proveedor'] . '|' . $r['pais']),
            'por_pais' => $this->agrupar($filas, fn ($r) => $r['pais']),
            'por_mes' => $this->agrupar($filas, fn ($r) => $r['fecha']->format('Y-m'))->sortKeys(),
            'productos' => $this->sobrecostoPorProducto($compras->pluck('id')->all()),
        ];
    }

    private function agrupar(Collection $filas, \Closure $clave): Collection
    {
        return $filas->groupBy($clave)->map(function (Collection $g) {
            $fob = $g->sum('fob_pen');
            $total = $g->sum('costo_total');

            return [
                'etiqueta' => $g->first()['proveedor'] ?? '', 'pais' => $g->first()['pais'], 'compras' => $g->count(), 'unidades' => $g->sum('unidades'),
                'fob_pen' => round($fob, 2), 'gastos' => round($g->sum('gastos'), 2), 'costo_total' => round($total, 2), 'factor' => $fob > 0 ? round($total / $fob, 3) : null,
            ];
        })->sortByDesc('costo_total');
    }

    /** Los productos importados cuyo costo puesto en almacén más se aleja del precio de fábrica (sobrecosto por unidad). */
    private function sobrecostoPorProducto(array $compraIds): Collection
    {
        if (!$compraIds) {
            return collect();
        }

        return DB::table('detalle_compras as d')->join('compras as c', 'c.id', '=', 'd.compra_id')->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'd.variante_id')->whereIn('d.compra_id', $compraIds)->groupBy('d.producto_id', 'd.variante_id', 'p.nombre', 'p.nombre_kyrios', 'p.nombre_fabrica', 'v.codigo_kyrios')
            ->selectRaw('d.producto_id, d.variante_id, COALESCE(p.nombre_kyrios, p.nombre_fabrica, p.nombre) as nombre, v.codigo_kyrios as codigo, SUM(d.cantidad) as unidades,
                SUM(d.cantidad * d.precio_unitario * c.tipo_cambio) / SUM(d.cantidad) as fob_unitario,
                SUM(d.cantidad * COALESCE(NULLIF(d.costo_unitario_final_pen, 0), d.precio_unitario * c.tipo_cambio)) / SUM(d.cantidad) as costo_unitario')
            ->havingRaw('SUM(d.cantidad) > 0')->get()->map(function ($r) {
                $r->fob_unitario = round((float) $r->fob_unitario, 2);
                $r->costo_unitario = round((float) $r->costo_unitario, 2);
                $r->sobrecosto = round($r->costo_unitario - $r->fob_unitario, 2);
                $r->porcentaje = $r->fob_unitario > 0 ? round($r->sobrecosto / $r->fob_unitario * 100, 1) : null;

                return $r;
            })->sortByDesc('porcentaje')->values();
    }

    // ═══════════════════════════════ VENTAS Y UTILIDAD ═══════════════════════════════

    /** Línea de venta del período: ingreso sin IGV y costo (el guardado en la venta; en las antiguas, el costo promedio actual). */
    private function base(string $desde, string $hasta, array $f)
    {
        return DB::table('detalle_ventas as dv')->join('ventas as v', 'v.id', '=', 'dv.venta_id')->join('productos as p', 'p.id', '=', 'dv.producto_id')
            ->leftJoin('producto_variantes as pv', 'pv.id', '=', 'dv.variante_id')
            ->whereNotIn('v.estado_pago', ['anulado', 'cancelado', 'cotizacion'])->whereNotIn('v.tipo_comprobante', self::COMPROBANTES_AJUSTE)
            ->whereNull('v.deleted_at')->whereBetween('v.fecha', [$desde, $hasta])->where('dv.cantidad', '>', 0)
            ->when(!empty($f['almacen_id']), fn ($q) => $q->where('v.almacen_id', $f['almacen_id']))
            ->when(!empty($f['vendedor_id']), fn ($q) => $q->where('v.user_id', $f['vendedor_id']))
            ->when(!empty($f['categoria_id']), fn ($q) => $q->where('p.categoria_id', $f['categoria_id']));
    }

    private const INGRESO = 'SUM(dv.subtotal)';
    private const COSTO = 'SUM(dv.cantidad * COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio, 0))';

    private function metricas(object $r): array
    {
        $ingreso = (float) $r->ingreso;
        $costo = (float) $r->costo;
        $utilidad = $ingreso - $costo;

        return [
            'ingreso' => round($ingreso, 2), 'costo' => round($costo, 2), 'utilidad' => round($utilidad, 2),
            'margen' => $ingreso > 0 ? round($utilidad / $ingreso * 100, 1) : null,          // sobre la venta
            'recargo' => $costo > 0 ? round($utilidad / $costo * 100, 1) : null,             // sobre el costo
        ];
    }

    /**
     * @param array{almacen_id?: ?int, vendedor_id?: ?int, categoria_id?: ?int} $f
     *
     * @return array<string, mixed>
     */
    public function ventas(string $desde, string $hasta, array $f = []): array
    {
        $select = self::INGRESO . ' as ingreso, ' . self::COSTO . ' as costo';
        $tot = $this->base($desde, $hasta, $f)->selectRaw($select . ', COUNT(DISTINCT v.id) as ventas, SUM(dv.cantidad) as unidades,
            SUM(CASE WHEN COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio) IS NULL THEN 1 ELSE 0 END) as sin_costo,
            SUM(CASE WHEN dv.costo_unitario IS NULL THEN 1 ELSE 0 END) as estimadas, COUNT(*) as lineas')->first();
        $m = $this->metricas($tot);
        $nVentas = (int) $tot->ventas;

        $grupo = fn (string $clave, string $etiqueta, ?string $join = null, int $limite = 0, string $orden = 'utilidad') => $this->base($desde, $hasta, $f)
            ->when($join, fn ($q) => $q->leftJoin(...explode(',', $join)))
            ->groupBy(DB::raw($clave))->selectRaw("{$clave} as clave, MAX({$etiqueta}) as etiqueta, {$select}, SUM(dv.cantidad) as unidades")
            ->get()->map(fn ($r) => $this->metricas($r) + (array) $r)->sortByDesc($orden)->when($limite, fn ($c) => $c->take($limite))->values();

        // IGV: el de las ventas del período contra el de las compras registradas (referencial, no es la declaración)
        $igvVentas = (float) DB::table('ventas as v')->whereNotIn('v.estado_pago', ['anulado', 'cancelado', 'cotizacion'])->whereNotIn('v.tipo_comprobante', self::COMPROBANTES_AJUSTE)
            ->whereNull('v.deleted_at')->whereBetween('v.fecha', [$desde, $hasta])
            ->when(!empty($f['almacen_id']), fn ($q) => $q->where('v.almacen_id', $f['almacen_id']))->when(!empty($f['vendedor_id']), fn ($q) => $q->where('v.user_id', $f['vendedor_id']))->sum('v.igv');
        $compras = Compra::where('estado', '!=', 'anulado')->whereBetween('fecha', [$desde, $hasta]);
        $igvCompras = (float) (clone $compras)->sum(DB::raw('igv * CASE WHEN tipo_moneda = \'USD\' THEN tipo_cambio ELSE 1 END'));
        $comprasPen = (float) (clone $compras)->sum('total_pen');

        // Líneas vendidas por debajo de su costo
        $perdidas = $this->base($desde, $hasta, $f)->whereRaw('dv.subtotal < dv.cantidad * COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio, 0)')
            ->whereRaw('COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio) IS NOT NULL')
            ->orderByRaw('(dv.subtotal - dv.cantidad * COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio, 0)) asc')->limit(15)
            ->selectRaw('v.id as venta_id, v.fecha, COALESCE(p.nombre_kyrios, p.nombre_fabrica, p.nombre) as producto, dv.cantidad, dv.subtotal as ingreso,
                dv.cantidad * COALESCE(dv.costo_unitario, pv.costo_promedio, p.costo_promedio) as costo')->get();

        return [
            'totales' => $m + ['ventas' => $nVentas, 'unidades' => (int) $tot->unidades, 'ticket' => $nVentas ? round($m['ingreso'] / $nVentas, 2) : null,
                'lineas' => (int) $tot->lineas, 'sin_costo' => (int) $tot->sin_costo, 'estimadas' => (int) $tot->estimadas,
                'cobertura' => $tot->lineas ? round((1 - $tot->sin_costo / $tot->lineas) * 100, 1) : null],
            'por_mes' => $grupo("DATE_FORMAT(v.fecha, '%Y-%m')", "DATE_FORMAT(v.fecha, '%Y-%m')", null, 0, 'clave')->sortBy('clave')->values(),
            'por_categoria' => $grupo('p.categoria_id', "COALESCE(cat.nombre, 'Sin categoría')", 'categorias as cat,cat.id,=,p.categoria_id'),
            'por_producto' => $grupo('dv.producto_id', 'COALESCE(p.nombre_kyrios, p.nombre_fabrica, p.nombre)', null, 15),
            'por_vendedor' => $grupo('v.user_id', "COALESCE(u.name, 'Sin vendedor')", 'users as u,u.id,=,v.user_id'),
            'por_tienda' => $grupo('v.almacen_id', "COALESCE(al.nombre, 'Sin tienda')", 'almacenes as al,al.id,=,v.almacen_id'),
            'por_cliente' => $grupo('v.cliente_id', "COALESCE(cl.nombre, 'Sin cliente')", 'clientes as cl,cl.id,=,v.cliente_id', 10),
            'perdidas' => $perdidas,
            'contable' => [
                'igv_ventas' => round($igvVentas, 2), 'igv_compras' => round($igvCompras, 2), 'saldo_igv' => round($igvVentas - $igvCompras, 2), 'compras' => round($comprasPen, 2),
            ],
        ];
    }
}
