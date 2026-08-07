<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editar Compra - CORPORACIÓN ADIVON SAC</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header con breadcrumb -->
        <div class="mb-6">
            <div class="flex items-center text-sm text-gray-500 mb-2">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-900">Dashboard</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="{{ route('compras.index') }}" class="hover:text-blue-900">Compras</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="{{ route('compras.show', $compra) }}" class="hover:text-blue-900">Compra #{{ $compra->numero_factura }}</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-gray-700 font-medium">Editar</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-edit mr-3 text-blue-900"></i>
                Editar Compra #{{ $compra->numero_factura }}
            </h1>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-triangle mr-2 text-lg"></i>
                    <strong>Por favor corrige los siguientes errores:</strong>
                </div>
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Formulario principal -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 px-8 py-5">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-edit mr-3"></i>
                    Editar Datos de la Compra
                </h2>
            </div>

            <form action="{{ route('compras.update', $compra) }}" method="POST" id="compraForm" class="p-8">
                @csrf
                @method('PUT')

                <!-- SECCIÓN 1: INFORMACIÓN GENERAL -->
                <div class="mb-10">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-file-invoice text-blue-900 text-sm"></i>
                        </span>
                        Información General
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Proveedor -->
                        <div class="relative">
                            <label for="proveedor_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Proveedor <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="proveedor_id" id="proveedor_id" required
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 appearance-none bg-white">
                                    <option value="">Seleccione un proveedor</option>
                                    @foreach($proveedores as $prov)
                                        <option value="{{ $prov->id }}" {{ old('proveedor_id', $compra->proveedor_id) == $prov->id ? 'selected' : '' }}>
                                            {{ $prov->razon_social }} ({{ $prov->ruc }})
                                        </option>
                                    @endforeach
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- N° Factura -->
                        <div>
                            <label for="numero_factura" class="block text-sm font-medium text-gray-700 mb-1.5">
                                N° Factura/Boleta <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="numero_factura" id="numero_factura" required
                                   value="{{ old('numero_factura', $compra->numero_factura) }}"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                                   placeholder="Ej: F001-00001234">
                        </div>

                        <!-- Sucursal -->
                        <div class="relative">
                            <label for="edit_sucursal_sel" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select id="edit_sucursal_sel" required
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 appearance-none bg-white"
                                        onchange="onEditSucursalChange(this.value)">
                                    <option value="">— Seleccione una sucursal —</option>
                                    @foreach($sucursales as $suc)
                                        <option value="{{ $suc->id }}"
                                            {{ old('sucursal_edit', $compra->almacen?->sucursal_id) == $suc->id ? 'selected' : '' }}>
                                            {{ $suc->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Almacén múltiple -->
                        <div id="edit_almacen_wrap" class="relative hidden">
                            <label for="edit_almacen_sel" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Almacén Destino <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select id="edit_almacen_sel"
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 appearance-none bg-white"
                                        onchange="document.getElementById('almacen_id_hidden').value = this.value">
                                    <option value="">Seleccione un almacén</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <!-- Almacén único -->
                        <div id="edit_almacen_unico_info" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Almacén Destino</label>
                            <div class="flex items-center gap-2 px-4 py-3 bg-yellow-50 border-2 border-yellow-200 rounded-xl">
                                <i class="fas fa-warehouse text-yellow-600"></i>
                                <span id="edit_almacen_unico_nombre" class="text-sm font-semibold text-yellow-900"></span>
                                <span class="text-xs text-yellow-500 ml-auto">Auto-seleccionado</span>
                            </div>
                        </div>
                        <input type="hidden" name="almacen_id" id="almacen_id_hidden" value="{{ old('almacen_id', $compra->almacen_id) }}">

                        <!-- Fecha -->
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Fecha de Compra <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="fecha" id="fecha" required
                                   value="{{ old('fecha', $compra->fecha->format('Y-m-d')) }}"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200">
                        </div>

                        <!-- Tipo Comprobante (solo lectura) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo Comprobante</label>
                            <input type="text" value="{{ ucfirst($compra->tipo_comprobante ?? 'Factura') }}"
                                   class="w-full px-4 py-3 bg-gray-100 border-2 border-gray-200 rounded-xl text-gray-700" readonly>
                        </div>

                        <!-- Forma de Pago -->
                        <div>
                            <label for="forma_pago" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Forma de Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="forma_pago" id="forma_pago" required
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 appearance-none bg-white"
                                        onchange="toggleCondicionPago(this.value)">
                                    <option value="contado" {{ old('forma_pago', $compra->forma_pago) === 'contado' ? 'selected' : '' }}>Contado</option>
                                    <option value="credito" {{ old('forma_pago', $compra->forma_pago) === 'credito' ? 'selected' : '' }}>Crédito</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Días de Crédito -->
                        <div id="condicion_pago_wrap" class="{{ old('forma_pago', $compra->forma_pago) === 'credito' ? '' : 'hidden' }}">
                            <label for="condicion_pago" class="block text-sm font-medium text-gray-700 mb-1.5">Días de Crédito</label>
                            <input type="number" name="condicion_pago" id="condicion_pago" min="1" max="365"
                                   value="{{ old('condicion_pago', $compra->condicion_pago) }}"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                                   placeholder="Ej: 30">
                        </div>

                        <!-- Moneda (solo lectura) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Moneda</label>
                            <input type="text" value="{{ $compra->tipo_moneda }}"
                                   class="w-full px-4 py-3 bg-gray-100 border-2 border-gray-200 rounded-xl text-gray-700" readonly>
                        </div>

                        <!-- Tipo Operación SUNAT (solo lectura) -->
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Operación SUNAT</label>
                            <input type="text"
                                   value="{{ $compra->tipo_operacion == '01' ? 'Gravado (IGV 18%)' : ($compra->tipo_operacion == '02' ? 'Exonerado' : ($compra->tipo_operacion == '03' ? 'Inafecto' : 'Exportación')) }}"
                                   class="w-full px-4 py-3 bg-gray-100 border-2 border-gray-200 rounded-xl text-gray-700" readonly>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: PRODUCTOS -->
                <div class="mb-10">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1 flex items-center">
                        <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-boxes text-green-700 text-sm"></i>
                        </span>
                        Productos de la Compra
                    </h3>
                    <p class="text-xs text-gray-500 mb-4 ml-10">Puedes corregir cantidades, precios y gestionar IMEIs. El stock se ajustará automáticamente.</p>

                    <div class="rounded-xl border-2 border-green-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="tablaProductos">
                                <thead class="bg-green-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Variante</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase w-28">Cantidad</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase w-36">Precio Unit.</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase w-32">Subtotal</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase w-28">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="tbody-detalles">
                                    @foreach($compra->detalles as $i => $detalle)
                                    <tr data-index="{{ $i }}" data-detalle-id="{{ $detalle->id }}" id="row-detalle-{{ $detalle->id }}">
                                        <td class="px-4 py-3">
                                            <input type="hidden" name="detalles[{{ $i }}][id]" value="{{ $detalle->id }}">
                                            <p class="font-medium text-gray-900 text-sm">{{ $detalle->producto->nombre }}</p>
                                            <p class="text-xs text-gray-500">{{ $detalle->producto->marca->nombre ?? '' }}</p>
                                            @if($detalle->producto->tipo_inventario === 'serie')
                                                <span class="inline-flex items-center gap-1 text-xs text-purple-600 mt-0.5">
                                                    <i class="fas fa-microchip text-xs"></i> Con IMEI
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $detalle->variante?->color?->nombre ?? ($detalle->producto->modelo->nombre ?? '-') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                   name="detalles[{{ $i }}][cantidad]"
                                                   value="{{ old("detalles.$i.cantidad", $detalle->cantidad) }}"
                                                   min="1"
                                                   class="w-full text-center px-2 py-1.5 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 text-sm font-semibold detalle-cantidad"
                                                   data-index="{{ $i }}"
                                                   required>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                   name="detalles[{{ $i }}][precio_unitario]"
                                                   value="{{ old("detalles.$i.precio_unitario", number_format($detalle->precio_unitario, 2, '.', '')) }}"
                                                   min="0.01"
                                                   step="0.01"
                                                   class="w-full text-center px-2 py-1.5 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 text-sm detalle-precio"
                                                   data-index="{{ $i }}"
                                                   required>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="font-semibold text-gray-900 text-sm subtotal-linea" data-index="{{ $i }}">
                                                {{ number_format($detalle->subtotal, 2) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1">
                                                @if($detalle->producto->tipo_inventario === 'serie')
                                                <button type="button"
                                                        onclick="abrirModalImeis({{ $detalle->id }}, '{{ addslashes($detalle->producto->nombre) }}')"
                                                        title="Gestionar IMEIs"
                                                        class="p-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition text-xs">
                                                    <i class="fas fa-microchip"></i>
                                                </button>
                                                @endif
                                                <button type="button"
                                                        onclick="eliminarDetalle({{ $detalle->id }}, '{{ addslashes($detalle->producto->nombre) }}')"
                                                        title="Eliminar producto"
                                                        class="p-1.5 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition text-xs btn-eliminar-detalle">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    @php
                                        $tipoOp = $compra->tipo_operacion ?? '01';
                                        // Detección: si el precio se ingresó "con IGV incluido" al crear la
                                        // compra, cada detalle guarda el monto CON IGV, y la suma de los
                                        // detalles coincide con el total (no con el subtotal). Se usa para
                                        // que el recálculo en vivo (al editar cantidad/precio) no vuelva a
                                        // sumar el 18% sobre un monto que ya lo trae incluido.
                                        $sumaDetalleOriginal = $compra->detalles->sum('subtotal');
                                        $detalleGuardaConIgv = $tipoOp === '01' && $compra->incluye_igv
                                            && abs($sumaDetalleOriginal - (float) $compra->total) < 0.05
                                            && abs($sumaDetalleOriginal - (float) $compra->subtotal) >= 0.05;
                                    @endphp
                                    <tr>
                                        <td colspan="5" class="px-4 py-2 text-right text-sm font-semibold text-gray-700">Subtotal:</td>
                                        <td class="px-4 py-2 text-right font-bold text-blue-900" id="resumen-subtotal">{{ number_format($compra->subtotal, 2) }}</td>
                                    </tr>
                                    @if($tipoOp === '01')
                                    <tr>
                                        <td colspan="5" class="px-4 py-2 text-right">
                                            <label class="inline-flex items-center gap-2 cursor-pointer justify-end w-full">
                                                <input type="hidden" name="incluye_igv" value="0">
                                                <input type="checkbox" id="incluir_igv_edit" name="incluye_igv" value="1"
                                                       {{ $compra->incluye_igv ? 'checked' : '' }}
                                                       class="w-4 h-4 rounded border-gray-300 text-blue-900 focus:ring-blue-900">
                                                <span class="text-sm font-semibold text-gray-700">IGV (18%):</span>
                                            </label>
                                        </td>
                                        <td class="px-4 py-2 text-right font-bold text-blue-900" id="resumen-igv">{{ number_format($compra->igv, 2) }}</td>
                                    </tr>
                                    @endif
                                    <tr class="border-t-2 border-gray-300">
                                        <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-900">Total:</td>
                                        <td class="px-4 py-3 text-right font-bold text-blue-900 text-base" id="resumen-total">{{ number_format($compra->total, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: OBSERVACIONES -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-comment text-yellow-600 text-sm"></i>
                        </span>
                        Observaciones
                    </h3>
                    <textarea name="observaciones" rows="3"
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                              placeholder="Notas adicionales sobre la compra...">{{ old('observaciones', $compra->observaciones) }}</textarea>
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t-2 border-gray-100">
                    <a href="{{ route('compras.show', $compra) }}"
                       class="px-6 py-3 border-2 border-gray-200 rounded-xl text-gray-700 hover:bg-gray-50 transition font-medium">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit"
                            class="px-8 py-3 bg-gradient-to-r from-yellow-600 to-yellow-500 text-white rounded-xl hover:from-yellow-500 hover:to-yellow-400 transition shadow-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Actualizar Compra
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL GESTIÓN DE IMEIs ===== -->
    <div id="modalImeis" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" onclick="cerrarModalImeis()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-microchip"></i>
                            Gestión de IMEIs
                        </h3>
                        <p class="text-purple-200 text-sm mt-0.5" id="modal-imei-producto-nombre"></p>
                    </div>
                    <button onclick="cerrarModalImeis()" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6">
                    <div id="modal-imei-loading" class="text-center py-8 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p>Cargando IMEIs...</p>
                    </div>
                    <div id="modal-imei-content" class="hidden">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm text-gray-600">
                                <span id="modal-imei-count" class="font-semibold text-purple-700">0</span> IMEI(s) registrados
                            </p>
                            <p class="text-xs text-gray-400">Solo puedes editar/eliminar IMEIs en stock</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Código IMEI</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-imeis-body" class="bg-white divide-y divide-gray-100 text-sm">
                                </tbody>
                            </table>
                        </div>
                        <div id="modal-imei-vacio" class="hidden text-center py-6 text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-2"></i>
                            <p>No hay IMEIs registrados para este producto</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL EDITAR IMEI ===== -->
    <div id="modalEditarImei" class="fixed inset-0 z-60 hidden overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="bg-purple-700 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-base font-bold text-white"><i class="fas fa-pen mr-2"></i>Editar IMEI</h3>
                    <button onclick="cerrarModalEditarImei()" class="text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6">
                    <input type="hidden" id="edit-imei-id">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nuevo código IMEI</label>
                    <input type="text" id="edit-imei-codigo" maxlength="15"
                           class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 font-mono text-sm"
                           placeholder="15 dígitos">
                    <p class="text-xs text-gray-400 mt-1">Debe tener exactamente 15 dígitos numéricos.</p>
                    <div id="edit-imei-error" class="hidden mt-2 text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2"></div>
                    <div class="flex gap-3 mt-5">
                        <button onclick="cerrarModalEditarImei()" class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                            Cancelar
                        </button>
                        <button onclick="guardarImei()" id="btn-guardar-imei"
                                class="flex-1 px-4 py-2.5 bg-purple-700 text-white rounded-xl hover:bg-purple-600 transition text-sm font-medium">
                            <i class="fas fa-save mr-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ── URLs para AJAX ──────────────────────────────────────────────
        const COMPRA_ID = {{ $compra->id }};
        const URL_DETALLE_BASE  = '{{ url("compras/{$compra->id}/detalle") }}';
        const URL_IMEI_BASE     = '{{ url("compras/{$compra->id}/imei") }}';
        const CSRF_TOKEN        = document.querySelector('meta[name="csrf-token"]').content;

        // ── Submit del formulario ───────────────────────────────────────
        document.getElementById('compraForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Actualizando...';
        });

        // ── Forma de pago ───────────────────────────────────────────────
        function toggleCondicionPago(value) {
            const wrap  = document.getElementById('condicion_pago_wrap');
            const input = document.getElementById('condicion_pago');
            if (value === 'credito') {
                wrap.classList.remove('hidden');
                input.required = true;
            } else {
                wrap.classList.add('hidden');
                input.required = false;
                input.value = '';
            }
        }

        // ── Sucursal → Almacén ──────────────────────────────────────────
        const editSucursalAlmacenesMap = @json(
            $sucursales->mapWithKeys(fn($s) => [
                $s->id => $s->almacenes->map(fn($a) => ['id' => $a->id, 'nombre' => $a->nombre])->values()
            ])
        );

        function onEditSucursalChange(sucursalId) {
            const wrap        = document.getElementById('edit_almacen_wrap');
            const selAlmacen  = document.getElementById('edit_almacen_sel');
            const hiddenAlm   = document.getElementById('almacen_id_hidden');
            const infoUnico   = document.getElementById('edit_almacen_unico_info');
            const nombreUnico = document.getElementById('edit_almacen_unico_nombre');

            wrap.classList.add('hidden');
            infoUnico.classList.add('hidden');
            selAlmacen.innerHTML = '<option value="">Seleccione un almacén</option>';

            if (!sucursalId) { hiddenAlm.value = ''; return; }

            const almacenes   = editSucursalAlmacenesMap[sucursalId] || [];
            const prevAlmacen = hiddenAlm.value;

            if (almacenes.length === 1) {
                hiddenAlm.value = almacenes[0].id;
                nombreUnico.textContent = almacenes[0].nombre;
                infoUnico.classList.remove('hidden');
            } else if (almacenes.length > 1) {
                almacenes.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.textContent = a.nombre;
                    selAlmacen.appendChild(opt);
                });
                const ids = almacenes.map(a => String(a.id));
                if (prevAlmacen && ids.includes(String(prevAlmacen))) {
                    selAlmacen.value = prevAlmacen;
                } else {
                    hiddenAlm.value = '';
                }
                wrap.classList.remove('hidden');
            } else {
                hiddenAlm.value = '';
            }
        }

        (function() {
            const sel = document.getElementById('edit_sucursal_sel');
            if (sel && sel.value) onEditSucursalChange(sel.value);
        })();

        // ── Recálculo de subtotales en tiempo real ──────────────────────
        const tipoOp             = '{{ $compra->tipo_operacion ?? "01" }}';
        const detalleGuardaConIgv = {{ $detalleGuardaConIgv ? 'true' : 'false' }};
        const chkIncluyeIgv       = document.getElementById('incluir_igv_edit');

        function recalcularTotales() {
            let sumaDetalle = 0;
            document.querySelectorAll('#tablaProductos tbody tr').forEach(function(row) {
                const idx      = row.dataset.index;
                const cantidad = parseFloat(document.querySelector(`[name="detalles[${idx}][cantidad]"]`)?.value) || 0;
                const precio   = parseFloat(document.querySelector(`[name="detalles[${idx}][precio_unitario]"]`)?.value) || 0;
                const linea    = cantidad * precio;
                const span     = row.querySelector('.subtotal-linea');
                if (span) span.textContent = linea.toFixed(2);
                sumaDetalle += linea;
            });

            const incluyeIgv = tipoOp === '01' && (!chkIncluyeIgv || chkIncluyeIgv.checked);

            let subtotal, igv, total;
            if (!incluyeIgv) {
                subtotal = sumaDetalle; igv = 0; total = sumaDetalle;
            } else if (detalleGuardaConIgv) {
                // Los precios de línea ya traen el IGV incluido: se extrae la base.
                subtotal = sumaDetalle / 1.18;
                igv      = sumaDetalle - subtotal;
                total    = sumaDetalle;
            } else {
                // Los precios de línea son sin IGV: se agrega el 18% encima.
                subtotal = sumaDetalle;
                igv      = sumaDetalle * 0.18;
                total    = subtotal + igv;
            }

            const elSub = document.getElementById('resumen-subtotal');
            const elIgv = document.getElementById('resumen-igv');
            const elTot = document.getElementById('resumen-total');
            if (elSub) elSub.textContent = subtotal.toFixed(2);
            if (elIgv) elIgv.textContent = igv.toFixed(2);
            if (elTot) elTot.textContent  = total.toFixed(2);
        }

        document.querySelectorAll('.detalle-cantidad, .detalle-precio').forEach(function(input) {
            input.addEventListener('input', recalcularTotales);
        });
        if (chkIncluyeIgv) chkIncluyeIgv.addEventListener('change', recalcularTotales);

        // ══════════════════════════════════════════════════════════════════
        // ELIMINAR DETALLE
        // ══════════════════════════════════════════════════════════════════
        function eliminarDetalle(detalleId, nombreProducto) {
            Swal.fire({
                title: '¿Eliminar producto?',
                html: `<p class="text-sm text-gray-600">Se eliminará <strong>${nombreProducto}</strong> de la compra y se revertirá el stock.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-1"></i>Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then(result => {
                if (!result.isConfirmed) return;

                fetch(`${URL_DETALLE_BASE}/${detalleId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`row-detalle-${detalleId}`);
                        if (row) row.remove();
                        recalcularTotales();
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: data.message, timer: 2000, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión. Intenta de nuevo.' }));
            });
        }

        // ══════════════════════════════════════════════════════════════════
        // MODAL IMEIs
        // ══════════════════════════════════════════════════════════════════
        let detalleIdActivo = null;

        const estadoImeiLabels = {
            'en_stock':    { label: 'En Stock',    cls: 'bg-green-100 text-green-700' },
            'en_transito': { label: 'En Tránsito', cls: 'bg-blue-100 text-blue-700' },
            'reservado':   { label: 'Reservado',   cls: 'bg-yellow-100 text-yellow-700' },
            'vendido':     { label: 'Vendido',      cls: 'bg-red-100 text-red-700' },
            'garantia':    { label: 'Garantía',    cls: 'bg-orange-100 text-orange-700' },
            'devuelto':    { label: 'Devuelto',    cls: 'bg-gray-100 text-gray-600' },
            'reemplazado': { label: 'Reemplazado', cls: 'bg-gray-100 text-gray-600' },
        };

        function abrirModalImeis(detalleId, nombreProducto) {
            detalleIdActivo = detalleId;
            document.getElementById('modal-imei-producto-nombre').textContent = nombreProducto;
            document.getElementById('modal-imei-loading').classList.remove('hidden');
            document.getElementById('modal-imei-content').classList.add('hidden');
            document.getElementById('modalImeis').classList.remove('hidden');

            fetch(`${URL_DETALLE_BASE}/${detalleId}/imeis`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('modal-imei-loading').classList.add('hidden');
                document.getElementById('modal-imei-content').classList.remove('hidden');

                if (data.success) {
                    renderImeis(data.imeis);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    cerrarModalImeis();
                }
            })
            .catch(() => {
                cerrarModalImeis();
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los IMEIs.' });
            });
        }

        function renderImeis(imeis) {
            const tbody = document.getElementById('tabla-imeis-body');
            const vacio = document.getElementById('modal-imei-vacio');
            const count = document.getElementById('modal-imei-count');

            count.textContent = imeis.length;
            tbody.innerHTML = '';

            if (imeis.length === 0) {
                vacio.classList.remove('hidden');
                return;
            }
            vacio.classList.add('hidden');

            imeis.forEach((imei, idx) => {
                const estadoInfo = estadoImeiLabels[imei.estado_imei] || { label: imei.estado_imei, cls: 'bg-gray-100 text-gray-600' };
                const editable   = imei.estado_imei !== 'vendido';
                const tr = document.createElement('tr');
                tr.id = `imei-row-${imei.id}`;
                tr.innerHTML = `
                    <td class="px-4 py-2.5 text-gray-500 text-xs">${idx + 1}</td>
                    <td class="px-4 py-2.5 font-mono text-sm font-medium text-gray-800" id="imei-codigo-${imei.id}">${imei.codigo_imei}</td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium ${estadoInfo.cls}">${estadoInfo.label}</span>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        ${editable ? `
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="abrirEditarImei(${imei.id}, '${imei.codigo_imei}')"
                                    class="p-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition" title="Editar IMEI">
                                <i class="fas fa-pen text-xs"></i>
                            </button>
                            <button onclick="eliminarImei(${imei.id}, '${imei.codigo_imei}')"
                                    class="p-1 bg-red-100 text-red-600 rounded hover:bg-red-200 transition" title="Eliminar IMEI">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>` : `<span class="text-xs text-gray-400">—</span>`}
                    </td>`;
                tbody.appendChild(tr);
            });
        }

        function cerrarModalImeis() {
            document.getElementById('modalImeis').classList.add('hidden');
            detalleIdActivo = null;
        }

        // ── Editar IMEI ─────────────────────────────────────────────────
        function abrirEditarImei(imeiId, codigoActual) {
            document.getElementById('edit-imei-id').value    = imeiId;
            document.getElementById('edit-imei-codigo').value = codigoActual;
            document.getElementById('edit-imei-error').classList.add('hidden');
            document.getElementById('modalEditarImei').classList.remove('hidden');
            setTimeout(() => document.getElementById('edit-imei-codigo').focus(), 100);
        }

        function cerrarModalEditarImei() {
            document.getElementById('modalEditarImei').classList.add('hidden');
        }

        function guardarImei() {
            const imeiId  = document.getElementById('edit-imei-id').value;
            const codigo  = document.getElementById('edit-imei-codigo').value.trim();
            const errDiv  = document.getElementById('edit-imei-error');
            const btn     = document.getElementById('btn-guardar-imei');

            errDiv.classList.add('hidden');

            if (!/^\d{15}$/.test(codigo)) {
                errDiv.textContent = 'El IMEI debe tener exactamente 15 dígitos numéricos.';
                errDiv.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...';

            fetch(`${URL_IMEI_BASE}/${imeiId}`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ codigo_imei: codigo })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Guardar';

                if (data.success) {
                    const el = document.getElementById(`imei-codigo-${imeiId}`);
                    if (el) el.textContent = codigo;
                    cerrarModalEditarImei();
                    Swal.fire({ icon: 'success', title: 'Actualizado', text: data.message, timer: 1800, showConfirmButton: false });
                } else {
                    errDiv.textContent = data.message || 'Error al actualizar.';
                    errDiv.classList.remove('hidden');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Guardar';
                errDiv.textContent = 'Error de conexión. Intenta de nuevo.';
                errDiv.classList.remove('hidden');
            });
        }

        // ── Eliminar IMEI ────────────────────────────────────────────────
        function eliminarImei(imeiId, codigo) {
            Swal.fire({
                title: '¿Eliminar IMEI?',
                html: `<p class="text-sm text-gray-600">Se eliminará el IMEI <strong class="font-mono">${codigo}</strong> y se reducirá el stock en 1.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-1"></i>Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then(result => {
                if (!result.isConfirmed) return;

                fetch(`${URL_IMEI_BASE}/${imeiId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`imei-row-${imeiId}`);
                        if (row) row.remove();

                        const count = document.getElementById('modal-imei-count');
                        const newCount = parseInt(count.textContent) - 1;
                        count.textContent = newCount;
                        if (newCount === 0) {
                            document.getElementById('modal-imei-vacio').classList.remove('hidden');
                        }

                        Swal.fire({ icon: 'success', title: 'Eliminado', text: data.message, timer: 1800, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' }));
            });
        }

        // ── Enter en campo IMEI ─────────────────────────────────────────
        document.getElementById('edit-imei-codigo').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') guardarImei();
        });
    </script>
</body>
</html>
