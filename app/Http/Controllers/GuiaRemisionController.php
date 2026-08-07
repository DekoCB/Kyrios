<?php

namespace App\Http\Controllers;

use App\Models\GuiaRemision;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Empresa;
use App\Services\GuiaRemisionService;
use App\Services\SunatGuiaService;
use App\Services\SunatService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class GuiaRemisionController extends Controller
{
    public function index(Request $request)
    {
        $query = GuiaRemision::with(['almacen', 'almacenDestino', 'cliente', 'proveedor', 'venta'])
            ->withCount('movimientos'); // para detectar origen=traslado

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('motivo')) {
            $query->where('motivo_traslado', $request->motivo);
        }
        if ($request->filled('origen')) {
            match ($request->origen) {
                'venta'    => $query->whereNotNull('venta_id'),
                'traslado' => $query->whereNull('venta_id')->whereHas('movimientos'),
                'manual'   => $query->whereNull('venta_id')->whereDoesntHave('movimientos'),
                default    => null,
            };
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_traslado', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_traslado', '<=', $request->fecha_hasta);
        }
        if ($request->filled('buscar')) {
            $query->where('numero_guia', 'like', '%' . $request->buscar . '%');
        }

        $guias = $query->latest()->paginate(20)->withQueryString();

        return view('guias-remision.index', compact('guias'));
    }

    public function create(Request $request)
    {
        $almacenes = Almacen::where('estado', 'activo')
            ->with(['sucursal.series' => fn($q) => $q->where('tipo_comprobante', '09')->where('activo', true)])
            ->orderBy('nombre')
            ->get();

        $guiaSeriesMap = $almacenes->mapWithKeys(function ($alm) {
            $serie = $alm->sucursal?->series->first();
            if (!$serie) return [$alm->id => null];
            return [$alm->id => [
                'serie_id' => $serie->id,
                'numero'   => $serie->serie . '-' . str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT),
            ]];
        })->filter();

        $almacenesAddressMap = $almacenes->mapWithKeys(fn($alm) => [
            $alm->id => [
                'direccion' => $alm->sucursal?->direccion,
                'ubigeo'    => $alm->sucursal?->ubigeo,
            ],
        ]);

        $ultimoConductor = GuiaRemision::whereNotNull('conductor_dni')
            ->whereNotNull('conductor_nombre')
            ->latest()->first();

        // Pre-fill cuando viene de un traslado ya registrado
        $fromTraslado = $request->query('from_traslado');
        $prefill = $fromTraslado ? [
            'numero_guia'        => $fromTraslado,
            'almacen_id'         => (int) $request->query('almacen_id', 0),
            'almacen_destino_id' => (int) $request->query('almacen_destino_id', 0),
            'tipo_destino'       => 'almacen',
            'motivo_traslado'    => 'TRASLADO_ENTRE_ALMACENES',
        ] : [];

        return view('guias-remision.create', compact(
            'almacenes', 'guiaSeriesMap', 'almacenesAddressMap', 'ultimoConductor',
            'fromTraslado', 'prefill'
        ));
    }

    public function store(Request $request)
    {
        $fromTraslado = $request->input('from_traslado');

        $rules = [
            'almacen_id'             => 'required|exists:almacenes,id',
            'tipo_destino'           => 'required|in:almacen,cliente,proveedor,libre',
            'almacen_destino_id'     => 'required_if:tipo_destino,almacen|nullable|exists:almacenes,id|different:almacen_id',
            'cliente_id'             => 'required_if:tipo_destino,cliente|nullable|exists:clientes,id',
            'proveedor_id'           => 'required_if:tipo_destino,proveedor|nullable|exists:proveedores,id',
            'numero_guia'            => 'nullable|string|max:50',
            'guia_serie_id'          => 'nullable|exists:series_comprobantes,id',
            'motivo_traslado'        => 'required|string|max:50',
            'modalidad'              => 'required|in:privado,publico',
            'fecha_traslado'         => 'required|date',
            'peso_total'             => 'nullable|numeric|min:0',
            'bultos'                 => 'nullable|integer|min:1',
            'direccion_partida'      => 'nullable|string|max:300',
            'ubigeo_partida'         => 'nullable|string|max:6',
            'direccion_llegada'      => 'nullable|string|max:300',
            'ubigeo_llegada'         => 'nullable|string|max:6',
            'transportista_tipo_doc' => 'nullable|string|max:10',
            'transportista_doc'      => 'nullable|string|max:15',
            'transportista_nombre'   => 'nullable|string|max:200',
            'conductor_dni'          => 'nullable|string|max:8',
            'conductor_nombre'       => 'nullable|string|max:200',
            'conductor_licencia'     => 'nullable|string|max:20',
            'placa_vehiculo'         => 'nullable|string|max:20',
        ];

        // Solo validar productos cuando NO viene de un traslado (en ese caso se auto-pueblan)
        if (!$fromTraslado) {
            $rules += [
                'productos'               => 'required|array|min:1',
                'productos.*.producto_id' => 'required|exists:productos,id',
                'productos.*.variante_id' => 'nullable|exists:producto_variantes,id',
                'productos.*.cantidad'    => 'nullable|integer|min:1',
                'productos.*.imei_ids'    => 'nullable|array',
                'productos.*.imei_ids.*'  => 'nullable|exists:imeis,id',
                'productos.*.descripcion' => 'nullable|string|max:300',
            ];
        }

        $validated = $request->validate($rules, [
            'almacen_destino_id.required_if' => 'Debe seleccionar el almacén destino.',
            'almacen_destino_id.different'   => 'El almacén destino debe ser diferente al origen.',
            'cliente_id.required_if'         => 'Debe seleccionar un cliente.',
            'proveedor_id.required_if'       => 'Debe seleccionar un proveedor.',
            'productos.required'             => 'Debe agregar al menos un producto.',
        ]);

        try {
            if ($fromTraslado) {
                $guia = app(GuiaRemisionService::class)->crearParaTraslado(
                    $fromTraslado,
                    array_merge($validated, ['user_id' => auth()->id()])
                );
            } else {
                $guia = app(GuiaRemisionService::class)->crear(
                    array_merge($validated, ['user_id' => auth()->id()])
                );
            }

            return redirect()
                ->route('guias-remision.show', $guia)
                ->with('success', "Guía {$guia->numero_guia} creada correctamente.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(GuiaRemision $guiasRemision)
    {
        $guiasRemision->load([
            'almacen', 'almacenDestino', 'cliente', 'proveedor',
            'detalles.producto.unidadMedida', 'detalles.variante',
        ]);

        $empresa = Empresa::first();

        return view('guias-remision.show', ['guia' => $guiasRemision, 'empresa' => $empresa]);
    }

    public function updateEstado(Request $request, GuiaRemision $guiasRemision)
    {
        $request->validate(['estado' => 'required|in:en_transito,entregada,anulada']);
        $guia = $guiasRemision;

        try {
            if ($request->estado === 'entregada') {
                app(GuiaRemisionService::class)->confirmarEntrega($guia, auth()->id());
            } elseif ($request->estado === 'anulada') {
                $request->validate(['motivo_anulacion' => 'required|string|max:300']);
                app(GuiaRemisionService::class)->anular($guia, $request->motivo_anulacion, auth()->id());
            } else {
                // en_transito: solo cambio de estado
                if ($guia->estado !== 'pendiente') {
                    throw new \Exception('Solo se puede marcar en tránsito una guía pendiente.');
                }
                $guia->update(['estado' => 'en_transito']);
            }

            return back()->with('success', 'Estado actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pdf(GuiaRemision $guiasRemision)
    {
        $guia = $guiasRemision;
        $guia->load([
            'almacen', 'almacenDestino', 'cliente', 'proveedor',
            'detalles.producto.unidadMedida', 'detalles.variante',
        ]);

        // Los IMEIs no se guardan en guia_remision_detalles: se registran en el
        // MovimientoInventario que se crea junto con la guía (mismo numero_guia),
        // vía TrasladoImei. Se recuperan aquí para poder listarlos en el PDF.
        $movimientosPorLinea = \App\Models\MovimientoInventario::where('numero_guia', $guia->numero_guia)
            ->with('imeisTrasladados.imei')
            ->get()
            ->keyBy(fn($m) => $m->producto_id . '-' . ($m->variante_id ?? ''));

        $empresa = Empresa::first();

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
            'chroot'               => public_path(),
        ])->loadView('pdf.guia-remision-modulo', compact('guia', 'empresa', 'movimientosPorLinea'));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('guia-' . $guia->numero_guia . '.pdf');
    }

    public function enviarSunat(GuiaRemision $guiasRemision)
    {
        $guia = $guiasRemision;

        if (!$guia->puedeEnviarSunat()) {
            return back()->with('error', 'Esta guía no puede enviarse a SUNAT en su estado actual.');
        }

        $result = app(SunatGuiaService::class)->enviar($guia);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function consultarSunat(GuiaRemision $guiasRemision)
    {
        $guia = $guiasRemision;

        if (!$guia->sunat_api_id) {
            return back()->with('error', 'Esta guía no ha sido enviada a SUNAT.');
        }

        $result = app(SunatGuiaService::class)->consultarEstado($guia);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function buscarDestinatario(Request $request)
    {
        $termino = trim($request->input('buscar', ''));
        $tipo    = $request->input('tipo', 'cliente');

        if (strlen($termino) < 2) return response()->json([]);

        if ($tipo === 'proveedor') {
            $results = Proveedor::where('ruc', 'like', "%{$termino}%")
                ->orWhere('razon_social', 'like', "%{$termino}%")
                ->limit(8)->get(['id', 'ruc', 'razon_social as nombre']);
        } else {
            $results = Cliente::where('numero_documento', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->limit(8)->get(['id', 'numero_documento as documento', 'nombre']);
        }

        return response()->json($results);
    }

    public function buscarRuc(string $ruc)
    {
        $result = app(SunatService::class)->consultarRuc($ruc);
        if (!($result['success'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'RUC no encontrado.'], 404);
        }

        $data           = $result['data'];
        $data['nombre'] = $data['razon_social'] ?? null;

        return response()->json($data);
    }
}
