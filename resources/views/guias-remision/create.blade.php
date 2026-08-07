<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Guía de Remisión</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header
        title="Nueva Guía de Remisión"
        subtitle="{{ $fromTraslado ? 'Paso 2 — completa los datos de transporte para el traslado ' . $fromTraslado : 'Emite una guía con movimiento de stock' }}"
    />

    <div class="flex flex-wrap gap-3 mb-6 text-sm">
        <a href="{{ route('guias-remision.index') }}" class="text-gray-500 hover:text-blue-700 flex items-center gap-1">
            <i class="fas fa-list"></i> Listado
        </a>
        <span class="text-gray-300">|</span>
        <span class="font-semibold text-blue-700 flex items-center gap-1">
            <i class="fas fa-plus-circle"></i> Nueva Guía
        </span>
    </div>

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-5 rounded-lg flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-5 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    @if($fromTraslado)
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex gap-3 text-sm text-blue-800 mb-5">
        <i class="fas fa-exchange-alt text-blue-500 mt-0.5 shrink-0"></i>
        <div>
            <p class="font-semibold">Traslado registrado: <span class="font-mono">{{ $fromTraslado }}</span></p>
            <p class="text-xs text-blue-600 mt-0.5">El stock ya fue movido. Aquí solo completas el documento de transporte (conductor, transportista, etc.).</p>
        </div>
    </div>
    @elseif(session('info'))
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex gap-2 text-sm text-blue-800 mb-5">
        <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
        {{ session('info') }}
    </div>
    @endif

    <div x-data="guiaForm()" x-init="init()">
        <form action="{{ route('guias-remision.store') }}" method="POST">
            @csrf
            @if($fromTraslado)
                <input type="hidden" name="from_traslado" value="{{ $fromTraslado }}">
            @endif

            {{-- ══ 1. CABECERA ══ --}}
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-blue-900 to-blue-700 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-file-invoice text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Datos de la Guía</h2>
                        <p class="text-blue-200 text-xs">Número, motivo y fecha de traslado</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- N° Guía --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-hashtag mr-1 text-blue-400"></i>N° Guía
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="numeroGuia" readonly
                                       placeholder="Se genera automáticamente"
                                       class="flex-1 px-3 py-2.5 text-sm border border-gray-200 rounded-lg font-mono uppercase bg-gray-50 text-gray-600 cursor-not-allowed">
                                <span x-show="guiaSerieId" x-cloak class="text-xs text-emerald-600 font-medium whitespace-nowrap">
                                    <i class="fas fa-check-circle"></i> Auto-generado
                                </span>
                            </div>
                            <input type="hidden" name="guia_serie_id" x-model="guiaSerieId">
                        </div>

                        {{-- Motivo --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-tag mr-1 text-blue-400"></i>Motivo de Traslado *
                            </label>
                            @php $motivoDefault = old('motivo_traslado', $prefill['motivo_traslado'] ?? 'VENTA'); @endphp
                            <select name="motivo_traslado" required
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="VENTA"                    {{ $motivoDefault === 'VENTA' ? 'selected':'' }}>Venta</option>
                                <option value="CONSIGNACION"             {{ $motivoDefault === 'CONSIGNACION' ? 'selected':'' }}>Consignación</option>
                                <option value="TRASLADO_ENTRE_ALMACENES" {{ $motivoDefault === 'TRASLADO_ENTRE_ALMACENES' ? 'selected':'' }}>Traslado entre almacenes</option>
                                <option value="COMPRA"                   {{ $motivoDefault === 'COMPRA' ? 'selected':'' }}>Compra</option>
                                <option value="DEVOLUCION"               {{ $motivoDefault === 'DEVOLUCION' ? 'selected':'' }}>Devolución</option>
                                <option value="IMPORTACION"              {{ $motivoDefault === 'IMPORTACION' ? 'selected':'' }}>Importación</option>
                                <option value="OTRO"                     {{ $motivoDefault === 'OTRO' ? 'selected':'' }}>Otro</option>
                            </select>
                        </div>

                        {{-- Fecha --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-calendar mr-1 text-blue-400"></i>Fecha de Traslado *
                            </label>
                            <input type="date" name="fecha_traslado" required
                                   value="{{ old('fecha_traslado', now()->format('Y-m-d')) }}"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ 2. ALMACÉN ORIGEN ══ --}}
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-orange-700 to-orange-500 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-warehouse text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Almacén Origen</h2>
                        <p class="text-orange-200 text-xs">Desde dónde salen los productos</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                            Almacén Origen *
                        </label>
                        <select name="almacen_id" required x-model="almacenId" @change="onAlmacenOrigenChange()"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($almacenes as $alm)
                                <option value="{{ $alm->id }}" {{ old('almacen_id', $prefill['almacen_id'] ?? '') == $alm->id ? 'selected':'' }}>{{ $alm->nombre }}</option>
                            @endforeach
                        </select>
                        @error('almacen_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                            Dirección de Partida
                        </label>
                        <input type="text" name="direccion_partida"
                               value="{{ old('direccion_partida') }}"
                               @input="$el.dataset.userEdited = '1'"
                               placeholder="Se rellena automático"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Ubigeo Partida</label>
                        <input type="text" name="ubigeo_partida" maxlength="6"
                               value="{{ old('ubigeo_partida') }}"
                               @input="$el.dataset.userEdited = '1'"
                               placeholder="150101"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 font-mono">
                    </div>
                </div>
            </div>

            {{-- ══ 3. DESTINO ══ --}}
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-teal-800 to-teal-600 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-map-marker-alt text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Destino</h2>
                        <p class="text-teal-200 text-xs">Almacén interno, cliente, proveedor o dirección libre</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">

                    {{-- Tipo destino --}}
                    <input type="hidden" name="tipo_destino" :value="tipoDestino">
                    <div class="flex flex-wrap gap-2">
                        @foreach(['almacen' => ['fas fa-warehouse','Almacén interno'], 'cliente' => ['fas fa-user','Cliente'], 'proveedor' => ['fas fa-industry','Proveedor'], 'libre' => ['fas fa-map-marker-alt','Dirección libre']] as $val => [$icon, $label])
                        <label class="cursor-pointer">
                            <input type="radio" value="{{ $val }}" x-model="tipoDestino" @change="onTipoDestinoChange()" class="sr-only">
                            <div class="px-4 py-2 border-2 rounded-xl text-sm font-medium transition-all flex items-center gap-2"
                                 :class="tipoDestino === '{{ $val }}' ? 'border-teal-600 bg-teal-50 text-teal-700' : 'border-gray-200 text-gray-600 hover:border-teal-300'">
                                <i class="{{ $icon }}"></i> {{ $label }}
                            </div>
                        </label>
                        @endforeach
                    </div>

                    {{-- Almacén destino --}}
                    <div x-show="tipoDestino === 'almacen'" x-cloak>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Almacén Destino *</label>
                        <select name="almacen_destino_id" x-model="almacenDestinoId" @change="onAlmacenDestinoChange()"
                                :required="tipoDestino === 'almacen'"
                                class="w-full sm:w-72 px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($almacenes as $alm)
                                <option value="{{ $alm->id }}" {{ old('almacen_destino_id', $prefill['almacen_destino_id'] ?? '') == $alm->id ? 'selected':'' }}>{{ $alm->nombre }}</option>
                            @endforeach
                        </select>
                        <p x-show="almacenId && almacenDestinoId && almacenId === almacenDestinoId" x-cloak
                           class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Origen y destino no pueden ser iguales</p>
                        @error('almacen_destino_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Cliente / Proveedor buscador --}}
                    <div x-show="tipoDestino === 'cliente' || tipoDestino === 'proveedor'" x-cloak class="relative">
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                            <span x-text="tipoDestino === 'cliente' ? 'Buscar cliente (nombre o documento)' : 'Buscar proveedor (razón social o RUC)'"></span> *
                        </label>
                        <input type="text" x-model="destinatarioBuscar"
                               @input="buscarDestinatarioAjax()"
                               @keydown.escape="destinatarioResultados = []"
                               placeholder="Mínimo 2 caracteres..."
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <div x-show="destinatarioSeleccionado" x-cloak
                             class="mt-2 flex items-center gap-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg text-sm">
                            <i class="fas fa-check-circle text-teal-500"></i>
                            <span class="flex-1 font-medium text-teal-800" x-text="destinatarioSeleccionado?.nombre"></span>
                            <button type="button" @click="destinatarioSeleccionado=null;destinatarioBuscar='';clienteId='';proveedorId=''"
                                    class="text-teal-400 hover:text-red-500"><i class="fas fa-times text-xs"></i></button>
                        </div>
                        <div x-show="destinatarioResultados.length > 0" x-cloak
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                            <template x-for="item in destinatarioResultados" :key="item.id">
                                <button type="button" @click="seleccionarDestinatario(item)"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-teal-50 border-b border-gray-50 last:border-0">
                                    <span class="font-medium text-gray-800" x-text="item.nombre"></span>
                                    <span class="text-xs text-gray-400 ml-2 font-mono" x-text="item.ruc ?? item.documento"></span>
                                </button>
                            </template>
                        </div>
                        <input type="hidden" name="cliente_id"   :value="clienteId">
                        <input type="hidden" name="proveedor_id" :value="proveedorId">
                        @error('cliente_id')   <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @error('proveedor_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Dirección llegada --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Dirección de Llegada
                            </label>
                            <input type="text" name="direccion_llegada"
                                   value="{{ old('direccion_llegada') }}"
                                   @input="$el.dataset.userEdited = '1'"
                                   placeholder="Dirección del destino"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Ubigeo Llegada</label>
                            <input type="text" name="ubigeo_llegada" maxlength="6"
                                   value="{{ old('ubigeo_llegada') }}"
                                   @input="$el.dataset.userEdited = '1'"
                                   placeholder="150101"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 font-mono">
                        </div>
                    </div>

                </div>
            </div>

            {{-- ══ 4. TRANSPORTE ══ --}}
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-emerald-900 to-emerald-700 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-truck text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Transporte</h2>
                        <p class="text-emerald-200 text-xs">Modalidad, conductor y vehículo</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">

                    {{-- Modalidad + Peso + Bultos --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Modalidad *</label>
                            <select name="modalidad" required x-model="modalidad" @change="onModalidadChange()"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 bg-white">
                                <option value="privado">Transporte Privado (propio)</option>
                                <option value="publico">Transporte Público (tercero)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-weight-hanging mr-1 text-gray-400"></i>Peso Total (kg)
                            </label>
                            <input type="number" step="0.01" min="0" name="peso_total"
                                   value="{{ old('peso_total') }}" placeholder="0.00"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-boxes mr-1 text-gray-400"></i>N° Bultos
                            </label>
                            <input type="number" min="1" name="bultos"
                                   value="{{ old('bultos') }}" placeholder="1"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    {{-- Transportista (público) --}}
                    <div x-show="modalidad === 'publico'" x-cloak class="border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            <i class="fas fa-id-card mr-1 text-blue-400"></i>Datos del Transportista
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Tipo Doc.</label>
                                <select name="transportista_tipo_doc" x-model="transpTipoDoc"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 bg-white">
                                    <option value="RUC">RUC</option>
                                    <option value="DNI">DNI</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">N° Documento</label>
                                <div class="flex gap-2">
                                    <input type="text" name="transportista_doc" x-model="transpDoc"
                                           maxlength="11" placeholder="RUC o DNI"
                                           @keydown.enter.prevent="buscarTransportista()"
                                           class="flex-1 px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono">
                                    <button type="button" @click="buscarTransportista()"
                                            :disabled="transpBuscando || !transpDoc"
                                            class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded-lg disabled:opacity-50">
                                        <i class="fas" :class="transpBuscando ? 'fa-spinner fa-spin' : 'fa-search'"></i>
                                    </button>
                                </div>
                                <p x-show="transpError" x-text="transpError" x-cloak class="text-xs text-amber-600 mt-1"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nombre / Razón Social</label>
                                <input type="text" name="transportista_nombre" x-model="transpNombre"
                                       maxlength="200" placeholder="Auto o manual"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    {{-- Conductor (privado): en público no sabemos quién conduce, solo
                         importa el transportista (empresa) que se encarga del traslado --}}
                    <div x-show="modalidad === 'privado'" x-cloak class="border-t border-gray-100 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <i class="fas fa-user-tie mr-1 text-purple-400"></i>Datos del Conductor
                            </p>
                            @if($ultimoConductor)
                            <button type="button" @click="restaurarUltimoConductor()"
                                    class="text-xs text-purple-600 hover:text-purple-800 flex items-center gap-1">
                                <i class="fas fa-history"></i> Usar último conductor
                            </button>
                            @endif
                        </div>
                        @if($ultimoConductor)
                        <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-purple-50 border border-purple-100 rounded-xl text-sm">
                            <i class="fas fa-user-check text-purple-400 shrink-0"></i>
                            <div class="flex-1 text-purple-800">
                                Último: <strong>{{ $ultimoConductor->conductor_nombre }}</strong>
                                &middot; DNI <span class="font-mono">{{ $ultimoConductor->conductor_dni }}</span>
                                @if($ultimoConductor->placa_vehiculo)
                                    &middot; <span class="font-mono font-semibold">{{ $ultimoConductor->placa_vehiculo }}</span>
                                @endif
                            </div>
                        </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">DNI</label>
                                <div class="flex gap-2">
                                    <input type="text" name="conductor_dni" x-model="condDni" maxlength="8" placeholder="DNI"
                                           @keydown.enter.prevent="buscarConductor()"
                                           class="flex-1 px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono">
                                    <button type="button" @click="buscarConductor()" :disabled="condBuscando || condDni.length !== 8"
                                            class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs rounded-lg disabled:opacity-50">
                                        <i class="fas" :class="condBuscando ? 'fa-spinner fa-spin' : 'fa-search'"></i>
                                    </button>
                                </div>
                                <p x-show="condError" x-text="condError" x-cloak class="text-xs text-amber-600 mt-1"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nombre</label>
                                <input type="text" name="conductor_nombre" x-model="condNombre" maxlength="200"
                                       placeholder="Apellidos y nombres"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Licencia</label>
                                <input type="text" name="conductor_licencia" x-model="condLicencia" maxlength="20"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                <i class="fas fa-car mr-1 text-gray-400"></i>Placa del Vehículo
                            </label>
                            <input type="text" name="placa_vehiculo" x-model="condPlaca" maxlength="20"
                                   @input="condPlaca = condPlaca.toUpperCase()"
                                   placeholder="ABC-123"
                                   class="w-full sm:w-48 px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono uppercase">
                        </div>
                    </div>

                </div>
            </div>

            {{-- ══ 5. PRODUCTOS ══ --}}
            @if($fromTraslado)
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-purple-900 to-purple-700 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-boxes text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Productos</h2>
                        <p class="text-purple-200 text-xs">Se importan automáticamente del traslado</p>
                    </div>
                </div>
                <div class="p-5 flex items-center gap-3 text-sm text-purple-700 bg-purple-50 border-t border-purple-100">
                    <i class="fas fa-check-circle text-purple-400 shrink-0 text-lg"></i>
                    <div>
                        <p class="font-semibold">Productos ya registrados en el traslado <span class="font-mono">{{ $fromTraslado }}</span></p>
                        <p class="text-xs text-purple-500 mt-0.5">Los ítems y cantidades del movimiento de stock se incluirán automáticamente en la guía.</p>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-5">
                <div class="bg-linear-to-r from-purple-900 to-purple-700 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-boxes text-white text-xl"></i></div>
                        <div>
                            <h2 class="text-base font-bold text-white">Productos</h2>
                            <p class="text-purple-200 text-xs">Productos incluidos en esta guía</p>
                        </div>
                    </div>
                    <span class="bg-white/20 text-white text-sm font-bold px-3 py-1 rounded-full"
                          x-text="productos.length + ' prod.'"></span>
                </div>

                <div class="p-6 space-y-4">

                    <div x-show="!almacenId"
                         class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 px-4 py-3 rounded-lg">
                        <i class="fas fa-info-circle shrink-0"></i> Selecciona el almacén origen primero.
                    </div>

                    <template x-for="(producto, idx) in productos" :key="producto._id">
                        <div class="border border-gray-200 rounded-xl overflow-hidden"
                             :class="esDuplicado(idx) ? 'border-red-300 bg-red-50' : 'bg-gray-50/40'">

                            <div class="flex items-center justify-between px-4 py-2.5 bg-white border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-500 uppercase">Producto <span x-text="idx+1"></span></span>
                                <div class="flex items-center gap-2">
                                    <span x-show="producto.esSerie" x-cloak
                                          class="text-[10px] font-semibold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                                        <i class="fas fa-barcode"></i> IMEI
                                    </span>
                                    <button type="button" @click="eliminarProducto(idx)" x-show="productos.length > 1"
                                            class="text-gray-400 hover:text-red-500 p-1 rounded-lg hover:bg-red-50">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="p-4 space-y-3">

                                {{-- Buscador dinámico --}}
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        <i class="fas fa-box mr-1 text-blue-400"></i>Producto *
                                    </label>
                                    <div x-show="producto.productoId" x-cloak
                                         class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                                        <i class="fas fa-check-circle text-blue-500 text-xs shrink-0"></i>
                                        <span class="flex-1 text-sm font-medium text-blue-800" x-text="producto.nombre"></span>
                                        <span class="text-[10px] text-blue-400 font-mono" x-text="producto.codigo"></span>
                                        <span x-show="producto.esSerie" class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold">IMEI</span>
                                        <button type="button" @click="limpiarProducto(idx)" class="text-blue-300 hover:text-red-500 ml-1">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    </div>
                                    <div x-show="!producto.productoId" class="relative">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs pointer-events-none"></i>
                                            <input type="text" x-model="producto.busqueda"
                                                   @input="buscarProducto(idx)"
                                                   @keydown.escape="producto.resultados = []"
                                                   :placeholder="almacenId ? 'Buscar por nombre o código...' : 'Selecciona almacén origen primero'"
                                                   :disabled="!almacenId"
                                                   class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                            <div x-show="producto.buscando" class="absolute right-3 top-2.5">
                                                <i class="fas fa-spinner fa-spin text-gray-400 text-xs"></i>
                                            </div>
                                        </div>
                                        <div x-show="producto.resultados.length > 0" x-cloak
                                             class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-52 overflow-y-auto">
                                            <template x-for="item in producto.resultados" :key="item.id">
                                                <button type="button" @click="seleccionarProducto(idx, item)"
                                                        :disabled="estaUsado(item.id, idx)"
                                                        class="w-full text-left px-4 py-2.5 text-sm border-b border-gray-50 last:border-0 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        :class="estaUsado(item.id, idx) ? 'bg-gray-50' : 'hover:bg-blue-50'">
                                                    <div class="flex items-center gap-2">
                                                        <span class="flex-1 font-medium text-gray-800" x-text="item.nombre"></span>
                                                        <span class="text-[10px] font-mono text-gray-400" x-text="item.codigo"></span>
                                                        <span x-show="item.es_serie" class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold shrink-0">IMEI</span>
                                                    </div>
                                                    <span class="text-[10px]" :class="item.stock_origen > 0 ? 'text-green-600' : 'text-red-500'"
                                                          x-text="'Stock: ' + item.stock_origen + (item.es_serie ? ' IMEIs' : ' unid.')"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <input type="hidden" :name="`productos[${idx}][producto_id]`" :value="producto.productoId">
                                    <p x-show="esDuplicado(idx)" x-cloak class="text-xs text-red-500 mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> Producto duplicado.
                                    </p>
                                </div>

                                {{-- Stock badge --}}
                                <div x-show="producto.productoId && producto.stockOrigen !== null" x-cloak>
                                    <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                                          :class="producto.stockOrigen > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'">
                                        <i class="fas fa-cubes mr-1"></i>Disponible: <strong x-text="producto.stockOrigen"></strong>
                                        <span x-show="producto.esSerie"> IMEIs</span>
                                        <span x-show="!producto.esSerie"> unid.</span>
                                    </span>
                                </div>

                                {{-- Variante (accesorio) --}}
                                <div x-show="producto.productoId && !producto.esSerie && producto.variantes.length > 0" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        <i class="fas fa-palette mr-1 text-pink-400"></i>Variante
                                    </label>
                                    <select :name="`productos[${idx}][variante_id]`" x-model="producto.varianteId"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option :value="null">— Sin variante —</option>
                                        <template x-for="v in producto.variantes" :key="v.id">
                                            <option :value="v.id" x-text="v.nombre + (v.sku ? ' (' + v.sku + ')' : '')"></option>
                                        </template>
                                    </select>
                                </div>

                                {{-- Variante filtro (serie) --}}
                                <div x-show="producto.productoId && producto.esSerie && producto.variantes.length > 0" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        <i class="fas fa-palette mr-1 text-pink-400"></i>Filtrar variante
                                    </label>
                                    <select x-model="producto.varianteId" @change="recargarImeis(idx)"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option :value="null">— Todas —</option>
                                        <template x-for="v in producto.variantes" :key="v.id">
                                            <option :value="v.id" x-text="v.nombre + (v.sku ? ' (' + v.sku + ')' : '')"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" :name="`productos[${idx}][variante_id]`" :value="producto.varianteId ?? ''">
                                </div>

                                {{-- Cantidad (accesorio) --}}
                                <div x-show="producto.productoId && !producto.esSerie" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Cantidad *</label>
                                    <input type="number" :name="`productos[${idx}][cantidad]`"
                                           x-model.number="producto.cantidad" min="1"
                                           :max="producto.stockOrigen ?? undefined"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <p x-show="producto.stockOrigen !== null && producto.cantidad > producto.stockOrigen" x-cloak
                                       class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Supera el stock disponible.</p>
                                </div>

                                {{-- IMEI picker (serie) --}}
                                <div x-show="producto.productoId && producto.esSerie" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                        <i class="fas fa-barcode mr-1 text-purple-500"></i>IMEIs *
                                    </label>
                                    <div x-show="almacenId" x-cloak>
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="relative flex-1">
                                                <i class="fas fa-search absolute left-2.5 top-2.5 text-[10px] text-gray-400 pointer-events-none"></i>
                                                <input type="text" x-model="producto.imeiBusqueda" placeholder="Buscar IMEI..."
                                                       class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-400">
                                            </div>
                                            <div class="shrink-0 text-xs bg-purple-50 border border-purple-200 text-purple-700 font-mono rounded-lg px-2.5 py-1.5">
                                                <span class="font-bold" x-text="producto.imeisSeleccionados.length"></span>/<span x-text="producto.imeisDisponibles.length"></span>
                                            </div>
                                        </div>
                                        <div x-show="producto.imeisLoading" class="py-4 text-center text-xs text-gray-400">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>Cargando...
                                        </div>
                                        <div x-show="!producto.imeisLoading && producto.imeisDisponibles.length === 0" x-cloak
                                             class="py-4 text-center text-xs text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-lg">
                                            <i class="fas fa-box-open text-gray-300 text-xl block mb-1"></i>Sin IMEIs disponibles
                                        </div>
                                        <div x-show="!producto.imeisLoading && producto.imeisDisponibles.length > 0" x-cloak
                                             class="border border-gray-200 rounded-lg overflow-hidden">
                                            <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-100 sticky top-0">
                                                <button type="button" @click="seleccionarTodos(idx)"
                                                        class="text-xs font-semibold text-purple-600 hover:text-purple-800">
                                                    <i class="fas fa-check-double text-[10px]"></i>
                                                    <span x-text="imeisFiltrados(idx).length > 0 && imeisFiltrados(idx).every(i => producto.imeisSeleccionados.includes(i.id)) ? 'Deseleccionar todos' : 'Seleccionar todos'"></span>
                                                </button>
                                            </div>
                                            <div class="max-h-44 overflow-y-auto divide-y divide-gray-100">
                                                <template x-for="imei in imeisFiltrados(idx)" :key="imei.id">
                                                    <label :for="`imei-${producto._id}-${imei.id}`"
                                                           class="flex items-center gap-2.5 px-3 py-2 cursor-pointer transition-colors"
                                                           :class="isSelected(idx, imei.id) ? 'bg-purple-50 hover:bg-purple-100' : 'hover:bg-gray-50'">
                                                        <input type="checkbox" :id="`imei-${producto._id}-${imei.id}`"
                                                               :checked="isSelected(idx, imei.id)" @change="toggleImei(idx, imei.id)"
                                                               class="w-3.5 h-3.5 accent-purple-600 cursor-pointer shrink-0">
                                                        <span class="font-mono text-xs font-semibold"
                                                              :class="isSelected(idx, imei.id) ? 'text-purple-700' : 'text-gray-800'"
                                                              x-text="imei.codigo_imei"></span>
                                                        <span x-show="imei.serie" class="text-[10px] text-gray-400 font-mono ml-1" x-text="'S/N: ' + imei.serie"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                        <div x-show="producto.imeisSeleccionados.length > 0" x-cloak class="mt-2 flex flex-wrap gap-1">
                                            <template x-for="imeiId in producto.imeisSeleccionados" :key="imeiId">
                                                <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-800 text-[10px] font-mono px-2 py-1 rounded-lg border border-purple-200">
                                                    <span x-text="getImeiCodigo(idx, imeiId)"></span>
                                                    <button type="button" @click="toggleImei(idx, imeiId)" class="text-purple-400 hover:text-red-500 ml-0.5">
                                                        <i class="fas fa-times text-[8px]"></i>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                    <template x-for="imeiId in producto.imeisSeleccionados" :key="imeiId">
                                        <input type="hidden" :name="`productos[${idx}][imei_ids][]`" :value="imeiId">
                                    </template>
                                </div>

                                {{-- Descripción --}}
                                <div x-show="producto.productoId" x-cloak>
                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        Descripción <span class="text-gray-400 font-normal normal-case">(opcional)</span>
                                    </label>
                                    <input type="text" :name="`productos[${idx}][descripcion]`"
                                           x-model="producto.descripcion" maxlength="300"
                                           placeholder="Descripción adicional para la guía"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>

                            </div>
                        </div>
                    </template>

                    <button type="button" @click="agregarProducto()"
                            class="w-full py-2.5 border-2 border-dashed border-blue-300 hover:border-blue-500 text-blue-500 hover:text-blue-700 text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-plus-circle"></i> Agregar otro producto
                    </button>
                </div>
            </div>
            @endif

            {{-- Acciones --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('guias-remision.index') }}"
                   class="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit" :disabled="!puedeEnviar()"
                        class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg flex items-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane"></i> Emitir Guía
                </button>
            </div>

        </form>
    </div>
</div>

<script>
function guiaForm() {
    const imeisUrl              = '{{ route('traslados.imeis-disponibles') }}';
    const buscarProductosUrl    = '{{ route('traslados.buscar-productos') }}';
    const buscarDestinatarioUrl = '{{ route('guias-remision.buscar-destinatario') }}';
    const guiaSeriesMap         = @json($guiaSeriesMap);
    const almacenesAddress      = @json($almacenesAddressMap);
    const isFromTraslado        = {{ $fromTraslado ? 'true' : 'false' }};
    const prefillData           = @json($prefill);
    const dniUrl = '{{ route('guias-remision.api.dni', ['dni' => '__DNI__']) }}';
    const rucUrl = '{{ route('guias-remision.api.ruc', ['ruc' => '__RUC__']) }}';
    @php
        $conductorData = [
            'dni'      => $ultimoConductor?->conductor_dni      ?? '',
            'nombre'   => $ultimoConductor?->conductor_nombre   ?? '',
            'licencia' => $ultimoConductor?->conductor_licencia ?? '',
            'placa'    => $ultimoConductor?->placa_vehiculo     ?? '',
        ];
    @endphp
    const ultimoConductor = @json($conductorData);

    return {
        almacenId:        '',
        almacenDestinoId: '',
        tipoDestino:      '{{ old('tipo_destino', $prefill['tipo_destino'] ?? 'cliente') }}',
        numeroGuia:       '{{ old('numero_guia', $prefill['numero_guia'] ?? '') }}',
        guiaSerieId:      '{{ old('guia_serie_id', '') }}',
        modalidad:        '{{ old('modalidad', 'privado') }}',
        productos:        [],
        nextId:           1,

        // Destinatario
        destinatarioBuscar:       '',
        destinatarioResultados:   [],
        destinatarioSeleccionado: null,
        destinatarioBuscando:     false,
        destinatarioTimer:        null,
        clienteId:                '{{ old('cliente_id', '') }}',
        proveedorId:              '{{ old('proveedor_id', '') }}',

        // Transportista
        transpTipoDoc: '{{ old('transportista_tipo_doc', 'RUC') }}',
        transpDoc:     '{{ old('transportista_doc', '') }}',
        transpNombre:  '{{ old('transportista_nombre', '') }}',
        transpBuscando: false,
        transpError:    '',

        // Conductor
        condDni:      '{{ old('conductor_dni', $ultimoConductor?->conductor_dni ?? '') }}',
        condNombre:   '{{ old('conductor_nombre', $ultimoConductor?->conductor_nombre ?? '') }}',
        condLicencia: '{{ old('conductor_licencia', $ultimoConductor?->conductor_licencia ?? '') }}',
        condPlaca:    '{{ old('placa_vehiculo', $ultimoConductor?->placa_vehiculo ?? '') }}',
        condBuscando: false,
        condError:    '',

        init() {
            if (isFromTraslado) {
                this.almacenId        = String(prefillData.almacen_id        || '');
                this.almacenDestinoId = String(prefillData.almacen_destino_id || '');
                this.tipoDestino      = 'almacen';
                this.numeroGuia       = prefillData.numero_guia || '';
                this.$nextTick(() => {
                    const orAddr = almacenesAddress[this.almacenId];
                    if (orAddr) {
                        const d = document.querySelector('input[name="direccion_partida"]');
                        const u = document.querySelector('input[name="ubigeo_partida"]');
                        if (d && !d.dataset.userEdited) d.value = orAddr.direccion ?? '';
                        if (u && !u.dataset.userEdited) u.value = orAddr.ubigeo   ?? '';
                    }
                    const destAddr = almacenesAddress[this.almacenDestinoId];
                    if (destAddr) {
                        const d = document.querySelector('input[name="direccion_llegada"]');
                        const u = document.querySelector('input[name="ubigeo_llegada"]');
                        if (d && !d.dataset.userEdited) d.value = destAddr.direccion ?? '';
                        if (u && !u.dataset.userEdited) u.value = destAddr.ubigeo   ?? '';
                    }
                });
            } else {
                this.agregarProducto();
            }
        },

        agregarProducto() {
            this.productos.push({
                _id: this.nextId++,
                productoId: '', nombre: '', codigo: '', esSerie: false,
                stockOrigen: null, variantes: [], varianteId: null,
                cantidad: 1, descripcion: '',
                imeisDisponibles: [], imeisSeleccionados: [], imeiBusqueda: '', imeisLoading: false,
                busqueda: '', resultados: [], buscando: false, timer: null,
            });
        },

        eliminarProducto(idx) {
            if (this.productos.length > 1) this.productos.splice(idx, 1);
        },

        estaUsado(id, idx) {
            return this.productos.some((p, i) => i !== idx && String(p.productoId) === String(id));
        },

        esDuplicado(idx) {
            const pid = String(this.productos[idx]?.productoId ?? '');
            if (!pid) return false;
            return this.productos.filter((p, i) => i !== idx && String(p.productoId) === pid).length > 0;
        },

        onAlmacenOrigenChange() {
            const serieData = guiaSeriesMap[this.almacenId];
            if (serieData) {
                this.numeroGuia  = serieData.numero;
                this.guiaSerieId = String(serieData.serie_id);
            } else {
                this.numeroGuia  = '';
                this.guiaSerieId = '';
            }
            const addr = almacenesAddress[this.almacenId];
            if (addr) {
                const d = document.querySelector('input[name="direccion_partida"]');
                const u = document.querySelector('input[name="ubigeo_partida"]');
                if (d && !d.dataset.userEdited) d.value = addr.direccion ?? '';
                if (u && !u.dataset.userEdited) u.value = addr.ubigeo   ?? '';
            }
            this.productos.forEach((_, i) => this.limpiarProducto(i));
        },

        onAlmacenDestinoChange() {
            const addr = almacenesAddress[this.almacenDestinoId];
            if (addr) {
                const d = document.querySelector('input[name="direccion_llegada"]');
                const u = document.querySelector('input[name="ubigeo_llegada"]');
                if (d && !d.dataset.userEdited) d.value = addr.direccion ?? '';
                if (u && !u.dataset.userEdited) u.value = addr.ubigeo   ?? '';
            }
        },

        onTipoDestinoChange() {
            this.destinatarioBuscar       = '';
            this.destinatarioResultados   = [];
            this.destinatarioSeleccionado = null;
            this.clienteId   = '';
            this.proveedorId = '';
            this.almacenDestinoId = '';
        },

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
                } catch { p.resultados = []; }
                finally { p.buscando = false; }
            }, 300);
        },

        seleccionarProducto(idx, item) {
            const p = this.productos[idx];
            p.productoId = item.id; p.nombre = item.nombre; p.codigo = item.codigo;
            p.esSerie = item.es_serie; p.stockOrigen = item.stock_origen;
            p.variantes = item.variantes ?? []; p.varianteId = null;
            p.cantidad = 1; p.imeisSeleccionados = []; p.imeisDisponibles = [];
            p.resultados = []; p.busqueda = '';
            if (p.esSerie && this.almacenId) this.cargarImeis(idx);
        },

        limpiarProducto(idx) {
            const p = this.productos[idx];
            p.productoId = ''; p.nombre = ''; p.codigo = ''; p.esSerie = false;
            p.stockOrigen = null; p.variantes = []; p.varianteId = null;
            p.cantidad = 1; p.imeisSeleccionados = []; p.imeisDisponibles = [];
            p.resultados = []; p.busqueda = '';
        },

        recargarImeis(idx) {
            const p = this.productos[idx];
            p.imeisSeleccionados = []; p.imeisDisponibles = []; p.imeiBusqueda = '';
            if (p.esSerie && this.almacenId) this.cargarImeis(idx);
        },

        async cargarImeis(idx) {
            const p = this.productos[idx];
            p.imeisLoading = true;
            try {
                const varParam = p.varianteId ? `&variante_id=${p.varianteId}` : '';
                const url = `${imeisUrl}?producto_id=${p.productoId}&almacen_id=${this.almacenId}${varParam}`;
                const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) throw new Error();
                p.imeisDisponibles = await resp.json();
            } catch { p.imeisDisponibles = []; }
            finally { p.imeisLoading = false; }
        },

        imeisFiltrados(idx) {
            const p = this.productos[idx];
            if (!p?.imeiBusqueda.trim()) return p?.imeisDisponibles ?? [];
            const q = p.imeiBusqueda.toLowerCase();
            return p.imeisDisponibles.filter(i =>
                i.codigo_imei.toLowerCase().includes(q) ||
                (i.serie && i.serie.toLowerCase().includes(q))
            );
        },

        toggleImei(idx, id) {
            const p = this.productos[idx];
            const pos = p.imeisSeleccionados.indexOf(id);
            pos === -1 ? p.imeisSeleccionados.push(id) : p.imeisSeleccionados.splice(pos, 1);
        },

        isSelected(idx, id) { return this.productos[idx]?.imeisSeleccionados.includes(id) ?? false; },

        seleccionarTodos(idx) {
            const p = this.productos[idx];
            const filtrados = this.imeisFiltrados(idx);
            const todos = filtrados.every(i => p.imeisSeleccionados.includes(i.id));
            if (todos) {
                const fids = filtrados.map(i => i.id);
                p.imeisSeleccionados = p.imeisSeleccionados.filter(id => !fids.includes(id));
            } else {
                filtrados.forEach(i => { if (!p.imeisSeleccionados.includes(i.id)) p.imeisSeleccionados.push(i.id); });
            }
        },

        getImeiCodigo(idx, id) {
            const imei = this.productos[idx]?.imeisDisponibles.find(i => i.id === id);
            return imei ? imei.codigo_imei : String(id);
        },

        async buscarConductor() {
            if (this.condDni.length !== 8) return;
            this.condBuscando = true; this.condError = '';
            try {
                const resp = await fetch(dniUrl.replace('__DNI__', this.condDni), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                if (resp.ok && data.nombre) this.condNombre = data.nombre;
                else this.condError = data.error ?? 'No encontrado.';
            } catch { this.condError = 'Sin conexión RENIEC.'; }
            finally { this.condBuscando = false; }
        },

        async buscarTransportista() {
            if (!this.transpDoc) return;
            this.transpBuscando = true; this.transpError = '';
            try {
                const url = this.transpTipoDoc === 'RUC'
                    ? rucUrl.replace('__RUC__', this.transpDoc)
                    : dniUrl.replace('__DNI__', this.transpDoc);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                if (resp.ok && data.nombre) this.transpNombre = data.nombre;
                else this.transpError = data.error ?? 'No encontrado.';
            } catch { this.transpError = 'Sin conexión.'; }
            finally { this.transpBuscando = false; }
        },

        restaurarUltimoConductor() {
            this.condDni = ultimoConductor.dni; this.condNombre = ultimoConductor.nombre;
            this.condLicencia = ultimoConductor.licencia; this.condPlaca = ultimoConductor.placa;
            this.condError = '';
        },

        // Al cambiar de modalidad se limpia el bloque que deja de aplicar, para
        // no enviar al guardar datos de conductor/vehículo en una guía pública
        // (donde no se conoce al chofer) ni datos de transportista en una privada.
        onModalidadChange() {
            if (this.modalidad === 'publico') {
                this.condDni = ''; this.condNombre = ''; this.condLicencia = ''; this.condPlaca = '';
                this.condError = '';
            } else {
                this.transpTipoDoc = 'RUC'; this.transpDoc = ''; this.transpNombre = '';
                this.transpError = '';
            }
        },

        async buscarDestinatarioAjax() {
            if (this.destinatarioBuscar.length < 2) { this.destinatarioResultados = []; return; }
            clearTimeout(this.destinatarioTimer);
            this.destinatarioTimer = setTimeout(async () => {
                this.destinatarioBuscando = true;
                try {
                    const url = `${buscarDestinatarioUrl}?tipo=${this.tipoDestino}&buscar=${encodeURIComponent(this.destinatarioBuscar)}`;
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    this.destinatarioResultados = await resp.json();
                } catch { this.destinatarioResultados = []; }
                finally { this.destinatarioBuscando = false; }
            }, 350);
        },

        seleccionarDestinatario(item) {
            this.destinatarioSeleccionado = item;
            this.destinatarioBuscar = item.nombre;
            this.destinatarioResultados = [];
            this.clienteId   = this.tipoDestino === 'cliente'   ? item.id : '';
            this.proveedorId = this.tipoDestino === 'proveedor' ? item.id : '';
        },

        puedeEnviar() {
            if (!this.almacenId) return false;
            if (this.tipoDestino === 'almacen' && (!this.almacenDestinoId || this.almacenId === this.almacenDestinoId)) return false;
            if (this.tipoDestino === 'cliente'   && !this.clienteId)   return false;
            if (this.tipoDestino === 'proveedor' && !this.proveedorId) return false;
            if (isFromTraslado) return true;
            if (this.productos.length === 0) return false;
            return this.productos.every((p, idx) => {
                if (!p.productoId || this.esDuplicado(idx)) return false;
                if (p.esSerie) return p.imeisSeleccionados.length > 0;
                return p.cantidad >= 1 && (p.stockOrigen === null || p.stockOrigen >= p.cantidad);
            });
        },
    };
}
</script>
</body>
</html>
