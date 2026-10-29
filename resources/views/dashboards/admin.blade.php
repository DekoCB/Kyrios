{{-- resources/views/dashboards/admin.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .hover-scale { transition: transform 0.2s ease-in-out; }
        .hover-scale:hover { transform: translateY(-5px); }
        .chart-container { position: relative; height: 250px; width: 100%; }
        .chart-card { min-height: 350px; display: flex; flex-direction: column; }
        .chart-card .chart-wrapper { flex: 1; min-height: 0; }
        .stat-value { font-size: 1.8rem; font-weight: 700; line-height: 1.2; }
        .trend-up   { color: #10b981; background: #d1fae5; padding: .25rem .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; }
        .trend-down { color: #ef4444; background: #fee2e2; padding: .25rem .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; }
        .trend-flat { color: #6b7280; background: #f3f4f6; padding: .25rem .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; }
    </style>
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 min-h-screen bg-gray-100">

        {{-- Top Bar --}}
        <div class="bg-white shadow-sm sticky top-0 z-20">
            <div class="px-6 py-3 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-chart-line text-blue-900 mr-2"></i>
                        ¡Hola, {{ auth()->user()->name }}!
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, d F Y') }} |
                        <i class="far fa-clock mr-1"></i>{{ now()->format('h:i A') }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">

                    {{-- Campana de Notificaciones --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open"
                                class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
                            <i class="fas fa-bell text-gray-600 text-xl"></i>
                            @if($total_notificaciones > 0)
                                <span class="absolute top-1 right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full
                                             flex items-center justify-center text-white text-[10px] font-bold px-1">
                                    {{ $total_notificaciones > 99 ? '99+' : $total_notificaciones }}
                                </span>
                            @endif
                        </button>

                        {{-- Dropdown de Notificaciones --}}
                        <div x-show="open"
                             x-cloak
                             class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">

                            <div class="px-4 py-3 bg-blue-900 text-white flex justify-between items-center">
                                <span class="font-semibold text-sm">
                                    <i class="fas fa-bell mr-2"></i>Notificaciones
                                </span>
                                <span class="bg-white text-blue-900 text-xs font-bold px-2 py-0.5 rounded-full">
                                    {{ $total_notificaciones }}
                                </span>
                            </div>

                            <div class="max-h-96 overflow-y-auto">

                                {{-- Cuotas por vencer / vencidas --}}
                                @if($notif_cuotas->count() > 0)
                                <div class="px-3 py-2 bg-gray-50 border-b border-gray-200">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        <i class="fas fa-file-invoice-dollar mr-1 text-red-500"></i>
                                        Cuotas ({{ $notif_cuotas->count() }})
                                    </p>
                                </div>
                                @foreach($notif_cuotas as $cuota)
                                @php
                                    $diasRestantes = now()->diffInDays($cuota->fecha_vencimiento, false);
                                    $vencida = $diasRestantes < 0;
                                    $hoy     = $diasRestantes === 0;
                                @endphp
                                <a href="{{ route('cuentas-por-pagar.index') }}"
                                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-100 transition-colors">
                                    <div class="mt-0.5 shrink-0">
                                        <span class="w-8 h-8 rounded-full flex items-center justify-center
                                            {{ $vencida ? 'bg-red-100' : ($hoy ? 'bg-orange-100' : 'bg-yellow-100') }}">
                                            <i class="fas fa-exclamation-circle text-sm
                                                {{ $vencida ? 'text-red-600' : ($hoy ? 'text-orange-600' : 'text-yellow-600') }}"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $cuota->cuentaPorPagar->proveedor->razon_social ?? 'Proveedor' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Cuota {{ $cuota->numero_cuota }}/{{ $cuota->total_cuotas }} —
                                            S/ {{ number_format($cuota->monto, 2) }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        @if($vencida)
                                            <span class="text-xs font-semibold text-red-600">VENCIDA</span>
                                        @elseif($hoy)
                                            <span class="text-xs font-semibold text-orange-600">Hoy</span>
                                        @else
                                            <span class="text-xs font-semibold text-yellow-700">{{ $diasRestantes }}d</span>
                                        @endif
                                        <p class="text-xs text-gray-400">{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</p>
                                    </div>
                                </a>
                                @endforeach
                                @endif

                                {{-- Productos bajo stock --}}
                                @if($notif_stock_bajo->count() > 0)
                                <div class="px-3 py-2 bg-gray-50 border-b border-gray-200">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        <i class="fas fa-boxes mr-1 text-orange-500"></i>
                                        Stock Bajo ({{ $notif_stock_bajo->count() }})
                                    </p>
                                </div>
                                @foreach($notif_stock_bajo as $prod)
                                <a href="{{ route('inventario.productos.index') }}"
                                   class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-100 transition-colors">
                                    <span class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                                        <i class="fas fa-box text-orange-500 text-sm"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $prod->nombre }}</p>
                                        <p class="text-xs text-gray-500">Mínimo: {{ $prod->stock_minimo }} und</p>
                                    </div>
                                    <span class="text-sm font-bold text-orange-600 shrink-0">
                                        {{ $prod->stock_actual }} und
                                    </span>
                                </a>
                                @endforeach
                                @endif

                                @if($total_notificaciones === 0)
                                <div class="px-4 py-8 text-center text-gray-400">
                                    <i class="fas fa-check-circle text-3xl text-green-400 mb-2 block"></i>
                                    <p class="text-sm">Todo en orden, sin alertas</p>
                                </div>
                                @endif
                            </div>

                            <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-center">
                                <a href="{{ route('cuentas-por-pagar.index') }}"
                                   class="text-xs text-blue-900 hover:underline font-medium">
                                    Ver cuentas por pagar <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Avatar + nombre --}}
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-900 to-blue-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        {{ substr(auth()->user()->name, 0, 2) }}
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500">Administrador</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">

            {{-- ⚠ ALERTA: CAJAS DE DÍAS ANTERIORES SIN CERRAR --}}
            @if(isset($cajas_atrasadas) && $cajas_atrasadas->count() > 0)
            <div class="mb-6 bg-red-50 border-2 border-red-400 rounded-xl p-5 shadow-md">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-red-800 font-bold text-lg mb-1">
                            {{ $cajas_atrasadas->count() }} {{ $cajas_atrasadas->count() == 1 ? 'Caja sin cerrar' : 'Cajas sin cerrar' }} de días anteriores
                        </h3>
                        <p class="text-red-700 text-sm mb-3">
                            Los siguientes usuarios tienen cajas abiertas de días anteriores que no fueron cerradas. Contacta al cajero o usa la opción "Forzar Cierre".
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-red-700 border-b border-red-200">
                                        <th class="pb-2 pr-4 font-semibold">Cajero</th>
                                        <th class="pb-2 pr-4 font-semibold">Fecha</th>
                                        <th class="pb-2 pr-4 font-semibold">Almacén</th>
                                        <th class="pb-2 pr-4 font-semibold">Saldo</th>
                                        <th class="pb-2 font-semibold">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cajas_atrasadas as $cajaAtras)
                                    @php
                                        $apertura  = $cajaAtras->fecha_apertura
                                            ? \Carbon\Carbon::parse($cajaAtras->fecha_apertura)
                                            : \Carbon\Carbon::parse($cajaAtras->fecha);
                                        $totalMin  = (int) $apertura->diffInMinutes(now());
                                        $dias      = intdiv($totalMin, 1440);
                                        $horas     = intdiv($totalMin % 1440, 60);
                                        $minutos   = $totalMin % 60;
                                        $tiempoStr = '';
                                        if ($dias > 0)   $tiempoStr .= $dias . 'd ';
                                        if ($horas > 0)  $tiempoStr .= $horas . 'h ';
                                        $tiempoStr .= $minutos . 'min';
                                    @endphp
                                    <tr class="border-b border-red-100 last:border-0">
                                        <td class="py-2 pr-4 font-medium text-gray-800">
                                            {{ $cajaAtras->usuario->name ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4 text-red-700">
                                            {{ \Carbon\Carbon::parse($cajaAtras->fecha)->locale('es')->isoFormat('D [de] MMMM, YYYY') }}
                                            <span class="ml-1 text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">
                                                hace {{ trim($tiempoStr) }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-600">{{ $cajaAtras->almacen->nombre ?? '—' }}</td>
                                        <td class="py-2 pr-4 font-semibold text-gray-800">S/ {{ number_format($cajaAtras->monto_final, 2) }}</td>
                                        <td class="py-2">
                                            <a href="{{ route('admin.cajas.show', $cajaAtras->id) }}"
                                               class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-semibold transition-colors">
                                                <i class="fas fa-lock text-xs"></i> Cerrar
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- FILA 1: KPIs Principales                                --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

                {{-- Ventas del Mes --}}
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-blue-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Ventas del Mes</p>
                            <p class="stat-value text-gray-900">S/ {{ number_format($ventas_mes_actual, 0, '.', ',') }}</p>
                            <div class="flex items-center mt-2 gap-2">
                                @if($variacion_ventas > 0)
                                    <span class="trend-up"><i class="fas fa-arrow-up mr-1"></i>+{{ $variacion_ventas }}%</span>
                                @elseif($variacion_ventas < 0)
                                    <span class="trend-down"><i class="fas fa-arrow-down mr-1"></i>{{ $variacion_ventas }}%</span>
                                @else
                                    <span class="trend-flat"><i class="fas fa-minus mr-1"></i>0%</span>
                                @endif
                                <span class="text-xs text-gray-500">vs mes anterior</span>
                            </div>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-blue-900 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-history mr-1"></i>
                            Mes anterior: <span class="font-semibold text-gray-700">S/ {{ number_format($ventas_mes_anterior, 0, '.', ',') }}</span>
                        </p>
                    </div>
                </div>

                {{-- Comprobantes Emitidos (Boletas y Facturas) --}}
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-purple-600">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Comprobantes Emitidos</p>
                            <p class="stat-value text-gray-900">{{ number_format($boletas_total + $facturas_total) }}</p>
                            <p class="text-xs text-gray-500 mt-1">boletas y facturas</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-file-invoice text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-center">
                        <div class="bg-blue-50 rounded-lg p-2">
                            <p class="text-xs text-gray-500">Boletas</p>
                            <p class="text-sm font-bold text-blue-700">{{ number_format($boletas_total) }}</p>
                            <p class="text-[10px] text-gray-400">mes: {{ $boletas_mes }}</p>
                        </div>
                        <div class="bg-indigo-50 rounded-lg p-2">
                            <p class="text-xs text-gray-500">Facturas</p>
                            <p class="text-sm font-bold text-indigo-700">{{ number_format($facturas_total) }}</p>
                            <p class="text-[10px] text-gray-400">mes: {{ $facturas_mes }}</p>
                        </div>
                    </div>
                </div>

                {{-- Unidades en stock --}}
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-green-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Unidades en stock</p>
                            <p class="stat-value text-gray-900">{{ number_format($stock_total) }}</p>
                            <div class="flex flex-wrap items-center mt-2 gap-1">
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                    <i class="fas fa-boxes mr-1"></i>todos los almacenes
                                </span>
                            </div>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-boxes text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                {{-- Alertas de Stock --}}
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale border-l-4 border-orange-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Alertas de Stock</p>
                            <p class="stat-value {{ $productos_bajo_stock > 0 ? 'text-orange-600' : 'text-green-600' }}">
                                {{ $productos_bajo_stock }}
                            </p>
                            <p class="text-xs text-orange-600 mt-1">productos por reabastecer</p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-orange-600 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        @forelse($productos_bajo_stock_lista as $pa)
                            <div class="flex justify-between text-xs">
                                <span class="truncate max-w-[120px]" title="{{ $pa->nombre }}">{{ $pa->nombre }}</span>
                                <span class="font-semibold text-orange-600 ml-1">{{ $pa->stock_actual }} und</span>
                            </div>
                        @empty
                            <p class="text-xs text-green-600"><i class="fas fa-check mr-1"></i>Todo en orden</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- FILA 2: KPIs Secundarios                                --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-store text-blue-900"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Sucursales</p>
                        <p class="text-xl font-bold text-gray-900">{{ $total_sucursales }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-warehouse text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Almacenes</p>
                        <p class="text-xl font-bold text-gray-900">{{ $total_almacenes }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-truck text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Proveedores</p>
                        <p class="text-xl font-bold text-gray-900">{{ $total_proveedores }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-teal-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-users text-teal-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Clientes</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($total_clientes) }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-exchange-alt text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Traslados Pend.</p>
                        <p class="text-xl font-bold text-gray-900">{{ $traslados_pendientes }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center shrink-0">
                        <i class="fas fa-shopping-bag text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Compras Mes</p>
                        <p class="text-lg font-bold text-gray-900">S/ {{ number_format($compras_mes_actual, 0, '.', ',') }}</p>
                    </div>
                </div>
            </div>

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- FILA 3: Gráfico + Top Productos                         --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                {{-- Gráfico Ventas Mensuales --}}
                <div class="bg-white rounded-xl shadow-lg p-6 chart-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            <i class="fas fa-chart-line text-blue-900 mr-2"></i>
                            Ventas Mensuales {{ $anio_chart }}
                        </h3>
                        <span class="text-xs text-gray-500 bg-gray-100 rounded px-2 py-1">S/ Soles</span>
                    </div>
                    <div class="chart-wrapper">
                        <div class="chart-container">
                            <canvas id="ventasChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Top Productos Más Vendidos --}}
                <div class="bg-white rounded-xl shadow-lg p-6 chart-card">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-crown text-yellow-500 mr-2"></i>
                        Top Productos Vendidos
                        <span class="text-sm font-normal text-gray-500">(mes actual)</span>
                    </h3>
                    @if($top_productos->isEmpty())
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p class="text-sm">Sin ventas registradas este mes</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($top_productos as $prod)
                            @php $pct = $max_vendido > 0 ? round(($prod->total_vendido / $max_vendido) * 100) : 0; @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700 truncate max-w-[200px]" title="{{ $prod->nombre }}">
                                        {{ $prod->nombre }}
                                    </span>
                                    <span class="text-gray-900 font-bold ml-2 shrink-0">{{ $prod->total_vendido }} und</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-900 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- FILA 4: Últimos Movimientos                             --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-history text-blue-900 mr-2"></i>
                        Últimos Movimientos de Inventario
                    </h3>
                    <a href="{{ route('inventario.movimientos.index') }}" class="text-sm text-blue-900 hover:text-blue-700 font-semibold">
                        Ver todos <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Producto</th>
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Cant.</th>
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Almacén</th>
                                <th class="text-left py-3 text-xs font-semibold text-gray-600 uppercase">Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ultimos_movimientos as $mov)
                            @php
                                $colorMap = [
                                    'ingreso'       => 'bg-green-100 text-green-800',
                                    'salida'        => 'bg-red-100 text-red-800',
                                    'transferencia' => 'bg-yellow-100 text-yellow-800',
                                    'ajuste'        => 'bg-blue-100 text-blue-800',
                                    'devolucion'    => 'bg-orange-100 text-orange-800',
                                    'merma'         => 'bg-gray-100 text-gray-800',
                                ];
                                $colorClass = $colorMap[$mov->tipo_movimiento] ?? 'bg-gray-100 text-gray-800';
                                $signo = in_array($mov->tipo_movimiento, ['ingreso','devolucion']) ? '+' : '-';
                            @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 text-sm text-gray-600">
                                    {{ $mov->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 text-sm font-medium text-gray-900">
                                    {{ $mov->producto ? \App\Support\EtiquetaProducto::nombre($mov->producto) : 'N/A' }}
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $colorClass }}">
                                        {{ ucfirst($mov->tipo_movimiento) }}
                                    </span>
                                </td>
                                <td class="py-3 text-sm font-semibold {{ in_array($mov->tipo_movimiento, ['ingreso','devolucion']) ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $signo }}{{ $mov->cantidad }}
                                </td>
                                <td class="py-3 text-sm text-gray-600">
                                    {{ $mov->almacen->nombre ?? 'N/A' }}
                                    @if($mov->almacenDestino)
                                        <span class="text-gray-400 mx-1">→</span>{{ $mov->almacenDestino->nombre }}
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-600">
                                    {{ $mov->usuario->name ?? 'Sistema' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400 text-sm">
                                    <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                    No hay movimientos registrados
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- FILA 5: Usuarios + Acciones Rápidas                     --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Usuarios por Rol --}}
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-users text-blue-900 mr-2"></i>
                        Usuarios del Sistema
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-gray-500">Total</p>
                            <p class="text-2xl font-bold text-blue-900">{{ $total_usuarios }}</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-gray-500">Activos</p>
                            <p class="text-2xl font-bold text-green-600">{{ $usuarios_activos }}</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach($usuarios_por_rol as $rol)
                        @php $porcentaje = $total_usuarios > 0 ? round(($rol->total / $total_usuarios) * 100) : 0; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ $rol->nombre }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-900">{{ $rol->total }}</span>
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-900 h-2 rounded-full" style="width: {{ $porcentaje }}%"></div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Acciones Rápidas --}}
                <div class="rounded-xl shadow-lg p-6 text-white"
                     style="background: linear-gradient(135deg, #44413A 0%, #44413A 100%);">
                    <h3 class="text-lg font-bold mb-4 text-white">
                        <i class="fas fa-bolt mr-2 text-yellow-300"></i>Acciones Rápidas
                    </h3>
                    <div class="grid grid-cols-3 gap-3">
                        <a href="{{ route('ventas.create') }}"
                           class="flex flex-col items-center justify-center rounded-lg p-4 text-center text-white transition-all hover:scale-105"
                           style="background: rgba(255,255,255,0.18);">
                            <i class="fas fa-shopping-cart text-2xl mb-2 text-white"></i>
                            <p class="text-sm font-semibold text-white">Nueva Venta</p>
                            <p class="text-xs mt-0.5" style="color: rgba(255,255,255,0.75);">Registrar venta</p>
                        </a>
                        <a href="{{ route('users.create') }}"
                           class="flex flex-col items-center justify-center rounded-lg p-4 text-center text-white transition-all hover:scale-105"
                           style="background: rgba(255,255,255,0.18);">
                            <i class="fas fa-user-plus text-2xl mb-2 text-white"></i>
                            <p class="text-sm font-semibold text-white">Nuevo Usuario</p>
                            <p class="text-xs mt-0.5" style="color: rgba(255,255,255,0.75);">Crear cuenta</p>
                        </a>
                        <a href="{{ route('inventario.productos.create') }}"
                           class="flex flex-col items-center justify-center rounded-lg p-4 text-center text-white transition-all hover:scale-105"
                           style="background: rgba(255,255,255,0.18);">
                            <i class="fas fa-box text-2xl mb-2 text-white"></i>
                            <p class="text-sm font-semibold text-white">Nuevo Producto</p>
                            <p class="text-xs mt-0.5" style="color: rgba(255,255,255,0.75);">Agregar catálogo</p>
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <a href="{{ route('compras.create') }}"
                           class="flex items-center justify-center rounded-lg p-3 text-sm font-medium text-white transition-all hover:scale-105"
                           style="background: rgba(255,255,255,0.12);">
                            <i class="fas fa-truck mr-2 text-white"></i>
                            <span class="text-white">Nueva Compra</span>
                        </a>
                        <a href="{{ route('clientes.index') }}"
                           class="flex items-center justify-center rounded-lg p-3 text-sm font-medium text-white transition-all hover:scale-105"
                           style="background: rgba(255,255,255,0.12);">
                            <i class="fas fa-users mr-2 text-white"></i>
                            <span class="text-white">Ver Clientes</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>{{-- /p-6 --}}
    </div>{{-- /md:ml-64 --}}

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ventasData = @json($ventas_mensuales_chart);

        const ctx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
                datasets: [{
                    label: 'Ventas {{ $anio_chart }}',
                    data: ventasData,
                    borderColor: '#C9A500',
                    backgroundColor: 'rgba(201,165,0, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#C9A500',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => 'S/ ' + ctx.parsed.y.toLocaleString('es-PE')
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: v => 'S/ ' + v.toLocaleString('es-PE')
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>
