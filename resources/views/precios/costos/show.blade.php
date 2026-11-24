<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costos de {{ \App\Support\EtiquetaProducto::nombre($producto) }} - {{ config('app.name') }}</title>
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
    $th = 'px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide';
@endphp

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-6">
    <x-header :title="\App\Support\EtiquetaProducto::nombre($producto)" subtitle="Costos de compra, proveedores y margen de venta" />

    <div class="flex flex-wrap items-center gap-3 mb-4 text-sm">
        <a href="{{ route('precios.costos.index') }}" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left text-xs"></i> Costos y márgenes</a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('precios.show', $producto) }}" class="text-blue-600 hover:underline">Gestionar precios de este producto</a>
        <span class="font-mono text-xs text-gray-400">{{ $producto->codigo }}</span>
    </div>

    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm"><i class="fas fa-check-circle text-green-500"></i><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"><p class="text-xs text-gray-400 uppercase font-medium">Costo promedio</p><p class="text-xl font-bold text-gray-900">{{ $soles($costo) }}</p><p class="text-[11px] text-gray-400">ponderado por cantidad</p></div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"><p class="text-xs text-gray-400 uppercase font-medium">Último costo</p><p class="text-xl font-bold text-gray-900">{{ $soles($ultimo) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"><p class="text-xs text-gray-400 uppercase font-medium">Precio sin IGV</p><p class="text-xl font-bold text-gray-900">{{ $soles($precio) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"><p class="text-xs text-gray-400 uppercase font-medium">Margen real{{ $conPrecio > 1 ? ' (peor variante)' : '' }}</p><p class="text-xl font-bold {{ $estado === 'bajo' ? 'text-red-600' : 'text-gray-900' }}">{{ $pct($margen) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"><p class="text-xs text-gray-400 uppercase font-medium">Estado</p>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border mt-1 {{ $colores[$estado] }}">{{ $estados[$estado] }}</span></div>
    </div>

    {{-- Margen mínimo --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Margen mínimo de este producto</h2>
        <p class="text-xs text-gray-500 mb-3">Si el margen real baja de este porcentaje sobre el costo, Administración y Logística reciben un aviso. Déjelo vacío para que use el margen general{{ $general !== null ? ' (' . rtrim(rtrim(number_format($general, 2, '.', ''), '0'), '.') . '%)' : ' (hoy desactivado)' }}.</p>
        <form method="POST" action="{{ route('precios.costos.margen-minimo', $producto) }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <label for="margen_minimo" class="sr-only">Margen mínimo (%)</label>
            <div class="relative"><input id="margen_minimo" type="number" name="margen_minimo" step="0.01" min="0" max="1000" value="{{ $producto->margen_minimo !== null ? rtrim(rtrim(number_format((float) $producto->margen_minimo, 2, '.', ''), '0'), '.') : '' }}" placeholder="{{ $general !== null ? rtrim(rtrim(number_format($general, 2, '.', ''), '0'), '.') : '' }}" class="w-32 pr-7 px-3 py-2 border border-gray-200 rounded-lg text-sm"><span class="absolute right-3 top-2 text-gray-400 text-sm">%</span></div>
            <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Guardar</button>
            @if($sugerido)
                <span class="text-sm text-gray-600">Para ganar {{ rtrim(rtrim(number_format($minimo, 2, '.', ''), '0'), '.') }}% hay que vender a <b>S/ {{ number_format($sugerido['sin_igv'], 2) }}</b> sin IGV (<b>S/ {{ number_format($sugerido['con_igv'], 2) }}</b> con IGV).</span>
            @endif
        </form>
    </div>

    {{-- Variantes --}}
    @if($variantes->isNotEmpty())
    <h2 class="text-base font-semibold text-gray-800 mb-2">Por variante</h2>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead><tr class="bg-gray-50/70 border-b border-gray-100"><th class="{{ $th }} text-left">Variante</th><th class="{{ $th }} text-right">Costo prom.</th><th class="{{ $th }} text-right">Último costo</th><th class="{{ $th }} text-right">Precio sin IGV</th><th class="{{ $th }} text-right">Margen</th></tr></thead>
            <tbody>
            @foreach($variantes as $v)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2.5"><span class="font-mono text-xs text-gray-500">{{ $v->variante->codigo_kyrios ?: $v->variante->sku }}</span> <span class="text-gray-800">{{ \App\Support\EtiquetaProducto::nombreVariante($v->variante) }}</span></td>
                    <td class="px-4 py-2.5 text-right">{{ $soles($v->costo) }}</td>
                    <td class="px-4 py-2.5 text-right text-gray-500">{{ $soles($v->ultimo) }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $soles($v->precio) }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold {{ $minimo !== null && $v->margen !== null && $v->margen < $minimo ? 'text-red-600' : 'text-gray-800' }}">{{ $pct($v->margen) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Proveedores --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 mb-2">Comparador de proveedores</h2>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="bg-gray-50/70 border-b border-gray-100"><th class="{{ $th }} text-left">Proveedor</th><th class="{{ $th }} text-right">Último</th><th class="{{ $th }} text-right">Ponderado</th><th class="{{ $th }} text-right">Mín – Máx</th><th class="{{ $th }} text-right">Compras</th></tr></thead>
                    <tbody>
                    @forelse($proveedores as $i => $p)
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-2.5 text-gray-800">{{ $p->proveedor }} @if($i === 0 && $proveedores->count() > 1)<span class="ml-1 text-[11px] font-medium bg-emerald-100 text-emerald-700 rounded px-1.5 py-0.5">Más barato</span>@endif<div class="text-[11px] text-gray-400">última: {{ \Illuminate\Support\Carbon::parse($p->fecha)->format('d/m/Y') }}</div></td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $soles($p->ultimo) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $soles($p->ponderado) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-500">{{ $soles($p->minimo) }} – {{ $soles($p->maximo) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-500">{{ $p->compras }} ({{ $p->unidades }} u.)</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Todavía no hay compras de este producto.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Historial --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 mb-2">Historial de compras</h2>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="bg-gray-50/70 border-b border-gray-100"><th class="{{ $th }} text-left">Compra</th><th class="{{ $th }} text-right">Cant.</th><th class="{{ $th }} text-right">Costo</th><th class="{{ $th }} text-right">Variación</th><th class="{{ $th }} text-right">Promedio</th></tr></thead>
                    <tbody>
                    @forelse($historial as $h)
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-2.5"><a href="{{ route('compras.show', $h->compra_id) }}" class="text-blue-600 hover:underline">{{ $h->codigo ?: '#' . $h->compra_id }}</a><div class="text-[11px] text-gray-400">{{ \Illuminate\Support\Carbon::parse($h->fecha)->format('d/m/Y') }} · {{ $h->proveedor ?: 'Sin proveedor' }}</div></td>
                            <td class="px-4 py-2.5 text-right">{{ $h->cantidad }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $soles($h->costo) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $h->variacion === null ? 'text-gray-300' : ($h->variacion > 0 ? 'text-red-600' : ($h->variacion < 0 ? 'text-emerald-600' : 'text-gray-500')) }}">
                                @if($h->variacion === null) — @else {{ $h->variacion > 0 ? '▲' : ($h->variacion < 0 ? '▼' : '') }} {{ number_format(abs($h->variacion), 1) }}% @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $soles($h->promedio) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin compras registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
