@extends('layouts.modulo')
@section('titulo', 'Competencia')
@section('subtitulo', 'Seguimiento y comparativa de otras marcas')

@section('contenido')
@php use App\Support\Marketing; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<div class="bg-blue-50 border border-blue-200 text-blue-900 text-sm rounded-lg px-4 py-3 mb-6">
    <i class="fas fa-circle-info mr-1"></i>Las redes no entregan por API los datos de otras cuentas. Aquí se <strong>registran a mano</strong> (por ejemplo, una vez por semana, o desde la competencia de Metricool) y el sistema calcula el crecimiento y la comparativa.
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                    <th class="text-left px-4 py-3">Marca</th><th class="text-right px-3 py-3">Seguidores</th><th class="text-right px-3 py-3">Crecimiento</th><th class="text-right px-3 py-3">Publ. del período</th><th class="text-right px-3 py-3">Interacciones</th><th class="text-left px-3 py-3">Último dato</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($filas as $f)
                    @php $c = $f['competidor']; $u = $f['ultima']; @endphp
                    <tr class="{{ $c->activo ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3"><a href="{{ route('marketing.competencia.show', $c) }}" class="flex items-center gap-2 font-semibold text-gray-800 hover:text-blue-700">
                            <span class="inline-flex w-8 h-8 rounded-full items-center justify-center {{ Marketing::claseRed($c->red) }}"><i class="{{ Marketing::iconoRed($c->red) }}"></i></span>
                            <span>{{ $c->nombre }}<span class="block text-xs font-normal text-gray-400">{{ $c->red_nombre }}{{ $c->usuario ? ' · ' . $c->usuario : '' }}</span></span></a></td>
                        <td class="px-3 py-3 text-right font-semibold">{{ Marketing::numero($u?->seguidores) }}</td>
                        <td class="px-3 py-3 text-right {{ ($f['crecimiento'] ?? 0) < 0 ? 'text-red-600' : 'text-green-600' }}">
                            @if($f['crecimiento'] === null)—@else{{ $f['crecimiento'] > 0 ? '+' : '' }}{{ Marketing::numero($f['crecimiento']) }}@if($f['crec_pct'] !== null) <span class="text-xs">({{ $f['crec_pct'] }} %)</span>@endif @endif</td>
                        <td class="px-3 py-3 text-right">{{ Marketing::numero($u?->publicaciones) }}</td>
                        <td class="px-3 py-3 text-right">{{ Marketing::numero($u?->interacciones) }}</td>
                        <td class="px-3 py-3 text-gray-500">{{ $u?->fecha->format('d/m/Y') ?? 'Sin datos' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aún no registras cuentas competidoras.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($propias->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-1">Nosotros, por red</h2>
            <p class="text-xs text-gray-500 mb-3">Seguidores actuales de nuestras cuentas y publicaciones de los últimos 30 días, para comparar con la tabla de arriba.</p>
            @foreach($propias as $red => $p)
                <div class="flex items-center justify-between py-2 border-t border-gray-100 text-sm">
                    <span><i class="{{ Marketing::iconoRed($red) }} mr-2"></i>{{ Marketing::nombreRed($red) }}</span>
                    <span><strong>{{ Marketing::numero($p['seguidores']) }}</strong> seguidores · <strong>{{ $p['publicaciones'] }}</strong> publicaciones (30 días)</span>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    <form method="POST" action="{{ route('marketing.competencia.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">
        @csrf
        <h2 class="font-bold text-gray-800">Registrar competidor</h2>
        @include('marketing.competencia._campos', ['competidor' => new \App\Models\Marketing\Competidor()])
        <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Registrar</button>
    </form>
</div>
@endsection
