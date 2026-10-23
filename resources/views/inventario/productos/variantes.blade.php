<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Variantes · {{ $producto->nombre }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    /* Utilidades que el CSS precompilado no incluye (se compilan solas con "npm run build") */
    .-right-1{right:-.25rem}.-top-1{top:-.25rem}.pb-4{padding-bottom:1rem}
    .bg-black\/55{background-color:rgb(0 0 0 / .55)}
    .max-h-\[92vh\]{max-height:92vh}
    .hover\:text-indigo-500:hover{color:#44413A}.hover\:text-red-300:hover{color:#fca5a5}.hover\:text-yellow-300:hover{color:#fde047}
    .gap-x-4{column-gap:1rem}.gap-y-1\.5{row-gap:.375rem}
</style>
</head>
<body class="bg-gray-50" x-data="editModal()">
    <x-sidebar :role="auth()->user()->role->nombre" />

    {{-- Modal Editar Variante --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="open = false"
             class="bg-white rounded-2xl shadow-xl w-full {{ $producto->tipo_producto_id ? 'max-w-3xl max-h-[92vh] overflow-y-auto' : 'max-w-md' }}">
            <div class="bg-gradient-to-r from-indigo-700 to-indigo-600 px-5 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-white font-bold flex items-center gap-2">
                    <i class="fas fa-pencil-alt"></i> Editar Variante
                </h3>
                <button @click="open = false" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form :action="formAction" method="POST" class="p-5 space-y-4">
                @csrf
                @method('PUT')

                @if($producto->tipo_producto_id)
                    <p class="text-xs text-gray-500">Código Kyrios: <span class="font-mono font-semibold text-gray-800" x-text="form.codigo_kyrios"></span>
                        (no cambia al editar)</p>
                    @include('inventario.productos.partials.campos-variante-kyrios', ['modo' => 'editar'])
                @else
                {{-- Color --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                    <select name="color_id" x-model="form.color_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        <option value="">Sin color específico</option>
                        @foreach($colores as $color)
                            <option value="{{ $color->id }}">{{ $color->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Capacidad --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capacidad / Almacenamiento</label>
                    <input type="text" name="capacidad" x-model="form.capacidad"
                           placeholder="Ej: 64GB, 128GB, 256GB"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                @endif

                {{-- Stock Mínimo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock Mínimo</label>
                    <input type="number" name="stock_minimo" x-model="form.stock_minimo"
                           min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" @click="open = false"
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-sm">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="md:ml-64 p-4 md:p-8">
        {{-- Breadcrumb --}}
        <div class="flex items-center text-sm text-gray-500 mb-4">
            <a href="{{ route('inventario.productos.index') }}" class="hover:text-blue-900">Productos</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <a href="{{ route('inventario.productos.show', $producto) }}" class="hover:text-blue-900 truncate max-w-xs">{{ $producto->nombre }}</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span class="text-gray-700 font-medium">Variantes</span>
        </div>

        {{-- Mensajes --}}
        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ─── INFO DEL PRODUCTO BASE ─────────────────────────── --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-box"></i> Producto Base
                        </h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Nombre</p>
                            <p class="font-semibold text-gray-900">{{ $producto->nombre }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Código</p>
                                <p class="font-mono text-gray-700">{{ $producto->codigo }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Tipo</p>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                    Cantidad
                                </span>
                            </div>
                        </div>
                        @if($producto->marca || $producto->modelo)
                        <div class="grid grid-cols-2 gap-3">
                            @if($producto->marca)
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Marca</p>
                                <p class="text-gray-700">{{ $producto->marca->nombre }}</p>
                            </div>
                            @endif
                            @if($producto->modelo)
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Modelo</p>
                                <p class="text-gray-700">{{ $producto->modelo->nombre }}</p>
                            </div>
                            @endif
                        </div>
                        @endif
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Stock Total</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ $stockTotalReal }}
                                <span class="text-sm font-normal text-gray-400">unidades</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ─── Mensajes flash ──────────────────────────────── --}}
                @if(session('success'))
                <div class="mt-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">
                    <i class="fas fa-check-circle text-green-500"></i>
                    {{ session('success') }}
                </div>
                @endif
                @if(session('error'))
                <div class="mt-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    {{ session('error') }}
                </div>
                @endif

                {{-- ─── AGREGAR VARIANTE ─────────────────────────────── --}}
                <div class="bg-white rounded-2xl shadow-md overflow-hidden mt-4"
                     x-data="{ abierto: {{ $errors->any() || session('error') ? 'true' : 'false' }} }">
                    <button @click="abierto = !abierto"
                            class="w-full bg-gradient-to-r from-indigo-600 to-indigo-500 px-5 py-4 flex items-center justify-between">
                        <span class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-plus-circle"></i> Nueva Variante
                        </span>
                        <i class="fas text-white transition-transform" :class="abierto ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>

                    <div x-show="abierto" x-collapse>
                        <form action="{{ route('inventario.productos.variantes.store', $producto) }}"
                              method="POST" class="p-5 space-y-4">
                            @csrf

                            @if($producto->tipo_producto_id)
                                <p class="text-xs text-gray-500">El código Kyrios (<span class="font-mono">KY-…</span>) se asigna automáticamente al guardar.</p>
                                @include('inventario.productos.partials.campos-variante-kyrios', ['modo' => 'nuevo'])
                            @else
                            {{-- Color --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Color <span class="text-gray-400 text-xs">(opcional)</span>
                                </label>
                                <div class="flex gap-2 items-center">
                                    <select id="color_id_variante" name="color_id"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                                        <option value="">Sin color específico</option>
                                        @foreach($colores as $color)
                                            <option value="{{ $color->id }}"
                                                    {{ old('color_id') == $color->id ? 'selected' : '' }}>
                                                {{ $color->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" onclick="abrirModalColor()"
                                            class="shrink-0 px-3 py-2 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded-lg text-sm font-medium transition"
                                            title="Agregar nuevo color">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                @error('color_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Capacidad --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Capacidad / Almacenamiento <span class="text-gray-400 text-xs">(opcional)</span>
                                </label>
                                <input type="text" name="capacidad" value="{{ old('capacidad') }}"
                                       placeholder="Ej: 64GB, 128GB, 256GB"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                                @error('capacidad')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            @endif

                            @if($producto->tipo_inventario === 'cantidad')
                            {{-- Stock inicial (solo para tipo cantidad) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Stock Inicial <span class="text-gray-400 text-xs">(opcional)</span>
                                </label>
                                <input type="number" name="stock_inicial" value="{{ old('stock_inicial', 0) }}"
                                       min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                            @endif

                            <button type="submit"
                                    class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-sm transition">
                                <i class="fas fa-plus mr-2"></i>Agregar Variante
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ─── LISTA DE VARIANTES ──────────────────────────────── --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-800 to-gray-700 px-5 py-4 flex items-center justify-between">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-layer-group"></i>
                            Variantes del Producto
                            <span class="bg-white/20 text-white text-xs px-2 py-0.5 rounded-full ml-1">
                                {{ $producto->variantes->count() }}
                            </span>
                        </h2>
                    </div>

                    @if($producto->variantes->isEmpty())
                        <div class="p-10 text-center text-gray-400">
                            <i class="fas fa-layer-group text-4xl mb-3 text-gray-200"></i>
                            <p class="font-medium">Sin variantes registradas</p>
                            <p class="text-sm mt-1">Agrega la primera variante usando el formulario</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($producto->variantes->sortBy('estado') as $variante)
                              <div>
                                <div class="p-4 flex items-center justify-between gap-4
                                    {{ $variante->estado === 'inactivo' ? 'opacity-50 bg-gray-50' : '' }}">
                                    <div class="flex items-center gap-4 min-w-0">
                                        {{-- Color dot --}}
                                        @if($variante->color && $variante->color->codigo_hex)
                                            <div class="w-9 h-9 rounded-full border-2 border-white shadow flex-shrink-0"
                                                 style="background-color: {{ $variante->color->codigo_hex }};"
                                                 title="{{ $variante->color->nombre }}"></div>
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-gray-200 border-2 border-white shadow flex-shrink-0 flex items-center justify-center">
                                                <i class="fas fa-palette text-gray-400 text-xs"></i>
                                            </div>
                                        @endif

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-gray-900 text-sm">{{ $variante->nombre_completo }}</span>
                                                <span class="font-mono text-xs {{ $variante->codigo_kyrios ? 'text-gray-800 font-semibold' : 'text-gray-400' }} bg-gray-100 px-2 py-0.5 rounded">{{ $variante->codigo_kyrios ?? $variante->sku }}</span>
                                                @if($variante->codigo_excel)
                                                    <span class="font-mono text-xs text-gray-400 border border-gray-200 px-2 py-0.5 rounded" title="2° código (Excel anterior)">2°: {{ $variante->codigo_excel }}</span>
                                                @endif
                                                @if($variante->estado_dano && $variante->estado_dano !== 'ninguno')
                                                    @php $claseDano = ['leve' => 'bg-yellow-100 text-yellow-800', 'moderado' => 'bg-cyan-100 text-cyan-800', 'grave' => 'bg-gray-700 text-white'][$variante->estado_dano]; @endphp
                                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $claseDano }}" title="{{ $variante->observacion_dano }}">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Daño {{ $variante->estado_dano }}
                                                    </span>
                                                @endif
                                                @if($variante->estado === 'inactivo')
                                                    <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium">Inactiva</span>
                                                @endif
                                            </div>
                                            @if($variante->codigo_fabricante || $variante->socket || $variante->potencia_w)
                                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
                                                    @if($variante->codigo_fabricante)<span>Fab.: <span class="font-mono">{{ $variante->codigo_fabricante }}</span></span>@endif
                                                    @if($variante->socket)<span>Socket {{ $variante->socket }}@if($variante->num_lamparas) {{ $variante->num_lamparas }}@endif</span>@endif
                                                    @if($variante->potencia_w)<span>{{ rtrim(rtrim($variante->potencia_w, '0'), '.') }} W</span>@endif
                                                    @if($variante->diametro)<span>Ø {{ rtrim(rtrim($variante->diametro, '0'), '.') }} mm</span>@endif
                                                    @if($variante->alto)<span>Alto {{ rtrim(rtrim($variante->alto, '0'), '.') }} mm</span>@endif
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-4 mt-1 text-xs text-gray-500">
                                                @if($variante->color)
                                                    <span><i class="fas fa-circle mr-1 text-gray-300"></i>{{ $variante->color->nombre }}</span>
                                                @endif
                                                @if($variante->capacidad)
                                                    <span><i class="fas fa-hdd mr-1 text-gray-300"></i>{{ $variante->capacidad }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 flex-shrink-0">
                                        {{-- Stock --}}
                                        <div class="text-right">
                                            @php
                                                $stockVar = $variante->stock_actual;
                                            @endphp
                                            <p class="text-lg font-bold {{ $stockVar <= $variante->stock_minimo ? 'text-red-600' : 'text-gray-900' }}">
                                                {{ $stockVar }}
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                en stock
                                            </p>
                                        </div>

                                        {{-- Acciones --}}
                                        @if($variante->estado === 'activo')
                                            {{-- Editar --}}
                                            <button type="button"
                                                    @click="openEdit({{ \Illuminate\Support\Js::from(array_merge(
                                                        ['id' => $variante->id, 'color_id' => $variante->color_id ?? '', 'capacidad' => $variante->capacidad ?? '', 'stock_minimo' => $variante->stock_minimo, 'codigo_kyrios' => $variante->codigo_kyrios],
                                                        collect(['codigo_fabricante','codigo_excel','acabado_1','acabado_2','tamano','diametro','alto','ancho','profundidad','alto_suspendido','diametro_agujero','socket','num_lamparas','potencia_w','lumenes','temperatura_color','tonalidad_luz','estado_dano','observacion_dano'])
                                                            ->mapWithKeys(fn ($c) => [$c => $variante->{$c} ?? ''])->all()
                                                    )) }})"
                                                    class="text-indigo-400 hover:text-indigo-600 p-2 rounded-lg hover:bg-indigo-50 transition"
                                                    title="Editar">
                                                <i class="fas fa-pencil-alt text-sm"></i>
                                            </button>

                                            {{-- Desactivar --}}
                                            <form action="{{ route('inventario.productos.variantes.destroy', $variante) }}"
                                                  method="POST"
                                                  data-confirm="¿Desactivar esta variante?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="text-red-400 hover:text-red-600 p-2 rounded-lg hover:bg-red-50 transition"
                                                        title="Desactivar">
                                                    <i class="fas fa-ban text-sm"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                {{-- Imágenes de la variante (máximo 6) --}}
                                @if($variante->estado === 'activo')
                                    @php $imgs = $variante->imagenes; $max = \App\Models\ProductoImagen::MAX_POR_VARIANTE; @endphp
                                    <div class="px-4 pb-4 -mt-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-medium text-gray-500 mr-1"><i class="fas fa-images mr-1"></i>Imágenes {{ $imgs->count() }}/{{ $max }}</span>
                                            @foreach($imgs as $img)
                                                <div class="relative group">
                                                    <img src="{{ $img->url }}" alt="Imagen {{ $img->orden }}"
                                                         class="h-16 w-16 rounded-lg object-cover border-2 {{ $img->es_principal ? 'border-indigo-500' : 'border-gray-200' }}">
                                                    <div class="absolute inset-0 rounded-lg bg-black/55 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1">
                                                        @unless($img->es_principal)
                                                            <form action="{{ route('inventario.productos.imagenes.principal', $img) }}" method="POST">
                                                                @csrf
                                                                <button class="text-white hover:text-yellow-300 text-xs p-1" title="Marcar como principal"><i class="fas fa-star"></i></button>
                                                            </form>
                                                        @endunless
                                                        <form action="{{ route('inventario.productos.imagenes.destroy', $img) }}" method="POST" data-confirm="¿Eliminar esta imagen?">
                                                            @csrf @method('DELETE')
                                                            <button class="text-white hover:text-red-300 text-xs p-1" title="Eliminar"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </div>
                                                    @if($img->es_principal)
                                                        <span class="absolute -top-1 -right-1 bg-indigo-600 text-white rounded-full w-4 h-4 flex items-center justify-center text-[9px]"><i class="fas fa-star"></i></span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if($imgs->count() < $max)
                                                <form action="{{ route('inventario.productos.variantes.imagenes.store', $variante) }}" method="POST" enctype="multipart/form-data"
                                                      class="flex items-center gap-2">
                                                    @csrf
                                                    <label class="h-16 w-16 rounded-lg border-2 border-dashed border-gray-300 hover:border-indigo-400 text-gray-400 hover:text-indigo-500 flex flex-col items-center justify-center cursor-pointer text-[10px] text-center leading-tight">
                                                        <i class="fas fa-plus mb-0.5"></i>Agregar
                                                        <input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden"
                                                               onchange="if (this.files.length > {{ $max - $imgs->count() }}) { alert('Solo puede agregar {{ $max - $imgs->count() }} imagen(es) más a esta variante.'); this.value = ''; return; } this.form.submit();">
                                                    </label>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                              </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
<script>
function editModal() {
    return {
        open: false,
        formAction: '',
        form: { color_id: '', capacidad: '', stock_minimo: 0, codigo_kyrios: '' },
        openEdit(variante) {
            this.formAction = '{{ url("inventario/productos/variantes") }}/' + variante.id;
            this.form = Object.assign({}, variante);
            this.open = true;
        }
    }
}
</script>

<!-- Modal Nuevo Color -->
<div id="modalColor" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
        <div class="bg-linear-to-r from-indigo-600 to-indigo-500 px-6 py-4 rounded-t-2xl flex items-center justify-between">
            <h3 class="font-bold text-white flex items-center gap-2">
                <i class="fas fa-palette"></i> Nuevo Color
            </h3>
            <button onclick="cerrarModalColor()" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                <input type="text" id="colorNombre" placeholder="Ej: Azul medianoche"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color HEX <span class="text-gray-400 text-xs">(opcional)</span></label>
                <div class="flex gap-3 items-center">
                    <input type="color" id="colorHexPicker" value="#44413A"
                           class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer p-0.5"
                           oninput="document.getElementById('colorHexText').value = this.value">
                    <input type="text" id="colorHexText" value="#44413A" maxlength="7"
                           placeholder="#rrggbb"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                           oninput="sincronizarHex(this.value)">
                </div>
            </div>
            <div id="colorError" class="hidden text-red-600 text-sm bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="cerrarModalColor()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" id="btnGuardarColor" onclick="guardarColor()"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Guardar Color
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModalColor() {
        document.getElementById('colorNombre').value = '';
        document.getElementById('colorHexPicker').value = '#44413A';
        document.getElementById('colorHexText').value = '#44413A';
        document.getElementById('colorError').classList.add('hidden');
        const modal = document.getElementById('modalColor');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => document.getElementById('colorNombre').focus(), 100);
    }

    function cerrarModalColor() {
        const modal = document.getElementById('modalColor');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function sincronizarHex(val) {
        if (/^#[a-fA-F0-9]{6}$/.test(val)) {
            document.getElementById('colorHexPicker').value = val;
        }
    }

    async function guardarColor() {
        const nombre = document.getElementById('colorNombre').value.trim();
        const hex    = document.getElementById('colorHexText').value.trim() || null;

        if (!nombre) {
            mostrarErrorColor('El nombre del color es obligatorio.');
            return;
        }
        if (hex && !/^#[a-fA-F0-9]{6}$/.test(hex)) {
            mostrarErrorColor('El código HEX debe tener formato #rrggbb.');
            return;
        }

        const btn = document.getElementById('btnGuardarColor');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        document.getElementById('colorError').classList.add('hidden');

        try {
            const res = await fetch('{{ route('catalogo.colores.rapida') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ nombre, codigo_hex: hex }),
            });
            const data = await res.json();
            if (data.success) {
                // Agregar al select y seleccionarlo
                const select = document.getElementById('color_id_variante');
                const option = document.createElement('option');
                option.value   = data.id;
                option.text    = data.nombre;
                option.selected = true;
                select.appendChild(option);
                cerrarModalColor();
            } else {
                mostrarErrorColor(data.message || 'Error al guardar el color.');
            }
        } catch (e) {
            mostrarErrorColor('Error de conexión. Intenta nuevamente.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Guardar Color';
        }
    }

    function mostrarErrorColor(msg) {
        const el = document.getElementById('colorError');
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    document.getElementById('modalColor').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalColor();
    });
</script>
</body>
</html>
