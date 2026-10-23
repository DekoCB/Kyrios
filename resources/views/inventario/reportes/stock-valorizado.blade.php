<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Valorizado · Inventario</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">

    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-0.5">
                <span>Inventario</span><span>/</span>
                <span class="text-gray-700 font-medium">Stock Valorizado</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-coins text-amber-500"></i>
                Stock Valorizado por Variante
            </h1>
            <p class="text-sm text-gray-500">
                Capital invertido calculado con
                <span class="font-semibold text-blue-600">Costo Promedio Ponderado (CPP)</span>
                real por variante
            </p>
        </div>
        @if($productos->count())
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition">
                <i class="fas fa-file-csv"></i> Exportar Excel
            </a>
        @endif
    </div>

    <div class="p-6 space-y-5">

        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Sucursal</label>
                    <select name="sucursal_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas las sucursales</option>
                        @foreach($sucursales as $s)
                            <option value="{{ $s->id }}" {{ $sucursalId == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Categoría</label>
                    <select name="categoria_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias as $c)
                            <option value="{{ $c->id }}" {{ $categoriaId == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Marca</label>
                    <select name="marca_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas las marcas</option>
                        @foreach($marcas as $m)
                            <option value="{{ $m->id }}" {{ $marcaId == $m->id ? 'selected' : '' }}>{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2.5 text-sm font-semibold transition flex items-center justify-center gap-2">
                        <i class="fas fa-filter"></i> Aplicar
                    </button>
                    <a href="{{ route('inventario.reportes.stock-valorizado') }}"
                       class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>

        {{-- KPI Cards (simplificado: solo valor de mercancía) --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Productos</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($totales['items']) }}</p>
                <p class="text-xs text-blue-500 mt-1">{{ number_format($totales['unidades']) }} unidades en stock</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Valor de Mercancía (Costo)</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($totales['valor_compra'], 2) }}</p>
                <p class="text-xs text-orange-500 mt-1">Capital invertido (stock × costo promedio)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Valor a Precio de Venta</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($totales['valor_venta'], 2) }}</p>
                <p class="text-xs text-green-500 mt-1">Valorización al precio de lista</p>
            </div>
        </div>

        {{-- Leyenda --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
            <div class="text-xs text-blue-700">
                <strong>CPP (Costo Promedio Ponderado):</strong>
                Calculado directamente desde el historial real de compras por variante:
                <code class="bg-blue-100 px-1 rounded">CPP = Σ(cantidad × precio_compra) / Σ(cantidad)</code>.
                Las filas con <i class="fas fa-chevron-right text-blue-500"></i> se pueden expandir para ver el detalle por variante.
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            @if($productos->isEmpty())
                <div class="p-12 text-center">
                    <i class="fas fa-box-open text-4xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500 font-medium">No hay productos con stock valorizado</p>
                    <p class="text-sm text-gray-400 mt-1">Ajusta los filtros o verifica que los productos tengan compras registradas</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-8"></th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Producto / Variante</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Categoría</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Marca</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Costo Unit. <span class="font-normal text-gray-400 normal-case">(CPP)</span>
                                </th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-orange-600 uppercase tracking-wider">Valor Mercancía</th>
                            </tr>
                        </thead>
                        @foreach($productos as $p)
                            @if($p['tiene_variantes'] && $p['variantes']->isNotEmpty())
                                {{-- tbody propio por producto con variantes: x-data vive aquí --}}
                                <tbody x-data="{ open: false }" class="divide-y divide-gray-100">

                                    {{-- Fila cabecera expandible --}}
                                    <tr class="hover:bg-gray-50 transition cursor-pointer border-b border-gray-200"
                                        @click="open = !open">
                                        <td class="px-3 py-3 text-center">
                                            <div class="w-5 h-5 flex items-center justify-center rounded bg-blue-100 text-blue-600 transition-transform duration-200"
                                                 :class="open ? 'rotate-90' : ''">
                                                <i class="fas fa-chevron-right text-xs"></i>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-gray-900">{{ $p['nombre'] }}</span>
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700 border border-indigo-200">
                                                    <i class="fas fa-layer-group text-[8px]"></i>
                                                    {{ $p['variantes']->count() }} variantes
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ $p['categoria'] }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $p['marca'] }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($p['stock']) }}</td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-right font-semibold text-orange-700">S/ {{ number_format($p['valor_compra'], 2) }}</td>
                                    </tr>

                                    {{-- Sub-filas de variantes --}}
                                    @foreach($p['variantes'] as $v)
                                        <tr x-show="open"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            style="display:none"
                                            class="bg-indigo-50/40 hover:bg-indigo-50 transition border-l-4 border-indigo-300">
                                            <td class="px-3 py-2.5"></td>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center gap-2 pl-4">
                                                    @if($v['color_hex'])
                                                        <span class="w-3 h-3 rounded-full border border-gray-300 shrink-0 shadow-sm"
                                                              style="background-color: {{ $v['color_hex'] }}"></span>
                                                    @else
                                                        <i class="fas fa-grip-lines text-gray-300 text-xs"></i>
                                                    @endif
                                                    <span class="text-gray-700 font-medium text-xs">{{ $v['nombre'] }}</span>
                                                    @if($v['sku'])
                                                        <span class="text-gray-400 font-mono text-[10px]">{{ $v['sku'] }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td colspan="2" class="px-4 py-2.5"></td>
                                            <td class="px-4 py-2.5 text-right font-bold text-indigo-700">{{ number_format($v['stock']) }}</td>
                                            <td class="px-4 py-2.5 text-right">
                                                <span class="font-mono font-semibold text-indigo-700 text-xs">S/ {{ number_format($v['costo_cpp'], 2) }}</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-orange-600 text-xs">S/ {{ number_format($v['valor_compra'], 2) }}</td>
                                        </tr>
                                    @endforeach

                                </tbody>
                            @else
                                {{-- Producto sin variantes: tbody simple --}}
                                <tbody class="divide-y divide-gray-100">
                                    <tr class="hover:bg-gray-50 transition border-b border-gray-200">
                                        <td class="px-3 py-3 text-center text-gray-300">
                                            <i class="fas fa-minus text-xs"></i>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $p['nombre'] }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $p['categoria'] }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $p['marca'] }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($p['stock']) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-600 font-mono text-xs">
                                            {{ $p['precio_compra'] > 0 ? 'S/ '.number_format($p['precio_compra'], 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-orange-700">S/ {{ number_format($p['valor_compra'], 2) }}</td>
                                    </tr>
                                </tbody>
                            @endif
                        @endforeach
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-700">
                                    TOTALES — {{ number_format($totales['items']) }} productos
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($totales['unidades']) }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right font-bold text-orange-700">S/ {{ number_format($totales['valor_compra'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>
</body>
</html>
