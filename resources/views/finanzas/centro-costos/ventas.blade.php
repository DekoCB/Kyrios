@extends('layouts.modulo')
@section('titulo', 'Centro de costos · Ventas y utilidad')
@section('subtitulo', 'Ingresos, costo de lo vendido y utilidad, en soles y sin IGV')

@section('contenido')
    @include('finanzas.centro-costos._nav', ['activo' => 'ventas'])
    @php
        $s = fn ($n) => ($n < 0 ? '-' : '') . 'S/ ' . number_format(abs((float) $n), 2);
        $t = $d['totales'];
        $c = $d['contable'];
        $pct = fn ($n) => $n === null ? '—' : number_format($n, 1) . ' %';
        $maxMes = max(1, (float) $d['por_mes']->max('ingreso'));
        $tabla = function ($filas, $titulo, $limite = null) use ($s, $pct) {
            return view('finanzas.centro-costos._tabla', compact('filas', 'titulo', 's', 'pct'))->render();
        };
    @endphp

    <form method="GET" class="grid grid-cols-2 lg:grid-cols-6 gap-3 items-end bg-white rounded-xl shadow-sm p-4 mb-5">
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-desde">Desde</label><input id="f-desde" type="date" name="desde" value="{{ $desde }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-hasta">Hasta</label><input id="f-hasta" type="date" name="hasta" value="{{ $hasta }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-tienda">Tienda</label><select id="f-tienda" name="almacen_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Todas</option>@foreach($tiendas as $a)<option value="{{ $a->id }}" @selected($filtros['almacen_id'] === $a->id)>{{ $a->nombre }}</option>@endforeach</select></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-vend">Vendedor</label><select id="f-vend" name="vendedor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Todos</option>@foreach($vendedores as $u)<option value="{{ $u->id }}" @selected($filtros['vendedor_id'] === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1" for="f-cat">Categoría</label><select id="f-cat" name="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Todas</option>@foreach($categorias as $a)<option value="{{ $a->id }}" @selected($filtros['categoria_id'] === $a->id)>{{ $a->nombre }}</option>@endforeach</select></div>
        <div class="flex gap-2"><button class="flex-1 bg-gray-900 hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-lg">Ver</button>
            <a href="{{ route('centro-costos.ventas.exportar', request()->query()) }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50" title="Exportar a CSV"><i class="fas fa-file-csv"></i></a></div>
    </form>

    @if($t['ventas'] === 0)
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-500"><i class="fas fa-chart-line text-4xl text-gray-300 mb-3"></i><p>No hay ventas en este período con esos filtros.</p></div>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500"><p class="text-xs text-gray-500 uppercase">Ingresos</p><p class="text-2xl font-bold">{{ $s($t['ingreso']) }}</p><p class="text-xs text-gray-500">{{ $t['ventas'] }} ventas · ticket {{ $s($t['ticket']) }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-400"><p class="text-xs text-gray-500 uppercase">Costo de ventas</p><p class="text-2xl font-bold">{{ $s($t['costo']) }}</p><p class="text-xs text-gray-500">{{ number_format($t['unidades']) }} unidades</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $t['utilidad'] >= 0 ? 'border-emerald-500' : 'border-red-500' }}"><p class="text-xs text-gray-500 uppercase">Utilidad bruta</p><p class="text-2xl font-bold {{ $t['utilidad'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $s($t['utilidad']) }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500"><p class="text-xs text-gray-500 uppercase">Margen</p><p class="text-2xl font-bold">{{ $pct($t['margen']) }}</p><p class="text-xs text-gray-500">de cada sol vendido queda utilidad</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400"><p class="text-xs text-gray-500 uppercase">Recargo sobre costo</p><p class="text-2xl font-bold">{{ $pct($t['recargo']) }}</p><p class="text-xs text-gray-500">como en Precios</p></div>
        </div>

        @if($t['sin_costo'] > 0 || $t['estimadas'] > 0)
            <p class="text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-2 mb-6"><i class="fas fa-circle-info mr-1"></i>
                @if($t['sin_costo'] > 0)<strong>{{ $t['sin_costo'] }}</strong> de {{ $t['lineas'] }} líneas vendidas no tienen costo (todavía no hay compras de ese producto): se cuentan con costo cero y la utilidad se ve mayor de lo real. Cobertura de costos: {{ $t['cobertura'] }} %.@endif
                @if($t['estimadas'] > 0) {{ $t['estimadas'] }} líneas son anteriores al centro de costos y usan el costo promedio actual; las ventas nuevas guardan su costo del momento.@endif
            </p>
        @endif

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Por mes</h2>
                <div class="space-y-3">
                    @foreach($d['por_mes'] as $r)
                        <div><div class="flex justify-between text-sm"><span class="font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $r['clave'])->isoFormat('MMM YYYY') }}</span>
                            <span><b>{{ $s($r['ingreso']) }}</b> <span class="text-xs text-gray-500">utilidad {{ $s($r['utilidad']) }} · {{ $pct($r['margen']) }}</span></span></div>
                            <div class="h-3 bg-gray-100 rounded-full overflow-hidden flex"><div class="h-full bg-gray-400" style="width: {{ round(min($r['costo'], $r['ingreso']) / $maxMes * 100, 1) }}%" title="Costo"></div><div class="h-full {{ $r['utilidad'] >= 0 ? 'bg-emerald-500' : 'bg-red-500' }}" style="width: {{ round(max(0, $r['utilidad']) / $maxMes * 100, 1) }}%" title="Utilidad"></div></div></div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-3"><span class="inline-block w-2.5 h-2.5 bg-gray-400 rounded-sm align-middle"></span> costo · <span class="inline-block w-2.5 h-2.5 bg-emerald-500 rounded-sm align-middle"></span> utilidad</p>
            </section>

            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Resumen contable del período</h2>
                <dl class="text-sm divide-y divide-gray-100">
                    <div class="flex justify-between py-2"><dt class="text-gray-600">Ventas sin IGV</dt><dd class="font-semibold">{{ $s($t['ingreso']) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-600">(−) Costo de lo vendido</dt><dd class="font-semibold">{{ $s($t['costo']) }}</dd></div>
                    <div class="flex justify-between py-2 bg-gray-50 px-2 -mx-2 rounded"><dt class="font-semibold text-gray-800">Utilidad bruta</dt><dd class="font-bold {{ $t['utilidad'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $s($t['utilidad']) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-600">IGV de las ventas</dt><dd>{{ $s($c['igv_ventas']) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-600">IGV de las compras registradas</dt><dd>{{ $s($c['igv_compras']) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-600">Saldo de IGV (referencial)</dt><dd class="font-semibold {{ $c['saldo_igv'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $s($c['saldo_igv']) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-600">Compras del período (con IGV)</dt><dd>{{ $s($c['compras']) }}</dd></div>
                </dl>
                <p class="text-xs text-gray-500 mt-3">Es utilidad bruta: no descuenta gastos de operación, planilla ni caja chica. El saldo de IGV es una referencia, no la declaración.</p>
            </section>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            {!! $tabla($d['por_categoria'], 'Por categoría') !!}
            {!! $tabla($d['por_tienda'], 'Por tienda') !!}
            {!! $tabla($d['por_vendedor'], 'Por vendedor') !!}
            {!! $tabla($d['por_cliente'], 'Por cliente (los 10 de mayor utilidad)') !!}
        </div>
        {!! $tabla($d['por_producto'], 'Productos de mayor utilidad (los 15 primeros)') !!}

        @if($d['perdidas']->isNotEmpty())
            <section class="bg-white rounded-xl shadow-sm overflow-x-auto mt-6 border border-red-100">
                <h2 class="font-semibold text-red-700 px-5 py-3 border-b border-red-100"><i class="fas fa-triangle-exclamation mr-1"></i>Líneas vendidas por debajo de su costo</h2>
                <table class="min-w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Ingreso</th><th class="px-3 py-2 text-right">Costo</th><th class="px-3 py-2 text-right">Pérdida</th><th class="px-3 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach($d['perdidas'] as $r)<tr><td class="px-4 py-2">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td><td class="px-3 py-2">{{ $r->producto }}</td><td class="px-3 py-2 text-right">{{ $r->cantidad }}</td><td class="px-3 py-2 text-right">{{ $s($r->ingreso) }}</td><td class="px-3 py-2 text-right">{{ $s($r->costo) }}</td><td class="px-3 py-2 text-right text-red-600 font-semibold">{{ $s($r->ingreso - $r->costo) }}</td><td class="px-3 py-2 text-right"><a href="{{ route('ventas.show', $r->venta_id) }}" class="text-blue-700 hover:underline">Ver venta</a></td></tr>@endforeach</tbody></table>
            </section>
        @endif
    @endif
@endsection
