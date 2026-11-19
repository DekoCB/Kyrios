<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Marketing\Campana;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Publicacion;
use App\Services\MarketingEstadisticas;
use App\Support\Marketing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Informes semanales y mensuales de redes y campañas, con comparación de períodos y exportación a PDF y Excel. */
class ReporteController extends Controller
{
    public function __construct(private MarketingEstadisticas $est) {}

    /** Todo lo que muestran la pantalla, el PDF y el Excel, calculado una sola vez. */
    private function datos(Request $request): array
    {
        [$desde, $hasta, $etiqueta, $tipo] = $this->est->periodo($request);
        $red = $request->filled('red') ? $request->red : null;

        $filas = $this->est->porCuenta($desde, $hasta, $red);
        $datos = [
            'desde'     => $desde,
            'hasta'     => $hasta,
            'etiqueta'  => $etiqueta,
            'tipo'      => $tipo,
            'red'       => $red,
            'filas'     => $filas,
            'totales'   => $this->est->totales($filas),
            'mejores'   => $this->est->mejoresPublicaciones($desde, $hasta, 10, $red),
            'estados'   => $this->est->publicacionesPorEstado($desde, $hasta),
            'campanas'  => $this->est->campanas($desde, $hasta, $red),
            'serie'     => $this->est->serieSeguidores($desde, $hasta, $red),
            'est'       => $this->est,
            'comparar'  => $request->boolean('comparar'),
            'anterior'  => null,
        ];

        if ($datos['comparar']) {
            [$d2, $h2] = $this->est->periodoAnterior($desde, $hasta, $tipo);
            $filas2 = $this->est->porCuenta($d2, $h2, $red);
            $datos['anterior'] = [
                'desde'    => $d2,
                'hasta'    => $h2,
                'etiqueta' => $d2->format('d/m/Y') . ' al ' . $h2->format('d/m/Y'),
                'filas'    => $filas2->keyBy(fn ($f) => $f['cuenta']->id),
                'totales'  => $this->est->totales($filas2),
            ];
        }

        return $datos;
    }

    public function index(Request $request)
    {
        return view('marketing.reportes.index', $this->datos($request));
    }

    public function pdf(Request $request)
    {
        $datos = $this->datos($request) + ['empresa' => Empresa::instancia()];

        return Pdf::loadView('marketing.reportes.pdf', $datos)->setPaper('a4', 'portrait')
            ->stream('reporte-marketing-' . $datos['desde']->format('Ymd') . '-' . $datos['hasta']->format('Ymd') . '.pdf');
    }

    public function excel(Request $request)
    {
        $d = $this->datos($request);
        $libro = new Spreadsheet();

        // Hoja 1: resultados por red y cuenta
        $hoja = $libro->getActiveSheet()->setTitle('Redes');
        $hoja->setCellValue('A1', 'Reporte de Marketing — ' . $d['etiqueta']);
        $cab = ['Red', 'Cuenta', 'Seguidores', 'Crecimiento', 'Alcance', 'Impresiones', 'Interacciones', 'Reproducciones', 'Clics', 'Publicaciones', 'Engagement %'];
        if ($d['comparar']) {
            $cab = array_merge($cab, ['Alcance (anterior)', 'Interacciones (anterior)', 'Variación interacciones %']);
        }
        $hoja->fromArray($cab, null, 'A3');
        $n = 4;
        foreach ($d['filas'] as $f) {
            $fila = [Marketing::nombreRed($f['cuenta']->red), $f['cuenta']->nombre, $f['seguidores'], $f['crecimiento'], $f['alcance'], $f['impresiones'],
                $f['interacciones'], $f['reproducciones'], $f['clics'], $f['publicaciones'], $f['engagement']];
            if ($d['comparar']) {
                $prev = $d['anterior']['filas']->get($f['cuenta']->id);
                $fila = array_merge($fila, [$prev['alcance'] ?? null, $prev['interacciones'] ?? null, $this->est->variacion($f['interacciones'], $prev['interacciones'] ?? null)]);
            }
            $hoja->fromArray($fila, null, 'A' . $n++);
        }
        $t = $d['totales'];
        $hoja->fromArray(['TOTAL', '', $t['seguidores'], $t['crecimiento'], $t['alcance'], $t['impresiones'], $t['interacciones'], $t['reproducciones'], $t['clics'], $t['publicaciones'], $t['engagement']], null, 'A' . $n);

        // Hoja 2: mejores publicaciones
        $hoja2 = $libro->createSheet()->setTitle('Mejores publicaciones');
        $hoja2->fromArray(['Fecha', 'Red', 'Título', 'Alcance', 'Impresiones', 'Interacciones', 'Reproducciones', 'Clics', 'Engagement %', 'Enlace'], null, 'A1');
        $n = 2;
        foreach ($d['mejores'] as $p) {
            $hoja2->fromArray([$p->publicada_en?->format('d/m/Y'), $p->red_nombre, $p->titulo, $p->alcance, $p->impresiones, $p->interacciones, $p->reproducciones, $p->clics, $p->engagement, $p->enlace], null, 'A' . $n++);
        }

        // Hoja 3: campañas del período
        $hoja3 = $libro->createSheet()->setTitle('Campañas');
        $hoja3->fromArray(['Campaña', 'Red', 'Estado', 'Moneda', 'Presupuesto', 'Inversión', 'Impresiones', 'Alcance', 'Clics', 'CPC', 'Conversiones', 'ROAS'], null, 'A1');
        $n = 2;
        foreach ($d['campanas'] as $c) {
            $hoja3->fromArray([$c->nombre, $c->red_nombre, Campana::ESTADOS[$c->estado] ?? $c->estado, $c->moneda, $c->presupuesto, $c->inversion, $c->impresiones, $c->alcance, $c->clics, $c->cpc, $c->conversiones, $c->roas], null, 'A' . $n++);
        }

        foreach ([[$hoja, 'A3:' . $hoja->getHighestColumn() . '3'], [$hoja2, 'A1:J1'], [$hoja3, 'A1:L1']] as [$h, $rango]) {
            $h->getStyle($rango)->getFont()->setBold(true);
            $h->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
            foreach (range('A', $h->getHighestColumn()) as $col) {
                $h->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $nombre = 'reporte-marketing-' . $d['desde']->format('Ymd') . '-' . $d['hasta']->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
