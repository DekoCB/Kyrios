<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - CORPORACIÓN ADIVON SAC</title>
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
            title="Gestión de Productos" 
            subtitle="Administra el catálogo completo de productos" 
        />

        <!-- Mensajes -->
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

        @if(session('info'))
            <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-4 rounded-lg">
                <div class="flex items-center gap-3">
                    <i class="fas fa-info-circle text-xl"></i>
                    <p>{{ session('info') }}</p>
                </div>
            </div>
        @endif

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Total Productos</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $productos->total() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-box text-blue-900 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Productos Activos</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ \App\Models\Producto::query()->activos()->count() }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Stock Bajo</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ \App\Models\Producto::query()->stockBajo()->count() }}</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Sin Stock</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ \App\Models\Producto::query()->sinStock()->count() }}</p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-times-circle text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form action="{{ route('inventario.productos.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Búsqueda -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                        <input type="text" 
                               name="buscar" 
                               value="{{ request('buscar') }}"
                               placeholder="Código, nombre o código de barras"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Categoría -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                        <select name="categoria_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas las categorías</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}" {{ request('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select name="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="todos"       {{ request('estado') == 'todos'        ? 'selected' : '' }}>Todos los estados</option>
                            <option value="activo"      {{ request('estado', 'activo') == 'activo'      ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo"    {{ request('estado') == 'inactivo'    ? 'selected' : '' }}>Inactivo</option>
                            <option value="descontinuado" {{ request('estado') == 'descontinuado' ? 'selected' : '' }}>Descontinuado</option>
                        </select>
                    </div>

                    <!-- Estado de Stock -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                        <select name="stock_estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="bajo" {{ request('stock_estado') == 'bajo' ? 'selected' : '' }}>Stock Bajo</option>
                            <option value="sin_stock" {{ request('stock_estado') == 'sin_stock' ? 'selected' : '' }}>Sin Stock</option>
                        </select>
                    </div>
                </div>

                <!-- Filtro por tipo de inventario y almacén -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Inventario</label>
                        <select name="tipo_inventario" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="cantidad" {{ request('tipo_inventario') == 'cantidad' ? 'selected' : '' }}>Stock por Cantidad</option>
                            <option value="serie" {{ request('tipo_inventario') == 'serie' ? 'selected' : '' }}>Stock por Serie/IMEI</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sede / Sucursal</label>
                        <select name="almacen_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas las sedes</option>
                            @foreach($almacenes as $alm)
                                <option value="{{ $alm->id }}" {{ request('almacen_id') == $alm->id ? 'selected' : '' }}>
                                    {{ $alm->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2"></div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <a href="{{ route('inventario.productos.index') }}" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-redo mr-2"></i>Limpiar filtros
                    </a>
                    <button type="submit" class="bg-blue-900 text-white px-6 py-2 rounded-lg hover:bg-blue-800">
                        <i class="fas fa-search mr-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de Productos -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-list mr-2 text-blue-900"></i>
                        Listado de Productos
                    </h2>
                    @if($canCreate)
                        <a href="{{ route('inventario.productos.create') }}" class="bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800 transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            Nuevo Producto
                        </a>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca/Modelo</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                @if($almacenFiltro)
                                    Stock en {{ $almacenFiltro->nombre }}
                                @else
                                    Stock total
                                @endif
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($productos as $producto)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">{{ $producto->codigo }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if($producto->imagen)
                                        <img src="{{ $producto->imagen_url }}" alt="{{ $producto->nombre }}" class="h-12 w-12 rounded-lg object-cover mr-3">
                                    @else
                                        <div class="h-12 w-12 rounded-lg bg-gray-200 flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-gray-400"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $producto->nombre }}</p>
                                        @if($producto->codigo_barras)
                                            <p class="text-xs text-gray-500">CB: {{ $producto->codigo_barras }}</p>
                                        @endif
                                        @if($producto->variantesActivas->count() > 0)
                                            <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">
                                                <i class="fas fa-layer-group text-indigo-500"></i>
                                                {{ $producto->variantesActivas->count() }} variante(s)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600">{{ $producto->categoria->nombre ?? 'Sin categoría' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    @if($producto->marca)
                                        <p class="font-medium">{{ $producto->marca->nombre ?? 'N/A' }}</p>
                                    @endif
                                    @if($producto->modelo)
                                        <p class="text-xs text-gray-500">{{ $producto->modelo->nombre ?? '' }}</p>
                                    @endif
                                    @if($producto->color)
                                        <p class="text-xs text-gray-500">Color: {{ $producto->color->nombre ?? '' }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    $esSerie = $producto->tipo_inventario === 'serie';
                                    if ($almacenFiltro) {
                                        // Stock de la sede seleccionada
                                        if ($esSerie) {
                                            $stockTotal = $producto->imeis_sede_count ?? 0;
                                        } else {
                                            $stockTotal = $producto->stockAlmacenes->first()?->cantidad ?? 0;
                                        }
                                    } else {
                                        // Stock global: si tiene variantes, sumar su stock (siempre al día,
                                        // a diferencia de productos.stock_actual que puede desincronizarse
                                        // para productos tipo "serie" — ver IMEIs registrados por compra).
                                        if ($producto->variantesActivas->count() > 0) {
                                            $stockTotal = $producto->variantesActivas->sum('stock_actual');
                                        } else {
                                            $stockTotal = $producto->stock_actual ?? 0;
                                        }
                                    }
                                    $colorClass = $stockTotal == 0
                                        ? 'bg-red-100 text-red-800'
                                        : ($stockTotal <= 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800');
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                    {{ $stockTotal }} unt
                                </span>
                                @if(!$almacenFiltro && $producto->variantesActivas->count() > 0)
                                    <div class="flex items-center justify-center gap-1 mt-1 flex-wrap">
                                        @foreach($producto->variantesActivas as $v)
                                            <span title="{{ $v->nombre_completo }}: {{ $v->stock_actual }} unt"
                                                  class="inline-flex items-center gap-1 text-xs text-gray-500">
                                                @if($v->color?->codigo_hex)
                                                    <span class="w-2.5 h-2.5 rounded-full border border-gray-300 shrink-0"
                                                          style="background-color:{{ $v->color->codigo_hex }}"></span>
                                                @endif
                                                {{ $v->stock_actual }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($producto->tipo_inventario === 'serie')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-mobile-alt mr-1"></i> IMEI
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-boxes mr-1"></i> Cantidad
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($producto->estado === 'activo')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Activo
                                    </span>
                                @elseif($producto->estado === 'inactivo')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Inactivo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Descontinuado
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- Botón Ver Detalles -->
                                    <a href="{{ route('inventario.productos.show', $producto) }}" class="text-gray-600 hover:text-gray-900" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @if($canEdit)
                                        <a href="{{ route('inventario.productos.edit', $producto) }}" class="text-blue-600 hover:text-blue-900" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif

                                    <!-- Botón Gestionar Variantes -->
                                    @if($producto->variantesActivas->count() > 0 || $producto->tipo_inventario === 'serie')
                                        <a href="{{ route('inventario.productos.variantes', $producto) }}"
                                           class="text-indigo-600 hover:text-indigo-900"
                                           title="Gestionar variantes{{ $producto->variantesActivas->count() > 0 ? ' (' . $producto->variantesActivas->count() . ')' : '' }}">
                                            <i class="fas fa-layer-group"></i>
                                        </a>
                                    @endif

                                    <!-- Botón para gestionar IMEIs (solo para tipo serie) -->
                                    @if($producto->tipo_inventario === 'serie')
                                        <a href="{{ route('inventario.imeis.index', ['producto_id' => $producto->id]) }}"
                                           class="text-purple-600 hover:text-purple-900"
                                           title="Gestionar IMEIs">
                                            <i class="fas fa-sim-card"></i>
                                        </a>
                                    @endif

                                    <!-- Ver stock por almacén -->
                                    <a href="{{ route('traslados.stock', ['buscar' => $producto->nombre]) }}"
                                       class="text-teal-600 hover:text-teal-900"
                                       title="Ver stock por almacén">
                                        <i class="fas fa-boxes"></i>
                                    </a>

                                    <!-- Botón para gestionar códigos de barras adicionales -->
                                    <a href="{{ route('inventario.productos.codigos-barras', $producto) }}"
                                       class="text-indigo-600 hover:text-indigo-900"
                                       title="Códigos de barras">
                                        <i class="fas fa-barcode"></i>
                                    </a>
                                    
                                    @if($canDelete)
                                        <form action="{{ route('inventario.productos.destroy', $producto) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Si el producto tiene movimientos se desactivará (no se borrará). ¿Continuar?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Desactivar / Eliminar">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-500">
                                    <i class="fas fa-inbox text-6xl mb-4"></i>
                                    <p class="text-lg font-medium">No se encontraron productos</p>
                                    <p class="text-sm text-gray-400 mt-1">Intenta con otros filtros o crea un nuevo producto</p>
                                    @if($canCreate)
                                        <a href="{{ route('inventario.productos.create') }}" class="mt-4 bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800">
                                            <i class="fas fa-plus mr-2"></i>
                                            Crear Producto
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Información adicional del listado -->
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <div>
                        <i class="fas fa-info-circle mr-1"></i>
                        Mostrando {{ $productos->firstItem() ?? 0 }} - {{ $productos->lastItem() ?? 0 }} de {{ $productos->total() }} productos
                    </div>
                    <div class="flex space-x-4">
                        <span class="flex items-center">
                            <span class="w-3 h-3 bg-green-100 border border-green-500 rounded-full mr-1"></span>
                            Stock normal
                        </span>
                        <span class="flex items-center">
                            <span class="w-3 h-3 bg-yellow-100 border border-yellow-500 rounded-full mr-1"></span>
                            Stock bajo
                        </span>
                        <span class="flex items-center">
                            <span class="w-3 h-3 bg-red-100 border border-red-500 rounded-full mr-1"></span>
                            Sin stock
                        </span>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            @if($productos->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $productos->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════ PANEL PRODUCTOS INACTIVOS ══════════════ --}}
    @if($productosInactivos->isNotEmpty())
    <div class="md:ml-64 px-4 md:px-8 pb-10">
        <details class="group bg-white rounded-2xl shadow border border-gray-200 overflow-hidden">
            <summary class="flex items-center justify-between px-5 py-4 cursor-pointer select-none
                            bg-gray-50 hover:bg-gray-100 transition list-none">
                <div class="flex items-center gap-3">
                    <i class="fas fa-archive text-gray-400"></i>
                    <span class="font-semibold text-gray-600 text-sm">
                        Productos Inactivos / Descontinuados
                    </span>
                    <span class="bg-gray-200 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">
                        {{ $productosInactivos->count() }}
                    </span>
                </div>
                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
            </summary>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-left">Categoría</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Desactivado</th>
                            @if($canDelete)
                            <th class="px-4 py-3 text-center">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($productosInactivos as $p)
                        <tr class="opacity-60 hover:opacity-100 transition-opacity">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if($p->imagen)
                                        <img src="{{ Storage::url($p->imagen) }}" class="w-8 h-8 rounded object-cover">
                                    @else
                                        <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-box text-gray-300 text-xs"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="font-medium text-gray-700 line-through">{{ $p->nombre }}</p>
                                        <p class="text-xs text-gray-400">{{ $p->codigo }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $p->categoria?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($p->estado === 'inactivo')
                                    <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">
                                        <i class="fas fa-ban text-[10px]"></i> Inactivo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full font-medium">
                                        <i class="fas fa-exclamation-circle text-[10px]"></i> Descontinuado
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs">
                                {{ $p->updated_at->format('d/m/Y') }}
                            </td>
                            @if($canDelete)
                            <td class="px-4 py-3 text-center">
                                <form action="{{ route('inventario.productos.reactivar', $p) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Reactivar el producto {{ addslashes($p->nombre) }}?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded-lg transition">
                                        <i class="fas fa-redo-alt"></i> Reactivar
                                    </button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {});
    </script>
</body>
</html>