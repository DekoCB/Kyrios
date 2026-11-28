@extends('layouts.modulo')
@section('titulo', 'Centro de costos · Importaciones')
@section('subtitulo', 'Cuánto cuesta realmente traer cada producto desde su sitio de origen, en soles')

@section('contenido')
    @include('finanzas.centro-costos._nav', ['activo' => 'importaciones'])
    @php
        $s = fn ($n) => 'S/ ' . number_format((float) $n, 2);
        $t = $d['totales'];
        $nombres = ['mercaderia' => ['Mercadería (precio de fábrica)', 'bg-blue-600'], 'flete' => ['Flete', 'bg-sky-500'], 'seguro' => ['Seguro', 'bg-cyan-500'], 'impuestos' => ['Impuestos y aduana', 'bg-amber-500'],
            'transporte' => ['Transporte local', 'bg-emerald-500'], 'percepcion' => ['Percepción', 'bg-purple-500'], 'otros' => ['Otros gastos', 'bg-gray-400']];
        $maxConcepto = max(1, ...array_values($d['conceptos']));
    @endphp

    <form method="GET" class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end bg-white rounded-xl shadow-sm p-4 mb-5">
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-desde">Desde</label><input id="f-desde" type="date" name="desde" value="{{ $desde }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-hasta">Hasta</label><input id="f-hasta" type="date" name="hasta" value="{{ $hasta }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <div class="col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1" for="f-prov">Proveedor</label>
            <select id="f-prov" name="proveedor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Todos</option>@foreach($proveedores as $p)<option value="{{ $p->id }}" @selected($filtros['proveedor_id'] === $p->id)>{{ $p->nombre_comercial ?: $p->razon_social }}{{ $p->pais ? ' · ' . $p->pais : '' }}</option>@endforeach</select></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-pais">País de origen</label>
            <select id="f-pais" name="pais" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Todos</option>@foreach($paises as $p)<option value="{{ $p }}" @selected($filtros['pais'] === $p)>{{ $p }}</option>@endforeach</select></div>
        <div class="flex gap-2"><button class="flex-1 bg-gray-900 hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-lg">Ver</button>
            <a href="{{ route('centro-costos.importaciones.exportar', request()->query()) }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50" title="Exportar a CSV"><i class="fas fa-file-csv"></i></a></div>
    </form>

    @if($t['compras'] === 0)
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-500"><i class="fas fa-ship text-4xl text-gray-300 mb-3"></i><p>No hay importaciones registradas en este período.</p>
            <p class="text-xs mt-1">Las importaciones salen de las compras de tipo «Importación». Cambie el período o los filtros.</p></div>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><p class="text-xs text-gray-500 uppercase">Importaciones</p><p class="text-2xl font-bold">{{ $t['compras'] }}</p><p class="text-xs text-gray-500">{{ number_format($t['unidades']) }} unidades</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-sky-500"><p class="text-xs text-gray-500 uppercase">Mercadería (fábrica)</p><p class="text-2xl font-bold">{{ $s($t['fob_pen']) }}</p><p class="text-xs text-gray-500">US$ {{ number_format($t['fob_usd'], 2) }} · T.C. {{ $t['tc_promedio'] }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-500"><p class="text-xs text-gray-500 uppercase">Gastos de importación</p><p class="text-2xl font-bold">{{ $s($t['gastos']) }}</p><p class="text-xs text-gray-500">{{ $t['fob_pen'] > 0 ? number_format($t['gastos'] / $t['fob_pen'] * 100, 1) : 0 }} % sobre la mercadería</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500"><p class="text-xs text-gray-500 uppercase">Costo puesto en almacén</p><p class="text-2xl font-bold">{{ $s($t['costo_total']) }}</p><p class="text-xs text-gray-500">Factor ×{{ $t['factor'] }} · {{ $s($t['costo_unitario']) }} por unidad</p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">¿En qué se va el costo?</h2>
                <div class="space-y-2.5">
                    @foreach($nombres as $k => [$etq, $color])
                        @php $v = (float) ($d['conceptos'][$k] ?? 0); @endphp
                        <div><div class="flex justify-between text-sm"><span class="text-gray-700">{{ $etq }}</span><span class="font-semibold">{{ $s($v) }} <span class="text-xs font-normal text-gray-400">{{ $t['costo_total'] > 0 ? number_format($v / $t['costo_total'] * 100, 1) : 0 }} %</span></span></div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full {{ $color }} rounded-full" style="width: {{ round($v / $maxConcepto * 100, 1) }}%"></div></div></div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-3">El costo puesto en almacén es lo que realmente costó cada línea una vez repartidos los gastos; si difiere de la suma de los conceptos, la diferencia son gastos no desglosados.</p>
            </section>

            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Por país de origen</h2>
                <table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">País</th><th class="px-3 py-2 text-right">Compras</th><th class="px-3 py-2 text-right">Mercadería</th><th class="px-3 py-2 text-right">Gastos</th><th class="px-3 py-2 text-right">Costo total</th><th class="px-3 py-2 text-right" title="Costo total ÷ mercadería">Factor</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach($d['por_pais'] as $r)<tr><td class="px-4 py-2 font-medium">{{ $r['pais'] }}</td><td class="px-3 py-2 text-right">{{ $r['compras'] }}</td><td class="px-3 py-2 text-right">{{ $s($r['fob_pen']) }}</td><td class="px-3 py-2 text-right">{{ $s($r['gastos']) }}</td><td class="px-3 py-2 text-right font-semibold">{{ $s($r['costo_total']) }}</td><td class="px-3 py-2 text-right">×{{ $r['factor'] }}</td></tr>@endforeach</tbody></table>
            </section>
        </div>

        <section class="bg-white rounded-xl shadow-sm overflow-x-auto mb-6">
            <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Cada importación</h2>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Compra</th><th class="px-3 py-2 text-left">Origen</th><th class="px-3 py-2 text-right">Mercadería</th><th class="px-3 py-2 text-right">Flete</th><th class="px-3 py-2 text-right">Seguro</th><th class="px-3 py-2 text-right">Impuestos</th><th class="px-3 py-2 text-right">Transp.</th><th class="px-3 py-2 text-right">Percep.</th><th class="px-3 py-2 text-right">Costo total</th><th class="px-3 py-2 text-right">Por unidad</th><th class="px-3 py-2 text-right">Factor</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($d['filas'] as $r)
                        <tr><td class="px-4 py-2 whitespace-nowrap">{{ $r['fecha']->format('d/m/Y') }}</td>
                            <td class="px-3 py-2"><a href="{{ route('compras.show', $r['compra']) }}" class="font-mono text-blue-700 hover:underline">{{ $r['compra']->codigo }}</a></td>
                            <td class="px-3 py-2">{{ $r['proveedor'] }}<span class="block text-xs text-gray-400">{{ collect([$r['ciudad'], $r['pais']])->filter()->implode(', ') }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $s($r['fob_pen']) }}<span class="block text-xs text-gray-400">US$ {{ number_format($r['fob_usd'], 2) }}</span></td>
                            @foreach(['flete', 'seguro', 'impuestos', 'transporte', 'percepcion'] as $k)<td class="px-3 py-2 text-right {{ $r['partes'][$k] ? '' : 'text-gray-300' }}">{{ $r['partes'][$k] ? $s($r['partes'][$k]) : '—' }}</td>@endforeach
                            <td class="px-3 py-2 text-right font-semibold">{{ $s($r['costo_total']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $r['costo_unitario'] !== null ? $s($r['costo_unitario']) : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $r['factor'] ? '×' . $r['factor'] : '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Por proveedor</h2>
                <table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Proveedor</th><th class="px-3 py-2 text-right">Compras</th><th class="px-3 py-2 text-right">Costo total</th><th class="px-3 py-2 text-right">Factor</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach($d['por_proveedor'] as $r)<tr><td class="px-4 py-2">{{ $r['etiqueta'] }}<span class="block text-xs text-gray-400">{{ $r['pais'] }}</span></td><td class="px-3 py-2 text-right">{{ $r['compras'] }}</td><td class="px-3 py-2 text-right font-semibold">{{ $s($r['costo_total']) }}</td><td class="px-3 py-2 text-right">×{{ $r['factor'] }}</td></tr>@endforeach</tbody></table>
            </section>
            <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Evolución por mes</h2>
                <table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Mes</th><th class="px-3 py-2 text-right">Compras</th><th class="px-3 py-2 text-right">Mercadería</th><th class="px-3 py-2 text-right">Gastos</th><th class="px-3 py-2 text-right">Costo total</th><th class="px-3 py-2 text-right">Factor</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach($d['por_mes'] as $mes => $r)<tr><td class="px-4 py-2 font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $mes)->isoFormat('MMM YYYY') }}</td><td class="px-3 py-2 text-right">{{ $r['compras'] }}</td><td class="px-3 py-2 text-right">{{ $s($r['fob_pen']) }}</td><td class="px-3 py-2 text-right">{{ $s($r['gastos']) }}</td><td class="px-3 py-2 text-right font-semibold">{{ $s($r['costo_total']) }}</td><td class="px-3 py-2 text-right">×{{ $r['factor'] }}</td></tr>@endforeach</tbody></table>
            </section>
        </div>

        @if($d['productos']->isNotEmpty())
            <section class="bg-white rounded-xl shadow-sm overflow-x-auto mt-6">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Productos con mayor sobrecosto <span class="text-sm font-normal text-gray-500">(costo en almacén frente al precio de fábrica, por unidad)</span></h2>
                <table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Unidades</th><th class="px-3 py-2 text-right">Precio de fábrica</th><th class="px-3 py-2 text-right">Costo en almacén</th><th class="px-3 py-2 text-right">Sobrecosto</th><th class="px-3 py-2 text-right">%</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach($d['productos']->take(15) as $r)<tr><td class="px-4 py-2">{{ $r->nombre }}<span class="block text-xs font-mono text-gray-400">{{ $r->codigo }}</span></td><td class="px-3 py-2 text-right">{{ number_format($r->unidades) }}</td><td class="px-3 py-2 text-right">{{ $s($r->fob_unitario) }}</td><td class="px-3 py-2 text-right">{{ $s($r->costo_unitario) }}</td><td class="px-3 py-2 text-right text-amber-700">{{ $s($r->sobrecosto) }}</td><td class="px-3 py-2 text-right font-semibold">{{ $r->porcentaje !== null ? $r->porcentaje . ' %' : '—' }}</td></tr>@endforeach</tbody></table>
            </section>
        @endif
    @endif
@endsection
