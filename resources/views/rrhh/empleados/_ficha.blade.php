{{-- Ficha del colaborador: lo que se capturó del formato oficial de ingreso --}}
@php
    $e = $empleado;
    $fila = fn ($etq, $val) => filled($val) ? '<div class="flex justify-between gap-4 py-1.5 border-b border-gray-100 text-sm"><span class="text-gray-500">' . e($etq) . '</span><span class="font-medium text-gray-900 text-right">' . e($val) . '</span></div>' : '';
    $si = fn ($b) => $b === null ? null : ($b ? 'Sí' : 'No');
    $nivel = fn ($n) => \App\Models\Empleado::NIVELES[$n] ?? $n;
@endphp

<section class="bg-white rounded-xl shadow-sm p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h2 class="font-semibold text-gray-900"><i class="fas fa-id-card text-gray-400 mr-1"></i> Ficha del colaborador</h2>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 text-xs text-gray-500" title="Datos clave de la ficha que ya están llenos">
                <div class="w-28 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-full {{ $e->ficha_completa >= 80 ? 'bg-green-500' : ($e->ficha_completa >= 40 ? 'bg-amber-500' : 'bg-red-400') }}" style="width: {{ $e->ficha_completa }}%"></div></div>
                {{ $e->ficha_completa }}% completa
            </div>
            <a href="{{ route('rrhh.empleados.ficha-pdf', $e) }}" target="_blank" class="inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm font-medium"><i class="fas fa-file-pdf text-red-500"></i> Ficha en PDF</a>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-x-8">
        <div>
            {!! $fila('Nacionalidad', $e->nacionalidad) !!}
            {!! $fila('Lugar de nacimiento', $e->lugar_nacimiento) !!}
            {!! $fila('Grado de instrucción', $e->grado_instruccion) !!}
            {!! $fila('Tipo de sangre', $e->tipo_sangre) !!}
            {!! $fila('Domicilio alterno', $e->domicilio_alterno) !!}
            {!! $fila('Referencia domiciliaria', $e->referencia_domiciliaria) !!}
            {!! $fila('Contrató / V°B°', $e->jefe_contrata) !!}
            {!! $fila('Código en el huellero', $e->codigo_huellero) !!}
        </div>
        <div>
            {!! $fila('Centro de costos', $e->centro_costos) !!}
            {!! $fila('Periodo de prueba', $e->periodo_prueba) !!}
            {!! $fila('Remuneración', \App\Models\Empleado::TIPOS_REMUNERACION[$e->tipo_remuneracion] ?? null) !!}
            {!! $fila('Modalidad', $e->modalidad_pago === 'otro' ? ($e->modalidad_pago_otro ?: 'Otro') : (\App\Models\Empleado::MODALIDADES_PAGO[$e->modalidad_pago] ?? null)) !!}
            {!! $fila('Local', $e->local_trabajo === 'otros' ? ($e->local_otro ?: 'Otros') : (\App\Models\Empleado::LOCALES[$e->local_trabajo] ?? null)) !!}
            {!! $fila('Calificación', \App\Models\Empleado::CALIFICACIONES[$e->calificacion] ?? null) !!}
            {!! $fila('Pensiones', \App\Models\Empleado::SITUACIONES_PENSION[$e->pension_situacion] ?? null) !!}
            {!! $fila('Cuenta de ahorro sueldo', $si($e->posee_cuenta_sueldo)) !!}
        </div>
    </div>

    @if($e->puesto_solicitado || $e->sueldo_minimo || $e->fecha_disponibilidad || $e->viajaria_provincias !== null || $e->trabajo_antes_kyrios !== null || $e->conocidos_kyrios || $e->familiares_kyrios)
        <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-1">Postulación</h3>
        <div class="grid md:grid-cols-2 gap-x-8">
            <div>
                {!! $fila('Puesto solicitado', $e->puesto_solicitado) !!}
                {!! $fila('Disponibilidad', $e->fecha_disponibilidad?->format('d/m/Y')) !!}
                {!! $fila('Sueldo mínimo', $e->sueldo_minimo !== null ? 'S/ ' . number_format((float) $e->sueldo_minimo, 2) : null) !!}
            </div>
            <div>
                {!! $fila('Viajaría a provincias', $si($e->viajaria_provincias)) !!}
                {!! $fila('Trabajó antes en Kyrios', $si($e->trabajo_antes_kyrios)) !!}
                {!! $fila('Conocidos en Kyrios', $e->conocidos_kyrios) !!}
                {!! $fila('Familiares en Kyrios', $e->familiares_kyrios) !!}
            </div>
        </div>
    @endif

    @if($e->educacion || $e->profesion || $e->idiomas || $e->software || $e->otros_estudios)
        <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-1">Educación y habilidades</h3>
        <ul class="text-sm text-gray-800 space-y-1">
            @foreach($e->educacion ?? [] as $f)<li><span class="text-gray-500">{{ $f['nivel'] }}:</span> {{ $f['centro'] }}@if($f['inicio'] || $f['fin']) <span class="text-gray-400">({{ $f['inicio'] }} – {{ $f['fin'] }})</span>@endif</li>@endforeach
            @if($e->profesion)<li><span class="text-gray-500">Profesión:</span> {{ $e->profesion }}@if($e->profesion_condicion) ({{ \App\Models\Empleado::CONDICIONES_PROFESION[$e->profesion_condicion] ?? $e->profesion_condicion }})@endif</li>@endif
            @foreach($e->idiomas ?? [] as $f)<li><span class="text-gray-500">Idioma:</span> {{ $f['idioma'] }} <span class="text-gray-400">· habla {{ $nivel($f['habla']) ?: '—' }}, lee {{ $nivel($f['lee']) ?: '—' }}, escribe {{ $nivel($f['escribe']) ?: '—' }}</span></li>@endforeach
            @foreach($e->software ?? [] as $f)<li><span class="text-gray-500">Software:</span> {{ $f['nombre'] }} <span class="text-gray-400">({{ $nivel($f['nivel']) ?: '—' }})</span></li>@endforeach
            @foreach($e->otros_estudios ?? [] as $t)<li><span class="text-gray-500">Otros estudios:</span> {{ $t }}</li>@endforeach
        </ul>
    @endif

    @if($e->experiencia)
        <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-1">Experiencia laboral</h3>
        <div class="space-y-3">
            @foreach($e->experiencia as $f)
                <div class="text-sm border-l-2 border-gray-200 pl-3">
                    <p class="font-medium text-gray-900">{{ $f['empresa'] }}@if($f['cargo']) <span class="text-gray-500 font-normal">· {{ $f['cargo'] }}</span>@endif</p>
                    <p class="text-xs text-gray-500">{{ $f['ingreso'] }} – {{ $f['salida'] ?: 'actualidad' }}@if($f['jefe']) · Jefe: {{ $f['jefe'] }}@endif @if($f['telefono']) · Tel. {{ $f['telefono'] }}@endif @if($f['remuneracion']) · Última remuneración: {{ $f['remuneracion'] }}@endif</p>
                    @if($f['funciones'])<p class="text-gray-700">{{ $f['funciones'] }}</p>@endif
                    @if($f['motivo_cese'])<p class="text-xs text-gray-500">Motivo de cese: {{ $f['motivo_cese'] }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif

    @if($e->dependientes || $e->familiares || $e->referencias)
        <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-1">Familia y referencias</h3>
        <ul class="text-sm text-gray-800 space-y-1">
            @foreach($e->dependientes ?? [] as $f)<li><span class="text-gray-500">Dependiente:</span> {{ $f['nombres'] }} <span class="text-gray-400">({{ $f['parentesco'] }}@if($f['dni']), DNI {{ $f['dni'] }}@endif)</span></li>@endforeach
            @foreach($e->familiares ?? [] as $f)<li><span class="text-gray-500">Familiar:</span> {{ $f['nombres'] }} <span class="text-gray-400">({{ $f['parentesco'] }}@if($f['telefono']), Tel. {{ $f['telefono'] }}@endif)</span></li>@endforeach
            @foreach($e->referencias ?? [] as $f)<li><span class="text-gray-500">Referencia:</span> {{ $f['nombres'] }} <span class="text-gray-400">({{ collect([$f['empresa'], $f['cargo'], $f['telefono']])->filter()->implode(' · ') }})</span></li>@endforeach
        </ul>
    @endif

    <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-2">Documentos y declaraciones firmadas</h3>
    <ul class="grid md:grid-cols-2 gap-x-6 gap-y-1 text-sm">
        @foreach(\App\Models\Empleado::DECLARACIONES as $k => $t)
            @php $fecha = $e->declaraciones[$k] ?? null; @endphp
            <li class="flex items-start gap-2 {{ $fecha ? 'text-gray-800' : 'text-gray-400' }}">
                <i class="fas {{ $fecha ? 'fa-circle-check text-green-600' : 'fa-circle text-gray-300' }} mt-1 text-xs"></i>
                <span>{{ $t }}@if($fecha) <span class="text-xs text-gray-500">· {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</span>@endif</span>
            </li>
        @endforeach
    </ul>
</section>
