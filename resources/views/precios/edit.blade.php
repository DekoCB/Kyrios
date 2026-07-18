<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editar Precio · {{ $producto->nombre }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('precios.index') }}" class="hover:text-blue-700 transition-colors">Gestión de Precios</a>
        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
        <a href="{{ route('precios.show', $producto) }}" class="hover:text-blue-700 transition-colors truncate max-w-xs">{{ $producto->nombre }}</a>
        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
        <span class="text-gray-800 font-medium">Editar Precio</span>
    </nav>

    {{-- Header --}}
    @php
        $varianteEditando = $precio->variante;
        $capacidadEditando = $varianteEditando
            ? ($varianteEditando->capacidad ?: 'Sin capacidad específica')
            : null;
        // Count how many colors share this capacity
        $coloresCapacidad = $varianteEditando
            ? $producto->variantesActivas->where('capacidad', $varianteEditando->capacidad)->count()
            : 0;
        $tiendaEditando = $precio->almacen;
    @endphp
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Editar Precio</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $producto->nombre }}</p>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                @if($capacidadEditando)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full border border-blue-200">
                        <i class="fas fa-microchip text-[10px]"></i>
                        Capacidad: {{ $capacidadEditando }}
                        @if($coloresCapacidad > 1)
                            <span class="text-blue-500">({{ $coloresCapacidad }} colores)</span>
                        @endif
                    </span>
                @endif
                @if($tiendaEditando)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full border border-indigo-200">
                        <i class="fas fa-store text-[10px]"></i> {{ $tiendaEditando->nombre }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full border border-gray-200">
                        <i class="fas fa-globe text-[10px]"></i> Precio global
                    </span>
                @endif
            </div>
        </div>
        <a href="{{ route('precios.show', $producto) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    {{-- Alertas de validación --}}
    @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <span class="text-sm font-semibold">Por favor corrige los siguientes errores:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Columna izquierda: info + referencia --}}
        <div class="space-y-5">

            {{-- Info del producto --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-4">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-box"></i> Información del Producto
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        ['Código', $producto->codigo, 'font-mono text-xs bg-gray-100 px-2 py-0.5 rounded'],
                        ['Categoría', $producto->categoria->nombre ?? '—', ''],
                        ['Marca', $producto->marca->nombre ?? '—', ''],
                        ['Modelo', $producto->modelo->nombre ?? '—', ''],
                        ['Stock', ($producto->stock_actual ?? 0) . ' und.', 'font-semibold text-blue-700'],
                    ] as [$label, $value, $extra])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">{{ $label }}</span>
                        <span class="font-medium text-gray-900 {{ $extra }}">{{ $value }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Precio actual guardado --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-700 to-gray-600 px-5 py-4">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-tag"></i> Precio Guardado
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    @if($capacidadEditando)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Capacidad</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-full text-xs font-semibold">
                            <i class="fas fa-microchip text-[9px]"></i> {{ $capacidadEditando }}
                        </span>
                    </div>
                    @endif
                    @if($tiendaEditando)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Tienda</span>
                        <span class="font-medium text-gray-800">{{ $tiendaEditando->nombre }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Precio compra</span>
                        <span class="font-semibold text-gray-800">S/ {{ number_format($precio->precio_compra, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 font-semibold">Precio de Venta</span>
                        <span class="font-bold text-blue-700">S/ {{ number_format($precio->precio, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400 border-t border-gray-100 pt-2 mt-1">
                        <span>Base sin IGV</span>
                        <span>S/ {{ number_format($precio->precio / 1.18, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>IGV (18%)</span>
                        <span>S/ {{ number_format($precio->precio - ($precio->precio / 1.18), 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Margen</span>
                        <span class="font-semibold {{ $precio->margen >= 20 ? 'text-green-700' : 'text-yellow-700' }}">
                            {{ $precio->margen }}%
                        </span>
                    </div>
                    @if($precio->proveedor)
                    <div class="flex items-start justify-between text-sm gap-2">
                        <span class="text-gray-500 shrink-0">Proveedor</span>
                        <span class="font-medium text-gray-800 text-right">{{ $precio->proveedor->razon_social }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Info tips --}}
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h4 class="text-xs font-semibold text-blue-900 mb-2 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> Información
                </h4>
                <ul class="text-xs text-blue-800 space-y-1.5 list-disc list-inside">
                    <li>El precio de compra se jala automáticamente de la última compra registrada para el proveedor seleccionado</li>
                    <li>El precio de venta se calcula según el margen ingresado</li>
                    <li>Solo puede existir un precio activo a la vez</li>
                    <li>Este cambio quedará registrado en el historial</li>
                </ul>
            </div>

        </div>

        {{-- Columna derecha: formulario --}}
        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
                 data-prov-id="{{ old('proveedor_id', $precio->proveedor_id) }}"
                 data-prov-nombre="{{ old('_prov_nombre', $precio->proveedor?->razon_social ?? '') }}"
                 x-data="{
                     precioCompra: {{ $precio->precio_compra ?? 0 }},
                     margen: {{ $precio->margen ?? 0 }},
                     precioVenta: {{ $precio->precio ?? 0 }},
                     modoCalculo: 'margen',
                     varianteId: {{ $precio->variante_id ?? 'null' }},

                     proveedorId: null,
                     busquedaProv: '',
                     resultadosProv: [],
                     abiertoDropdown: false,
                     buscandoProv: false,

                     ultimaCompra: null,
                     cargandoCompra: false,

                     init() {
                         const pid = this.$el.dataset.provId;
                         this.proveedorId = pid ? parseInt(pid) : null;
                         this.busquedaProv = this.$el.dataset.provNombre || '';
                         // No recalculamos al cargar — usamos los valores guardados tal cual
                         if (this.proveedorId) {
                             this.fetchUltimaCompra();
                         }
                     },

                     calcularPrecioVenta() {
                         const compra = parseFloat(this.precioCompra) || 0;
                         const margen = parseFloat(this.margen) || 0;
                         if (compra > 0 && margen >= 0) {
                             this.precioVenta = Math.round(compra * (1 + margen / 100) * 100) / 100;
                         }
                     },

                     calcularMargen() {
                         const compra = parseFloat(this.precioCompra) || 0;
                         const venta  = parseFloat(this.precioVenta)  || 0;
                         if (compra > 0 && venta > 0) {
                             // Margen siempre sobre precio base, IGV es solo referencial
                             this.margen = Math.round(((venta - compra) / compra * 100) * 10) / 10;
                         }
                     },

                     async buscarProveedor() {
                         if (this.busquedaProv.length < 2) {
                             this.resultadosProv = [];
                             this.abiertoDropdown = false;
                             return;
                         }
                         this.buscandoProv = true;
                         const res = await fetch('{{ route('precios.proveedores.buscar') }}?q=' + encodeURIComponent(this.busquedaProv));
                         this.resultadosProv = await res.json();
                         this.abiertoDropdown = this.resultadosProv.length > 0;
                         this.buscandoProv = false;
                     },

                     async seleccionarProveedor(prov) {
                         this.proveedorId = prov.id;
                         this.busquedaProv = prov.razon_social;
                         this.abiertoDropdown = false;
                         this.resultadosProv = [];
                         await this.fetchUltimaCompra();
                     },

                     limpiarProveedor() {
                         this.proveedorId = null;
                         this.busquedaProv = '';
                         this.abiertoDropdown = false;
                         this.resultadosProv = [];
                         this.ultimaCompra = null;
                     },

                     async fetchUltimaCompra() {
                         if (!this.proveedorId) return;
                         this.cargandoCompra = true;
                         let url = '{{ route('precios.ultimo-precio-compra', $producto) }}?proveedor_id=' + this.proveedorId;
                         if (this.varianteId) url += '&variante_id=' + this.varianteId;
                         const res = await fetch(url);
                         const data = await res.json();
                         this.ultimaCompra = data.found ? data : null;
                         this.cargandoCompra = false;
                     },

                     usarPrecioCompra() {
                         if (this.ultimaCompra) {
                             this.precioCompra = this.ultimaCompra.precio_unitario;
                             this.calcularPrecioVenta();
                         }
                     }
                 }"
                 x-init="init()">

                <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 px-5 py-4 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-edit"></i> Actualizar Precio
                    </h2>
                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                        @if($capacidadEditando)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-400/40 text-white text-xs font-semibold rounded-full border border-yellow-300/50">
                                <i class="fas fa-microchip text-[9px]"></i> {{ $capacidadEditando }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-400/40 text-white text-xs font-medium rounded-full border border-yellow-300/50">
                                <i class="fas fa-box text-[9px]"></i> Precio base
                            </span>
                        @endif
                        @if($tiendaEditando)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-400/40 text-white text-xs font-medium rounded-full border border-yellow-300/50">
                                <i class="fas fa-store text-[9px]"></i> {{ $tiendaEditando->nombre }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-400/40 text-white text-xs font-medium rounded-full border border-yellow-300/50">
                                <i class="fas fa-globe text-[9px]"></i> Global
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    <form action="{{ route('precios.update', ['producto' => $producto->id, 'precio' => $precio->id]) }}"
                          method="POST">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            {{-- Búsqueda dinámica de proveedor --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Proveedor <span class="text-red-500">*</span>
                                </label>
                                <input type="hidden" name="proveedor_id" :value="proveedorId">
                                <div class="relative" @click.away="abiertoDropdown = false">
                                    <div class="relative">
                                        <input type="text"
                                               x-model="busquedaProv"
                                               @input.debounce.400ms="buscarProveedor()"
                                               @focus="if (resultadosProv.length) abiertoDropdown = true"
                                               placeholder="Buscar proveedor por nombre o RUC..."
                                               class="w-full border border-gray-200 rounded-lg px-3 py-2 pr-9 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <i x-show="buscandoProv || cargandoCompra" class="fas fa-spinner fa-spin text-gray-400 text-xs"></i>
                                            <i x-show="!buscandoProv && !cargandoCompra && proveedorId"
                                               @click="limpiarProveedor()"
                                               class="fas fa-times text-gray-400 text-xs cursor-pointer hover:text-red-500 transition-colors"></i>
                                            <i x-show="!buscandoProv && !cargandoCompra && !proveedorId"
                                               class="fas fa-search text-gray-400 text-xs"></i>
                                        </div>
                                    </div>
                                    {{-- Dropdown --}}
                                    <div x-show="abiertoDropdown"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 -translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        <template x-for="prov in resultadosProv" :key="prov.id">
                                            <button type="button" @click="seleccionarProveedor(prov)"
                                                    class="w-full text-left px-3 py-2.5 hover:bg-yellow-50 transition-colors border-b border-gray-50 last:border-0">
                                                <div class="text-sm font-medium text-gray-800" x-text="prov.razon_social"></div>
                                                <div class="text-xs text-gray-400" x-text="'RUC: ' + prov.ruc"></div>
                                            </button>
                                        </template>
                                    </div>
                                    {{-- Badge seleccionado --}}
                                    <div x-show="proveedorId && !abiertoDropdown" class="mt-1.5">
                                        <span class="inline-flex items-center gap-1 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded-full">
                                            <i class="fas fa-check-circle text-[9px]"></i>
                                            <span x-text="busquedaProv"></span>
                                        </span>
                                    </div>
                                </div>
                                @error('proveedor_id')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Precio de compra --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Precio Compra (S/) <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="precio_compra"
                                       x-model="precioCompra"
                                       @input="calcularPrecioVenta()"
                                       step="0.01" min="0.01"
                                       required
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">

                                {{-- Referencia última compra --}}
                                <div x-show="cargandoCompra" class="mt-1.5 text-xs text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Consultando compras...
                                </div>
                                <div x-show="ultimaCompra && !cargandoCompra"
                                     class="mt-1.5 flex items-start justify-between gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                                    <div class="flex items-start gap-1.5 text-xs text-blue-700">
                                        <i class="fas fa-shopping-cart mt-0.5 shrink-0"></i>
                                        <div>
                                            <span class="font-semibold">Última compra:</span>
                                            <span x-text="'S/ ' + Number(ultimaCompra?.precio_unitario).toFixed(2)"></span>
                                            <span class="text-blue-400 mx-1">·</span>
                                            <span x-text="ultimaCompra?.compra_codigo"></span>
                                            <span class="text-blue-400 mx-1">·</span>
                                            <span x-text="ultimaCompra?.fecha_compra"></span>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="usarPrecioCompra()"
                                            class="shrink-0 text-xs text-blue-700 font-semibold bg-blue-100 hover:bg-blue-200 px-2 py-0.5 rounded transition-colors whitespace-nowrap">
                                        Usar precio
                                    </button>
                                </div>
                                <div x-show="proveedorId && !cargandoCompra && !ultimaCompra" class="mt-1.5">
                                    <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-lg">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Sin compras registradas para este proveedor
                                    </span>
                                </div>
                                @error('precio_compra')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Toggle modo de cálculo --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Calcular desde</label>
                                <div class="flex rounded-lg border border-gray-200 overflow-hidden w-full">
                                    <button type="button"
                                            @click="modoCalculo='margen'; calcularPrecioVenta()"
                                            :class="modoCalculo==='margen' ? 'bg-yellow-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                            class="flex-1 py-2 text-xs font-semibold transition-colors border-r border-gray-200">
                                        <i class="fas fa-percentage mr-1"></i> Margen %
                                    </button>
                                    <button type="button"
                                            @click="modoCalculo='precio'; calcularMargen()"
                                            :class="modoCalculo==='precio' ? 'bg-yellow-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                            class="flex-1 py-2 text-xs font-semibold transition-colors">
                                        <i class="fas fa-tag mr-1"></i> Precio de venta
                                    </button>
                                </div>
                            </div>

                            {{-- Margen --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Margen (%) <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="margen"
                                       x-model="margen"
                                       @input="if(modoCalculo==='margen') calcularPrecioVenta()"
                                       :readonly="modoCalculo==='precio'"
                                       :class="modoCalculo==='precio' ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                       step="0.1" min="0" max="1000"
                                       required
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <p x-show="modoCalculo==='precio'" class="text-xs text-gray-400 mt-1">Calculado según el precio ingresado</p>
                                @error('margen')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Precio venta --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Precio de Venta (S/) <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="precio_venta"
                                       x-model="precioVenta"
                                       @input="if(modoCalculo==='precio') calcularMargen()"
                                       :readonly="modoCalculo==='margen'"
                                       :class="modoCalculo==='margen' ? 'bg-gray-50 text-blue-700 font-semibold cursor-not-allowed' : 'text-blue-700 font-semibold'"
                                       step="0.01" min="0.01"
                                       required
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <p x-show="modoCalculo==='margen'" class="text-xs text-gray-400 mt-1">Calculado según el margen</p>
                                <p x-show="modoCalculo==='precio'" class="text-xs text-gray-400 mt-1">Ingresa el precio que quieres cobrar</p>
                            </div>

                            {{-- Resumen deducción IGV --}}
                            <input type="hidden" name="incluye_igv" value="1">
                            <div class="md:col-span-2">
                                <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 space-y-1.5">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        <i class="fas fa-receipt mr-1"></i> Resumen
                                    </p>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Base sin IGV</span>
                                        <span class="font-medium text-gray-700" x-text="'S/ ' + (Math.round(parseFloat(precioVenta || 0) / 1.18 * 100) / 100).toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">IGV (18%)</span>
                                        <span class="font-medium text-gray-700" x-text="'S/ ' + (Math.round((parseFloat(precioVenta || 0) - parseFloat(precioVenta || 0) / 1.18) * 100) / 100).toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between text-sm border-t border-gray-200 pt-1.5">
                                        <span class="font-semibold text-blue-800">Precio de Venta</span>
                                        <span class="font-bold text-blue-800 text-lg" x-text="'S/ ' + (parseFloat(precioVenta || 0)).toFixed(2)"></span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Este es el precio que se muestra en el POS y que paga el cliente.</p>
                            </div>

                            {{-- Precio mayorista --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Precio Mayorista (S/)
                                </label>
                                @if($tiendaEditando)
                                    <input type="text"
                                           value="{{ $precioMayoristaActual !== null ? number_format($precioMayoristaActual, 2) : 'No definido' }}"
                                           disabled
                                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-500">
                                    <p class="text-xs text-amber-600 mt-1">
                                        <i class="fas fa-circle-info mr-0.5"></i>
                                        El precio mayorista es global (no varía por tienda).
                                        @if($precioGlobalId)
                                            Edítalo desde el <a href="{{ route('precios.edit', [$producto, $precioGlobalId]) }}" class="underline font-medium">precio global</a> de este producto/variante.
                                        @else
                                            No hay un precio global registrado aún para esta variante donde editarlo.
                                        @endif
                                    </p>
                                @else
                                    <input type="number"
                                           name="precio_mayorista"
                                           step="0.01" min="0.01"
                                           value="{{ old('precio_mayorista', $precioMayoristaActual) }}"
                                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                           placeholder="Opcional">
                                @endif
                            </div>

                            {{-- Fecha inicio --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Fecha Inicio
                                </label>
                                <input type="date"
                                       name="fecha_inicio"
                                       value="{{ old('fecha_inicio', $precio->fecha_inicio?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            </div>

                            {{-- Fecha fin --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Fecha Fin
                                </label>
                                <input type="date"
                                       name="fecha_fin"
                                       value="{{ old('fecha_fin', $precio->fecha_fin?->format('Y-m-d')) }}"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <p class="text-xs text-gray-400 mt-1">Vacío = vigencia indefinida</p>
                            </div>

                            {{-- Estado --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
                                    Estado
                                </label>
                                <div class="flex items-center gap-5">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="activo" value="1"
                                               {{ old('activo', $precio->activo) ? 'checked' : '' }}
                                               class="w-4 h-4 text-yellow-600 border-gray-300 focus:ring-yellow-500">
                                        <span class="text-sm text-gray-700 font-medium">Activo</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="activo" value="0"
                                               {{ old('activo', $precio->activo) == '0' ? 'checked' : '' }}
                                               class="w-4 h-4 text-gray-500 border-gray-300 focus:ring-gray-400">
                                        <span class="text-sm text-gray-700 font-medium">Inactivo</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Observaciones --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                    Motivo / Observaciones
                                </label>
                                <textarea name="observaciones" rows="3"
                                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 resize-none"
                                          placeholder="Describe el motivo del cambio de precio...">{{ old('observaciones', $precio->observaciones) }}</textarea>
                            </div>

                        </div>

                        {{-- Botones --}}
                        <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                            <a href="{{ route('precios.show', $producto) }}"
                               class="inline-flex items-center gap-2 px-5 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 py-2 bg-yellow-500 text-white text-sm font-semibold rounded-lg hover:bg-yellow-600 transition-colors">
                                <i class="fas fa-save"></i> Actualizar Precio
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
