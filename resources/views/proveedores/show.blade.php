<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Proveedor - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Detalle del Proveedor"
            subtitle="Información completa del proveedor y su historial"
        />

        <div class="max-w-6xl mx-auto">
            {{-- Información del Proveedor --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {{-- Información General --}}
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-blue-900 px-6 py-4">
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-building mr-2"></i>{{ $proveedor->razon_social }}
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">RUC</label>
                                    <p class="text-lg font-mono font-bold text-gray-900">{{ $proveedor->ruc }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                                    <span class="inline-flex items-center px-3 py-1 text-sm font-bold rounded-full {{ $proveedor->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        <i class="fas fa-circle text-xs mr-2"></i>
                                        {{ ucfirst($proveedor->estado) }}
                                    </span>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Nombre Comercial</label>
                                    <p class="text-sm text-gray-900">{{ $proveedor->nombre_comercial ?? '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                                    <p class="text-sm text-gray-900">
                                        @if($proveedor->telefono)
                                            <i class="fas fa-phone mr-1 text-blue-600"></i>{{ $proveedor->telefono }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                                    <p class="text-sm text-gray-900">
                                        @if($proveedor->email)
                                            <i class="fas fa-envelope mr-1 text-blue-600"></i>{{ $proveedor->email }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Persona de Contacto</label>
                                    <p class="text-sm text-gray-900">
                                        @if($proveedor->contacto_nombre)
                                            <i class="fas fa-user mr-1 text-blue-600"></i>{{ $proveedor->contacto_nombre }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Dirección</label>
                                    <p class="text-sm text-gray-900">
                                        @if($proveedor->direccion)
                                            <i class="fas fa-map-marker-alt mr-1 text-blue-600"></i>{{ $proveedor->direccion }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Estadísticas --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4">
                            <h3 class="text-lg font-bold text-white">
                                <i class="fas fa-chart-pie mr-2"></i>Estadísticas
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-blue-900">Total Compras</p>
                                            <p class="text-3xl font-bold text-blue-600">{{ $proveedor->compras->count() }}</p>
                                        </div>
                                        <div class="bg-blue-100 rounded-full p-3">
                                            <i class="fas fa-shopping-cart text-2xl text-blue-600"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-green-900">Pedidos</p>
                                            <p class="text-3xl font-bold text-green-600">{{ $proveedor->pedidos->count() }}</p>
                                        </div>
                                        <div class="bg-green-100 rounded-full p-3">
                                            <i class="fas fa-clipboard-list text-2xl text-green-600"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-900 to-purple-800 px-6 py-4">
                            <h3 class="text-lg font-bold text-white">
                                <i class="fas fa-cogs mr-2"></i>Acciones
                            </h3>
                        </div>
                        <div class="p-4 space-y-2">
                            <a href="{{ route('proveedores.edit', $proveedor) }}"
                               class="flex items-center justify-center w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-edit mr-2"></i>Editar Proveedor
                            </a>
                            <a href="{{ route('proveedores.index') }}"
                               class="flex items-center justify-center w-full px-4 py-2 border-2 border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold rounded-lg transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Volver al Listado
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Historial de Compras --}}
            @if($proveedor->compras->count())
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-history mr-2"></i>Historial de Compras
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Código</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">N° Factura</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($proveedor->compras->take(10) as $compra)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono font-semibold text-blue-900">{{ $compra->codigo }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <i class="fas fa-calendar mr-1 text-gray-400"></i>{{ $compra->fecha->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $compra->numero_factura ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-gray-900">S/ {{ number_format($compra->total, 2) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ ucfirst($compra->estado) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <a href="{{ route('compras.show', $compra) }}"
                                       class="text-blue-600 hover:text-blue-900 font-medium">
                                        <i class="fas fa-eye mr-1"></i>Ver Detalle
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($proveedor->compras->count() > 10)
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        Mostrando las últimas 10 compras de {{ $proveedor->compras->count() }} en total
                    </p>
                </div>
                @endif
            </div>
            @else
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-history mr-2"></i>Historial de Compras
                    </h2>
                </div>
                <div class="p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-lg font-medium text-gray-500">No hay compras registradas</p>
                    <p class="text-sm text-gray-400 mt-2">Este proveedor aún no tiene compras asociadas</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
