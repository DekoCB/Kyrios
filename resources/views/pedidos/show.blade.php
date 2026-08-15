<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido - CORPORACIÓN ADIVON SAC</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Detalle de Pedido" 
            subtitle="Información completa del pedido" 
        />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <div class="max-w-5xl">
            <div class="mb-6 flex justify-between items-center">
                <a href="{{ route('pedidos.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Volver a pedidos
                </a>
                @php
                    $ep = match($pedido->estado) {
                        'pendiente' => 'bg-yellow-100 text-yellow-800',
                        'aprobado' => 'bg-blue-100 text-blue-800',
                        'recibido' => 'bg-green-100 text-green-800',
                        'cancelado' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                @endphp
                <span class="inline-flex px-3 py-1.5 text-sm font-semibold rounded-full {{ $ep }}">
                    {{ ucfirst($pedido->estado) }}
                </span>
            </div>

            {{-- Info y Cambio de Estado --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>Información del Pedido
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Código:</dt><dd class="font-mono font-bold text-blue-900">{{ $pedido->codigo }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Proveedor:</dt>
                            <dd class="font-semibold">
                                <a href="{{ route('proveedores.show', $pedido->proveedor) }}" class="text-blue-600 hover:underline">
                                    {{ $pedido->proveedor->razon_social ?? '-' }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-gray-500">Almacén destino:</dt><dd class="font-semibold">{{ $pedido->almacen->nombre ?? 'No especificado' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fecha:</dt><dd>{{ $pedido->fecha->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fecha Esperada:</dt><dd>{{ $pedido->fecha_esperada ? $pedido->fecha_esperada->format('d/m/Y') : 'No especificada' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Creado por:</dt><dd>{{ $pedido->usuario->name ?? '-' }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-exchange-alt mr-2 text-purple-600"></i>Cambiar Estado
                    </h3>

                    @if($pedido->estado !== 'cancelado' && $pedido->estado !== 'recibido')
                        <div class="space-y-3">
                            @if($pedido->estado === 'pendiente')
                                <form action="{{ route('pedidos.cambiar-estado', $pedido) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="aprobado">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors"
                                            onclick="return confirm('¿Aprobar este pedido?')">
                                        <i class="fas fa-check mr-2"></i>Aprobar Pedido
                                    </button>
                                </form>
                            @endif

                            @if($pedido->estado === 'aprobado')
                                <a href="{{ route('compras.create', ['pedido_id' => $pedido->id]) }}"
                                   class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors">
                                    <i class="fas fa-box-open mr-2"></i>Recibir Mercadería
                                </a>
                                <p class="text-xs text-gray-400 text-center -mt-1">
                                    Te lleva a registrar la Compra real (factura, IMEIs, precios finales). El pedido pasará a "Recibido" automáticamente al guardarla.
                                </p>
                            @endif

                            <form action="{{ route('pedidos.cambiar-estado', $pedido) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="cancelado">
                                <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-700 font-semibold py-2.5 px-4 rounded-lg transition-colors border border-red-200"
                                        onclick="return confirm('¿Cancelar este pedido? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-times mr-2"></i>Cancelar Pedido
                                </button>
                            </form>
                        </div>
                    @elseif($pedido->estado === 'recibido')
                        <div class="text-center py-6">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-2"></i>
                            <p class="text-sm text-gray-500">Mercadería recibida.</p>
                            @if($pedido->compra)
                                <a href="{{ route('compras.show', $pedido->compra) }}" class="inline-block mt-2 text-sm text-blue-600 hover:underline">
                                    <i class="fas fa-file-invoice mr-1"></i>Ver Compra {{ $pedido->compra->numero_factura }}
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-6 text-gray-400">
                            <i class="fas fa-lock text-3xl mb-2"></i>
                            <p class="text-sm">Pedido {{ $pedido->estado }}.<br>No se puede cambiar.</p>
                        </div>
                    @endif

                    {{-- Timeline de estados --}}
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <p class="text-xs font-semibold text-gray-400 mb-3 uppercase tracking-wider">Flujo de Estados</p>
                        <div class="flex items-center justify-between text-xs">
                            @php
                                $estados = ['pendiente', 'aprobado', 'recibido'];
                                $estadoActualIdx = array_search($pedido->estado, $estados);
                                if ($pedido->estado === 'cancelado') $estadoActualIdx = -1;
                            @endphp
                            @foreach($estados as $idx => $estado)
                                <div class="flex flex-col items-center">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm
                                        {{ $idx <= $estadoActualIdx ? 'bg-green-500' : 'bg-gray-300' }}">
                                        @if($idx <= $estadoActualIdx)
                                            <i class="fas fa-check"></i>
                                        @else
                                            {{ $idx + 1 }}
                                        @endif
                                    </div>
                                    <span class="mt-1.5 font-medium {{ $idx <= $estadoActualIdx ? 'text-green-600' : 'text-gray-400' }}">
                                        {{ ucfirst($estado) }}
                                    </span>
                                </div>
                                @if(!$loop->last)
                                    <div class="flex-1 h-1 rounded {{ $idx < $estadoActualIdx ? 'bg-green-500' : 'bg-gray-200' }} mx-2 mb-5"></div>
                                @endif
                            @endforeach
                        </div>
                        @if($pedido->estado === 'cancelado')
                            <p class="text-center text-red-500 text-xs mt-3 font-bold">
                                <i class="fas fa-ban mr-1"></i>Pedido cancelado
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detalle de Productos --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-lg font-bold text-white">
                        <i class="fas fa-boxes mr-2"></i>Productos Solicitados
                    </h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio Ref.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pedido->detalles as $i => $detalle)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="font-medium text-gray-900">{{ $detalle->producto->nombre ?? '-' }}</span>
                                    <span class="text-gray-400 text-xs ml-1">({{ $detalle->producto->codigo ?? '' }})</span>
                                    @if($detalle->variante)
                                        <span class="block text-xs text-indigo-600 mt-0.5">
                                            <i class="fas fa-tag mr-1"></i>{{ $detalle->variante->nombre_completo }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-center font-bold">{{ $detalle->cantidad }}</td>
                                <td class="px-6 py-4 text-sm text-right">
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
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-yellow-400">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Observaciones
                    </h3>
                    <p class="text-gray-600 text-sm">{{ $pedido->observaciones }}</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>