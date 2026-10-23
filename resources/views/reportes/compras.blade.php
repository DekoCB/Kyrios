<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Compras — {{ $label }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .chart-container { position: relative; height: 260px; }
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
                    <i class="fas fa-boxes text-orange-600"></i>
                    Reporte de Compras e Importaciones
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
                <a href="{{ route('reportes.compras.csv', request()->query()) }}"
                   class="flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                <button onclick="window.print()"
                        class="flex items-center gap-1.5 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="{{ route('compras.index') }}"
                   class="flex items-center gap-1.5 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <div class="p-4 md:p-6 space-y-6">

            {{-- FILTROS --}}
            <form method="GET" action="{{ route('reportes.compras') }}" class="bg-white rounded-xl shadow-sm p-4 no-print">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 items-end">
                    {{-- Período --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Período</label>
                        <select name="periodo" onchange="toggleCustom(this.value)"
                                class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            @foreach(['7dias'=>'Últimos 7 días','30dias'=>'Últimos 30 días','mes'=>'Este mes','trimestre'=>'Este trimestre','anio'=>'Este año','custom'=>'Personalizado'] as $val => $lbl)
                                <option value="{{ $val }}" {{ $periodo === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Desde --}}
                    <div id="custom-desde" class="{{ $periodo === 'custom' ? '' : 'hidden' }}">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                        <input type="date" name="fecha_desde" value="{{ $desde }}"
                               class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                    </div>
                    {{-- Hasta --}}
                    <div id="custom-hasta" class="{{ $periodo === 'custom' ? '' : 'hidden' }}">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                        <input type="date" name="fecha_hasta" value="{{ $hasta }}"
                               class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                    </div>
                    {{-- Proveedor --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Proveedor</label>
                        <select name="proveedor_id" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                            <option value="">Todos</option>
                            @foreach($proveedores as $prov)
                                <option value="{{ $prov->id }}" {{ $proveedorId == $prov->id ? 'selected' : '' }}>{{ $prov->razon_social }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Tipo --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                        <select name="tipo_compra" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                            <option value="">Todos</option>
                            <option value="local"       {{ $tipoCompra === 'local'       ? 'selected' : '' }}>Local</option>
                            <option value="importacion" {{ $tipoCompra === 'importacion' ? 'selected' : '' }}>Importación</option>
                        </select>
                    </div>
                    {{-- Botón --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1 invisible">Filtrar</label>
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-search mr-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>

            {{-- KPIs --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Invertido</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">S/ {{ number_format($kpis['total_invertido'], 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $kpis['num_compras'] }} compras en el período</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Gastos Importación</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">S/ {{ number_format($kpis['gastos_importacion'], 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $kpis['num_importaciones'] }} importaciones</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Compras Locales</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $kpis['num_locales'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $kpis['proveedores_distintos'] }} proveedores distintos</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Promedio por Compra</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">S/ {{ number_format($kpis['promedio_por_compra'], 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Incluyendo gastos</p>
                </div>
            </div>

            {{-- Gráfica tendencia + Top proveedores --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Gráfica de tendencia --}}
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5">
                    <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-bar text-orange-500"></i>
                        Inversión diaria (S/)
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartTendencia"></canvas>
                    </div>
                </div>

                {{-- Top proveedores --}}
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-trophy text-yellow-500"></i>
                        Top Proveedores
                    </h3>
                    <div class="space-y-3">
                        @forelse($porProveedor as $prov)
                            @php $pct = $kpis['total_invertido'] > 0 ? ($prov->total / $kpis['total_invertido'] * 100) : 0; @endphp
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="font-medium text-gray-700 truncate max-w-[60%]">{{ $prov->razon_social }}</span>
                                    <span class="text-gray-500">S/ {{ number_format($prov->total, 0) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-orange-500 h-1.5 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Sin datos</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Tabla detalle --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-table text-orange-600"></i>
                        Detalle de Compras
                        <span class="text-sm font-normal text-gray-500">({{ $tablaCompras->count() }} registros)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Código</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Proveedor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Factura</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Subtotal (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Gastos Imp. (S/)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total c/Gastos (S/)</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado Pago</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($tablaCompras as $compra)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('compras.show', $compra->id) }}"
                                           class="text-sm font-mono font-semibold text-blue-600 hover:underline">
                                            {{ $compra->codigo }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-800">{{ $compra->razon_social }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $compra->numero_factura }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($compra->tipo_compra === 'importacion')
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                <i class="fas fa-ship mr-1"></i>Importación
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-store mr-1"></i>Local
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-800">{{ number_format($compra->total_pen, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right {{ $compra->gastos_importacion_pen > 0 ? 'text-orange-600 font-medium' : 'text-gray-400' }}">
                                        {{ $compra->gastos_importacion_pen > 0 ? number_format($compra->gastos_importacion_pen, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">
                                        {{ number_format($compra->total_con_gastos, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $estadoBadge = match($compra->estado_pago ?? '') {
                                                'pagado'   => 'bg-green-100 text-green-800',
                                                'pendiente'=> 'bg-yellow-100 text-yellow-800',
                                                'parcial'  => 'bg-orange-100 text-orange-800',
                                                'vencido'  => 'bg-red-100 text-red-800',
                                                default    => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $estadoBadge }}">
                                            {{ ucfirst($compra->estado_pago ?? 'Sin cuenta') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-boxes text-4xl text-gray-200 mb-3 block"></i>
                                        No hay compras en el período seleccionado
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($tablaCompras->isNotEmpty())
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr class="font-bold">
                                    <td colspan="5" class="px-4 py-3 text-sm text-gray-700">TOTALES</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900">S/ {{ number_format($tablaCompras->sum('total_pen'), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-orange-600">S/ {{ number_format($tablaCompras->sum('gastos_importacion_pen'), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900">S/ {{ number_format($tablaCompras->sum('total_con_gastos'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>{{-- /p-6 --}}
    </div>{{-- /md:ml-64 --}}

    <script>
    // Gráfica tendencia
    const tendencia = @json($tendencia);
    if (tendencia.length > 0) {
        new Chart(document.getElementById('chartTendencia'), {
            type: 'bar',
            data: {
                labels: tendencia.map(r => {
                    const d = new Date(r.dia + 'T00:00:00');
                    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'Total (S/)',
                    data: tendencia.map(r => parseFloat(r.total || 0)),
                    backgroundColor: 'rgba(234, 88, 12, 0.7)',
                    borderColor: 'rgb(234, 88, 12)',
                    borderWidth: 1,
                    borderRadius: 4,
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
    }

    function toggleCustom(val) {
        const show = val === 'custom';
        document.getElementById('custom-desde').classList.toggle('hidden', !show);
        document.getElementById('custom-hasta').classList.toggle('hidden', !show);
    }
    </script>
</body>
</html>
