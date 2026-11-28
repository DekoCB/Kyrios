<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use App\Services\CentroCostosService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Centro de costos: importaciones (costo real de traer cada producto desde su origen) y ventas (utilidad y estadística). */
class CentroCostosController extends Controller
{
    public function __construct(private CentroCostosService $servicio)
    {
    }

    public function index()
    {
        return redirect()->route('centro-costos.importaciones');
    }

    public function importaciones(Request $request)
    {
        [$desde, $hasta] = CentroCostosService::periodo($request->query('desde'), $request->query('hasta'));
        $filtros = ['proveedor_id' => $request->integer('proveedor_id') ?: null, 'pais' => $request->query('pais') ?: null];

        return view('finanzas.centro-costos.importaciones', [
            'd' => $this->servicio->importaciones($desde, $hasta, $filtros), 'desde' => $desde, 'hasta' => $hasta, 'filtros' => $filtros,
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial', 'pais']),
            'paises' => Proveedor::whereNotNull('pais')->distinct()->orderBy('pais')->pluck('pais'),
            'veVentas' => $this->veVentas(),
        ]);
    }

    public function ventas(Request $request)
    {
        abort_unless($this->veVentas(), 403);
        [$desde, $hasta] = CentroCostosService::periodo($request->query('desde'), $request->query('hasta'));
        $filtros = $this->filtrosVentas($request);

        return view('finanzas.centro-costos.ventas', [
            'd' => $this->servicio->ventas($desde, $hasta, $filtros), 'desde' => $desde, 'hasta' => $hasta, 'filtros' => $filtros,
            'tiendas' => Almacen::where('tipo', 'tienda')->orderBy('nombre')->get(['id', 'nombre']),
            'vendedores' => User::whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::ADMINISTRACION, Role::VENTAS]))->orderBy('name')->get(['id', 'name']),
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'veVentas' => true,
        ]);
    }

    public function exportarImportaciones(Request $request): StreamedResponse
    {
        [$desde, $hasta] = CentroCostosService::periodo($request->query('desde'), $request->query('hasta'));
        $d = $this->servicio->importaciones($desde, $hasta, ['proveedor_id' => $request->integer('proveedor_id') ?: null, 'pais' => $request->query('pais') ?: null]);

        return $this->csv("centro-costos-importaciones-{$desde}-al-{$hasta}.csv", function ($out) use ($d, $desde, $hasta) {
            fputcsv($out, ['CENTRO DE COSTOS · IMPORTACIONES'], ';');
            fputcsv($out, ["Período: {$desde} al {$hasta}"], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Fecha', 'Compra', 'Proveedor', 'País', 'Ciudad de origen', 'Tipo de cambio', 'Mercadería USD', 'Mercadería S/', 'Flete S/', 'Seguro S/', 'Impuestos S/', 'Transporte local S/',
                'Percepción S/', 'Otros S/', 'Gastos de importación S/', 'Costo total en almacén S/', 'Unidades', 'Costo por unidad S/', 'Factor de importación'], ';');
            foreach ($d['filas'] as $r) {
                fputcsv($out, [$r['fecha']->format('Y-m-d'), $r['compra']->codigo, $r['proveedor'], $r['pais'], $r['ciudad'], $r['tc'], $r['fob_usd'], $r['fob_pen'], $r['partes']['flete'], $r['partes']['seguro'],
                    $r['partes']['impuestos'], $r['partes']['transporte'], $r['partes']['percepcion'], $r['partes']['otros'], $r['gastos'], $r['costo_total'], $r['unidades'], $r['costo_unitario'], $r['factor']], ';');
            }
            fputcsv($out, [], ';');
            $t = $d['totales'];
            fputcsv($out, ['TOTAL', $t['compras'] . ' importaciones', '', '', '', $t['tc_promedio'], $t['fob_usd'], $t['fob_pen'], '', '', '', '', '', '', $t['gastos'], $t['costo_total'], $t['unidades'], $t['costo_unitario'], $t['factor']], ';');
        });
    }

    public function exportarVentas(Request $request): StreamedResponse
    {
        abort_unless($this->veVentas(), 403);
        [$desde, $hasta] = CentroCostosService::periodo($request->query('desde'), $request->query('hasta'));
        $d = $this->servicio->ventas($desde, $hasta, $this->filtrosVentas($request));

        return $this->csv("centro-costos-ventas-{$desde}-al-{$hasta}.csv", function ($out) use ($d, $desde, $hasta) {
            fputcsv($out, ['CENTRO DE COSTOS · VENTAS Y UTILIDAD (S/ sin IGV)'], ';');
            fputcsv($out, ["Período: {$desde} al {$hasta}"], ';');
            $t = $d['totales'];
            fputcsv($out, ['Ingresos', $t['ingreso'], 'Costo de ventas', $t['costo'], 'Utilidad bruta', $t['utilidad'], 'Margen %', $t['margen'], 'Recargo sobre costo %', $t['recargo']], ';');
            foreach (['por_mes' => 'Por mes', 'por_categoria' => 'Por categoría', 'por_producto' => 'Por producto (los 15 de mayor utilidad)', 'por_vendedor' => 'Por vendedor', 'por_tienda' => 'Por tienda', 'por_cliente' => 'Por cliente (los 10 de mayor utilidad)'] as $clave => $titulo) {
                fputcsv($out, [], ';');
                fputcsv($out, [mb_strtoupper($titulo)], ';');
                fputcsv($out, ['Concepto', 'Unidades', 'Ingresos', 'Costo', 'Utilidad', 'Margen %', 'Recargo %'], ';');
                foreach ($d[$clave] as $r) {
                    fputcsv($out, [$r['etiqueta'], $r['unidades'], $r['ingreso'], $r['costo'], $r['utilidad'], $r['margen'], $r['recargo']], ';');
                }
            }
        });
    }

    private function veVentas(): bool
    {
        return in_array(auth()->user()->role->nombre, [Role::ADMINISTRACION, Role::CONTADOR], true);
    }

    private function filtrosVentas(Request $r): array
    {
        return ['almacen_id' => $r->integer('almacen_id') ?: null, 'vendedor_id' => $r->integer('vendedor_id') ?: null, 'categoria_id' => $r->integer('categoria_id') ?: null];
    }

    private function csv(string $nombre, \Closure $escribir): StreamedResponse
    {
        return response()->streamDownload(function () use ($escribir) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $escribir($out);
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
