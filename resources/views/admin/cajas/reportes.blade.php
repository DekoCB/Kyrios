<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Comparativos de Cajas</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .chart-container { position: relative; height: 260px; }
    </style>
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">

    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-0.5">
                <a href="{{ route('admin.cajas.dashboard') }}" class="hover:text-blue-600">Dashboard Cajas</a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Reportes Comparativos</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800">Reportes Comparativos</h1>
            <p class="text-sm text-gray-500">
                Período: {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cajas.alertas') }}"
               class="relative inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition">
                <i class="fas fa-bell"></i>
                @if($alertasCount > 0)
                    <span class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center font-bold">
                        {{ $alertasCount > 9 ? '9+' : $alertasCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.cajas.dashboard') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="p-6 space-y-6">

        {{-- Filtro de período --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <form method="GET" action="{{ route('admin.cajas.reportes') }}"
                  class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $desde }}"
                           class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta }}"
                           class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg font-medium hover:bg-blue-700 transition">
                    <i class="fas fa-filter mr-1"></i> Aplicar
                </button>
                {{-- Accesos rápidos --}}
                <div class="flex gap-2 ml-auto">
                    @php
                        $periodos = [
                            'Hoy'       => [now()->toDateString(), now()->toDateString()],
                            '7 días'    => [now()->subDays(6)->toDateString(), now()->toDateString()],
                            '30 días'   => [now()->subDays(29)->toDateString(), now()->toDateString()],
                            'Este mes'  => [now()->startOfMonth()->toDateString(), now()->toDateString()],
                        ];
                    @endphp
                    @foreach($periodos as $label => [$d, $h])
                        <a href="{{ route('admin.cajas.reportes', ['desde' => $d, 'hasta' => $h]) }}"
                           class="px-3 py-2 text-xs border rounded-lg transition
                               {{ $desde === $d && $hasta === $h
                                   ? 'bg-blue-600 text-white border-blue-600'
                                   : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </form>
        </div>

        {{-- KPI Cards del período --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total ventas</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['total_ventas'], 2) }}</p>
                <p class="text-xs text-blue-500 mt-1">{{ $kpis['total_cajas'] }} cajas</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio / día</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['promedio_por_dia'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">días con movimiento</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mejor día</p>
                @if($kpis['mejor_dia'])
                    <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($kpis['mejor_dia']->total, 2) }}</p>
                    <p class="text-xs text-purple-500 mt-1">{{ \Carbon\Carbon::parse($kpis['mejor_dia']->fecha)->format('d/m/Y') }}</p>
                @else
                    <p class="text-2xl font-bold text-gray-400 mt-1">—</p>
                @endif
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 {{ $kpis['dif_total'] < 0 ? 'border-red-500' : 'border-gray-300' }}">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Diferencia neta</p>
                <p class="text-2xl font-bold {{ $kpis['dif_total'] < 0 ? 'text-red-600' : 'text-gray-800' }} mt-1">
                    S/ {{ number_format($kpis['dif_total'], 2) }}
                </p>
                <p class="text-xs text-gray-400 mt-1">período seleccionado</p>
            </div>
        </div>

        {{-- Gráficas fila 1 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Ventas por sucursal --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-blue-500"></i> Ventas por Sucursal
                </h2>
                @if($ventasPorSucursal->isEmpty())
                    <div class="h-64 flex items-center justify-center text-gray-400 text-sm">Sin datos</div>
                @else
                    <div class="chart-container">
                        <canvas id="chartVentasSucursal"></canvas>
                    </div>
                    {{-- Tabla resumen --}}
                    <div class="mt-4 space-y-1">
                        @foreach($ventasPorSucursal as $row)
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600 truncate">{{ $row->nombre }}</span>
                                <span class="font-semibold text-gray-800 ml-2">S/ {{ number_format($row->total, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Tendencia diaria --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-line text-green-500"></i> Tendencia Diaria de Ventas
                </h2>
                @if($tendenciaDiaria->isEmpty())
                    <div class="h-64 flex items-center justify-center text-gray-400 text-sm">Sin datos</div>
                @else
                    <div class="chart-container">
                        <canvas id="chartTendencia"></canvas>
                    </div>
                @endif
            </div>
        </div>

        {{-- Gráficas fila 2 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Diferencias por sucursal --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-balance-scale text-red-500"></i> Diferencias por Sucursal
                </h2>
                @if($diferenciasPorSucursal->isEmpty())
                    <div class="h-64 flex items-center justify-center text-gray-400 text-sm">Sin diferencias registradas</div>
                @else
                    <div class="chart-container">
                        <canvas id="chartDiferencias"></canvas>
                    </div>
                @endif
            </div>

            {{-- Métodos de pago por sucursal --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-credit-card text-purple-500"></i> Métodos de Pago por Sucursal
                </h2>
                @if($metodoPorSucursal->isEmpty())
                    <div class="h-64 flex items-center justify-center text-gray-400 text-sm">Sin datos</div>
                @else
                    <div class="chart-container">
                        <canvas id="chartMetodos"></canvas>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

<script>
const coloresPaleta = [
    '#44413A','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899','#84cc16'
];

@if($ventasPorSucursal->isNotEmpty())
new Chart(document.getElementById('chartVentasSucursal'), {
    type: 'bar',
    data: {
        labels: @json($ventasPorSucursal->pluck('nombre')),
        datasets: [{
            label: 'Ventas (S/)',
            data: @json($ventasPorSucursal->pluck('total')),
            backgroundColor: coloresPaleta.slice(0, {{ $ventasPorSucursal->count() }}),
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') } }
        }
    }
});
@endif

@if($tendenciaDiaria->isNotEmpty())
new Chart(document.getElementById('chartTendencia'), {
    type: 'line',
    data: {
        labels: @json($tendenciaDiaria->pluck('fecha')),
        datasets: [{
            label: 'Ventas (S/)',
            data: @json($tendenciaDiaria->pluck('total')),
            borderColor: '#C9A500',
            backgroundColor: 'rgba(201,165,0,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: @json($tendenciaDiaria->count() > 30 ? 2 : 4),
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') } },
            x: { ticks: { maxTicksLimit: 10 } }
        }
    }
});
@endif

@if($diferenciasPorSucursal->isNotEmpty())
const difData = @json($diferenciasPorSucursal->pluck('total'));
new Chart(document.getElementById('chartDiferencias'), {
    type: 'bar',
    data: {
        labels: @json($diferenciasPorSucursal->pluck('nombre')),
        datasets: [{
            label: 'Diferencia (S/)',
            data: difData,
            backgroundColor: difData.map(v => v < 0 ? 'rgba(239,68,68,0.7)' : 'rgba(16,185,129,0.7)'),
            borderColor: difData.map(v => v < 0 ? '#ef4444' : '#10b981'),
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') } }
        }
    }
});
@endif

@if($metodoPorSucursal->isNotEmpty())
@php
    $metodoLabels = ['efectivo','yape','plin','transferencia','mixto'];
    $metodoColores = ['#10b981','#8b5cf6','#06b6d4','#44413A','#f59e0b'];
    $sucursalLabels = $metodoPorSucursal->keys()->toArray();
    $datasets = [];
    foreach ($metodoLabels as $i => $met) {
        $data = [];
        foreach ($sucursalLabels as $suc) {
            $row = $metodoPorSucursal->get($suc)?->firstWhere('metodo_pago', $met);
            $data[] = $row ? (float)$row->total : 0;
        }
        $datasets[] = [
            'label' => ucfirst($met),
            'data'  => $data,
            'backgroundColor' => $metodoColores[$i],
            'borderRadius' => 4,
        ];
    }
@endphp
new Chart(document.getElementById('chartMetodos'), {
    type: 'bar',
    data: {
        labels: @json($sucursalLabels),
        datasets: @json($datasets),
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') } }
        }
    }
});
@endif
</script>
</body>
</html>
