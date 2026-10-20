<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Almacenero - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <x-sidebar  :role="auth()->user()->role->nombre" />

    <!-- Main Content -->
    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header -->
        <x-header 
            title="Dashboard Almacenero" 
            subtitle="Gestiona el inventario y almacenes, {{ auth()->user()->name }}" 
        />

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Productos en Stock -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Productos en Stock</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $productos_stock }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-boxes text-blue-900 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Productos Bajo Stock -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Bajo Stock</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $productos_bajo_stock }}</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Movimientos Hoy -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Movimientos Hoy</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $movimientos_hoy }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-exchange-alt text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Almacenes Activos -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-pink-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Almacenes</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $almacenes_activos }}</p>
                    </div>
                    <div class="bg-pink-100 rounded-full p-3">
                        <i class="fas fa-warehouse text-pink-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de Stock Bajo -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-8 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-yellow-600 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800">Alerta de Inventario</h3>
                    <p class="text-sm text-yellow-700 mt-1">No hay productos con stock bajo en este momento</p>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Gestión de Productos -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-box mr-2 text-blue-900"></i>
                    Gestión de Productos
                </h2>
                <div class="space-y-3">
                    <a href="#" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-plus-circle text-blue-900 mr-2"></i>
                        <span class="font-medium text-gray-900">Agregar Producto</span>
                    </a>
                    <a href="#" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-list text-green-600 mr-2"></i>
                        <span class="font-medium text-gray-900">Ver Inventario</span>
                    </a>
                    <a href="#" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-tags text-pink-600 mr-2"></i>
                        <span class="font-medium text-gray-900">Gestionar Categorías</span>
                    </a>
                </div>
            </div>

            <!-- Movimientos de Inventario -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-exchange-alt mr-2 text-blue-900"></i>
                    Movimientos Recientes
                </h2>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-clipboard-list text-5xl mb-3"></i>
                    <p class="text-sm">No hay movimientos registrados</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>