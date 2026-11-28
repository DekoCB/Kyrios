@extends('layouts.modulo')
@section('titulo', 'Asistencia por mes')
@section('subtitulo', \Carbon\Carbon::create($anio, $mes, 1)->isoFormat('MMMM [de] YYYY'))

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'reporte'])

    @php
        $codigo = ['puntual' => ['P', 'bg-green-100 text-green-800'], 'tardanza' => ['T', 'bg-amber-100 text-amber-800'], 'falta' => ['F', 'bg-red-100 text-red-800'], 'permiso' => ['Pe', 'bg-sky-100 text-sky-800'],
            'vacaciones' => ['V', 'bg-indigo-100 text-indigo-800'], 'descanso_medico' => ['DM', 'bg-purple-100 text-purple-800'], 'licencia' => ['L', 'bg-gray-200 text-gray-700']];
        $soles = fn ($n) => ($n < 0 ? '-' : '') . 'S/ ' . number_format(abs($n), 2);
    @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="month" name="mes" value="{{ $mesTexto }}" max="{{ today()->format('Y-m') }}" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <a href="{{ route('rrhh.asistencia.exportar', ['mes' => $mesTexto]) }}" class="ml-auto inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-csv"></i> Exportar CSV</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm overflow-x-auto mb-6">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Asistidos</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tardanzas</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Min. atraso</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Faltas</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Permisos</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vacac.</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Desc. méd.</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Horas</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">H. extra</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Descuentos</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bono extra</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase" title="Sueldo básico + bono − descuentos">Sueldo estimado</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($resumen as $r)
                    @php $p = $r['planilla']; @endphp
                    <tr>
                        <td class="px-4 py-2.5"><a href="{{ route('rrhh.asistencia.trabajador', ['empleado' => $r['empleado']->id, 'mes' => $mesTexto]) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $r['empleado']->nombre_completo }}</a><p class="text-xs text-gray-400">{{ $r['empleado']->cargo }}</p></td>
                        <td class="px-3 py-2.5 text-center font-semibold">{{ $r['asistidos'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['tardanzas'] ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">{{ $r['tardanzas'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['min_tarde'] ? 'text-amber-700' : 'text-gray-400' }}">{{ $r['min_tarde'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['faltas'] ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $r['faltas'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['permisos'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['vacaciones'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['descanso'] }}</td>
                        <td class="px-3 py-2.5 text-right">{{ $r['horas'] }}</td>
                        <td class="px-3 py-2.5 text-right {{ $p['horas_extra'] ? 'text-emerald-700 font-semibold' : 'text-gray-400' }}">{{ $p['horas_extra'] }}</td>
                        <td class="px-3 py-2.5 text-right {{ $p['descuentos'] ? 'text-red-600' : 'text-gray-400' }}">{{ $p['descuentos'] ? $soles(-$p['descuentos']) : '—' }}</td>
                        <td class="px-3 py-2.5 text-right {{ $p['bono_extra'] ? 'text-emerald-700' : 'text-gray-400' }}">{{ $p['bono_extra'] ? $soles($p['bono_extra']) : '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold">{{ $p['sueldo'] > 0 ? $soles($p['sueldo_estimado']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="px-6 py-12 text-center text-gray-500">No hay personal registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-500 mb-6">El sueldo estimado es sueldo básico − descuentos + bono por horas extra, según las reglas de cada ficha. No reemplaza la boleta de pago.</p>

    {{-- Calendario del mes --}}
    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">Calendario del mes</h2>
    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="text-xs border-collapse">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 min-w-[160px]">Empleado</th>
                @for($d = 1; $d <= $diasMes; $d++)
                    @php $f = \Carbon\Carbon::create($anio, $mes, $d); @endphp
                    <th class="px-1 py-2 text-center font-medium {{ $f->isWeekend() ? 'text-gray-300' : 'text-gray-500' }}">{{ $d }}<span class="block text-[9px] font-normal">{{ mb_substr($f->isoFormat('dd'), 0, 1) }}</span></th>
                @endfor
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($resumen as $r)
                    <tr>
                        <td class="px-3 py-1.5 sticky left-0 bg-white font-medium text-gray-800">{{ $r['empleado']->nombre_completo }}</td>
                        @for($d = 1; $d <= $diasMes; $d++)
                            @php $estado = $calendario[$r['empleado']->id][$d] ?? null; @endphp
                            <td class="px-0.5 py-1 text-center">@if($estado)<span title="{{ \App\Models\Asistencia::ESTADOS[$estado][0] }}" class="inline-flex w-6 h-6 items-center justify-center rounded text-[10px] font-bold {{ $codigo[$estado][1] }}">{{ $codigo[$estado][0] }}</span>@else<span class="text-gray-200">·</span>@endif</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-500 mt-2">P puntual · T tardanza · F falta · Pe permiso · V vacaciones · DM descanso médico · L licencia · · sin registro</p>
@endsection
