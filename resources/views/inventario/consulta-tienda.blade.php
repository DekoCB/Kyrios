<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Inventario - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Consulta de Inventario" 
            subtitle="Consulta disponibilidad y precios de productos" 
        />

        <!-- Buscador -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form action="{{ route('inventario.productos.consulta-tienda') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar Producto</label>
                        <x-buscador-producto name="buscar" :value="request('buscar')" placeholder="Buscar por código o nombre..." />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                        <select name="categoria_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}" {{ request('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-4">
                    <a href="{{ route('inventario.productos.consulta-tienda') }}" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-redo mr-2"></i>Limpiar
                    </a>
                    <button type="submit" class="bg-blue-900 text-white px-6 py-2 rounded-lg hover:bg-blue-800">
                        <i class="fas fa-search mr-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-boxes mr-2 text-blue-900"></i>
                    Productos disponibles
                    <span class="ml-2 text-sm font-normal text-gray-500">{{ $productos->total() }} resultado(s)</span>
                </h2>
                <x-selector-moneda />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Códigos</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($productos as $producto)
                        @php
                            $variantes = $producto->variantesActivas;
                            $imagen = $producto->imagen ? $producto->imagen_url : $variantes->map->imagenPrincipal->filter()->first()?->url;
                            $nombre = \App\Support\EtiquetaProducto::nombre($producto);
                            $conNombre = \App\Support\EtiquetaProducto::tieneNombre($producto);
                            $detalle = collect([$producto->tipoProducto?->nombre, $producto->marca?->nombre, $producto->modelo?->nombre])->filter()->implode(' · ');
                            $stock = $variantes->count() > 0 ? (int) $variantes->sum('stock_actual') : (int) $producto->stock_actual;
                            $regulares = $producto->precios->where('tipo_precio', 'venta_regular');
                            $regular = $regulares->sortBy('precio')->first();
                            $mayorista = $producto->precios->where('tipo_precio', 'venta_mayorista')->sortBy('precio')->first();
                            $fabricantes = $producto->variantes->pluck('codigo_fabricante')->filter()->unique()->values();
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($imagen)
                                        <img src="{{ $imagen }}" alt="{{ $nombre }}" class="h-14 w-14 rounded-lg object-cover shrink-0">
                                    @else
                                        <div class="h-14 w-14 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                                            <i class="fas fa-box text-gray-400"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold {{ $conNombre ? 'text-gray-900' : 'text-gray-400 italic' }}">{{ $nombre }}</p>
                                        @if($detalle !== '')
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $detalle }}</p>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-1 mt-1">
                                            @if($producto->categoria)
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">{{ $producto->categoria->nombre }}</span>
                                            @endif
                                            @if($variantes->count() > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">
                                                    <i class="fas fa-layer-group"></i> {{ $variantes->count() }} variante(s)
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-mono font-medium text-gray-900">{{ $producto->codigo }}</p>
                                @if($fabricantes->isNotEmpty())
                                    <p class="text-xs font-mono text-gray-500 mt-0.5">fab: {{ $fabricantes->first() }}@if($fabricantes->count() > 1) <span class="text-gray-400">+{{ $fabricantes->count() - 1 }}</span>@endif</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="text-base font-bold {{ $stock <= 0 ? 'text-red-600' : ($stock <= $producto->stock_minimo ? 'text-yellow-600' : 'text-green-600') }}">{{ $stock }}</span>
                                <span class="text-xs text-gray-500 ml-1">unt</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($regular)
                                    <p class="text-base font-bold text-gray-900">{{ $regulares->count() > 1 ? 'desde ' : '' }}{{ precio_dual($regular->precio_con_igv, $regular->moneda) }}</p>
                                    @if($mayorista)
                                        <p class="text-xs text-gray-500">Mayor: {{ precio_dual($mayorista->precio, $mayorista->moneda) }}</p>
                                    @endif
                                @else
                                    <span class="text-sm text-gray-400">Sin precio</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($stock > $producto->stock_minimo)
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"><i class="fas fa-check-circle mr-1"></i>Disponible</span>
                                @elseif($stock > 0)
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800"><i class="fas fa-exclamation-triangle mr-1"></i>Stock bajo</span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800"><i class="fas fa-times-circle mr-1"></i>Agotado</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                                <p class="text-lg font-medium text-gray-500">No se encontraron productos</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($productos->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $productos->links() }}
                </div>
            @endif
        </div>

        <!-- Leyenda -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-900">
                <i class="fas fa-lock mr-2"></i>
                <strong>Vista de solo consulta.</strong> Para gestionar inventario, contacta a Administración o Logística.
            </p>
        </div>
    </div>
</body>
</html>