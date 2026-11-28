@extends('layouts.modulo')
@section('titulo', 'Evaluaciones del personal')
@section('subtitulo', 'Notas de desempeño de 0 a 20. La nota orienta, pero el aumento lo decide Administración')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'evaluaciones'])

    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Última nota</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Promedio</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Evaluaciones</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Último periodo</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sueldo básico</th>
                <th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($empleados as $e)
                    @php $ult = $e->evaluaciones->first(); @endphp
                    <tr>
                        <td class="px-4 py-2.5"><a href="{{ route('rrhh.evaluaciones.show', $e) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $e->nombre_completo }}</a><p class="text-xs text-gray-400">{{ $e->cargo }}</p></td>
                        <td class="px-4 py-2.5 text-center">@if($ult)<span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full {{ \App\Models\EvaluacionEmpleado::nivel($ult->nota)[1] }}">{{ number_format($ult->nota, 2) }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-4 py-2.5 text-center text-gray-700">{{ $e->evaluaciones->isNotEmpty() ? number_format($e->evaluaciones->avg('nota'), 2) : '—' }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ $e->evaluaciones->count() }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $ult?->periodo ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $e->sueldo_basico > 0 ? 'S/ ' . number_format((float) $e->sueldo_basico, 2) : '—' }}</td>
                        <td class="px-4 py-2.5 text-right"><a href="{{ route('rrhh.evaluaciones.show', $e) }}" class="text-sm text-blue-700 hover:underline">Evaluar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No hay personal registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
