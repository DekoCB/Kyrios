@extends('layouts.modulo')
@section('titulo', 'Asistencia por trabajador')
@section('subtitulo', $empleado ? $empleado->nombre_completo . ' · ' . \Carbon\Carbon::create($anio, $mes, 1)->isoFormat('MMMM [de] YYYY') : 'Elija a una persona para ver su mes, día por día')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'trabajador'])

    @php $soles = fn ($n) => ($n < 0 ? '-' : '') . 'S/ ' . number_format(abs($n), 2); @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-5">
        <select name="empleado" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm min-w-[240px]" aria-label="Trabajador">
            <option value="">— Elija un trabajador —</option>
            @foreach($empleados as $e)<option value="{{ $e->id }}" @selected($empleado?->id === $e->id)>{{ $e->nombre_completo }} ({{ $e->cargo }})</option>@endforeach
        </select>
        <input type="month" name="mes" value="{{ $mesTexto }}" max="{{ today()->format('Y-m') }}" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        @if($empleado)<a href="{{ route('rrhh.empleados.show', $empleado) }}" class="ml-auto text-sm text-blue-700 hover:underline"><i class="fas fa-id-card mr-1"></i>Ver ficha</a>@endif
    </form>

    @if(!$empleado)
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-500"><i class="fas fa-user-check text-4xl text-gray-300 mb-3"></i><p>Elija un trabajador para ver cada día del mes con su entrada, salida, tardanza, horas extra y ubicación.</p></div>
    @else
        {{-- Lo que significa el mes en plata --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-300"><p class="text-xs text-gray-500 uppercase">Sueldo básico</p><p class="text-xl font-bold">{{ $ajuste['sueldo'] > 0 ? $soles($ajuste['sueldo']) : '—' }}</p><p class="text-[11px] text-gray-400">Hora: {{ $soles($ajuste['valor_hora']) }} · día: {{ $soles($ajuste['valor_dia']) }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400"><p class="text-xs text-gray-500 uppercase">Tardanzas</p><p class="text-xl font-bold">{{ $ajuste['tardanzas'] }} <span class="text-sm font-normal text-gray-500">({{ $ajuste['min_tarde'] }} min)</span></p><p class="text-sm text-red-600">{{ $ajuste['desc_tardanzas'] ? $soles(-$ajuste['desc_tardanzas']) : 'Sin descuento' }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-400"><p class="text-xs text-gray-500 uppercase">Faltas</p><p class="text-xl font-bold">{{ $ajuste['faltas'] }}</p><p class="text-sm text-red-600">{{ $ajuste['desc_faltas'] ? $soles(-$ajuste['desc_faltas']) : 'Sin descuento' }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500"><p class="text-xs text-gray-500 uppercase">Horas extra</p><p class="text-xl font-bold">{{ $ajuste['horas_extra'] }} h</p><p class="text-sm text-emerald-700">{{ $ajuste['bono_extra'] ? $soles($ajuste['bono_extra']) : 'Sin bono' }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><p class="text-xs text-gray-500 uppercase">Sueldo estimado</p><p class="text-xl font-bold">{{ $ajuste['sueldo'] > 0 ? $soles($ajuste['sueldo_estimado']) : '—' }}</p><p class="text-sm {{ $ajuste['neto'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">Ajuste {{ $soles($ajuste['neto']) }}</p></div>
        </div>
        @if($ajuste['sueldo'] <= 0)
            <p class="text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-2 mb-4"><i class="fas fa-circle-info mr-1"></i>Esta persona no tiene sueldo básico en su ficha, así que los descuentos y bonos salen en cero. <a href="{{ route('rrhh.empleados.edit', $empleado) }}" class="underline">Completar la ficha</a>.</p>
        @endif

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    @foreach(['Fecha', 'Estado', 'Entrada', 'Salida', 'Horas', 'Atraso', 'Extra', 'Ubicación y foto'] as $t)<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($registros as $a)
                        <tr class="align-top">
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ $a->fecha->isoFormat('ddd D/M') }}</td>
                            <td class="px-4 py-2.5"><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Asistencia::ESTADOS[$a->estado][1] }}">{{ $a->estado_nombre }}</span>@if($a->observaciones)<p class="text-[11px] text-gray-400 mt-0.5">{{ $a->observaciones }}</p>@endif</td>
                            <td class="px-4 py-2.5">{{ $a->hora_entrada ? substr($a->hora_entrada, 0, 5) : '—' }}</td>
                            <td class="px-4 py-2.5">{{ $a->hora_salida ? substr($a->hora_salida, 0, 5) : '—' }}</td>
                            <td class="px-4 py-2.5">{{ $a->horas ?? '—' }}</td>
                            <td class="px-4 py-2.5 {{ $a->minutos_tarde ? 'text-amber-700' : 'text-gray-400' }}">{{ $a->minutos_tarde ? $a->minutos_tarde . ' min' : '—' }}</td>
                            <td class="px-4 py-2.5 {{ $a->minutos_extra ? 'text-emerald-700 font-semibold' : 'text-gray-400' }}">{{ $a->minutos_extra ? intdiv($a->minutos_extra, 60) . ' h ' . ($a->minutos_extra % 60) . ' min' : '—' }}</td>
                            <td class="px-4 py-2.5">@include('rrhh.asistencia._marcaciones', ['a' => $a])</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">Sin registros en este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
