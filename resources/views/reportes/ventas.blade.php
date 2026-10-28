<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas — {{ $label }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .chart-container { position: relative; height: 280px; }
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

        {{-- ── HEADER ─────────────────────────────── --}}
        <div class="bg-white shadow-sm px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 no-print">
            <div>
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-chart-line text-blue-600"></i>
                    Reporte de Ventas — Márgenes de Ganancia
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $label }}:
                    <strong>{{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</strong>
                    @if($desde !== $hasta)
                        — <strong>{{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</strong>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Exportar CSV --}}
                <a href="{{ route('reportes.ventas.csv', array_merge(request()->query(), ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta])) }}"
                   class="flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                {{-- Exportar PDF --}}
                <a href="{{ route('reportes.ventas.pdf', array_merge(request()->query(), ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta])) }}"
                   class="flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                {{-- Imprimir --}}
                <button onclick="window.print()"
                        class="flex items-center gap-1.5 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>

        <div class="p-4 md:p-6 space-y-6">

            {{-- ── FILTROS ──────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm p-4 no-print">
                <form method="GET" action="{{ route('reportes.ventas') }}" class="flex flex-wrap gap-3 items-end">

                    {{-- Tabs de período --}}
                    <div class="flex flex-wrap gap-1">
                        @php
                            $tabs = [
                                'hoy'          => 'Hoy',
                                'ayer'         => 'Ayer',
                                '7dias'        => 'Últ. 7 días',
                                'este_mes'     => 'Este mes',
                                'mes_pasado'   => 'Mes pasado',
                                'este_anio'    => 'Este año',
                                'personalizado'=> 'Personalizado',
                            ];
                        @endphp
                        @foreach($tabs as $key => $tabLabel)
                            <button type="submit" name="periodo" value="{{ $key }}"
                                    class="px-3 py-1.5 text-xs rounded-lg font-medium transition-colors
                                        {{ $periodo === $key
                                            ? 'bg-blue-700 text-white'
                                            : 'border border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Rango personalizado --}}
                    @if($periodo === 'personalizado')
                        <div class="flex items-end gap-2 ml-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                                <input type="date" name="desde" value="{{ $desde }}"
                                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                                <input type="date" name="hasta" value="{{ $hasta }}"
                                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <input type="hidden" name="periodo" value="personalizado">
                            <button type="submit" class="px-4 py-1.5 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800">
                                <i class="fas fa-search mr-1"></i>Aplicar
                            </button>
                        </div>
                    @endif

                    {{-- Filtros opcionales --}}
                    <div class="flex gap-2 ml-auto flex-wrap items-end">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Almacén</label>
                            <select name="almacen_id" onchange="this.form.submit()"
                                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">Todos los almacenes</option>
                                @foreach($almacenes as $alm)
                                    <option value="{{ $alm->id }}" {{ $almacenId == $alm->id ? 'selected' : '' }}>{{ $alm->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Categoría</label>
                            <select name="categoria_id" onchange="this.form.submit()"
                                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">Todas las categorías</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}" {{ $categoriaId == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($almacenId || $categoriaId)
                            <a href="{{ route('reportes.ventas', ['periodo' => $periodo]) }}"
                               class="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-times mr-1"></i>Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── KPI CARDS ────────────────────────── --}}
            @php
                $varPct = fn ($actual, $prev) => \App\Http\Controllers\ReporteVentasController::varPct((float) $actual, (float) $prev);
                $vVentas  = $varPct($kpis['total_ventas'],    $kpisPrev['total_ventas']);
                $vCosto   = $varPct($kpis['total_costo'],     $kpisPrev['total_costo']);
                $vGanancia = $varPct($kpis['ganancia_bruta'], $kpisPrev['ganancia_bruta']);
                $vMargen  = round($kpis['margen_promedio'] - $kpisPrev['margen_promedio'], 1);
            @endphp

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- Ventas totales --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ventas Totales</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['total_ventas'], 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $kpis['num_ventas'] }} ventas · {{ $kpis['unidades_vendidas'] }} uds.</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-2 text-blue-500">
                            <i class="fas fa-shopping-cart text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1 text-xs font-medium
                        {{ $vVentas >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        <i class="fas fa-arrow-{{ $vVentas >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($vVentas) }}% vs período anterior
                    </div>
                </div>

                {{-- Costo total --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-400">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Costo Total</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['total_costo'], 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">Costo promedio del producto</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-2 text-orange-400">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1 text-xs font-medium
                        {{ $vCosto <= 0 ? 'text-green-600' : 'text-gray-500' }}">
                        <i class="fas fa-arrow-{{ $vCosto >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($vCosto) }}% vs período anterior
                    </div>
                </div>

                {{-- Ganancia bruta --}}
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ganancia Bruta</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['ganancia_bruta'], 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">Ingresos menos costos</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-2 text-green-500">
                            <i class="fas fa-coins text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1 text-xs font-medium
                        {{ $vGanancia >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        <i class="fas fa-arrow-{{ $vGanancia >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($vGanancia) }}% vs período anterior
                    </div>
                </div>

                {{-- Ticket promedio --}}
                @php $ticketPromedio = $kpis['num_ventas'] > 0 ? $kpis['total_ventas'] / $kpis['num_ventas'] : 0; @endphp
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ticket Promedio</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($ticketPromedio, 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">Valor promedio por venta · {{ $kpis['num_ventas'] }} ventas</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-2 text-purple-500">
                            <i class="fas fa-receipt text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1 text-xs font-medium
                        {{ $vVentas >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        <i class="fas fa-arrow-{{ $vVentas >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($vVentas) }}% ventas vs período anterior
                    </div>
                </div>
            </div>

            {{-- ── GRÁFICOS FILA 1 ─────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Línea: evolución ventas vs ganancia --}}
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-line text-blue-500"></i>
                        Evolución: Ventas vs Ganancia
                    </h2>
                    @if($tendencia->isEmpty())
                        <div class="h-64 flex items-center justify-center text-gray-400 text-sm">
                            <div class="text-center">
                                <i class="fas fa-chart-line text-4xl text-gray-200 block mb-2"></i>
                                Sin datos para el período
                            </div>
                        </div>
                    @else
                        <div class="chart-container">
                            <canvas id="chartTendencia"></canvas>
                        </div>
                    @endif
                </div>

                {{-- Pastel: distribución por categoría --}}
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-pie text-purple-500"></i>
                        Ventas por Categoría
                    </h2>
                    @if($porCategoria->isEmpty())
                        <div class="h-64 flex items-center justify-center text-gray-400 text-sm">
                            <div class="text-center">
                                <i class="fas fa-chart-pie text-4xl text-gray-200 block mb-2"></i>
                                Sin datos
                            </div>
                        </div>
                    @else
                        <div class="chart-container">
                            <canvas id="chartCategoria"></canvas>
                        </div>
                        <div class="mt-3 space-y-1">
                            @foreach($porCategoria as $i => $row)
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-600 truncate">{{ $row->categoria }}</span>
                                    <span class="font-semibold ml-2">S/ {{ number_format($row->total_ventas, 0) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── GRÁFICO TOP PRODUCTOS ────────────── --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-green-500"></i>
                    Top 10 Productos por Ganancia
                </h2>
                @if($topProductos->isEmpty())
                    <div class="h-48 flex items-center justify-center text-gray-400 text-sm">
                        <div class="text-center">
                            <i class="fas fa-chart-bar text-4xl text-gray-200 block mb-2"></i>
                            Sin datos para el período
                        </div>
                    </div>
                @else
                    <div style="position:relative; height:220px;">
                        <canvas id="chartTopProductos"></canvas>
                    </div>
                @endif
            </div>

            {{-- ── TABLA DETALLADA ──────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2 shrink-0">
                        <i class="fas fa-table text-blue-500"></i>
                        Detalle por Producto
                        <span id="contadorProductos" class="bg-gray-100 text-gray-500 text-xs rounded-full px-2 py-0.5 ml-1">
                            {{ $tablaProductos->count() }} productos
                        </span>
                    </h2>
                    <div class="relative w-full sm:w-72">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        <input type="text" id="buscarProducto"
                               placeholder="Buscar por nombre o código..."
                               oninput="filtrarTablaProductos(this.value)"
                               class="w-full pl-8 pr-8 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <button onclick="document.getElementById('buscarProducto').value=''; filtrarTablaProductos('');"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 hidden" id="btnLimpiarBuscar">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide sticky left-0 bg-gray-50 min-w-[200px]">
                                    Producto
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Categoría</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Cant.</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">P. Venta Prom.</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Costo Unit.</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                    <span class="flex items-center justify-end gap-1">
                                        Gan. Unit.
                                        @include('reportes.info-tip', ['tip' => 'Ganancia por unidad sobre el precio cobrado al cliente (incluye IGV).'])
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Total Vendido</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                    <span class="flex items-center justify-end gap-1">
                                        Total Ganancia
                                        @include('reportes.info-tip', ['tip' => 'Suma de ganancias sobre el precio cobrado (con IGV).'])
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tbodyProductos" class="divide-y divide-gray-100">
                            @forelse($tablaProductos as $row)
                                <tr class="hover:bg-gray-50 transition-colors"
                                    data-nombre="{{ strtolower($row->nombre . ' ' . ($row->nombre_variante ?? '')) }}"
                                    data-codigo="{{ strtolower($row->codigo ?? '') }}">
                                    <td class="px-4 py-3 sticky left-0 bg-white hover:bg-gray-50">
                                        <div class="font-medium text-gray-900">{{ $row->nombre }}</div>
                                        @if($row->nombre_variante)
                                            <div class="text-xs font-medium text-indigo-600 mt-0.5">
                                                <i class="fas fa-tag mr-1 opacity-60"></i>{{ $row->nombre_variante }}
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $row->codigo }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $row->categoria }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ number_format($row->cantidad_vendida) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">S/ {{ number_format($row->precio_promedio, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">S/ {{ number_format($row->costo_unitario, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium
                                        {{ $row->ganancia_unitaria >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                        S/ {{ number_format($row->ganancia_unitaria, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800">S/ {{ number_format($row->total_vendido, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold
                                        {{ $row->total_ganancia >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                        S/ {{ number_format($row->total_ganancia, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                        <i class="fas fa-search text-3xl block mb-2 text-gray-300"></i>
                                        No hay ventas registradas para este período
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($tablaProductos->isNotEmpty())
                            @php
                                $totalVendido  = $tablaProductos->sum('total_vendido');
                                $totalGanancia = $tablaProductos->sum('total_ganancia');
                                $totalCantidad = $tablaProductos->sum('cantidad_vendida');
                            @endphp
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td class="px-4 py-3 font-bold text-gray-800 sticky left-0 bg-gray-50" colspan="2">
                                        TOTALES
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($totalCantidad) }}</td>
                                    <td colspan="3"></td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-800">S/ {{ number_format($totalVendido, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-green-700">S/ {{ number_format($totalGanancia, 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>{{-- /p-6 --}}
    </div>{{-- /md:ml-64 --}}

{{-- ── SCRIPTS CHART.JS ──────────────────────── --}}
<script>
const PALETTE = ['#44413A','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899','#84cc16','#f97316','#14b8a6'];

const fmtSoles = (v) => 'S/ ' + Number(v).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});

@if($tendencia->isNotEmpty())
new Chart(document.getElementById('chartTendencia'), {
    type: 'line',
    data: {
        labels: @json($tendencia->pluck('fecha')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))),
        datasets: [
            {
                label: 'Ventas (S/)',
                data: @json($tendencia->pluck('total_ventas')),
                borderColor: '#C9A500',
                backgroundColor: 'rgba(201,165,0,0.08)',
                fill: true,
                tension: 0.35,
                pointRadius: {{ $tendencia->count() > 30 ? 2 : 4 }},
                pointHoverRadius: 6,
                borderWidth: 2,
            },
            {
                label: 'Ganancia (S/)',
                data: @json($tendencia->pluck('ganancia')),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.08)',
                fill: true,
                tension: 0.35,
                pointRadius: {{ $tendencia->count() > 30 ? 2 : 4 }},
                pointHoverRadius: 6,
                borderWidth: 2,
                borderDash: [],
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + fmtSoles(ctx.parsed.y) } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + Number(v).toLocaleString('es-PE') } },
            x: { ticks: { maxTicksLimit: 12 } }
        }
    }
});
@endif

@if($porCategoria->isNotEmpty())
new Chart(document.getElementById('chartCategoria'), {
    type: 'doughnut',
    data: {
        labels: @json($porCategoria->pluck('categoria')),
        datasets: [{
            data: @json($porCategoria->pluck('total_ventas')),
            backgroundColor: PALETTE.slice(0, {{ $porCategoria->count() }}),
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + fmtSoles(ctx.parsed) } }
        },
        cutout: '55%',
    }
});
@endif

function filtrarTablaProductos(q) {
    const term = q.toLowerCase().trim();
    const rows = document.querySelectorAll('#tbodyProductos tr[data-nombre]');
    let visible = 0;
    rows.forEach(row => {
        const match = !term
            || row.dataset.nombre.includes(term)
            || row.dataset.codigo.includes(term);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const contador = document.getElementById('contadorProductos');
    const total = rows.length;
    contador.textContent = term
        ? `${visible} de ${total} productos`
        : `${total} productos`;
    const btnLimpiar = document.getElementById('btnLimpiarBuscar');
    if (btnLimpiar) btnLimpiar.classList.toggle('hidden', !term);
}

@if($topProductos->isNotEmpty())
const topNombres  = @json($topProductos->pluck('nombre'));
const topGanancia = @json($topProductos->pluck('ganancia'));
const topMargen   = @json($topProductos->pluck('margen'));

new Chart(document.getElementById('chartTopProductos'), {
    type: 'bar',
    data: {
        labels: topNombres.map(n => n.length > 25 ? n.substring(0,25)+'…' : n),
        datasets: [
            {
                label: 'Ganancia (S/)',
                data: topGanancia,
                backgroundColor: topGanancia.map((_, i) => PALETTE[i % PALETTE.length] + 'CC'),
                borderColor:     topGanancia.map((_, i) => PALETTE[i % PALETTE.length]),
                borderWidth: 1,
                borderRadius: 5,
                yAxisID: 'y',
            },
            {
                label: 'Margen %',
                data: topMargen,
                type: 'line',
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.15)',
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.3,
                borderWidth: 2,
                yAxisID: 'y2',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.yAxisID === 'y2'
                        ? ' Margen: ' + Number(ctx.parsed.y).toFixed(1) + '%'
                        : ' Ganancia: ' + fmtSoles(ctx.parsed.y)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                ticks: { callback: v => 'S/ ' + Number(v).toLocaleString('es-PE') }
            },
            y2: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { callback: v => v.toFixed(0) + '%' }
            }
        }
    }
});
@endif
</script>
</body>
</html>
