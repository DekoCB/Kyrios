@extends('layouts.modulo')
@section('titulo', 'Publicaciones')
@section('subtitulo', 'Crea, aprueba y programa el contenido de las redes')

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Publicacion; $input = 'border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white'; @endphp
<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="{{ route('marketing.publicaciones.index') }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('estado') ? 'bg-white text-gray-700 border-gray-300' : 'bg-blue-900 text-white border-blue-900' }}">Todas <span class="opacity-70">{{ $conteo->sum() }}</span></a>
    @foreach(Publicacion::ESTADOS as $k => $v)
        <a href="{{ route('marketing.publicaciones.index', ['estado' => $k] + request()->except('estado', 'page')) }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('estado') === $k ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-700 border-gray-300' }}">{{ $v }} <span class="opacity-70">{{ $conteo[$k] ?? 0 }}</span></a>
    @endforeach
    <div class="ml-auto flex gap-2">
        <a href="{{ route('marketing.publicaciones.calendario') }}" class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 bg-white hover:bg-gray-50"><i class="fas fa-calendar-days mr-1"></i>Calendario</a>
        <a href="{{ route('marketing.publicaciones.create') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-900 hover:bg-blue-800 text-white"><i class="fas fa-plus mr-1"></i>Nueva publicación</a>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="estado" value="{{ request('estado') }}">
    <input name="q" value="{{ request('q') }}" placeholder="Buscar por título" class="{{ $input }}">
    <select name="red" class="{{ $input }}"><option value="">Todas las redes</option>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(request('red') === $k)>{{ $v }}</option>@endforeach</select>
    <button class="px-4 py-1.5 rounded-lg text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50">Filtrar</button>
</form>

<div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
            <th class="text-left px-4 py-3">Publicación</th><th class="text-left px-4 py-3">Red</th><th class="text-left px-4 py-3">Fecha</th><th class="text-left px-4 py-3">Estado</th><th class="text-right px-4 py-3">Interacciones</th><th></th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($publicaciones as $p)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><a href="{{ route('marketing.publicaciones.edit', $p) }}" class="font-semibold text-gray-800 hover:text-blue-700">{{ $p->titulo }}</a>
                    <span class="block text-xs text-gray-400">{{ Publicacion::TIPOS[$p->tipo] ?? $p->tipo }}{{ $p->campana ? ' · ' . $p->campana->nombre : '' }} · por {{ $p->creador?->name ?? '—' }}</span></td>
                <td class="px-4 py-3"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs {{ Marketing::claseRed($p->red) }}"><i class="{{ Marketing::iconoRed($p->red) }}"></i>{{ $p->red_nombre }}</span></td>
                <td class="px-4 py-3 text-gray-600">{{ $p->fecha_calendario?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ Publicacion::COLORES[$p->estado] }}">{{ $p->estado_nombre }}</span></td>
                <td class="px-4 py-3 text-right text-gray-700">{{ Marketing::numero($p->interacciones) }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('marketing.publicaciones.edit', $p) }}" class="text-blue-700 hover:underline">Abrir</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay publicaciones con estos filtros.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $publicaciones->links() }}</div>
@endsection
