<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valorización con Prorrateo — Inventario</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .md\:ml-64 { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 min-h-screen">

        {{-- HEADER --}}
        <div class="bg-white shadow-sm px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 no-print">
            <div>
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-balance-scale text-purple-600"></i>
                    Valorización con Costo Prorateado
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Compara costo promedio (CPP) vs costo real prorateado de importaciones
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('inventario.reportes.stock-valorizado') }}"
                   class="flex items-center gap-1.5 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chart-bar"></i> Stock Valorizado
                </a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
                   class="flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                <button onclick="window.print()"
                        class="flex items-center gap-1.5 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>

        <div class="p-4 md:p-6 space-y-6">

            {{-- FILTROS --}}
            <form method="GET" action="{{ route('inventario.reportes.valorizacion-prorateada') }}" class="bg-white rounded-xl shadow-sm p-4 no-print">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Categoría</label>
                        <select name="categoria_id" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">Todas las categorías</option>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" {{ $categoriaId == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-5">
                        <input type="checkbox" name="solo_prorrateo" id="solo_prorrateo" value="1"
                               {{ $soloConProrrateo ? 'checked' : '' }}
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <label for="solo_prorrateo" class="text-sm text-gray-700">
                            Solo productos con prorrateo calculado
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1 invisible">Filtrar</label>
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-search mr-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>

            {{-- KPIs --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Productos en Stock</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totales['items'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ number_format($totales['unidades']) }} unidades totales</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Valor al CPP</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">S/ {{ number_format($totales['valor_cpp'], 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Costo promedio ponderado</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Valor Prorateado</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        S/ {{ $totales['valor_prorateado'] > 0 ? number_format($totales['valor_prorateado'], 2) : '—' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Costo real de importación</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Con Prorrateo</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totales['con_prorrateo'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                        de {{ $totales['items'] }} productos
                        @if($totales['items'] > 0)
                            ({{ round($totales['con_prorrateo'] / $totales['items'] * 100) }}%)
                        @endif
                    </p>
                </div>
            </div>

            {{-- Diferencia total si aplica --}}
            @php $diferencia = $totales['valor_prorateado'] - $totales['valor_cpp']; @endphp
            @if($totales['valor_prorateado'] > 0 && abs($diferencia) > 0.01)
                <div class="rounded-xl p-4 flex items-center gap-3 {{ $diferencia > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-green-50 border border-green-200' }}">
                    <i class="fas {{ $diferencia > 0 ? 'fa-exclamation-triangle text-amber-500' : 'fa-check-circle text-green-500' }} text-xl"></i>
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">
                            @if($diferencia > 0)
                                El inventario está subvalorado en S/ {{ number_format($diferencia, 2) }} según el costo prorateado
                            @else
                                El inventario está sobrevalorado en S/ {{ number_format(abs($diferencia), 2) }} según el costo prorateado
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            El costo prorateado incluye flete, seguro e impuestos de importación distribuidos proporcionalmente
                        </p>
                    </div>
                </div>
            @endif

            {{-- TABLA --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-table text-purple-600"></i>
                        Detalle por Producto
                        <span class="text-sm font-normal text-gray-500">({{ $productos->count() }} productos)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Producto</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoría</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Stock</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Costo CPP (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Costo Prorateado (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Diferencia (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Valor al CPP (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Valor Prorateado (S/)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($productos as $prod)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('inventario.productos.show', $prod['id']) }}"
                                           class="text-sm font-semibold text-blue-600 hover:underline">
                                            {{ $prod['nombre'] }}
                                        </a>
                                        <p class="text-xs text-gray-400 font-mono">{{ $prod['codigo'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $prod['categoria'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">{{ $prod['stock'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700">
                                        {{ $prod['costo_cpp'] > 0 ? number_format($prod['costo_cpp'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $prod['tiene_prorrateo'] ? 'font-semibold text-orange-700' : 'text-gray-400 italic' }}">
                                        {{ $prod['tiene_prorrateo'] ? number_format($prod['costo_prorateado'], 2) : 'Sin prorrateo' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        @if($prod['tiene_prorrateo'] && $prod['costo_cpp'] > 0)
                                            @php $dif = $prod['diferencia']; @endphp
                                            <span class="{{ $dif > 0.01 ? 'text-amber-600' : ($dif < -0.01 ? 'text-green-600' : 'text-gray-400') }} font-medium">
                                                {{ $dif > 0 ? '+' : '' }}{{ number_format($dif, 2) }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-800">
                                        S/ {{ number_format($prod['valor_al_cpp'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $prod['valor_prorateado'] !== null ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                                        {{ $prod['valor_prorateado'] !== null ? 'S/ ' . number_format($prod['valor_prorateado'], 2) : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-balance-scale text-4xl text-gray-200 mb-3 block"></i>
                                        No hay productos con stock activo en el filtro seleccionado
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($productos->isNotEmpty())
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr class="font-bold text-sm">
                                    <td colspan="2" class="px-4 py-3 text-gray-700">TOTALES</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ number_format($totales['unidades']) }}</td>
                                    <td colspan="3"></td>
                                    <td class="px-4 py-3 text-right text-gray-900">S/ {{ number_format($totales['valor_cpp'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">
                                        {{ $totales['valor_prorateado'] > 0 ? 'S/ ' . number_format($totales['valor_prorateado'], 2) : '—' }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
