{{-- Tarjetas y tabla de resultados por cuenta. Variables: $filas, $totales, $anteriorTotales (opcional), $est --}}
@php
    use App\Support\Marketing;
    $anteriorTotales = $anteriorTotales ?? null;
    $kpis = [
        ['seguidores', 'Seguidores', 'fa-users', 'n'],
        ['crecimiento', 'Crecimiento', 'fa-arrow-trend-up', 'n'],
        ['alcance', 'Alcance', 'fa-bullseye', 'n'],
        ['impresiones', 'Impresiones', 'fa-eye', 'n'],
        ['interacciones', 'Interacciones', 'fa-heart', 'n'],
        ['reproducciones', 'Reproducciones', 'fa-play', 'n'],
        ['clics', 'Clics', 'fa-arrow-pointer', 'n'],
        ['engagement', 'Engagement', 'fa-percent', 'p'],
    ];
@endphp
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach($kpis as [$campo, $etq, $icono, $fmt])
        @php
            $valor = $totales[$campo] ?? null;
            $var = $anteriorTotales && $campo !== 'crecimiento' && $campo !== 'seguidores' ? $est->variacion($valor, $anteriorTotales[$campo] ?? null) : null;
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500"><i class="fas {{ $icono }} mr-1"></i>{{ $etq }}</p>
            <p class="text-2xl font-bold text-gray-800">
                @if($campo === 'crecimiento' && $valor !== null)<span class="{{ $valor >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $valor > 0 ? '+' : '' }}{{ Marketing::numero($valor) }}</span>
                @else{{ $fmt === 'p' ? Marketing::porcentaje($valor) : Marketing::numero($valor) }}@endif
            </p>
            @if($var !== null)<p class="text-xs {{ $var >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $var >= 0 ? '▲' : '▼' }} {{ abs($var) }} % vs. período anterior</p>
            @elseif($anteriorTotales && !in_array($campo, ['crecimiento', 'seguidores'], true))<p class="text-xs text-gray-400">Sin dato anterior para comparar</p>@endif
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl shadow-sm overflow-x-auto mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
            <th class="text-left px-4 py-3">Cuenta</th><th class="text-right px-3 py-3">Seguidores</th><th class="text-right px-3 py-3">Crec.</th><th class="text-right px-3 py-3">Alcance</th>
            <th class="text-right px-3 py-3">Impresiones</th><th class="text-right px-3 py-3">Interacc.</th><th class="text-right px-3 py-3">Reprod.</th><th class="text-right px-3 py-3">Clics</th><th class="text-right px-3 py-3">Engag.</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($filas as $f)
            <tr>
                <td class="px-4 py-2"><span class="flex items-center gap-2"><span class="inline-flex w-7 h-7 rounded-full items-center justify-center {{ Marketing::claseRed($f['cuenta']->red) }}"><i class="{{ Marketing::iconoRed($f['cuenta']->red) }} text-xs"></i></span>
                    <span>{{ $f['cuenta']->nombre }}@unless($f['tiene_datos'])<span class="block text-xs text-gray-400">Sin datos en este período</span>@endunless</span></span></td>
                <td class="px-3 py-2 text-right font-semibold">{{ Marketing::numero($f['seguidores']) }}</td>
                <td class="px-3 py-2 text-right {{ ($f['crecimiento'] ?? 0) < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $f['crecimiento'] === null ? '—' : ($f['crecimiento'] > 0 ? '+' : '') . Marketing::numero($f['crecimiento']) }}</td>
                @foreach(['alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics'] as $c)<td class="px-3 py-2 text-right">{{ Marketing::numero($f[$c]) }}</td>@endforeach
                <td class="px-3 py-2 text-right">{{ Marketing::porcentaje($f['engagement']) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No hay cuentas para mostrar. <a href="{{ route('marketing.cuentas.index') }}" class="text-blue-700 hover:underline">Agrega una cuenta</a> y carga sus métricas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
