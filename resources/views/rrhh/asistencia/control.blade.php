@extends('layouts.modulo')
@section('titulo', 'Control de asistencia')
@section('subtitulo', $fecha->isoFormat('dddd D [de] MMMM [de] YYYY'))

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'asistencia'])

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('rrhh.asistencia.control', ['fecha' => $fecha->copy()->subDay()->toDateString()]) }}" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
        <form method="GET" class="inline"><input type="date" name="fecha" value="{{ $fecha->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></form>
        @unless($fecha->isToday())
            <a href="{{ route('rrhh.asistencia.control', ['fecha' => $fecha->copy()->addDay()->toDateString()]) }}" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
            <a href="{{ route('rrhh.asistencia.control') }}" class="px-3 py-2 text-sm text-blue-700 hover:underline">Hoy</a>
        @endunless
        @if(!$laborable)<span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">Día no laborable</span>@endif
        @if($laborable && $conteo['sin_marcar'] > 0)
            <form method="POST" action="{{ route('rrhh.asistencia.faltas') }}" class="ml-auto" data-confirm="¿Marcar falta a los {{ $conteo['sin_marcar'] }} empleados sin registro de este día?">
                @csrf <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                <button class="px-4 py-2 text-sm border border-red-200 text-red-700 bg-white hover:bg-red-50 rounded-lg"><i class="fas fa-user-xmark mr-1"></i>Marcar falta a quienes no marcaron ({{ $conteo['sin_marcar'] }})</button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500"><p class="text-xs text-gray-500 uppercase">Presentes</p><p class="text-2xl font-bold">{{ $conteo['presentes'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400"><p class="text-xs text-gray-500 uppercase">Tardanzas</p><p class="text-2xl font-bold">{{ $conteo['tardanzas'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-400"><p class="text-xs text-gray-500 uppercase">Ausentes / permisos</p><p class="text-2xl font-bold">{{ $conteo['ausentes'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-300"><p class="text-xs text-gray-500 uppercase">Sin marcar</p><p class="text-2xl font-bold">{{ $conteo['sin_marcar'] }}</p></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrada</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salida</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registrar / corregir</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($empleados as $e)
                    @php $a = $registros->get($e->id); @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3"><a href="{{ route('rrhh.empleados.show', $e) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $e->nombre_completo }}</a><p class="text-xs text-gray-400">{{ $e->cargo }} · {{ substr($e->hora_entrada, 0, 5) }}–{{ substr($e->hora_salida, 0, 5) }}</p></td>
                        <td class="px-4 py-3">
                            @if($a)<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Asistencia::ESTADOS[$a->estado][1] }}">{{ $a->estado_nombre }}</span>@if($a->minutos_tarde)<span class="text-xs text-amber-700 ml-1">{{ $a->minutos_tarde }} min</span>@endif
                            @else<span class="text-xs text-gray-400">Sin marcar</span>@endif
                        </td>
                        <td class="px-4 py-3">{{ $a?->hora_entrada ? substr($a->hora_entrada, 0, 5) : '—' }}</td>
                        <td class="px-4 py-3">{{ $a?->hora_salida ? substr($a->hora_salida, 0, 5) : '—' }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('rrhh.asistencia.registrar', $e) }}" class="flex flex-wrap items-center gap-1" x-data="{ est: '{{ $a?->estado ?? 'puntual' }}' }">
                                @csrf <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                                <select name="estado" x-model="est" class="px-2 py-1 border border-gray-300 rounded text-xs">
                                    @foreach(\App\Models\Asistencia::ESTADOS as $k => [$nombre])<option value="{{ $k }}">{{ $nombre }}</option>@endforeach
                                </select>
                                <template x-if="['puntual','tardanza'].includes(est)"><span class="inline-flex items-center gap-1">
                                    <input type="time" name="hora_entrada" value="{{ $a?->hora_entrada ? substr($a->hora_entrada, 0, 5) : substr($e->hora_entrada, 0, 5) }}" class="px-2 py-1 border border-gray-300 rounded text-xs" title="Entrada">
                                    <input type="time" name="hora_salida" value="{{ $a?->hora_salida ? substr($a->hora_salida, 0, 5) : '' }}" class="px-2 py-1 border border-gray-300 rounded text-xs" title="Salida">
                                </span></template>
                                <input name="observaciones" value="{{ $a?->observaciones }}" maxlength="255" placeholder="Obs." class="px-2 py-1 border border-gray-300 rounded text-xs w-24">
                                <button class="text-xs bg-gray-800 hover:bg-gray-900 text-white px-2 py-1 rounded">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No hay empleados registrados para esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
