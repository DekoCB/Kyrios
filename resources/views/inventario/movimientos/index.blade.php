<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Movimientos de Inventario</h1>
            <p class="text-sm text-gray-500 mt-0.5">Historial completo de entradas, salidas y ajustes de stock</p>
        </div>
        <a href="{{ route('inventario.movimientos.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white text-sm font-semibold rounded-xl hover:bg-blue-800 transition-colors shadow-sm">
            <i class="fas fa-plus"></i> Nuevo Movimiento
        </a>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <i class="fas fa-exchange-alt text-blue-700 text-lg"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_movimientos'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                <i class="fas fa-calendar-day text-purple-700 text-lg"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Hoy</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['movimientos_hoy'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <i class="fas fa-arrow-circle-down text-green-600 text-lg"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Ingresos hoy</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['ingresos_hoy'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                <i class="fas fa-arrow-circle-up text-red-500 text-lg"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Salidas hoy</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['salidas_hoy'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                <i class="fas fa-random text-purple-600 text-lg"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Transferencias hoy</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['transferencias_hoy'] }}</p>
            </div>
        </div>
    </div>

    {{-- Panel tiendas --}}
    <div class="mb-6">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">
            <i class="fas fa-store mr-1"></i> Tiendas / Almacenes activos
        </h2>
        <div class="flex gap-3 overflow-x-auto pb-2">
            @foreach($almacenes as $alm)
            @php $movHoy = $movHoyPorAlmacen[$alm->id] ?? 0; @endphp
            <a href="{{ route('inventario.almacenes.show', $alm) }}"
               class="shrink-0 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 min-w-45 hover:border-blue-300 hover:shadow-md transition-all group">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center
                        {{ $alm->tipo === 'principal' ? 'bg-purple-100' : 'bg-blue-100' }}">
                        <i class="fas {{ $alm->tipo === 'principal' ? 'fa-star text-purple-600' : 'fa-store text-blue-600' }} text-sm"></i>
                    </div>
                    <span class="text-xs font-medium px-1.5 py-0.5 rounded
                        {{ $alm->tipo === 'principal' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                        {{ ucfirst($alm->tipo) }}
                    </span>
                </div>
                <p class="text-sm font-semibold text-gray-800 group-hover:text-blue-700 transition-colors leading-tight">{{ $alm->nombre }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    @if($movHoy > 0)
                        <span class="text-green-600 font-medium">{{ $movHoy }} mov. hoy</span>
                    @else
                        Sin movimientos hoy
                    @endif
                </p>
                <p class="text-xs text-blue-500 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    Ver stock <i class="fas fa-arrow-right text-[10px]"></i>
                </p>
            </a>
            @endforeach
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
        <form method="GET" action="{{ route('inventario.movimientos.index') }}"
              class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tipo</label>
                <select name="tipo_movimiento"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach([
                        'ingreso'       => 'Ingreso',
                        'salida'        => 'Salida',
                        'ajuste'        => 'Ajuste',
                        'transferencia' => 'Transferencia',
                        'devolucion'    => 'Devolución',
                        'merma'         => 'Merma',
                    ] as $val => $label)
                    <option value="{{ $val }}" {{ request('tipo_movimiento') == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Producto</label>
                <select name="producto_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos los productos</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" {{ request('producto_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->codigo }} – {{ \App\Support\EtiquetaProducto::nombre($p) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Almacén</label>
                <select name="almacen_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach($almacenes as $a)
                        <option value="{{ $a->id }}" {{ request('almacen_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-36">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-36">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2 bg-blue-900 text-white text-sm font-medium rounded-lg hover:bg-blue-800 transition-colors flex items-center gap-2">
                    <i class="fas fa-filter text-xs"></i> Filtrar
                </button>
                <a href="{{ route('inventario.movimientos.index') }}"
                   class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    {{-- Tabla de movimientos --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">
                Historial de movimientos
                @if($movimientos->total() > 0)
                    <span class="ml-2 text-xs text-gray-400 font-normal">{{ $movimientos->total() }} registros</span>
                @endif
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Almacén</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Cantidad</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Usuario</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Motivo</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movimientos as $mov)
                    @php
                        $esIngreso  = in_array($mov->tipo_movimiento, ['ingreso', 'devolucion']);
                        $esSalida   = in_array($mov->tipo_movimiento, ['salida', 'merma']);
                        $esTransfer = $mov->tipo_movimiento === 'transferencia';
                        $esAjuste   = $mov->tipo_movimiento === 'ajuste';
                        $badgeClass = match($mov->tipo_movimiento) {
                            'ingreso'       => 'bg-green-50 text-green-700 border-green-200',
                            'salida'        => 'bg-red-50 text-red-700 border-red-200',
                            'ajuste'        => 'bg-blue-50 text-blue-700 border-blue-200',
                            'transferencia' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'devolucion'    => 'bg-orange-50 text-orange-700 border-orange-200',
                            'merma'         => 'bg-gray-100 text-gray-600 border-gray-200',
                            default         => 'bg-gray-100 text-gray-600 border-gray-200',
                        };
                    @endphp
                    <tr class="hover:bg-blue-50/20 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <p class="text-sm text-gray-800">{{ $mov->created_at->format('d/m/Y') }}</p>
                            <p class="text-xs text-gray-400">{{ $mov->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                <i class="fas {{ $mov->icono_tipo_movimiento }} text-[10px]"></i>
                                {{ $mov->tipo_movimiento_nombre }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-gray-900">{{ \App\Support\EtiquetaProducto::nombre($mov->producto) }}</p>
                            <p class="text-[11px] font-mono text-gray-400">{{ \App\Support\EtiquetaProducto::codigos($mov->producto, $mov->variante) }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $mov->producto->codigo }}</p>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="text-sm text-gray-700">{{ $mov->nombre_almacen }}</span>
                            @if($esTransfer && $mov->almacenDestino)
                                <p class="text-xs text-purple-600 mt-0.5">
                                    <i class="fas fa-arrow-right text-[9px]"></i>
                                    {{ $mov->almacenDestino->nombre }}
                                </p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center whitespace-nowrap">
                            <span class="text-sm font-bold
                                {{ $esIngreso ? 'text-green-600' : ($esSalida ? 'text-red-600' : 'text-blue-600') }}">
                                {{ $esIngreso ? '+' : ($esSalida ? '−' : '') }}{{ $mov->cantidad }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center whitespace-nowrap">
                            <span class="text-xs text-gray-400">{{ $mov->stock_anterior }}</span>
                            <i class="fas fa-arrow-right text-gray-300 text-[9px] mx-1"></i>
                            <span class="text-sm font-semibold text-gray-800">{{ $mov->stock_nuevo }}</span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="text-sm text-gray-600">{{ $mov->nombre_usuario }}</span>
                        </td>
                        <td class="px-5 py-3 max-w-50">
                            <span class="text-sm text-gray-600 truncate block" title="{{ $mov->motivo }}">
                                {{ Str::limit($mov->motivo, 45) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('inventario.movimientos.show', $mov) }}"
                               class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-gray-50 text-gray-500 hover:bg-blue-50 hover:text-blue-700 transition-colors border border-gray-200"
                               title="Ver detalle">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <i class="fas fa-exchange-alt text-5xl"></i>
                                <p class="text-lg font-medium">No hay movimientos registrados</p>
                                <p class="text-sm">Ajusta los filtros o registra el primer movimiento</p>
                                <a href="{{ route('inventario.movimientos.create') }}"
                                   class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors">
                                    <i class="fas fa-plus"></i> Nuevo Movimiento
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movimientos->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $movimientos->links() }}
        </div>
        @endif
    </div>

</div>
</body>
</html>
