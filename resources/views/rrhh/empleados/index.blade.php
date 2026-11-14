@extends('layouts.modulo')
@section('titulo', 'Recursos humanos')
@section('subtitulo', 'Personal de Kyrios: fichas, contratos y datos de contacto')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'personal'])

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500"><p class="text-xs text-gray-500 uppercase">Personal activo</p><p class="text-2xl font-bold text-gray-900">{{ $resumen['activos'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-sky-500"><p class="text-xs text-gray-500 uppercase">En licencia</p><p class="text-2xl font-bold text-gray-900">{{ $resumen['licencia'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400"><p class="text-xs text-gray-500 uppercase">Contratos por vencer (30 días)</p><p class="text-2xl font-bold text-gray-900">{{ $resumen['por_vencer'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-pink-400"><p class="text-xs text-gray-500 uppercase">Cumpleaños este mes</p><p class="text-2xl font-bold text-gray-900">{{ $resumen['cumpleanos_mes'] }}</p></div>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, DNI o cargo…" class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64">
        <select name="area" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">Todas las áreas</option>
            @foreach(\App\Models\Empleado::AREAS as $a)<option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>@endforeach
        </select>
        <select name="estado" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="vigentes" @selected(request('estado', 'vigentes') === 'vigentes')>Vigentes</option>
            @foreach(\App\Models\Empleado::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg">Buscar</button>
        <a href="{{ route('rrhh.empleados.create') }}" class="ml-auto inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-user-plus"></i> Nuevo empleado</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cargo / área</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contrato</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingreso</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($empleados as $e)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center text-xs font-bold">{{ $e->iniciales }}</span>
                                <div>
                                    <a href="{{ route('rrhh.empleados.show', $e) }}" class="font-semibold text-gray-900 hover:text-blue-700">{{ $e->nombre_completo }}</a>
                                    <p class="text-xs text-gray-400">{{ $e->codigo }} · DNI {{ $e->dni }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $e->cargo }}<p class="text-xs text-gray-400">{{ $e->area ?: '—' }}</p></td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $e->tipo_contrato_nombre }}
                            @if($e->contrato_por_vencer)<p class="text-xs text-amber-700"><i class="fas fa-triangle-exclamation mr-1"></i>Vence {{ $e->fecha_fin_contrato->format('d/m/Y') }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $e->fecha_ingreso->format('d/m/Y') }}<p class="text-xs text-gray-400">{{ $e->antiguedad }}</p></td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ['activo' => 'bg-green-100 text-green-800', 'licencia' => 'bg-sky-100 text-sky-800', 'cesado' => 'bg-gray-200 text-gray-600'][$e->estado] }}">{{ \App\Models\Empleado::ESTADOS[$e->estado] }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('rrhh.empleados.show', $e) }}" class="text-blue-600 hover:text-blue-800 mr-2" title="Ver ficha"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('rrhh.empleados.edit', $e) }}" class="text-yellow-600 hover:text-yellow-800" title="Editar"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Todavía no hay empleados registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $empleados->links() }}</div>
@endsection
