@extends('layouts.modulo')
@section('titulo', 'Estadísticas')
@section('subtitulo', $etiqueta)

@section('contenido')
@php use App\Support\Marketing; @endphp
@include('marketing._filtro_periodo', ['ruta' => 'marketing.estadisticas.index', 'conCuenta' => true])

@if($anterior)<p class="text-sm text-gray-500 mb-3">Comparado con {{ $anterior['desde']->format('d/m/Y') }} al {{ $anterior['hasta']->format('d/m/Y') }}.</p>@endif

@include('marketing._resumen', ['anteriorTotales' => $anteriorTotales])
@include('marketing._grafico_seguidores')

<div class="bg-white rounded-xl shadow-sm p-5">
    <h2 class="font-bold text-gray-800 mb-3">Publicaciones con mejores resultados</h2>
    @forelse($mejores as $p)
        <a href="{{ route('marketing.publicaciones.edit', $p) }}" class="flex items-center gap-3 py-2 border-t border-gray-100 hover:bg-gray-50 -mx-2 px-2 rounded text-sm">
            <span class="inline-flex w-8 h-8 rounded-full items-center justify-center shrink-0 {{ Marketing::claseRed($p->red) }}"><i class="{{ Marketing::iconoRed($p->red) }}"></i></span>
            <span class="flex-1 min-w-0"><span class="block font-semibold text-gray-800 truncate">{{ $p->titulo }}</span><span class="text-xs text-gray-400">{{ $p->publicada_en->format('d/m/Y') }} · {{ $p->red_nombre }}</span></span>
            <span class="text-right text-xs text-gray-500"><strong class="text-gray-800 text-sm">{{ Marketing::numero($p->interacciones) }}</strong> interacciones<br>alcance {{ Marketing::numero($p->alcance) }} · engagement {{ Marketing::porcentaje($p->engagement) }}</span>
        </a>
    @empty
        <p class="text-sm text-gray-500">Aún no hay publicaciones publicadas con resultados cargados en este período. Al marcar una publicación como publicada puedes cargar su alcance e interacciones.</p>
    @endforelse
</div>
@endsection
