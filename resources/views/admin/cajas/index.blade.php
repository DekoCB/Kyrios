<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cajas</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Historial de Cajas</h1>
            <p class="text-sm text-gray-500">Filtros avanzados y exportación</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cajas.alertas') }}"
               class="relative inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition">
                <i class="fas fa-bell"></i> Alertas
                @if($alertasCount > 0)
                    <span class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center font-bold">
                        {{ $alertasCount > 9 ? '9+' : $alertasCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.cajas.dashboard') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="p-6 space-y-4">

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <form method="GET" action="{{ route('admin.cajas.index') }}"
                  class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sucursal</label>
                    <select name="sucursal_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas</option>
                        @foreach($sucursales as $s)
                            <option value="{{ $s->id }}" {{ request('sucursal_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cajero</label>
                    <select name="user_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select name="estado" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="abierta" {{ request('estado') === 'abierta' ? 'selected' : '' }}>Abierta</option>
                        <option value="cerrada" {{ request('estado') === 'cerrada' ? 'selected' : '' }}>Cerrada</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 bg-blue-600 text-white text-sm rounded-lg px-3 py-2 hover:bg-blue-700 transition font-medium">
                        <i class="fas fa-search mr-1"></i> Filtrar
                    </button>
                    <a href="{{ route('admin.cajas.index') }}"
                       class="px-3 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition text-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                {{-- Exportar --}}
                <div class="col-span-2 md:col-span-3 lg:col-span-6 flex justify-end items-center gap-2 border-t pt-3 mt-1">
                    <span class="text-xs text-gray-500">Exportar con filtros actuales:</span>
                    <button type="submit" name="export" value="csv"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition font-medium">
                        <i class="fas fa-file-csv"></i> CSV / Excel
                    </button>
                    <button type="submit" name="export" value="pdf"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 transition font-medium">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </form>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cajero</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Sucursal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Apertura</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cierre</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">M. Inicial</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Ventas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-green-700 uppercase">Efectivo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-purple-700 uppercase">Yape</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-blue-700 uppercase">Plin</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-teal-700 uppercase">Transfer</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Diferencia</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($cajas as $caja)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ $caja->id }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $caja->usuario?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $caja->sucursal?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                                    {{ $caja->fecha_apertura?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                                    {{ $caja->fecha_cierre?->format('d/m/Y H:i') ?? '—' }}
                                    @php
                                        $numDevoluciones = $caja->movimientos
                                            ->where('tipo', 'egreso')
                                            ->whereNotNull('venta_id')
                                            ->count();
                                    @endphp
                                    @if($numDevoluciones > 0)
                                        <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-600 whitespace-nowrap">
                                            <i class="fas fa-rotate-left"></i> {{ $numDevoluciones }} dev./anul.
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">S/ {{ number_format($caja->monto_inicial, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">S/ {{ number_format($caja->total_ventas, 2) }}</td>
                                <td class="px-4 py-3 text-right text-green-700 text-xs">S/ {{ number_format($caja->arqueo['ventas_efectivo'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-purple-700 text-xs">S/ {{ number_format($caja->arqueo['ventas_yape'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-blue-700 text-xs">S/ {{ number_format($caja->arqueo['ventas_plin'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-teal-700 text-xs">S/ {{ number_format($caja->arqueo['ventas_transferencia'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($caja->diferencia_cierre !== null)
                                        <span class="{{ $caja->diferencia_cierre < 0 ? 'text-red-600 font-semibold' : ($caja->diferencia_cierre > 0 ? 'text-green-600' : 'text-gray-400') }}">
                                            S/ {{ number_format($caja->diferencia_cierre, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($caja->estado === 'abierta')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Abierta
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span> Cerrada
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.cajas.show', $caja) }}"
                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ver →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-4 py-10 text-center text-gray-400">
                                    <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                    No se encontraron cajas con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($cajas->count() > 0)
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-xs text-gray-500">
                                {{ $cajas->total() }} registros
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-gray-700">
                                S/ {{ number_format($cajas->sum('monto_inicial'), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-gray-700">
                                S/ {{ number_format($cajas->sum('total_ventas'), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-green-700">
                                S/ {{ number_format($cajas->sum(fn($c) => $c->arqueo['ventas_efectivo'] ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-purple-700">
                                S/ {{ number_format($cajas->sum(fn($c) => $c->arqueo['ventas_yape'] ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-blue-700">
                                S/ {{ number_format($cajas->sum(fn($c) => $c->arqueo['ventas_plin'] ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold text-teal-700">
                                S/ {{ number_format($cajas->sum(fn($c) => $c->arqueo['ventas_transferencia'] ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-semibold {{ $cajas->sum('diferencia_cierre') < 0 ? 'text-red-600' : 'text-gray-700' }}">
                                S/ {{ number_format($cajas->sum('diferencia_cierre'), 2) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            @if($cajas->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $cajas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
