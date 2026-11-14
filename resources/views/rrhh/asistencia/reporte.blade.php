@extends('layouts.modulo')
@section('titulo', 'Reporte de asistencia')
@section('subtitulo', \Carbon\Carbon::create($anio, $mes, 1)->isoFormat('MMMM [de] YYYY'))

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'reporte'])

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="month" name="mes" value="{{ $mesTexto }}" max="{{ today()->format('Y-m') }}" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <a href="{{ route('rrhh.asistencia.exportar', ['mes' => $mesTexto]) }}" class="ml-auto inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-csv text-green-600"></i> Exportar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Asistidos</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tardanzas</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Min. atraso</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Faltas</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Permisos</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vacaciones</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Desc. médico</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Horas</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($resumen as $r)
                    <tr>
                        <td class="px-4 py-2.5"><a href="{{ route('rrhh.empleados.show', $r['empleado']) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $r['empleado']->nombre_completo }}</a><p class="text-xs text-gray-400">{{ $r['empleado']->cargo }}</p></td>
                        <td class="px-3 py-2.5 text-center font-semibold">{{ $r['asistidos'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['tardanzas'] ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">{{ $r['tardanzas'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['min_tarde'] ? 'text-amber-700' : 'text-gray-400' }}">{{ $r['min_tarde'] }}</td>
                        <td class="px-3 py-2.5 text-center {{ $r['faltas'] ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $r['faltas'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['permisos'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['vacaciones'] }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600">{{ $r['descanso'] }}</td>
                        <td class="px-3 py-2.5 text-right">{{ $r['horas'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500">No hay personal registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
