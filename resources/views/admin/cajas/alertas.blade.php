<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas de Caja</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
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
                <a href="{{ route('admin.cajas.dashboard') }}" class="hover:text-blue-600">Dashboard Cajas</a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Alertas</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                Alertas de Caja
                @if($alertasCount > 0)
                    <span class="text-sm font-semibold bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full">
                        {{ $alertasCount }} activa{{ $alertasCount > 1 ? 's' : '' }}
                    </span>
                @endif
            </h1>
            <p class="text-sm text-gray-500">Anomalías detectadas en el sistema de cajas</p>
        </div>
        <a href="{{ route('admin.cajas.dashboard') }}"
           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>

    <div class="p-6 space-y-4">

        @if($alertas->isEmpty())
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">Todo en orden</h3>
                <p class="text-gray-400 text-sm">No hay alertas activas en este momento.</p>
            </div>
        @else
            @php
                $sinCerrar   = $alertas->where('tipo', 'sin_cerrar');
                $cajasLargas = $alertas->where('tipo', 'caja_larga');
                $diferencias = $alertas->where('tipo', 'diferencia');
            @endphp

            {{-- Resumen --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-4">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-red-700">{{ $sinCerrar->count() }}</p>
                        <p class="text-xs text-red-500">Sin cerrar (día anterior)</p>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-4">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-yellow-700">{{ $cajasLargas->count() }}</p>
                        <p class="text-xs text-yellow-500">Abiertas más de 12 h</p>
                    </div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-center gap-4">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-balance-scale text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-orange-700">{{ $diferencias->count() }}</p>
                        <p class="text-xs text-orange-500">Diferencias > S/100 (7 días)</p>
                    </div>
                </div>
            </div>

            {{-- Lista --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100">
                    @foreach($alertas as $alerta)
                        @php
                            $esCritico = in_array($alerta['tipo'], ['sin_cerrar', 'diferencia']);
                            $color = $esCritico
                                ? ['bg' => 'bg-red-50',    'icon_bg' => 'bg-red-100',    'icon_txt' => 'text-red-600',    'border' => 'border-red-200']
                                : ['bg' => 'bg-yellow-50', 'icon_bg' => 'bg-yellow-100', 'icon_txt' => 'text-yellow-600', 'border' => 'border-yellow-200'];
                            $icon = match($alerta['tipo']) {
                                'sin_cerrar' => 'fas fa-door-open',
                                'caja_larga' => 'fas fa-clock',
                                'diferencia' => 'fas fa-balance-scale',
                                default      => 'fas fa-bell',
                            };
                            $tipoLabel = match($alerta['tipo']) {
                                'sin_cerrar' => 'Sin cerrar',
                                'caja_larga' => 'Tiempo excedido',
                                'diferencia' => 'Diferencia alta',
                                default      => 'Alerta',
                            };
                        @endphp
                        <div class="flex items-start gap-4 px-5 py-4 {{ $color['bg'] }}">
                            <div class="w-9 h-9 rounded-lg {{ $color['icon_bg'] }} flex items-center justify-center shrink-0 mt-0.5">
                                <i class="{{ $icon }} {{ $color['icon_txt'] }} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-xs font-semibold {{ $color['icon_txt'] }} uppercase tracking-wider">{{ $tipoLabel }}</span>
                                    <span class="text-xs text-gray-400">Caja #{{ $alerta['caja']->id }}</span>
                                </div>
                                <p class="text-sm text-gray-700">{{ $alerta['mensaje'] }}</p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <a href="{{ route('admin.cajas.show', $alerta['caja']->id) }}"
                                   class="px-3 py-1.5 text-xs font-medium border {{ $color['border'] }} {{ $color['icon_txt'] }} rounded-lg hover:opacity-80 transition">
                                    Ver caja →
                                </a>
                                @if($alerta['caja']->estado === 'abierta')
                                    <a href="{{ route('admin.cajas.show', $alerta['caja']->id) }}"
                                       class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                        Forzar cierre
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
</body>
</html>
