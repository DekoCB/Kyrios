@extends('layouts.modulo')
@section('titulo', 'Bandeja de entrada')
@section('subtitulo', 'Comentarios y mensajes de las redes: quién los atiende y qué se respondió')

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Interaccion; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; $filtro = 'border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white'; @endphp
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-3">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <a href="{{ route('marketing.interacciones.index') }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('estado') || request('asignado') ? 'bg-white text-gray-700 border-gray-300' : 'bg-blue-900 text-white border-blue-900' }}">Todas <span class="opacity-70">{{ $conteo->sum() }}</span></a>
            <a href="{{ route('marketing.interacciones.index', ['estado' => 'pendiente']) }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('estado') === 'pendiente' && !request('asignado') ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-700 border-gray-300' }}">Pendientes <span class="opacity-70">{{ $conteo['pendiente'] ?? 0 }}</span></a>
            <a href="{{ route('marketing.interacciones.index', ['estado' => 'atendida']) }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('estado') === 'atendida' ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-700 border-gray-300' }}">Atendidas <span class="opacity-70">{{ $conteo['atendida'] ?? 0 }}</span></a>
            <a href="{{ route('marketing.interacciones.index', ['estado' => 'pendiente', 'asignado' => 'yo']) }}" class="px-3 py-1.5 rounded-full text-sm border {{ request('asignado') === 'yo' ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-700 border-gray-300' }}">Mías pendientes <span class="opacity-70">{{ $mias }}</span></a>
        </div>
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="estado" value="{{ request('estado') }}"><input type="hidden" name="asignado" value="{{ request('asignado') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Buscar persona o texto" class="{{ $filtro }}">
            <select name="red" class="{{ $filtro }}"><option value="">Todas las redes</option>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(request('red') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="tipo" class="{{ $filtro }}"><option value="">Comentarios y mensajes</option>@foreach(Interaccion::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach</select>
            <button class="px-4 py-1.5 rounded-lg text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50">Filtrar</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm divide-y divide-gray-100">
            @forelse($interacciones as $i)
                <a href="{{ route('marketing.interacciones.show', $i) }}" class="flex items-start gap-3 p-4 hover:bg-gray-50">
                    <span class="inline-flex w-9 h-9 rounded-full items-center justify-center shrink-0 {{ Marketing::claseRed($i->red) }}"><i class="{{ Marketing::iconoRed($i->red) }}"></i></span>
                    <span class="flex-1 min-w-0">
                        <span class="flex flex-wrap items-center gap-x-2 text-sm"><strong class="text-gray-800">{{ $i->autor }}</strong>
                            <span class="text-xs text-gray-400 ml-2">{{ Interaccion::TIPOS[$i->tipo] ?? $i->tipo }} · {{ $i->recibida_en->diffForHumans() }}</span></span>
                        <span class="block text-sm text-gray-600 truncate">{{ $i->texto }}</span>
                        <span class="block text-xs text-gray-400 mt-0.5">{{ $i->asignado ? 'Atiende: ' . $i->asignado->name : 'Sin responsable' }}</span>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 {{ $i->estado === 'pendiente' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800' }}">{{ Interaccion::ESTADOS[$i->estado] }}</span>
                </a>
            @empty
                <p class="p-8 text-center text-gray-500">No hay interacciones con estos filtros.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $interacciones->links() }}</div>
    </div>

    <form method="POST" action="{{ route('marketing.interacciones.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">
        @csrf
        <h2 class="font-bold text-gray-800">Registrar interacción</h2>
        <p class="text-xs text-gray-500">Copia aquí el comentario o mensaje que llegó a la red.</p>
        <select name="red" class="{{ $input }}">@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(old('red') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="tipo" class="{{ $input }}">@foreach(Interaccion::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <select name="cuenta_id" class="{{ $input }}"><option value="">Cuenta (opcional)</option>@foreach($cuentas as $c)<option value="{{ $c->id }}">{{ $c->nombre }} · {{ Marketing::nombreRed($c->red) }}</option>@endforeach</select>
        <input name="autor" value="{{ old('autor') }}" class="{{ $input }}" placeholder="Persona (usuario o nombre)" required>
        <textarea name="texto" rows="3" class="{{ $input }}" placeholder="Lo que escribió" required>{{ old('texto') }}</textarea>
        <input type="datetime-local" name="recibida_en" value="{{ old('recibida_en') }}" class="{{ $input }}" title="Cuándo llegó (si lo dejas vacío, ahora)">
        <input name="enlace" value="{{ old('enlace') }}" class="{{ $input }}" placeholder="Enlace (opcional)">
        <select name="asignado_a" class="{{ $input }}"><option value="">Sin responsable</option>@foreach($equipo as $u)<option value="{{ $u->id }}" @selected((int) old('asignado_a') === $u->id)>{{ $u->name }}</option>@endforeach</select>
        @if($errors->any())<ul class="text-xs text-red-600 list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Registrar</button>
    </form>
</div>
@endsection
