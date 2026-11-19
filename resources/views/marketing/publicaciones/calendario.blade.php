@extends('layouts.modulo')
@section('titulo', 'Calendario de contenido')
@section('subtitulo', 'Planifica y programa las publicaciones')

@section('contenido')
@php
    use App\Support\Marketing; use App\Models\Marketing\Publicacion;
    \Carbon\Carbon::setLocale('es');
    $filtros = request()->only(['red', 'estado']);
    $titulo = match ($vista) {
        'mes'    => ucfirst($fecha->translatedFormat('F Y')),
        'semana' => $desde->format('d/m') . ' al ' . $hasta->format('d/m/Y'),
        default  => ucfirst($fecha->translatedFormat('l d \d\e F Y')),
    };
    $input = 'border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white';
    $chip = function ($p) {
        return '<a href="' . route('marketing.publicaciones.edit', $p) . '" class="block rounded px-1.5 py-1 text-xs leading-tight truncate ' . Publicacion::COLORES[$p->estado] . '" title="' . e($p->titulo . ' · ' . $p->estado_nombre) . '">'
            . '<i class="' . Marketing::iconoRed($p->red) . ' mr-1"></i>' . $p->fecha_calendario->format('H:i') . ' ' . e($p->titulo) . '</a>';
    };
@endphp

<div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <a href="{{ route('marketing.publicaciones.calendario', ['vista' => $vista, 'fecha' => $anterior->toDateString()] + $filtros) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50" aria-label="Anterior"><i class="fas fa-chevron-left"></i></a>
    <h2 class="text-lg font-bold text-gray-800 min-w-[10rem] text-center">{{ $titulo }}</h2>
    <a href="{{ route('marketing.publicaciones.calendario', ['vista' => $vista, 'fecha' => $siguiente->toDateString()] + $filtros) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></a>
    <a href="{{ route('marketing.publicaciones.calendario', ['vista' => $vista] + $filtros) }}" class="text-sm text-blue-700 hover:underline">Hoy</a>
    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm" role="group" aria-label="Vista">
        @foreach(['mes' => 'Mes', 'semana' => 'Semana', 'dia' => 'Día'] as $k => $v)
            <a href="{{ route('marketing.publicaciones.calendario', ['vista' => $k, 'fecha' => $fecha->toDateString()] + $filtros) }}" class="px-3 py-1.5 {{ $loop->first ? '' : 'border-l border-gray-300' }} {{ $vista === $k ? 'bg-blue-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">{{ $v }}</a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-2 ml-auto">
        <input type="hidden" name="vista" value="{{ $vista }}"><input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
        <select name="red" class="{{ $input }}"><option value="">Todas las redes</option>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(request('red') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="estado" class="{{ $input }}"><option value="">Todos los estados</option>@foreach(Publicacion::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">Filtrar</button>
    </form>
    <a href="{{ route('marketing.publicaciones.create', ['fecha' => $fecha->toDateString()]) }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Nueva</a>
</div>

<div class="flex flex-wrap gap-2 mb-3 text-xs">
    @foreach(Publicacion::ESTADOS as $k => $v)<span class="px-2 py-0.5 rounded-full {{ Publicacion::COLORES[$k] }}">{{ $v }}</span>@endforeach
</div>

@if($vista === 'mes')
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="grid grid-cols-7 bg-gray-50 text-xs font-semibold text-gray-500 text-center">
            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<div class="py-2">{{ $d }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach($dias as $d)
                @php $delMes = $d->month === $fecha->month; $lista = $items->get($d->toDateString(), collect()); @endphp
                <div class="min-h-[6.5rem] border-t border-l border-gray-100 p-1 {{ $delMes ? '' : 'bg-gray-50 opacity-60' }}">
                    <a href="{{ route('marketing.publicaciones.create', ['fecha' => $d->toDateString()]) }}" class="text-xs {{ $d->isToday() ? 'bg-blue-900 text-white rounded-full px-1.5 py-0.5 font-bold' : 'text-gray-500' }} hover:underline" title="Nueva publicación este día">{{ $d->day }}</a>
                    <div class="space-y-1 mt-1">
                        @foreach($lista->take(3) as $p){!! $chip($p) !!}@endforeach
                        @if($lista->count() > 3)<a href="{{ route('marketing.publicaciones.calendario', ['vista' => 'dia', 'fecha' => $d->toDateString()] + $filtros) }}" class="block text-xs text-blue-700 hover:underline">+{{ $lista->count() - 3 }} más</a>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@elseif($vista === 'semana')
    <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
        @foreach($dias as $d)
            @php $lista = $items->get($d->toDateString(), collect()); @endphp
            <div class="bg-white rounded-xl shadow-sm p-2 min-h-[10rem] {{ $d->isToday() ? 'ring-2 ring-blue-400' : '' }}">
                <a href="{{ route('marketing.publicaciones.calendario', ['vista' => 'dia', 'fecha' => $d->toDateString()] + $filtros) }}" class="block text-xs font-semibold text-gray-600 mb-2 hover:underline">{{ ucfirst($d->translatedFormat('D d/m')) }}</a>
                <div class="space-y-1">@forelse($lista as $p){!! $chip($p) !!}@empty<p class="text-xs text-gray-300">—</p>@endforelse</div>
            </div>
        @endforeach
    </div>
@else
    @php $lista = $items->get($fecha->toDateString(), collect()); @endphp
    <div class="bg-white rounded-xl shadow-sm p-5">
        @forelse($lista as $p)
            <a href="{{ route('marketing.publicaciones.edit', $p) }}" class="flex items-start gap-3 py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                <span class="text-sm font-bold text-gray-700 w-14">{{ $p->fecha_calendario->format('H:i') }}</span>
                <span class="flex-1"><span class="block font-semibold text-gray-800">{{ $p->titulo }}</span>
                    <span class="block text-xs text-gray-500"><i class="{{ Marketing::iconoRed($p->red) }} mr-1"></i>{{ $p->red_nombre }} · {{ Publicacion::TIPOS[$p->tipo] ?? $p->tipo }} · por {{ $p->creador?->name ?? '—' }}</span></span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ Publicacion::COLORES[$p->estado] }}">{{ $p->estado_nombre }}</span>
            </a>
        @empty
            <p class="text-sm text-gray-500 py-6 text-center">No hay publicaciones este día. <a class="text-blue-700 hover:underline" href="{{ route('marketing.publicaciones.create', ['fecha' => $fecha->toDateString()]) }}">Crear una</a></p>
        @endforelse
    </div>
@endif
@endsection
