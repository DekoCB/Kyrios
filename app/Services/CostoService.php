<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Notifications\AvisoSistema;
use App\Support\EtiquetaProducto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Control de costos de compra y de margen de venta.
 *
 * El costo de un producto es el promedio ponderado de sus compras no anuladas, en soles y con los gastos de
 * importación prorrateados: SUM(cantidad × costo unitario final) / SUM(cantidad). El margen se mide sobre el costo
 * (precio sin IGV − costo) / costo, igual que en el módulo de precios, contra el margen mínimo de cada producto.
 */
class CostoService
{
    public const IGV = 1.18;

    /** Roles que reciben los avisos de margen: los mismos que ven costos y precios. */
    public const ROLES_AVISO = [\App\Models\Role::ADMINISTRACION, \App\Models\Role::LOGISTICA];

    /**
     * Líneas de compra (no anuladas) de un producto, en orden cronológico, con el costo unitario en soles.
     * Si la línea no tiene costo prorrateado (compras anteriores a esa función) se usa el precio convertido a soles.
     *
     * @return Collection<int, object{detalle_id:int, compra_id:int, codigo:?string, factura:?string, fecha:string, proveedor_id:?int, proveedor:?string, variante_id:?int, cantidad:int, costo:float}>
     */
    public function lineas(int $productoId, ?int $varianteId = null): Collection
    {
        $q = DB::table('detalle_compras as dc')
            ->join('compras as c', 'c.id', '=', 'dc.compra_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'c.proveedor_id')
            ->where('dc.producto_id', $productoId)
            ->where('c.estado', '!=', 'anulado')
            ->orderBy('c.fecha')->orderBy('dc.id')
            ->select('dc.id as detalle_id', 'dc.compra_id', 'c.codigo', 'c.numero_factura as factura', 'c.fecha', 'c.proveedor_id',
                'pr.razon_social as proveedor', 'dc.variante_id', 'dc.cantidad', 'dc.precio_unitario', 'dc.costo_unitario_final_pen',
                'c.tipo_moneda', 'c.tipo_cambio');
        if ($varianteId) {
            $q->where('dc.variante_id', $varianteId);
        }

        return $q->get()->map(function ($l) {
            $tc = (float) ($l->tipo_cambio ?: 1);
            $l->costo = (float) $l->costo_unitario_final_pen > 0
                ? (float) $l->costo_unitario_final_pen
                : (float) $l->precio_unitario * ($l->tipo_moneda === 'USD' ? $tc : 1);
            $l->cantidad = (int) $l->cantidad;

            return $l;
        });
    }

    /** Promedio ponderado de un conjunto de líneas; null si no hay unidades. */
    public function ponderado(Collection $lineas): ?float
    {
        $unidades = $lineas->sum('cantidad');

        return $unidades > 0 ? $lineas->sum(fn ($l) => $l->cantidad * $l->costo) / $unidades : null;
    }

    /**
     * Recalcula y guarda el costo promedio ponderado y el último costo del producto y, si se indica, de la variante.
     * Sin compras no toca nada (conserva el costo cargado a mano o por importación).
     */
    public function recalcular(int $productoId, ?int $varianteId = null): void
    {
        $lineas = $this->lineas($productoId);
        if ($lineas->isEmpty()) {
            return;
        }

        $this->guardar(Producto::query()->whereKey($productoId), $lineas, true);

        $variantes = $varianteId ? [$varianteId] : [];
        foreach ($variantes as $vid) {
            $propias = $lineas->where('variante_id', $vid);
            if ($propias->isNotEmpty()) {
                $this->guardar(ProductoVariante::query()->whereKey($vid), $propias, false);
            }
        }
    }

    /** Recalcula todas las variantes que tienen compras, y el producto. */
    public function recalcularProducto(int $productoId): void
    {
        $lineas = $this->lineas($productoId);
        if ($lineas->isEmpty()) {
            return;
        }
        $this->guardar(Producto::query()->whereKey($productoId), $lineas, true);
        foreach ($lineas->whereNotNull('variante_id')->groupBy('variante_id') as $vid => $propias) {
            $this->guardar(ProductoVariante::query()->whereKey($vid), $propias, false);
        }
    }

    private function guardar($consulta, Collection $lineas, bool $esProducto): void
    {
        $ultima = $lineas->last();
        $datos = ['costo_promedio' => round($this->ponderado($lineas) ?? $ultima->costo, 2), 'ultimo_costo_compra' => round($ultima->costo, 2)];
        if ($esProducto) {
            $datos['fecha_ultima_compra'] = $ultima->fecha;
        }
        $consulta->update($datos);
    }

    /**
     * Historial de compras del producto con el promedio ponderado acumulado y cuánto subió o bajó cada costo
     * respecto de la compra anterior (%). Del más reciente al más antiguo.
     */
    public function historial(int $productoId, ?int $varianteId = null): Collection
    {
        $acumUnidades = 0;
        $acumValor = 0.0;
        $anterior = null;

        return $this->lineas($productoId, $varianteId)->map(function ($l) use (&$acumUnidades, &$acumValor, &$anterior) {
            $acumUnidades += $l->cantidad;
            $acumValor += $l->cantidad * $l->costo;
            $l->promedio = $acumUnidades > 0 ? $acumValor / $acumUnidades : $l->costo;
            $l->variacion = $anterior && $anterior > 0 ? round(($l->costo - $anterior) / $anterior * 100, 1) : null;
            $anterior = $l->costo;

            return $l;
        })->reverse()->values();
    }

    /** Comparador de proveedores: del más barato (último costo) al más caro. */
    public function proveedores(int $productoId): Collection
    {
        return $this->lineas($productoId)->groupBy(fn ($l) => $l->proveedor_id ?? 0)->map(function (Collection $ls) {
            $ultima = $ls->last();

            return (object) [
                'proveedor' => $ultima->proveedor ?: 'Sin proveedor',
                'compras' => $ls->pluck('compra_id')->unique()->count(),
                'unidades' => $ls->sum('cantidad'),
                'ponderado' => $this->ponderado($ls),
                'ultimo' => $ultima->costo,
                'minimo' => $ls->min('costo'),
                'maximo' => $ls->max('costo'),
                'fecha' => $ultima->fecha,
            ];
        })->sortBy('ultimo')->values();
    }

    // ── Margen de venta ──────────────────────────────────────────────────────

    /** Precios de venta regular vigentes (generales, sin tienda) de los productos dados, listos para `precioDe()`. */
    public function precios(array $productoIds): Collection
    {
        return ProductoPrecio::where('tipo_precio', 'venta_regular')->where('activo', true)->whereNull('almacen_id')
            ->whereIn('producto_id', $productoIds)->where('precio', '>', 0)->orderByDesc('prioridad')->orderByDesc('id')->get();
    }

    /** Precio en soles sin IGV de la variante o, si no tiene uno propio, del producto; null si no hay precio. */
    public function precioDe(Collection $precios, int $productoId, ?int $varianteId = null): ?float
    {
        $delProducto = $precios->where('producto_id', $productoId);
        $p = ($varianteId ? $delProducto->firstWhere('variante_id', $varianteId) : null) ?? $delProducto->firstWhere('variante_id', null);

        return $p ? round($p->precio_soles / ($p->incluye_igv ? self::IGV : 1), 2) : null;
    }

    /** Precio de venta regular en soles sin IGV de un producto (o variante); null si no hay precio. */
    public function precioSinIgv(int $productoId, ?int $varianteId = null): ?float
    {
        return $this->precioDe($this->precios([$productoId]), $productoId, $varianteId);
    }

    /** Variantes activas (id, producto_id, costo_promedio) de los productos dados, para `evaluar()`. */
    public function variantesDe(array $productoIds): Collection
    {
        return ProductoVariante::whereIn('producto_id', $productoIds)->where('estado', 'activo')->get(['id', 'producto_id', 'codigo_kyrios', 'costo_promedio']);
    }

    /**
     * Evalúa un producto contra su margen mínimo. Los precios suelen estar cargados por variante, así que se mide el
     * margen de cada precio vigente (costo de la variante o, si no tiene compras propias, el del producto) y el
     * producto queda representado por el de peor margen.
     *
     * @return object{costo:?float, precio:?float, margen:?float, minimo:?float, estado:string, sugerido:?array, variante_id:?int, variante:?string, con_precio:int}
     */
    public function evaluar(Producto $p, Collection $precios, Collection $variantes, ?float $general): object
    {
        $minimo = self::minimoDe($p, $general);
        $costoProducto = (float) $p->costo_promedio > 0 ? (float) $p->costo_promedio : null;
        $activas = $variantes->where('producto_id', $p->id)->keyBy('id');

        $candidatos = collect();
        $vistos = [];
        foreach ($precios->where('producto_id', $p->id) as $pr) {
            $vid = $pr->variante_id;
            if ($vid !== null && !$activas->has($vid)) {
                continue;
            }
            if (isset($vistos[$vid ?? 0])) {
                continue;
            }
            $vistos[$vid ?? 0] = true;
            $costoVariante = $vid !== null && (float) $activas[$vid]->costo_promedio > 0 ? (float) $activas[$vid]->costo_promedio : null;
            $costo = $costoVariante ?? $costoProducto;
            $sin = round($pr->precio_soles / ($pr->incluye_igv ? self::IGV : 1), 2);
            $candidatos->push((object) ['variante_id' => $vid, 'costo' => $costo, 'precio' => $sin, 'margen' => self::margen($sin, $costo)]);
        }

        $peor = $candidatos->whereNotNull('margen')->sortBy('margen')->first();
        $costo = $peor?->costo ?? $costoProducto;
        $precio = $peor?->precio ?? $candidatos->first()?->precio;

        return (object) [
            'costo' => $costo,
            'precio' => $precio,
            'margen' => $peor?->margen,
            'minimo' => $minimo,
            'estado' => self::estado($costo, $precio, $minimo),
            'sugerido' => $minimo !== null ? self::precioSugerido($costo, $minimo) : null,
            'variante_id' => $peor?->variante_id,
            'variante' => $peor?->variante_id ? ($activas[$peor->variante_id]->codigo_kyrios ?? null) : null,
            'con_precio' => $candidatos->count(),
        ];
    }

    /** Margen sobre costo en %: (precio sin IGV − costo) / costo. Null si no hay costo o precio. */
    public static function margen(?float $precioSinIgv, ?float $costo): ?float
    {
        return $precioSinIgv !== null && $costo > 0 ? round(($precioSinIgv - $costo) / $costo * 100, 1) : null;
    }

    /** Precio sugerido (sin IGV y con IGV) para ganar el margen indicado sobre el costo. */
    public static function precioSugerido(?float $costo, ?float $margen): ?array
    {
        if (!$costo || $costo <= 0 || $margen === null) {
            return null;
        }
        $sin = round($costo * (1 + $margen / 100), 2);

        return ['sin_igv' => $sin, 'con_igv' => round($sin * self::IGV, 2)];
    }

    /** Estado del margen de un producto: sin_costo, sin_precio, sin_minimo, bajo u ok. */
    public static function estado(?float $costo, ?float $precioSinIgv, ?float $margenMinimo): string
    {
        if (!$costo || $costo <= 0) {
            return 'sin_costo';
        }
        if ($precioSinIgv === null) {
            return 'sin_precio';
        }
        if ($margenMinimo === null) {
            return 'sin_minimo';
        }

        return self::margen($precioSinIgv, $costo) < (float) $margenMinimo ? 'bajo' : 'ok';
    }

    /** Margen mínimo general (%); null si está desactivado. */
    public static function minimoGeneral(): ?float
    {
        $v = \App\Models\Ajuste::obtener('margen_minimo_general');

        return $v === null || $v === '' ? null : (float) $v;
    }

    public static function guardarMinimoGeneral(?float $valor): void
    {
        \App\Models\Ajuste::guardar('margen_minimo_general', $valor === null ? null : (string) $valor);
    }

    /** Margen mínimo que rige para un producto: el propio si lo tiene y, si no, el general. */
    public static function minimoDe(Producto $p, ?float $general = null): ?float
    {
        if ($p->margen_minimo !== null) {
            return (float) $p->margen_minimo;
        }

        return func_num_args() > 1 ? $general : self::minimoGeneral();
    }

    /** A partir de cuántos productos bajo el mínimo se envía un solo aviso resumen en vez de uno por producto. */
    public const MAX_AVISOS_INDIVIDUALES = 10;

    /**
     * Productos con costo cuyo margen real quedó por debajo de su margen mínimo (el propio o el general): avisa a
     * Administración y Logística —un aviso por producto, o uno solo de resumen si son muchos— y retira los avisos de
     * los que ya se corrigieron.
     *
     * @return array{avisos:int, bajo_minimo:int, retirados:int}
     */
    public function revisarMargenes(?AlertaService $alertas = null): array
    {
        $alertas ??= app(AlertaService::class);
        $general = self::minimoGeneral();
        $bajos = collect();

        Producto::where('estado', 'activo')->where('costo_promedio', '>', 0)
            ->when($general === null, fn ($q) => $q->whereNotNull('margen_minimo'))
            ->orderBy('id')
            ->chunkById(300, function ($productos) use (&$bajos, $general) {
                $ids = $productos->pluck('id')->all();
                $precios = $this->precios($ids);
                $variantes = $this->variantesDe($ids);
                foreach ($productos as $p) {
                    $e = $this->evaluar($p, $precios, $variantes, $general);
                    if ($e->estado === 'bajo') {
                        $bajos->push((object) ['producto' => $p, 'e' => $e]);
                    }
                }
            });

        $vigentes = [];
        $avisos = 0;
        $fmt = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        if ($bajos->count() > self::MAX_AVISOS_INDIVIDUALES) {
            $clave = "margen:resumen:{$bajos->count()}";
            $vigentes[] = $clave;
            $avisos += $alertas->avisar(self::ROLES_AVISO, new AvisoSistema(
                $clave,
                'Productos por debajo del margen mínimo',
                "{$bajos->count()} productos tienen un margen menor al mínimo. Revíselos y ajuste su precio.",
                route('precios.costos.index', ['estado' => 'bajo'], false),
                'alerta'
            ));
        } else {
            foreach ($bajos as $b) {
                $p = $b->producto;
                $clave = "margen:{$p->id}";
                $vigentes[] = $clave;
                $e = $b->e;
                $avisos += $alertas->avisar(self::ROLES_AVISO, new AvisoSistema(
                    $clave,
                    'Margen por debajo del mínimo',
                    EtiquetaProducto::nombre($p) . ($e->variante ? " · {$e->variante}" : '') . ": margen {$e->margen}% (mínimo {$fmt($e->minimo)}%). Precio sugerido: S/ " . number_format($e->sugerido['sin_igv'], 2) . ' sin IGV.',
                    route('precios.costos.show', $p->id, false),
                    'alerta'
                ));
            }
        }

        return ['avisos' => $avisos, 'bajo_minimo' => $bajos->count(), 'retirados' => $alertas->retirarObsoletos(['margen:'], $vigentes)];
    }

    /**
     * Ventas de los últimos días cuyo precio quedó por debajo del margen mínimo del producto (o por debajo del costo).
     * Usa el costo promedio actual, no el de la fecha de la venta.
     */
    public function ventasBajoMargen(int $dias = 30, int $limite = 100): Collection
    {
        $filas = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->join('productos as p', 'p.id', '=', 'dv.producto_id')
            ->leftJoin('producto_variantes as pv', 'pv.id', '=', 'dv.variante_id')
            ->whereNotIn('v.estado_pago', ['anulado', 'cancelado', 'cotizacion'])
            ->whereNotIn('v.tipo_comprobante', ['nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'])
            ->whereNull('v.deleted_at')
            ->where('v.fecha', '>=', today()->subDays($dias)->toDateString())
            ->where('dv.cantidad', '>', 0)
            ->whereRaw('COALESCE(pv.costo_promedio, p.costo_promedio, 0) > 0')
            ->orderByDesc('v.fecha')->orderByDesc('dv.id')
            ->select('v.id as venta_id', 'v.fecha', 'v.correlativo', 'p.id as producto_id', 'p.nombre', 'p.margen_minimo',
                'dv.cantidad', 'dv.subtotal', DB::raw('COALESCE(pv.costo_promedio, p.costo_promedio) as costo'))
            ->limit(3000)->get();

        $general = self::minimoGeneral();

        return $filas->map(function ($f) use ($general) {
            $f->margen_minimo = $f->margen_minimo !== null ? (float) $f->margen_minimo : $general;
            $f->precio = round((float) $f->subtotal / $f->cantidad, 2);
            $f->margen = self::margen($f->precio, (float) $f->costo);
            $f->bajo_costo = $f->precio < (float) $f->costo;

            return $f;
        })->filter(fn ($f) => $f->bajo_costo || ($f->margen_minimo !== null && $f->margen < (float) $f->margen_minimo))
            ->take($limite)->values();
    }
}
