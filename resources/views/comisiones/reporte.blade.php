<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Comisiones & Bonos</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="reporteApp()">

    <x-header title="Reporte de Comisiones & Bonos" subtitle="Liquidación y pago de comisiones y bonos por vendedor" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <a href="{{ route('comisiones.index') }}"
           class="text-sm text-gray-500 hover:text-blue-700 flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Volver a configuración
        </a>
        <a href="{{ route('comisiones.progreso-bonos') }}"
           class="text-sm text-gray-500 hover:text-purple-700 flex items-center gap-1">
            <i class="fas fa-trophy"></i> Ver progreso de metas
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <select name="user_id" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos los vendedores</option>
                @foreach($vendedores as $v)
                    <option value="{{ $v->id }}" {{ request('user_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                @endforeach
            </select>
            <select name="estado" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos los estados</option>
                <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="pagado"    {{ request('estado') === 'pagado'    ? 'selected' : '' }}>Pagado</option>
            </select>
            <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-lg transition">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="{{ route('comisiones.reporte') }}" class="px-3 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 flex items-center">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
    </form>

    {{-- Resumen por vendedor --}}
    @if($resumen->count())
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @foreach($resumen as $row)
        @php $total = $row['comision_pendiente'] + $row['comision_pagado'] + $row['bonus_pendiente'] + $row['bonus_pagado']; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl bg-blue-700 flex items-center justify-center text-white font-bold text-sm shrink-0">
                    {{ strtoupper(substr($row['vendedor']?->name ?? '?', 0, 1)) }}
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $row['vendedor']?->name ?? 'Desconocido' }}</p>
                    <p class="text-xs text-gray-400">Total acumulado: <strong class="text-gray-700">S/ {{ number_format($total, 2) }}</strong></p>
                </div>
            </div>
            <div class="space-y-1.5">
                <div class="flex justify-between text-xs">
                    <span class="flex items-center gap-1 text-gray-500"><i class="fas fa-percentage text-blue-400"></i> Comisión pendiente</span>
                    <span class="font-semibold text-amber-600">S/ {{ number_format($row['comision_pendiente'], 2) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="flex items-center gap-1 text-gray-500"><i class="fas fa-percentage text-green-400"></i> Comisión pagada</span>
                    <span class="font-semibold text-green-600">S/ {{ number_format($row['comision_pagado'], 2) }}</span>
                </div>
                <div class="border-t border-dashed border-gray-100 pt-1.5 mt-1.5"></div>
                <div class="flex justify-between text-xs">
                    <span class="flex items-center gap-1 text-gray-500"><i class="fas fa-star text-amber-400"></i> Bono pendiente</span>
                    <span class="font-semibold text-amber-600">S/ {{ number_format($row['bonus_pendiente'], 2) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="flex items-center gap-1 text-gray-500"><i class="fas fa-star text-green-400"></i> Bono pagado</span>
                    <span class="font-semibold text-green-600">S/ {{ number_format($row['bonus_pagado'], 2) }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Resumen mensual (para pago mensual) --}}
    @if($resumenMensualPorMes->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6" x-data="{ mesAbierto: '{{ $resumenMensualPorMes->keys()->first() }}' }">
        <div class="bg-linear-to-r from-indigo-900 to-indigo-700 px-6 py-4 flex items-center gap-3">
            <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-calendar-alt text-white text-xl"></i></div>
            <div>
                <h2 class="text-base font-bold text-white">Resumen Mensual</h2>
                <p class="text-indigo-200 text-xs">Totales por vendedor y mes, para liquidar el pago mensual</p>
            </div>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($resumenMensualPorMes as $mes => $filas)
                @php
                    $mesLabel = \Carbon\Carbon::createFromFormat('Y-m', $mes)->locale('es')->translatedFormat('F Y');
                    $mesLabel = ucfirst($mesLabel);
                    $totalPendienteMes = $filas->sum(fn($f) => $f['comision_pendiente'] + $f['bonus_pendiente']);
                    $totalPagadoMes    = $filas->sum(fn($f) => $f['comision_pagado']    + $f['bonus_pagado']);
                @endphp
                <div>
                    <button type="button" @click="mesAbierto = (mesAbierto === '{{ $mes }}' ? null : '{{ $mes }}')"
                            class="w-full px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition text-left">
                        <span class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs text-gray-400 transition-transform" :class="mesAbierto === '{{ $mes }}' ? 'rotate-90' : ''"></i>
                            {{ $mesLabel }}
                        </span>
                        <span class="flex items-center gap-3 text-xs">
                            <span class="text-amber-600 font-semibold">Pendiente: S/ {{ number_format($totalPendienteMes, 2) }}</span>
                            <span class="text-green-600 font-semibold">Pagado: S/ {{ number_format($totalPagadoMes, 2) }}</span>
                        </span>
                    </button>
                    <div x-show="mesAbierto === '{{ $mes }}'" x-cloak class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase">Vendedor</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase">Comisión Pend.</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase">Comisión Pag.</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase">Bono Pend.</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase">Bono Pag.</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase">Total mes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($filas as $fila)
                                @php $totalFila = $fila['comision_pendiente'] + $fila['comision_pagado'] + $fila['bonus_pendiente'] + $fila['bonus_pagado']; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-700">{{ $fila['vendedor_nombre'] }}</td>
                                    <td class="px-4 py-2 text-right text-amber-600">S/ {{ number_format($fila['comision_pendiente'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-green-600">S/ {{ number_format($fila['comision_pagado'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-amber-600">S/ {{ number_format($fila['bonus_pendiente'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-green-600">S/ {{ number_format($fila['bonus_pagado'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-gray-800">S/ {{ number_format($totalFila, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tabs comisiones / bonos --}}
    <div class="flex gap-1 mb-4 bg-white border border-gray-200 rounded-xl p-1 w-fit shadow-sm">
        <button @click="tab='comisiones'"
                :class="tab==='comisiones' ? 'bg-blue-700 text-white shadow' : 'text-gray-500 hover:text-blue-700'"
                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
            <i class="fas fa-percentage"></i> Comisiones
            <span class="bg-white/20 text-xs px-2 py-0.5 rounded-full font-mono">{{ $comisiones->total() }}</span>
        </button>
        <button @click="tab='bonos'"
                :class="tab==='bonos' ? 'bg-amber-500 text-white shadow' : 'text-gray-500 hover:text-amber-600'"
                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
            <i class="fas fa-star"></i> Bonos
            <span class="bg-white/20 text-xs px-2 py-0.5 rounded-full font-mono">{{ $bonos->total() }}</span>
        </button>
    </div>

    {{-- ═══════════ COMISIONES ═══════════ --}}
    <div x-show="tab==='comisiones'" x-cloak>
        <form action="{{ route('comisiones.marcar-pagado') }}" method="POST">
            @csrf
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                @if($comisiones->isEmpty())
                    <div class="py-12 text-center text-gray-400">
                        <i class="fas fa-percentage text-3xl mb-2 block opacity-30"></i>
                        <p class="text-sm">No hay comisiones con estos filtros.</p>
                    </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 w-10">
                                    <input type="checkbox" @change="toggleTodos('comision')" class="w-4 h-4 accent-blue-600 cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vendedor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Venta / Producto</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Regla</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Base</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Comisión</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($comisiones as $com)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    @if($com->estado === 'pendiente')
                                        <input type="checkbox" name="ids[]" value="{{ $com->id }}"
                                               @change="checkComision($event)"
                                               class="w-4 h-4 accent-blue-600 cursor-pointer">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($com->vendedor?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <span class="text-gray-700 text-xs font-medium">{{ $com->vendedor?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-xs font-mono text-gray-600">{{ $com->detalleVenta?->venta?->codigo ?? '—' }}</p>
                                    <p class="text-[10px] text-gray-400">{{ $com->detalleVenta?->producto?->nombre ?? '—' }}</p>
                                    <p class="text-[10px] text-gray-400">{{ $com->detalleVenta?->venta?->fecha ? \Carbon\Carbon::parse($com->detalleVenta->venta->fecha)->format('d/m/Y') : '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <p class="font-medium text-gray-700">{{ $com->regla?->nombre ?? 'Sin regla' }}</p>
                                    <p class="text-gray-400">{{ $com->regla?->tipo_calculo_label ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    @if($com->tipo_calculo === 'porcentaje_margen')
                                        @if($com->margen_calculado !== null)
                                            <span class="inline-flex flex-col">
                                                <span class="text-emerald-700 font-semibold">Gan. real: S/ {{ number_format($com->margen_calculado, 2) }}</span>
                                                <span class="text-gray-400">{{ number_format($com->valor_configurado, 2) }}% aplicado</span>
                                            </span>
                                        @else
                                            <span class="text-gray-400">% margen (sin costo)</span>
                                        @endif
                                    @elseif($com->tipo_calculo === 'porcentaje')
                                        {{ number_format($com->valor_configurado, 2) }}% sobre venta
                                    @else
                                        S/ {{ number_format($com->valor_configurado, 2) }} / u.
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-gray-800">S/ {{ number_format($com->monto_comision, 2) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $com->estado === 'pagado' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $com->estado === 'pagado' ? 'Pagado' : 'Pendiente' }}
                                    </span>
                                    @if($com->estado === 'pagado' && $com->fecha_pago)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($com->fecha_pago)->format('d/m/Y') }}</p>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">{{ $comisiones->links() }}</div>
                @endif
            </div>

            {{-- Acción pagar comisiones --}}
            <div x-show="selComision > 0" x-cloak
                 class="fixed bottom-6 right-6 bg-white border border-gray-200 shadow-2xl rounded-2xl px-5 py-4 flex items-center gap-4 z-40">
                <div class="text-sm text-gray-700">
                    <span class="font-bold text-blue-700" x-text="selComision"></span> comisiones seleccionadas
                </div>
                <button type="submit"
                        class="px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition">
                    <i class="fas fa-money-bill-wave"></i> Marcar como pagadas
                </button>
            </div>
        </form>
    </div>

    {{-- ═══════════ BONOS ═══════════ --}}
    <div x-show="tab==='bonos'" x-cloak>
        <form action="{{ route('comisiones.marcar-bonus-pagado') }}" method="POST">
            @csrf
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                @if($bonos->isEmpty())
                    <div class="py-12 text-center text-gray-400">
                        <i class="fas fa-star text-3xl mb-2 block opacity-30"></i>
                        <p class="text-sm">No hay bonos con estos filtros.</p>
                    </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 w-10">
                                    <input type="checkbox" @change="toggleTodos('bonus')" class="w-4 h-4 accent-amber-500 cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vendedor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Regla de bono</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Referencia</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Bono</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($bonos as $bonus)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    @if($bonus->estado === 'pendiente')
                                        <input type="checkbox" name="ids[]" value="{{ $bonus->id }}"
                                               @change="checkBonus($event)"
                                               class="w-4 h-4 accent-amber-500 cursor-pointer">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($bonus->vendedor?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <span class="text-gray-700 text-xs font-medium">{{ $bonus->vendedor?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <p class="font-medium text-gray-700">{{ $bonus->regla?->nombre ?? 'Sin regla' }}</p>
                                    <p class="text-gray-400">{{ $bonus->regla?->tipo_calculo_label ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if($bonus->tipo_origen === 'fijo')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                            <i class="fas fa-bolt text-[8px]"></i> Fijo
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700">
                                            <i class="fas fa-trophy text-[8px]"></i> Meta
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    @if($bonus->tipo_origen === 'fijo')
                                        <p class="font-mono text-gray-600">{{ $bonus->detalleVenta?->venta?->codigo ?? '—' }}</p>
                                        <p class="text-gray-400">{{ $bonus->detalleVenta?->producto?->nombre ?? '—' }}</p>
                                    @else
                                        <p class="text-purple-700 font-medium">{{ $bonus->unidades_periodo }} unidades</p>
                                        @if($bonus->periodo_inicio)
                                            <p class="text-gray-400">{{ \Carbon\Carbon::parse($bonus->periodo_inicio)->format('d/m') }} – {{ \Carbon\Carbon::parse($bonus->periodo_fin)->format('d/m/Y') }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-gray-800">S/ {{ number_format($bonus->monto_bonus, 2) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $bonus->estado_css }}">
                                        {{ $bonus->estado_label }}
                                    </span>
                                    @if($bonus->estado === 'pagado' && $bonus->fecha_pago)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($bonus->fecha_pago)->format('d/m/Y') }}</p>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100">{{ $bonos->links() }}</div>
                @endif
            </div>

            {{-- Acción pagar bonos --}}
            <div x-show="selBonus > 0" x-cloak
                 class="fixed bottom-6 right-6 bg-white border border-gray-200 shadow-2xl rounded-2xl px-5 py-4 flex items-center gap-4 z-40">
                <div class="text-sm text-gray-700">
                    <span class="font-bold text-amber-500" x-text="selBonus"></span> bonos seleccionados
                </div>
                <button type="submit"
                        class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition">
                    <i class="fas fa-star"></i> Marcar como pagados
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function reporteApp() {
    return {
        tab: 'comisiones',
        selComision: 0,
        selBonus: 0,

        checkComision(e) { e.target.checked ? this.selComision++ : this.selComision--; },
        checkBonus(e)    { e.target.checked ? this.selBonus++    : this.selBonus--;    },

        toggleTodos(tipo) {
            const selector = tipo === 'comision'
                ? 'input[name="ids[]"][class*="accent-blue"]'
                : 'input[name="ids[]"][class*="accent-amber"]';
            const boxes = document.querySelectorAll(selector);
            const allChecked = [...boxes].every(b => b.checked);
            boxes.forEach(b => { b.checked = !allChecked; });
            if (tipo === 'comision') this.selComision = allChecked ? 0 : boxes.length;
            else this.selBonus = allChecked ? 0 : boxes.length;
        },
    };
}
</script>
</body>
</html>
