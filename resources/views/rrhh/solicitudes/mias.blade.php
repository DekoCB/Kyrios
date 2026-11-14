@extends('layouts.modulo')
@section('titulo', 'Mis solicitudes')
@section('subtitulo', 'Pide vacaciones, permisos o licencias; Administración las aprueba')

@section('contenido')
    @if(!$empleado)
        <div class="max-w-xl bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5 text-sm">
            <i class="fas fa-circle-info mr-1"></i> Tu usuario todavía no está vinculado a una ficha de personal. Pide a Administración que lo vincule.
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-6">
            <form method="POST" action="{{ route('rrhh.solicitudes.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 self-start">
                @csrf
                <h2 class="font-semibold text-gray-900">Nueva solicitud</h2>
                <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">@foreach(\App\Models\SolicitudRrhh::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="text-xs text-gray-500">Desde</label><input type="date" name="desde" required min="{{ today()->subMonth()->toDateString() }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                    <div><label class="text-xs text-gray-500">Hasta</label><input type="date" name="hasta" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                </div>
                <input name="motivo" maxlength="255" placeholder="Motivo (opcional)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Enviar solicitud</button>
            </form>

            <section class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Mis solicitudes</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse($solicitudes as $s)
                        <li class="px-5 py-3 flex flex-wrap justify-between gap-2">
                            <span><strong>{{ $s->tipo_nombre }}</strong> · {{ $s->desde->format('d/m/Y') }} al {{ $s->hasta->format('d/m/Y') }} ({{ $s->dias }} d)@if($s->motivo)<span class="text-gray-400"> — {{ $s->motivo }}</span>@endif
                                @if($s->motivo_rechazo)<span class="block text-xs text-red-600">Motivo del rechazo: {{ $s->motivo_rechazo }}</span>@endif</span>
                            <span class="text-xs font-semibold self-start px-2 py-1 rounded-full {{ ['pendiente' => 'bg-amber-100 text-amber-800', 'aprobada' => 'bg-green-100 text-green-800', 'rechazada' => 'bg-red-100 text-red-800'][$s->estado] }}">{{ ucfirst($s->estado) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-gray-400">Todavía no has hecho solicitudes.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
@endsection
