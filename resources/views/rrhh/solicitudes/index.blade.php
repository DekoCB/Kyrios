@extends('layouts.modulo')
@section('titulo', 'Solicitudes de personal')
@section('subtitulo', 'Vacaciones, permisos, licencias y descansos médicos')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'solicitudes'])

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'todas' => 'Todas'] as $k => $t)
            <a href="{{ route('rrhh.solicitudes.index', ['estado' => $k]) }}" class="px-4 py-1.5 text-sm rounded-full border {{ $estado === $k ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $t }}@if($k === 'pendiente' && $pendientes) ({{ $pendientes }})@endif</a>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-3">
            @forelse($solicitudes as $s)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex flex-wrap justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $s->empleado?->nombre_completo }} <span class="font-normal text-gray-500">· {{ $s->tipo_nombre }}</span></p>
                            <p class="text-sm text-gray-600">{{ $s->desde->format('d/m/Y') }} al {{ $s->hasta->format('d/m/Y') }} ({{ $s->dias }} día{{ $s->dias > 1 ? 's' : '' }})@if($s->motivo) — {{ $s->motivo }}@endif</p>
                        </div>
                        <span class="text-xs font-semibold self-start px-2 py-1 rounded-full {{ ['pendiente' => 'bg-amber-100 text-amber-800', 'aprobada' => 'bg-green-100 text-green-800', 'rechazada' => 'bg-red-100 text-red-800'][$s->estado] }}">{{ ucfirst($s->estado) }}</span>
                    </div>
                    @if($s->estado === 'pendiente')
                        <div class="mt-3 flex flex-wrap items-start gap-3">
                            <form method="POST" action="{{ route('rrhh.solicitudes.aprobar', $s) }}" data-confirm="¿Aprobar esta solicitud? Los días quedarán registrados en la asistencia.">@csrf
                                <button class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"><i class="fas fa-check mr-1"></i>Aprobar</button></form>
                            <form method="POST" action="{{ route('rrhh.solicitudes.rechazar', $s) }}" class="flex flex-wrap gap-2 flex-1 min-w-[14rem]">@csrf
                                <input name="motivo" required minlength="3" maxlength="255" placeholder="Motivo del rechazo" class="flex-1 min-w-[9rem] px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <button class="px-4 py-2 text-sm font-medium text-red-700 border border-red-300 rounded-lg hover:bg-red-50"><i class="fas fa-xmark mr-1"></i>Rechazar</button></form>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-gray-500">Resuelta por {{ $s->resolutor?->name ?? '—' }} el {{ $s->resuelta_en?->format('d/m/Y H:i') }}@if($s->motivo_rechazo) · Motivo: {{ $s->motivo_rechazo }}@endif</p>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm px-5 py-10 text-center text-sm text-gray-500">No hay solicitudes en esta vista.</div>
            @endforelse
            {{ $solicitudes->links() }}
        </div>

        <form method="POST" action="{{ route('rrhh.solicitudes.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 self-start">
            @csrf
            <h2 class="font-semibold text-gray-900">Registrar una solicitud</h2>
            <select name="empleado_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Empleado…</option>@foreach($empleados as $e)<option value="{{ $e->id }}">{{ $e->nombre_completo }}</option>@endforeach</select>
            <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">@foreach(\App\Models\SolicitudRrhh::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
            <div class="grid grid-cols-2 gap-2"><input type="date" name="desde" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm"><input type="date" name="hasta" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <input name="motivo" maxlength="255" placeholder="Motivo (opcional)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Registrar (queda pendiente)</button>
        </form>
    </div>
@endsection
