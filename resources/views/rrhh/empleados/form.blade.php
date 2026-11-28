@extends('layouts.modulo')
@php $e = $empleado; $esNuevo = !$e; @endphp
@section('titulo', $esNuevo ? 'Nuevo empleado' : 'Editar ' . $e->nombre_completo)
@section('subtitulo', 'Ficha del colaborador: datos personales, laborales, educación, experiencia, familia y declaraciones')

@php
    $v = fn ($campo, $defecto = '') => old($campo, $e?->{$campo} instanceof \DateTimeInterface ? $e->{$campo}->format('Y-m-d') : ($e?->{$campo} ?? $defecto));
    $hora = fn ($campo, $defecto) => old($campo, $e ? substr($e->{$campo}, 0, 5) : $defecto);
    $caja = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring focus:ring-blue-200';
    $etq = 'block text-xs font-medium text-gray-600 mb-1';
    $sino = fn ($campo) => old($campo, $e?->{$campo} === null ? '' : ($e->{$campo} ? '1' : '0'));
    $lista = fn ($campo, $defecto = []) => old($campo, $e?->{$campo} ?? $defecto);
    $datosJs = [
        'educacion'    => collect(['Superior', 'Técnico', 'Secundaria'])->map(fn ($n) => collect($lista('educacion'))->firstWhere('nivel', $n) ?? ['nivel' => $n, 'centro' => '', 'inicio' => '', 'fin' => ''])->values(),
        'idiomas'      => $lista('idiomas'),
        'software'     => $lista('software'),
        'otros'        => $lista('otros_estudios'),
        'experiencia'  => $lista('experiencia'),
        'dependientes' => $lista('dependientes'),
        'familiares'   => $lista('familiares'),
        'referencias'  => $lista('referencias'),
        'beneficiarios' => old('vida_ley.beneficiarios', $e?->vida_ley['beneficiarios'] ?? []),
        'enfermedades' => $lista('enfermedades'),
        'pension'      => $v('pension_situacion'),
        'modalidad'    => $v('modalidad_pago'),
        'local'        => $v('local_trabajo'),
        'cuenta'       => $sino('posee_cuenta_sueldo'),
        'vidaLey'      => $sino('vida_ley_extrabajador'),
        'sinEnfermedades' => $sino('sin_enfermedades') === '1',
    ];
    $pestanas = ['personal' => ['Personal', 'fa-user'], 'laboral' => ['Laboral y nóminas', 'fa-briefcase'], 'educacion' => ['Educación', 'fa-graduation-cap'],
        'experiencia' => ['Experiencia', 'fa-building'], 'familia' => ['Familia y referencias', 'fa-people-roof'], 'pago' => ['Pago y pensiones', 'fa-piggy-bank'], 'declaraciones' => ['Declaraciones', 'fa-file-signature']];
@endphp

@section('contenido')
    <form method="POST" action="{{ $esNuevo ? route('rrhh.empleados.store') : route('rrhh.empleados.update', $e) }}" enctype="multipart/form-data" class="max-w-5xl space-y-6"
          x-data="fichaForm(@js($datosJs))" @invalid.capture="tab = $event.target.closest('[data-tab]')?.dataset.tab || tab">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                <p class="font-semibold mb-1">Revise los datos:</p>
                <ul class="list-disc pl-5">@foreach(collect($errors->all())->take(6) as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Pestañas: todas las secciones son del mismo formulario y se guardan juntas --}}
        <nav class="flex flex-wrap gap-2 sticky top-0 z-10 bg-gray-50/95 backdrop-blur py-2">
            @foreach($pestanas as $k => [$t, $i])
                <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                        class="px-3 py-2 rounded-lg text-sm font-medium"><i class="fas {{ $i }} mr-1"></i>{{ $t }}</button>
            @endforeach
        </nav>

        {{-- ───────── Personal ───────── --}}
        <section data-tab="personal" x-show="tab === 'personal'" class="bg-white rounded-xl shadow-sm p-5 space-y-5">
            <h2 class="font-semibold text-gray-900"><i class="fas fa-user text-gray-400 mr-1"></i> Datos personales</h2>
            <div class="grid md:grid-cols-4 gap-4">
                <div class="md:col-span-3 grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">Nombres *</label><input name="nombres" value="{{ $v('nombres') }}" required maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Apellidos *</label><input name="apellidos" value="{{ $v('apellidos') }}" required maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">DNI *</label><input name="dni" value="{{ $v('dni') }}" required maxlength="8" inputmode="numeric" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Nacionalidad</label><input name="nacionalidad" value="{{ $v('nacionalidad', 'Peruana') }}" maxlength="40" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="{{ $v('fecha_nacimiento') }}" max="{{ today()->toDateString() }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Lugar de nacimiento</label><input name="lugar_nacimiento" value="{{ $v('lugar_nacimiento') }}" maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Sexo</label>
                        <select name="sexo" class="{{ $caja }}"><option value="">—</option><option value="F" @selected($v('sexo') === 'F')>Femenino</option><option value="M" @selected($v('sexo') === 'M')>Masculino</option></select></div>
                    <div><label class="{{ $etq }}">Estado civil</label>
                        <select name="estado_civil" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::ESTADOS_CIVILES as $k => $t)<option value="{{ $k }}" @selected($v('estado_civil') === $k)>{{ $t }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}">Grado de instrucción</label>
                        <select name="grado_instruccion" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::GRADOS_INSTRUCCION as $g)<option value="{{ $g }}" @selected($v('grado_instruccion') === $g)>{{ $g }}</option>@endforeach</select></div>
                </div>
                {{-- Foto --}}
                <div class="text-center">
                    <label class="{{ $etq }}">Foto</label>
                    <div class="w-28 h-32 mx-auto border border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                        <img x-show="fotoUrl" :src="fotoUrl" alt="" class="w-full h-full object-cover" @if($e?->foto) x-init="fotoUrl = '{{ route('rrhh.empleados.foto', $e) }}'" @endif>
                        <i x-show="!fotoUrl" class="fas fa-camera text-gray-300 text-2xl"></i>
                    </div>
                    <input type="file" name="foto" accept="image/*" class="mt-2 text-xs w-full" @change="fotoUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : fotoUrl">
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-3"><label class="{{ $etq }}">Dirección</label><input name="direccion" value="{{ $v('direccion') }}" maxlength="255" class="{{ $caja }}"></div>
                <div><label class="{{ $etq }}">Número de celular</label><input name="telefono" value="{{ $v('telefono') }}" maxlength="20" class="{{ $caja }}"></div>
                <div class="md:col-span-2"><label class="{{ $etq }}">Correo electrónico</label><input type="email" name="email_personal" value="{{ $v('email_personal') }}" maxlength="150" class="{{ $caja }}"></div>
                <div class="md:col-span-3"><label class="{{ $etq }}">Referencia domiciliaria <span class="text-gray-400">(cómo llegar a su casa)</span></label><input name="referencia_domiciliaria" value="{{ $v('referencia_domiciliaria') }}" maxlength="255" class="{{ $caja }}"></div>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2">En caso de emergencias</h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="md:col-span-3"><label class="{{ $etq }}">Dirección de domicilio alterno</label><input name="domicilio_alterno" value="{{ $v('domicilio_alterno') }}" maxlength="255" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Nombre de referencia</label><input name="contacto_emergencia" value="{{ $v('contacto_emergencia') }}" maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Teléfono de referencia</label><input name="telefono_emergencia" value="{{ $v('telefono_emergencia') }}" maxlength="20" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Parentesco</label><input name="parentesco_emergencia" value="{{ $v('parentesco_emergencia') }}" maxlength="40" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Tipo de sangre</label>
                        <select name="tipo_sangre" class="{{ $caja }}"><option value="">—</option>@foreach(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $s)<option value="{{ $s }}" @selected($v('tipo_sangre') === $s)>{{ $s }}</option>@endforeach</select></div>
                </div>
            </div>
        </section>

        {{-- ───────── Laboral y nóminas ───────── --}}
        <section data-tab="laboral" x-show="tab === 'laboral'" x-cloak class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-briefcase text-gray-400 mr-1"></i> Datos laborales</h2>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">Puesto *</label><input name="cargo" value="{{ $v('cargo') }}" required maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Área</label>
                        <select name="area" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::AREAS as $a)<option value="{{ $a }}" @selected($v('area') === $a)>{{ $a }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}">Nombre y V°B° de la persona que contrata</label><input name="jefe_contrata" value="{{ $v('jefe_contrata') }}" maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Usuario del sistema</label>
                        <select name="user_id" class="{{ $caja }}"><option value="">Sin usuario</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((string) $v('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->role?->nombre }})</option>@endforeach</select>
                        <p class="text-[11px] text-gray-400 mt-1">Con un usuario vinculado, la persona marca su propia asistencia.</p></div>
                    <div><label class="{{ $etq }}">Código en el huellero</label><input name="codigo_huellero" value="{{ $v('codigo_huellero') }}" maxlength="20" inputmode="numeric" class="{{ $caja }}">
                        <p class="text-[11px] text-gray-400 mt-1">El número con que la persona aparece en el reloj biométrico: sirve para importar su asistencia.</p></div>
                    <div><label class="{{ $etq }}">Tipo de contrato *</label>
                        <select name="tipo_contrato" class="{{ $caja }}">@foreach(\App\Models\Empleado::TIPOS_CONTRATO as $k => $t)<option value="{{ $k }}" @selected($v('tipo_contrato', 'indefinido') === $k)>{{ $t }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}">Fecha de ingreso *</label><input type="date" name="fecha_ingreso" value="{{ $v('fecha_ingreso') }}" required max="{{ today()->toDateString() }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Fin de contrato (plazo fijo)</label><input type="date" name="fecha_fin_contrato" value="{{ $v('fecha_fin_contrato') }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Periodo de prueba</label><input name="periodo_prueba" value="{{ $v('periodo_prueba') }}" maxlength="60" placeholder="Ej.: 3 meses" class="{{ $caja }}"></div>
                    @if($esNuevo || $e->estado !== 'cesado')
                        <div><label class="{{ $etq }}">Estado</label>
                            <select name="estado" class="{{ $caja }}"><option value="activo" @selected($v('estado', 'activo') === 'activo')>Activo</option><option value="licencia" @selected($v('estado') === 'licencia')>En licencia</option></select></div>
                    @endif
                </div>
            </div>

            {{-- Salario, horario y lo que se descuenta o bonifica por tardanzas, faltas y horas extra --}}
            <div class="bg-white rounded-xl shadow-sm p-5" x-data="{ tm: '{{ $v('tardanza_modo', 'proporcional') }}', fm: '{{ $v('falta_modo', 'dia') }}', em: '{{ $v('extra_modo', 'factor') }}' }">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-money-bill-wave text-gray-400 mr-1"></i> Salario, horario y descuentos</h2>
                <p class="text-xs text-gray-500 mb-4">Con esto el sistema calcula cada mes cuánto se descuenta por tardanzas y faltas y cuánto se bonifica por horas extra. El horario de partida es de 8:00 a 18:00.</p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">Sueldo básico (S/)</label><input type="number" step="0.01" min="0" name="sueldo_basico" value="{{ $v('sueldo_basico') }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Hora de entrada *</label><input type="time" name="hora_entrada" value="{{ $hora('hora_entrada', '08:00') }}" required class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Hora de salida *</label><input type="time" name="hora_salida" value="{{ $hora('hora_salida', '18:00') }}" required class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Horas de la jornada *</label><input type="number" step="0.25" min="1" max="24" name="horas_jornada" value="{{ $v('horas_jornada', 8) }}" required class="{{ $caja }}">
                        <p class="text-[11px] text-gray-400 mt-1">Sirve para el valor de la hora: sueldo ÷ 30 días ÷ horas.</p></div>
                    <div><label class="{{ $etq }}">Tolerancia de ingreso (minutos)</label><input type="number" min="0" max="120" name="tolerancia_min" value="{{ $v('tolerancia_min') }}" placeholder="General: {{ \App\Services\AsistenciaService::tolerancia() }}" class="{{ $caja }}">
                        <p class="text-[11px] text-gray-400 mt-1">Vacío = la tolerancia general del sistema.</p></div>
                    <div></div>

                    <div class="md:col-span-3 border-t border-dashed border-gray-200 pt-3"><p class="text-xs font-semibold text-gray-500 uppercase">Tardanzas</p></div>
                    <div class="md:col-span-2"><label class="{{ $etq }}">Cómo se descuenta</label>
                        <select name="tardanza_modo" x-model="tm" class="{{ $caja }}">@foreach(\App\Models\Empleado::TARDANZA_MODOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                    <div x-show="['por_minuto','por_tardanza'].includes(tm)" x-cloak><label class="{{ $etq }}" x-text="tm === 'por_minuto' ? 'Monto por minuto (S/)' : 'Monto por tardanza (S/)'"></label><input type="number" step="0.01" min="0" name="tardanza_valor" value="{{ $v('tardanza_valor', 0) }}" class="{{ $caja }}"></div>

                    <div class="md:col-span-3 border-t border-dashed border-gray-200 pt-3"><p class="text-xs font-semibold text-gray-500 uppercase">Faltas</p></div>
                    <div class="md:col-span-2"><label class="{{ $etq }}">Cómo se descuenta</label>
                        <select name="falta_modo" x-model="fm" class="{{ $caja }}">@foreach(\App\Models\Empleado::FALTA_MODOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                    <div x-show="fm === 'monto'" x-cloak><label class="{{ $etq }}">Monto por falta (S/)</label><input type="number" step="0.01" min="0" name="falta_valor" value="{{ $v('falta_valor', 0) }}" class="{{ $caja }}"></div>

                    <div class="md:col-span-3 border-t border-dashed border-gray-200 pt-3"><p class="text-xs font-semibold text-gray-500 uppercase">Horas extra (después de la hora de salida)</p></div>
                    <div class="md:col-span-2"><label class="{{ $etq }}">Cómo se bonifica</label>
                        <select name="extra_modo" x-model="em" class="{{ $caja }}">@foreach(\App\Models\Empleado::EXTRA_MODOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                    <div x-show="['factor','por_hora'].includes(em)" x-cloak><label class="{{ $etq }}" x-text="em === 'factor' ? 'Factor (1.25 = 25 % más)' : 'Monto por hora extra (S/)'"></label><input type="number" step="0.01" min="0" name="extra_valor" value="{{ $v('extra_valor', 1.25) }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Cuentan desde (minutos extra) *</label><input type="number" min="0" max="600" name="extra_minimo_min" value="{{ $v('extra_minimo_min', 30) }}" required class="{{ $caja }}">
                        <p class="text-[11px] text-gray-400 mt-1">Quedarse menos que esto no se bonifica.</p></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-calculator text-gray-400 mr-1"></i> Datos para nóminas</h2>
                <p class="text-xs text-gray-500 mb-4">Los llena Recursos Humanos.</p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">Centro de costos</label><input name="centro_costos" value="{{ $v('centro_costos') }}" maxlength="60" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Remuneración</label>
                        <select name="tipo_remuneracion" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::TIPOS_REMUNERACION as $k => $t)<option value="{{ $k }}" @selected($v('tipo_remuneracion') === $k)>{{ $t }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}">Pagos adicionales / modalidad</label>
                        <select name="modalidad_pago" x-model="modalidad" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::MODALIDADES_PAGO as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
                        <input name="modalidad_pago_otro" x-show="modalidad === 'otro'" x-cloak value="{{ $v('modalidad_pago_otro') }}" maxlength="60" placeholder="Especifique" class="{{ $caja }} mt-2"></div>
                    <div><label class="{{ $etq }}">Local</label>
                        <select name="local_trabajo" x-model="local" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::LOCALES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
                        <input name="local_otro" x-show="local === 'otros'" x-cloak value="{{ $v('local_otro') }}" maxlength="60" placeholder="¿Cuál?" class="{{ $caja }} mt-2"></div>
                    <div class="md:col-span-3"><label class="{{ $etq }}">Calificación</label>
                        <select name="calificacion" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::CALIFICACIONES as $k => $t)<option value="{{ $k }}" @selected($v('calificacion') === $k)>{{ $t }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-clipboard-user text-gray-400 mr-1"></i> Postulación</h2>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">Puesto solicitado</label><input name="puesto_solicitado" value="{{ $v('puesto_solicitado') }}" maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Fecha de disponibilidad</label><input type="date" name="fecha_disponibilidad" value="{{ $v('fecha_disponibilidad') }}" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Sueldo mínimo que aceptaría (S/)</label><input type="number" step="0.01" min="0" name="sueldo_minimo" value="{{ $v('sueldo_minimo') }}" class="{{ $caja }}"></div>
                    @foreach(['viajaria_provincias' => '¿Viajaría a provincias?', 'trabajo_antes_kyrios' => '¿Trabajó o practicó antes en Kyrios?'] as $campo => $texto)
                        <div><label class="{{ $etq }}">{{ $texto }}</label>
                            <select name="{{ $campo }}" class="{{ $caja }}"><option value="">—</option><option value="1" @selected($sino($campo) === '1')>Sí</option><option value="0" @selected($sino($campo) === '0')>No</option></select></div>
                    @endforeach
                    <div class="md:col-span-3 grid md:grid-cols-2 gap-4">
                        <div><label class="{{ $etq }}">¿Tiene conocidos en Kyrios? ¿Quiénes?</label><input name="conocidos_kyrios" value="{{ $v('conocidos_kyrios') }}" maxlength="255" class="{{ $caja }}"></div>
                        <div><label class="{{ $etq }}">¿Tiene familiares en Kyrios? ¿Quiénes?</label><input name="familiares_kyrios" value="{{ $v('familiares_kyrios') }}" maxlength="255" class="{{ $caja }}"></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ───────── Educación ───────── --}}
        <section data-tab="educacion" x-show="tab === 'educacion'" x-cloak class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-graduation-cap text-gray-400 mr-1"></i> Educación</h2>
                <p class="text-xs text-gray-500 mb-4">Solo el centro de estudios en el que concluyó.</p>
                <div class="space-y-3">
                    <template x-for="(f, i) in educacion" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input type="hidden" :name="`educacion[${i}][nivel]`" :value="f.nivel">
                            <span class="col-span-12 sm:col-span-2 text-sm font-medium text-gray-700" x-text="f.nivel"></span>
                            <input :name="`educacion[${i}][centro]`" x-model="f.centro" maxlength="160" placeholder="Centro de estudios" class="col-span-12 sm:col-span-6 {{ $caja }}">
                            <input :name="`educacion[${i}][inicio]`" x-model="f.inicio" maxlength="20" placeholder="Inicio (ej. 2015)" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input :name="`educacion[${i}][fin]`" x-model="f.fin" maxlength="20" placeholder="Término" class="col-span-6 sm:col-span-2 {{ $caja }}">
                        </div>
                    </template>
                </div>
                <div class="grid md:grid-cols-3 gap-4 mt-4">
                    <div class="md:col-span-2"><label class="{{ $etq }}">Profesión</label><input name="profesion" value="{{ $v('profesion') }}" maxlength="160" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Condición</label>
                        <select name="profesion_condicion" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::CONDICIONES_PROFESION as $k => $t)<option value="{{ $k }}" @selected($v('profesion_condicion') === $k)>{{ $t }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Idiomas <span class="text-xs font-normal text-gray-500">(nivel con que habla, lee y escribe)</span></h2>
                <div class="space-y-2">
                    <template x-for="(f, i) in idiomas" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input :name="`idiomas[${i}][idioma]`" x-model="f.idioma" maxlength="60" placeholder="Idioma" class="col-span-12 sm:col-span-3 {{ $caja }}">
                            @foreach(['habla' => 'Habla', 'lee' => 'Lee', 'escribe' => 'Escribe'] as $k => $t)
                                <select :name="`idiomas[${i}][{{ $k }}]`" x-model="f.{{ $k }}" class="col-span-4 sm:col-span-2 {{ $caja }}"><option value="">{{ $t }}</option>@foreach(\App\Models\Empleado::NIVELES as $nk => $nt)<option value="{{ $nk }}">{{ $nt }}</option>@endforeach</select>
                            @endforeach
                            <button type="button" @click="idiomas.splice(i, 1)" class="col-span-12 sm:col-span-1 text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="idiomas.push({idioma: '', habla: '', lee: '', escribe: ''})" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar idioma</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Software</h2>
                <div class="space-y-2">
                    <template x-for="(f, i) in software" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input :name="`software[${i}][nombre]`" x-model="f.nombre" maxlength="80" placeholder="Programa (Excel, AutoCAD, Dialux…)" class="col-span-8 sm:col-span-6 {{ $caja }}">
                            <select :name="`software[${i}][nivel]`" x-model="f.nivel" class="col-span-3 sm:col-span-5 {{ $caja }}"><option value="">Nivel</option>@foreach(\App\Models\Empleado::NIVELES as $nk => $nt)<option value="{{ $nk }}">{{ $nt }}</option>@endforeach</select>
                            <button type="button" @click="software.splice(i, 1)" class="col-span-1 text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="software.push({nombre: '', nivel: ''})" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar programa</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Otros estudios <span class="text-xs font-normal text-gray-500">(seminarios, cursos, especializaciones, maestrías…)</span></h2>
                <div class="space-y-2 mt-3">
                    <template x-for="(t, i) in otros" :key="i">
                        <div class="flex gap-2"><input :name="`otros_estudios[${i}]`" x-model="otros[i]" maxlength="200" class="flex-1 {{ $caja }}">
                            <button type="button" @click="otros.splice(i, 1)" class="text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button></div>
                    </template>
                </div>
                <button type="button" @click="otros.push('')" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar estudio</button>
            </div>
        </section>

        {{-- ───────── Experiencia ───────── --}}
        <section data-tab="experiencia" x-show="tab === 'experiencia'" x-cloak class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-building text-gray-400 mr-1"></i> Experiencia laboral</h2>
            <p class="text-xs text-gray-500 mb-4">Los tres últimos empleos, desde el más reciente, incluido el actual si está laborando.</p>
            <div class="space-y-5">
                <template x-for="(f, i) in experiencia" :key="i">
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <div class="flex justify-between"><p class="text-sm font-semibold text-gray-700" x-text="'Empleo ' + (i + 1)"></p>
                            <button type="button" @click="experiencia.splice(i, 1)" class="text-red-500 text-sm"><i class="fas fa-trash mr-1"></i>Quitar</button></div>
                        <div class="grid md:grid-cols-4 gap-3">
                            <div class="md:col-span-3"><label class="{{ $etq }}">Empresa</label><input :name="`experiencia[${i}][empresa]`" x-model="f.empresa" maxlength="120" class="{{ $caja }}"></div>
                            <div><label class="{{ $etq }}">Teléfono</label><input :name="`experiencia[${i}][telefono]`" x-model="f.telefono" maxlength="30" class="{{ $caja }}"></div>
                            <div class="md:col-span-2"><label class="{{ $etq }}">Último cargo</label><input :name="`experiencia[${i}][cargo]`" x-model="f.cargo" maxlength="120" class="{{ $caja }}"></div>
                            <div class="md:col-span-2"><label class="{{ $etq }}">Jefe inmediato</label><input :name="`experiencia[${i}][jefe]`" x-model="f.jefe" maxlength="120" class="{{ $caja }}"></div>
                            <div><label class="{{ $etq }}">Fecha de ingreso</label><input :name="`experiencia[${i}][ingreso]`" x-model="f.ingreso" maxlength="20" placeholder="mm/aaaa" class="{{ $caja }}"></div>
                            <div><label class="{{ $etq }}">Fecha de salida</label><input :name="`experiencia[${i}][salida]`" x-model="f.salida" maxlength="20" placeholder="mm/aaaa" class="{{ $caja }}"></div>
                            <div class="md:col-span-2"><label class="{{ $etq }}">Última remuneración</label><input :name="`experiencia[${i}][remuneracion]`" x-model="f.remuneracion" maxlength="30" class="{{ $caja }}"></div>
                            <div class="md:col-span-4"><label class="{{ $etq }}">Funciones principales</label><textarea :name="`experiencia[${i}][funciones]`" x-model="f.funciones" rows="2" maxlength="600" class="{{ $caja }}"></textarea></div>
                            <div class="md:col-span-3"><label class="{{ $etq }}">Motivo de cese</label><input :name="`experiencia[${i}][motivo_cese]`" x-model="f.motivo_cese" maxlength="200" class="{{ $caja }}"></div>
                            <label class="flex items-end gap-2 text-sm text-gray-700 pb-2"><input type="checkbox" value="1" :name="`experiencia[${i}][consultar]`" :checked="f.consultar === '1' || f.consultar === true" @change="f.consultar = $event.target.checked ? '1' : ''" class="rounded border-gray-300"> Se puede pedir información al empleador actual</label>
                        </div>
                    </div>
                </template>
            </div>
            <button type="button" x-show="experiencia.length < 3" @click="experiencia.push({empresa: '', telefono: '', cargo: '', jefe: '', ingreso: '', salida: '', remuneracion: '', funciones: '', motivo_cese: '', consultar: ''})" class="mt-4 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar empleo</button>
        </section>

        {{-- ───────── Familia y referencias ───────── --}}
        <section data-tab="familia" x-show="tab === 'familia'" x-cloak class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-people-roof text-gray-400 mr-1"></i> Datos familiares: personas que viven o dependen de usted</h2>
                <div class="space-y-3 mt-3">
                    <template x-for="(f, i) in dependientes" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input :name="`dependientes[${i}][nombres]`" x-model="f.nombres" maxlength="140" placeholder="Nombres y apellidos" class="col-span-12 sm:col-span-4 {{ $caja }}">
                            <input :name="`dependientes[${i}][parentesco]`" x-model="f.parentesco" maxlength="40" placeholder="Parentesco" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input :name="`dependientes[${i}][dni]`" x-model="f.dni" maxlength="12" placeholder="DNI" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input type="date" :name="`dependientes[${i}][nacimiento]`" x-model="f.nacimiento" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input :name="`dependientes[${i}][ocupacion]`" x-model="f.ocupacion" maxlength="80" placeholder="Ocupación" class="col-span-5 sm:col-span-1 {{ $caja }}">
                            <button type="button" @click="dependientes.splice(i, 1)" class="col-span-1 text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button>
                            <input :name="`dependientes[${i}][centro]`" x-model="f.centro" maxlength="120" placeholder="Centro de estudios o trabajo" class="col-span-12 {{ $caja }}">
                        </div>
                    </template>
                </div>
                <button type="button" @click="dependientes.push({nombres: '', parentesco: '', dni: '', nacimiento: '', centro: '', ocupacion: ''})" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar persona</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Familiares directos <span class="text-xs font-normal text-gray-500">(esposo(a), hijos(as), padre, madre, hermanos(as), suegros que estén vivos)</span></h2>
                <div class="space-y-4 mt-3">
                    <template x-for="(f, i) in familiares" :key="i">
                        <div class="border border-gray-200 rounded-xl p-3 grid grid-cols-12 gap-2 items-center">
                            <input :name="`familiares[${i}][nombres]`" x-model="f.nombres" maxlength="140" placeholder="Nombres y apellidos" class="col-span-12 sm:col-span-5 {{ $caja }}">
                            <input :name="`familiares[${i}][parentesco]`" x-model="f.parentesco" maxlength="40" placeholder="Parentesco" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`familiares[${i}][dni]`" x-model="f.dni" maxlength="12" placeholder="DNI" class="col-span-5 sm:col-span-3 {{ $caja }}">
                            <button type="button" @click="familiares.splice(i, 1)" class="col-span-1 text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button>
                            <input type="date" :name="`familiares[${i}][nacimiento]`" x-model="f.nacimiento" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`familiares[${i}][telefono]`" x-model="f.telefono" maxlength="30" placeholder="Teléfono" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`familiares[${i}][ocupacion]`" x-model="f.ocupacion" maxlength="80" placeholder="Ocupación" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`familiares[${i}][centro]`" x-model="f.centro" maxlength="120" placeholder="Centro de estudios o trabajo" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`familiares[${i}][direccion]`" x-model="f.direccion" maxlength="200" placeholder="Dirección de domicilio" class="col-span-12 sm:col-span-8 {{ $caja }}">
                            <input :name="`familiares[${i}][otros]`" x-model="f.otros" maxlength="120" placeholder="Otros" class="col-span-12 sm:col-span-4 {{ $caja }}">
                        </div>
                    </template>
                </div>
                <button type="button" x-show="familiares.length < 12" @click="familiares.push({nombres: '', parentesco: '', dni: '', nacimiento: '', telefono: '', centro: '', ocupacion: '', direccion: '', otros: ''})" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar familiar</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Referencias personales</h2>
                <div class="space-y-2 mt-3">
                    <template x-for="(f, i) in referencias" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input :name="`referencias[${i}][nombres]`" x-model="f.nombres" maxlength="140" placeholder="Nombres y apellidos" class="col-span-12 sm:col-span-3 {{ $caja }}">
                            <input :name="`referencias[${i}][empresa]`" x-model="f.empresa" maxlength="120" placeholder="Empresa en que labora" class="col-span-6 sm:col-span-3 {{ $caja }}">
                            <input :name="`referencias[${i}][cargo]`" x-model="f.cargo" maxlength="80" placeholder="Cargo" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input :name="`referencias[${i}][telefono]`" x-model="f.telefono" maxlength="30" placeholder="Teléfono" class="col-span-6 sm:col-span-2 {{ $caja }}">
                            <input :name="`referencias[${i}][tiempo]`" x-model="f.tiempo" maxlength="40" placeholder="Tiempo de conocerlo" class="col-span-5 sm:col-span-1 {{ $caja }}">
                            <button type="button" @click="referencias.splice(i, 1)" class="col-span-1 text-red-500 text-sm" title="Quitar"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="referencias.push({nombres: '', empresa: '', cargo: '', telefono: '', tiempo: ''})" class="mt-3 text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar referencia</button>
            </div>
        </section>

        {{-- ───────── Pago y pensiones ───────── --}}
        <section data-tab="pago" x-show="tab === 'pago'" x-cloak class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-4"><i class="fas fa-piggy-bank text-gray-400 mr-1"></i> Cuenta bancaria para el pago de haberes</h2>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="{{ $etq }}">¿Posee cuenta de ahorro sueldo?</label>
                        <select name="posee_cuenta_sueldo" x-model="cuenta" class="{{ $caja }}"><option value="">—</option><option value="1">Sí</option><option value="0">No</option></select></div>
                    <div><label class="{{ $etq }}"><span x-text="cuenta === '0' ? 'Banco donde se abonará' : 'Banco'"></span></label><input name="banco" value="{{ $v('banco') }}" maxlength="40" placeholder="BBVA, BCP, Scotiabank, Interbank…" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Moneda</label>
                        <select name="moneda_pago" class="{{ $caja }}"><option value="">—</option><option value="PEN" @selected($v('moneda_pago') === 'PEN')>Soles</option><option value="USD" @selected($v('moneda_pago') === 'USD')>Dólares</option></select></div>
                    <div class="md:col-span-3"><label class="{{ $etq }}">N.° de cuenta o CCI</label><input name="numero_cuenta" value="{{ $v('numero_cuenta') }}" maxlength="40" class="{{ $caja }}">
                        <p class="text-[11px] text-gray-400 mt-1" x-show="cuenta === '0'" x-cloak>Sin cuenta, la persona autoriza que el abono de remuneraciones, conceptos no remunerativos, otros ingresos y CTS se haga en el banco y moneda indicados.</p></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Sistema de pensiones</h2>
                <div class="space-y-2">
                    @foreach(\App\Models\Empleado::SITUACIONES_PENSION as $k => $t)
                        <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer"><input type="radio" name="pension_situacion" value="{{ $k }}" x-model="pension" class="mt-1"> {{ $t }}</label>
                    @endforeach
                    <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer"><input type="radio" name="pension_situacion" value="" x-model="pension"> Sin definir</label>
                </div>
                <div class="grid md:grid-cols-3 gap-4 mt-4">
                    <div x-show="pension === 'jubilado'" x-cloak><label class="{{ $etq }}">Régimen pensionario actual</label>
                        <select name="pension_regimen" class="{{ $caja }}"><option value="">—</option>@foreach(\App\Models\Empleado::REGIMENES_JUBILADO as $r)<option value="{{ $r }}" @selected($v('pension_regimen') === $r)>{{ $r }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}">Sistema</label>
                        <select name="sistema_pension" class="{{ $caja }}"><option value="">—</option><option value="AFP" @selected($v('sistema_pension') === 'AFP')>AFP</option><option value="ONP" @selected($v('sistema_pension') === 'ONP')>ONP</option></select></div>
                    <div><label class="{{ $etq }}">AFP</label><input name="afp_nombre" list="lista-afps" value="{{ $v('afp_nombre') }}" maxlength="40" class="{{ $caja }}">
                        <datalist id="lista-afps">@foreach(\App\Models\Empleado::AFPS as $a)<option value="{{ $a }}">@endforeach</datalist></div>
                    <div><label class="{{ $etq }}">CUSPP</label><input name="cuspp" value="{{ $v('cuspp') }}" maxlength="20" class="{{ $caja }}"></div>
                    <div class="md:col-span-3"><label class="{{ $etq }}">Notas</label><textarea name="notas" rows="3" maxlength="2000" class="{{ $caja }}">{{ $v('notas') }}</textarea></div>
                </div>
            </div>
        </section>

        {{-- ───────── Declaraciones ───────── --}}
        <section data-tab="declaraciones" x-show="tab === 'declaraciones'" x-cloak class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-file-signature text-gray-400 mr-1"></i> Documentos y declaraciones firmadas</h2>
                <p class="text-xs text-gray-500 mb-4">Anote la fecha en que la persona firmó cada documento del paquete de ingreso. La ficha en PDF trae los textos para imprimir y firmar.</p>
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach(\App\Models\Empleado::DECLARACIONES as $k => $t)
                        <div class="flex items-center justify-between gap-3 border border-gray-200 rounded-lg px-3 py-2">
                            <span class="text-sm text-gray-800">{{ $t }}</span>
                            <input type="date" name="declaraciones[{{ $k }}]" value="{{ old('declaraciones.' . $k, $e?->declaraciones[$k] ?? '') }}" class="px-2 py-1 border border-gray-300 rounded-lg text-sm w-40">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Póliza de Seguro Vida Ley (como extrabajador)</h2>
                <select name="vida_ley_extrabajador" x-model="vidaLey" class="{{ $caja }} max-w-md"><option value="">—</option><option value="0">No he adquirido la póliza como extrabajador</option><option value="1">Sí tengo contratada la póliza como extrabajador</option></select>
                <div x-show="vidaLey === '1'" x-cloak class="grid md:grid-cols-2 gap-4 mt-4">
                    <div><label class="{{ $etq }}">Compañía de seguros</label><input name="vida_ley[compania]" value="{{ old('vida_ley.compania', $e?->vida_ley['compania'] ?? '') }}" maxlength="120" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Número de la póliza</label><input name="vida_ley[poliza]" value="{{ old('vida_ley.poliza', $e?->vida_ley['poliza'] ?? '') }}" maxlength="60" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Vigencia</label><input name="vida_ley[vigencia]" value="{{ old('vida_ley.vigencia', $e?->vida_ley['vigencia'] ?? '') }}" maxlength="60" class="{{ $caja }}"></div>
                    <div><label class="{{ $etq }}">Remuneración asegurable</label><input name="vida_ley[remuneracion]" value="{{ old('vida_ley.remuneracion', $e?->vida_ley['remuneracion'] ?? '') }}" maxlength="40" class="{{ $caja }}"></div>
                    <div class="md:col-span-2">
                        <label class="{{ $etq }}">Beneficiarios</label>
                        <template x-for="(b, i) in beneficiarios" :key="i">
                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <input :name="`vida_ley[beneficiarios][${i}][nombres]`" x-model="b.nombres" maxlength="140" placeholder="Nombres y apellidos" class="col-span-7 {{ $caja }}">
                                <input :name="`vida_ley[beneficiarios][${i}][parentesco]`" x-model="b.parentesco" maxlength="40" placeholder="Parentesco" class="col-span-4 {{ $caja }}">
                                <button type="button" @click="beneficiarios.splice(i, 1)" class="col-span-1 text-red-500 text-sm"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="beneficiarios.push({nombres: '', parentesco: ''})" class="text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar beneficiario</button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Declaración de salud</h2>
                <label class="flex items-center gap-2 text-sm text-gray-800 mb-3"><input type="checkbox" name="sin_enfermedades" value="1" x-model="sinEnfermedades" class="rounded border-gray-300"> No tengo ninguna enfermedad que impida el desempeño de mis funciones</label>
                <div x-show="!sinEnfermedades" x-cloak>
                    <label class="{{ $etq }}">Enfermedades que declara al iniciar el puesto</label>
                    <template x-for="(t, i) in enfermedades" :key="i">
                        <div class="flex gap-2 mb-2"><input :name="`enfermedades[${i}]`" x-model="enfermedades[i]" maxlength="160" class="flex-1 {{ $caja }}">
                            <button type="button" @click="enfermedades.splice(i, 1)" class="text-red-500 text-sm"><i class="fas fa-times"></i></button></div>
                    </template>
                    <button type="button" x-show="enfermedades.length < 10" @click="enfermedades.push('')" class="text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar</button>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-3 sticky bottom-0 bg-gray-50/95 backdrop-blur py-3">
            <a href="{{ $esNuevo ? route('rrhh.empleados.index') : route('rrhh.empleados.show', $e) }}" class="px-5 py-2 text-sm text-gray-600 bg-gray-200 hover:bg-gray-300 rounded-lg">Cancelar</a>
            <button class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"><i class="fas fa-save mr-1"></i>Guardar ficha</button>
        </div>
    </form>

    <script>
    function fichaForm(d) {
        return {
            tab: 'personal', fotoUrl: '',
            educacion: d.educacion, idiomas: d.idiomas, software: d.software, otros: d.otros, experiencia: d.experiencia,
            dependientes: d.dependientes, familiares: d.familiares, referencias: d.referencias, beneficiarios: d.beneficiarios, enfermedades: d.enfermedades,
            pension: d.pension, modalidad: d.modalidad, local: d.local, cuenta: d.cuenta, vidaLey: d.vidaLey, sinEnfermedades: d.sinEnfermedades,
        };
    }
    </script>
@endsection
