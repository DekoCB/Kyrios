<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Precios - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-6">
    <x-header title="Gestión de Precios" subtitle="Administra los precios de venta y márgenes por producto" />

    {{-- Alerta --}}
    @if(session('success'))
    <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">
        <i class="fas fa-check-circle text-green-500"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- ── STATS ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <i class="fas fa-boxes text-blue-600 text-base"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total</p>
                <p class="text-2xl font-bold text-gray-900 leading-tight">{{ $totalProductos }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                <i class="fas fa-tag text-emerald-600 text-base"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Con precio</p>
                <p class="text-2xl font-bold text-gray-900 leading-tight">{{ $conPrecio }}</p>
            </div>
        </div>
        <a href="{{ route('precios.index', array_merge(request()->except('tab','page'), ['tab' => 'sin_precio'])) }}"
           class="bg-white rounded-xl border {{ request('tab') === 'sin_precio' ? 'border-red-300 ring-2 ring-red-200' : 'border-gray-100' }} shadow-sm p-4 flex items-center gap-3 hover:border-red-200 transition-colors">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                <i class="fas fa-exclamation-circle text-red-500 text-base"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Sin precio</p>
                <p class="text-2xl font-bold text-red-600 leading-tight">{{ $sinPrecio }}</p>
            </div>
        </a>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                <i class="fas fa-percentage text-purple-600 text-base"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Margen prom.</p>
                <p class="text-2xl font-bold text-gray-900 leading-tight">
                    {{ $margenPromedio ? number_format($margenPromedio, 1) : '—' }}%
                </p>
            </div>
        </div>
    </div>

    {{-- ── FILTROS ── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">

        {{-- Búsqueda --}}
        <form method="GET" id="filtroForm" class="flex flex-wrap gap-3 items-center mb-4">
            @if(request('tab'))
                <input type="hidden" name="tab" value="{{ request('tab') }}">
            @endif
            <div class="relative flex-1 min-w-56">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       placeholder="Buscar por nombre o código..."
                       class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <input type="hidden" name="categoria_id" id="categoriaHidden" value="{{ request('categoria_id') }}">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                <i class="fas fa-search text-xs"></i> Buscar
            </button>
            @if(request()->hasAny(['buscar','categoria_id','tab']))
            <a href="{{ route('precios.index') }}"
               class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                <i class="fas fa-times text-xs"></i> Limpiar
            </a>
            @endif
        </form>

        {{-- Pills de categorías --}}
        <div class="flex flex-wrap gap-2 items-center">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide mr-1">Categoría:</span>
            <a href="{{ route('precios.index', array_merge(request()->except('categoria_id','page'), [])) }}"
               class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-colors
                   {{ !request('categoria_id') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-600' }}">
                Todas
            </a>
            @foreach($categorias as $cat)
            <a href="{{ route('precios.index', array_merge(request()->except('categoria_id','page'), ['categoria_id' => $cat->id])) }}"
               class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-colors
                   {{ request('categoria_id') == $cat->id ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-600' }}">
                {{ $cat->nombre }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- ── TABS ── --}}
    <div class="flex items-center gap-1 mb-4 bg-white rounded-xl border border-gray-100 shadow-sm p-1 w-fit">
        <a href="{{ route('precios.index', request()->except('tab','page')) }}"
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ !request('tab') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <i class="fas fa-list text-xs"></i>
            Todos los productos
            <span class="text-xs {{ !request('tab') ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-500' }} px-1.5 py-0.5 rounded-full font-semibold">
                {{ $totalProductos }}
            </span>
        </a>
        <a href="{{ route('precios.index', array_merge(request()->except('tab','page'), ['tab' => 'sin_precio'])) }}"
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request('tab') === 'sin_precio' ? 'bg-red-500 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <i class="fas fa-exclamation-triangle text-xs"></i>
            Sin precio
            @if($sinPrecio > 0)
            <span class="text-xs {{ request('tab') === 'sin_precio' ? 'bg-red-400 text-white' : 'bg-red-100 text-red-600' }} px-1.5 py-0.5 rounded-full font-semibold">
                {{ $sinPrecio }}
            </span>
            @endif
        </a>
    </div>

    {{-- ── TABLA ── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

        {{-- Header de la tabla --}}
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                @if(request('tab') === 'sin_precio')
                    <span class="inline-flex items-center gap-1.5 text-red-600 font-medium">
                        <i class="fas fa-exclamation-circle text-xs"></i>
                        Productos que necesitan precio configurado
                    </span>
                @else
                    <span class="font-medium text-gray-700">{{ $productos->total() }}</span> productos encontrados
                @endif
            </p>
            <p class="text-xs text-gray-400">Página {{ $productos->currentPage() }} de {{ $productos->lastPage() }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">P. Compra</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">P. Venta</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Margen</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
                    </tr>
                </thead>

                @forelse($productos as $producto)
                @php
                    $tieneVariantes  = $producto->variantes->isNotEmpty();
                    $preciosActivos  = $producto->precios->unique('variante_id');

                    if ($tieneVariantes) {
                        $porCapacidad = $preciosActivos->groupBy(
                            fn($p) => $p->variante?->capacidad ?? ''
                        );
                        $tienePrecio = $preciosActivos->isNotEmpty();
                        // For summary: all capacity groups (even without a price yet)
                        $capacidades = $producto->variantes->groupBy(fn($v) => $v->capacidad ?? '');
                    } else {
                        $precio       = $preciosActivos->first();
                        $tienePrecio  = $precio && (float)$precio->precio > 0;
                        $porCapacidad = collect();
                        $capacidades  = collect();
                    }
                @endphp

                {{-- Use <tbody> per product so x-data scopes correctly --}}
                <tbody x-data="{ open: false }">

                    {{-- ── Main row ── --}}
                    <tr class="border-b border-gray-100 hover:bg-blue-50/20 transition-colors">

                        {{-- Producto --}}
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                @if($tieneVariantes)
                                {{-- Expand toggle --}}
                                <button type="button" @click="open = !open"
                                        class="w-6 h-6 flex items-center justify-center rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors shrink-0">
                                    <i class="fas fa-chevron-down text-[10px] transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                @else
                                <div class="w-9 h-9 rounded-xl {{ $tienePrecio ? 'bg-blue-100' : 'bg-red-50' }} flex items-center justify-center shrink-0">
                                    <i class="fas fa-box {{ $tienePrecio ? 'text-blue-600' : 'text-red-400' }} text-sm"></i>
                                </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate max-w-xs">{{ \App\Support\EtiquetaProducto::nombre($producto) }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-gray-400 font-mono">{{ $producto->codigo }}</span>
                                        @if($producto->categoria)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                                            {{ $producto->categoria->nombre }}
                                        </span>
                                        @endif
                                        @if($tieneVariantes)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-600">
                                            <i class="fas fa-microchip text-[9px]"></i>
                                            {{ $capacidades->count() }} cap.
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        @if($tieneVariantes)
                            @php
                                $pvMin = $preciosActivos->min('precio');
                                $pvMax = $preciosActivos->max('precio');
                                $conPrecioCount = $porCapacidad->count();
                            @endphp
                            {{-- P. Compra --}}
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs text-gray-400 italic">múltiple</span>
                            </td>
                            {{-- P. Venta --}}
                            <td class="px-5 py-3.5 text-right">
                                @if($tienePrecio)
                                    <span class="text-sm font-bold text-blue-700">
                                        S/ {{ number_format($pvMin, 2) }}
                                        @if($pvMin != $pvMax)<br><span class="text-xs font-normal text-blue-400">— S/ {{ number_format($pvMax, 2) }}</span>@endif
                                    </span>
                                @else
                                    <span class="text-gray-300 text-sm">—</span>
                                @endif
                            </td>
                            {{-- Margen --}}
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs text-gray-400 italic">múltiple</span>
                            </td>
                            {{-- Estado --}}
                            <td class="px-5 py-3.5 text-center">
                                @if(!$tienePrecio)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                                        <i class="fas fa-times-circle text-xs"></i> Sin precio
                                    </span>
                                @elseif($conPrecioCount < $capacidades->count())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fas fa-exclamation-circle text-xs"></i> Parcial
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fas fa-check-circle text-xs"></i> Vigente
                                    </span>
                                @endif
                            </td>
                        @else
                            {{-- Single-price product cells --}}
                            <td class="px-5 py-3.5 text-sm text-right text-gray-600">
                                @if($tienePrecio && $precio->precio_compra)
                                    S/ {{ number_format($precio->precio_compra, 2) }}
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if($tienePrecio)
                                    <span class="text-sm font-bold text-blue-700">S/ {{ number_format($precio->precio, 2) }}</span>
                                @else
                                    <span class="text-gray-300 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if($tienePrecio && $precio->margen !== null)
                                    @php $m = (float)$precio->margen; @endphp
                                    @php $color = $m >= 30 ? 'text-emerald-700 bg-emerald-50' : ($m >= 15 ? 'text-yellow-700 bg-yellow-50' : 'text-red-600 bg-red-50'); @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold {{ $color }}">
                                        <i class="fas fa-arrow-{{ $m >= 15 ? 'up' : 'down' }} text-[10px]"></i>
                                        {{ number_format($m, 1) }}%
                                    </span>
                                @else
                                    <span class="text-gray-300 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if(!$tienePrecio)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                                        <i class="fas fa-times-circle text-xs"></i> Sin precio
                                    </span>
                                @elseif($precio->fecha_fin && $precio->fecha_fin->isPast())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200">
                                        <i class="fas fa-clock text-xs"></i> Vencido
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fas fa-check-circle text-xs"></i> Vigente
                                    </span>
                                @endif
                            </td>
                        @endif

                        {{-- Acciones --}}
                        <td class="px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('precios.show', $producto) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                                       {{ !$tienePrecio ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}">
                                    <i class="fas fa-{{ !$tienePrecio ? 'plus' : 'tags' }} text-xs"></i>
                                    {{ !$tienePrecio ? 'Asignar' : 'Gestionar' }}
                                </a>
                                @if($tienePrecio)
                                <a href="{{ route('precios.historial', $producto) }}"
                                   class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 hover:bg-purple-100 hover:text-purple-600 transition-colors"
                                   title="Historial">
                                    <i class="fas fa-history text-xs"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- ── Variant sub-rows (expandable) ── --}}
                    @if($tieneVariantes)
                        @foreach($capacidades as $cap => $vars)
                            @php
                                $preciosCap = $porCapacidad->get($cap) ?? collect();
                                $pCap = $preciosCap->first();
                                $hayPrecio = $pCap && (float)$pCap->precio > 0;
                            @endphp
                            <tr x-show="open" x-cloak
                                class="border-b border-blue-100 bg-blue-50/40 transition-colors hover:bg-blue-50/70">
                                <td class="pl-14 pr-5 py-2.5 text-sm">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-microchip text-blue-400 text-xs"></i>
                                        <span class="font-medium text-gray-700">{{ $cap ?: 'Sin capacidad' }}</span>
                                        <span class="text-xs text-gray-400">
                                            ({{ $vars->count() }} {{ $vars->count() === 1 ? 'color' : 'colores' }})
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-2.5 text-sm text-right text-gray-600">
                                    @if($hayPrecio && $pCap->precio_compra)
                                        S/ {{ number_format($pCap->precio_compra, 2) }}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    @if($hayPrecio)
                                        <span class="text-sm font-bold text-blue-700">S/ {{ number_format($pCap->precio, 2) }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fas fa-exclamation-circle text-[9px]"></i> Sin precio
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    @if($hayPrecio)
                                        <span class="text-xs font-medium text-emerald-700">
                                            S/ {{ number_format($pCap->precio * 1.18, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    @if($hayPrecio && $pCap->margen !== null)
                                        @php $m = (float)$pCap->margen; @endphp
                                        @php $color = $m >= 30 ? 'text-emerald-700 bg-emerald-50' : ($m >= 15 ? 'text-yellow-700 bg-yellow-50' : 'text-red-600 bg-red-50'); @endphp
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold {{ $color }}">
                                            <i class="fas fa-arrow-{{ $m >= 15 ? 'up' : 'down' }} text-[10px]"></i>
                                            {{ number_format($m, 1) }}%
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    @if($hayPrecio)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fas fa-circle text-[6px]"></i> Vigente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fas fa-circle text-[6px]"></i> Pendiente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    <a href="{{ route('precios.show', $producto) }}?variante_id={{ $vars->first()->id }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                        <i class="fas fa-tags text-xs"></i> Gestionar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                </tbody>

                @empty
                <tbody>
                    <tr>
                        <td colspan="7" class="px-5 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                @if(request('tab') === 'sin_precio')
                                    <div class="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center">
                                        <i class="fas fa-check-circle text-emerald-500 text-3xl"></i>
                                    </div>
                                    <p class="text-base font-semibold text-gray-700">¡Todos los productos tienen precio!</p>
                                    <p class="text-sm text-gray-400">No hay productos pendientes de configuración.</p>
                                @else
                                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-search text-gray-400 text-3xl"></i>
                                    </div>
                                    <p class="text-base font-semibold text-gray-700">Sin resultados</p>
                                    <p class="text-sm text-gray-400">Intenta ajustar los filtros de búsqueda.</p>
                                @endif
                                <a href="{{ route('precios.index') }}" class="text-sm text-blue-600 hover:underline mt-1">Ver todos los productos</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
                @endforelse

            </table>
        </div>

        {{-- Paginación --}}
        @if($productos->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/50">
            {{ $productos->links() }}
        </div>
        @endif
    </div>
</div>
</body>
</html>
