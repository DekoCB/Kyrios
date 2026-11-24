<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costos y márgenes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">
@php
    $colores = ['bajo' => 'bg-red-50 text-red-700 border-red-200', 'ok' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'sin_minimo' => 'bg-gray-100 text-gray-600 border-gray-200', 'sin_precio' => 'bg-amber-50 text-amber-700 border-amber-200', 'sin_costo' => 'bg-amber-50 text-amber-700 border-amber-200'];
    $soles = fn ($n) => $n === null ? '—' : 'S/ ' . number_format($n, 2);
    $pct = fn ($n) => $n === null ? '—' : number_format($n, 1) . '%';
@endphp

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-6">
    <x-header title="Costos y márgenes" subtitle="Costo promedio ponderado de compra frente al precio de venta, con alerta cuando el margen baja del mínimo" />

    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm"><i class="fas fa-check-circle text-green-500"></i><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ $errors->first() }}</div>
    @endif

    <p class="text-xs text-gray-500 mb-4">
        <b>Costo</b>: promedio ponderado de las compras (en soles, con flete e impuestos de importación prorrateados). <b>Margen</b>: (precio de venta sin IGV − costo) ÷ costo.
        Los precios en dólares se convierten con el tipo de cambio vigente.
    </p>

    {{-- Margen mínimo general --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Margen mínimo general</h2>
        <p class="text-xs text-gray-500 mb-3">Rige para todos los productos que no tengan uno propio. Un producto con margen mínimo propio (columna «Mínimo») usa el suyo. Déjelo vacío para controlar solo los productos con mínimo propio.</p>
        <form method="POST" action="{{ route('precios.costos.margen-general') }}" class="flex flex-wrap items-center gap-x-4 gap-y-3">
            @csrf
            <label for="margen_general" class="sr-only">Margen mínimo general (%)</label>
            <div class="relative"><input id="margen_general" type="number" name="margen_general" step="0.01" min="0" max="1000" value="{{ $general !== null ? rtrim(rtrim(number_format($general, 2, '.', ''), '0'), '.') : '' }}" class="w-32 pr-7 px-3 py-2 border border-gray-200 rounded-lg text-sm"><span class="absolute right-3 top-2 text-gray-400 text-sm">%</span></div>
            <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="aplicar_a_todos" value="1" class="rounded border-gray-300"> Quitar los mínimos propios para que todos usen este</label>
            <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Guardar</button>
        </form>
    </div>

    {{-- Resumen por estado --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        @foreach($estados as $k => $nombre)
            <a href="{{ route('precios.costos.index', array_merge(request()->except('estado', 'page'), request('estado') === $k ? [] : ['estado' => $k])) }}"
               class="bg-white rounded-xl border {{ request('estado') === $k ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-100' }} shadow-sm p-4 hover:border-blue-200 transition-colors">
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">{{ $nombre }}</p>
                <p class="text-2xl font-bold leading-tight {{ $k === 'bajo' && ($resumen[$k] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $resumen[$k] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4 flex flex-wrap gap-3 items-center">
        @if(request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
        <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o código..." class="flex-1 min-w-56 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <select name="categoria_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="">Todas las categorías</option>
            @foreach($categorias as $c)<option value="{{ $c->id }}" @selected(request('categoria_id') == $c->id)>{{ $c->nombre }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"><i class="fas fa-search text-xs mr-1"></i> Buscar</button>
        @if(request()->hasAny(['buscar', 'categoria_id', 'estado']))
            <a href="{{ route('precios.costos.index') }}" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200">Limpiar</a>
        @endif
    </form>

    {{-- Tablero --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-8">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-500"><span class="font-medium text-gray-700">{{ $filas->total() }}</span> productos</p>
            <p class="text-xs text-gray-400">Página {{ $filas->currentPage() }} de {{ $filas->lastPage() }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">Producto</th>
                        <th class="px-4 py-3 text-right">Costo prom.</th>
                        <th class="px-4 py-3 text-right">Último costo</th>
                        <th class="px-4 py-3 text-right">Precio (sin IGV)</th>
                        <th class="px-4 py-3 text-right">Margen</th>
                        <th class="px-4 py-3 text-right">Mínimo</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($filas as $f)
                    <tr class="border-b border-gray-100 hover:bg-blue-50/20">
                        <td class="px-4 py-3">
                            <a href="{{ route('precios.costos.show', $f->producto->id) }}" class="font-semibold text-gray-900 hover:text-blue-700">{{ \App\Support\EtiquetaProducto::nombre($f->producto) }}</a>
                            <div class="text-xs text-gray-400"><span class="font-mono">{{ $f->producto->codigo }}</span>@if($f->producto->categoria) · {{ $f->producto->categoria->nombre }}@endif</div>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $soles($f->costo) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ $soles($f->ultimo) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $soles($f->precio) }}@if($f->con_precio > 1)<div class="text-[11px] text-gray-400">{{ $f->con_precio }} precios</div>@endif</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $f->estado === 'bajo' ? 'text-red-600' : 'text-gray-800' }}">{{ $pct($f->margen) }}@if($f->variante && $f->con_precio > 1)<div class="text-[11px] font-normal text-gray-400" title="Variante con el peor margen">peor: {{ $f->variante }}</div>@endif</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('precios.costos.margen-minimo', $f->producto->id) }}" class="inline-flex items-center gap-1 justify-end">
                                @csrf
                                <input type="number" name="margen_minimo" value="{{ $f->propio ? rtrim(rtrim(number_format($f->minimo, 2, '.', ''), '0'), '.') : '' }}" step="0.01" min="0" max="1000" placeholder="{{ $general !== null ? rtrim(rtrim(number_format($general, 2, '.', ''), '0'), '.') . ' (general)' : '—' }}" title="{{ $f->propio ? 'Mínimo propio' : 'Usa el margen mínimo general' }}" aria-label="Margen mínimo de {{ $f->producto->codigo }}"
                                       class="w-20 px-2 py-1 border border-gray-200 rounded-md text-right text-sm">
                                <button class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600" title="Guardar margen mínimo"><i class="fas fa-check"></i></button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $colores[$f->estado] }}">{{ $estados[$f->estado] }}</span>
                            @if($f->estado === 'bajo' && $f->sugerido)
                                <div class="text-[11px] text-gray-500 mt-1">Sugerido: S/ {{ number_format($f->sugerido['sin_igv'], 2) }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay productos con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $filas->links() }}</div>
    </div>

    {{-- Ventas por debajo del margen --}}
    <h2 class="text-base font-semibold text-gray-800 mb-1">Ventas de los últimos 30 días por debajo del margen mínimo</h2>
    <p class="text-xs text-gray-500 mb-3">Se compara el precio al que se vendió con el costo promedio actual. «Bajo costo» significa que se vendió por menos de lo que costó.</p>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Producto</th>
                        <th class="px-4 py-3 text-right">Cant.</th>
                        <th class="px-4 py-3 text-right">Precio vendido</th>
                        <th class="px-4 py-3 text-right">Costo</th>
                        <th class="px-4 py-3 text-right">Margen</th>
                        <th class="px-4 py-3 text-right">Mínimo</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($ventasBajas as $v)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-2.5 text-gray-600">{{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5"><a href="{{ route('precios.costos.show', $v->producto_id) }}" class="text-gray-900 hover:text-blue-700">{{ $v->nombre }}</a></td>
                        <td class="px-4 py-2.5 text-right">{{ $v->cantidad }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $soles($v->precio) }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-500">{{ $soles((float) $v->costo) }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-red-600">{{ $pct($v->margen) }} @if($v->bajo_costo)<span class="ml-1 text-[11px] font-medium bg-red-100 text-red-700 rounded px-1.5 py-0.5">Bajo costo</span>@endif</td>
                        <td class="px-4 py-2.5 text-right text-gray-500">{{ $v->margen_minimo !== null ? $pct((float) $v->margen_minimo) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Ninguna venta reciente quedó por debajo del margen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
