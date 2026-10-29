<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Movimiento #{{ $movimiento->id }} - {{ config('app.name') }}</title>
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
            <h1 class="text-2xl font-bold text-gray-900">Detalle de Movimiento</h1>
            <p class="text-sm text-gray-500 mt-0.5">Movimiento #{{ $movimiento->id }}</p>
        </div>
        <a href="{{ route('inventario.movimientos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-300 transition-colors shadow-sm">
            <i class="fas fa-arrow-left"></i> Volver al historial
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Información principal --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Tipo y estado --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Información del Movimiento</h2>

                <div class="flex items-center gap-4 mb-6">
                    @php
                        $tipoConfig = [
                            'ingreso'       => ['bg-green-100 text-green-800',  'fas fa-arrow-down',       'Ingreso'],
                            'salida'        => ['bg-red-100 text-red-800',      'fas fa-arrow-up',         'Salida'],
                            'transferencia' => ['bg-blue-100 text-blue-800',    'fas fa-exchange-alt',     'Transferencia'],
                            'ajuste'        => ['bg-yellow-100 text-yellow-800','fas fa-sliders-h',        'Ajuste'],
                            'devolucion'    => ['bg-purple-100 text-purple-800','fas fa-undo',             'Devolución'],
                            'merma'         => ['bg-orange-100 text-orange-800','fas fa-exclamation-triangle','Merma'],
                        ];
                        [$badgeClass, $icon, $label] = $tipoConfig[$movimiento->tipo_movimiento] ?? ['bg-gray-100 text-gray-800', 'fas fa-circle', ucfirst($movimiento->tipo_movimiento)];
                    @endphp
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold {{ $badgeClass }}">
                        <i class="{{ $icon }}"></i> {{ $label }}
                    </span>
                    @if($movimiento->numero_guia)
                        <span class="text-sm text-gray-500">Guía: <span class="font-medium text-gray-700">{{ $movimiento->numero_guia }}</span></span>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Motivo</p>
                        <p class="text-sm font-medium text-gray-800">{{ $movimiento->motivo }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Fecha y Hora</p>
                        <p class="text-sm font-medium text-gray-800">{{ $movimiento->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($movimiento->observaciones)
                    <div class="sm:col-span-2">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Observaciones</p>
                        <p class="text-sm text-gray-700">{{ $movimiento->observaciones }}</p>
                    </div>
                    @endif
                    @if($movimiento->documento_referencia)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Documento Referencia</p>
                        <p class="text-sm font-medium text-gray-800">{{ $movimiento->documento_referencia }}</p>
                    </div>
                    @endif
                    @if($movimiento->numero_factura)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Número de Factura</p>
                        <p class="text-sm font-medium text-gray-800">{{ $movimiento->numero_factura }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Producto --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Producto</h2>
                @if($movimiento->producto)
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                        <i class="fas fa-box text-blue-600"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900">{{ \App\Support\EtiquetaProducto::nombre($movimiento->producto) }}</p>
                        <p class="text-[11px] font-mono text-gray-400">{{ \App\Support\EtiquetaProducto::codigos($movimiento->producto, $movimiento->variante) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Código: {{ $movimiento->producto->codigo }}</p>
                    </div>
                </div>
                @else
                    <p class="text-sm text-gray-400 italic">Producto no disponible</p>
                @endif
            </div>

            {{-- Almacenes --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Almacén(es)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                            <i class="fas fa-warehouse text-indigo-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Almacén Origen</p>
                            <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $movimiento->almacen->nombre ?? '—' }}</p>
                            @if($movimiento->almacen)
                                <p class="text-xs text-gray-500">{{ $movimiento->almacen->codigo }}</p>
                            @endif
                        </div>
                    </div>

                    @if($movimiento->almacenDestino)
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                            <i class="fas fa-warehouse text-teal-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Almacén Destino</p>
                            <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $movimiento->almacenDestino->nombre }}</p>
                            <p class="text-xs text-gray-500">{{ $movimiento->almacenDestino->codigo }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Panel lateral: cantidades y usuario --}}
        <div class="space-y-6">

            {{-- Stock --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Stock</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Cantidad</span>
                        <span class="text-lg font-bold text-gray-900">{{ $movimiento->cantidad }}</span>
                    </div>
                    @if(!is_null($movimiento->stock_anterior))
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Stock anterior</span>
                        <span class="text-sm font-medium text-gray-700">{{ $movimiento->stock_anterior }}</span>
                    </div>
                    @endif
                    @if(!is_null($movimiento->stock_nuevo))
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Stock resultante</span>
                        <span class="text-sm font-bold text-blue-700">{{ $movimiento->stock_nuevo }}</span>
                    </div>
                    @endif
                    @if(!is_null($movimiento->stock_anterior) && !is_null($movimiento->stock_nuevo))
                    <hr class="border-gray-100">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Diferencia</span>
                        @php $diff = $movimiento->stock_nuevo - $movimiento->stock_anterior; @endphp
                        <span class="text-sm font-semibold {{ $diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $diff >= 0 ? '+' : '' }}{{ $diff }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Usuario --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Registrado por</h2>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center shrink-0">
                        <i class="fas fa-user text-gray-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $movimiento->usuario->name ?? 'Usuario desconocido' }}</p>
                        @if($movimiento->usuario)
                            <p class="text-xs text-gray-500">{{ $movimiento->usuario->email }}</p>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4">{{ $movimiento->created_at->diffForHumans() }}</p>
            </div>

        </div>
    </div>

</div>

</body>
</html>
