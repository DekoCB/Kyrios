@extends('layouts.modulo')
@section('titulo', $competidor->nombre)
@section('subtitulo', $competidor->red_nombre . ($competidor->usuario ? ' · ' . $competidor->usuario : ''))

@section('contenido')
@php use App\Support\Marketing; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<a href="{{ route('marketing.competencia.index') }}" class="inline-block mb-4 text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i>Volver a la competencia</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-3">Registrar datos de una fecha</h2>
            <form method="POST" action="{{ route('marketing.competencia.metricas.store', $competidor) }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">@csrf
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="{{ $input }}" required></div>
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Seguidores</label><input type="number" min="0" name="seguidores" value="{{ old('seguidores') }}" class="{{ $input }}"></div>
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Publicaciones del período</label><input type="number" min="0" name="publicaciones" value="{{ old('publicaciones') }}" class="{{ $input }}"></div>
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Interacciones</label><input type="number" min="0" name="interacciones" value="{{ old('interacciones') }}" class="{{ $input }}"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Publicación destacada</label><input name="destacada" value="{{ old('destacada') }}" class="{{ $input }}" placeholder="Qué publicó y funcionó bien"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Enlace de la destacada</label><input name="enlace_destacada" value="{{ old('enlace_destacada') }}" class="{{ $input }}" placeholder="https://…"></div>
                <div class="col-span-2 md:col-span-4"><button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-5 py-2 rounded-lg text-sm">Guardar</button></div>
            </form>
            @if($errors->any())<ul class="text-xs text-red-600 list-disc ml-4 mt-2">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
            <p class="text-xs text-gray-500 mt-2">Si ya hay datos de esa fecha, se actualizan.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <h2 class="font-bold text-gray-800 p-5 pb-3">Historial</h2>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr><th class="text-left px-4 py-2">Fecha</th><th class="text-right px-3 py-2">Seguidores</th><th class="text-right px-3 py-2">Publicaciones</th><th class="text-right px-3 py-2">Interacciones</th><th class="text-left px-3 py-2">Destacada</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($metricas as $m)
                    <tr><td class="px-4 py-2 font-medium">{{ $m->fecha->format('d/m/Y') }}</td><td class="px-3 py-2 text-right">{{ Marketing::numero($m->seguidores) }}</td>
                        <td class="px-3 py-2 text-right">{{ Marketing::numero($m->publicaciones) }}</td><td class="px-3 py-2 text-right">{{ Marketing::numero($m->interacciones) }}</td>
                        <td class="px-3 py-2">@if($m->enlace_destacada)<a href="{{ $m->enlace_destacada }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ $m->destacada ?: 'Ver' }}</a>@else{{ $m->destacada }}@endif</td>
                        <td class="px-3 py-2 text-right"><form method="POST" action="{{ route('marketing.competencia.metricas.destroy', [$competidor, $m]) }}" data-confirm="¿Eliminar este registro?">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button></form></td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aún no hay datos de esta cuenta.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('marketing.competencia.update', $competidor) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">@csrf @method('PUT')
            <h2 class="font-bold text-gray-800">Datos de la cuenta</h2>
            @include('marketing.competencia._campos')
            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="activo" value="1" @checked(old('activo', $competidor->activo))> Seguir esta cuenta</label>
            <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm">Guardar cambios</button>
        </form>
        <form method="POST" action="{{ route('marketing.competencia.destroy', $competidor) }}" data-confirm="¿Eliminar este competidor y todo su historial?">@csrf @method('DELETE')
            <button class="text-sm text-red-700 hover:text-red-900"><i class="fas fa-trash mr-1"></i>Eliminar competidor</button></form>
    </div>
</div>
@endsection
