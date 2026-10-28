<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas por Cobrar</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">

        {{-- Cabecera --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-hand-holding-usd mr-3 text-orange-600"></i>
                    Cuentas por Cobrar
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Créditos otorgados a clientes</p>
            </div>
            <a href="{{ route('ventas.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                <i class="fas fa-arrow-left"></i> Volver a Ventas
            </a>
        </div>

        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
        </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total pendiente</p>
                <p class="text-xl font-bold text-gray-900">S/ {{ number_format($stats['total_pendiente'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total vencido</p>
                <p class="text-xl font-bold text-red-600">S/ {{ number_format($stats['total_vencido'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-green-100 p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Cobrado este mes</p>
                <p class="text-xl font-bold text-green-600">S/ {{ number_format($stats['cobrado_mes'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-amber-100 p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Por vencer (7d)</p>
                <p class="text-xl font-bold text-amber-600">{{ $stats['por_vencer_7dias'] }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-orange-100 p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Cuotas vencidas</p>
                <p class="text-xl font-bold text-orange-600">{{ $stats['cuotas_vencidas'] }}</p>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="w-full sm:w-auto min-w-0">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Cliente</label>
                    <select name="cliente_id" class="w-full sm:w-auto sm:max-w-xs border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                        <option value="">Todos los clientes</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Estado</label>
                    <select name="estado" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                        <option value="">Todos</option>
                        <option value="vigente"  {{ request('estado') === 'vigente'  ? 'selected' : '' }}>Vigente</option>
                        <option value="vencido"  {{ request('estado') === 'vencido'  ? 'selected' : '' }}>Vencido</option>
                        <option value="pagado"   {{ request('estado') === 'pagado'   ? 'selected' : '' }}>Pagado</option>
                        <option value="anulado"  {{ request('estado') === 'anulado'  ? 'selected' : '' }}>Anulado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                           class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                </div>
                <button type="submit"
                        class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['cliente_id','estado','fecha_desde','fecha_hasta']))
                <a href="{{ route('cuentas-por-cobrar.index') }}"
                   class="border border-gray-200 text-gray-500 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-semibold transition-colors">
                    Limpiar
                </a>
                @endif
            </form>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-900">Cuentas ({{ $cuentas->total() }})</h2>
            </div>

            @if($cuentas->isEmpty())
            <div class="px-6 py-16 text-center text-gray-400">
                <i class="fas fa-file-invoice-dollar text-4xl mb-3 opacity-30"></i>
                <p class="text-sm">No hay cuentas por cobrar registradas</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Venta</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Pagado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Saldo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Vencimiento</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($cuentas as $cuenta)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $cuenta->cliente?->nombre }}</p>
                                <p class="text-xs text-gray-400">{{ $cuenta->cliente?->numero_documento }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('ventas.show', $cuenta->venta_id) }}"
                                   class="text-blue-600 hover:text-blue-700 font-mono text-xs font-medium">
                                    {{ $cuenta->venta?->codigo }}
                                </a>
                                <p class="text-xs text-gray-400">{{ $cuenta->fecha_inicio->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-700">
                                S/ {{ number_format($cuenta->monto_total, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-green-600">
                                S/ {{ number_format($cuenta->monto_pagado, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $cuenta->saldo_pendiente > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                                S/ {{ number_format($cuenta->saldo_pendiente, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center text-xs {{ $cuenta->esta_vencida ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                {{ $cuenta->fecha_vencimiento_final->format('d/m/Y') }}
                                @if($cuenta->esta_vencida)
                                <br><span class="text-[10px] text-red-400">Vencida</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $badge = match($cuenta->estado) {
                                        'vigente' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'vencido' => 'bg-red-50 text-red-700 border-red-200',
                                        'pagado'  => 'bg-green-50 text-green-700 border-green-200',
                                        'anulado' => 'bg-gray-100 text-gray-500 border-gray-200',
                                        default   => 'bg-gray-100 text-gray-500 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center border {{ $badge }} px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize">
                                    {{ $cuenta->estado }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('ventas.credito.show', $cuenta->venta_id) }}"
                                   class="text-xs text-orange-600 hover:text-orange-700 font-medium">
                                    Ver <i class="fas fa-arrow-right ml-0.5"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $cuentas->links() }}
            </div>
            @endif
        </div>

    </div>
</body>
</html>
