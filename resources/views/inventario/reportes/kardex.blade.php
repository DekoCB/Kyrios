<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex Valorizado · Inventario</title>
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
                <span>Inventario</span>
                <span>/</span>
                <span class="text-gray-700 font-medium">Kardex Valorizado</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-book-open text-blue-500"></i>
                Kardex Valorizado
            </h1>
            <p class="text-sm text-gray-500">Historial de movimientos con valorización por costo promedio</p>
        </div>
        @if($productoSel && $movimientos->isNotEmpty())
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition">
                <i class="fas fa-file-csv"></i> Exportar Excel
            </a>
        @endif
    </div>

    <div class="p-6 space-y-5">

        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Producto</label>
                    <select name="producto_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Seleccionar producto —</option>
                        @foreach($productosList as $prod)
                            <option value="{{ $prod->id }}" {{ $productoId == $prod->id ? 'selected' : '' }}>{{ $prod->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Desde</label>
                    <input type="date" name="desde" value="{{ $desde }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Tipo</label>
                    <select name="tipo_movimiento" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        @foreach($tiposMovimiento as $tipo)
                            <option value="{{ $tipo }}" {{ $tipoMov == $tipo ? 'selected' : '' }}>{{ ucfirst($tipo) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <button type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-search"></i> Consultar
                </button>
                <a href="{{ route('inventario.reportes.kardex') }}"
                   class="flex items-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </div>
        </form>

        @if(!$productoId)
            {{-- Empty prompt --}}
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-hand-pointer text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 font-medium">Selecciona un producto para ver su kardex</p>
                <p class="text-sm text-gray-400 mt-1">Elige el producto y el rango de fechas en el formulario</p>
            </div>

        @elseif($movimientos->isEmpty())
            {{-- No movements --}}
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-inbox text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 font-medium">Sin movimientos en el período seleccionado</p>
                <p class="text-sm text-gray-400 mt-1">Intenta ampliar el rango de fechas o cambiar el tipo de movimiento</p>
            </div>

        @else
            {{-- Product Info --}}
            <div class="bg-white rounded-xl shadow-sm p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-bold text-gray-800">{{ $productoSel->nombre }}</h2>
                    <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
                        <span><i class="fas fa-tag mr-1"></i>{{ $productoSel->categoria?->nombre ?? '—' }}</span>
                        <span><i class="fas fa-trademark mr-1"></i>{{ $productoSel->marca?->nombre ?? '—' }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                            bg-blue-100 text-blue-700">
                            Cantidad
                        </span>
                    </div>
                </div>
                <div class="flex gap-6 text-center shrink-0">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Costo Unit.</p>
                        <p class="text-lg font-bold text-orange-600">
                            {{ $resumenKardex['costo_unit'] > 0 ? 'S/ '.number_format($resumenKardex['costo_unit'], 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">P. Venta</p>
                        <p class="text-lg font-bold text-green-600">
                            {{ $resumenKardex['precio_venta'] > 0 ? 'S/ '.number_format($resumenKardex['precio_venta'], 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Período</p>
                        <p class="text-sm font-semibold text-gray-700">{{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
                    </div>
                </div>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Ingresos</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($resumenKardex['total_ingresos_qty']) }}</p>
                    <p class="text-xs text-green-600 mt-1">S/ {{ number_format($resumenKardex['total_ingresos_val'], 2) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Salidas</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($resumenKardex['total_salidas_qty']) }}</p>
                    <p class="text-xs text-red-600 mt-1">S/ {{ number_format($resumenKardex['total_salidas_val'], 2) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Final (Qty)</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($resumenKardex['saldo_final_qty']) }}</p>
                    <p class="text-xs text-blue-500 mt-1">unidades en stock</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Valorizado</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">S/ {{ number_format($resumenKardex['saldo_final_val'], 2) }}</p>
                    <p class="text-xs text-purple-500 mt-1">capital en stock</p>
                </div>
            </div>

            {{-- Movement Table --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Fecha</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Almacén</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Doc. Ref.</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Motivo</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Costo U.</th>
                                {{-- Ingresos --}}
                                <th colspan="2" class="text-center px-4 py-3 text-xs font-semibold text-green-600 uppercase tracking-wider bg-green-50">Ingreso</th>
                                {{-- Salidas --}}
                                <th colspan="2" class="text-center px-4 py-3 text-xs font-semibold text-red-600 uppercase tracking-wider bg-red-50">Salida</th>
                                {{-- Saldo --}}
                                <th colspan="2" class="text-center px-4 py-3 text-xs font-semibold text-blue-600 uppercase tracking-wider bg-blue-50">Saldo</th>
                            </tr>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th colspan="6"></th>
                                <th class="text-right px-4 py-2 text-xs text-green-600 bg-green-50">Qty</th>
                                <th class="text-right px-4 py-2 text-xs text-green-600 bg-green-50">Valor</th>
                                <th class="text-right px-4 py-2 text-xs text-red-600 bg-red-50">Qty</th>
                                <th class="text-right px-4 py-2 text-xs text-red-600 bg-red-50">Valor</th>
                                <th class="text-right px-4 py-2 text-xs text-blue-600 bg-blue-50">Qty</th>
                                <th class="text-right px-4 py-2 text-xs text-blue-600 bg-blue-50">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($movimientos as $m)
                                @php
                                    $esIngreso = $m['ingreso_qty'] > 0;
                                    $esSalida  = $m['salida_qty'] > 0;
                                    $tipoBadgeClass = match($m['tipo']) {
                                        'ingreso'       => 'bg-green-100 text-green-700',
                                        'devolucion'    => 'bg-teal-100 text-teal-700',
                                        'salida'        => 'bg-red-100 text-red-700',
                                        'merma'         => 'bg-rose-100 text-rose-700',
                                        'ajuste'        => 'bg-yellow-100 text-yellow-700',
                                        'transferencia' => 'bg-purple-100 text-purple-700',
                                        default         => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap font-mono text-xs">{{ $m['fecha'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tipoBadgeClass }}">
                                            {{ ucfirst($m['tipo']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $m['almacen'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $m['doc_ref'] }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs max-w-[180px] truncate" title="{{ $m['motivo'] }}">{{ $m['motivo'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 font-mono text-xs">
                                        {{ $m['costo_unit'] > 0 ? 'S/ '.number_format($m['costo_unit'], 2) : '—' }}
                                    </td>
                                    {{-- Ingreso --}}
                                    <td class="px-4 py-3 text-right bg-green-50/40 font-semibold text-green-700">
                                        {{ $esIngreso ? number_format($m['ingreso_qty']) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right bg-green-50/40 text-green-700 font-mono text-xs">
                                        {{ $esIngreso ? 'S/ '.number_format($m['ingreso_val'], 2) : '—' }}
                                    </td>
                                    {{-- Salida --}}
                                    <td class="px-4 py-3 text-right bg-red-50/40 font-semibold text-red-700">
                                        {{ $esSalida ? number_format($m['salida_qty']) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right bg-red-50/40 text-red-700 font-mono text-xs">
                                        {{ $esSalida ? 'S/ '.number_format($m['salida_val'], 2) : '—' }}
                                    </td>
                                    {{-- Saldo --}}
                                    <td class="px-4 py-3 text-right bg-blue-50/40 font-bold text-blue-700">
                                        {{ number_format($m['saldo_qty']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right bg-blue-50/40 text-blue-700 font-mono text-xs font-semibold">
                                        S/ {{ number_format($m['saldo_val'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm font-bold text-gray-700">
                                    TOTALES — {{ $movimientos->count() }} movimientos
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-green-700 bg-green-50/40">{{ number_format($resumenKardex['total_ingresos_qty']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-green-700 bg-green-50/40 font-mono text-xs">S/ {{ number_format($resumenKardex['total_ingresos_val'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-700 bg-red-50/40">{{ number_format($resumenKardex['total_salidas_qty']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-700 bg-red-50/40 font-mono text-xs">S/ {{ number_format($resumenKardex['total_salidas_val'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-700 bg-blue-50/40">{{ number_format($resumenKardex['saldo_final_qty']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-700 bg-blue-50/40 font-mono text-xs">S/ {{ number_format($resumenKardex['saldo_final_val'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <p class="text-xs text-gray-400 text-center">
                * Valorización calculada con costo promedio del producto al momento de la consulta
            </p>
        @endif

    </div>
</div>
</body>
</html>
