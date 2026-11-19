@extends('layouts.modulo')
@section('titulo', 'Panel de Marketing')
@section('subtitulo', 'Redes, interacciones, publicaciones y campañas de un vistazo')

@section('contenido')
@php use App\Support\Marketing; @endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('marketing.cuentas.index') }}" class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5 hover:shadow-md transition">
        <p class="text-sm text-gray-500">Redes conectadas</p>
        <p class="text-3xl font-bold text-gray-800">{{ $cuentas->where('estado', 'conectada')->count() }}<span class="text-base font-normal text-gray-400"> / {{ $cuentas->count() }}</span></p>
        <p class="text-xs text-gray-500 mt-1">{{ Marketing::numero($seguidoresTotales) }} seguidores en total</p>
    </a>
    <a href="{{ route('marketing.interacciones.index', ['estado' => 'pendiente']) }}" class="bg-white rounded-xl shadow-sm border-l-4 border-orange-500 p-5 hover:shadow-md transition">
        <p class="text-sm text-gray-500">Interacciones pendientes</p>
        <p class="text-3xl font-bold text-gray-800">{{ $interaccionesPend }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $misInteracciones }} asignadas a mí @if($antiguas)· <span class="text-red-600 font-semibold">{{ $antiguas }} con más de 1 día</span>@endif</p>
    </a>
    <a href="{{ route('marketing.publicaciones.index', ['estado' => 'pendiente']) }}" class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-500 p-5 hover:shadow-md transition">
        <p class="text-sm text-gray-500">Publicaciones por aprobar</p>
        <p class="text-3xl font-bold text-gray-800">{{ $porAprobar }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $publicadasMes }} publicadas este mes @if($atrasadas)· <span class="text-red-600 font-semibold">{{ $atrasadas }} con fecha vencida</span>@endif</p>
    </a>
    <a href="{{ route('marketing.campanas.index') }}" class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-5 hover:shadow-md transition">
        <p class="text-sm text-gray-500">Campañas activas</p>
        <p class="text-3xl font-bold text-gray-800">{{ $campanasActivas->count() }}</p>
        <p class="text-xs text-gray-500 mt-1">Anuncios en curso</p>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-gray-800">Redes: este mes</h2>
            <a href="{{ route('marketing.estadisticas.index') }}" class="text-sm text-blue-700 hover:underline">Ver estadísticas</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-center">
            @foreach([['Alcance', $mes['alcance']], ['Impresiones', $mes['impresiones']], ['Interacciones', $mes['interacciones']], ['Engagement', null]] as [$etq, $val])
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500">{{ $etq }}</p>
                    <p class="text-xl font-bold text-gray-800">{{ $etq === 'Engagement' ? Marketing::porcentaje($mes['engagement']) : Marketing::numero($val) }}</p>
                </div>
            @endforeach
        </div>
        @forelse($cuentas as $c)
            <div class="flex items-center justify-between py-2 border-t border-gray-100 text-sm">
                <a href="{{ route('marketing.cuentas.show', $c) }}" class="flex items-center gap-2 text-gray-800 hover:text-blue-700">
                    <span class="inline-flex w-8 h-8 rounded-full items-center justify-center {{ Marketing::claseRed($c->red) }}"><i class="{{ Marketing::iconoRed($c->red) }}"></i></span>
                    <span>{{ $c->nombre }}<span class="block text-xs text-gray-400">{{ $c->red_nombre }}{{ $c->usuario ? ' · ' . $c->usuario : '' }}</span></span>
                </a>
                <span class="text-right">
                    <span class="font-semibold text-gray-800">{{ Marketing::numero($c->seguidores_actuales) }}</span> <span class="text-xs text-gray-400">seguidores</span>
                    <span class="block text-xs {{ $c->estado === 'conectada' ? 'text-green-600' : 'text-red-600' }}">{{ $c->estado_nombre }}</span>
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-500 py-4 border-t border-gray-100">Todavía no hay cuentas. <a href="{{ route('marketing.cuentas.index') }}" class="text-blue-700 hover:underline">Agrega la primera</a> para empezar a cargar métricas.</p>
        @endforelse
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-gray-800">Próximas publicaciones</h2>
            <a href="{{ route('marketing.publicaciones.calendario') }}" class="text-sm text-blue-700 hover:underline">Calendario</a>
        </div>
        @forelse($proximas as $p)
            <a href="{{ route('marketing.publicaciones.edit', $p) }}" class="block py-2 border-t border-gray-100 hover:bg-gray-50 -mx-2 px-2 rounded">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ $p->titulo }}</p>
                <p class="text-xs text-gray-500"><i class="{{ Marketing::iconoRed($p->red) }} mr-1"></i>{{ $p->programada_para->format('d/m H:i') }}
                    <span class="ml-1 px-1.5 py-0.5 rounded-full {{ \App\Models\Marketing\Publicacion::COLORES[$p->estado] }}">{{ $p->estado_nombre }}</span></p>
            </a>
        @empty
            <p class="text-sm text-gray-500 py-4 border-t border-gray-100">No hay publicaciones aprobadas o programadas próximas.</p>
        @endforelse
        <a href="{{ route('marketing.publicaciones.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-700 hover:underline"><i class="fas fa-plus mr-1"></i>Nueva publicación</a>
    </div>
</div>

@if($campanasActivas->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm p-5">
    <h2 class="font-bold text-gray-800 mb-3">Campañas activas</h2>
    @foreach($campanasActivas as $c)
        <div class="py-2 border-t border-gray-100 text-sm">
            <div class="flex justify-between"><a href="{{ route('marketing.campanas.edit', $c) }}" class="font-semibold text-gray-800 hover:text-blue-700">{{ $c->nombre }}</a>
                <span class="text-gray-600">{{ $c->simbolo }} {{ number_format($c->inversion, 2) }} de {{ number_format($c->presupuesto, 2) }}</span></div>
            <div class="h-2 bg-gray-100 rounded-full mt-1 overflow-hidden"><div class="h-2 rounded-full {{ ($c->avance_presupuesto ?? 0) > 100 ? 'bg-red-500' : 'bg-green-500' }}" style="width: {{ min(100, $c->avance_presupuesto ?? 0) }}%"></div></div>
        </div>
    @endforeach
</div>
@endif
@endsection
