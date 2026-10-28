<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Cajas</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen" x-data="{ refreshIn: 30 }" x-init="setInterval(() => { if(--refreshIn <= 0) location.reload(); }, 1000)">

    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Dashboard Ejecutivo de Cajas</h1>
            <p class="text-sm text-gray-500">
                Supervisión en tiempo real · Actualiza en
                <span class="font-semibold text-blue-600" x-text="refreshIn"></span> s
                · {{ now()->format('d/m/Y H:i') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.cajas.alertas') }}"
               class="relative inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition">
                <i class="fas fa-bell"></i> Alertas
                @if($alertasCount > 0)
                    <span class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center font-bold">
                        {{ $alertasCount > 9 ? '9+' : $alertasCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.cajas.apertura-remota') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                <i class="fas fa-cash-register mr-1"></i> Apertura Remota
            </a>
            <a href="{{ route('admin.cajas.reportes') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-chart-bar mr-1"></i> Reportes
            </a>
            <a href="{{ route('admin.cajas.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-list mr-1"></i> Historial
            </a>
        </div>
    </div>

    <div class="p-6 space-y-6">

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Ventas hoy</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($totalVentasHoy, 2) }}</p>
                <p class="text-xs text-blue-500 mt-1">Efectivo: S/ {{ number_format($totalEfectivoHoy, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cajas abiertas</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $cajasAbiertas->count() }}</p>
                <p class="text-xs text-green-500 mt-1">{{ $cajasCerradasHoy }} cerradas hoy</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursales activas</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">
                    {{ $estadoSucursales->whereIn('estado_label', ['open','warning'])->count() }}
                    / {{ $estadoSucursales->count() }}
                </p>
                <p class="text-xs text-purple-500 mt-1">{{ $estadoSucursales->where('estado_label','warning')->count() }} con alerta</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 {{ abs($diferenciasMes) > 0 ? 'border-red-500' : 'border-gray-300' }}">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Diferencias (mes)</p>
                <p class="text-2xl font-bold {{ $diferenciasMes < 0 ? 'text-red-600' : 'text-gray-800' }} mt-1">
                    S/ {{ number_format($diferenciasMes, 2) }}
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ now()->format('m/Y') }}</p>
            </div>
        </div>

        {{-- Estado por sucursal --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-store text-blue-500"></i> Estado por Sucursal
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @forelse($estadoSucursales as $item)
                    @php
                        $colors = [
                            'open'    => ['bg' => 'bg-green-50 border-green-200',  'dot' => 'bg-green-500',            'label' => 'Abierta', 'txt' => 'text-green-700'],
                            'warning' => ['bg' => 'bg-yellow-50 border-yellow-200','dot' => 'bg-yellow-500 animate-pulse','label' => 'Alerta',  'txt' => 'text-yellow-700'],
                            'closed'  => ['bg' => 'bg-gray-50 border-gray-200',    'dot' => 'bg-gray-400',             'label' => 'Cerrada', 'txt' => 'text-gray-500'],
                        ];
                        $c = $colors[$item['estado_label']];
                    @endphp
                    <div class="border rounded-lg p-4 {{ $c['bg'] }}">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate">{{ $item['sucursal']->nombre }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $item['caja_abierta']?->usuario?->name ?? 'Sin cajero' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 ml-2 shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full {{ $c['dot'] }}"></span>
                                <span class="text-xs font-medium {{ $c['txt'] }}">{{ $c['label'] }}</span>
                            </div>
                        </div>
                        @if($item['caja_abierta'])
                            <div class="mt-3 pt-3 border-t border-black/5 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <p class="text-gray-400">Monto</p>
                                    <p class="font-semibold text-gray-700">S/ {{ number_format($item['caja_abierta']->monto_final, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Abierta hace</p>
                                    <p class="font-semibold {{ $item['alerta'] ? 'text-yellow-600' : 'text-gray-700' }}">
                                        {{ $item['horas_abierta'] }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('admin.cajas.show', $item['caja_abierta']->id) }}"
                               class="text-xs text-blue-600 hover:underline mt-2 block">Ver caja →</a>
                        @elseif($item['ultima_caja'])
                            <p class="text-xs text-gray-400 mt-2">
                                Último cierre: {{ $item['ultima_caja']->fecha_cierre?->format('d/m H:i') ?? '—' }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 col-span-full">No hay sucursales registradas.</p>
                @endforelse
            </div>
        </div>

        {{-- Top vendedores + Métodos de pago --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-trophy text-yellow-500"></i> Top 5 Vendedores Hoy
                </h2>
                @forelse($topVendedores as $i => $v)
                    @php $max = $topVendedores->first()?->total ?: 1; @endphp
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-400 w-4">#{{ $i+1 }}</span>
                                <span class="text-sm text-gray-700">{{ $v->name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-semibold text-gray-800">S/ {{ number_format($v->total, 2) }}</span>
                                <span class="text-xs text-gray-400 ml-1">({{ $v->ventas }} vtas)</span>
                            </div>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full"
                                 style="width: {{ min(100, ($v->total / $max) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-6">Sin ventas registradas hoy.</p>
                @endforelse
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-credit-card text-purple-500"></i> Métodos de Pago Hoy
                </h2>
                @php
                    $metodos = [
                        'efectivo'      => ['label' => 'Efectivo',      'icon' => 'fas fa-money-bill-wave', 'color' => 'bg-green-500'],
                        'yape'          => ['label' => 'Yape',          'icon' => 'fas fa-mobile-alt',      'color' => 'bg-purple-500'],
                        'plin'          => ['label' => 'Plin',          'icon' => 'fas fa-mobile-alt',      'color' => 'bg-teal-500'],
                        'transferencia' => ['label' => 'Transferencia', 'icon' => 'fas fa-university',      'color' => 'bg-blue-500'],
                        'mixto'         => ['label' => 'Mixto',         'icon' => 'fas fa-layer-group',     'color' => 'bg-orange-500'],
                    ];
                    $totalPago = $metodoPago->sum('total') ?: 1;
                @endphp
                <div class="space-y-3">
                    @foreach($metodos as $key => $m)
                        @php $monto = $metodoPago->get($key)?->total ?? 0; @endphp
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 {{ $m['color'] }} rounded-lg flex items-center justify-center shrink-0">
                                <i class="{{ $m['icon'] }} text-white text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-600">{{ $m['label'] }}</span>
                                    <span class="font-semibold text-gray-800">S/ {{ number_format($monto, 2) }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="{{ $m['color'] }} h-full rounded-full"
                                         style="width: {{ ($monto / $totalPago) * 100 }}%"></div>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 w-10 text-right shrink-0">
                                {{ $totalVentasHoy > 0 ? number_format(($monto / ($totalVentasHoy ?: 1)) * 100, 1) : 0 }}%
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
