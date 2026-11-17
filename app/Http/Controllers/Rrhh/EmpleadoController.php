<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $this->guardarFoto($request, $empleado);

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
        $this->guardarFoto($request, $empleado);

        return redirect()->route('rrhh.empleados.show', $empleado)->with('success', 'Datos del empleado actualizados.');
    }

    /** Foto del colaborador (disco privado: solo la ve quien administra el personal). */
    public function foto(Empleado $empleado)
    {
        abort_unless($empleado->foto && Storage::disk('local')->exists($empleado->foto), 404);

        return Storage::disk('local')->response($empleado->foto, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Ficha del colaborador en PDF con el formato oficial de ingreso (datos + declaraciones juradas para firmar). */
    public function fichaPdf(Empleado $empleado)
    {
        $empleado->load('usuario');
        $empresa = \App\Models\Empresa::first() ?? new \App\Models\Empresa();
        $fotoDataUri = null;
        if ($empleado->foto && Storage::disk('local')->exists($empleado->foto)) {
            $fotoDataUri = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($empleado->foto));
        }

        return Pdf::setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif', 'chroot' => public_path()])
            ->loadView('pdf.ficha-colaborador', compact('empleado', 'empresa', 'fotoDataUri'))
            ->setPaper('A4', 'portrait')
            ->stream('ficha-' . $empleado->codigo . '.pdf');
    }

    /** Guarda la foto reducida a un JPG de hasta 600 px. */
    private function guardarFoto(Request $request, Empleado $empleado): void
    {
        $archivo = $request->file('foto');
        if (!$archivo || !$archivo->isValid()) {
            return;
        }

        $jpg = null;
        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath())))) {
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($archivo->getRealPath());
                $giro = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
                if ($giro && ($rotada = imagerotate($img, $giro, 0))) {
                    $img = $rotada;
                }
            }
            $factor = min(1, 600 / max(imagesx($img), imagesy($img)));
            if ($factor < 1) {
                $img = imagescale($img, (int) round(imagesx($img) * $factor), (int) round(imagesy($img) * $factor));
            }
            ob_start();
            imagejpeg($img, null, 85);
            $jpg = ob_get_clean() ?: null;
        }

        $ruta = "rrhh/fotos/{$empleado->id}.jpg";
        Storage::disk('local')->put($ruta, $jpg ?? file_get_contents($archivo->getRealPath()));
        $empleado->update(['foto' => $ruta]);
    }

    /**
     * Normaliza una lista de filas del formulario: recorta los textos y descarta las filas vacías.
     *
     * @param string[] $campos campos que se conservan
     * @param string[] $ignorar campos que no cuentan para decidir si la fila está vacía (p. ej. un nivel ya escrito de antemano)
     */
    private function filas($filas, array $campos, array $ignorar = []): ?array
    {
        $limpias = collect(is_array($filas) ? $filas : [])
            ->map(fn ($f) => collect($campos)->mapWithKeys(fn ($c) => [$c => trim((string) (is_array($f) ? ($f[$c] ?? '') : ''))])->all())
            ->filter(fn ($f) => collect($f)->except($ignorar)->filter(fn ($v) => $v !== '')->isNotEmpty())
            ->values()->all();

        return $limpias ?: null;
    }

    /** Reglas de una lista de filas: «lista.*.campo» con su largo máximo. */
    private function reglasFilas(string $lista, array $campos, int $maxFilas = 10): array
    {
        $reglas = [$lista => "nullable|array|max:{$maxFilas}"];
        foreach ($campos as $campo => $largo) {
            $reglas["{$lista}.*.{$campo}"] = "nullable|string|max:{$largo}";
        }

        return $reglas;
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

            // Ficha del colaborador
            'nacionalidad'          => 'nullable|string|max:40',
            'grado_instruccion'     => 'nullable|string|max:40',
            'lugar_nacimiento'      => 'nullable|string|max:120',
            'tipo_sangre'           => 'nullable|string|max:5',
            'domicilio_alterno'     => 'nullable|string|max:255',
            'referencia_domiciliaria' => 'nullable|string|max:255',
            'jefe_contrata'         => 'nullable|string|max:120',
            'foto'                  => 'nullable|image|max:8192',
            'codigo_huellero'       => ['nullable', 'string', 'max:20', Rule::unique('empleados', 'codigo_huellero')->ignore($empleado?->id)],
            'centro_costos'         => 'nullable|string|max:60',
            'periodo_prueba'        => 'nullable|string|max:60',
            'tipo_remuneracion'     => ['nullable', Rule::in(array_keys(Empleado::TIPOS_REMUNERACION))],
            'modalidad_pago'        => ['nullable', Rule::in(array_keys(Empleado::MODALIDADES_PAGO))],
            'modalidad_pago_otro'   => 'nullable|string|max:60',
            'local_trabajo'         => ['nullable', Rule::in(array_keys(Empleado::LOCALES))],
            'local_otro'            => 'nullable|string|max:60',
            'calificacion'          => ['nullable', Rule::in(array_keys(Empleado::CALIFICACIONES))],
            'puesto_solicitado'     => 'nullable|string|max:120',
            'fecha_disponibilidad'  => 'nullable|date',
            'sueldo_minimo'         => 'nullable|numeric|min:0|max:999999',
            'viajaria_provincias'   => 'nullable|boolean',
            'trabajo_antes_kyrios'  => 'nullable|boolean',
            'conocidos_kyrios'      => 'nullable|string|max:255',
            'familiares_kyrios'     => 'nullable|string|max:255',
            'profesion'             => 'nullable|string|max:160',
            'profesion_condicion'   => ['nullable', Rule::in(array_keys(Empleado::CONDICIONES_PROFESION))],
            'posee_cuenta_sueldo'   => 'nullable|boolean',
            'moneda_pago'           => 'nullable|in:PEN,USD',
            'pension_situacion'     => ['nullable', Rule::in(array_keys(Empleado::SITUACIONES_PENSION))],
            'pension_regimen'       => 'nullable|string|max:40',
            'vida_ley_extrabajador' => 'nullable|boolean',
            'sin_enfermedades'      => 'nullable|boolean',
            'declaraciones'         => 'nullable|array',
            'declaraciones.*'       => 'nullable|date',
            'enfermedades'          => 'nullable|array|max:10',
            'enfermedades.*'        => 'nullable|string|max:160',
            'otros_estudios'        => 'nullable|array|max:10',
            'otros_estudios.*'      => 'nullable|string|max:200',
            'vida_ley'              => 'nullable|array',
            'vida_ley.compania'     => 'nullable|string|max:120',
            'vida_ley.poliza'       => 'nullable|string|max:60',
            'vida_ley.vigencia'     => 'nullable|string|max:60',
            'vida_ley.remuneracion' => 'nullable|string|max:40',
        ] + $this->reglasFilas('educacion', ['nivel' => 20, 'centro' => 160, 'inicio' => 20, 'fin' => 20], 3)
          + $this->reglasFilas('idiomas', ['idioma' => 60, 'habla' => 12, 'lee' => 12, 'escribe' => 12])
          + $this->reglasFilas('software', ['nombre' => 80, 'nivel' => 12])
          + $this->reglasFilas('experiencia', ['empresa' => 120, 'telefono' => 30, 'cargo' => 120, 'jefe' => 120, 'ingreso' => 20, 'salida' => 20, 'remuneracion' => 30, 'funciones' => 600, 'motivo_cese' => 200, 'consultar' => 3], 3)
          + $this->reglasFilas('dependientes', ['nombres' => 140, 'parentesco' => 40, 'dni' => 12, 'nacimiento' => 20, 'centro' => 120, 'ocupacion' => 80])
          + $this->reglasFilas('familiares', ['nombres' => 140, 'parentesco' => 40, 'dni' => 12, 'nacimiento' => 20, 'telefono' => 30, 'centro' => 120, 'ocupacion' => 80, 'direccion' => 200, 'otros' => 120], 12)
          + $this->reglasFilas('referencias', ['nombres' => 140, 'empresa' => 120, 'cargo' => 80, 'telefono' => 30, 'tiempo' => 40])
          + $this->reglasFilas('vida_ley.beneficiarios', ['nombres' => 140, 'parentesco' => 40], 8), [
            'dni.regex'                  => 'El DNI debe tener 8 dígitos.',
            'dni.unique'                 => 'Ya existe un empleado con ese DNI.',
            'user_id.unique'             => 'Ese usuario ya está asignado a otro empleado.',
            'hora_salida.after'          => 'La hora de salida debe ser posterior a la de entrada.',
            'fecha_fin_contrato.after'   => 'El fin de contrato debe ser posterior al ingreso.',
            'codigo_huellero.unique'     => 'Otro empleado ya usa ese código de huellero.',
        ]);

        // Listas de la ficha: se guardan sin filas vacías
        $datos['educacion']    = $this->filas($datos['educacion'] ?? null, ['nivel', 'centro', 'inicio', 'fin'], ['nivel']);
        $datos['idiomas']      = $this->filas($datos['idiomas'] ?? null, ['idioma', 'habla', 'lee', 'escribe']);
        $datos['software']     = $this->filas($datos['software'] ?? null, ['nombre', 'nivel']);
        $datos['experiencia']  = $this->filas($datos['experiencia'] ?? null, ['empresa', 'telefono', 'cargo', 'jefe', 'ingreso', 'salida', 'remuneracion', 'funciones', 'motivo_cese', 'consultar'], ['consultar']);
        $datos['dependientes'] = $this->filas($datos['dependientes'] ?? null, ['nombres', 'parentesco', 'dni', 'nacimiento', 'centro', 'ocupacion']);
        $datos['familiares']   = $this->filas($datos['familiares'] ?? null, ['nombres', 'parentesco', 'dni', 'nacimiento', 'telefono', 'centro', 'ocupacion', 'direccion', 'otros']);
        $datos['referencias']  = $this->filas($datos['referencias'] ?? null, ['nombres', 'empresa', 'cargo', 'telefono', 'tiempo']);
        $datos['otros_estudios'] = array_values(array_filter(array_map('trim', $datos['otros_estudios'] ?? []))) ?: null;
        $datos['enfermedades'] = array_values(array_filter(array_map('trim', $datos['enfermedades'] ?? []))) ?: null;
        $datos['declaraciones'] = array_filter($datos['declaraciones'] ?? []) ?: null;

        $vl = $datos['vida_ley'] ?? [];
        $vl['beneficiarios'] = $this->filas($vl['beneficiarios'] ?? null, ['nombres', 'parentesco']);
        $datos['vida_ley'] = array_filter($vl, fn ($x) => filled($x)) ?: null;
        unset($datos['foto']);

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
