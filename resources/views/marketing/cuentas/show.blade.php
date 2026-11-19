@extends('layouts.modulo')
@section('titulo', $cuenta->nombre)
@section('subtitulo', $cuenta->red_nombre . ($cuenta->usuario ? ' · ' . $cuenta->usuario : ''))

@section('contenido')
@php use App\Support\Marketing; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
@if(session('avisos'))
    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6 rounded-lg text-sm">
        <p class="font-semibold mb-1">Filas que no se pudieron leer:</p>
        <ul class="list-disc ml-5">@foreach(session('avisos') as $a)<li>{{ $a }}</li>@endforeach</ul>
    </div>
@endif

<a href="{{ route('marketing.cuentas.index') }}" class="inline-block mb-4 text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i>Volver a las cuentas</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-3">Cargar métricas de un día</h2>
            <form method="POST" action="{{ route('marketing.cuentas.metricas.store', $cuenta) }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @csrf
                <div class="col-span-2 md:col-span-4 md:w-1/4"><label class="block text-xs font-semibold text-gray-600 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="{{ $input }}" required>
                    @error('fecha')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                @foreach(['seguidores' => 'Seguidores', 'alcance' => 'Alcance', 'impresiones' => 'Impresiones', 'interacciones' => 'Interacciones', 'reproducciones' => 'Reproducciones', 'clics' => 'Clics', 'publicaciones' => 'Publicaciones'] as $campo => $etq)
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">{{ $etq }}</label>
                        <input type="number" min="0" name="{{ $campo }}" value="{{ old($campo) }}" class="{{ $input }}">
                        @error($campo)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                @endforeach
                <div class="flex items-end"><button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm">Guardar</button></div>
            </form>
            <p class="text-xs text-gray-500 mt-2">Si ya hay datos de esa fecha, se actualizan. Deja vacío lo que no tengas.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-1">Importar desde un archivo</h2>
            <p class="text-sm text-gray-600 mb-3">Sube un CSV o Excel con una fila por día. La primera fila lleva los encabezados:
                <code class="text-xs bg-gray-100 px-1 rounded">fecha, seguidores, alcance, impresiones, interacciones, reproducciones, clics, publicaciones</code> (sirven también en inglés). Fechas como 2026-11-18 o 18/11/2026.</p>
            <form method="POST" action="{{ route('marketing.cuentas.importar', $cuenta) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="archivo" accept=".csv,.txt,.xlsx,.xls" class="text-sm" required>
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white font-semibold px-4 py-2 rounded-lg text-sm"><i class="fas fa-file-import mr-1"></i>Importar</button>
            </form>
            @error('archivo')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <h2 class="font-bold text-gray-800 p-5 pb-3">Historial (últimos 60 registros)</h2>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                    <th class="text-left px-4 py-2">Fecha</th><th class="text-right px-3 py-2">Seguidores</th><th class="text-right px-3 py-2">Alcance</th><th class="text-right px-3 py-2">Impresiones</th>
                    <th class="text-right px-3 py-2">Interacc.</th><th class="text-right px-3 py-2">Reprod.</th><th class="text-right px-3 py-2">Clics</th><th class="text-right px-3 py-2">Publ.</th><th></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($metricas as $m)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $m->fecha->format('d/m/Y') }}</td>
                        @foreach(['seguidores', 'alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics', 'publicaciones'] as $c)<td class="px-3 py-2 text-right">{{ Marketing::numero($m->$c) }}</td>@endforeach
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('marketing.cuentas.metricas.destroy', [$cuenta, $m]) }}" data-confirm="¿Eliminar el registro del {{ $m->fecha->format('d/m/Y') }}?">@csrf @method('DELETE')
                                <button class="text-red-600 hover:text-red-800" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Todavía no hay métricas de esta cuenta.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('marketing.cuentas.update', $cuenta) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">
        @csrf @method('PUT')
        <h2 class="font-bold text-gray-800">Datos de la cuenta</h2>
        @include('marketing.cuentas._campos')
        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="activa" value="1" @checked(old('activa', $cuenta->activa))> Cuenta activa (entra en las estadísticas)</label>
        <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm">Guardar cambios</button>
    </form>
    <form method="POST" action="{{ route('marketing.cuentas.destroy', $cuenta) }}" data-confirm="¿Eliminar la cuenta y todas sus métricas? No se puede deshacer." class="lg:col-start-3">
        @csrf @method('DELETE')
        <button class="text-sm text-red-700 hover:text-red-900"><i class="fas fa-trash mr-1"></i>Eliminar esta cuenta</button>
    </form>
</div>
@endsection
