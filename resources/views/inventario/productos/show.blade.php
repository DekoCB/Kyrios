<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header con navegación -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Detalle del Producto</h1>
                    <p class="text-sm text-gray-600 mt-1">Información completa de {{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('inventario.productos.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Volver
                    </a>
                    @if(in_array(auth()->user()->role->nombre, ['Administración', 'Logística']))
                        <a href="{{ route('inventario.productos.edit', $producto) }}" class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                            <i class="fas fa-edit mr-2"></i>Editar
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        <!-- Grid principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna izquierda: Imagen e información básica -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Imagen: foto principal + miniaturas de las fotos cargadas (hasta 6 por variante) -->
                    @php
                        $fotos = $producto->imagenes->map(fn ($i) => $i->url)->values();
                        if ($fotos->isEmpty() && $producto->imagen_url) { $fotos = collect([$producto->imagen_url]); }
                    @endphp
                    <div class="p-6 bg-gray-50 border-b" x-data="{ fotos: @js($fotos), actual: 0, fallo: false }">
                        <div class="flex justify-center">
                            <template x-if="fotos.length && !fallo">
                                <img :src="fotos[actual]" alt="{{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}" class="max-h-64 w-full object-contain" x-on:error="fallo = true">
                            </template>
                            <div x-show="!fotos.length || fallo" class="h-48 w-48 bg-gray-200 rounded-lg flex items-center justify-center" @if($fotos->isNotEmpty()) style="display:none" @endif>
                                <i class="fas fa-box text-6xl text-gray-400"></i>
                            </div>
                        </div>
                        <div x-show="fotos.length > 1" class="flex flex-wrap justify-center gap-2 mt-4 max-h-36 overflow-y-auto" style="display:none">
                            <template x-for="(f, i) in fotos" :key="i">
                                <button type="button" @click="actual = i; fallo = false"
                                        :class="actual === i ? 'ring-2 ring-blue-500' : 'opacity-70 hover:opacity-100'"
                                        class="h-14 w-14 rounded-lg overflow-hidden border border-gray-200 bg-white">
                                    <img :src="f" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Información básica -->
                    <div class="p-6">
                        <h2 class="text-xl font-bold {{ \App\Support\EtiquetaProducto::tieneNombre($producto) ? 'text-gray-900' : 'text-gray-400 italic' }}">{{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}</h2>
                        @if(filled($producto->nombre_fabrica) && mb_strtolower(trim($producto->nombre_fabrica)) !== mb_strtolower(\App\Support\EtiquetaProducto::nombrePrincipal($producto)))
                            <p class="text-sm text-gray-500 mb-4">Nombre de fábrica: <span class="font-medium text-gray-700">{{ $producto->nombre_fabrica }}</span></p>
                        @else
                            <div class="mb-4"></div>
                        @endif
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Código:</span>
                                <span class="text-sm text-gray-900 font-mono">{{ $producto->codigo }}</span>
                            </div>
                            
                            @if($producto->codigo_barras)
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Código Barras:</span>
                                <span class="text-sm text-gray-900 font-mono">{{ $producto->codigo_barras }}</span>
                            </div>
                            @endif
                            
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Tipo Inventario:</span>
                                <span class="text-sm">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                        <i class="fas fa-boxes mr-1"></i> Cantidad
                                    </span>
                                </span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Estado:</span>
                                <span class="text-sm">
                                    @if($producto->estado === 'activo')
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Activo</span>
                                    @elseif($producto->estado === 'inactivo')
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">Inactivo</span>
                                    @else
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Descontinuado</span>
                                    @endif
                                </span>
                            </div>
                            
                            <div class="pt-3 border-t">
                                <span class="text-sm font-medium text-gray-500 block mb-2">Descripción:</span>
                                <p class="text-sm text-gray-700">{{ $producto->descripcion ?: 'Sin descripción' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Detalles y movimientos -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Tarjeta de clasificación -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-900 px-6 py-3">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-tags mr-2"></i>
                            Clasificación
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Categoría</p>
                                <p class="font-medium">{{ $producto->categoria->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Marca</p>
                                <p class="font-medium">{{ $producto->marca->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Modelo</p>
                                <p class="font-medium">{{ $producto->modelo->nombre ?? 'N/A' }}</p>
                            </div>
                            @if($producto->variantesActivas->isEmpty())
                            <div>
                                <p class="text-xs text-gray-500">Color</p>
                                <p class="font-medium">{{ $producto->color->nombre ?? 'N/A' }}</p>
                            </div>
                            @endif
                            <div>
                                <p class="text-xs text-gray-500">Unidad Medida</p>
                                <p class="font-medium">{{ $producto->unidadMedida->nombre ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de stock -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-green-600 px-6 py-3">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-boxes mr-2"></i>
                            Control de Stock
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Stock Actual</p>
                                @php $colorStock = $stockReal <= 0 ? 'text-red-600' : ($stockReal <= $producto->stock_minimo ? 'text-yellow-600' : 'text-green-600'); @endphp
                                <p class="text-2xl font-bold {{ $colorStock }}">{{ $stockReal }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Stock Mínimo</p>
                                <p class="text-xl font-semibold">{{ $producto->stock_minimo }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Stock Máximo</p>
                                <p class="text-xl font-semibold">{{ $producto->stock_maximo }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ubicación</p>
                                <p class="font-medium">{{ $producto->ubicacion ?: 'No definida' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vista rápida de variantes (solo si tiene) -->
                @if($producto->variantesActivas->isNotEmpty())
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-indigo-700 px-6 py-3 flex items-center justify-between">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-layer-group mr-2"></i>
                            Variantes
                            <span class="ml-2 bg-white/20 text-white text-xs px-2 py-0.5 rounded-full">{{ $producto->variantesActivas->count() }}</span>
                        </h3>
                        @if(in_array(auth()->user()->role->nombre, ['Administración', 'Logística']))
                        <a href="{{ route('inventario.productos.variantes', $producto) }}"
                           class="text-xs text-indigo-200 hover:text-white transition">
                            Gestionar <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                        @endif
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($producto->variantesActivas as $variante)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @php $miniatura = $variante->imagenPrincipal; @endphp
                                @if($miniatura)
                                    <img src="{{ $miniatura->url }}" alt="" class="h-10 w-10 rounded-lg object-cover border border-gray-200 shrink-0">
                                @endif
                                @if($variante->color?->codigo_hex)
                                    <span class="w-4 h-4 rounded-full border border-gray-300 shrink-0"
                                          style="background-color: {{ $variante->color->codigo_hex }}"></span>
                                @else
                                    <span class="w-4 h-4 rounded-full bg-gray-200 border border-gray-300 shrink-0"></span>
                                @endif
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ $variante->nombre_completo }}</span>
                                    <span class="ml-2 text-xs {{ $variante->codigo_kyrios ? 'text-gray-700 font-semibold' : 'text-gray-400' }} font-mono">{{ $variante->codigo_kyrios ?? $variante->sku }}</span>
                                    @if($variante->codigo_excel)
                                        <span class="ml-1 text-xs text-gray-400 font-mono" title="2° código (Excel anterior)">2°: {{ $variante->codigo_excel }}</span>
                                    @endif
                                    @if($variante->estado_dano && $variante->estado_dano !== 'ninguno')
                                        @php $claseDano = ['leve' => 'bg-yellow-100 text-yellow-800', 'moderado' => 'bg-cyan-100 text-cyan-800', 'grave' => 'bg-gray-700 text-white'][$variante->estado_dano]; @endphp
                                        <span class="ml-1 text-xs px-2 py-0.5 rounded-full font-medium {{ $claseDano }}" title="{{ $variante->observacion_dano }}">
                                            Daño {{ $variante->estado_dano }}
                                        </span>
                                    @endif
                                    @if($variante->codigo_fabricante || $variante->socket || $variante->potencia_w)
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            @if($variante->codigo_fabricante)Fab.: <span class="font-mono">{{ $variante->codigo_fabricante }}</span>@endif
                                            @if($variante->socket) · Socket {{ $variante->socket }}@if($variante->num_lamparas) {{ $variante->num_lamparas }}@endif @endif
                                            @if($variante->potencia_w) · {{ rtrim(rtrim($variante->potencia_w, '0'), '.') }} W @endif
                                            @if($variante->diametro) · Ø {{ rtrim(rtrim($variante->diametro, '0'), '.') }} mm @endif
                                            @if($variante->alto) · Alto {{ rtrim(rtrim($variante->alto, '0'), '.') }} mm @endif
                                        </p>
                                    @endif
                                    @include('inventario.productos.partials.atributos-variante-resumen', ['variante' => $variante])
                                </div>
                            </div>
                            @php
                                $stockVar = $variante->stock_actual;
                                $labelVar = 'unt';
                            @endphp
                            <span class="text-sm font-bold {{ $stockVar <= ($variante->stock_minimo ?? 0) ? 'text-red-600' : 'text-gray-800' }}">
                                {{ $stockVar }} <span class="text-xs font-normal text-gray-400">{{ $labelVar }}</span>
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Acciones rápidas -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-700 px-6 py-3">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-bolt mr-2"></i>
                            Acciones Rápidas
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="flex flex-wrap gap-3">
                            @if($producto->variantesActivas->isNotEmpty())
                                <a href="{{ route('inventario.productos.variantes', $producto) }}"
                                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    <i class="fas fa-layer-group mr-2"></i>
                                    Gestionar Variantes
                                </a>
                            @endif

                            <a href="{{ route('inventario.productos.codigos-barras', $producto) }}"
                               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                <i class="fas fa-barcode mr-2"></i>
                                Códigos de Barras
                            </a>

                            @if($producto->tipo_inventario === 'cantidad')
                                <a href="{{ route('inventario.movimientos.create', ['producto_id' => $producto->id]) }}"
                                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-exchange-alt mr-2"></i>
                                    Movimiento de Stock
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Últimos movimientos -->
                @if($producto->movimientos && $producto->movimientos->count() > 0)
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-800 px-6 py-3">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-history mr-2"></i>
                            Últimos Movimientos
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($producto->movimientos as $movimiento)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $movimiento->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4">
                                        @if($movimiento->tipo_movimiento === 'ingreso')
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Ingreso</span>
                                        @else
                                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Salida</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm {{ $movimiento->tipo_movimiento === 'ingreso' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $movimiento->tipo_movimiento === 'ingreso' ? '+' : '-' }}{{ $movimiento->cantidad }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $movimiento->motivo }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($producto->movimientos->count() >= 10)
                    <div class="px-6 py-3 bg-gray-50 border-t">
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-900">Ver todos los movimientos →</a>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Información de costos (solo administradores) -->
                @if(auth()->user()->role->nombre === 'Administración')
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-yellow-600 px-6 py-3">
                        <h3 class="text-white font-semibold">
                            <i class="fas fa-chart-line mr-2"></i>
                            Información de Costos
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($producto->variantesActivas->isNotEmpty())
                            {{-- Costos por variante --}}
                            <div class="divide-y divide-gray-100">
                                @foreach($producto->variantesActivas as $v)
                                <div class="py-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @if($v->color?->codigo_hex)
                                            <span class="w-3 h-3 rounded-full border border-gray-300 shrink-0"
                                                  style="background-color: {{ $v->color->codigo_hex }}"></span>
                                        @endif
                                        <span class="text-sm font-medium text-gray-700">{{ $v->nombre_completo }}</span>
                                    </div>
                                    <div class="flex gap-6 text-right">
                                        <div>
                                            <p class="text-xs text-gray-400">Costo promedio</p>
                                            <p class="text-sm font-semibold">S/ {{ number_format($v->costo_promedio ?? 0, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Último costo</p>
                                            <p class="text-sm font-semibold">S/ {{ number_format($v->ultimo_costo_compra ?? 0, 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Actualizado</p>
                                            <p class="text-sm font-semibold">
                                                {{ $v->updated_at ? $v->updated_at->format('d/m/Y') : 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Costo Promedio</p>
                                    <p class="text-lg font-semibold">S/ {{ number_format($producto->costo_promedio ?? 0, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Último Costo Compra</p>
                                    <p class="text-lg font-semibold">S/ {{ number_format($producto->ultimo_costo_compra ?? 0, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Última Compra</p>
                                    <p class="text-lg font-semibold">{{ $producto->fecha_ultima_compra ? $producto->fecha_ultima_compra->format('d/m/Y') : 'N/A' }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>