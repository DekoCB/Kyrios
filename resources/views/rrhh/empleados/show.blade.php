@extends('layouts.modulo')
@section('titulo', $empleado->nombre_completo)
@section('subtitulo', $empleado->codigo . ' · ' . $empleado->cargo . ($empleado->area ? ' · ' . $empleado->area : ''))

@section('contenido')
    @php
        $dato = fn ($etiqueta, $valor) => $valor === null || $valor === '' ? '' : '<div class="flex justify-between gap-4 py-1.5 border-b border-gray-100 text-sm"><span class="text-gray-500">' . e($etiqueta) . '</span><span class="font-medium text-gray-900 text-right">' . e($valor) . '</span></div>';
    @endphp

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('rrhh.empleados.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
        <a href="{{ route('rrhh.empleados.edit', $empleado) }}" class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 text-yellow-800 hover:bg-yellow-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-edit"></i> Editar</a>
        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ ['activo' => 'bg-green-100 text-green-800', 'licencia' => 'bg-sky-100 text-sky-800', 'cesado' => 'bg-gray-200 text-gray-600'][$empleado->estado] }}">{{ \App\Models\Empleado::ESTADOS[$empleado->estado] }}</span>
        @if($empleado->estado === 'cesado')
            <form method="POST" action="{{ route('rrhh.empleados.reactivar', $empleado) }}" class="ml-auto" data-confirm="¿Reactivar a este empleado?">@csrf
                <button class="inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-rotate-left"></i> Reactivar</button></form>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Antigüedad</p><p class="font-semibold text-gray-900">{{ $empleado->antiguedad }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Días asistidos (mes)</p><p class="text-2xl font-bold text-gray-900">{{ $mes['asistidos'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Tardanzas (mes)</p><p class="text-2xl font-bold {{ $mes['tardanzas'] ? 'text-amber-600' : 'text-gray-900' }}">{{ $mes['tardanzas'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Faltas (mes)</p><p class="text-2xl font-bold {{ $mes['faltas'] ? 'text-red-600' : 'text-gray-900' }}">{{ $mes['faltas'] }}</p></div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-2"><i class="fas fa-user text-gray-400 mr-1"></i> Datos personales</h2>
                {!! $dato('DNI', $empleado->dni) !!}
                {!! $dato('Nacimiento', $empleado->fecha_nacimiento ? $empleado->fecha_nacimiento->format('d/m/Y') . ' (' . $empleado->edad . ' años)' : null) !!}
                {!! $dato('Estado civil', \App\Models\Empleado::ESTADOS_CIVILES[$empleado->estado_civil] ?? null) !!}
                {!! $dato('Teléfono', $empleado->telefono) !!}
                {!! $dato('Correo', $empleado->email_personal) !!}
                {!! $dato('Dirección', $empleado->direccion) !!}
                {!! $dato('Emergencia', trim($empleado->contacto_emergencia . ' ' . $empleado->telefono_emergencia . ($empleado->parentesco_emergencia ? ' (' . $empleado->parentesco_emergencia . ')' : ''))) !!}
            </section>
            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-2"><i class="fas fa-briefcase text-gray-400 mr-1"></i> Datos laborales</h2>
                {!! $dato('Contrato', $empleado->tipo_contrato_nombre) !!}
                {!! $dato('Ingreso', $empleado->fecha_ingreso->format('d/m/Y')) !!}
                {!! $dato('Fin de contrato', $empleado->fecha_fin_contrato?->format('d/m/Y')) !!}
                {!! $dato('Sueldo básico', $empleado->sueldo_basico !== null ? 'S/ ' . number_format((float) $empleado->sueldo_basico, 2) : null) !!}
                {!! $dato('Jornada', substr($empleado->hora_entrada, 0, 5) . ' a ' . substr($empleado->hora_salida, 0, 5)) !!}
                {!! $dato('Pensión', trim(($empleado->sistema_pension ?? '') . ' ' . ($empleado->afp_nombre ?? ''))) !!}
                {!! $dato('CUSPP', $empleado->cuspp) !!}
                {!! $dato('Banco', trim(($empleado->banco ?? '') . ' ' . ($empleado->numero_cuenta ?? ''))) !!}
                {!! $dato('Usuario del sistema', $empleado->usuario ? $empleado->usuario->name . ' (' . $empleado->usuario->role?->nombre . ')' : 'Sin vincular') !!}
                @if($empleado->estado === 'cesado')
                    {!! $dato('Cese', $empleado->fecha_cese?->format('d/m/Y') . ' · ' . $empleado->motivo_cese) !!}
                @endif
                @if($empleado->notas)<p class="mt-3 text-sm text-gray-600 bg-gray-50 rounded-lg p-3">{{ $empleado->notas }}</p>@endif
            </section>
            @if($empleado->estado !== 'cesado')
                <form method="POST" action="{{ route('rrhh.empleados.cesar', $empleado) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-2" data-confirm="¿Registrar el cese de este empleado?">
                    @csrf
                    <h2 class="font-semibold text-gray-900"><i class="fas fa-door-open text-gray-400 mr-1"></i> Registrar cese</h2>
                    <input type="date" name="fecha_cese" value="{{ today()->toDateString() }}" min="{{ $empleado->fecha_ingreso->toDateString() }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <input name="motivo_cese" required maxlength="255" placeholder="Motivo (renuncia, fin de contrato…)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <button class="w-full border border-red-200 text-red-700 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg">Registrar cese</button>
                </form>
            @endif
        </div>

        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100"><i class="fas fa-user-clock text-gray-400 mr-1"></i> Últimas asistencias</h2>
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-left">Entrada</th><th class="px-4 py-2 text-left">Salida</th><th class="px-4 py-2 text-right">Horas</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($ultimas as $a)
                            <tr>
                                <td class="px-4 py-2">{{ $a->fecha->isoFormat('ddd D/M') }}</td>
                                <td class="px-4 py-2"><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Asistencia::ESTADOS[$a->estado][1] }}">{{ $a->estado_nombre }}</span>@if($a->minutos_tarde)<span class="text-xs text-amber-700 ml-1">{{ $a->minutos_tarde }} min</span>@endif</td>
                                <td class="px-4 py-2">{{ $a->hora_entrada ? substr($a->hora_entrada, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2">{{ $a->hora_salida ? substr($a->hora_salida, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $a->horas ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin registros de asistencia todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100"><i class="fas fa-umbrella-beach text-gray-400 mr-1"></i> Vacaciones, permisos y licencias</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse($empleado->solicitudes->take(10) as $s)
                        <li class="px-5 py-2.5 flex flex-wrap justify-between gap-2">
                            <span><strong>{{ $s->tipo_nombre }}</strong> · {{ $s->desde->format('d/m/Y') }} al {{ $s->hasta->format('d/m/Y') }} ({{ $s->dias }} d)@if($s->motivo) <span class="text-gray-400">— {{ $s->motivo }}</span>@endif</span>
                            <span class="text-xs font-semibold {{ ['pendiente' => 'text-amber-700', 'aprobada' => 'text-green-700', 'rechazada' => 'text-red-700'][$s->estado] }}">{{ ucfirst($s->estado) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-gray-400">Sin solicitudes.</li>
                    @endforelse
                </ul>
            </section>

            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100"><i class="fas fa-folder-open text-gray-400 mr-1"></i> Legajo (documentos)</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse($empleado->documentos as $d)
                        <li class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <span><span class="text-xs font-semibold text-gray-500 uppercase mr-2">{{ $d->tipo_nombre }}</span><a href="{{ route('rrhh.documentos.descargar', $d) }}" target="_blank" class="text-blue-700 hover:underline">{{ $d->nombre }}</a>
                                @if($d->vence)<span class="text-xs ml-2 {{ $d->vence->isPast() ? 'text-red-600' : 'text-gray-400' }}">vence {{ $d->vence->format('d/m/Y') }}</span>@endif</span>
                            <form method="POST" action="{{ route('rrhh.documentos.destroy', $d) }}" data-confirm="¿Eliminar este documento?">@csrf @method('DELETE')<button class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></form>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-gray-400">Todavía no hay documentos.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('rrhh.documentos.store', $empleado) }}" enctype="multipart/form-data" class="grid sm:grid-cols-5 gap-2 p-4 bg-gray-50 border-t border-gray-100">
                    @csrf
                    <select name="tipo" class="px-2 py-2 border border-gray-300 rounded-lg text-sm">@foreach(\App\Models\EmpleadoDocumento::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
                    <input name="nombre" maxlength="160" placeholder="Nombre (opcional)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <input type="date" name="vence" title="Vencimiento (opcional)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <input type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" class="sm:col-span-2 text-sm">
                    <button class="sm:col-span-5 sm:justify-self-end bg-gray-800 hover:bg-gray-900 text-white text-sm px-4 py-2 rounded-lg"><i class="fas fa-upload mr-1"></i>Subir documento</button>
                </form>
            </section>
        </div>
    </div>
@endsection
