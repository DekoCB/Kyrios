<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Logística - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Panel de Logística" subtitle="Fábricas, proveedores, compras e inventario, {{ auth()->user()->name }}" />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Productos activos</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $productos_activos }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-boxes text-blue-900 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Proveedores</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $proveedores_activos }}</p>
                    </div>
                    <div class="bg-indigo-100 rounded-full p-3">
                        <i class="fas fa-truck text-indigo-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Compras del mes</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $compras_mes }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-file-invoice text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Movimientos hoy</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $movimientos_hoy }}</p>
                    </div>
                    <div class="bg-amber-100 rounded-full p-3">
                        <i class="fas fa-exchange-alt text-amber-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-teal-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Almacenes activos</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $almacenes_activos }}</p>
                    </div>
                    <div class="bg-teal-100 rounded-full p-3">
                        <i class="fas fa-warehouse text-teal-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Accesos rápidos</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <a href="{{ route('proveedores.index') }}" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50"><i class="fas fa-truck mr-2"></i>Proveedores</a>
                <a href="{{ route('compras.create') }}" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50"><i class="fas fa-plus mr-2"></i>Nueva compra</a>
                <a href="{{ route('pedidos.index') }}" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50"><i class="fas fa-clipboard-list mr-2"></i>Pedidos</a>
                <a href="{{ route('inventario.recepcion.index') }}" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50"><i class="fas fa-barcode mr-2"></i>Recepción con escáner</a>
            </div>
        </div>
    </div>
</body>
</html>
