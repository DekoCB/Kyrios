{{-- Selector de período para estadísticas y reportes. Variables: $ruta, $tipo, $desde, $hasta (y opcional $cuentas, $conCuenta, $exportar) --}}
@php $f = 'border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white'; $exportar = $exportar ?? false; $conCuenta = $conCuenta ?? false; @endphp
<form method="GET" action="{{ route($ruta) }}" class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3" x-data="{ tipo: '{{ $tipo }}' }">
    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Período</label>
        <select name="periodo" x-model="tipo" class="{{ $f }}">
            <option value="semana">Semanal</option><option value="mes">Mensual</option><option value="rango">Fechas a elegir</option>
        </select></div>
    <div x-show="tipo !== 'rango'"><label class="block text-xs font-semibold text-gray-600 mb-1">Semana o mes que contiene</label>
        <input type="date" name="fecha" value="{{ request('fecha', now()->toDateString()) }}" class="{{ $f }}"></div>
    <div x-show="tipo === 'rango'" x-cloak><label class="block text-xs font-semibold text-gray-600 mb-1">Desde</label>
        <input type="date" name="desde" value="{{ request('desde', $desde->toDateString()) }}" class="{{ $f }}"></div>
    <div x-show="tipo === 'rango'" x-cloak><label class="block text-xs font-semibold text-gray-600 mb-1">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta', $hasta->toDateString()) }}" class="{{ $f }}"></div>
    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
        <select name="red" class="{{ $f }}"><option value="">Todas</option>@foreach(\App\Support\Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(request('red') === $k)>{{ $v }}</option>@endforeach</select></div>
    @if($conCuenta)
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Cuenta</label>
            <select name="cuenta" class="{{ $f }}"><option value="">Todas</option>@foreach($cuentas as $c)<option value="{{ $c->id }}" @selected((int) request('cuenta') === $c->id)>{{ $c->nombre }} ({{ \App\Support\Marketing::nombreRed($c->red) }})</option>@endforeach</select></div>
    @endif
    <label class="flex items-center gap-2 text-sm text-gray-700 pb-2"><input type="checkbox" name="comparar" value="1" @checked(request()->boolean('comparar'))> Comparar con el período anterior</label>
    <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-5 py-2 rounded-lg text-sm">Ver</button>
    @if($exportar)
        <span class="ml-auto flex gap-2">
            <a href="{{ route('marketing.reportes.pdf', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm font-semibold border border-red-300 text-red-700 hover:bg-red-50"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            <a href="{{ route('marketing.reportes.excel', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm font-semibold border border-green-300 text-green-700 hover:bg-green-50"><i class="fas fa-file-excel mr-1"></i>Excel</a>
        </span>
    @endif
</form>
