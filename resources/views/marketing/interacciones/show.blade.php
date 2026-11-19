@extends('layouts.modulo')
@section('titulo', 'Interacción de ' . $interaccion->autor)
@section('subtitulo', $interaccion->red_nombre . ' · ' . \App\Models\Marketing\Interaccion::TIPOS[$interaccion->tipo])

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Interaccion; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; $i = $interaccion; @endphp
<a href="{{ route('marketing.interacciones.index') }}" class="inline-block mb-4 text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i>Volver a la bandeja</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="flex items-center gap-3 mb-3">
                <span class="inline-flex w-10 h-10 rounded-full items-center justify-center {{ Marketing::claseRed($i->red) }}"><i class="{{ Marketing::iconoRed($i->red) }}"></i></span>
                <div class="flex-1"><p class="font-bold text-gray-800">{{ $i->autor }}</p>
                    <p class="text-xs text-gray-500">{{ $i->recibida_en->format('d/m/Y H:i') }}{{ $i->cuenta ? ' · en ' . $i->cuenta->nombre : '' }}{{ $i->publicacion ? ' · sobre «' . $i->publicacion->titulo . '»' : '' }}</p></div>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $i->estado === 'pendiente' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800' }}">{{ Interaccion::ESTADOS[$i->estado] }}</span>
            </div>
            <p class="text-gray-800 whitespace-pre-line bg-gray-50 rounded-lg p-4">{{ $i->texto }}</p>
            @if($i->enlace)<a href="{{ $i->enlace }}" target="_blank" rel="noopener" class="text-sm text-blue-700 hover:underline mt-2 inline-block"><i class="fas fa-arrow-up-right-from-square mr-1"></i>Abrir en la red</a>@endif
        </div>

        @if($i->estado === 'atendida')
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-2">Respuesta dada</h2>
                <p class="text-gray-800 whitespace-pre-line bg-green-50 rounded-lg p-4">{{ $i->respuesta }}</p>
                <p class="text-xs text-gray-500 mt-2">Por {{ $i->respondedor?->name ?? '—' }} el {{ $i->respondida_en?->format('d/m/Y H:i') }}</p>
                <form method="POST" action="{{ route('marketing.interacciones.reabrir', $i) }}" class="mt-3">@csrf
                    <button class="text-sm text-orange-700 hover:text-orange-900"><i class="fas fa-rotate-left mr-1"></i>Volver a pendientes</button></form>
            </div>
        @else
            <form method="POST" action="{{ route('marketing.interacciones.responder', $i) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3">@csrf
                <h2 class="font-bold text-gray-800">Registrar la respuesta</h2>
                <p class="text-xs text-gray-500">Responde desde la red y deja aquí lo que contestaste; la interacción pasa a atendida.</p>
                <textarea name="respuesta" rows="4" class="{{ $input }}" required>{{ old('respuesta') }}</textarea>
                @error('respuesta')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2 rounded-lg text-sm"><i class="fas fa-check mr-1"></i>Marcar como atendida</button>
            </form>
        @endif
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('marketing.interacciones.asignar', $i) }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3">@csrf
            <h2 class="font-bold text-gray-800">Responsable</h2>
            <select name="asignado_a" class="{{ $input }}"><option value="">Sin responsable</option>@foreach($equipo as $u)<option value="{{ $u->id }}" @selected($i->asignado_a === $u->id)>{{ $u->name }}</option>@endforeach</select>
            <button class="w-full border border-blue-300 text-blue-700 hover:bg-blue-50 font-semibold py-2 rounded-lg text-sm">Asignar</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-2">Historial con {{ $i->autor }}</h2>
            @forelse($historial as $h)
                <a href="{{ route('marketing.interacciones.show', $h) }}" class="block py-2 border-t border-gray-100 text-sm hover:bg-gray-50">
                    <span class="text-xs text-gray-400">{{ $h->recibida_en->format('d/m/Y') }} · {{ Interaccion::ESTADOS[$h->estado] }}</span>
                    <span class="block text-gray-700 truncate">{{ $h->texto }}</span></a>
            @empty
                <p class="text-sm text-gray-500">Es la primera vez que esta persona escribe.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('marketing.interacciones.destroy', $i) }}" data-confirm="¿Eliminar esta interacción?">@csrf @method('DELETE')
            <button class="text-sm text-red-700 hover:text-red-900"><i class="fas fa-trash mr-1"></i>Eliminar</button></form>
    </div>
</div>
@endsection
