<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Comisiones & Bonos</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="{ tab: 'comisiones' }">

    <x-header title="Mis Comisiones & Bonos" subtitle="Tu historial de comisiones y bonos generados" />

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <i class="fas fa-clock text-amber-500"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Comisiones por cobrar</p>
                <p class="text-lg font-bold text-gray-800">S/ {{ number_format($totales['comision_pendiente'], 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Comisiones cobradas</p>
                <p class="text-lg font-bold text-gray-800">S/ {{ number_format($totales['comision_pagado'], 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">
                <i class="fas fa-star text-orange-400"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Bonos por cobrar</p>
                <p class="text-lg font-bold text-gray-800">S/ {{ number_format($totales['bonus_pendiente'], 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <i class="fas fa-wallet text-blue-500"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500">Total acumulado</p>
                <p class="text-lg font-bold text-blue-700">
                    S/ {{ number_format($totales['comision_pendiente'] + $totales['comision_pagado'] + $totales['bonus_pendiente'] + $totales['bonus_pagado'], 2) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <select name="estado" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none">
                <option value="">Todos los estados</option>
                <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="pagado"    {{ request('estado') === 'pagado'    ? 'selected' : '' }}>Pagado</option>
            </select>
            <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                   class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none">
            <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                   class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-gray-800 text-white rounded-xl px-4 py-2 text-sm hover:bg-gray-700 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-search text-xs"></i> Filtrar
                </button>
                @if(request()->hasAny(['estado','fecha_desde','fecha_hasta']))
                    <a href="{{ route('mis-comisiones') }}" class="w-9 h-9 flex items-center justify-center border border-gray-200 rounded-xl text-gray-400 hover:text-red-500 hover:border-red-300 transition-colors">
                        <i class="fas fa-times text-xs"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Tabs --}}
    <div class="flex gap-2 mb-4">
        <button @click="tab='comisiones'"
                :class="tab==='comisiones' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-percentage text-xs"></i> Comisiones
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                  :class="tab==='comisiones' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'">
                {{ $comisiones->total() }}
            </span>
        </button>
        <button @click="tab='bonos'"
                :class="tab==='bonos' ? 'bg-amber-500 text-white shadow' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-star text-xs"></i> Bonos
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                  :class="tab==='bonos' ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'">
                {{ $bonos->total() }}
            </span>
        </button>
    </div>

    {{-- Tab Comisiones --}}
    <div x-show="tab==='comisiones'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($comisiones->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <i class="fas fa-percentage text-4xl mb-3 block opacity-20"></i>
                <p class="font-medium">No hay comisiones registradas.</p>
                <p class="text-sm mt-1">Las comisiones se generan automáticamente al registrar ventas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Venta</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Regla</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Venta S/</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Comisión</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($comisiones as $c)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                {{ $c->detalleVenta?->venta?->fecha ? \Carbon\Carbon::parse($c->detalleVenta->venta->fecha)->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-blue-600">
                                    {{ $c->detalleVenta?->venta?->codigo ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 max-w-[160px] truncate">
                                {{ $c->detalleVenta?->producto ? \App\Support\EtiquetaProducto::nombre($c->detalleVenta?->producto) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs text-gray-500">{{ $c->regla?->nombre ?? '—' }}</span>
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full
                                    {{ $c->tipo_calculo === 'porcentaje_margen' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $c->tipo_calculo === 'porcentaje'        ? '%venta'
                                      : ($c->tipo_calculo === 'porcentaje_margen' ? '%margen'
                                      : 'S/fijo') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 text-xs">
                                S/ {{ number_format($c->detalleVenta?->subtotal_con_igv ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                S/ {{ number_format($c->monto_comision, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($c->estado === 'pagado')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                        <i class="fas fa-check text-[8px]"></i> Cobrado
                                    </span>
                                    @if($c->fecha_pago)
                                        <div class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($c->fecha_pago)->format('d/m/Y') }}</div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-amber-100 text-amber-700">
                                        <i class="fas fa-clock text-[8px]"></i> Pendiente
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-blue-50 border-t border-blue-100">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-xs font-semibold text-blue-700">
                                Total página:
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-blue-700">
                                S/ {{ number_format($comisiones->sum('monto_comision'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $comisiones->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Tab Bonos --}}
    <div x-show="tab==='bonos'" style="display:none" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($bonos->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <i class="fas fa-star text-4xl mb-3 block opacity-20"></i>
                <p class="font-medium">No hay bonos registrados.</p>
                <p class="text-sm mt-1">Los bonos se generan cuando vendes productos con reglas de bono activas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Regla / Detalle</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Bono</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($bonos as $b)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                {{ $b->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($b->tipo_origen === 'fijo')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                        <i class="fas fa-bolt text-[8px]"></i> Fijo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-purple-100 text-purple-700">
                                        <i class="fas fa-trophy text-[8px]"></i> Meta
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-gray-700 text-xs">{{ $b->regla?->nombre ?? '—' }}</p>
                                @if($b->tipo_origen === 'meta')
                                    <p class="text-[10px] text-purple-600 mt-0.5">
                                        {{ $b->unidades_periodo }} uds · {{ \Carbon\Carbon::parse($b->periodo_inicio)->format('d/m') }} – {{ \Carbon\Carbon::parse($b->periodo_fin)->format('d/m/Y') }}
                                    </p>
                                @elseif($b->detalleVenta?->venta)
                                    <p class="text-[10px] text-gray-400 mt-0.5">Venta {{ $b->detalleVenta->venta->codigo }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs max-w-[140px] truncate">
                                {{ $b->detalleVenta?->producto?->nombre ?? ($b->regla?->tipo_aplicacion === 'categoria' ? 'Categoría: ' . ($b->regla?->categoria?->nombre ?? '—') : '—') }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-700">
                                S/ {{ number_format($b->monto_bonus, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($b->estado === 'pagado')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                        <i class="fas fa-check text-[8px]"></i> Cobrado
                                    </span>
                                    @if($b->fecha_pago)
                                        <div class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($b->fecha_pago)->format('d/m/Y') }}</div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full bg-amber-100 text-amber-700">
                                        <i class="fas fa-clock text-[8px]"></i> Pendiente
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-amber-50 border-t border-amber-100">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right text-xs font-semibold text-amber-700">
                                Total página:
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-amber-700">
                                S/ {{ number_format($bonos->sum('monto_bonus'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $bonos->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Nota informativa --}}
    <div class="mt-6 bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-3">
        <i class="fas fa-info-circle text-blue-400 mt-0.5 shrink-0"></i>
        <div class="text-sm text-blue-700">
            <p class="font-semibold">¿Cómo se calculan?</p>
            <p class="mt-1 text-blue-600">Las comisiones y bonos se generan automáticamente al registrar cada venta. El pago lo realiza el administrador. Si tienes dudas sobre algún monto, consulta con tu supervisor.</p>
        </div>
    </div>

</div>
</body>
</html>
