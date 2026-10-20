<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Mis Pedidos" 
            subtitle="Pedidos asignados a su cuenta de proveedor" 
        />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if($pedidos->isEmpty())
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-inbox text-5xl text-gray-300 mb-4 block"></i>
                <p class="text-gray-500 text-lg">No tiene pedidos asignados</p>
                <p class="text-gray-400 text-sm mt-2">Los pedidos realizados por la empresa aparecerán aquí.</p>
            </div>
        @else
            {{-- Resumen --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-500 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pendientes</p>
                            <p class="text-3xl font-bold text-gray-800">{{ $pedidos->where('estado', 'pendiente')->count() }}</p>
                        </div>
                        <div class="bg-yellow-100 rounded-full p-3">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Aprobados</p>
                            <p class="text-3xl font-bold text-gray-800">{{ $pedidos->where('estado', 'aprobado')->count() }}</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-3">
                            <i class="fas fa-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Recibidos</p>
                            <p class="text-3xl font-bold text-gray-800">{{ $pedidos->where('estado', 'recibido')->count() }}</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-3">
                            <i class="fas fa-box-open text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de Pedidos como Cards --}}
            <div class="space-y-4">
                @foreach($pedidos as $pedido)
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-blue-900">{{ $pedido->codigo }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Solicitado el {{ $pedido->fecha->format('d/m/Y') }}
                                        @if($pedido->fecha_esperada)
                                            · Entrega: {{ $pedido->fecha_esperada->format('d/m/Y') }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">Por: {{ $pedido->usuario->name ?? '-' }}</p>
                                </div>
                                @php
                                    $ep = match($pedido->estado) {
                                        'pendiente' => 'bg-yellow-100 text-yellow-800',
                                        'aprobado' => 'bg-blue-100 text-blue-800',
                                        'recibido' => 'bg-green-100 text-green-800',
                                        'cancelado' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $ep }}">
                                    {{ ucfirst($pedido->estado) }}
                                </span>
                            </div>

                            <div class="border-t border-gray-100 pt-3">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">Producto</th>
                                            <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Cantidad</th>
                                            <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2">Precio Ref.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pedido->detalles as $detalle)
                                            <tr>
                                                <td class="py-1.5 text-sm text-gray-700">
                                                    {{ $detalle->producto->nombre ?? '-' }}
                                                    <span class="text-xs text-gray-400">({{ $detalle->producto->codigo ?? '' }})</span>
                                                </td>
                                                <td class="py-1.5 text-sm text-center font-semibold">{{ $detalle->cantidad }}</td>
                                                <td class="py-1.5 text-sm text-right">
                                                    @if($detalle->precio_referencial)
                                                        S/ {{ number_format($detalle->precio_referencial, 2) }}
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($pedido->observaciones)
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500"><i class="fas fa-sticky-note mr-1 text-yellow-500"></i>{{ $pedido->observaciones }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>