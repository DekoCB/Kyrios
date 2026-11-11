<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Services\CotizacionService;
use App\Services\HomologacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Plantilla de homologación de cotizaciones: se entrega al cliente, él describe lo que necesita,
 * se sube llena y el sistema propone los productos que mejor coinciden para armar la cotización.
 */
class HomologacionController extends Controller
{
    public function __construct(private HomologacionService $homologacion, private CotizacionService $cotizaciones)
    {
    }

    private function datosFormulario(): array
    {
        $user = auth()->user();
        $almacenes = Almacen::activos()->orderBy('nombre')->get(['id', 'nombre']);

        return [
            'clientes'  => Cliente::orderBy('nombre')->limit(500)->get(['id', 'nombre']),
            'almacenes' => $almacenes,
            'almacenId' => old('almacen_id', $user->almacen_id ?: ($almacenes->count() === 1 ? $almacenes->first()->id : null)),
        ];
    }

    public function index()
    {
        return view('cotizaciones.homologacion', $this->datosFormulario() + ['columnas' => HomologacionService::COLUMNAS]);
    }

    public function plantillaPdf()
    {
        $empresa = Empresa::first() ?? new Empresa(['razon_social' => config('app.name'), 'ruc' => '']);

        return Pdf::setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif', 'chroot' => public_path()])
            ->loadView('pdf.plantilla-homologacion', ['empresa' => $empresa, 'columnas' => HomologacionService::COLUMNAS])
            ->setPaper('A4', 'landscape')
            ->stream('plantilla-homologacion.pdf');
    }

    public function plantillaExcel()
    {
        return response()->download($this->homologacion->plantillaExcel(), 'plantilla-homologacion.xlsx')->deleteFileAfterSend(true);
    }

    /** Lee el archivo y muestra, fila por fila, las mejores coincidencias para que se elijan. */
    public function analizar(Request $request)
    {
        $datos = $request->validate([
            'archivo'    => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
            'cliente_id' => 'required|exists:clientes,id',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        try {
            $filas = $this->homologacion->leer($request->file('archivo')->getRealPath());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'No se pudo leer el archivo. Use la plantilla descargada del sistema.');
        }

        if (!$filas) {
            return back()->withInput()->with('error', 'El archivo no tiene filas con productos.');
        }

        session(['homologacion' => [
            'resultados' => array_map(fn ($fila) => ['fila' => $fila, 'candidatos' => $this->homologacion->coincidencias($fila)], $filas),
            'cliente_id' => (int) $datos['cliente_id'],
            'almacen_id' => (int) $datos['almacen_id'],
        ]]);

        return redirect()->route('cotizaciones.homologacion.resultado');
    }

    /** Pantalla de revisión: se lee de la sesión para poder volver a ella si algo falla al generar. */
    public function resultado()
    {
        $h = session('homologacion');
        if (!$h) {
            return redirect()->route('cotizaciones.homologacion')->with('error', 'Sube de nuevo la plantilla llena.');
        }

        return view('cotizaciones.homologacion-resultado', [
            'resultados' => $h['resultados'],
            'cliente'    => Cliente::findOrFail($h['cliente_id']),
            'almacen'    => Almacen::findOrFail($h['almacen_id']),
            'validez'    => config('kyrios.cotizacion.validez_dias', 7),
        ]);
    }

    /** Crea la cotización con las coincidencias elegidas. */
    public function generar(Request $request)
    {
        $datos = $request->validate([
            'cliente_id'            => 'required|exists:clientes,id',
            'almacen_id'            => 'required|exists:almacenes,id',
            'validez_dias'          => 'required|integer|min:1|max:365',
            'lineas'                => 'required|array|min:1|max:200',
            'lineas.*.elegido'      => 'nullable|string|max:40',
            'lineas.*.cantidad'     => 'nullable|integer|min:1|max:100000',
            'lineas.*.precio'       => 'nullable|numeric|min:0|max:9999999',
        ]);

        $detalles = [];
        foreach ($datos['lineas'] as $l) {
            if (!preg_match('/^(\d+):(\d*)$/', (string) ($l['elegido'] ?? ''), $m)) {
                continue; // «No incluir»
            }
            if (empty($l['cantidad']) || empty($l['precio']) || (float) $l['precio'] <= 0) {
                return back()->withInput()->with('error', 'Cada producto elegido necesita cantidad y precio mayores que cero.');
            }
            $detalles[] = ['producto_id' => (int) $m[1], 'variante_id' => $m[2] !== '' ? (int) $m[2] : null, 'cantidad' => (int) $l['cantidad'], 'precio_unitario' => (float) $l['precio']];
        }

        if (!$detalles) {
            return back()->withInput()->with('error', 'Elige al menos un producto para generar la cotización.');
        }

        try {
            $cotizacion = $this->cotizaciones->crear([
                'user_id'       => auth()->id(),
                'cliente_id'    => $datos['cliente_id'],
                'almacen_id'    => $datos['almacen_id'],
                'validez_dias'  => $datos['validez_dias'],
                'condiciones'   => config('kyrios.cotizacion.condiciones'),
                'observaciones' => 'Generada desde la plantilla de homologación.',
            ], $detalles);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        session()->forget('homologacion');

        return redirect()->route('cotizaciones.show', $cotizacion)->with('success', 'Cotización ' . $cotizacion->codigo . ' creada desde la homologación.');
    }
}
