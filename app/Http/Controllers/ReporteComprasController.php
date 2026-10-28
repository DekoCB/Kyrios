<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteComprasController extends Controller
{
    public function index(Request $request)
    {
        [$desde, $hasta, $label] = $this->parsePeriodo($request);

        $proveedorId = $request->input('proveedor_id');
        $tipoCompra  = $request->input('tipo_compra');

        $kpis         = $this->getKpis($desde, $hasta, $proveedorId, $tipoCompra);
        $tendencia    = $this->getTendencia($desde, $hasta, $proveedorId, $tipoCompra);
        $porProveedor = $this->getPorProveedor($desde, $hasta, $tipoCompra);
        $tablaCompras = $this->getTablaCompras($desde, $hasta, $proveedorId, $tipoCompra);

        $proveedores = Proveedor::where('estado', 'activo')->orderBy('razon_social')->get();
        $periodo     = $request->input('periodo', '30dias');

        return view('reportes.compras', compact(
            'desde', 'hasta', 'label', 'periodo',
            'kpis', 'tendencia', 'porProveedor', 'tablaCompras',
            'proveedores', 'proveedorId', 'tipoCompra'
        ));
    }

    public function exportCsv(Request $request)
    {
        [$desde, $hasta, $label] = $this->parsePeriodo($request);
        $proveedorId = $request->input('proveedor_id');
        $tipoCompra  = $request->input('tipo_compra');
        $tabla       = $this->getTablaCompras($desde, $hasta, $proveedorId, $tipoCompra);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=reporte-compras-{$desde}-al-{$hasta}.csv",
        ];

        $callback = function () use ($tabla, $desde, $hasta, $label) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($f, ["REPORTE DE COMPRAS E IMPORTACIONES"]);
            fputcsv($f, ["Período: {$label} ({$desde} al {$hasta})"]);
            fputcsv($f, []);
            fputcsv($f, [
                'Fecha', 'Código', 'Proveedor', 'Nº Factura', 'Tipo',
                'Subtotal (S/)', 'Gastos Importación (S/)', 'Total (S/)', 'Estado Pago',
            ]);

            foreach ($tabla as $row) {
                fputcsv($f, [
                    $row->fecha,
                    $row->codigo,
                    $row->razon_social,
                    $row->numero_factura,
                    $row->tipo_compra === 'importacion' ? 'Importación' : 'Local',
                    number_format($row->total_pen, 2),
                    number_format($row->gastos_importacion_pen, 2),
                    number_format($row->total_con_gastos, 2),
                    $row->estado_pago ?? '—',
                ]);
            }

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────


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

    private function parsePeriodo(Request $request): array
    {
        $periodo = $request->input('periodo', '30dias');
        $hoy     = Carbon::today();

        return match ($periodo) {
            '7dias'     => [$hoy->copy()->subDays(6)->toDateString(),  $hoy->toDateString(), 'Últimos 7 días'],
            '30dias'    => [$hoy->copy()->subDays(29)->toDateString(), $hoy->toDateString(), 'Últimos 30 días'],
            'mes'       => [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString(), 'Este mes'],
            'trimestre' => [Carbon::now()->startOfQuarter()->toDateString(), Carbon::now()->endOfQuarter()->toDateString(), 'Este trimestre'],
            'anio'      => [Carbon::now()->startOfYear()->toDateString(), $hoy->toDateString(), 'Este año'],
            'custom'    => self::rangoOrdenado(
                self::fechaOr($request->input('fecha_desde'), $hoy->copy()->subDays(29)->toDateString()),
                self::fechaOr($request->input('fecha_hasta'), $hoy->toDateString()),
                'Período personalizado',
            ),
            default     => [$hoy->copy()->subDays(29)->toDateString(), $hoy->toDateString(), 'Últimos 30 días'],
        };
    }

    private function getKpis(string $desde, string $hasta, ?string $proveedorId, ?string $tipoCompra): array
    {
        $base = Compra::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulado')
            ->when($proveedorId, fn($q) => $q->where('proveedor_id', $proveedorId))
            ->when($tipoCompra,  fn($q) => $q->where('tipo_compra', $tipoCompra));

        $totalPen = (clone $base)->sum('total_pen');

        // Gastos de importación en PEN
        $gastosImportacion = (clone $base)
            ->where('tipo_compra', 'importacion')
            ->selectRaw('
                SUM((flete_usd + seguro_usd + otros_usd + impuestos_usd) * tipo_cambio
                    + impuestos_pen + percepcion_pen + transporte_local_pen) as gastos
            ')
            ->value('gastos') ?? 0;

        return [
            'total_invertido'       => $totalPen,
            'num_compras'           => (clone $base)->count(),
            'num_importaciones'     => (clone $base)->where('tipo_compra', 'importacion')->count(),
            'num_locales'           => (clone $base)->where('tipo_compra', 'local')->count(),
            'gastos_importacion'    => $gastosImportacion,
            'promedio_por_compra'   => (clone $base)->count() > 0 ? $totalPen / (clone $base)->count() : 0,
            'proveedores_distintos' => (clone $base)->distinct('proveedor_id')->count('proveedor_id'),
        ];
    }

    private function getTendencia(string $desde, string $hasta, ?string $proveedorId, ?string $tipoCompra): array
    {
        return Compra::selectRaw("DATE(fecha) as dia, COUNT(*) as num_compras, SUM(total_pen) as total")
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulado')
            ->when($proveedorId, fn($q) => $q->where('proveedor_id', $proveedorId))
            ->when($tipoCompra,  fn($q) => $q->where('tipo_compra', $tipoCompra))
            ->groupByRaw('DATE(fecha)')
            ->orderBy('dia')
            ->get()
            ->toArray();
    }

    private function getPorProveedor(string $desde, string $hasta, ?string $tipoCompra): \Illuminate\Support\Collection
    {
        return Compra::join('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->selectRaw('proveedores.razon_social, COUNT(*) as num_compras, SUM(compras.total_pen) as total')
            ->whereBetween('compras.fecha', [$desde, $hasta])
            ->where('compras.estado', '!=', 'anulado')
            ->when($tipoCompra, fn($q) => $q->where('compras.tipo_compra', $tipoCompra))
            ->groupBy('proveedores.id', 'proveedores.razon_social')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    private function getTablaCompras(string $desde, string $hasta, ?string $proveedorId, ?string $tipoCompra): \Illuminate\Support\Collection
    {
        return Compra::join('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->leftJoin('cuentas_por_pagar', 'compras.id', '=', 'cuentas_por_pagar.compra_id')
            ->selectRaw('
                compras.id,
                compras.fecha,
                compras.codigo,
                compras.numero_factura,
                compras.tipo_compra,
                compras.total_pen,
                compras.tipo_cambio,
                COALESCE(
                    (compras.flete_usd + compras.seguro_usd + compras.otros_usd + compras.impuestos_usd) * compras.tipo_cambio
                    + compras.impuestos_pen + compras.percepcion_pen + compras.transporte_local_pen,
                    0
                ) as gastos_importacion_pen,
                COALESCE(
                    compras.total_pen
                    + (compras.flete_usd + compras.seguro_usd + compras.otros_usd + compras.impuestos_usd) * compras.tipo_cambio
                    + compras.impuestos_pen + compras.percepcion_pen + compras.transporte_local_pen,
                    compras.total_pen
                ) as total_con_gastos,
                proveedores.razon_social,
                cuentas_por_pagar.estado as estado_pago
            ')
            ->whereBetween('compras.fecha', [$desde, $hasta])
            ->where('compras.estado', '!=', 'anulado')
            ->when($proveedorId, fn($q) => $q->where('compras.proveedor_id', $proveedorId))
            ->when($tipoCompra,  fn($q) => $q->where('compras.tipo_compra', $tipoCompra))
            ->orderByDesc('compras.fecha')
            ->get();
    }
}
