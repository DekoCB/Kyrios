<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ficha de personal. Solo Administración. */
class EmpleadoController extends Controller
{
    public function index(Request $request)
    {
        $empleados = Empleado::query()
            ->buscar($request->query('q'))
            ->when($request->filled('area'), fn ($q) => $q->where('area', $request->query('area')))
            ->when($request->query('estado', 'vigentes') === 'vigentes', fn ($q) => $q->where('estado', '!=', 'cesado'))
            ->when(in_array($request->query('estado'), array_keys(Empleado::ESTADOS), true), fn ($q) => $q->where('estado', $request->query('estado')))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(20)->withQueryString();

        $resumen = [
            'activos'         => Empleado::where('estado', 'activo')->count(),
            'licencia'        => Empleado::where('estado', 'licencia')->count(),
            'por_vencer'      => Empleado::where('estado', '!=', 'cesado')->whereBetween('fecha_fin_contrato', [today(), today()->addDays(30)])->count(),
            'cumpleanos_mes'  => Empleado::where('estado', '!=', 'cesado')->whereMonth('fecha_nacimiento', today()->month)->count(),
        ];

        return view('rrhh.empleados.index', compact('empleados', 'resumen'));
    }

    public function create()
    {
        return view('rrhh.empleados.form', ['empleado' => null, 'usuarios' => $this->usuariosLibres(null)]);
    }

    public function store(Request $request)
    {
        $empleado = Empleado::create($this->validar($request));

        return redirect()->route('rrhh.empleados.show', $empleado)->with('success', "Empleado {$empleado->codigo} registrado.");
    }

    public function show(Empleado $empleado)
    {
        $empleado->load(['usuario.role', 'documentos', 'solicitudes']);
        $ultimas = $empleado->asistencias()->orderByDesc('fecha')->limit(15)->get();

        $mes = [
            'tardanzas' => $empleado->asistencias()->whereMonth('fecha', today()->month)->whereYear('fecha', today()->year)->where('estado', 'tardanza')->count(),
            'faltas'    => $empleado->asistencias()->whereMonth('fecha', today()->month)->whereYear('fecha', today()->year)->where('estado', 'falta')->count(),
            'asistidos' => $empleado->asistencias()->whereMonth('fecha', today()->month)->whereYear('fecha', today()->year)->whereIn('estado', ['puntual', 'tardanza'])->count(),
        ];

        return view('rrhh.empleados.show', compact('empleado', 'ultimas', 'mes'));
    }

    public function edit(Empleado $empleado)
    {
        return view('rrhh.empleados.form', ['empleado' => $empleado, 'usuarios' => $this->usuariosLibres($empleado)]);
    }

    public function update(Request $request, Empleado $empleado)
    {
        $empleado->update($this->validar($request, $empleado));

        return redirect()->route('rrhh.empleados.show', $empleado)->with('success', 'Datos del empleado actualizados.');
    }

    /** El personal no se borra: se registra el cese y queda en el historial. */
    public function cesar(Request $request, Empleado $empleado)
    {
        $datos = $request->validate([
            'fecha_cese'  => 'required|date|after_or_equal:' . $empleado->fecha_ingreso->toDateString(),
            'motivo_cese' => 'required|string|max:255',
        ], ['fecha_cese.after_or_equal' => 'La fecha de cese no puede ser anterior al ingreso.']);

        $empleado->update($datos + ['estado' => 'cesado']);

        return redirect()->route('rrhh.empleados.show', $empleado)->with('success', 'Cese registrado.');
    }

    public function reactivar(Empleado $empleado)
    {
        $empleado->update(['estado' => 'activo', 'fecha_cese' => null, 'motivo_cese' => null]);

        return redirect()->route('rrhh.empleados.show', $empleado)->with('success', 'Empleado reactivado.');
    }

    private function usuariosLibres(?Empleado $actual)
    {
        return User::where('estado', 'activo')->whereHas('role', fn ($q) => $q->whereIn('nombre', \App\Models\Role::INTERNOS))
            ->where(fn ($q) => $q->whereDoesntHave('empleado')->when($actual?->user_id, fn ($w) => $w->orWhere('id', $actual->user_id)))
            ->orderBy('name')->get();
    }

    private function validar(Request $request, ?Empleado $empleado = null): array
    {
        $datos = $request->validate([
            'user_id'               => ['nullable', 'exists:users,id', Rule::unique('empleados', 'user_id')->ignore($empleado?->id)],
            'nombres'               => 'required|string|max:120',
            'apellidos'             => 'required|string|max:120',
            'dni'                   => ['required', 'regex:/^\d{8}$/', Rule::unique('empleados', 'dni')->ignore($empleado?->id)],
            'fecha_nacimiento'      => 'nullable|date|before:today',
            'sexo'                  => 'nullable|in:M,F',
            'estado_civil'          => ['nullable', Rule::in(array_keys(Empleado::ESTADOS_CIVILES))],
            'direccion'             => 'nullable|string|max:255',
            'telefono'              => 'nullable|string|max:20',
            'email_personal'        => 'nullable|email|max:150',
            'contacto_emergencia'   => 'nullable|string|max:120',
            'telefono_emergencia'   => 'nullable|string|max:20',
            'parentesco_emergencia' => 'nullable|string|max:40',
            'cargo'                 => 'required|string|max:120',
            'area'                  => 'nullable|string|max:80',
            'tipo_contrato'         => ['required', Rule::in(array_keys(Empleado::TIPOS_CONTRATO))],
            'fecha_ingreso'         => 'required|date|before_or_equal:today',
            'fecha_fin_contrato'    => 'nullable|date|after:fecha_ingreso',
            'sueldo_basico'         => 'nullable|numeric|min:0|max:999999',
            'hora_entrada'          => 'required|date_format:H:i',
            'hora_salida'           => 'required|date_format:H:i|after:hora_entrada',
            'estado'                => ['required', Rule::in(['activo', 'licencia'])],
            'sistema_pension'       => 'nullable|in:AFP,ONP',
            'afp_nombre'            => 'nullable|string|max:40',
            'cuspp'                 => 'nullable|string|max:20',
            'banco'                 => 'nullable|string|max:40',
            'numero_cuenta'         => 'nullable|string|max:40',
            'notas'                 => 'nullable|string|max:2000',
        ], [
            'dni.regex'                  => 'El DNI debe tener 8 dígitos.',
            'dni.unique'                 => 'Ya existe un empleado con ese DNI.',
            'user_id.unique'             => 'Ese usuario ya está asignado a otro empleado.',
            'hora_salida.after'          => 'La hora de salida debe ser posterior a la de entrada.',
            'fecha_fin_contrato.after'   => 'El fin de contrato debe ser posterior al ingreso.',
        ]);

        // Un empleado cesado no vuelve a «activo» por el formulario: se reactiva a propósito
        if ($empleado?->estado === 'cesado') {
            unset($datos['estado']);
        }
        $datos['hora_entrada'] .= ':00';
        $datos['hora_salida'] .= ':00';
        $datos['user_id'] = $datos['user_id'] ?? null;

        return $datos;
    }
}
