@extends('layouts.modulo')
@section('titulo', 'Mi asistencia')
@section('subtitulo', 'Marca tu entrada y tu salida del día')

@section('contenido')
    @if(!$empleado)
        <div class="max-w-xl bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5 text-sm">
            <i class="fas fa-circle-info mr-1"></i> Tu usuario todavía no está vinculado a una ficha de personal. Pide a Administración que lo vincule para poder marcar asistencia.
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <p class="text-sm text-gray-500">{{ now()->isoFormat('dddd D [de] MMMM') }}</p>
                    <p class="text-4xl font-bold text-gray-900 my-2" x-data="{ h: '' }" x-init="const t = () => h = new Date().toLocaleTimeString('es-PE', {hour: '2-digit', minute: '2-digit', hour12: false}); t(); setInterval(t, 20000)" x-text="h">{{ now()->format('H:i') }}</p>
                    <p class="text-xs text-gray-400 mb-5">Tu jornada: {{ substr($empleado->hora_entrada, 0, 5) }} a {{ substr($empleado->hora_salida, 0, 5) }} · tolerancia {{ \App\Services\AsistenciaService::tolerancia() }} min</p>

                    @if($hoy && in_array($hoy->estado, ['permiso', 'vacaciones', 'descanso_medico', 'licencia']))
                        <p class="rounded-lg bg-sky-50 text-sky-800 text-sm px-3 py-2">Hoy figuras con <strong>{{ mb_strtolower($hoy->estado_nombre) }}</strong>.</p>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <form method="POST" action="{{ route('rrhh.marcar') }}">@csrf <input type="hidden" name="accion" value="entrada">
                                <button @disabled($hoy?->hora_entrada) class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed"><i class="fas fa-right-to-bracket mr-1"></i>Marcar entrada</button></form>
                            <form method="POST" action="{{ route('rrhh.marcar') }}">@csrf <input type="hidden" name="accion" value="salida">
                                <button @disabled(!$hoy?->hora_entrada || $hoy?->hora_salida) class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"><i class="fas fa-right-from-bracket mr-1"></i>Marcar salida</button></form>
                        </div>
                        @if($hoy?->hora_entrada)
                            <p class="mt-4 text-sm text-gray-600">Entrada <strong>{{ substr($hoy->hora_entrada, 0, 5) }}</strong>@if($hoy->hora_salida) · Salida <strong>{{ substr($hoy->hora_salida, 0, 5) }}</strong>@endif
                                @if($hoy->minutos_tarde)<span class="block text-amber-700 mt-1">Tardanza de {{ $hoy->minutos_tarde }} min</span>@endif</p>
                        @endif
                    @endif
                </div>
                <a href="{{ route('rrhh.mis-solicitudes') }}" class="block bg-white rounded-xl shadow-sm p-4 text-sm text-blue-700 hover:bg-blue-50"><i class="fas fa-umbrella-beach mr-2"></i>Pedir permiso o vacaciones</a>
            </div>

            <section class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Mi historial (últimos 31 registros)</h2>
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-left">Entrada</th><th class="px-4 py-2 text-left">Salida</th><th class="px-4 py-2 text-right">Horas</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($historial as $a)
                            <tr>
                                <td class="px-4 py-2">{{ $a->fecha->isoFormat('ddd D/M') }}</td>
                                <td class="px-4 py-2"><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Asistencia::ESTADOS[$a->estado][1] }}">{{ $a->estado_nombre }}</span></td>
                                <td class="px-4 py-2">{{ $a->hora_entrada ? substr($a->hora_entrada, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2">{{ $a->hora_salida ? substr($a->hora_salida, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $a->horas ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aún no tienes registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    @endif
@endsection
