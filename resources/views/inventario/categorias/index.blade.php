<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <x-sidebar :role="auth()->user()->role->nombre" />

    <!-- Main Content -->
    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header -->
        <x-header 
            title="Gestión de Categorías" 
            subtitle="Administra las categorías de productos del inventario" 
        />

        <!-- Mensajes de éxito/error -->
        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-xl mr-3"></i>
                    <p>{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                    <p>{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Total Categorías</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $categorias->count() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-tags text-blue-900 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Categorías Activas</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $categorias->where('estado', 'activo')->count() }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Categorías Inactivas</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $categorias->where('estado', 'inactivo')->count() }}</p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-times-circle text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-pink-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Total Productos</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $categorias->sum('productos_count') }}</p>
                    </div>
                    <div class="bg-pink-100 rounded-full p-3">
                        <i class="fas fa-boxes text-pink-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Categorías -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header de la tabla -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-list mr-2 text-blue-900"></i>
                        Listado de Categorías
                    </h2>
                    @if($canCreate)
                        <a href="{{ route('inventario.categorias.create') }}" class="bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800 transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            Nueva Categoría
                        </a>
                    @endif
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Productos</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($categorias as $categoria)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">{{ $categoria->codigo }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($categoria->imagen)
                                        <img src="{{ $categoria->imagen_url }}" alt="{{ $categoria->nombre }}" class="h-10 w-10 rounded-lg object-cover mr-3">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center mr-3">
                                            <i class="fas fa-image text-gray-400"></i>
                                        </div>
                                    @endif
                                    <span class="text-sm font-medium text-gray-900">{{ $categoria->nombre }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-box mr-1"></i>
                                    {{ $categoria->productos_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($categoria->estado === 'activo')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex items-center justify-center space-x-2">
                                    @if($canEdit)
                                        <a href="{{ route('inventario.categorias.edit', $categoria) }}" class="text-blue-600 hover:text-blue-900" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                    
                                    @if($canDelete)
                                        <form action="{{ route('inventario.categorias.destroy', $categoria) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-500">
                                    <i class="fas fa-inbox text-6xl mb-4"></i>
                                    <p class="text-lg font-medium">No hay categorías registradas</p>
                                    <p class="text-sm mt-2">Comienza creando tu primera categoría</p>
                                    @if($canCreate)
                                        <a href="{{ route('inventario.categorias.create') }}" class="mt-4 bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800 transition-colors">
                                            <i class="fas fa-plus mr-2"></i>
                                            Crear Categoría
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>