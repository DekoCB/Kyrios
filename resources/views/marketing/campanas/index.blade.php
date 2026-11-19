@extends('layouts.modulo')
@section('titulo', 'Campañas publicitarias')
@section('subtitulo', 'Anuncios: presupuesto, inversión y resultados')

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Campana; $filtro = 'border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white'; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    @foreach($porMoneda as $moneda => $t)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-500 mb-2">Totales en {{ Campana::MONEDAS[$moneda] ?? $moneda }} ({{ $moneda }})</p>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div><p class="text-xs text-gray-500">Presupuesto</p><p class="text-lg font-bold">{{ number_format($t['presupuesto'], 2) }}</p></div>
                <div><p class="text-xs text-gray-500">Invertido</p><p class="text-lg font-bold">{{ number_format($t['inversion'], 2) }}</p></div>
                <div><p class="text-xs text-gray-500">ROAS global</p><p class="text-lg font-bold">{{ $t['inversion'] > 0 && $t['ingresos'] > 0 ? round($t['ingresos'] / $t['inversion'], 2) . 'x' : '—' }}</p></div>
            </div>
        </div>
    @endforeach
</div>

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="estado" class="{{ $filtro }}"><option value="">Todos los estados</option>@foreach(Campana::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="red" class="{{ $filtro }}"><option value="">Todas las redes</option>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(request('red') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="px-4 py-1.5 rounded-lg text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50">Filtrar</button>
    </form>
    <a href="{{ route('marketing.campanas.create') }}" class="ml-auto bg-blue-900 hover:bg-blue-800 text-white font-semibold px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Nueva campaña</a>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
            <th class="text-left px-4 py-3">Campaña</th><th class="text-left px-3 py-3">Estado</th><th class="text-right px-3 py-3">Presupuesto</th><th class="text-right px-3 py-3">Invertido</th>
            <th class="text-right px-3 py-3">Impresiones</th><th class="text-right px-3 py-3">Alcance</th><th class="text-right px-3 py-3">Clics</th><th class="text-right px-3 py-3">CPC</th><th class="text-right px-3 py-3">Conv.</th><th class="text-right px-3 py-3">ROAS</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($campanas as $c)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><a href="{{ route('marketing.campanas.edit', $c) }}" class="font-semibold text-gray-800 hover:text-blue-700">{{ $c->nombre }}</a>
                    <span class="block text-xs text-gray-400">{{ $c->red_nombre }}{{ $c->fecha_inicio ? ' · ' . $c->fecha_inicio->format('d/m/Y') . ($c->fecha_fin ? ' al ' . $c->fecha_fin->format('d/m/Y') : '') : '' }} · {{ $c->publicaciones_count }} publicación(es)</span></td>
                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ Campana::COLORES[$c->estado] }}">{{ Campana::ESTADOS[$c->estado] }}</span></td>
                <td class="px-3 py-3 text-right">{{ $c->simbolo }} {{ number_format($c->presupuesto, 2) }}</td>
                <td class="px-3 py-3 text-right">{{ $c->simbolo }} {{ number_format($c->inversion, 2) }}@if($c->avance_presupuesto !== null)<span class="block text-xs {{ $c->avance_presupuesto > 100 ? 'text-red-600' : 'text-gray-400' }}">{{ $c->avance_presupuesto }} %</span>@endif</td>
                <td class="px-3 py-3 text-right">{{ Marketing::numero($c->impresiones) }}</td><td class="px-3 py-3 text-right">{{ Marketing::numero($c->alcance) }}</td><td class="px-3 py-3 text-right">{{ Marketing::numero($c->clics) }}</td>
                <td class="px-3 py-3 text-right">{{ $c->cpc === null ? '—' : $c->simbolo . ' ' . number_format($c->cpc, 2) }}</td>
                <td class="px-3 py-3 text-right">{{ Marketing::numero($c->conversiones) }}</td>
                <td class="px-3 py-3 text-right font-semibold">{{ $c->roas === null ? '—' : $c->roas . 'x' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">Aún no registras campañas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
