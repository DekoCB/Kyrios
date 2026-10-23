<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\SunatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clientes a los que se vende: registro, ficha con su historial y exportación.
 */
class ClienteController extends Controller
{
    private function rol(): string
    {
        return auth()->user()->role->nombre ?? '';
    }

    private function consulta(Request $request)
    {
        return Cliente::query()
            ->buscar($request->q)
            ->when($request->tipo_documento === 'SIN', fn ($q) => $q->whereNull('numero_documento'))
            ->when($request->filled('tipo_documento') && $request->tipo_documento !== 'SIN', fn ($q) => $q->where('tipo_documento', $request->tipo_documento))
            ->when($request->filled('segmento'), fn ($q) => $q->where('segmento', $request->segmento))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado));
    }

    public function index(Request $request)
    {
        $clientes = $this->consulta($request)
            ->withCount(['ventas', 'cotizaciones'])
            ->conTotalComprado()
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'    => Cliente::count(),
            'activos'  => Cliente::where('estado', 'activo')->count(),
            'empresas' => Cliente::where('tipo_documento', 'RUC')->count(),
            'personas' => Cliente::whereIn('tipo_documento', ['DNI', 'CE'])->count(),
            'prospectos' => Cliente::prospectos()->count(),
        ];

        $canCreate = in_array($this->rol(), ['Administrador', 'Vendedor', 'Tienda', 'Cajero'], true);
        $canEdit   = $canCreate;
        $canDelete = $this->rol() === 'Administrador';

        return view('clientes.index', compact('clientes', 'stats', 'canCreate', 'canEdit', 'canDelete'));
    }

    public function create()
    {
        return view('clientes.create');
    }

    /** Reglas comunes de alta y edición; el formato del documento depende de su tipo. */
    private function reglas(?Cliente $cliente = null): array
    {
        return [
            // Sin documento = prospecto; se completa cuando el cliente lo entregue.
            'tipo_documento'   => 'nullable|required_with:numero_documento|in:DNI,RUC,CE',
            'numero_documento' => [
                'nullable', 'required_with:tipo_documento', 'string', 'max:11',
                Rule::unique('clientes', 'numero_documento')->ignore($cliente?->id),
                function (string $atributo, $valor, \Closure $fallar) {
                    $tipo = request('tipo_documento');
                    if ($tipo === 'DNI' && !preg_match('/^\d{8}$/', (string) $valor)) {
                        $fallar('El DNI debe tener 8 dígitos.');
                    } elseif ($tipo === 'RUC' && !preg_match('/^(10|15|16|17|20)\d{9}$/', (string) $valor)) {
                        $fallar('El RUC debe tener 11 dígitos y empezar con 10, 15, 16, 17 o 20.');
                    } elseif ($tipo === 'CE' && !preg_match('/^[A-Za-z0-9]{6,11}$/', (string) $valor)) {
                        $fallar('El carné de extranjería debe tener entre 6 y 11 caracteres.');
                    }
                },
            ],
            'nombre'       => 'required|string|max:255',
            'contacto'     => 'nullable|string|max:120',
            'direccion'    => 'nullable|string|max:255',
            'distrito'     => 'nullable|string|max:100',
            'provincia'    => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'ubigeo'       => 'nullable|string|max:6',
            'telefono'     => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'notas'        => 'nullable|string|max:2000',
            'estado'       => 'required|in:activo,inactivo',
            'segmento'         => 'nullable|string|max:20',
            'especialidad'     => 'nullable|string|max:150',
            'cargo'            => 'nullable|string|max:120',
            'empresa'          => 'nullable|string|max:190',
            'fecha_cumpleanos' => 'nullable|date|before:today',
            'telefono_fijo'    => 'nullable|string|max:30',
            'anexo'            => 'nullable|string|max:15',
            'email_empresa'    => 'nullable|email|max:255',
            'redes_sociales'   => 'nullable|string|max:1000',
            'comision'         => 'nullable|numeric|between:0,100',
            'preferencias'     => 'nullable|string|max:1000',
            'asesor'           => 'nullable|string|max:80',
            'origen'           => 'nullable|string|max:150',
        ];
    }

    private function mensajes(): array
    {
        return [
            'numero_documento.unique' => 'Este documento ya está registrado',
            'nombre.required'         => 'El nombre es obligatorio',
            'numero_documento.required_with' => 'Ingrese el número de documento o deje vacío el tipo para registrarlo como prospecto',
            'tipo_documento.required_with'   => 'Elija el tipo de documento',
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $cliente = Cliente::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'id'     => $cliente->id,
                'nombre' => $cliente->nombre,
            ]);
        }

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'Cliente registrado exitosamente');
    }

    public function show(Cliente $cliente)
    {
        $ventas = $cliente->ventas()->with('serieComprobante')->latest('fecha')->latest('id')->limit(15)->get();
        $cotizaciones = $cliente->cotizaciones()->latest('fecha')->latest('id')->limit(15)->get();

        $comprasValidas = $cliente->ventas()->whereNotIn('estado_pago', ['anulado', 'cancelado', 'cotizacion']);
        $resumen = [
            'ventas'          => (clone $comprasValidas)->count(),
            'total_comprado'  => (float) (clone $comprasValidas)->sum('total'),
            'ultima_compra'   => (clone $comprasValidas)->max('fecha'),
            'cotizaciones_vigentes' => $cliente->cotizaciones()->vigentes()->count(),
            'monto_cotizado'  => (float) $cliente->cotizaciones()->vigentes()->sum('total'),
        ];
        $resumen['ticket_promedio'] = $resumen['ventas'] > 0 ? $resumen['total_comprado'] / $resumen['ventas'] : 0.0;

        $canEdit = in_array($this->rol(), ['Administrador', 'Vendedor', 'Tienda', 'Cajero'], true);
        $canDelete = $this->rol() === 'Administrador';

        return view('clientes.show', compact('cliente', 'ventas', 'cotizaciones', 'resumen', 'canEdit', 'canDelete'));
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate($this->reglas($cliente), $this->mensajes());

        $cliente->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'id'     => $cliente->id,
                'nombre' => $cliente->nombre,
            ]);
        }

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'Cliente actualizado exitosamente');
    }

    /** Solo se elimina un cliente sin historial; con ventas o cotizaciones se recomienda inactivarlo. */
    public function destroy(Cliente $cliente)
    {
        if ($cliente->ventas()->withTrashed()->exists() || $cliente->cotizaciones()->exists()) {
            return redirect()
                ->route('clientes.show', $cliente)
                ->with('error', 'No se puede eliminar: tiene ventas o cotizaciones. Puede marcarlo como inactivo desde «Editar».');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado exitosamente');
    }

    /** Descarga la lista filtrada en CSV (abre directo en Excel). */
    public function exportar(Request $request): StreamedResponse
    {
        $clientes = $this->consulta($request)->conTotalComprado()->withCount('ventas')->orderBy('nombre');

        return response()->streamDownload(function () use ($clientes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel respeta las tildes
            fputcsv($out, ['Tipo doc.', 'Documento', 'Nombre / Razón social', 'Segmento', 'Contacto', 'Cargo', 'Empresa', 'Teléfono', 'Teléfono fijo', 'Correo', 'Correo empresa', 'Dirección', 'Distrito', 'Provincia', 'Departamento', 'Cumpleaños', 'Asesor', 'Estado', 'Verificación', 'Ventas', 'Total comprado (S/)', 'Notas'], ';');
            $clientes->chunk(500, function ($lote) use ($out) {
                foreach ($lote as $c) {
                    fputcsv($out, [
                        $c->tipo_documento ?? 'SIN DOC.', $c->numero_documento, $c->nombre, $c->segmento, $c->contacto, $c->cargo, $c->empresa,
                        $c->telefono, $c->telefono_fijo, $c->email, $c->email_empresa,
                        $c->direccion, $c->distrito, $c->provincia, $c->departamento, $c->fecha_cumpleanos?->format('d/m'), $c->asesor, $c->estado, $c->verificacion,
                        $c->ventas_count, number_format((float) $c->total_comprado, 2, '.', ''), $c->notas,
                    ], ';');
                }
            });
            fclose($out);
        }, 'clientes-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function consultarDocumento(Request $request)
    {
        $tipo   = strtoupper($request->input('tipo', 'DNI'));
        $numero = trim($request->input('numero', ''));
        $sunat  = app(SunatService::class);

        if ($tipo === 'RUC') {
            $result = $sunat->consultarRuc($numero);
            if (!$result['success']) {
                return response()->json(['error' => $result['message']], 422);
            }
            return response()->json([
                'nombre'       => $result['data']['razon_social']     ?? '',
                'razon_social' => $result['data']['razon_social']     ?? '',
                'direccion'    => $result['data']['direccion_fiscal']  ?? '',
                'distrito'     => $result['data']['distrito']          ?? '',
                'provincia'    => $result['data']['provincia']         ?? '',
                'departamento' => $result['data']['departamento']      ?? '',
                'ubigeo'       => $result['data']['ubigeo']             ?? '',
            ]);
        }

        $result = $sunat->consultarDni($numero);
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json([
            'nombre' => $result['data']['nombre'] ?? '',
        ]);
    }
}
