<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis ABC · Inventario</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">

    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-0.5">
                <span>Inventario</span>
                <span>/</span>
                <span class="text-gray-700 font-medium">Análisis ABC</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-chart-bar text-indigo-500"></i>
                Análisis ABC de Inventario
            </h1>
            <p class="text-sm text-gray-500">Clasificación por valor de inventario — Principio de Pareto</p>
        </div>
        @if($productos->isNotEmpty())
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition">
                <i class="fas fa-file-csv"></i> Exportar Excel
            </a>
        @endif
    </div>

    <div class="p-6 space-y-5">

        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    <a href="{{ route('inventario.reportes.abc') }}"
                       class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>

        @if($productos->isEmpty())
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-box-open text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 font-medium">No hay productos con valor de inventario</p>
                <p class="text-sm text-gray-400 mt-1">Verifica que los productos tengan costo y stock configurados</p>
            </div>
        @else

            {{-- Summary Cards A/B/C --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Clase A --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-red-500">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-red-100 text-red-700 text-xl font-black">A</span>
                        </div>
                        <span class="text-xs font-semibold text-red-500 bg-red-50 px-2 py-1 rounded-full">Alta prioridad</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800">{{ $resumen['A']['count'] }} productos</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $resumen['A']['pct_productos'] }}% del catálogo</p>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-lg font-bold text-red-600">S/ {{ number_format($resumen['A']['valor'], 2) }}</p>
                        <p class="text-xs text-gray-400">{{ $resumen['A']['pct_valor'] }}% del valor total</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 italic">Control estricto — revisión continua</p>
                </div>

                {{-- Clase B --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-amber-500">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-amber-100 text-amber-700 text-xl font-black">B</span>
                        </div>
                        <span class="text-xs font-semibold text-amber-500 bg-amber-50 px-2 py-1 rounded-full">Prioridad media</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800">{{ $resumen['B']['count'] }} productos</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $resumen['B']['pct_productos'] }}% del catálogo</p>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-lg font-bold text-amber-600">S/ {{ number_format($resumen['B']['valor'], 2) }}</p>
                        <p class="text-xs text-gray-400">{{ $resumen['B']['pct_valor'] }}% del valor total</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 italic">Control moderado — revisión mensual</p>
                </div>

                {{-- Clase C --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-400">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-100 text-blue-700 text-xl font-black">C</span>
                        </div>
                        <span class="text-xs font-semibold text-blue-500 bg-blue-50 px-2 py-1 rounded-full">Baja prioridad</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800">{{ $resumen['C']['count'] }} productos</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $resumen['C']['pct_productos'] }}% del catálogo</p>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-lg font-bold text-blue-500">S/ {{ number_format($resumen['C']['valor'], 2) }}</p>
                        <p class="text-xs text-gray-400">{{ $resumen['C']['pct_valor'] }}% del valor total</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 italic">Control simplificado — pedidos esporádicos</p>
                </div>
            </div>

            {{-- Pareto Chart --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">Diagrama de Pareto (Top 30 productos)</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Barras = % valor individual · Línea = % acumulado</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-indigo-500 inline-block"></span> % Valor</span>
                        <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 bg-red-500 inline-block"></span> % Acumulado</span>
                    </div>
                </div>
                <div class="relative h-72">
                    <canvas id="paretoChart"></canvas>
                </div>
                {{-- Reference lines labels --}}
                <div class="flex justify-end gap-6 mt-2 text-xs text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-3 h-px border-t-2 border-dashed border-red-400 inline-block"></span> 80% (A→B)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-px border-t-2 border-dashed border-amber-400 inline-block"></span> 95% (B→C)</span>
                </div>
            </div>

            {{-- Products Table --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-800">Clasificación Completa</h2>
                    <span class="text-sm text-gray-500">{{ $productos->count() }} productos con valor de inventario</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">#</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Producto</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Categoría</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Marca</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Costo U.</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Valor Inv.</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">% Valor</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">% Acum.</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Clase</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Recomendación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($productos as $p)
                                @php
                                    $claseColor = match($p['clase']) {
                                        'A' => ['bg' => 'bg-red-100 text-red-700', 'row' => 'bg-red-50/20'],
                                        'B' => ['bg' => 'bg-amber-100 text-amber-700', 'row' => 'bg-amber-50/20'],
                                        'C' => ['bg' => 'bg-blue-100 text-blue-600', 'row' => ''],
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 transition {{ $claseColor['row'] }}">
                                    <td class="px-4 py-3 text-center text-gray-400 font-mono text-xs">{{ $p['rank'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $p['nombre'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $p['categoria'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $p['marca'] }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-700">{{ number_format($p['stock']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 font-mono text-xs">
                                        {{ $p['precio_compra'] > 0 ? 'S/ '.number_format($p['precio_compra'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800">S/ {{ number_format($p['valor_inventario'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $p['pct_valor'] }}%</td>
                                    <td class="px-4 py-3 text-right">
                                        {{-- Progress bar for cumulative % --}}
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full {{ $p['clase'] === 'A' ? 'bg-red-500' : ($p['clase'] === 'B' ? 'bg-amber-500' : 'bg-blue-400') }}"
                                                     style="width: {{ min(100, $p['pct_acum']) }}%"></div>
                                            </div>
                                            <span class="text-xs font-mono text-gray-600 min-w-[40px] text-right">{{ $p['pct_acum'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-black {{ $claseColor['bg'] }}">
                                            {{ $p['clase'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $p['recomendacion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm font-bold text-gray-700">
                                    TOTAL — {{ $productos->count() }} productos
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">S/ {{ number_format($totalValor, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-700">100%</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        @endif
    </div>
</div>

@if($productos->isNotEmpty())
<script>
(function () {
    const labels    = @json($chartLabels);
    const valores   = @json($chartValores);
    const acumulado = @json($chartAcumulado);

    const ctx = document.getElementById('paretoChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: '% Valor individual',
                    data: valores,
                    backgroundColor: labels.map((_, i) => {
                        const acum = acumulado[i];
                        if (acum <= 80)  return 'rgba(239, 68, 68, 0.75)';   // red A
                        if (acum <= 95)  return 'rgba(245, 158, 11, 0.75)';  // amber B
                        return 'rgba(96, 165, 250, 0.75)';                    // blue C
                    }),
                    borderColor: labels.map((_, i) => {
                        const acum = acumulado[i];
                        if (acum <= 80)  return 'rgb(220, 38, 38)';
                        if (acum <= 95)  return 'rgb(217, 119, 6)';
                        return 'rgb(59, 130, 246)';
                    }),
                    borderWidth: 1,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: '% Acumulado',
                    data: acumulado,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    fill: false,
                    tension: 0.3,
                    yAxisID: 'y2',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}%`
                    }
                },
                annotation: {
                    annotations: {
                        line80: {
                            type: 'line',
                            yScaleID: 'y2',
                            yMin: 80, yMax: 80,
                            borderColor: 'rgba(239,68,68,0.5)',
                            borderDash: [6, 3],
                            borderWidth: 1.5,
                        },
                        line95: {
                            type: 'line',
                            yScaleID: 'y2',
                            yMin: 95, yMax: 95,
                            borderColor: 'rgba(245,158,11,0.5)',
                            borderDash: [6, 3],
                            borderWidth: 1.5,
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 45,
                        minRotation: 30,
                    },
                    grid: { display: false }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: '% Valor', font: { size: 11 }, color: '#6b7280' },
                    ticks: { callback: v => v + '%', font: { size: 11 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y2: {
                    type: 'linear',
                    position: 'right',
                    min: 0, max: 100,
                    title: { display: true, text: '% Acumulado', font: { size: 11 }, color: '#C9A500' },
                    ticks: { callback: v => v + '%', font: { size: 11 }, color: '#C9A500' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
})();
</script>
@endif

</body>
</html>
