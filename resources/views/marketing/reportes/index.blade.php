@extends('layouts.modulo')
@section('titulo', 'Reportes de Marketing')
@section('subtitulo', $etiqueta)

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Publicacion; use App\Models\Marketing\Campana; @endphp
@include('marketing._filtro_periodo', ['ruta' => 'marketing.reportes.index', 'exportar' => true])

@if($anterior)<p class="text-sm text-gray-500 mb-3">Comparado con {{ $anterior['etiqueta'] }}.</p>@endif

@include('marketing._resumen', ['anteriorTotales' => $anterior['totales'] ?? null])
@include('marketing._grafico_seguidores')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-bold text-gray-800 mb-3">Publicaciones del período por estado</h2>
        @foreach(Publicacion::ESTADOS as $k => $v)
            <div class="flex justify-between py-1.5 border-t border-gray-100 text-sm"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ Publicacion::COLORES[$k] }}">{{ $v }}</span><strong>{{ $estados[$k] ?? 0 }}</strong></div>
        @endforeach
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-bold text-gray-800 mb-3">Mejores publicaciones</h2>
        @forelse($mejores as $p)
            <div class="py-1.5 border-t border-gray-100 text-sm flex justify-between gap-3"><span class="truncate">{{ $p->titulo }} <span class="text-xs text-gray-400">· {{ $p->red_nombre }}</span></span><strong class="shrink-0">{{ Marketing::numero($p->interacciones) }}</strong></div>
        @empty
            <p class="text-sm text-gray-500">Sin resultados cargados en el período.</p>
        @endforelse
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <h2 class="font-bold text-gray-800 p-5 pb-3">Campañas del período</h2>
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
            <th class="text-left px-4 py-2">Campaña</th><th class="text-left px-3 py-2">Estado</th><th class="text-right px-3 py-2">Presupuesto</th><th class="text-right px-3 py-2">Inversión</th>
            <th class="text-right px-3 py-2">Impresiones</th><th class="text-right px-3 py-2">Clics</th><th class="text-right px-3 py-2">CPC</th><th class="text-right px-3 py-2">Conv.</th><th class="text-right px-3 py-2">ROAS</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($campanas as $c)
            <tr><td class="px-4 py-2">{{ $c->nombre }}<span class="block text-xs text-gray-400">{{ $c->red_nombre }}</span></td>
                <td class="px-3 py-2">{{ Campana::ESTADOS[$c->estado] }}</td>
                <td class="px-3 py-2 text-right">{{ $c->simbolo }} {{ number_format($c->presupuesto, 2) }}</td><td class="px-3 py-2 text-right">{{ $c->simbolo }} {{ number_format($c->inversion, 2) }}</td>
                <td class="px-3 py-2 text-right">{{ Marketing::numero($c->impresiones) }}</td><td class="px-3 py-2 text-right">{{ Marketing::numero($c->clics) }}</td>
                <td class="px-3 py-2 text-right">{{ $c->cpc === null ? '—' : $c->simbolo . ' ' . number_format($c->cpc, 2) }}</td>
                <td class="px-3 py-2 text-right">{{ Marketing::numero($c->conversiones) }}</td><td class="px-3 py-2 text-right">{{ $c->roas === null ? '—' : $c->roas . 'x' }}</td></tr>
        @empty
            <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">No hubo campañas en este período.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
