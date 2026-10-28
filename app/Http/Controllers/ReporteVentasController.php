<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteVentasController extends Controller
{
    public function index(Request $request)
    {
        [$desde, $hasta, $label] = $this->parsePeriodo($request);
        [$desdePrev, $hastaPrev]  = $this->periodoAnterior($desde, $hasta);

        $almacenId   = $request->input('almacen_id');
        $categoriaId = $request->input('categoria_id');

        $kpis        = $this->getKpis($desde, $hasta, $almacenId, $categoriaId);
        $kpisPrev    = $this->getKpis($desdePrev, $hastaPrev, $almacenId, $categoriaId);
        $tendencia   = $this->getTendencia($desde, $hasta, $almacenId, $categoriaId);
        $topProductos  = $this->getTopProductos($desde, $hasta, $almacenId, $categoriaId, 10);
        $porCategoria  = $this->getPorCategoria($desde, $hasta, $almacenId, $categoriaId);
        $tablaProductos = $this->getTablaProductos($desde, $hasta, $almacenId, $categoriaId);

        $almacenes  = Almacen::where('estado', 'activo')->orderBy('nombre')->get();
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        $periodo    = $request->input('periodo', '7dias');

        return view('reportes.ventas', compact(
            'desde', 'hasta', 'label', 'periodo',
            'desdePrev', 'hastaPrev',
            'kpis', 'kpisPrev',
            'tendencia', 'topProductos', 'porCategoria', 'tablaProductos',
            'almacenes', 'categorias', 'almacenId', 'categoriaId'
        ));
    }

    public function exportCsv(Request $request)
    {
        [$desde, $hasta, $label] = $this->parsePeriodo($request);
        $almacenId   = $request->input('almacen_id');
        $categoriaId = $request->input('categoria_id');
        $kpis        = $this->getKpis($desde, $hasta, $almacenId, $categoriaId);
        $tabla       = $this->getTablaProductos($desde, $hasta, $almacenId, $categoriaId);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=reporte-ventas-{$desde}-al-{$hasta}.csv",
        ];

        $callback = function () use ($tabla, $desde, $hasta, $label, $kpis) {
            $f = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($f, ["REPORTE DE VENTAS — MÁRGENES DE GANANCIA"]);
            fputcsv($f, ["Período: {$label} ({$desde} al {$hasta})"]);
            fputcsv($f, []);
            fputcsv($f, [
                "Total Ventas (S/)", "Total Costo (S/)", "Ganancia Bruta (S/)",
                "Margen Promedio %", "N° Ventas", "Unidades vendidas",
            ]);
            fputcsv($f, [
                number_format($kpis['total_ventas'], 2),
                number_format($kpis['total_costo'], 2),
                number_format($kpis['ganancia_bruta'], 2),
                number_format($kpis['margen_promedio'], 2) . '%',
                $kpis['num_ventas'],
                $kpis['unidades_vendidas'],
            ]);
            fputcsv($f, []);
            fputcsv($f, [
                'Código', 'Producto', 'Categoría',
                'Cant. Vendida', 'Precio Prom. (S/)', 'Costo Unit. (S/)',
                'Ganancia Unit. (S/)',
                'Total Vendido (S/)', 'Total Ganancia (S/)',
            ]);
            foreach ($tabla as $row) {
                fputcsv($f, [
                    $row->codigo,
                    $row->nombre,
                    $row->categoria,
                    $row->cantidad_vendida,
                    number_format($row->precio_promedio, 2),
                    number_format($row->costo_unitario, 2),
                    number_format($row->ganancia_unitaria, 2),
                    number_format($row->total_vendido, 2),
                    number_format($row->total_ganancia, 2),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        [$desde, $hasta, $label] = $this->parsePeriodo($request);
        $almacenId   = $request->input('almacen_id');
        $categoriaId = $request->input('categoria_id');

        $kpis           = $this->getKpis($desde, $hasta, $almacenId, $categoriaId);
        $tablaProductos = $this->getTablaProductos($desde, $hasta, $almacenId, $categoriaId);
        $empresa        = Empresa::instancia();

        $pdf = Pdf::loadView('reportes.pdf-ventas', compact(
            'desde', 'hasta', 'label', 'kpis', 'tablaProductos', 'empresa'
        ))->setPaper('a4', 'landscape');

        return $pdf->download("reporte-ventas-{$desde}-al-{$hasta}.pdf");
    }

    // ─────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────

    private function baseQuery(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId)
    {
        $q = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'dv.venta_id', '=', 'v.id')
            ->join('productos as p', 'dv.producto_id', '=', 'p.id')
            ->leftJoin('categorias as c', 'p.categoria_id', '=', 'c.id')
            ->leftJoin('producto_variantes as pv', 'dv.variante_id', '=', 'pv.id')
            ->leftJoin('colores as col', 'pv.color_id', '=', 'col.id')
            ->whereNotIn('v.estado_pago', ['anulado', 'cancelado', 'cotizacion'])
            // Las notas de crédito/débito ajustan una venta: no son ventas (la original ya queda anulada)
            ->whereNotIn('v.tipo_comprobante', ['nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'])
            ->whereNull('v.deleted_at')
            ->whereBetween('v.fecha', [$desde, $hasta]);

        if ($almacenId) {
            $q->where('v.almacen_id', $almacenId);
        }
        if ($categoriaId) {
            $q->where('p.categoria_id', $categoriaId);
        }

        return $q;
    }

    private function getKpis(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId): array
    {
        $row = $this->baseQuery($desde, $hasta, $almacenId, $categoriaId)
            ->selectRaw('
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))), 0) as total_ventas,
                COALESCE(SUM(dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio)), 0) as total_costo,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)) - dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio)), 0) as ganancia_bruta,
                COUNT(DISTINCT v.id) as num_ventas,
                COALESCE(SUM(dv.cantidad), 0) as unidades_vendidas
            ')
            ->first();

        $totalVentas  = (float) $row->total_ventas;
        $gananciaBruta = (float) $row->ganancia_bruta;
        $margenPromedio = $totalVentas > 0 ? ($gananciaBruta / $totalVentas * 100) : 0;

        return [
            'total_ventas'       => $totalVentas,
            'total_costo'        => (float) $row->total_costo,
            'ganancia_bruta'     => $gananciaBruta,
            'margen_promedio'    => round($margenPromedio, 2),
            'num_ventas'         => (int) $row->num_ventas,
            'unidades_vendidas'  => (int) $row->unidades_vendidas,
        ];
    }

    private function getTendencia(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId)
    {
        return $this->baseQuery($desde, $hasta, $almacenId, $categoriaId)
            ->selectRaw('
                DATE(v.fecha) as fecha,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))), 0) as total_ventas,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)) - dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio)), 0) as ganancia
            ')
            ->groupBy(DB::raw('DATE(v.fecha)'))
            ->orderBy(DB::raw('DATE(v.fecha)'))
            ->get();
    }

    private function getTopProductos(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId, int $limit = 10)
    {
        return $this->baseQuery($desde, $hasta, $almacenId, $categoriaId)
            ->selectRaw('
                p.nombre,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))), 0) as total_ventas,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)) - dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio)), 0) as ganancia,
                CASE WHEN SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))) > 0
                    THEN SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)) - dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio))
                         / SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))) * 100
                    ELSE 0 END as margen
            ')
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('ganancia')
            ->limit($limit)
            ->get();
    }

    private function getPorCategoria(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId)
    {
        return $this->baseQuery($desde, $hasta, $almacenId, $categoriaId)
            ->selectRaw('
                COALESCE(c.nombre, "Sin categoría") as categoria,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))), 0) as total_ventas,
                COALESCE(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)) - dv.cantidad * COALESCE(pv.costo_promedio, p.costo_promedio)), 0) as ganancia
            ')
            ->groupBy('p.categoria_id', 'c.nombre')
            ->orderByDesc('total_ventas')
            ->get();
    }

    private function getTablaProductos(string $desde, string $hasta, ?string $almacenId, ?string $categoriaId)
    {
        return $this->baseQuery($desde, $hasta, $almacenId, $categoriaId)
            ->selectRaw('
                p.id,
                COALESCE(pv.sku, p.codigo) as codigo,
                p.nombre,
                CASE
                    WHEN pv.id IS NOT NULL
                    THEN TRIM(CONCAT(COALESCE(col.nombre, ""), " ", COALESCE(pv.capacidad, "")))
                    ELSE NULL
                END as nombre_variante,
                COALESCE(c.nombre, "Sin categoría") as categoria,
                SUM(dv.cantidad) as cantidad_vendida,
                ROUND(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))) / NULLIF(SUM(dv.cantidad), 0), 2) as precio_promedio,
                ROUND(COALESCE(pv.costo_promedio, p.costo_promedio), 2) as costo_unitario,
                ROUND(
                    SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))) / NULLIF(SUM(dv.cantidad), 0)
                    - COALESCE(pv.costo_promedio, p.costo_promedio),
                2) as ganancia_unitaria,
                ROUND(SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2))), 2) as total_vendido,
                ROUND(
                    SUM(COALESCE(dv.subtotal_con_igv, ROUND(dv.subtotal * 1.18, 2)))
                    - SUM(dv.cantidad) * COALESCE(pv.costo_promedio, p.costo_promedio),
                2) as total_ganancia
            ')
            ->groupBy(
                'p.id', 'p.codigo', 'p.nombre', 'c.nombre',
                'p.costo_promedio', 'pv.id', 'pv.sku', 'pv.capacidad',
                'pv.costo_promedio', 'col.nombre'
            )
            ->orderBy('p.nombre')
            ->orderByDesc('total_ganancia')
            ->get();
    }

    // ─────────────────────────────────────────────
    // Helpers de período
    // ─────────────────────────────────────────────

    private function parsePeriodo(Request $request): array
    {
        $periodo = $request->input('periodo', '7dias');
        $today   = Carbon::today();

        return match ($periodo) {
            'hoy'          => [$today->toDateString(), $today->toDateString(), 'Hoy'],
            'ayer'         => [
                                $today->copy()->subDay()->toDateString(),
                                $today->copy()->subDay()->toDateString(),
                                'Ayer',
                              ],
            '7dias'        => [$today->copy()->subDays(6)->toDateString(), $today->toDateString(), 'Últimos 7 días'],
            'este_mes'     => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString(), 'Este mes'],
            'mes_pasado'   => [
                                $today->copy()->subMonth()->startOfMonth()->toDateString(),
                                $today->copy()->subMonth()->endOfMonth()->toDateString(),
                                'Mes pasado',
                              ],
            'este_anio'    => [$today->copy()->startOfYear()->toDateString(), $today->toDateString(), 'Este año'],
            'personalizado' => self::rangoOrdenado(
                                self::fechaOr($request->input('desde'), $today->copy()->subDays(6)->toDateString()),
                                self::fechaOr($request->input('hasta'), $today->toDateString()),
                                'Rango personalizado',
                               ),
            default        => [$today->copy()->subDays(6)->toDateString(), $today->toDateString(), 'Últimos 7 días'],
        };
    }


    /** Fecha Y-m-d válida o el valor por defecto: evita errores 500 y saltos de línea en el nombre del archivo. */
    private static function fechaOr(?string $valor, string $defecto): string
    {
        $d = \DateTime::createFromFormat('!Y-m-d', (string) $valor);

        return ($d && $d->format('Y-m-d') === $valor && $d->format('Y') >= '2000') ? $valor : $defecto;
    }

    private static function rangoOrdenado(string $desde, string $hasta, string $label): array
    {
        return $desde <= $hasta ? [$desde, $hasta, $label] : [$hasta, $desde, $label];
    }

    private function periodoAnterior(string $desde, string $hasta): array
    {
        $d    = Carbon::parse($desde);
        $h    = Carbon::parse($hasta);
        $dias = $d->diffInDays($h) + 1;

        return [
            $d->copy()->subDays($dias)->toDateString(),
            $d->copy()->subDay()->toDateString(),
        ];
    }

    /** Variación porcentual entre dos valores */
    public static function varPct(float $actual, float $anterior): float
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100 : 0;
        }
        return round(($actual - $anterior) / $anterior * 100, 1);
    }
}
