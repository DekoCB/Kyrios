{{-- resources/views/dashboards/tienda.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tienda - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        /* Altura fija para contenedores de gráficos */
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .chart-card {
            min-height: 350px;
            display: flex;
            flex-direction: column;
        }
        .chart-card .chart-wrapper {
            flex: 1;
            min-height: 0;
        }
        .quick-actions-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .quick-actions-scroll::-webkit-scrollbar {
            height: 6px;
        }
        .quick-actions-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .quick-actions-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 min-h-screen bg-gray-100">
        {{-- Top Bar --}}
        <div class="bg-white shadow-sm sticky top-0 z-10">
            <div class="px-6 py-3 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-store text-blue-900 mr-2"></i>
                    Panel de Tienda
                </h1>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-900 to-blue-700 rounded-full flex items-center justify-center text-white font-bold">
                            {{ substr(auth()->user()->name, 0, 2) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">Tienda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">

            {{-- ⚠ ALERTA: CAJA DE DÍA ANTERIOR SIN CERRAR --}}
            @if(isset($caja_atrasada) && $caja_atrasada)
            @php
                $fechaCajaAtrasada = \Carbon\Carbon::parse($caja_atrasada->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
                $aperturaCajaAtras = $caja_atrasada->fecha_apertura
                    ? \Carbon\Carbon::parse($caja_atrasada->fecha_apertura)
                    : \Carbon\Carbon::parse($caja_atrasada->fecha);
                $totalMinAtraso = (int) $aperturaCajaAtras->diffInMinutes(now());
                $diasAtraso    = intdiv($totalMinAtraso, 1440);
                $horasAtraso   = intdiv($totalMinAtraso % 1440, 60);
                $minutosAtraso = $totalMinAtraso % 60;
                $tiempoAtraso  = '';
                if ($diasAtraso > 0)   $tiempoAtraso .= $diasAtraso . ' ' . ($diasAtraso == 1 ? 'día' : 'días') . ', ';
                if ($horasAtraso > 0)  $tiempoAtraso .= $horasAtraso . ' ' . ($horasAtraso == 1 ? 'hora' : 'horas') . ', ';
                $tiempoAtraso .= $minutosAtraso . ' ' . ($minutosAtraso == 1 ? 'minuto' : 'minutos');
            @endphp
            <div class="mb-6 bg-red-50 border-2 border-red-400 rounded-xl p-5 shadow-md">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-red-800 font-bold text-lg mb-1">
                            Caja sin cerrar del {{ $fechaCajaAtrasada }}
                        </h3>
                        <p class="text-red-700 text-sm mb-3">
                            Tienes una caja abierta hace <strong>{{ $tiempoAtraso }}</strong>
                            con un saldo de <strong>S/ {{ number_format($caja_atrasada->monto_final, 2) }}</strong>
                            en el almacén <strong>{{ $caja_atrasada->almacen->nombre ?? '—' }}</strong>.
                            Debes cerrarla antes de abrir una nueva caja para hoy.
                        </p>
                        <a href="{{ route('caja.actual') }}"
                           class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-lock"></i>
                            Cerrar Caja del {{ $fechaCajaAtrasada }}
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Barra de Acciones Rápidas --}}
            <div class="mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3">
                    <div class="quick-actions-scroll overflow-x-auto pb-1">
                        <div class="flex items-center gap-2 min-w-max">
                            <a href="{{ route('ventas.create') }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 transition whitespace-nowrap text-sm font-medium">
                                <i class="fas fa-plus-circle text-xs"></i>
                                <span>Venta</span>
                            </a>

                            <a href="{{ route('caja.index') }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition whitespace-nowrap text-sm font-medium">
                                <i class="fas fa-cash-register text-xs"></i>
                                <span>Caja</span>
                            </a>

                            @if($caja)
                                <a href="{{ route('caja.actual') }}"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition whitespace-nowrap text-sm font-medium">
                                    <i class="fas fa-lock text-xs"></i>
                                    <span>Cerrar Caja</span>
                                </a>
                            @else
                                <a href="{{ route('caja.abrir') }}"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 transition whitespace-nowrap text-sm font-medium">
                                    <i class="fas fa-lock-open text-xs"></i>
                                    <span>Abrir Caja</span>
                                </a>
                            @endif

                            <a href="{{ route('tienda.inventario.ver') }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-yellow-200 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 transition whitespace-nowrap text-sm font-medium">
                                <i class="fas fa-boxes text-xs"></i>
                                <span>Stock</span>
                            </a>

                            <a href="{{ route('traslados.pendientes') }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition whitespace-nowrap text-sm font-medium">
                                <i class="fas fa-truck-loading text-xs"></i>
                                <span>Traslados</span>
                            </a>

                            <a href="{{ route('clientes.create') }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 transition whitespace-nowrap text-sm font-medium">
                                <i class="fas fa-user-plus text-xs"></i>
                                <span>Cliente</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Estado de Caja --}}
            <div class="mb-6">
                @if($caja)
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    Caja abierta desde {{ $caja->created_at->format('H:i') }} |
                                    Monto inicial: S/ {{ number_format($caja->monto_inicial, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif(isset($caja_atrasada) && $caja_atrasada)
                    {{-- Cuando hay caja atrasada, no mostrar el botón "Abrir Caja" --}}
                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-orange-400 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-orange-700">
                                    No hay caja abierta para hoy. Primero debes cerrar la caja pendiente del
                                    {{ \Carbon\Carbon::parse($caja_atrasada->fecha)->locale('es')->isoFormat('D [de] MMMM') }}.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        No tienes una caja abierta. Abre una caja para comenzar a operar.
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('caja.abrir') }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                                <i class="fas fa-cash-register mr-2"></i>Abrir Caja
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- KPIs --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-blue-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Ventas del Día</p>
                            <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($ventas_dia, 2) }}</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-dollar-sign text-blue-900 text-2xl"></i>
                        </div>
                    </div>
                    @if($ventas_dia > 0)
                    <div class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            @php
                                $meta_diaria = 5000; // Meta ejemplo
                                $porcentaje = min(($ventas_dia / $meta_diaria) * 100, 100);
                            @endphp
                            <div class="bg-blue-900 h-2 rounded-full" style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ round($porcentaje) }}% de la meta diaria</p>
                    </div>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-green-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Caja Actual</p>
                            <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($caja_actual, 2) }}</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-cash-register text-green-600 text-2xl"></i>
                        </div>
                    </div>
                    @if($caja)
                    <a href="{{ route('caja.actual') }}" class="mt-3 inline-block text-xs text-red-600 hover:text-red-800">
                        <i class="fas fa-times-circle mr-1"></i>Ir al cierre
                    </a>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-purple-600">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Transacciones Hoy</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $transacciones_dia }}</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-receipt text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-yellow-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Clientes Atendidos</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $clientes_atendidos }}</p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-lg">
                            <i class="fas fa-users text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gráfico de Ventas por Hora y Últimas Ventas --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 chart-card">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-chart-line text-blue-900 mr-2"></i>
                        Ventas por Hora
                    </h3>
                    <div class="chart-wrapper">
                        <div class="chart-container">
                            <canvas id="ventasHoraChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 chart-card">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex justify-between items-center">
                        <span>
                            <i class="fas fa-history text-blue-900 mr-2"></i>
                            Últimas Ventas
                        </span>
                        <a href="{{ route('ventas.index') }}" class="text-sm text-blue-900 hover:text-blue-700">
                            Ver todas <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </h3>
                    
                    <div class="overflow-y-auto" style="max-height: 250px;">
                        @if($ultimas_ventas->count() > 0)
                            <div class="space-y-3">
                                @foreach($ultimas_ventas as $venta)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ $venta->cliente->nombre ?? 'Cliente Mostrador' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $venta->created_at->format('H:i') }} - {{ $venta->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-900">S/ {{ number_format($venta->total, 2) }}</p>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full {{ $venta->estado_pago == 'pagado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($venta->estado_pago) }}
                                        </span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-shopping-cart text-4xl mb-3 text-gray-300"></i>
                                <p>No hay ventas registradas hoy</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Accesos Rápidos --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('ventas.create') }}" class="bg-gradient-to-r from-blue-900 to-blue-800 text-white rounded-xl p-6 hover-scale flex items-center justify-between group">
                    <div>
                        <i class="fas fa-cart-plus text-3xl mb-2 opacity-80"></i>
                        <h4 class="text-lg font-semibold">Nueva Venta</h4>
                        <p class="text-sm opacity-80">Registrar venta</p>
                    </div>
                    <i class="fas fa-arrow-right text-2xl group-hover:translate-x-2 transition-transform"></i>
                </a>

                <a href="{{ route('caja.actual') }}" class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-6 hover-scale flex items-center justify-between group">
                    <div>
                        <i class="fas fa-cash-register text-3xl mb-2 opacity-80"></i>
                        <h4 class="text-lg font-semibold">Caja Actual</h4>
                        <p class="text-sm opacity-80">Ver estado de caja</p>
                    </div>
                    <i class="fas fa-arrow-right text-2xl group-hover:translate-x-2 transition-transform"></i>
                </a>


                <a href="{{ route('clientes.create') }}" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-6 hover-scale flex items-center justify-between group">
                    <div>
                        <i class="fas fa-user-plus text-3xl mb-2 opacity-80"></i>
                        <h4 class="text-lg font-semibold">Nuevo Cliente</h4>
                        <p class="text-sm opacity-80">Registrar cliente</p>
                    </div>
                    <i class="fas fa-arrow-right text-2xl group-hover:translate-x-2 transition-transform"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Esperar a que el DOM esté completamente cargado
        document.addEventListener('DOMContentLoaded', function() {
            const ctxHora = document.getElementById('ventasHoraChart').getContext('2d');
            
            // Generar datos de ejemplo para ventas por hora
            const horas = [];
            const ventasHora = [];
            
            for(let i = 8; i <= 20; i++) {
                horas.push(i + ':00');
                // Aquí deberías pasar datos reales desde el controlador
                ventasHora.push(Math.floor(Math.random() * 500) + 100);
            }

            new Chart(ctxHora, {
                type: 'bar',
                data: {
                    labels: horas,
                    datasets: [{
                        label: 'Ventas por Hora',
                        data: ventasHora,
                        backgroundColor: '#1e3a8a',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
