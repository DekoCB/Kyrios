<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva Compra - CORPORACIÓN ADIVON SAC</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-5">
        <!-- Header con breadcrumb -->
        <div class="mb-4">
            <div class="flex items-center text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-900">Dashboard</a>
                <i class="fas fa-chevron-right mx-1.5 text-[10px]"></i>
                <a href="{{ route('compras.index') }}" class="hover:text-blue-900">Compras</a>
                <i class="fas fa-chevron-right mx-1.5 text-[10px]"></i>
                <span class="text-gray-700 font-medium">Nueva Compra</span>
            </div>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-file-invoice mr-2 text-blue-900 text-lg"></i>
                    Registrar Nueva Compra
                </h1>
                <span class="px-2.5 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                    <i class="fas fa-clock mr-1"></i>{{ now()->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>

        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded-lg flex items-start text-sm">
                <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded-lg">
                <div class="flex items-center mb-1">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong class="text-sm">Por favor corrige los siguientes errores:</strong>
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($pedidoOrigen ?? null)
            <div class="mb-4 bg-indigo-50 border-l-4 border-indigo-500 text-indigo-800 p-3 rounded-lg flex items-start text-sm">
                <i class="fas fa-clipboard-check mt-0.5 mr-2"></i>
                <span>
                    Recibiendo mercadería del pedido <strong>{{ $pedidoOrigen['codigo'] }}</strong> —
                    proveedor, almacén y productos se precargaron. Completa factura, precios finales
                    e IMEIs (si aplica) y guarda para cerrar el pedido.
                </span>
            </div>
        @endif

        <!-- Formulario principal -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Cabecera decorativa -->
            <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-5 py-3">
                <h2 class="text-base font-bold text-white flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Datos de la Compra
                </h2>
            </div>

            <form action="{{ route('compras.store') }}" method="POST" id="compraForm" class="p-5">
                @csrf
                @if($pedidoOrigen ?? null)
                    <input type="hidden" name="pedido_id" value="{{ $pedidoOrigen['id'] }}">
                @endif

                <!-- TIPO DE COMPRA -->
                <div class="mb-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                        <span class="w-6 h-6 bg-purple-100 rounded flex items-center justify-center mr-1.5">
                            <i class="fas fa-tag text-purple-700 text-xs"></i>
                        </span>
                        Tipo de Compra
                    </h3>
                    <div class="grid grid-cols-2 gap-3 max-w-xl">
                        <label class="cursor-pointer">
                            <input type="radio" name="tipo_compra" value="local"
                                   class="sr-only peer"
                                   onchange="cambiarTipoCompra('local')"
                                   {{ old('tipo_compra', 'local') === 'local' ? 'checked' : '' }}>
                            <div class="peer-checked:border-green-500 peer-checked:bg-green-50 border border-gray-200 rounded-lg p-3 flex items-center gap-2 transition hover:border-green-300">
                                <div class="w-8 h-8 bg-green-100 rounded flex items-center justify-center shrink-0">
                                    <i class="fas fa-store text-green-700 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-xs">Compra Local</p>
                                    <p class="text-[11px] text-gray-500">Sin documentos aduaneros</p>
                                </div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="tipo_compra" value="importacion"
                                   class="sr-only peer"
                                   onchange="cambiarTipoCompra('importacion')"
                                   {{ old('tipo_compra') === 'importacion' ? 'checked' : '' }}>
                            <div class="peer-checked:border-orange-500 peer-checked:bg-orange-50 border border-gray-200 rounded-lg p-3 flex items-center gap-2 transition hover:border-orange-300">
                                <div class="w-8 h-8 bg-orange-100 rounded flex items-center justify-center shrink-0">
                                    <i class="fas fa-ship text-orange-700 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-xs">Importación</p>
                                    <p class="text-[11px] text-gray-500">DUA, manifiesto y costos CIF</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SECCIÓN IMPORTACIÓN (condicional) -->
                <div id="seccion_importacion" class="{{ old('tipo_compra') === 'importacion' ? '' : 'hidden' }} mb-5">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-orange-900 mb-3 flex items-center">
                            <i class="fas fa-ship mr-1.5 text-orange-600 text-xs"></i>
                            Datos de Importación
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <!-- Número DUA -->
                            <div>
                                <label for="numero_dua" class="block text-xs font-medium text-gray-700 mb-1">
                                    Número DUA <span class="text-orange-500">*</span>
                                    <span class="text-xs text-gray-400 font-normal">(Declaración Única de Aduanas)</span>
                                </label>
                                <input type="text" name="numero_dua" id="numero_dua"
                                       value="{{ old('numero_dua') }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                       placeholder="Ej: 117-2026-00001234">
                                @error('numero_dua')
                                    <p class="mt-1 text-xs text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                                @enderror
                            </div>
                            <!-- Número Manifiesto -->
                            <div>
                                <label for="numero_manifiesto" class="block text-xs font-medium text-gray-700 mb-1">
                                    N° Manifiesto / Carga
                                </label>
                                <input type="text" name="numero_manifiesto" id="numero_manifiesto"
                                       value="{{ old('numero_manifiesto') }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                       placeholder="Ej: MAN-2026-001">
                            </div>
                            <!-- Agente de Aduanas -->
                            <div>
                                <label for="agente_aduanas" class="block text-xs font-medium text-gray-700 mb-1">
                                    Agente de Aduanas
                                </label>
                                <input type="text" name="agente_aduanas" id="agente_aduanas"
                                       value="{{ old('agente_aduanas') }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                       placeholder="Nombre del agente o empresa">
                            </div>
                            <!-- Flete USD -->
                            <div>
                                <label for="flete_usd" class="block text-xs font-medium text-gray-700 mb-1">
                                    Flete (USD)
                                    <span class="text-xs text-gray-400 font-normal">Costo de transporte internacional</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">$</span>
                                    <input type="number" name="flete_usd" id="flete_usd"
                                           value="{{ old('flete_usd', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Seguro USD -->
                            <div>
                                <label for="seguro_usd" class="block text-xs font-medium text-gray-700 mb-1">
                                    Seguro (USD)
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">$</span>
                                    <input type="number" name="seguro_usd" id="seguro_usd"
                                           value="{{ old('seguro_usd', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Otros Gastos USD -->
                            <div>
                                <label for="otros_usd" class="block text-xs font-medium text-gray-700 mb-1">
                                    Otros Gastos (USD)
                                    <span class="text-xs text-gray-400 font-normal">Almacenaje, etc.</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">$</span>
                                    <input type="number" name="otros_usd" id="otros_usd"
                                           value="{{ old('otros_usd', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Impuestos USD (Ad Valorem, etc.) -->
                            <div>
                                <label for="impuestos_usd" class="block text-xs font-medium text-gray-700 mb-1">
                                    Impuestos (USD)
                                    <span class="text-xs text-gray-400 font-normal">Ad Valorem, etc.</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">$</span>
                                    <input type="number" name="impuestos_usd" id="impuestos_usd"
                                           value="{{ old('impuestos_usd', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Impuestos PEN (IGV importación, etc.) -->
                            <div>
                                <label for="impuestos_pen" class="block text-xs font-medium text-gray-700 mb-1">
                                    Impuestos (S/)
                                    <span class="text-xs text-gray-400 font-normal">IGV importación, ISC</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">S/</span>
                                    <input type="number" name="impuestos_pen" id="impuestos_pen"
                                           value="{{ old('impuestos_pen', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Transporte Local PEN -->
                            <div>
                                <label for="transporte_local_pen" class="block text-xs font-medium text-gray-700 mb-1">
                                    Transporte Local (S/)
                                    <span class="text-xs text-gray-400 font-normal">Puerto → almacén</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">S/</span>
                                    <input type="number" name="transporte_local_pen" id="transporte_local_pen"
                                           value="{{ old('transporte_local_pen', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Percepción PEN -->
                            <div>
                                <label for="percepcion_pen" class="block text-xs font-medium text-gray-700 mb-1">
                                    Percepción (S/)
                                    <span class="text-xs text-gray-400 font-normal">Percepción SUNAT</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">S/</span>
                                    <input type="number" name="percepcion_pen" id="percepcion_pen"
                                           value="{{ old('percepcion_pen', 0) }}" min="0" step="0.01"
                                           oninput="calcularTotales()"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg focus:border-orange-400 focus:ring-2 focus:ring-orange-100 text-sm"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <!-- Resumen CIF -->
                            <div class="flex items-center">
                                <div class="w-full bg-white border border-orange-200 rounded-lg p-3 text-sm">
                                    <p class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-1.5">
                                        <i class="fas fa-calculator mr-1"></i>Costos CIF / Totales
                                    </p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between"><span>Flete (USD):</span><span id="cif_flete" class="font-medium">$ 0.00</span></div>
                                        <div class="flex justify-between"><span>Seguro (USD):</span><span id="cif_seguro" class="font-medium">$ 0.00</span></div>
                                        <div class="flex justify-between"><span>Otros (USD):</span><span id="cif_otros" class="font-medium">$ 0.00</span></div>
                                        <div class="flex justify-between"><span>Imp. (S/):</span><span id="cif_impuestos_pen" class="font-medium">S/ 0.00</span></div>
                                        <div class="flex justify-between"><span>Transp. (S/):</span><span id="cif_transporte" class="font-medium">S/ 0.00</span></div>
                                        <div class="flex justify-between"><span>Percepción (S/):</span><span id="cif_percepcion" class="font-medium">S/ 0.00</span></div>
                                        <div class="flex justify-between pt-1 border-t border-orange-200 font-semibold text-orange-800">
                                            <span>Total CIF:</span><span id="cif_total">S/ 0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Prorrateo -->
                        <div id="prorrateo_section" class="hidden mt-3 bg-white border border-orange-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-3">
                                <i class="fas fa-divide mr-1"></i>Distribución de Costos por Producto (Prorrateo)
                            </p>
                            <div id="prorrateo_tabla" class="text-xs text-gray-700 space-y-1">
                                <p class="text-gray-400 italic">Agrega productos para ver el prorrateo...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 1: INFORMACIÓN PRINCIPAL -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                        <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center mr-1.5">
                            <i class="fas fa-file-invoice text-blue-900 text-xs"></i>
                        </span>
                        Información de la Factura
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Proveedor (búsqueda en vivo) -->
                        <div class="relative" id="proveedor_container">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Proveedor <span class="text-red-500">*</span>
                            </label>
                            {{-- Campo oculto que envía el ID al servidor --}}
                            <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ old('proveedor_id', $pedidoOrigen['proveedor_id'] ?? '') }}">

                            {{-- Input de búsqueda --}}
                            <div class="relative" id="proveedor_busqueda_wrap" style="{{ old('proveedor_id', $pedidoOrigen['proveedor_id'] ?? null) ? 'display:none' : '' }}">
                                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 pointer-events-none text-sm"></i>
                                <input type="text"
                                       id="buscar_proveedor"
                                       placeholder="RUC, razón social o nombre (mín. 3 car.)..."
                                       autocomplete="off"
                                       class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                            </div>

                            {{-- Dropdown de resultados --}}
                            <div id="proveedor_resultados"
                                 class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 hidden max-h-64 overflow-y-auto">
                            </div>

                            {{-- Proveedor seleccionado (card) --}}
                            @php
                                $provSeleccionadoId = old('proveedor_id', $pedidoOrigen['proveedor_id'] ?? null);
                                $provSeleccionado = $provSeleccionadoId ? $proveedores->firstWhere('id', $provSeleccionadoId) : null;
                            @endphp
                            <div id="proveedor_seleccionado"
                                 class="{{ $provSeleccionado ? '' : 'hidden' }} mt-1.5 p-2 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="fas fa-building text-blue-700 text-sm shrink-0"></i>
                                    <div class="min-w-0">
                                        <p id="proveedor_nombre_display" class="text-sm font-semibold text-blue-900 truncate">
                                            {{ $provSeleccionado?->nombre_comercial ?? $provSeleccionado?->razon_social ?? '' }}
                                        </p>
                                        <p id="proveedor_ruc_display" class="text-xs text-blue-600">
                                            @if($provSeleccionado)
                                                {{ $provSeleccionado->razon_social !== $provSeleccionado->nombre_comercial ? $provSeleccionado->razon_social . ' · ' : '' }}RUC: {{ $provSeleccionado->ruc }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <button type="button" onclick="limpiarProveedorSeleccionado()"
                                        class="shrink-0 ml-2 text-xs text-blue-600 hover:text-red-600 transition flex items-center gap-1 border border-blue-300 hover:border-red-400 rounded-lg px-2 py-1">
                                    <i class="fas fa-times"></i> Cambiar
                                </button>
                            </div>

                            @error('proveedor_id')
                                <p class="mt-1 text-xs text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Número de Factura -->
                        <div>
                            <label for="numero_factura" class="block text-xs font-medium text-gray-700 mb-1">
                                N° Factura/Boleta <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="numero_factura" id="numero_factura"
                                   value="{{ old('numero_factura') }}" required
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm"
                                   placeholder="Ej: F001-000001">
                            @error('numero_factura')
                                <p class="mt-1 text-xs text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Almacén de Destino -->
                        <div class="relative">
                            <label for="almacen_id" class="block text-xs font-medium text-gray-700 mb-1">
                                Almacén de Destino <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                @php $almacenSeleccionado = old('almacen_id', $pedidoOrigen['almacen_id'] ?? null); @endphp
                                <select name="almacen_id" id="almacen_id" required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm appearance-none bg-white text-sm">
                                    <option value="">— Seleccione un almacén —</option>
                                    @if($almacenesCentral->isNotEmpty())
                                        <optgroup label="── Almacenes Centrales">
                                            @foreach($almacenesCentral as $alm)
                                                <option value="{{ $alm->id }}" {{ $almacenSeleccionado == $alm->id ? 'selected' : '' }}>
                                                    {{ $alm->nombre }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($almacenesTienda->isNotEmpty())
                                        <optgroup label="── Almacenes de Tienda">
                                            @foreach($almacenesTienda as $alm)
                                                <option value="{{ $alm->id }}" {{ $almacenSeleccionado == $alm->id ? 'selected' : '' }}>
                                                    {{ $alm->nombre }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                            @error('almacen_id')
                                <p class="mt-1 text-xs text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Fecha -->
                        <div>
                            <label for="fecha" class="block text-xs font-medium text-gray-700 mb-1">
                                Fecha de Compra <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="fecha" id="fecha" required
                                   value="{{ old('fecha', date('Y-m-d')) }}"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                        </div>

                        <!-- Tipo Comprobante -->
                        <div class="relative">
                            <label for="tipo_comprobante" class="block text-xs font-medium text-gray-700 mb-1">
                                Tipo Comprobante
                            </label>
                            <div class="relative">
                                <select name="tipo_comprobante" id="tipo_comprobante"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm appearance-none bg-white">
                                    <option value="factura">Factura</option>
                                    <option value="boleta">Boleta</option>
                                    <option value="nota_credito">Nota de Crédito</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <!-- Forma de Pago -->
                        <div class="relative">
                            <label for="forma_pago" class="block text-xs font-medium text-gray-700 mb-1">
                                Forma de Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="forma_pago" id="forma_pago" required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm appearance-none bg-white"
                                        onchange="toggleCondicionPago(this.value)">
                                    <option value="contado" {{ old('forma_pago') == 'contado' ? 'selected' : '' }}>Contado</option>
                                    <option value="credito" {{ old('forma_pago') == 'credito' ? 'selected' : '' }}>Crédito</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <!-- Condición de Pago (crédito) -->
                        <div id="condicion_pago_div" class="{{ old('forma_pago') == 'credito' ? '' : 'hidden' }}">
                            <label for="condicion_pago" class="block text-xs font-medium text-gray-700 mb-1">
                                Días de Crédito
                            </label>
                            <input type="number" name="condicion_pago" id="condicion_pago"
                                value="{{ old('condicion_pago', 30) }}" min="1" max="90"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm"
                                {{ old('forma_pago') == 'credito' ? '' : 'disabled' }}>
                        </div>

                        <!-- Moneda -->
                        <div class="relative">
                            <label for="tipo_moneda" class="block text-xs font-medium text-gray-700 mb-1">
                                Moneda
                            </label>
                            <div class="relative">
                                <select name="tipo_moneda" id="tipo_moneda"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm appearance-none bg-white"
                                        onchange="toggleTipoCambio(this.value)">
                                    <option value="PEN" {{ old('tipo_moneda', 'PEN') == 'PEN' ? 'selected' : '' }}>PEN (S/)</option>
                                    <option value="USD" {{ old('tipo_moneda') == 'USD' ? 'selected' : '' }}>USD ($)</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <!-- Tipo de Cambio -->
                        <div id="tipo_cambio_div" class="hidden">
                            <label for="tipo_cambio" class="block text-xs font-medium text-gray-700 mb-1">
                                Tipo de Cambio (S/ por $)
                            </label>
                            <div class="flex gap-2 items-center">
                                <input type="number" name="tipo_cambio" id="tipo_cambio"
                                       value="{{ old('tipo_cambio', '') }}" min="0.001" step="0.001"
                                       placeholder="Ej: 3.750"
                                       oninput="calcularTotales()"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                                <button type="button" id="btnCargarTC" onclick="cargarTipoCambioSUNAT()"
                                        title="Cargar tipo de cambio desde SUNAT"
                                        class="flex-shrink-0 px-2.5 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg border border-blue-200 transition text-sm font-medium whitespace-nowrap">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div id="tcInfo" class="hidden mt-1.5 px-3 py-2 bg-green-50 rounded-lg text-xs text-gray-600 flex flex-wrap gap-3">
                                <span>Compra: <strong id="tcCompra" class="text-gray-800"></strong></span>
                                <span>Venta: <strong id="tcVenta" class="text-gray-800"></strong></span>
                                <span class="text-gray-400 italic" id="tcFecha"></span>
                                <span class="text-green-600 font-medium"><i class="fas fa-check-circle mr-0.5"></i>SUNAT</span>
                            </div>
                        </div>

                        <!-- Tipo de Operación SUNAT -->
                        <div class="lg:col-span-3">
                            <label for="tipo_operacion" class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-file-invoice mr-1 text-blue-600 text-[10px]"></i>
                                Tipo de Operación SUNAT <span class="text-red-500">*</span>
                            </label>
                            <div class="relative max-w-md">
                                <select name="tipo_operacion" id="tipo_operacion" required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm appearance-none bg-white">
                                    <option value="01" {{ old('tipo_operacion', '01') == '01' ? 'selected' : '' }}>01 — Gravado (IGV 18%)</option>
                                    <option value="02" {{ old('tipo_operacion') == '02' ? 'selected' : '' }}>02 — Exonerado</option>
                                    <option value="03" {{ old('tipo_operacion') == '03' ? 'selected' : '' }}>03 — Inafecto</option>
                                    <option value="04" {{ old('tipo_operacion') == '04' ? 'selected' : '' }}>04 — Exportación</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: PRODUCTOS -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <span class="w-6 h-6 bg-green-100 rounded flex items-center justify-center mr-1.5">
                                <i class="fas fa-boxes text-green-700 text-xs"></i>
                            </span>
                            Productos de la Compra
                        </h3>
                        <button type="button" onclick="abrirModalProductos()"
                                class="px-3 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition shadow-md text-sm flex items-center">
                            <i class="fas fa-plus-circle mr-1.5"></i>
                            Agregar Productos
                        </button>
                    </div>

                    <!-- Tabla de productos -->
                    <div class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="tablaProductos">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-2 py-2 text-center text-[10px] font-semibold text-gray-600 uppercase w-8">#</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Marca</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Modelo</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Color</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Cant.</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase" id="thPrecioUnit">P. Unit. (S/)</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase" id="thSubtotal">Subtotal</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">IMEIs</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-semibold text-gray-600 uppercase w-16">Acc.</th>
                                    </tr>
                                </thead>
                                <tbody id="detallesBody" class="bg-white divide-y divide-gray-200">
                                    <!-- Los productos se agregarán dinámicamente aquí -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Mensaje cuando no hay productos -->
                        <div id="emptyProductos" class="text-center py-8 bg-white">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-2">
                                    <i class="fas fa-box-open text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 text-sm">No hay productos agregados</p>
                                <p class="text-xs text-gray-400">Haz clic en "Agregar Productos" para comenzar</p>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                            <div class="flex justify-end">
                                <div class="w-72 space-y-2">

                                    {{-- Toggle: precio incluye IGV --}}
                                    <div id="togglePrecioIgvWrap" class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded px-2.5 py-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer select-none text-[11px] font-semibold text-amber-800" for="precio_incluye_igv">
                                            <i class="fas fa-tags text-amber-500 text-xs"></i>
                                            ¿Precio incluye IGV?
                                        </label>
                                        <input type="hidden" name="precio_incluye_igv" value="0">
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                            <input type="checkbox" id="precio_incluye_igv" name="precio_incluye_igv" value="1" checked
                                                   class="sr-only peer">
                                            <div class="w-9 h-5 bg-gray-300 peer-checked:bg-amber-500 rounded-full transition-colors"></div>
                                            <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                        </label>
                                    </div>

                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Subtotal <span id="lblSinIgv" class="text-xs text-gray-400 hidden">(sin IGV)</span>:</span>
                                        <span id="subtotal" class="font-medium text-gray-900">S/ 0.00</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="hidden" name="incluye_igv" value="0">
                                            <input type="checkbox" id="incluir_igv" name="incluye_igv" value="1" checked
                                                   class="w-4 h-4 rounded border-gray-300 text-blue-900 focus:ring-blue-900">
                                            <span class="text-gray-600">IGV (18%):</span>
                                        </label>
                                        <span id="igv" class="font-medium text-gray-900">S/ 0.00</span>
                                    </div>
                                    <!-- Costos de importación (visibles solo en tipo importacion) -->
                                    <div id="totales_importacion" class="hidden space-y-2 pt-2 border-t border-dashed border-orange-200">
                                        <p class="text-xs font-semibold text-orange-700 uppercase tracking-wide">
                                            <i class="fas fa-ship mr-1"></i>Costos Importación
                                        </p>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Flete (USD):</span>
                                            <span id="total_flete" class="text-gray-700">$ 0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Seguro (USD):</span>
                                            <span id="total_seguro" class="text-gray-700">$ 0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Otros (USD):</span>
                                            <span id="total_otros" class="text-gray-700">$ 0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Imp. (S/):</span>
                                            <span id="total_impuestos_pen" class="text-gray-700">S/ 0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Transp. (S/):</span>
                                            <span id="total_transporte_pen" class="text-gray-700">S/ 0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Percepción (S/):</span>
                                            <span id="total_percepcion_pen" class="text-gray-700">S/ 0.00</span>
                                        </div>
                                    </div>

                                    <div class="flex justify-between font-bold text-base pt-3 border-t-2 border-gray-200">
                                        <span class="text-gray-900">Total:</span>
                                        <span id="total" class="text-blue-900">S/ 0.00</span>
                                    </div>
                                    <!-- Equivalente en PEN (solo visible cuando moneda = USD) -->
                                    <div id="equivalentePEN" class="hidden pt-2 border-t border-dashed border-blue-200">
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-500 flex items-center gap-1">
                                                <i class="fas fa-exchange-alt text-xs text-blue-500"></i>
                                                Equivalente en soles:
                                            </span>
                                            <span id="totalPEN" class="font-semibold text-blue-900">S/ 0.00</span>
                                        </div>
                                        <p class="text-xs text-gray-400 text-right mt-0.5">TC: <span id="tcUsado">—</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: OBSERVACIONES -->
                <div class="mb-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                        <span class="w-6 h-6 bg-yellow-100 rounded flex items-center justify-center mr-1.5">
                            <i class="fas fa-comment text-yellow-600 text-xs"></i>
                        </span>
                        Observaciones
                    </h3>
                    <textarea name="observaciones" rows="2"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm"
                              placeholder="Notas adicionales sobre la compra...">{{ old('observaciones') }}</textarea>
                </div>

                <!-- Botones de acción -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                    <a href="{{ route('compras.index') }}"
                       class="px-5 py-2 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-sm">
                        <i class="fas fa-times mr-1.5"></i>
                        Cancelar
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-gradient-to-r from-blue-900 to-blue-800 text-white rounded-lg hover:from-blue-800 hover:to-blue-700 transition shadow-lg font-medium text-sm">
                        <i class="fas fa-save mr-1.5"></i>
                        Registrar Compra
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE SELECCIÓN DE PRODUCTOS (MEJORADO) -->
    <div id="modalProductos" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="cerrarModalProductos()"></div>
        
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden transform transition-all">
            <!-- Header del modal -->
            <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-search mr-3"></i>
                    Buscar y Seleccionar Productos
                </h3>
                <button onclick="cerrarModalProductos()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- Cuerpo del modal -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                <!-- Buscador en vivo -->
                <div class="mb-6">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        <input type="text" 
                            id="buscadorProductos"
                            placeholder="Buscar producto por nombre, código, marca o modelo..."
                            class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <p class="text-xs text-gray-500 mt-2 flex items-center">
                        <i class="fas fa-info-circle mr-1 text-blue-400"></i>
                        Mínimo 2 caracteres para buscar
                    </p>
                </div>

                <!-- FILTROS POR CATEGORÍA (NUEVO) -->
                <div class="mb-6 overflow-x-auto pb-2">
                    <div class="flex gap-2 min-w-max">
                        <button type="button" 
                                class="categoria-filter active px-4 py-2 rounded-full text-sm font-medium transition-all"
                                data-categoria="todos"
                                style="background-color: #1e3a8a; color: white;">
                            <i class="fas fa-boxes mr-1"></i>Todos
                        </button>
                        
                        @foreach($categorias as $categoria)
                            <button type="button" 
                                    class="categoria-filter px-4 py-2 rounded-full text-sm font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200"
                                    data-categoria="{{ $categoria->id }}">
                                <i class="fas fa-tag mr-1"></i>{{ $categoria->nombre }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Grid de resultados mejorado -->
                <div id="resultadosProductos" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Los resultados se cargarán dinámicamente -->
                </div>

                <!-- Mensaje de carga -->
                <div id="cargandoProductos" class="hidden text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-900"></i>
                    <p class="mt-2 text-gray-500">Buscando productos...</p>
                </div>

                <!-- Mensaje sin resultados -->
                <div id="sinResultados" class="hidden text-center py-10">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-box-open text-3xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 mb-1">No se encontraron productos</p>
                    <p class="text-xs text-gray-400 mb-4">Prueba con otros términos o crea el producto ahora</p>
                    <button type="button" onclick="abrirModalCrearProducto(document.getElementById('buscadorProductos').value)"
                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-green-700 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-500 transition shadow-md text-sm font-medium">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Crear este producto en el catálogo
                    </button>
                </div>
            </div>

            <!-- Footer con acciones (mejorado) -->
            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 flex justify-between items-center">
                <div>
                    <span id="productosSeleccionadosCount" class="text-sm font-medium text-blue-900">0 productos seleccionados</span>
                    <span id="totalUnidadesCount" class="ml-2 text-sm text-gray-500">(0 unidades)</span>
                </div>
                <div class="flex space-x-3">
                    <button onclick="cerrarModalProductos()"
                            class="px-6 py-2 border-2 border-gray-200 rounded-lg text-gray-700 hover:bg-white transition">
                        Cancelar
                    </button>
                    <button onclick="agregarProductosSeleccionados()"
                            class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition shadow-md flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Agregar Seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- MODAL DE IMEIs MEJORADO --}}
    <div id="imeiModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="cerrarModalIMEI()"></div>
        
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
            <!-- Header con gradiente -->
            <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white flex items-center" id="imeiModalTitle">
                    <i class="fas fa-microchip mr-3"></i>
                    Registrar IMEIs
                </h3>
                <button onclick="cerrarModalIMEI()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- Tabs: Manual / Pistola -->
            <div class="flex border-b border-gray-200 bg-gray-50">
                <button type="button" id="tab_manual"
                        onclick="activarModoIMEI('manual')"
                        class="flex-1 py-2.5 text-sm font-medium text-purple-700 border-b-2 border-purple-600 bg-white transition">
                    <i class="fas fa-keyboard mr-1"></i> Manual
                </button>
                <button type="button" id="tab_pistola"
                        onclick="activarModoIMEI('pistola')"
                        class="flex-1 py-2.5 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition">
                    <i class="fas fa-barcode mr-1"></i> Pistola / Escáner
                </button>
            </div>

            <!-- Cuerpo del modal -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">

                <!-- ── MODO MANUAL ── -->
                <div id="panel_manual">
                    <!-- Barra de herramientas -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 p-3 bg-gray-50 rounded-xl">
                        <span class="text-sm font-medium text-gray-700">
                            <i class="fas fa-info-circle mr-1 text-blue-500"></i>
                            Total: <span id="imeiTotalCount" class="font-bold text-purple-700">0</span> IMEIs
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="generarIMEIsAleatorios()"
                                    class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm flex items-center">
                                <i class="fas fa-magic mr-1"></i>Generar
                            </button>
                            <button type="button" onclick="limpiarIMEIs()"
                                    class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm flex items-center">
                                <i class="fas fa-eraser mr-1"></i>Limpiar
                            </button>
                        </div>
                    </div>
                    <div id="imeiContainer" class="space-y-3"></div>
                    <div class="mt-4 text-xs text-gray-500 flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span><i class="fas fa-info-circle mr-1 text-blue-500"></i> Cada IMEI debe tener exactamente 15 dígitos</span>
                        <span><i class="fas fa-level-down-alt mr-1 text-blue-500"></i> Enter avanza al siguiente campo</span>
                    </div>
                </div>

                <!-- ── MODO PISTOLA ── -->
                <div id="panel_pistola" class="hidden">
                    <!-- Input principal de escaneo -->
                    <div class="mb-4">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                                <i class="fas fa-barcode text-purple-400 text-2xl"></i>
                            </div>
                            <input type="text" id="pistola_input"
                                   class="w-full pl-14 pr-4 py-4 border-2 border-purple-300 rounded-xl focus:border-purple-600 focus:ring-4 focus:ring-purple-100 font-mono text-xl tracking-widest text-center transition"
                                   placeholder="Escanear IMEI aquí..."
                                   maxlength="15"
                                   autocomplete="off"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   onkeydown="pistolaManejarTecla(event)">
                        </div>
                        <p class="text-xs text-center text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Apunta la pistola a este campo y escanea — se registra automáticamente al recibir el Enter
                        </p>
                    </div>

                    <!-- Contador y acciones -->
                    <div class="flex items-center justify-between mb-3 px-1">
                        <span class="text-sm font-semibold text-gray-700">
                            IMEIs escaneados: <span id="pistola_count" class="text-purple-700">0</span>
                        </span>
                        <button type="button" onclick="pistolaBorrarUltimo()"
                                class="text-xs text-red-500 hover:text-red-700 transition flex items-center gap-1">
                            <i class="fas fa-undo"></i> Deshacer último
                        </button>
                    </div>

                    <!-- Lista de IMEIs escaneados -->
                    <div id="pistola_lista" class="space-y-1.5 max-h-72 overflow-y-auto pr-1"></div>

                    <!-- Estado vacío -->
                    <div id="pistola_vacio" class="py-10 text-center text-gray-400">
                        <i class="fas fa-barcode text-5xl mb-3 block opacity-30"></i>
                        <p class="text-sm">Aún no se han escaneado IMEIs</p>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                <button type="button" onclick="cerrarModalIMEI()"
                        class="px-6 py-2 border-2 border-gray-200 rounded-lg text-gray-700 hover:bg-white transition">
                    Cancelar
                </button>
                <button type="button" onclick="guardarIMEIs()"
                        class="px-6 py-2 bg-gradient-to-r from-purple-700 to-purple-600 text-white rounded-lg hover:from-purple-600 hover:to-purple-500 transition shadow-md">
                    <i class="fas fa-check-circle mr-2"></i>
                    Guardar IMEIs
                </button>
            </div>
        </div>
    </div>
<script>
    
    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    let timeoutBusqueda;
    let contadorProductos = 0;
    let imeisPorFila = {}; // { rowIndex: ['imei1', 'imei2', ...] }
    let productoEnEdicion = null;
    let productosSeleccionadosIds = new Set();
    let categoriaActual = 'todos';
    let productosFiltrados = [];
    let productosOriginales = []; // Para mantener la lista completa
    let cantidadesSeleccionadas = {}; // { productoId: cantidad }
    let variantesSeleccionadas  = {}; // { "prodId_varId": { productoId, varianteId, cantidad, producto, variante } }
    let productosModal  = {};  // prodId → producto (registro para handlers sin JSON inline)
    let variantesModal  = {};  // "prodId_varId" → variante




    // Datos de catálogo (cargados desde PHP)
    const categoriasDisponibles = @json($categorias);
    const catalogoProductos   = @json($productos);
    const marcasCatalogo      = @json($marcas);
    const coloresCatalogo     = @json($colores);
    const proveedoresCatalogo = {!! json_encode($proveedores->map(fn($p) => ['id' => $p->id, 'ruc' => $p->ruc, 'razon_social' => $p->razon_social, 'nombre_comercial' => $p->nombre_comercial])) !!};

    // Elementos del DOM
    const modalProductos = document.getElementById('modalProductos');
    const buscador = document.getElementById('buscadorProductos');
    const resultadosDiv = document.getElementById('resultadosProductos');
    const cargandoDiv = document.getElementById('cargandoProductos');
    const sinResultadosDiv = document.getElementById('sinResultados');

    // ============================================
    // BÚSQUEDA EN VIVO DE PROVEEDOR
    // ============================================
    (function initProveedorSearch() {
        const provInput    = document.getElementById('buscar_proveedor');
        const provResultados = document.getElementById('proveedor_resultados');
        let timeoutProv;

        if (!provInput) return;

        provInput.addEventListener('input', function () {
            clearTimeout(timeoutProv);
            const termino = this.value.trim();

            if (termino.length < 3) {
                provResultados.classList.add('hidden');
                provResultados.innerHTML = '';
                return;
            }

            timeoutProv = setTimeout(() => {
                const tl = termino.toLowerCase();
                const resultados = proveedoresCatalogo.filter(p =>
                    (p.ruc             && p.ruc.toLowerCase().includes(tl)) ||
                    (p.razon_social    && p.razon_social.toLowerCase().includes(tl)) ||
                    (p.nombre_comercial && p.nombre_comercial.toLowerCase().includes(tl))
                );

                if (resultados.length === 0) {
                    provResultados.innerHTML =
                        '<div class="px-4 py-3 text-sm text-gray-500 text-center">' +
                        '<i class="fas fa-search mr-1"></i>No se encontraron proveedores</div>';
                } else {
                    provResultados.innerHTML = resultados.map(p => {
                        const nombre = p.nombre_comercial || p.razon_social || '';
                        const extra  = (p.nombre_comercial && p.nombre_comercial !== p.razon_social)
                            ? p.razon_social : '';
                        // Escape para el onclick
                        const n  = nombre.replace(/\\/g,'\\\\').replace(/'/g, "\\'");
                        const r  = (p.ruc || '').replace(/'/g, "\\'");
                        const rs = (p.razon_social || '').replace(/\\/g,'\\\\').replace(/'/g, "\\'");
                        return `
                            <div onclick="seleccionarProveedor(${p.id},'${n}','${r}','${rs}')"
                                 class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors">
                                <div class="font-medium text-gray-900 text-sm">${nombre}</div>
                                <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-3">
                                    ${extra ? `<span>${extra}</span>` : ''}
                                    <span class="font-mono text-blue-600">RUC: ${p.ruc || '—'}</span>
                                </div>
                            </div>`;
                    }).join('');
                }
                provResultados.classList.remove('hidden');
            }, 300);
        });

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function (e) {
            const container = document.getElementById('proveedor_container');
            if (container && !container.contains(e.target)) {
                provResultados.classList.add('hidden');
            }
        });

        // Cerrar con Escape
        provInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                provResultados.classList.add('hidden');
                provResultados.innerHTML = '';
            }
        });
    })();

    function seleccionarProveedor(id, nombre, ruc, razonSocial) {
        document.getElementById('proveedor_id').value = id;
        document.getElementById('proveedor_nombre_display').textContent = nombre;
        document.getElementById('proveedor_ruc_display').textContent =
            (razonSocial && razonSocial !== nombre)
                ? `${razonSocial} · RUC: ${ruc}`
                : `RUC: ${ruc}`;

        // Mostrar card, ocultar buscador
        document.getElementById('proveedor_seleccionado').classList.remove('hidden');
        document.getElementById('proveedor_busqueda_wrap').style.display = 'none';
        document.getElementById('proveedor_resultados').classList.add('hidden');
        document.getElementById('buscar_proveedor').value = '';
    }

    function limpiarProveedorSeleccionado() {
        document.getElementById('proveedor_id').value = '';
        document.getElementById('proveedor_seleccionado').classList.add('hidden');
        document.getElementById('proveedor_busqueda_wrap').style.display = '';
        document.getElementById('buscar_proveedor').value = '';
        document.getElementById('buscar_proveedor').focus();
    }

    // ============================================
    // FUNCIONES DE UTILIDAD
    // ============================================
    function toggleCondicionPago(valor) {
        const div = document.getElementById('condicion_pago_div');
        const input = document.getElementById('condicion_pago');
        
        if (valor === 'credito') {
            div.style.display = 'block';
            input.disabled = false;
        } else {
            div.style.display = 'none';
            input.disabled = true;
            input.value = ''; // Limpiar valor cuando no es crédito
        }
    }
    // ============================================
    // FILTRO POR CATEGORÍA
    // ============================================
    function filtrarPorCategoria(categoriaId) {
        categoriaActual = categoriaId;
        
        // Actualizar estilos de los botones
        document.querySelectorAll('.categoria-filter').forEach(btn => {
            const btnCategoria = btn.dataset.categoria;
            if (btnCategoria == categoriaId) {
                btn.style.backgroundColor = '#1e3a8a';
                btn.style.color = 'white';
            } else {
                btn.style.backgroundColor = '#f3f4f6';
                btn.style.color = '#374151';
            }
        });
        
        // Filtrar productos según categoría
        if (productosOriginales && productosOriginales.length > 0) {
            let productosFiltrados = productosOriginales;
            if (categoriaId !== 'todos') {
                productosFiltrados = productosOriginales.filter(p => p.categoria_id == categoriaId);
            }
            
            if (productosFiltrados.length === 0) {
                resultadosDiv.innerHTML = '';
                sinResultadosDiv.classList.remove('hidden');
            } else {
                sinResultadosDiv.classList.add('hidden');
                mostrarResultadosConSeleccion(productosFiltrados);
            }   
        }
    }

    // Asignar eventos a los botones de categoría
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.categoria-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                filtrarPorCategoria(this.dataset.categoria);
            });
        });
    });

    function toggleTipoCambio(valor) {
        const div = document.getElementById('tipo_cambio_div');
        if (valor === 'USD') {
            div.style.display = 'block';
            // Si el campo está vacío, cargar automáticamente
            const tc = document.getElementById('tipo_cambio');
            if (!tc.value) cargarTipoCambioSUNAT();
        } else {
            div.style.display = 'none';
        }
        actualizarSimbolosMoneda(valor);
        calcularTotales();
    }

    async function cargarTipoCambioSUNAT() {
        const btn = document.getElementById('btnCargarTC');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        try {
            const res = await fetch('{{ route("compras.tipo-cambio") }}').then(r => r.json());
            if (res.success) {
                // Usamos el precio de venta: es lo que pagamos al comprar dólares
                document.getElementById('tipo_cambio').value = res.venta;
                document.getElementById('tcCompra').textContent = res.compra;
                document.getElementById('tcVenta').textContent = res.venta;
                document.getElementById('tcFecha').textContent = res.fecha ? `(${res.fecha})` : '';
                document.getElementById('tcInfo').classList.remove('hidden');
                calcularTotales();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin conexión a SUNAT',
                    text: res.message,
                    confirmButtonColor: '#1e3a8a',
                });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'Ingresa el tipo de cambio manualmente.', confirmButtonColor: '#d33' });
        } finally {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            btn.disabled = false;
        }
    }

    function actualizarSimbolosMoneda(moneda) {
        const simbolo = moneda === 'USD' ? '$' : 'S/';
        const thPrecio = document.getElementById('thPrecioUnit');
        if (thPrecio) thPrecio.textContent = `Precio Unit. (${simbolo})`;

        // Actualizar prefijos en filas existentes
        document.querySelectorAll('[id^="precio_prefix_"]').forEach(el => {
            el.textContent = simbolo;
        });

        // Recalcular subtotales con nuevo símbolo
        document.querySelectorAll('#detallesBody tr').forEach(row => {
            const match = row.id?.match(/producto_(\d+)/);
            if (match) calcularSubtotal(parseInt(match[1]));
        });
    }

    // ============================================
    // FUNCIONES PARA AGREGAR PRODUCTOS
    // ============================================
    function agregarProducto() {
        const tbody = document.getElementById('detallesBody');
        const idx = contadorProductos;
        const rowId = `producto_${idx}`;

        const opcionesProductos = catalogoProductos.map(p =>
            `<option value="${p.id}" data-tipo="${p.tipo_inventario}">${p.nombre}</option>`
        ).join('');

        const opcionesMarcas = marcasCatalogo.map(m =>
            `<option value="${m.id}">${m.nombre}</option>`
        ).join('');

        const opcionesColores = coloresCatalogo.map(c =>
            `<option value="${c.id}">${c.nombre}</option>`
        ).join('');

        const row = document.createElement('tr');
        row.id = rowId;
        row.className = 'border-b border-gray-100 hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-2 py-2 text-center">
                <span class="item-num inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold"></span>
            </td>
            <td class="px-2 py-2">
                <select name="detalles[${idx}][producto_id]"
                        id="producto_select_${idx}"
                        onchange="cargarDetallesProducto(this, ${idx})"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500">
                    <option value="">Seleccione producto</option>
                    ${opcionesProductos}
                </select>
                <div id="variante_container_${idx}"></div>
            </td>
            <td class="px-2 py-2">
                <select id="marca_select_${idx}"
                        onchange="cambiarMarca(${idx})"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500"
                        disabled>
                    <option value="">— Marca —</option>
                    ${opcionesMarcas}
                </select>
            </td>
            <td class="px-2 py-2">
                <select name="detalles[${idx}][modelo_id]"
                        id="modelo_select_${idx}"
                        onchange="actualizarTrasCambioModelo(${idx})"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500"
                        disabled>
                    <option value="">— Modelo —</option>
                </select>
            </td>
            <td class="px-2 py-2">
                <select name="detalles[${idx}][color_id]"
                        id="color_${idx}"
                        onchange="actualizarVistaIMEI(${idx})"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500"
                        disabled>
                    <option value="">No aplica</option>
                    ${opcionesColores}
                </select>
            </td>
            <td class="px-2 py-2">
                <input type="number" name="detalles[${idx}][cantidad]"
                       id="cantidad_${idx}"
                       value="1" min="1" step="1"
                       onchange="actualizarCantidad(${idx})"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs">
            </td>
            <td class="px-2 py-2">
                <div class="relative">
                    <span class="absolute left-2 top-1.5 text-gray-500 text-xs" id="precio_prefix_${idx}">${document.getElementById('tipo_moneda').value === 'USD' ? '$' : 'S/'}</span>
                    <input type="number" name="detalles[${idx}][precio_unitario]"
                           id="precio_${idx}"
                           value="" min="0.01" step="0.01"
                           placeholder="0.00"
                           onfocus="if(this.value==='0'||this.value==='0.00')this.value=''"
                           onblur="if(this.value===''||parseFloat(this.value)===0){this.value='';}"
                           oninput="calcularSubtotal(${idx})"
                           onchange="calcularSubtotal(${idx}); propagarPrecio(${idx})"
                           class="w-full pl-7 pr-2 py-1.5 border border-gray-300 rounded text-xs">
                </div>
            </td>
            <td class="px-2 py-2 font-semibold text-xs" id="subtotal_${idx}">${document.getElementById('tipo_moneda').value === 'USD' ? '$' : 'S/'} 0.00</td>
            <td class="px-2 py-2">
                <div id="imei_info_${idx}" class="hidden text-[10px] text-gray-500">
                    <span id="imei_count_${idx}">0</span> IMEI(s)
                </div>
                <button type="button" onclick="gestionarIMEIs(${idx})"
                        id="btn_imei_${idx}"
                        class="text-blue-600 hover:text-blue-800 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                        disabled>
                    <i class="fas fa-microchip mr-0.5"></i>IMEIs
                </button>
            </td>
            <td class="px-2 py-2 text-center">
                <button type="button" onclick="eliminarProducto('${rowId}')"
                        class="text-red-600 hover:text-red-800 text-xs">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);
        contadorProductos++;
        renumerarFilas();

        // Ocultar mensaje de productos vacíos
        const emptyDiv = document.getElementById('emptyProductos');
        if (emptyDiv) emptyDiv.style.display = 'none';
    }

    function renumerarFilas() {
        document.querySelectorAll('#detallesBody tr .item-num').forEach((el, i) => {
            el.textContent = i + 1;
        });
    }

    function eliminarProducto(rowId) {
        if (confirm('¿Eliminar este producto?')) {
            document.getElementById(rowId).remove();
            renumerarFilas();
            calcularTotales();
        }
    }

    function cargarDetallesProducto(select, index) {
        const productoId = select.value;
        const producto = catalogoProductos.find(p => p.id == productoId);
        const marcaSelect = document.getElementById(`marca_select_${index}`);
        const modeloSelect = document.getElementById(`modelo_select_${index}`);
        const colorSelect = document.getElementById(`color_${index}`);
        const btnIMEI = document.getElementById(`btn_imei_${index}`);

        // Resetear dependientes
        marcaSelect.value = '';
        marcaSelect.disabled = true;
        modeloSelect.innerHTML = '<option value="">— Modelo —</option>';
        modeloSelect.disabled = true;
        colorSelect.value = '';
        colorSelect.disabled = true;
        btnIMEI.disabled = true;

        // Limpiar selector de variante si existe
        const varianteContainer = document.getElementById(`variante_container_${index}`);
        if (varianteContainer) varianteContainer.innerHTML = '';

        // Asegurarse de restaurar el colspan cuando se deselecciona un producto
        const _productTd = select.closest('td');
        if (_productTd) _productTd.removeAttribute('colspan');
        const _marcaTd = marcaSelect.closest('td');
        const _modeloTd = modeloSelect.closest('td');
        const _colorTd = colorSelect.closest('td');
        if (_marcaTd)  _marcaTd.style.display  = '';
        if (_modeloTd) _modeloTd.style.display = '';
        if (_colorTd)  _colorTd.style.display  = '';

        if (!producto) {
            calcularSubtotal(index);
            return;
        }

        // ── LÓGICA DE VARIANTES ────────────────────────────────────────────────
        const productTd   = select.closest('td');
        const marcaTd     = marcaSelect.closest('td');
        const modeloTd    = modeloSelect.closest('td');
        const colorTd     = colorSelect.closest('td');

        if (producto.tiene_variantes && producto.variantes && producto.variantes.length > 0) {
            renderVarianteSelector(index, producto.variantes, producto.tipo_inventario);

            // Expandir celda de Producto para cubrir MARCA + MODELO + COLOR
            if (productTd) productTd.setAttribute('colspan', '4');
            // Ocultar las 3 celdas → con colspan=4 el layout queda alineado
            if (marcaTd)  marcaTd.style.display  = 'none';
            if (modeloTd) modeloTd.style.display = 'none';
            if (colorTd)  colorTd.style.display  = 'none';
        } else {
            // Restaurar celda de Producto a tamaño normal
            if (productTd) productTd.removeAttribute('colspan');
            if (marcaTd)  marcaTd.style.display  = '';
            if (modeloTd) modeloTd.style.display = '';
            if (colorTd)  colorTd.style.display  = '';

            marcaSelect.disabled = false;
            colorSelect.disabled = false;

            if (producto.marca_id) {
                marcaSelect.value = producto.marca_id;
                cambiarMarca(index, producto.modelo_id || null);
            }
            if (producto.color_id) colorSelect.value = producto.color_id;
        }

        calcularSubtotal(index);
    }

    /**
     * Renderiza el selector de variante dentro de la celda `variante_container_{index}`.
     */
    function renderVarianteSelector(index, variantes, tipoInventario) {
        const container = document.getElementById(`variante_container_${index}`);
        if (!container) return;

        const opciones = variantes.map(v => {
            const partes = [];
            if (v.color_nombre) partes.push(v.color_nombre);
            if (v.capacidad)    partes.push(v.capacidad);
            const label = partes.join(' / ') || 'Base';
            return `<option value="${v.id}"
                             data-color="${v.color_id || ''}"
                             data-color-nombre="${v.color_nombre || ''}"
                             data-capacidad="${v.capacidad || ''}"
                             data-stock="${v.stock_actual}"
                             data-tipo="${tipoInventario}">
                        ${label} — Stock: ${v.stock_actual}
                    </option>`;
        }).join('');

        container.innerHTML = `
            <div class="mt-2">
                <label class="block text-xs text-gray-500 mb-1 font-medium">
                    <i class="fas fa-layer-group mr-1 text-indigo-500"></i>Variante
                </label>
                <select name="detalles[${index}][variante_id]"
                        id="variante_select_${index}"
                        onchange="seleccionarVariante(${index})"
                        class="w-full px-2 py-1.5 border border-indigo-300 rounded-lg text-sm bg-indigo-50 focus:ring-2 focus:ring-indigo-400">
                    <option value="">— Seleccione variante —</option>
                    ${opciones}
                </select>
                <p id="variante_stock_badge_${index}" class="text-xs mt-1 hidden"></p>
            </div>`;
    }

    function seleccionarVariante(index) {
        const varianteSelect = document.getElementById(`variante_select_${index}`);
        const opt = varianteSelect.selectedOptions[0];
        if (!opt || !opt.value) return;

        const stock  = parseInt(opt.dataset.stock) || 0;
        const tipo   = opt.dataset.tipo;
        const badge  = document.getElementById(`variante_stock_badge_${index}`);
        const btnIMEI = document.getElementById(`btn_imei_${index}`);

        // Actualizar campo color_id oculto si existe
        const colorHidden = document.querySelector(`[name="detalles[${index}][color_id]"]`);
        if (colorHidden) colorHidden.value = opt.dataset.color || '';

        // Badge de stock
        if (badge) {
            badge.classList.remove('hidden');
            badge.className = `text-xs mt-1 font-semibold ${stock > 0 ? 'text-green-600' : 'text-red-600'}`;
            badge.textContent = stock > 0 ? `✓ ${stock} en stock` : '✗ Sin stock';
        }

        // Habilitar IMEIs si aplica
        if (btnIMEI && tipo === 'serie') {
            btnIMEI.disabled = !opt.value;
        }
    }

    function cambiarMarca(index, preseleccionarModeloId = null) {
        const marcaSelect = document.getElementById(`marca_select_${index}`);
        const modeloSelect = document.getElementById(`modelo_select_${index}`);
        const colorSelect = document.getElementById(`color_${index}`);
        const btnIMEI = document.getElementById(`btn_imei_${index}`);

        modeloSelect.innerHTML = '<option value="">— Modelo —</option>';
        modeloSelect.disabled = true;
        btnIMEI.disabled = true;

        const marcaId = marcaSelect.value;
        if (!marcaId) return;

        // Cargar modelos de la marca seleccionada
        fetch(`/catalogo/modelos-por-marca/${marcaId}`)
            .then(response => response.json())
            .then(modelos => {
                if (modelos.length === 0) {
                    modeloSelect.innerHTML = '<option value="">Sin modelos</option>';
                    return;
                }
                modeloSelect.innerHTML = '<option value="">— Seleccione modelo —</option>';
                modelos.forEach(m => {
                    modeloSelect.innerHTML += `<option value="${m.id}">${m.nombre}</option>`;
                });
                modeloSelect.disabled = false;

                // Pre-seleccionar modelo si se indicó (ej: al cargar producto existente)
                if (preseleccionarModeloId) {
                    modeloSelect.value = preseleccionarModeloId;
                    modeloSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })
            .catch(() => {
                modeloSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    }

    function actualizarTrasCambioModelo(index) {
        const modeloSelect = document.getElementById(`modelo_select_${index}`);
        const colorSelect = document.getElementById(`color_${index}`);
        const btnIMEI = document.getElementById(`btn_imei_${index}`);
        const productoSelect = document.getElementById(`producto_select_${index}`);
        const producto = catalogoProductos.find(p => p.id == productoSelect.value);

        if (!producto) return;

        // El botón de IMEIs requiere tipo 'serie' + modelo seleccionado
        if (producto.tipo_inventario === 'serie') {
            btnIMEI.disabled = !modeloSelect.value;
        }
    }

    function actualizarVistaIMEI(index) {
        const colorSelect   = document.getElementById(`color_${index}`);
        const btnIMEI       = document.getElementById(`btn_imei_${index}`);
        const productoSelect = document.getElementById(`producto_select_${index}`);
        const producto = catalogoProductos.find(p => p.id == productoSelect.value);

        // Solo habilitar el botón IMEI si el producto es de tipo 'serie' y tiene modelo
        if (producto && producto.tipo_inventario === 'serie') {
            const modeloSelect = document.getElementById(`modelo_select_${index}`);
            btnIMEI.disabled = !modeloSelect.value;
        }
        // Para productos regulares el botón queda siempre deshabilitado
    }

    function actualizarCantidad(index) {
        calcularSubtotal(index);
        actualizarInfoIMEI(index);
    }
    function actualizarCantidadProducto(productoId, cantidad) {
        if (productosSeleccionadosIds.has(productoId)) {
            cantidadesSeleccionadas[productoId] = parseInt(cantidad) || 1;
        }
        actualizarContadorUnidades();
    }


    function propagarPrecio(index) {
        const precio = parseFloat(document.getElementById(`precio_${index}`)?.value);
        if (!precio || precio <= 0) return;

        const productoId = document.getElementById(`producto_select_${index}`)?.value;
        if (!productoId) return;

        // Obtener capacidad de la variante seleccionada en esta fila
        const varianteSelect = document.getElementById(`variante_select_${index}`);
        const capacidad = varianteSelect
            ? (varianteSelect.selectedOptions[0]?.dataset?.capacidad ?? '')
            : '';

        let propagados = 0;

        document.querySelectorAll('[id^="producto_select_"]').forEach(sel => {
            const idx = sel.id.replace('producto_select_', '');
            if (idx === String(index)) return;           // skip fila actual
            if (sel.value !== productoId) return;        // distinto producto

            // Comparar capacidad
            const vs = document.getElementById(`variante_select_${idx}`);
            const capOtra = vs ? (vs.selectedOptions[0]?.dataset?.capacidad ?? '') : '';
            if (capOtra !== capacidad) return;           // distinta capacidad

            // Solo rellenar si el precio está vacío o en 0
            const inputPrecio = document.getElementById(`precio_${idx}`);
            if (!inputPrecio) return;
            const valorActual = parseFloat(inputPrecio.value) || 0;
            if (valorActual > 0) return;                 // ya tiene precio, no pisar

            inputPrecio.value = precio.toFixed(2);
            calcularSubtotal(parseInt(idx));
            propagados++;
        });

        if (propagados > 0) {
            mostrarToastPrecio(`Precio S/ ${precio.toFixed(2)} aplicado a ${propagados} fila(s) con la misma capacidad.`);
        }
    }

    function mostrarToastPrecio(mensaje) {
        let toast = document.getElementById('toastPrecio');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toastPrecio';
            toast.className = 'fixed bottom-6 right-6 z-50 bg-indigo-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 transition-opacity duration-300';
            toast.innerHTML = '<i class="fas fa-magic"></i><span id="toastPrecioMsg"></span>';
            document.body.appendChild(toast);
        }
        document.getElementById('toastPrecioMsg').textContent = mensaje;
        toast.style.opacity = '1';
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => { toast.style.opacity = '0'; }, 3000);
    }

    function calcularSubtotal(index) {
        const cantidad = parseFloat(document.getElementById(`cantidad_${index}`).value) || 0;
        const precio   = parseFloat(document.getElementById(`precio_${index}`).value) || 0;
        const moneda   = document.getElementById('tipo_moneda').value;
        const simbolo  = moneda === 'USD' ? '$' : 'S/';
        document.getElementById(`subtotal_${index}`).innerText = `${simbolo} ${(cantidad * precio).toFixed(2)}`;
        calcularTotales();
    }

    function calcularTotales() {
        const moneda  = document.getElementById('tipo_moneda').value;
        const simbolo = moneda === 'USD' ? '$' : 'S/';
        const tc      = parseFloat(document.getElementById('tipo_cambio')?.value) || 0;

        // Suma bruta de (cantidad × precio) tal como el usuario los ingresó
        let sumaBruta = 0;
        const filasProductos = [];
        document.querySelectorAll('#detallesBody tr').forEach(row => {
            const match = row.id?.match(/producto_(\d+)/);
            if (match) {
                const el = document.getElementById(`subtotal_${match[1]}`);
                const val = el ? (parseFloat(el.innerText.replace(/[^\d.]/g, '')) || 0) : 0;
                sumaBruta += val;
                filasProductos.push({ index: match[1], subtotal: val });
            }
        });

        // Costos de importación
        const tipoCompra       = document.querySelector('input[name="tipo_compra"]:checked')?.value || 'local';
        const esImportacion    = tipoCompra === 'importacion';
        const fleteUsd         = esImportacion ? (parseFloat(document.getElementById('flete_usd')?.value) || 0) : 0;
        const seguroUsd        = esImportacion ? (parseFloat(document.getElementById('seguro_usd')?.value) || 0) : 0;
        const otrosUsd         = esImportacion ? (parseFloat(document.getElementById('otros_usd')?.value) || 0) : 0;
        const impuestosUsd     = esImportacion ? (parseFloat(document.getElementById('impuestos_usd')?.value) || 0) : 0;
        const impuestosPen     = esImportacion ? (parseFloat(document.getElementById('impuestos_pen')?.value) || 0) : 0;
        const transportePen    = esImportacion ? (parseFloat(document.getElementById('transporte_local_pen')?.value) || 0) : 0;
        const percepcionPen    = esImportacion ? (parseFloat(document.getElementById('percepcion_pen')?.value) || 0) : 0;
        // CIF en USD y en PEN (separados)
        const cifUsdTotal   = fleteUsd + seguroUsd + otrosUsd + impuestosUsd;
        const cifPenTotal   = impuestosPen + transportePen + percepcionPen;
        const totalCIF      = cifUsdTotal + cifPenTotal; // solo para mostrar en caja CIF (mixto)

        // CIF convertido a la moneda de la compra (para sumarlo correctamente al total)
        const cifEnMonedaCompra = esImportacion ? (
            moneda === 'USD'
                ? (cifUsdTotal + (tc > 0 ? cifPenTotal / tc : 0))
                : (cifUsdTotal * tc + cifPenTotal)
        ) : 0;

        // Opciones de IGV
        const tipoOperacion  = document.getElementById('tipo_operacion').value;
        const incluyeIGV     = document.getElementById('incluir_igv').checked;
        const precioConIGV   = document.getElementById('precio_incluye_igv').checked;

        // IGV aplica SOLO sobre los productos (subtotalNeto), no sobre los costos CIF
        let subtotalNeto = sumaBruta;
        let igv          = 0;
        let total        = sumaBruta;

        if (tipoOperacion === '01' && incluyeIGV) {
            if (precioConIGV) {
                subtotalNeto = sumaBruta / 1.18;
                igv          = sumaBruta - sumaBruta / 1.18;
                total        = sumaBruta; // = subtotalNeto + igv
            } else {
                subtotalNeto = sumaBruta;
                igv          = sumaBruta * 0.18;
                total        = sumaBruta + igv;
            }
        }

        // Agregar costos CIF al total final (ya convertidos a moneda de la compra)
        total += cifEnMonedaCompra;

        // Mostrar etiqueta de subtotal
        const lblSinIgv = document.getElementById('lblSinIgv');
        if (precioConIGV && tipoOperacion === '01' && incluyeIGV) {
            lblSinIgv.classList.remove('hidden');
        } else {
            lblSinIgv.classList.add('hidden');
        }

        // Mostrar valores
        document.getElementById('subtotal').innerText = `${simbolo} ${subtotalNeto.toFixed(2)}`;
        document.getElementById('igv').innerText      = `${simbolo} ${igv.toFixed(2)}`;
        document.getElementById('total').innerText    = `${simbolo} ${total.toFixed(2)}`;

        // Costos importacion en totales
        const totalesImportacion = document.getElementById('totales_importacion');
        if (esImportacion) {
            totalesImportacion.classList.remove('hidden');
            document.getElementById('total_flete').textContent          = `$ ${fleteUsd.toFixed(2)}`;
            document.getElementById('total_seguro').textContent         = `$ ${seguroUsd.toFixed(2)}`;
            document.getElementById('total_otros').textContent          = `$ ${otrosUsd.toFixed(2)}`;
            document.getElementById('total_impuestos_pen').textContent  = `S/ ${impuestosPen.toFixed(2)}`;
            document.getElementById('total_transporte_pen').textContent = `S/ ${transportePen.toFixed(2)}`;
            document.getElementById('total_percepcion_pen').textContent = `S/ ${percepcionPen.toFixed(2)}`;
        } else {
            totalesImportacion.classList.add('hidden');
        }

        // Actualizar resumen CIF
        if (document.getElementById('cif_flete')) {
            document.getElementById('cif_flete').textContent         = `$ ${fleteUsd.toFixed(2)}`;
            document.getElementById('cif_seguro').textContent        = `$ ${seguroUsd.toFixed(2)}`;
            document.getElementById('cif_otros').textContent         = `$ ${otrosUsd.toFixed(2)}`;
            document.getElementById('cif_impuestos_pen').textContent = `S/ ${impuestosPen.toFixed(2)}`;
            document.getElementById('cif_transporte').textContent    = `S/ ${transportePen.toFixed(2)}`;
            document.getElementById('cif_percepcion').textContent    = `S/ ${percepcionPen.toFixed(2)}`;
            // Total CIF en moneda de la compra (con conversión real)
            document.getElementById('cif_total').textContent         = `${simbolo} ${cifEnMonedaCompra.toFixed(2)}`;
        }

        // Prorrateo por producto
        actualizarProrrateo(filasProductos, sumaBruta, totalCIF, tipoCompra);

        // Equivalente en PEN solo si moneda es USD y hay tipo de cambio
        const eqDiv = document.getElementById('equivalentePEN');
        if (moneda === 'USD' && tc > 0) {
            const totalPEN = total * tc;
            document.getElementById('totalPEN').textContent = `S/ ${totalPEN.toFixed(2)}`;
            document.getElementById('tcUsado').textContent  = `1 $ = S/ ${tc.toFixed(3)}`;
            eqDiv.classList.remove('hidden');
        } else {
            eqDiv.classList.add('hidden');
        }

        // Deshabilitar opciones IGV si tipo de operación no es gravado
        const igvCheckbox = document.getElementById('incluir_igv');
        const igvLabel    = igvCheckbox.parentElement;
        const toggleWrap  = document.getElementById('togglePrecioIgvWrap');
        if (tipoOperacion !== '01') {
            igvCheckbox.checked = false;
            igvLabel.classList.add('opacity-40', 'pointer-events-none');
            toggleWrap.classList.add('opacity-40', 'pointer-events-none');
        } else {
            igvLabel.classList.remove('opacity-40', 'pointer-events-none');
            toggleWrap.classList.remove('opacity-40', 'pointer-events-none');
        }
    }

    function actualizarProrrateo(filas, sumaBruta, totalCIF, tipoCompra) {
        const seccion = document.getElementById('prorrateo_section');
        const tabla   = document.getElementById('prorrateo_tabla');
        if (!seccion || !tabla) return;

        if (tipoCompra !== 'importacion' || filas.length === 0 || totalCIF === 0) {
            seccion.classList.add('hidden');
            return;
        }

        seccion.classList.remove('hidden');

        if (sumaBruta === 0) {
            tabla.innerHTML = '<p class="text-gray-400 italic">Ingresa precios para calcular el prorrateo.</p>';
            return;
        }

        const moneda  = document.getElementById('tipo_moneda').value;
        const simbolo = moneda === 'USD' ? '$' : 'S/';

        let html = `<div class="grid grid-cols-4 gap-2 font-semibold text-orange-700 border-b border-orange-100 pb-1 mb-1">
            <span>Producto</span><span class="text-right">Subtotal</span><span class="text-right">CIF asignado</span><span class="text-right font-bold">Costo total</span>
        </div>`;

        filas.forEach(fila => {
            const selectEl = document.getElementById(`producto_select_${fila.index}`);
            const nombre   = selectEl?.options[selectEl.selectedIndex]?.text || `Producto ${fila.index}`;
            const proporcion  = sumaBruta > 0 ? fila.subtotal / sumaBruta : 0;
            const cifAsignado = proporcion * totalCIF;
            const costoTotal  = fila.subtotal + cifAsignado;

            html += `<div class="grid grid-cols-4 gap-2 py-0.5 text-gray-700 items-center">
                <span class="truncate text-xs" title="${nombre}">${nombre}</span>
                <span class="text-right text-xs">${simbolo} ${fila.subtotal.toFixed(2)}</span>
                <span class="text-right text-xs text-orange-600">+ ${simbolo} ${cifAsignado.toFixed(2)}</span>
                <span class="text-right text-xs font-semibold text-gray-900">${simbolo} ${costoTotal.toFixed(2)}</span>
            </div>`;
        });

        tabla.innerHTML = html;
    }

    function cambiarTipoCompra(tipo) {
        const seccion = document.getElementById('seccion_importacion');
        if (tipo === 'importacion') {
            seccion.classList.remove('hidden');
        } else {
            seccion.classList.add('hidden');
        }
        calcularTotales();
    }
    // ============================================
    // FUNCIONES DEL MODAL DE PRODUCTOS
    // ============================================
    function abrirModalProductos() {
        modalProductos.classList.remove('hidden');
        modalProductos.classList.add('flex');
        setTimeout(() => {
            buscador.focus();
            buscarProductos('');
        }, 100);
    }

    if (buscador) {
        buscador.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const termino = this.value.trim();
            
            this.classList.add('border-blue-500');
            
            timeoutBusqueda = setTimeout(() => {
                this.classList.remove('border-blue-500');
                buscarProductos(termino);
            }, 300);
        });
    }

    function buscarProductos(termino) {
        resultadosDiv.innerHTML = '';
        cargandoDiv.classList.remove('hidden');
        sinResultadosDiv.classList.add('hidden');

        fetch(`/compras/buscar-productos?q=${encodeURIComponent(termino)}`)
            .then(response => {
                if (!response.ok) throw new Error(`Error ${response.status} del servidor`);
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    // La sesión expiró y el servidor redirigió a la página de login
                    window.location.href = '/login';
                    throw new Error('Sesión expirada. Redirigiendo al login...');
                }
                return response.json();
            })
            .then(productos => {
                cargandoDiv.classList.add('hidden');
                
                // Guardar TODOS los productos originales
                productosOriginales = productos;

                // Actualizar el catálogo local con los datos frescos (variantes incluidas)
                productos.forEach(p => {
                    const idx = catalogoProductos.findIndex(c => c.id === p.id);
                    if (idx !== -1) {
                        catalogoProductos[idx] = { ...catalogoProductos[idx], ...p };
                    } else {
                        catalogoProductos.push(p);
                    }
                });

                // Filtrar por categoría si no es 'todos'
                let productosFiltrados = productos;
                if (categoriaActual !== 'todos') {
                    productosFiltrados = productos.filter(p => p.categoria_id == categoriaActual);
                }

                if (productosFiltrados.length === 0) {
                    sinResultadosDiv.classList.remove('hidden');
                    return;
                }

                // Mostrar resultados manteniendo selecciones
                mostrarResultadosConSeleccion(productosFiltrados);
            })
            .catch(error => {
                console.error('Error:', error);
                cargandoDiv.classList.add('hidden');
                resultadosDiv.innerHTML = `
                    <div class="col-span-3 text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-2"></i>
                        <p class="text-red-600">Error al cargar productos</p>
                        <p class="text-xs text-gray-500 mt-2">${error.message}</p>
                    </div>
                `;
            });
    }
    // Actualizar la función mostrarResultados para incluir checkboxes
    // Actualizar la función mostrarResultados para incluir checkboxes y cantidad
    function mostrarResultados(productos) {
        productosFiltrados = productos;
        
        resultadosDiv.innerHTML = productos.map(p => {
            const cantidadGuardada = cantidadesSeleccionadas[p.id] || 1;
            
            return `
                <div class="bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-blue-500 hover:shadow-lg transition-all group">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center mt-1">
                            <input type="checkbox"
                                class="producto-checkbox w-5 h-5 rounded border-gray-300 text-blue-900 focus:ring-blue-500"
                                value="${p.id}"
                                data-producto-id="${p.id}"
                                onchange="actualizarSeleccion(this, ${p.id})"
                                ${productosSeleccionadosIds.has(p.id) ? 'checked' : ''}>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                            <i class="fas fa-box text-blue-900"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900">${p.nombre}</h4>
                            <p class="text-sm text-gray-600">${p.marca || ''} ${p.modelo || ''}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs px-2 py-0.5 bg-gray-100 rounded-full text-gray-600">
                                    ${p.categoria || 'Sin categoría'}
                                </span>
                                ${p.tipo_inventario === 'serie' ?
                                    '<span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full"><i class="fas fa-microchip mr-1"></i>IMEI</span>' :
                                    '<span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full"><i class="fas fa-boxes mr-1"></i>Stock</span>'}
                            </div>
                            <!-- NUEVO: Selector de cantidad -->
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-xs text-gray-500">Cantidad:</span>
                                <input type="number" 
                                    class="cantidad-input w-20 px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    value="${cantidadGuardada}"
                                    min="1"
                                    data-producto-id="${p.id}"
                                    onchange="actualizarCantidadProducto(${p.id}, this.value)"
                                    ${productosSeleccionadosIds.has(p.id) ? '' : 'disabled'}>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Agregar el botón de crear producto al final
        resultadosDiv.innerHTML += `
            <div class="col-span-full mt-3 pt-3 border-t border-gray-200 text-center">
                <button type="button"
                        onclick="abrirModalCrearProducto(document.getElementById('buscadorProductos').value)"
                        class="inline-flex items-center text-sm text-green-700 hover:text-green-900 transition font-medium">
                    <i class="fas fa-plus-circle mr-1"></i>
                    ¿No está el producto? Créalo aquí
                </button>
            </div>`;
    }
    function mostrarResultadosConSeleccion(productos) {
        resultadosDiv.innerHTML = productos.map(p => {
            // ── Producto CON variantes ────────────────────────────────────────
            if (p.tiene_variantes && p.variantes && p.variantes.length > 0) {
                // Registrar producto en el registry (evita JSON en handlers inline)
                productosModal[p.id] = p;
                const variantesHTML = p.variantes.map(v => {
                    const key  = `${p.id}_${v.id}`;
                    variantesModal[key] = v;  // registrar variante
                    const sel  = !!variantesSeleccionadas[key];
                    const cant = sel ? variantesSeleccionadas[key].cantidad : 1;
                    const label = [v.color_nombre, v.capacidad].filter(Boolean).join(' / ') || 'Base';
                    const dot = v.color_hex
                        ? `<span class="w-3 h-3 rounded-full border border-white shadow shrink-0 inline-block" style="background-color:${v.color_hex}"></span>`
                        : '<i class="fas fa-circle text-gray-300 text-xs shrink-0"></i>';
                    const stockClr = v.stock_actual > 0 ? 'text-green-600' : 'text-red-500';
                    const stockTxt = v.stock_actual > 0 ? `${v.stock_actual} u.` : 'Sin stock';
                    const bordClr  = sel ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50';
                    return `
                        <div id="vcard_${key}" class="flex items-center gap-2 px-2 py-1.5 rounded-lg border ${bordClr} transition-all cursor-pointer"
                             onclick="npToggleVariante(${p.id},${v.id})">
                            <input type="checkbox"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-400 pointer-events-none"
                                   ${sel ? 'checked' : ''}>
                            ${dot}
                            <span class="text-xs font-medium text-gray-800 flex-1 truncate">${label}</span>
                            <span class="text-xs ${stockClr} font-medium shrink-0">${stockTxt}</span>
                        </div>`;
                }).join('');

                const totalSelVariantes = p.variantes.filter(v => !!variantesSeleccionadas[`${p.id}_${v.id}`]).length;
                const badgeSel = totalSelVariantes > 0
                    ? `<span class="text-xs px-2 py-0.5 bg-indigo-600 text-white rounded-full font-semibold">${totalSelVariantes} sel.</span>` : '';

                return `
                    <div class="bg-white border-2 border-gray-200 rounded-xl p-3 hover:border-indigo-300 hover:shadow-lg transition-all">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-9 h-9 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                                <i class="fas fa-mobile-alt text-indigo-600 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 text-sm truncate">${p.nombre}</h4>
                                <p class="text-xs text-gray-500">${p.marca || ''} ${p.modelo ? '· ' + p.modelo : ''}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full">
                                    <i class="fas fa-layer-group mr-1"></i>${p.variantes.length} var.
                                </span>
                                ${badgeSel}
                            </div>
                        </div>
                        <div class="space-y-1 border-t border-gray-100 pt-2">
                            ${variantesHTML}
                        </div>
                    </div>`;

            // ── Producto SIN variantes ────────────────────────────────────────
            } else {
                const sel  = productosSeleccionadosIds.has(p.id);
                const cant = cantidadesSeleccionadas[p.id] || 1;
                return `
                    <div class="bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-blue-500 hover:shadow-lg transition-all group">
                        <div class="flex items-start gap-3">
                            <input type="checkbox"
                                   class="w-5 h-5 rounded border-gray-300 text-blue-900 focus:ring-blue-500 mt-1 shrink-0"
                                   value="${p.id}"
                                   onchange="actualizarSeleccion(this, ${p.id})"
                                   ${sel ? 'checked' : ''}>
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                                <i class="fas fa-box text-blue-900"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 text-sm truncate">${p.nombre}</h4>
                                <p class="text-xs text-gray-500">${p.marca || ''} ${p.modelo ? '· ' + p.modelo : ''}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 bg-gray-100 rounded-full text-gray-600">${p.categoria || '—'}</span>
                                    ${p.tipo_inventario === 'serie'
                                        ? '<span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full"><i class="fas fa-microchip mr-1"></i>IMEI</span>'
                                        : '<span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full"><i class="fas fa-boxes mr-1"></i>Stock</span>'}
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Cant.:</span>
                                    <input type="number"
                                           class="cantidad-input w-16 px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           value="${cant}" min="1"
                                           data-producto-id="${p.id}"
                                           onchange="actualizarCantidadProducto(${p.id}, this.value)"
                                           ${sel ? '' : 'disabled'}>
                                </div>
                            </div>
                        </div>
                    </div>`;
            }
        }).join('');

        // Botón crear producto
        resultadosDiv.innerHTML += `
            <div class="col-span-full mt-3 pt-3 border-t border-gray-200 text-center">
                <button type="button"
                        onclick="abrirModalCrearProducto(document.getElementById('buscadorProductos').value)"
                        class="inline-flex items-center text-sm text-green-700 hover:text-green-900 transition font-medium">
                    <i class="fas fa-plus-circle mr-1"></i>¿No está el producto? Créalo aquí
                </button>
            </div>`;
    }
    function actualizarSeleccion(checkbox, productoId) {
        const cantidadInput = document.querySelector(`.cantidad-input[data-producto-id="${productoId}"]`);
        
        if (checkbox.checked) {
            productosSeleccionadosIds.add(productoId);
            cantidadInput.disabled = false;
            const cantidad = parseInt(cantidadInput.value) || 1;
            cantidadesSeleccionadas[productoId] = cantidad;
        } else {
            productosSeleccionadosIds.delete(productoId);
            cantidadInput.disabled = true;
            delete cantidadesSeleccionadas[productoId];
        }
        
        document.getElementById('productosSeleccionadosCount').innerText = 
            `${productosSeleccionadosIds.size} productos seleccionados`;
        actualizarContadorUnidades();
    }

    // Función para agregar productos seleccionados (simples + variantes)
    function agregarProductosSeleccionados() {
        const tieneSimples   = productosSeleccionadosIds.size > 0;
        const tieneVariantes = Object.keys(variantesSeleccionadas).length > 0;

        if (!tieneSimples && !tieneVariantes) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Selecciona al menos un producto o variante'
            });
            return;
        }

        // Capturar estado antes de cerrar el modal
        const cantidadesCapturadas = { ...cantidadesSeleccionadas };
        const variantesCapturadas  = { ...variantesSeleccionadas };
        const ids = Array.from(productosSeleccionadosIds);

        // Cerrar modal (limpia cantidadesSeleccionadas y variantesSeleccionadas)
        cerrarModalProductos();

        // ── Agregar variantes directamente (sin fetch — datos ya disponibles) ──
        Object.values(variantesCapturadas).forEach(({ producto, varianteId, cantidad }) => {
            agregarFilaConVariante(producto, varianteId, cantidad);
        });

        // Si no hay productos simples, mostrar éxito de inmediato
        if (ids.length === 0) {
            const total = Object.keys(variantesCapturadas).length;
            Swal.fire({
                icon: 'success',
                title: 'Variantes agregadas',
                text: `${total} variante(s) agregadas correctamente`,
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // ── Agregar productos simples (requiere fetch) ──
        Swal.fire({
            title: 'Agregando productos...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const totalIds   = ids.length;
        let procesados   = 0;

        ids.forEach(id => {
            fetch(`/compras/producto/${id}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error al cargar producto');
                    return response.json();
                })
                .then(producto => {
                    const cantidad = cantidadesCapturadas[id] || 1;
                    agregarProductoConCantidad(producto, cantidad);
                    procesados++;
                    if (procesados === totalIds) {
                        Swal.close();
                        const total = totalIds + Object.keys(variantesCapturadas).length;
                        Swal.fire({
                            icon: 'success',
                            title: 'Productos agregados',
                            text: `${total} elemento(s) agregados correctamente`,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    procesados++;
                    if (procesados === totalIds) {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron agregar algunos productos'
                        });
                    }
                });
        });
    }

 // NUEVA FUNCIÓN para agregar producto con cantidad específica (N filas)
    function agregarProductoConCantidad(producto, cantidad) {
        const filas = parseInt(cantidad) || 1;
        
        // Validar que la cantidad sea válida
        if (filas < 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Cantidad inválida',
                text: 'La cantidad debe ser al menos 1',
                confirmButtonColor: '#1e3a8a'
            });
            return;
        }
        
        // Crear las filas
        for (let i = 0; i < filas; i++) {
            agregarProducto();
            const index = contadorProductos - 1;

            const selectProducto = document.getElementById(`producto_select_${index}`);
            const inputCantidad = document.getElementById(`cantidad_${index}`);
            const inputPrecio = document.getElementById(`precio_${index}`);

            if (selectProducto) {
                selectProducto.value = producto.id;
                inputCantidad.value = 1; // Cada fila es 1 unidad

                const event = new Event('change', { bubbles: true });
                selectProducto.dispatchEvent(event);
            }
        }
        
        // Mostrar mensaje de éxito
        Swal.fire({
            icon: 'success',
            title: `${filas} unidades agregadas`,
            text: `Se agregaron ${filas} filas para ${producto.nombre}`,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
    // Limpiar selección al cerrar modal
    function cerrarModalProductos() {
        modalProductos.classList.add('hidden');
        modalProductos.classList.remove('flex');
        buscador.value = '';
        resultadosDiv.innerHTML = '';
        productosSeleccionadosIds.clear();
        cantidadesSeleccionadas  = {};
        variantesSeleccionadas   = {};
        productosModal           = {};
        variantesModal           = {};
        document.getElementById('productosSeleccionadosCount').innerText = '0 productos seleccionados';
        document.getElementById('totalUnidadesCount').innerText = '(0 unidades)';

        // Resetear filtro a 'todos'
        if (document.querySelector('.categoria-filter.active')) {
            filtrarPorCategoria('todos');
        }
    }

    function seleccionarProductoModal(id) {
        const productoDiv = event?.currentTarget;
        if (productoDiv) {
            productoDiv.classList.add('opacity-50', 'pointer-events-none');
        }
        
        fetch(`/compras/producto/${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error al cargar el producto');
                }
                return response.json();
            })
            .then(producto => {
                agregarProductoConDatos(producto);
                cerrarModalProductos();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar el producto: ' + error.message);
            })
            .finally(() => {
                if (productoDiv) {
                    productoDiv.classList.remove('opacity-50', 'pointer-events-none');
                }
            });
    }

    function agregarProductoConDatos(producto) {
        agregarProducto();
        const index = contadorProductos - 1;
        
        const selectProducto = document.getElementById(`producto_select_${index}`);
        const selectMarca = document.getElementById(`marca_select_${index}`);
        const selectModelo = document.getElementById(`modelo_select_${index}`);
        const selectColor = document.getElementById(`color_${index}`);
        
        if (selectProducto) {
            selectProducto.value = producto.id;

            const event = new Event('change', { bubbles: true });
            // Disparar change en producto — esto llama a cargarDetallesProducto,
            // que ya se encarga de seleccionar marca, cargar modelos y pre-seleccionar modelo/color
            selectProducto.dispatchEvent(event);
        }
    }

    // ============================================
    // FUNCIONES DEL MODAL DE IMEIs
    // ============================================
    function gestionarIMEIs(index) {
        const select        = document.getElementById(`producto_select_${index}`);
        const modeloSelect  = document.getElementById(`modelo_select_${index}`);
        const colorSelect   = document.getElementById(`color_${index}`);
        const varianteSelect = document.getElementById(`variante_select_${index}`);
        const cantidad      = parseInt(document.getElementById(`cantidad_${index}`).value) || 1;

        if (!select.value) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Primero seleccione un producto' });
            return;
        }

        const producto = catalogoProductos.find(p => p.id == select.value);
        let subtitulo = '';

        // ── Producto CON variantes ────────────────────────────────────────
        if (varianteSelect) {
            if (!varianteSelect.value) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Primero seleccione una variante' });
                return;
            }
            const opt = varianteSelect.selectedOptions[0];
            subtitulo = opt.text.split('—')[0].trim();   // "Negro / 128GB" parte del label

        // ── Producto SIN variantes (flujo clásico) ────────────────────────
        } else {
            if (!modeloSelect.value) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Primero seleccione un modelo' });
                return;
            }
            const modeloNombre = modeloSelect.options[modeloSelect.selectedIndex].text;
            const colorNombre  = colorSelect.value ? colorSelect.options[colorSelect.selectedIndex].text : '';
            subtitulo = colorNombre ? `${modeloNombre} · ${colorNombre}` : modeloNombre;
        }

        document.getElementById('imeiModalTitle').innerHTML = `
            <i class="fas fa-microchip mr-3"></i>
            ${producto?.nombre || ''} · ${subtitulo}
        `;

        productoEnEdicion = index;
        
        const imeisGuardados = imeisPorFila[index] || [];
        generarInputsIMEI(cantidad, imeisGuardados);
        
        document.getElementById('imeiModal').classList.remove('hidden');
        document.getElementById('imeiModal').classList.add('flex');
        actualizarContadorIMEI();
    }

    // ── Modo IMEI (manual / pistola) ─────────────────────────────────────────
    let modoIMEIActual = 'manual';

    function activarModoIMEI(modo) {
        modoIMEIActual = modo;
        document.getElementById('panel_manual').classList.toggle('hidden', modo !== 'manual');
        document.getElementById('panel_pistola').classList.toggle('hidden', modo !== 'pistola');
        document.getElementById('tab_manual').className  = modo === 'manual'
            ? 'flex-1 py-2.5 text-sm font-medium text-purple-700 border-b-2 border-purple-600 bg-white transition'
            : 'flex-1 py-2.5 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition';
        document.getElementById('tab_pistola').className = modo === 'pistola'
            ? 'flex-1 py-2.5 text-sm font-medium text-purple-700 border-b-2 border-purple-600 bg-white transition'
            : 'flex-1 py-2.5 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition';
        if (modo === 'pistola') {
            pistolaRenderLista();
            setTimeout(() => document.getElementById('pistola_input')?.focus(), 100);
        }
    }

    // ── Pistola: lógica ───────────────────────────────────────────────────────
    function pistolaManejarTecla(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const input = document.getElementById('pistola_input');
        const valor = input.value.trim();

        if (valor.length !== 15) {
            input.classList.add('border-red-400', 'bg-red-50');
            setTimeout(() => input.classList.remove('border-red-400', 'bg-red-50'), 600);
            return;
        }

        // Verificar duplicado
        const lista = imeisPorFila[productoEnEdicion] || [];
        if (lista.includes(valor)) {
            Swal.fire({ icon: 'warning', title: 'IMEI duplicado', text: `${valor} ya fue escaneado.`, timer: 1800, showConfirmButton: false });
            input.select();
            return;
        }

        lista.push(valor);
        imeisPorFila[productoEnEdicion] = lista;
        input.value = '';
        input.classList.add('border-green-400', 'bg-green-50');
        setTimeout(() => input.classList.remove('border-green-400', 'bg-green-50'), 400);
        pistolaRenderLista();
        actualizarContadorIMEI();
    }

    function pistolaRenderLista() {
        const lista  = imeisPorFila[productoEnEdicion] || [];
        const div    = document.getElementById('pistola_lista');
        const vacio  = document.getElementById('pistola_vacio');
        const contEl = document.getElementById('pistola_count');
        if (contEl) contEl.textContent = lista.length;

        if (lista.length === 0) {
            div.innerHTML = '';
            vacio?.classList.remove('hidden');
            return;
        }
        vacio?.classList.add('hidden');
        div.innerHTML = lista.map((imei, i) => `
            <div class="flex items-center justify-between px-3 py-2 rounded-lg ${i % 2 === 0 ? 'bg-gray-50' : 'bg-white'} border border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 text-xs font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                    <span class="font-mono text-sm text-gray-800 tracking-wider">${imei}</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    <button type="button" onclick="pistolaEliminar(${i})"
                            class="text-red-400 hover:text-red-600 transition text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>`).join('');
    }

    function pistolaEliminar(idx) {
        const lista = imeisPorFila[productoEnEdicion] || [];
        lista.splice(idx, 1);
        imeisPorFila[productoEnEdicion] = lista;
        pistolaRenderLista();
        actualizarContadorIMEI();
    }

    function pistolaBorrarUltimo() {
        const lista = imeisPorFila[productoEnEdicion] || [];
        if (!lista.length) return;
        lista.pop();
        imeisPorFila[productoEnEdicion] = lista;
        pistolaRenderLista();
        actualizarContadorIMEI();
    }

    // ── Inputs manuales ───────────────────────────────────────────────────────
    function generarInputsIMEI(cantidad, imeisGuardados = []) {
        const container = document.getElementById('imeiContainer');
        let html = '';

        for (let i = 0; i < cantidad; i++) {
            const valor = imeisGuardados[i] || '';
            const esValido = valor.length === 15 ? 'border-green-500 bg-green-50' : '';

            html += `
                <div class="grid grid-cols-12 gap-3 items-center" id="imei_row_${i}">
                    <div class="col-span-1 text-sm font-medium text-gray-600 text-center bg-gray-100 py-2 rounded-lg">
                        ${i + 1}
                    </div>
                    <div class="col-span-11">
                        <input type="text"
                            class="imei-input w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 font-mono text-lg tracking-wider ${esValido}"
                            placeholder="Ingrese IMEI de 15 dígitos"
                            value="${valor}"
                            maxlength="15"
                            autocomplete="off"
                            data-index="${i}"
                            oninput="this.value = this.value.replace(/[^0-9]/g, ''); validarIMEIInput(this)"
                            onkeydown="imeiManualTecla(event, ${i})">
                    </div>
                </div>`;
        }

        container.innerHTML = html;
        actualizarContadorIMEI();
    }

    function imeiManualTecla(e, idx) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const inputs = document.querySelectorAll('.imei-input');
        const actual = inputs[idx];
        if (actual?.value.length === 15) {
            const siguiente = inputs[idx + 1];
            if (siguiente) {
                siguiente.focus();
                siguiente.select();
            }
            // Si es el último y está lleno, el foco queda ahí (puede usar Tab o Guardar)
        }
    }
    function actualizarContadorUnidades() {
        const simplesTotal   = Object.values(cantidadesSeleccionadas).reduce((a, b) => a + b, 0);
        const variantesTotal = Object.values(variantesSeleccionadas).reduce((a, s) => a + (s.cantidad || 1), 0);
        document.getElementById('totalUnidadesCount').innerText = `(${simplesTotal + variantesTotal} unidades)`;
    }

    function npToggleVariante(prodId, varId) {
        const key      = `${prodId}_${varId}`;
        const estaSelec = !!variantesSeleccionadas[key];
        const checkbox  = document.querySelector(`#vcard_${key} input[type="checkbox"]`);
        if (checkbox) checkbox.checked = !estaSelec;
        actualizarSeleccionVarianteEstado(key, prodId, varId, !estaSelec);
    }

    function actualizarSeleccionVariante(checkbox, prodId, varId) {
        const key = `${prodId}_${varId}`;
        actualizarSeleccionVarianteEstado(key, prodId, varId, checkbox.checked);
    }

    function actualizarSeleccionVarianteEstado(key, prodId, varId, seleccionado) {
        const producto = productosModal[prodId];
        const variante = variantesModal[key];
        const card     = document.getElementById(`vcard_${key}`);

        if (seleccionado) {
            variantesSeleccionadas[key] = {
                productoId: producto.id,
                varianteId: variante.id,
                cantidad:   1,
                producto,
                variante,
            };
            if (card) {
                card.classList.replace('border-gray-200', 'border-indigo-400');
                card.classList.replace('bg-gray-50',    'bg-indigo-50');
            }
        } else {
            delete variantesSeleccionadas[key];
            if (card) {
                card.classList.replace('border-indigo-400', 'border-gray-200');
                card.classList.replace('bg-indigo-50',     'bg-gray-50');
            }
        }
        actualizarContadorUnidades();
    }

    function actualizarCantidadVariante(key, cantidad) {
        if (variantesSeleccionadas[key]) {
            variantesSeleccionadas[key].cantidad = parseInt(cantidad) || 1;
        }
        actualizarContadorUnidades();
    }

    /**
     * Agrega `cantidad` filas en la tabla de detalles, pre-seleccionando
     * el producto y la variante indicados.
     */
    function agregarFilaConVariante(producto, varianteId, cantidad) {
        const filas = parseInt(cantidad) || 1;
        for (let i = 0; i < filas; i++) {
            agregarProducto();
            const index = contadorProductos - 1;

            const selectProducto = document.getElementById(`producto_select_${index}`);
            if (!selectProducto) continue;

            selectProducto.value = producto.id;
            // Disparar change → cargarDetallesProducto (síncrono) renderiza el selector de variante
            selectProducto.dispatchEvent(new Event('change', { bubbles: true }));

            // Pre-seleccionar la variante en el dropdown recién renderizado
            const varianteSelect = document.getElementById(`variante_select_${index}`);
            if (varianteSelect) {
                varianteSelect.value = varianteId;
                seleccionarVariante(index);   // actualiza badge de stock y botón IMEI
            }

            const inputCantidad = document.getElementById(`cantidad_${index}`);
            if (inputCantidad) inputCantidad.value = 1;   // cada fila = 1 unidad

            calcularSubtotal(index);
        }
    }

    function validarIMEIInput(input) {
        const valor = input.value.trim();
        if (valor.length === 15) {
            input.classList.add('border-green-500', 'bg-green-50');
            input.classList.remove('border-red-500', 'bg-red-50');
        } else if (valor.length > 0) {
            input.classList.remove('border-green-500', 'bg-green-50');
            input.classList.add('border-red-500', 'bg-red-50');
        } else {
            input.classList.remove('border-red-500', 'bg-red-50', 'border-green-500', 'bg-green-50');
        }
        actualizarContadorIMEI();
    }

    function actualizarContadorIMEI() {
        const inputs = document.querySelectorAll('.imei-input');
        const total = inputs.length;
        const validos = Array.from(inputs).filter(input => input.value.trim().length === 15).length;
        
        document.getElementById('imeiTotalCount').innerText = `${validos}/${total}`;
    }

    function guardarIMEIs() {
        let imeis = [];

        if (modoIMEIActual === 'pistola') {
            // En modo pistola los IMEIs ya están en imeisPorFila
            imeis = imeisPorFila[productoEnEdicion] || [];
            if (imeis.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Sin IMEIs', text: 'Escanea al menos un IMEI.', confirmButtonColor: '#7c3aed' });
                return;
            }
        } else {
            // Modo manual: leer inputs
            const inputs = document.querySelectorAll('.imei-input');
            let valido = true;
            let primerError = null;

            inputs.forEach(input => {
                const valor = input.value.trim();
                if (valor.length !== 15) {
                    input.classList.add('border-red-500', 'bg-red-50');
                    valido = false;
                    if (!primerError) primerError = input;
                } else {
                    input.classList.remove('border-red-500', 'bg-red-50');
                    input.classList.add('border-green-500', 'bg-green-50');
                    imeis.push(valor);
                }
            });

            if (!valido) {
                Swal.fire({ icon: 'error', title: 'Error de validación', text: 'Todos los IMEI deben tener exactamente 15 dígitos numéricos', confirmButtonColor: '#d33' });
                if (primerError) primerError.focus();
                return;
            }
        }

        if (productoEnEdicion !== null) {
            const idx = productoEnEdicion;

            // Eliminar inputs ocultos anteriores
            document.querySelectorAll(`[data-imei-row="${idx}"]`).forEach(el => el.remove());

            // Guardar en objeto
            imeisPorFila[idx] = imeis;

            // Crear inputs ocultos con estructura correcta
            imeis.forEach((imei, i) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `detalles[${idx}][imeis][${i}][codigo_imei]`;
                input.setAttribute('data-imei-row', idx);
                input.value = imei;
                document.getElementById('compraForm').appendChild(input);
            });

            // Actualizar info en la tabla
            actualizarInfoIMEI(idx);
            
            // CERRAR MODAL
            cerrarModalIMEI();
            
            // MOSTRAR MENSAJE DE ÉXITO
            Swal.fire({
                icon: 'success',
                title: '¡IMEIs guardados!',
                text: `${imeis.length} IMEI(s) registrados correctamente`,
                timer: 2000,
                showConfirmButton: false,
                position: 'center',
                background: '#ffffff',
                iconColor: '#10b981'
            });
        } else {
            cerrarModalIMEI();
        }
    }

    function generarIMEIsAleatorios() {
        const inputs = document.querySelectorAll('.imei-input');
        
        inputs.forEach(input => {
            let imei = '';
            for (let i = 0; i < 14; i++) {
                imei += Math.floor(Math.random() * 10);
            }
            
            let suma = 0;
            for (let i = 0; i < 14; i++) {
                let digito = parseInt(imei[i]);
                if (i % 2 === 0) {
                    digito *= 2;
                    if (digito > 9) digito -= 9;
                }
                suma += digito;
            }
            let verificador = (10 - (suma % 10)) % 10;
            imei += verificador;
            
            input.value = imei;
            validarIMEIInput(input);
        });
        
        Swal.fire({
            icon: 'success',
            title: 'IMEIs generados',
            text: `${inputs.length} IMEI(s) generados aleatoriamente`,
            timer: 1500,
            showConfirmButton: false
        });
    }

    function importarIMEIs() {
        const inputs = document.querySelectorAll('.imei-input');
        
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.txt,.csv';
        fileInput.onchange = function(e) {
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const contenido = e.target.result;
                const lineas = contenido.split('\n')
                    .map(line => line.trim())
                    .filter(line => line.length > 0);
                
                let importados = 0;
                lineas.forEach((linea, index) => {
                    if (index < inputs.length) {
                        const imei = linea.substring(0, 15);
                        if (imei.length === 15 && /^\d+$/.test(imei)) {
                            inputs[index].value = imei;
                            validarIMEIInput(inputs[index]);
                            importados++;
                        }
                    }
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'Importación completada',
                    text: `Se importaron ${importados} de ${Math.min(lineas.length, inputs.length)} IMEIs válidos`,
                    confirmButtonColor: '#2563eb'
                });
            };
            
            reader.readAsText(file);
        };
        
        fileInput.click();
    }
    function importarIMEIDesdeArchivo(index) {
        // Crear input file oculto
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.txt,.csv';
        
        fileInput.onchange = function(e) {
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const contenido = e.target.result;
                const lineas = contenido.split('\n')
                    .map(line => line.trim())
                    .filter(line => line.length > 0);
                
                const inputs = document.querySelectorAll('.imei-input');
                let importados = 0;
                
                lineas.forEach((linea, idx) => {
                    if (idx < inputs.length) {
                        const partes = linea.split(',');
                        const imei = partes[0].trim().substring(0, 15);
                        
                        if (imei.length === 15 && /^\d+$/.test(imei)) {
                            inputs[idx].value = imei;
                            validarIMEIInput(inputs[idx]);
                            importados++;
                            
                            // Si hay serie, guardarla (opcional)
                            if (partes[1]) {
                                // Aquí podrías guardar la serie si tienes campo para eso
                            }
                        }
                    }
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'Importación completada',
                    text: `Se importaron ${importados} de ${inputs.length} IMEIs válidos`,
                    timer: 2000,
                    showConfirmButton: false
                });
            };
            
            reader.readAsText(file);
        };
        
        fileInput.click();
    }

    function limpiarIMEIs() {
        Swal.fire({
            title: '¿Limpiar todos?',
            text: 'Esta acción eliminará todos los IMEI ingresados',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.querySelectorAll('.imei-input').forEach(input => {
                    input.value = '';
                    input.classList.remove('border-green-500', 'bg-green-50', 'border-red-500', 'bg-red-50');
                });
                actualizarContadorIMEI();
            }
        });
    }

    function cerrarModalIMEI() {
        document.getElementById('imeiModal').classList.add('hidden');
        document.getElementById('imeiModal').classList.remove('flex');
        productoEnEdicion = null;
        activarModoIMEI('manual'); // resetear al abrir siguiente vez
    }

    function actualizarInfoIMEI(index) {
        const infoDiv = document.getElementById(`imei_info_${index}`);
        const countSpan = document.getElementById(`imei_count_${index}`);
        const btnImei = document.getElementById(`btn_imei_${index}`);
        const guardados = imeisPorFila[index] || [];

        const cantidadInput = document.getElementById(`cantidad_${index}`);
        const cantidad = cantidadInput ? (parseInt(cantidadInput.value) || 1) : 1;
        const pendientes = cantidad - guardados.length;

        if (guardados.length > 0 && pendientes <= 0) {
            countSpan.innerText = guardados.length;
            infoDiv.classList.remove('hidden');
            btnImei.innerHTML = `<i class="fas fa-check-circle mr-1 text-green-600"></i>${guardados.length} IMEI(s)`;
            btnImei.classList.remove('text-orange-600', 'font-medium');
            btnImei.classList.add('text-green-700', 'font-medium');
        } else if (guardados.length > 0 && pendientes > 0) {
            countSpan.innerText = guardados.length;
            infoDiv.classList.remove('hidden');
            btnImei.innerHTML = `<i class="fas fa-exclamation-triangle mr-1 text-orange-500"></i>${guardados.length}/${cantidad} IMEIs`;
            btnImei.classList.remove('text-green-700');
            btnImei.classList.add('text-orange-600', 'font-medium');
        } else {
            infoDiv.classList.add('hidden');
            btnImei.innerHTML = '<i class="fas fa-microchip mr-1"></i>IMEIs <span class="text-red-500 font-bold">*</span>';
            btnImei.classList.remove('text-green-700', 'text-orange-600', 'font-medium');
        }
    }

    // ============================================
    // INICIALIZACIÓN
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {

        // Recalcular totales al cambiar checkbox IGV
        document.getElementById('incluir_igv').addEventListener('change', calcularTotales);

        // Recalcular totales al cambiar tipo de operación SUNAT
        document.getElementById('tipo_operacion').addEventListener('change', calcularTotales);

        // Recalcular totales al cambiar toggle "precio incluye IGV"
        document.getElementById('precio_incluye_igv').addEventListener('change', calcularTotales);

        // Ejecutar cálculo inicial para reflejar el tipo de operación por defecto
        calcularTotales();

        // Validación del formulario al enviar
        document.getElementById('compraForm').addEventListener('submit', function(e) {
            if (!document.getElementById('proveedor_id').value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Proveedor requerido',
                    text: 'Selecciona un proveedor antes de continuar.',
                    confirmButtonColor: '#1e3a8a'
                });
                document.getElementById('buscar_proveedor')?.focus();
                return false;
            }
            // Validar almacén
            if (!document.getElementById('almacen_id').value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Almacén requerido',
                    text: 'Debes seleccionar el almacén de destino.',
                    confirmButtonColor: '#1e3a8a'
                });
                document.getElementById('almacen_id')?.focus();
                return false;
            }
            // Validar que haya al menos un producto
            if (contadorProductos === 0 || !document.querySelector('#detallesBody tr')) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Productos requeridos',
                    text: 'Debes agregar al menos un producto a la compra.',
                    confirmButtonColor: '#1e3a8a'
                });
                return false;
            }

            // Validar que cada fila tenga producto, precio y variante (si aplica)
            let filaIncompleta = null;
            document.querySelectorAll('[id^="producto_select_"]').forEach(sel => {
                if (filaIncompleta) return;
                const idx = sel.id.replace('producto_select_', '');
                if (!sel.value) {
                    filaIncompleta = 'Fila ' + (parseInt(idx) + 1) + ': selecciona un producto o elimina la fila vacía.';
                    return;
                }
                const precio = parseFloat(document.getElementById('precio_' + idx)?.value) || 0;
                if (precio <= 0) {
                    filaIncompleta = 'Fila ' + (parseInt(idx) + 1) + ' (' + sel.options[sel.selectedIndex].text + '): ingresa el precio unitario.';
                    return;
                }
                const varianteSelect = document.getElementById('variante_select_' + idx);
                if (varianteSelect && !varianteSelect.value) {
                    filaIncompleta = 'Fila ' + (parseInt(idx) + 1) + ' (' + sel.options[sel.selectedIndex].text + '): selecciona una variante.';
                    return;
                }
            });
            if (filaIncompleta) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Datos incompletos',
                    text: filaIncompleta,
                    confirmButtonColor: '#1e3a8a'
                });
                return false;
            }

            // Validar IMEIs completos para productos tipo serie
            const filasSerie = [];
            document.querySelectorAll('[id^="producto_select_"]').forEach(sel => {
                if (!sel.value) return;
                const opt = sel.selectedOptions[0];
                if (!opt || opt.dataset.tipo !== 'serie') return;
                const idx = sel.id.replace('producto_select_', '');
                const cantidad = parseInt(document.getElementById(`cantidad_${idx}`)?.value) || 1;
                const registrados = (imeisPorFila[idx] || []).length;
                if (registrados < cantidad) {
                    filasSerie.push({ nombre: opt.text.trim(), registrados, cantidad });
                }
            });

            if (filasSerie.length > 0) {
                e.preventDefault();
                const lista = filasSerie.map(f =>
                    `• ${f.nombre}: ${f.registrados}/${f.cantidad} IMEIs`
                ).join('\n');
                Swal.fire({
                    icon: 'warning',
                    title: 'IMEIs incompletos',
                    html: `<p class="text-gray-600 mb-2">Los siguientes productos requieren IMEIs:</p>
                           <pre class="text-left text-sm bg-gray-50 rounded p-3">${lista}</pre>
                           <p class="text-xs text-gray-400 mt-2">Haz clic en el botón <strong>IMEIs</strong> de cada fila para registrarlos.</p>`,
                    confirmButtonColor: '#7c3aed',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }

            return true; // Todo OK, se envía
        });
    });

// ============================================
// MODAL CREAR PRODUCTO RÁPIDO
// ============================================
function abrirModalCrearProducto(terminoBusqueda) {
    document.getElementById('np_nombre').value = terminoBusqueda || '';
    document.getElementById('np_categoria').value = '';
    document.getElementById('np_tipo').value = 'cantidad';
    const marcaSelect = document.getElementById('np_marca');
    marcaSelect.innerHTML = '<option value="">Seleccionar categoría primero...</option>';
    marcaSelect.disabled = true;
    const modeloSelect = document.getElementById('np_modelo');
    modeloSelect.innerHTML = '<option value="">Seleccionar marca primero...</option>';
    modeloSelect.disabled = true;
    document.getElementById('np_tiene_variantes').checked = false;
    document.getElementById('np_codigo_barras').value = '';
    document.getElementById('np_unidad_medida').value = '';
    document.getElementById('np_dias_garantia').value = 365;
    document.getElementById('np_tipo_garantia').value = 'proveedor';
    document.getElementById('np_garantia_section').classList.add('hidden');
    document.getElementById('np_variantes_section').classList.add('hidden');
    document.getElementById('np_var_color').value = '';
    document.getElementById('np_var_capacidad').value = '';
    npVariantes = [];
    npRenderVariantes();
    toggleModeloLabel();

    const modal = document.getElementById('modalCrearProducto');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => document.getElementById('np_nombre').focus(), 100);
}

// ── Variantes inline del modal crear producto rápido ──────────────────────
let npVariantes = []; // [{color_id, color_nombre, color_hex, capacidad}]

function toggleVariantesNuevoProducto() {
    const checked = document.getElementById('np_tiene_variantes').checked;
    document.getElementById('np_variantes_section').classList.toggle('hidden', !checked);
    if (!checked) { npVariantes = []; npRenderVariantes(); }
}

function npAgregarVariante() {
    const colorSel   = document.getElementById('np_var_color');
    const colorId    = colorSel.value;
    const colorNombre= colorSel.options[colorSel.selectedIndex]?.dataset.nombre || '';
    const colorHex   = colorSel.options[colorSel.selectedIndex]?.dataset.hex || '';
    const capacidad  = document.getElementById('np_var_capacidad').value.trim();

    if (!colorId && !capacidad) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Ingresa al menos el color o la capacidad.', confirmButtonColor: '#1e3a8a' });
        return;
    }

    // Evitar duplicados
    const existe = npVariantes.some(v => v.color_id == colorId && v.capacidad === capacidad);
    if (existe) {
        Swal.fire({ icon: 'warning', title: 'Variante duplicada', text: 'Ya agregaste esta combinación de color y capacidad.', confirmButtonColor: '#1e3a8a' });
        return;
    }

    npVariantes.push({ color_id: colorId || null, color_nombre: colorNombre, color_hex: colorHex, capacidad });
    npRenderVariantes();

    // Reset fila
    colorSel.value = '';
    document.getElementById('np_var_capacidad').value = '';
    document.getElementById('np_var_capacidad').focus();
}

function npEliminarVariante(idx) {
    npVariantes.splice(idx, 1);
    npRenderVariantes();
}

function npRenderVariantes() {
    const lista  = document.getElementById('np_variantes_lista');
    const conteo = document.getElementById('np_variantes_count');
    conteo.textContent = npVariantes.length + ' agregada' + (npVariantes.length !== 1 ? 's' : '');

    if (npVariantes.length === 0) {
        lista.innerHTML = `<div class="py-4 text-center text-sm text-gray-400">
            <i class="fas fa-layer-group mr-1"></i> Agrega variantes abajo
        </div>`;
        return;
    }

    lista.innerHTML = npVariantes.map((v, i) => {
        const circulo = v.color_hex
            ? `<span class="w-4 h-4 rounded-full border border-gray-300 shrink-0 inline-block" style="background-color:${v.color_hex}"></span>`
            : `<span class="w-4 h-4 rounded-full bg-gray-200 border border-gray-300 shrink-0 inline-block"></span>`;
        const label = [v.color_nombre, v.capacidad].filter(Boolean).join(' / ') || 'Sin especificar';
        return `<div class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center gap-2">
                ${circulo}
                <span class="text-sm text-gray-800">${label}</span>
            </div>
            <button type="button" onclick="npEliminarVariante(${i})"
                    class="text-red-400 hover:text-red-600 transition text-xs px-2 py-1 rounded hover:bg-red-50">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    }).join('');
}

function toggleModeloLabel() {
    const tipo = document.getElementById('np_tipo')?.value;
    const req  = document.getElementById('np_modelo_req_label');
    const opt  = document.getElementById('np_modelo_opt_label');
    if (tipo === 'serie') {
        req?.classList.remove('hidden');
        opt?.classList.add('hidden');
    } else {
        req?.classList.add('hidden');
        opt?.classList.remove('hidden');
    }
}

async function crearMarcaRapida() {
    const categoriaId = document.getElementById('np_categoria').value;
    if (!categoriaId) {
        Swal.fire({ icon: 'warning', title: 'Selecciona categoría', text: 'Primero selecciona una categoría antes de crear una marca.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    const { value: nombre } = await Swal.fire({
        title: 'Nueva Marca',
        input: 'text',
        inputPlaceholder: 'Nombre de la marca...',
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1e3a8a',
        inputValidator: v => !v.trim() ? 'El nombre es obligatorio' : null,
    });
    if (!nombre) return;

    const res = await fetch('{{ route("catalogo.marcas.rapida") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ nombre: nombre.trim(), categoria_id: categoriaId }),
    }).then(r => r.json());

    if (!res.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo crear la marca.', confirmButtonColor: '#d33' });
        return;
    }
    const marcaSelect = document.getElementById('np_marca');
    const opt = document.createElement('option');
    opt.value = res.id; opt.text = res.nombre; opt.selected = true;
    marcaSelect.appendChild(opt);
    marcaSelect.disabled = false;
    cargarModelosNuevoProducto();
    Swal.fire({ icon: 'success', title: `Marca "${res.nombre}" creada`, timer: 1500, showConfirmButton: false });
}

async function crearModeloRapido() {
    const marcaId = document.getElementById('np_marca').value;
    if (!marcaId) {
        Swal.fire({ icon: 'warning', title: 'Selecciona marca', text: 'Primero selecciona una marca antes de crear un modelo.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    const { value: nombre } = await Swal.fire({
        title: 'Nuevo Modelo',
        input: 'text',
        inputPlaceholder: 'Nombre del modelo...',
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1e3a8a',
        inputValidator: v => !v.trim() ? 'El nombre es obligatorio' : null,
    });
    if (!nombre) return;

    const res = await fetch('{{ route("catalogo.modelos.rapida") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ nombre: nombre.trim(), marca_id: marcaId }),
    }).then(r => r.json());

    if (!res.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo crear el modelo.', confirmButtonColor: '#d33' });
        return;
    }
    const modeloSelect = document.getElementById('np_modelo');
    const opt = document.createElement('option');
    opt.value = res.id; opt.text = res.nombre; opt.selected = true;
    modeloSelect.appendChild(opt);
    modeloSelect.disabled = false;
    Swal.fire({ icon: 'success', title: `Modelo "${res.nombre}" creado`, timer: 1500, showConfirmButton: false });
}

async function crearColorRapido() {
    const { value: nombre } = await Swal.fire({
        title: 'Nuevo Color',
        input: 'text',
        inputPlaceholder: 'Nombre del color...',
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1e3a8a',
        inputValidator: v => !v.trim() ? 'El nombre es obligatorio' : null,
    });
    if (!nombre) return;

    const res = await fetch('{{ route("catalogo.colores.rapida") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ nombre: nombre.trim() }),
    }).then(r => r.json());

    if (!res.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo crear el color.', confirmButtonColor: '#d33' });
        return;
    }
    const colorSelect = document.getElementById('np_color');
    const opt = document.createElement('option');
    opt.value = res.id; opt.text = res.nombre; opt.selected = true;
    colorSelect.appendChild(opt);
    Swal.fire({ icon: 'success', title: `Color "${res.nombre}" creado`, timer: 1500, showConfirmButton: false });
}

function cerrarModalCrearProducto() {
    const modal = document.getElementById('modalCrearProducto');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function cargarMarcasNuevoProducto() {
    const categoriaId = document.getElementById('np_categoria').value;
    const marcaSelect = document.getElementById('np_marca');
    const modeloSelect = document.getElementById('np_modelo');

    modeloSelect.innerHTML = '<option value="">Seleccionar marca primero...</option>';
    modeloSelect.disabled = true;

    if (!categoriaId) {
        marcaSelect.innerHTML = '<option value="">Seleccionar categoría primero...</option>';
        marcaSelect.disabled = true;
        return;
    }

    marcaSelect.innerHTML = '<option value="">Cargando...</option>';
    marcaSelect.disabled = true;

    fetch(`/catalogo/marcas-por-categoria/${categoriaId}`)
        .then(r => r.json())
        .then(marcas => {
            marcaSelect.disabled = false;
            marcaSelect.innerHTML = '<option value="">Seleccionar marca...</option>' +
                marcas.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('');
        })
        .catch(() => {
            marcaSelect.disabled = false;
            marcaSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
}

function cargarModelosNuevoProducto() {
    const marcaId = document.getElementById('np_marca').value;
    const modeloSelect = document.getElementById('np_modelo');

    if (!marcaId) {
        modeloSelect.innerHTML = '<option value="">Seleccionar marca primero...</option>';
        modeloSelect.disabled = true;
        return;
    }

    modeloSelect.innerHTML = '<option value="">Cargando...</option>';
    modeloSelect.disabled = true;

    fetch(`/catalogo/modelos-por-marca/${marcaId}`)
        .then(r => r.json())
        .then(modelos => {
            modeloSelect.disabled = false;
            modeloSelect.innerHTML = '<option value="">Seleccionar modelo...</option>' +
                modelos.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('');
        })
        .catch(() => {
            modeloSelect.disabled = false;
            modeloSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
}

function toggleGarantiaSection() {
    const tipo = document.getElementById('np_tipo').value;
    const sec  = document.getElementById('np_garantia_section');
    if (tipo === 'serie') {
        sec.classList.remove('hidden');
    } else {
        sec.classList.add('hidden');
    }
}

function guardarNuevoProducto() {
    const nombre          = document.getElementById('np_nombre').value.trim();
    const categoriaId     = document.getElementById('np_categoria').value;
    const marcaId         = document.getElementById('np_marca').value;
    const modeloId        = document.getElementById('np_modelo').value;
    const colorId         = document.getElementById('np_color').value;
    const tipo            = document.getElementById('np_tipo').value;
    const tieneVariantes  = document.getElementById('np_tiene_variantes').checked;
    const codigoBarras    = document.getElementById('np_codigo_barras').value.trim();
    const unidadMedidaId  = document.getElementById('np_unidad_medida').value;
    const diasGarantia    = document.getElementById('np_dias_garantia')?.value || 365;
    const tipoGarantia    = document.getElementById('np_tipo_garantia')?.value || 'proveedor';

    if (!nombre) {
        Swal.fire({ icon: 'warning', title: 'Falta el nombre', text: 'Ingresa el nombre del producto.', confirmButtonColor: '#1e3a8a' });
        document.getElementById('np_nombre').focus();
        return;
    }
    if (!categoriaId) {
        Swal.fire({ icon: 'warning', title: 'Falta la categoría', text: 'Selecciona una categoría.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    if (!marcaId) {
        Swal.fire({ icon: 'warning', title: 'Falta la marca', text: 'Selecciona una marca.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    if (!unidadMedidaId) {
        Swal.fire({ icon: 'warning', title: 'Falta la unidad de medida', text: 'Selecciona una unidad de medida.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    if (tipo === 'serie' && !modeloId) {
        Swal.fire({ icon: 'warning', title: 'Falta el modelo', text: 'Para productos con IMEI el modelo es obligatorio.', confirmButtonColor: '#1e3a8a' });
        return;
    }
    if (tieneVariantes && npVariantes.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Sin variantes', text: 'Agregaste que tiene variantes pero no definiste ninguna. Agrega al menos una.', confirmButtonColor: '#1e3a8a' });
        return;
    }

    const btn = document.getElementById('btn_guardar_nuevo_producto');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando...';

    fetch('{{ route("compras.crear-producto-rapido") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            nombre,
            categoria_id:     categoriaId,
            marca_id:         marcaId,
            modelo_id:        modeloId || null,
            color_id:         tieneVariantes ? null : (colorId || null),
            unidad_medida_id: unidadMedidaId,
            tipo_inventario:  tipo,
            tiene_variantes:  tieneVariantes,
            variantes:        tieneVariantes ? npVariantes.map(v => ({ color_id: v.color_id || null, capacidad: v.capacidad || null })) : [],
            codigo_barras:    codigoBarras || null,
            dias_garantia:    tipo === 'serie' ? parseInt(diasGarantia) : null,
            tipo_garantia:    tipo === 'serie' ? tipoGarantia : null,
        }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Crear y Agregar';

        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo crear el producto.', confirmButtonColor: '#d33' });
            return;
        }

        // Agregar al catálogo local para que agregarProductoConDatos lo encuentre
        catalogoProductos.push({
            id:              data.id,
            nombre:          data.nombre,
            tipo_inventario: data.tipo_inventario,
            categoria:       data.categoria,
            marca_id:        data.marca_id,
            marca:           data.marca,
            modelo_id:       data.modelo_id,
            modelo:          data.modelo,
            requiere_imei:   data.requiere_imei,
            tiene_variantes: data.tiene_variantes || false,
            variantes:       data.variantes || [],
        });

        cerrarModalCrearProducto();
        cerrarModalProductos();
        agregarProductoConDatos(data);

        Swal.fire({
            icon: 'success',
            title: '¡Producto creado!',
            text: `"${data.nombre}" fue creado y agregado a la compra.`,
            timer: 2000,
            showConfirmButton: false,
        });
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Crear y Agregar';
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#d33' });
    });
}

// ============================================
// PRECARGA DESDE PEDIDO A PROVEEDOR (Recibir mercadería)
// ============================================
@if($pedidoOrigen ?? null)
(function precargarDesdePedido() {
    const detalles = @json($pedidoOrigen['detalles']);

    detalles.forEach(function (d) {
        const cantidad = parseInt(d.cantidad) || 1;
        const inicio = contadorProductos;

        if (d.variante_id) {
            agregarFilaConVariante({ id: d.producto_id }, d.variante_id, cantidad);
        } else {
            for (let i = 0; i < cantidad; i++) {
                agregarProducto();
                const idx = contadorProductos - 1;
                const sel = document.getElementById(`producto_select_${idx}`);
                if (sel) {
                    sel.value = d.producto_id;
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        if (d.precio_referencial) {
            for (let idx = inicio; idx < contadorProductos; idx++) {
                const inputPrecio = document.getElementById(`precio_${idx}`);
                if (inputPrecio) {
                    inputPrecio.value = d.precio_referencial;
                    calcularSubtotal(idx);
                }
            }
        }
    });
})();
@endif
</script>

<!-- ============================================================ -->
<!-- MODAL: CREAR PRODUCTO RÁPIDO                                 -->
<!-- ============================================================ -->
<div id="modalCrearProducto"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-start justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg my-auto">

        <!-- Header -->
        <div class="bg-gradient-to-r from-green-700 to-green-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
            <h3 class="text-lg font-bold text-white flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>
                Crear Producto Rápido
            </h3>
            <button type="button" onclick="cerrarModalCrearProducto()"
                    class="text-white hover:text-green-200 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">

            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nombre del producto <span class="text-red-500">*</span>
                </label>
                <input type="text" id="np_nombre"
                       class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition"
                       placeholder="Ej: iPhone 15 Pro Max">
            </div>

            <!-- Categoría + Tipo inventario -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Categoría <span class="text-red-500">*</span>
                    </label>
                    <select id="np_categoria" onchange="cargarMarcasNuevoProducto()"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition">
                        <option value="">Seleccionar...</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Tipo <span class="text-red-500">*</span>
                    </label>
                    <select id="np_tipo" onchange="toggleModeloLabel(); toggleGarantiaSection();"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition">
                        <option value="cantidad">Regular (stock)</option>
                        <option value="serie">Serie (IMEI)</option>
                    </select>
                </div>
            </div>

            <!-- Marca -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Marca <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <select id="np_marca" onchange="cargarModelosNuevoProducto()"
                            class="flex-1 px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition">
                        <option value="">Seleccionar categoría primero...</option>
                    </select>
                    <button type="button" onclick="crearMarcaRapida()"
                            title="Nueva marca"
                            class="px-3 py-2 bg-blue-50 text-blue-700 border-2 border-blue-200 rounded-xl hover:bg-blue-100 transition shrink-0">
                        <i class="fas fa-plus text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Modelo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Modelo
                    <span id="np_modelo_req_label" class="text-red-500">*</span>
                    <span id="np_modelo_opt_label" class="text-gray-400 text-xs font-normal hidden">(opcional)</span>
                </label>
                <div class="flex gap-2">
                    <select id="np_modelo"
                            class="flex-1 px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition">
                        <option value="">Seleccionar marca primero...</option>
                    </select>
                    <button type="button" onclick="crearModeloRapido()"
                            title="Nuevo modelo"
                            class="px-3 py-2 bg-indigo-50 text-indigo-700 border-2 border-indigo-200 rounded-xl hover:bg-indigo-100 transition shrink-0">
                        <i class="fas fa-plus text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Unidad de Medida -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Unidad de Medida <span class="text-red-500">*</span>
                </label>
                <select id="np_unidad_medida"
                        class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 transition">
                    <option value="">Seleccionar...</option>
                    @foreach($unidades as $u)
                        <option value="{{ $u->id }}">{{ $u->nombre }} ({{ $u->abreviatura }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Garantía (solo serie/IMEI) -->
            <div id="np_garantia_section" class="hidden">
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-3">
                    <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide">
                        <i class="fas fa-shield-alt mr-1"></i>Garantía
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Días de garantía</label>
                            <input type="number" id="np_dias_garantia" value="365" min="0"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 text-sm transition">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo garantía</label>
                            <select id="np_tipo_garantia"
                                    class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 text-sm transition">
                                <option value="proveedor">Proveedor</option>
                                <option value="tienda">Tienda</option>
                                <option value="fabricante">Fabricante</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ¿Tiene variantes? -->
            <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="np_tiene_variantes"
                           onchange="toggleVariantesNuevoProducto()"
                           class="w-4 h-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-400">
                    <span class="text-sm font-medium text-indigo-800">
                        <i class="fas fa-layer-group mr-1"></i>Este producto tiene variantes (colores / capacidades)
                    </span>
                </label>
            </div>

            <!-- Sección variantes inline -->
            <div id="np_variantes_section" class="hidden">
                <div class="border-2 border-indigo-200 rounded-xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-indigo-700 px-4 py-2 flex items-center justify-between">
                        <span class="text-white text-sm font-semibold"><i class="fas fa-layer-group mr-1"></i> Variantes</span>
                        <span id="np_variantes_count" class="bg-white/20 text-white text-xs px-2 py-0.5 rounded-full">0 agregadas</span>
                    </div>

                    <!-- Lista de variantes agregadas -->
                    <div id="np_variantes_lista" class="divide-y divide-gray-100 bg-white"></div>

                    <!-- Fila para agregar nueva variante -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200">
                        <div class="grid grid-cols-[1fr_1fr_auto] gap-2 items-end">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Color</label>
                                <div class="flex gap-1">
                                    <select id="np_var_color"
                                            class="flex-1 px-2 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition">
                                        <option value="">Sin color</option>
                                        @foreach($colores as $c)
                                            <option value="{{ $c->id }}"
                                                    data-hex="{{ $c->codigo_hex }}"
                                                    data-nombre="{{ $c->nombre }}">{{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Capacidad <span class="text-gray-400">(ej: 256GB)</span></label>
                                <input type="text" id="np_var_capacidad"
                                       placeholder="256GB+8RAM"
                                       class="w-full px-2 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition">
                            </div>
                            <button type="button" onclick="npAgregarVariante()"
                                    class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm shrink-0">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Código de barras -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Código de barras
                    <span class="text-gray-400 text-xs font-normal">(dejar vacío para generar automáticamente)</span>
                </label>
                <input type="text" id="np_codigo_barras"
                       class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100 font-mono transition"
                       placeholder="Ej: 7501234567890">
            </div>

        </div><!-- /body -->

        <!-- Footer -->
        <div class="px-6 pb-6 flex justify-end gap-3">
            <button type="button" onclick="cerrarModalCrearProducto()"
                    class="px-5 py-2.5 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                Cancelar
            </button>
            <button type="button" onclick="guardarNuevoProducto()"
                    id="btn_guardar_nuevo_producto"
                    class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-green-700 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-500 transition shadow-md font-medium">
                <i class="fas fa-save mr-2"></i>
                Crear y Agregar
            </button>
        </div>

    </div>
</div>

</body>
</html>