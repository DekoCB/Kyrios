<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Traslado - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Nuevo Traslado"
            subtitle="Registra un traslado de stock entre almacenes — puedes incluir múltiples productos"
        />

        {{-- Navegación --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <a href="{{ route('traslados.index') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-exchange-alt"></i> Historial
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.pendientes') }}" class="text-sm text-gray-600 hover:text-yellow-600 flex items-center gap-1">
                <i class="fas fa-clock"></i> Pendientes
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.stock') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-boxes"></i> Stock por Almacén
            </a>
            <span class="text-gray-300">|</span>
            <span class="text-sm font-semibold text-blue-700 flex items-center gap-1">
                <i class="fas fa-plus-circle"></i> Nuevo Traslado
            </span>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-data="trasladoForm()" x-init="init()">

            <form action="{{ route('traslados.store') }}" method="POST">
                @csrf

                {{-- ══════════════════════════════════════════════════
                     CABECERA DEL TRASLADO
                ═══════════════════════════════════════════════════ --}}
                <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">

                    <div class="bg-linear-to-r from-blue-900 to-blue-700 px-6 py-4 flex items-center gap-3">
                        <div class="bg-white/20 rounded-xl p-2.5">
                            <i class="fas fa-exchange-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-white">Datos del Traslado</h2>
                            <p class="text-blue-200 text-xs">Origen, destino y guía de remisión</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">

                        {{-- Origen / Destino --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                    <i class="fas fa-warehouse mr-1 text-orange-500"></i>Almacén Origen *
                                </label>
                                <select name="almacen_id" required
                                        x-model="almacenId"
                                        @change="onAlmacenOrigenChange()"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="">— Seleccione origen —</option>
                                    @foreach($almacenes as $alm)
                                        <option value="{{ $alm->id }}" {{ old('almacen_id') == $alm->id ? 'selected' : '' }}>
                                            {{ $alm->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('almacen_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                    <i class="fas fa-store mr-1 text-green-500"></i>Almacén Destino *
                                </label>
                                <select name="almacen_destino_id" required
                                        x-model="almacenDestinoId"
                                        @change="onAlmacenDestinoChange()"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="">— Seleccione destino —</option>
                                    @foreach($almacenes as $alm)
                                        <option value="{{ $alm->id }}" {{ old('almacen_destino_id') == $alm->id ? 'selected' : '' }}>
                                            {{ $alm->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('almacen_destino_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                                <p x-show="almacenId && almacenDestinoId && almacenId === almacenDestinoId" x-cloak
                                   class="text-xs text-red-500 mt-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Origen y destino no pueden ser iguales
                                </p>
                            </div>
                        </div>

                        {{-- N° Guía --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-file-alt mr-1 text-blue-400"></i>N° Guía
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="numeroGuia" readonly
                                       class="w-full sm:w-64 px-3 py-2.5 text-sm border border-gray-200 rounded-lg font-mono uppercase bg-gray-50 text-gray-600 cursor-not-allowed"
                                       placeholder="Se genera automáticamente">
                                <span x-show="guiaSerieId" x-cloak
                                      class="text-xs text-emerald-600 font-medium flex items-center gap-1 whitespace-nowrap">
                                    <i class="fas fa-check-circle"></i> Auto-generado
                                </span>
                                <span x-show="almacenId && !guiaSerieId" x-cloak
                                      class="text-xs text-amber-500 font-medium flex items-center gap-1 whitespace-nowrap">
                                    <i class="fas fa-exclamation-triangle"></i> Sin serie configurada
                                </span>
                            </div>
                            <input type="hidden" name="guia_serie_id" x-model="guiaSerieId">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-comment-alt mr-1 text-gray-400"></i>Observaciones
                                <span class="text-gray-400 font-normal normal-case">(opcional)</span>
                            </label>
                            <textarea name="observaciones" rows="2"
                                      class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"
                                      placeholder="Motivo del traslado, instrucciones...">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ══ Datos de transporte → se capturan en el siguiente paso (Guía de Remisión) ══ --}}
                <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex gap-2 text-sm text-blue-800 mb-5">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500 shrink-0"></i>
                    <span>Al guardar, serás redirigido al formulario de <strong>Guía de Remisión</strong> para completar los datos de transporte (conductor, modalidad, etc.).</span>
                </div>
                <div class="bg-white rounded-2xl shadow-md mb-5">

                    <div class="bg-linear-to-r from-purple-900 to-purple-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                        <div class="flex items-center gap-3">
                            <div class="bg-white/20 rounded-xl p-2.5">
                                <i class="fas fa-boxes text-white text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-white">Productos a Trasladar</h2>
                                <p class="text-purple-200 text-xs">Agrega uno o varios productos</p>
                            </div>
                        </div>
                        <span class="bg-white/20 text-white text-sm font-bold px-3 py-1 rounded-full"
                              x-text="productos.length + ' producto(s)'"></span>
                    </div>

                    <div class="p-6 space-y-4">

                        {{-- Aviso sin almacén origen --}}
                        <div x-show="!almacenId"
                             class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 px-4 py-3 rounded-lg">
                            <i class="fas fa-arrow-up shrink-0"></i>
                            Selecciona el almacén origen primero para ver el stock disponible.
                        </div>

                        {{-- ── Filas de producto ── --}}
                        <template x-for="(producto, idx) in productos" :key="producto._id">
                            <div class="border border-gray-200 rounded-xl"
                                 :class="esDuplicado(idx) ? 'border-red-300 bg-red-50' : 'bg-gray-50/40'">

                                {{-- Cabecera de la fila --}}
                                <div class="flex items-center justify-between px-4 py-2.5 bg-white border-b border-gray-100">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                                        Producto <span x-text="idx + 1"></span>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span x-show="producto.esSerie" x-cloak
                                              class="inline-flex items-center gap-1 text-[10px] font-semibold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                                            <i class="fas fa-barcode"></i> IMEI
                                        </span>
                                        <button type="button"
                                                @click="eliminarProducto(idx)"
                                                x-show="productos.length > 1"
                                                class="text-gray-400 hover:text-red-500 transition p-1 rounded-lg hover:bg-red-50">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="p-4 space-y-3">

                                    {{-- Buscador dinámico de producto --}}
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                            <i class="fas fa-box mr-1 text-blue-400"></i>Producto *
                                        </label>

                                        {{-- Chip del producto seleccionado --}}
                                        <div x-show="producto.productoId" x-cloak
                                             class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                                            <i class="fas fa-check-circle text-blue-500 text-xs shrink-0"></i>
                                            <span class="flex-1 text-sm font-medium text-blue-800" x-text="producto.nombre"></span>
                                            <span class="text-[10px] text-blue-400 font-mono" x-text="producto.codigo"></span>
                                            <span x-show="producto.esSerie"
                                                  class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold">IMEI</span>
                                            <button type="button" @click="limpiarProducto(idx)"
                                                    class="text-blue-300 hover:text-red-500 transition ml-1">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>

                                        {{-- Buscador (visible cuando no hay producto seleccionado) --}}
                                        <div x-show="!producto.productoId" class="relative">
                                            <div class="relative">
                                                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs pointer-events-none"></i>
                                                <input type="text"
                                                       x-model="producto.busqueda"
                                                       @input="buscarProducto(idx)"
                                                       @keydown.escape="producto.resultados = []"
                                                       :placeholder="almacenId ? 'Buscar por nombre o código...' : 'Selecciona almacén origen primero'"
                                                       :disabled="!almacenId"
                                                       class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                                <div x-show="producto.buscando" class="absolute right-3 top-2.5">
                                                    <i class="fas fa-spinner fa-spin text-gray-400 text-xs"></i>
                                                </div>
                                            </div>

                                            {{-- Dropdown resultados --}}
                                            <div x-show="producto.resultados.length > 0" x-cloak
                                                 class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden max-h-[420px] overflow-y-auto">
                                                <template x-for="item in producto.resultados" :key="item.id">
                                                    <div class="border-b border-gray-100 last:border-0"
                                                         :class="estaUsado(item.id, idx) ? 'opacity-40' : ''">
                                                        {{-- Cabecera del producto --}}
                                                        <button type="button"
                                                                @click="seleccionarProducto(idx, item)"
                                                                :disabled="estaUsado(item.id, idx)"
                                                                class="w-full text-left px-4 py-3 transition-colors disabled:cursor-not-allowed"
                                                                :class="estaUsado(item.id, idx) ? 'bg-gray-50' : 'hover:bg-blue-50'">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                                                     :class="item.es_serie ? 'bg-purple-100' : 'bg-blue-100'">
                                                                    <i class="text-xs" :class="item.es_serie ? 'fas fa-mobile-alt text-purple-600' : 'fas fa-box text-blue-600'"></i>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-semibold text-gray-800 truncate" x-text="item.nombre"></p>
                                                                    <div class="flex items-center gap-2 mt-0.5">
                                                                        <span class="text-[10px] font-mono text-gray-400" x-text="item.codigo"></span>
                                                                        <span x-show="item.es_serie"
                                                                              class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold">IMEI</span>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right shrink-0">
                                                                    <span class="text-xs font-bold px-2 py-1 rounded-lg"
                                                                          :class="item.stock_origen > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                                                                          x-text="item.stock_origen + (item.es_serie ? ' IMEI' : ' u.')"></span>
                                                                </div>
                                                            </div>
                                                            <span x-show="estaUsado(item.id, idx)"
                                                                  class="text-[10px] text-amber-500 mt-1 block">Ya agregado en otra fila</span>
                                                        </button>

                                                        {{-- Preview de variantes (inline en el dropdown) --}}
                                                        <div x-show="item.tiene_variantes && !estaUsado(item.id, idx)" x-cloak
                                                             class="px-4 pb-3 -mt-1">
                                                            <div class="flex flex-wrap gap-1.5">
                                                                <template x-for="v in item.variantes" :key="v.id">
                                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-medium border"
                                                                          :class="v.stock > 0 ? 'bg-white border-gray-200 text-gray-700' : 'bg-gray-50 border-gray-100 text-gray-400'">
                                                                        <span x-show="v.color_hex"
                                                                              class="w-3 h-3 rounded-full border border-gray-300 shrink-0"
                                                                              :style="'background-color:' + v.color_hex"></span>
                                                                        <span x-text="v.nombre"></span>
                                                                        <span class="font-bold" :class="v.stock > 0 ? 'text-green-600' : 'text-red-400'"
                                                                              x-text="'(' + v.stock + ')'"></span>
                                                                    </span>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div x-show="producto.resultados.length === 0 && producto.busqueda.length >= 2 && !producto.buscando"
                                                     class="px-4 py-3 text-center text-xs text-gray-400">
                                                    Sin resultados para "<span x-text="producto.busqueda"></span>"
                                                </div>
                                            </div>
                                        </div>

                                        <input type="hidden" :name="`productos[${idx}][producto_id]`" :value="producto.productoId">

                                        <p x-show="esDuplicado(idx)" x-cloak
                                           class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span x-text="producto.variantes.length > 0 ? 'Esta variante ya está en otra fila.' : 'Este producto ya está en otra fila.'"></span>
                                        </p>
                                    </div>

                                    {{-- Stock badge --}}
                                    <div x-show="producto.productoId && producto.stockOrigen !== null && almacenId" x-cloak>
                                        <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                                              :class="producto.stockOrigen > 0
                                                  ? 'bg-green-100 text-green-700'
                                                  : 'bg-red-100 text-red-600'">
                                            <i class="fas fa-cubes mr-1"></i>
                                            Disponible:
                                            <strong x-text="producto.stockOrigen"></strong>
                                            <span x-show="producto.esSerie"> IMEIs</span>
                                            <span x-show="!producto.esSerie"> unid.</span>
                                        </span>
                                    </div>

                                    {{-- ── VARIANTE SELECTOR (accesorio y serie con variantes) ── --}}
                                    <div x-show="producto.productoId && producto.variantes.length > 0" x-cloak>
                                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                            <i class="fas fa-palette mr-1 text-pink-400"></i>
                                            <span x-text="producto.esSerie ? 'Filtrar por variante' : 'Variante'"></span>
                                        </label>

                                        {{-- Tarjetas de variantes --}}
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                            {{-- Opción "Todas" solo para serie --}}
                                            <div x-show="producto.esSerie" x-cloak
                                                 @click="producto.varianteId = null; producto.stockOrigen = producto.stockTotal ?? producto.stockOrigen"
                                                 class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-all"
                                                 :class="!producto.varianteId ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-200' : 'border-gray-200 hover:border-blue-300'">
                                                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center shrink-0">
                                                    <i class="fas fa-layer-group text-[8px] text-gray-500"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs font-semibold text-gray-700">Todas las variantes</p>
                                                    <p class="text-[10px] text-gray-400" x-text="producto.variantes.length + ' opciones'"></p>
                                                </div>
                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full"
                                                      :class="producto.stockOrigen > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                                                      x-text="producto.stockOrigen + ' disp.'"></span>
                                            </div>

                                            <template x-for="v in producto.variantes" :key="v.id">
                                                <div @click="seleccionarVariante(idx, v)"
                                                     class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-all"
                                                     :class="String(producto.varianteId) === String(v.id) ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-200' : 'border-gray-200 hover:border-blue-300'">
                                                    <template x-if="v.color_hex">
                                                        <span class="w-6 h-6 rounded-full border-2 border-white shadow-sm shrink-0"
                                                              :style="'background-color:' + v.color_hex"></span>
                                                    </template>
                                                    <template x-if="!v.color_hex">
                                                        <span class="w-6 h-6 rounded-full bg-gray-200 border-2 border-white shadow-sm shrink-0 flex items-center justify-center">
                                                            <i class="fas fa-mobile-alt text-[8px] text-gray-400"></i>
                                                        </span>
                                                    </template>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-xs font-semibold text-gray-700 truncate" x-text="v.nombre"></p>
                                                        <p x-show="v.sku" class="text-[10px] text-gray-400 font-mono" x-text="v.sku"></p>
                                                    </div>
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0"
                                                          :class="v.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                                                          x-text="v.stock + (producto.esSerie ? ' IMEI' : ' u.')"></span>
                                                </div>
                                            </template>
                                        </div>

                                        <input type="hidden" :name="`productos[${idx}][variante_id]`" :value="varianteIdEnvioDe(idx) ?? ''">
                                    </div>

                                    {{-- ── CANTIDAD (accesorio) ── --}}
                                    <div x-show="producto.productoId && !producto.esSerie">
                                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                            <i class="fas fa-hashtag mr-1 text-blue-400"></i>Cantidad *
                                        </label>
                                        <input type="number"
                                               :name="`productos[${idx}][cantidad]`"
                                               x-model.number="producto.cantidad"
                                               min="1"
                                               :max="producto.stockOrigen ?? undefined"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <p x-show="producto.stockOrigen !== null && producto.cantidad > producto.stockOrigen" x-cloak
                                           class="text-xs text-red-500 mt-1">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Supera el stock disponible.
                                        </p>
                                    </div>

                                    {{-- ── IMEI PICKER ── --}}
                                    <div x-show="producto.productoId && producto.esSerie" x-cloak>
                                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                            <i class="fas fa-barcode mr-1 text-purple-500"></i>IMEIs a trasladar *
                                        </label>

                                        {{-- Prompt si no hay almacén --}}
                                        <div x-show="!almacenId"
                                             class="text-xs text-gray-400 bg-gray-100 rounded-lg px-3 py-2">
                                            <i class="fas fa-info-circle mr-1"></i>Seleccione almacén origen para ver los IMEIs.
                                        </div>

                                        <div x-show="almacenId" x-cloak>

                                            {{-- Buscador + contador --}}
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="relative flex-1">
                                                    <i class="fas fa-search absolute left-2.5 top-2.5 text-[10px] text-gray-400 pointer-events-none"></i>
                                                    <input type="text"
                                                           x-model="producto.imeiBusqueda"
                                                           placeholder="Buscar IMEI o S/N..."
                                                           class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-400">
                                                </div>
                                                <div class="shrink-0 text-xs bg-purple-50 border border-purple-200 text-purple-700 font-mono rounded-lg px-2.5 py-1.5">
                                                    <span class="font-bold" x-text="producto.imeisSeleccionados.length"></span>
                                                    /
                                                    <span x-text="producto.imeisDisponibles.length"></span>
                                                </div>
                                            </div>

                                            {{-- Loading --}}
                                            <div x-show="producto.imeisLoading"
                                                 class="py-4 text-center text-xs text-gray-400">
                                                <i class="fas fa-spinner fa-spin mr-1"></i>Cargando IMEIs...
                                            </div>

                                            {{-- Sin IMEIs --}}
                                            <div x-show="!producto.imeisLoading && producto.imeisDisponibles.length === 0" x-cloak
                                                 class="py-4 text-center text-xs text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-lg">
                                                <i class="fas fa-box-open text-gray-300 text-xl block mb-1"></i>
                                                Sin IMEIs disponibles en este almacén
                                            </div>

                                            {{-- Lista de IMEIs --}}
                                            <div x-show="!producto.imeisLoading && producto.imeisDisponibles.length > 0" x-cloak
                                                 class="border border-gray-200 rounded-lg overflow-hidden">

                                                {{-- Toolbar: seleccionar todos --}}
                                                <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-100 sticky top-0">
                                                    <button type="button"
                                                            @click="seleccionarTodos(idx)"
                                                            class="text-xs font-semibold text-purple-600 hover:text-purple-800 flex items-center gap-1">
                                                        <i class="fas fa-check-double text-[10px]"></i>
                                                        <span x-text="imeisFiltradosDe(idx).length > 0 && imeisFiltradosDe(idx).every(i => producto.imeisSeleccionados.includes(i.id))
                                                            ? 'Deseleccionar todos' : 'Seleccionar todos'"></span>
                                                    </button>
                                                    <span class="text-[10px] text-gray-400"
                                                          x-show="producto.imeiBusqueda" x-cloak
                                                          x-text="imeisFiltradosDe(idx).length + ' resultado(s)'"></span>
                                                </div>

                                                <div class="max-h-44 overflow-y-auto divide-y divide-gray-100">
                                                    <template x-for="imei in imeisFiltradosDe(idx)" :key="imei.id">
                                                        <label :for="`imei-${producto._id}-${imei.id}`"
                                                               class="flex items-center gap-2.5 px-3 py-2 cursor-pointer transition-colors"
                                                               :class="isSelected(idx, imei.id) ? 'bg-purple-50 hover:bg-purple-100' : 'hover:bg-gray-50'">
                                                            <input type="checkbox"
                                                                   :id="`imei-${producto._id}-${imei.id}`"
                                                                   :checked="isSelected(idx, imei.id)"
                                                                   @change="toggleImei(idx, imei.id)"
                                                                   class="w-3.5 h-3.5 accent-purple-600 cursor-pointer shrink-0">
                                                            <div class="flex-1 min-w-0">
                                                                <span class="font-mono text-xs font-semibold"
                                                                      :class="isSelected(idx, imei.id) ? 'text-purple-700' : 'text-gray-800'"
                                                                      x-text="imei.codigo_imei"></span>
                                                                <span x-show="imei.serie"
                                                                      class="text-[10px] text-gray-400 ml-1.5 font-mono"
                                                                      x-text="'S/N: ' + imei.serie"></span>
                                                            </div>
                                                            {{-- Etiqueta de variante: solo visible en "Todas las variantes",
                                                                 para distinguir de un vistazo de qué color/variante es cada IMEI --}}
                                                            <span x-show="!producto.varianteId && producto.variantes.length > 0"
                                                                  x-cloak
                                                                  class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full border border-gray-200 bg-white text-gray-500 shrink-0">
                                                                <span x-show="varianteDeImei(idx, imei)?.color_hex"
                                                                      class="w-2 h-2 rounded-full shrink-0"
                                                                      :style="'background-color:' + (varianteDeImei(idx, imei)?.color_hex ?? '#ccc')"></span>
                                                                <span x-text="varianteDeImei(idx, imei)?.nombre ?? '—'"></span>
                                                            </span>
                                                            <span x-show="isSelected(idx, imei.id)"
                                                                  class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold shrink-0">✓</span>
                                                        </label>
                                                    </template>
                                                    <div x-show="producto.imeiBusqueda && imeisFiltradosDe(idx).length === 0" x-cloak
                                                         class="px-3 py-3 text-center text-xs text-gray-400">
                                                        Sin resultados para "<span x-text="producto.imeiBusqueda"></span>"
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Chips de seleccionados --}}
                                            <div x-show="producto.imeisSeleccionados.length > 0" x-cloak
                                                 class="mt-2 flex flex-wrap gap-1">
                                                <template x-for="imeiId in producto.imeisSeleccionados" :key="imeiId">
                                                    <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-800 text-[10px] font-mono px-2 py-1 rounded-lg border border-purple-200">
                                                        <span x-show="producto.variantes.length > 0 && getVarianteDeImeiId(idx, imeiId)?.color_hex"
                                                              class="w-2 h-2 rounded-full shrink-0"
                                                              :style="'background-color:' + (getVarianteDeImeiId(idx, imeiId)?.color_hex ?? '#ccc')"></span>
                                                        <span x-text="getImeiCodigo(idx, imeiId)"></span>
                                                        <button type="button" @click="toggleImei(idx, imeiId)"
                                                                class="text-purple-400 hover:text-red-500 ml-0.5 leading-none">
                                                            <i class="fas fa-times text-[8px]"></i>
                                                        </button>
                                                    </span>
                                                </template>
                                            </div>

                                        </div>{{-- /x-show almacenId --}}

                                        {{-- Hidden inputs para IMEIs seleccionados --}}
                                        <template x-for="imeiId in producto.imeisSeleccionados" :key="imeiId">
                                            <input type="hidden"
                                                   :name="`productos[${idx}][imei_ids][]`"
                                                   :value="imeiId">
                                        </template>

                                    </div>{{-- /IMEI PICKER --}}

                                </div>{{-- /p-4 --}}
                            </div>{{-- /border row --}}
                        </template>

                        {{-- Botón agregar producto --}}
                        <button type="button"
                                @click="agregarProducto()"
                                class="w-full py-2.5 border-2 border-dashed border-blue-300 hover:border-blue-500 text-blue-500 hover:text-blue-700 text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-plus-circle"></i> Agregar otro producto
                        </button>

                    </div>{{-- /p-6 --}}
                </div>{{-- /card productos --}}

                {{-- ── Info box + Acciones ── --}}
                <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex gap-2 text-sm text-blue-800 mb-5">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500 shrink-0"></i>
                    <span>
                        El stock de <strong>accesorios</strong> se descuenta del origen al registrar.
                        Los <strong>IMEIs</strong> se marcan «En Tránsito» hasta que el destino confirme la recepción.
                    </span>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('traslados.index') }}"
                       class="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </a>
                    <button type="submit"
                            :disabled="!puedeEnviar()"
                            class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg flex items-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-arrow-right"></i>
                        <span x-text="'Registrar traslado (' + productos.length + ' prod.) → Guía de Remisión'"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>

<script>
function trasladoForm() {
    const imeisUrl           = '{{ route('traslados.imeis-disponibles') }}';
    const buscarProductosUrl = '{{ route('traslados.buscar-productos') }}';
    const guiaSeriesMap      = @json($guiaSeriesMap);

    return {
        almacenId:        '',
        almacenDestinoId: '',
        productos:        [],
        nextId:           1,

        // N° Guía (auto-filled desde la serie del almacén)
        numeroGuia:  '{{ old('numero_guia', '') }}',
        guiaSerieId: '{{ old('guia_serie_id', '') }}',

        init() {
            this.agregarProducto();
        },

        // ── Gestión del repeater ──────────────────────────────────────

        agregarProducto() {
            this.productos.push({
                _id:               this.nextId++,
                productoId:        '',
                nombre:            '',
                codigo:            '',
                esSerie:           false,
                stockOrigen:       null,
                stockTotal:        null,
                variantes:         [],
                cantidad:          1,
                varianteId:        null,
                imeisDisponibles:  [],
                imeisSeleccionados: [],
                imeiBusqueda:      '',
                imeisLoading:      false,
                busqueda:          '',
                resultados:        [],
                buscando:          false,
                timer:             null,
            });
        },

        eliminarProducto(idx) {
            if (this.productos.length > 1) {
                this.productos.splice(idx, 1);
            }
        },

        estaUsado(productoId, idx) {
            const item = this.productos[idx]?.resultados?.find(r => r.id == productoId);
            if (item && item.tiene_variantes) return false;
            return this.productos.some((p, i) => i !== idx && String(p.productoId) === String(productoId));
        },

        esDuplicado(idx) {
            const cur = this.productos[idx];
            if (!cur?.productoId) return false;
            const pid = String(cur.productoId);
            const vid = String(cur.varianteId ?? '');
            return this.productos.some((p, i) => {
                if (i === idx) return false;
                if (String(p.productoId) !== pid) return false;
                if (cur.variantes.length > 0) {
                    return vid && String(p.varianteId ?? '') === vid;
                }
                return true;
            });
        },

        // ── Eventos de selección ─────────────────────────────────────

        onAlmacenOrigenChange() {
            const serieData = guiaSeriesMap[this.almacenId];
            if (serieData) {
                this.numeroGuia  = serieData.numero;
                this.guiaSerieId = String(serieData.serie_id);
            } else {
                this.numeroGuia  = '';
                this.guiaSerieId = '';
            }
            this.productos.forEach(p => this.limpiarProducto(this.productos.indexOf(p)));
        },

        onAlmacenDestinoChange() {},

        // ── Búsqueda dinámica de productos ───────────────────────────

        buscarProducto(idx) {
            const p = this.productos[idx];
            clearTimeout(p.timer);
            if (p.busqueda.length < 2) { p.resultados = []; return; }
            p.timer = setTimeout(async () => {
                p.buscando = true;
                try {
                    const url  = `${buscarProductosUrl}?q=${encodeURIComponent(p.busqueda)}&almacen_id=${this.almacenId}`;
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    p.resultados = await resp.json();
                } catch {
                    p.resultados = [];
                } finally {
                    p.buscando = false;
                }
            }, 300);
        },

        seleccionarProducto(idx, item) {
            const p              = this.productos[idx];
            p.productoId         = item.id;
            p.nombre             = item.nombre;
            p.codigo             = item.codigo;
            p.esSerie            = item.es_serie;
            p.stockOrigen        = item.stock_origen;
            p.stockTotal         = item.stock_origen;
            p.variantes          = item.variantes ?? [];
            p.varianteId         = null;
            p.cantidad           = 1;
            p.imeisSeleccionados = [];
            p.imeisDisponibles   = [];
            p.imeiBusqueda       = '';
            p.resultados         = [];
            p.busqueda           = '';
            if (p.esSerie && this.almacenId) {
                this.cargarImeis(idx);
            }
        },

        seleccionarVariante(idx, variante) {
            const p = this.productos[idx];
            if (String(p.varianteId) === String(variante.id)) {
                p.varianteId = null;
                p.stockOrigen = p.stockTotal ?? p.stockOrigen;
                return;
            }
            p.varianteId = variante.id;
            p.stockOrigen = variante.stock;
            // No se re-consulta ni se limpia imeisSeleccionados: los IMEIs de
            // TODAS las variantes ya se cargaron una sola vez en cargarImeis()
            // (cada uno trae su variante_id), así que cambiar el filtro solo
            // cambia qué se ve en la lista — no borra lo ya marcado en otra
            // variante, para poder combinar IMEIs de varios colores/variantes.
        },

        limpiarProducto(idx) {
            const p              = this.productos[idx];
            p.productoId         = '';
            p.nombre             = '';
            p.codigo             = '';
            p.esSerie            = false;
            p.stockOrigen        = null;
            p.stockTotal         = null;
            p.variantes          = [];
            p.varianteId         = null;
            p.cantidad           = 1;
            p.imeisSeleccionados = [];
            p.imeisDisponibles   = [];
            p.imeiBusqueda       = '';
            p.resultados         = [];
            p.busqueda           = '';
        },

        // ── IMEI carga y gestión ─────────────────────────────────────

        async cargarImeis(idx) {
            const p = this.productos[idx];
            p.imeisLoading = true;
            try {
                // Sin variante_id: trae los IMEIs de TODAS las variantes de una
                // sola vez (cada uno con su propio variante_id). El filtro por
                // variante se aplica solo en el cliente (imeisFiltradosDe), así
                // se puede seleccionar de varias variantes sin perder lo marcado.
                const url  = `${imeisUrl}?producto_id=${p.productoId}&almacen_id=${this.almacenId}`;
                const resp = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.error || `HTTP ${resp.status}`);
                }
                p.imeisDisponibles = await resp.json();
            } catch (e) {
                p.imeisDisponibles = [];
                console.error('Error cargando IMEIs:', e.message);
            } finally {
                p.imeisLoading = false;
            }
        },

        imeisFiltradosDe(idx) {
            const p = this.productos[idx];
            if (!p) return [];
            let lista = p.varianteId
                ? p.imeisDisponibles.filter(i => String(i.variante_id) === String(p.varianteId))
                : p.imeisDisponibles;
            const q = p.imeiBusqueda.trim().toLowerCase();
            if (q) {
                lista = lista.filter(i =>
                    i.codigo_imei.toLowerCase().includes(q) ||
                    (i.serie && i.serie.toLowerCase().includes(q))
                );
            }
            return lista;
        },

        // Info de la variante (nombre / color) a la que pertenece un IMEI —
        // se usa para distinguir visualmente los IMEIs cuando se ve "Todas
        // las variantes" y hay seleccionados de más de un color/variante.
        varianteDeImei(idx, imei) {
            const p = this.productos[idx];
            if (!p || !imei?.variante_id) return null;
            return p.variantes.find(v => String(v.id) === String(imei.variante_id)) ?? null;
        },

        getVarianteDeImeiId(idx, imeiId) {
            const imei = this.productos[idx]?.imeisDisponibles.find(i => i.id === imeiId);
            return imei ? this.varianteDeImei(idx, imei) : null;
        },

        // El chip de filtro activo (producto.varianteId) ya no representa
        // necesariamente la variante de TODOS los IMEIs marcados (ahora se
        // puede mezclar). El valor que se envía al backend se calcula a partir
        // de lo realmente seleccionado: si todos son de la misma variante se
        // envía esa; si están mezclados se envía null (sin variante única).
        varianteIdEnvioDe(idx) {
            const p = this.productos[idx];
            if (!p) return null;
            if (!p.esSerie || p.imeisSeleccionados.length === 0) return p.varianteId ?? null;
            const variantesUsadas = new Set(p.imeisSeleccionados.map(id => {
                const imei = p.imeisDisponibles.find(i => i.id === id);
                return String(imei?.variante_id ?? '');
            }));
            return variantesUsadas.size === 1 ? [...variantesUsadas][0] : null;
        },

        toggleImei(idx, id) {
            const p   = this.productos[idx];
            const pos = p.imeisSeleccionados.indexOf(id);
            pos === -1 ? p.imeisSeleccionados.push(id) : p.imeisSeleccionados.splice(pos, 1);
        },

        isSelected(idx, id) {
            return this.productos[idx]?.imeisSeleccionados.includes(id) ?? false;
        },

        seleccionarTodos(idx) {
            const p        = this.productos[idx];
            const filtrados = this.imeisFiltradosDe(idx);
            const todos    = filtrados.every(i => p.imeisSeleccionados.includes(i.id));
            if (todos) {
                const fids = filtrados.map(i => i.id);
                p.imeisSeleccionados = p.imeisSeleccionados.filter(id => !fids.includes(id));
            } else {
                filtrados.forEach(i => {
                    if (!p.imeisSeleccionados.includes(i.id)) p.imeisSeleccionados.push(i.id);
                });
            }
        },

        getImeiCodigo(idx, id) {
            const imei = this.productos[idx]?.imeisDisponibles.find(i => i.id === id);
            return imei ? imei.codigo_imei : String(id);
        },

        // ── Validación global ────────────────────────────────────────

        puedeEnviar() {
            if (!this.almacenId || !this.almacenDestinoId) return false;
            if (this.almacenId === this.almacenDestinoId)   return false;
            if (this.productos.length === 0)                 return false;

            return this.productos.every((p, idx) => {
                if (!p.productoId)         return false;
                if (this.esDuplicado(idx)) return false;
                if (p.esSerie) return p.imeisSeleccionados.length > 0;
                // Accesorio con variantes: al ser por cantidad (sin IMEI que lo
                // identifique) sí se necesita fijar una única variante.
                if (p.variantes.length > 0 && !p.varianteId) return false;
                return p.cantidad >= 1 && (p.stockOrigen === null || p.stockOrigen >= p.cantidad);
            });
        },
    };
}
</script>
</body>
</html>
