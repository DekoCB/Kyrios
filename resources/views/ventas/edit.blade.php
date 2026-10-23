<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Comprobante {{ $venta->codigo }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-10 max-w-2xl">

        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <a href="{{ route('ventas.index') }}" class="hover:text-blue-600 transition-colors">Ventas</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <a href="{{ route('ventas.show', $venta) }}" class="hover:text-blue-600 transition-colors">{{ $venta->codigo }}</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-gray-700 font-medium">Editar</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Editar Comprobante</h1>

        {{-- Advertencia --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 shrink-0"></i>
            <div class="text-sm text-amber-800">
                <p class="font-semibold mb-0.5">Edición limitada</p>
                <p>Solo se pueden modificar campos no contables. Los productos, cantidades y totales no pueden cambiarse (requieren Nota de Crédito SUNAT). Esta edición queda registrada en el sistema.</p>
                <p class="mt-1 text-xs">Ventana de edición: {{ $ventanaMaxima }} horas desde la emisión. Emitido: {{ $venta->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        {{-- Resumen no editable --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Datos del comprobante</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-400">Código</p>
                    <p class="font-semibold text-gray-800">{{ $venta->codigo }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Tipo</p>
                    <p class="font-semibold text-gray-800 capitalize">{{ $venta->tipo_comprobante }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Cliente</p>
                    <p class="font-semibold text-gray-800">{{ $venta->cliente?->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Total</p>
                    <p class="font-bold text-blue-600 text-lg">S/ {{ number_format($venta->total, 2) }}</p>
                </div>
            </div>
        </div>

        @if ($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <ul class="text-sm text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <li><i class="fas fa-times-circle mr-1"></i>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Formulario de edición --}}
        <form action="{{ route('ventas.update', $venta) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- ── SECCIÓN 1: Datos generales ── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5 mb-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2">
                    <i class="fas fa-file-invoice text-blue-400"></i> Datos del comprobante
                </h2>

                {{-- Tipo de comprobante (solo si no enviado a SUNAT) --}}
                @if(!in_array($venta->estado_sunat, ['aceptado', 'enviado']))
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipo de comprobante</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['boleta' => ['icon'=>'fa-receipt','label'=>'Boleta','color'=>'blue'], 'factura' => ['icon'=>'fa-file-invoice-dollar','label'=>'Factura','color'=>'indigo'], 'nota_venta' => ['icon'=>'fa-file-alt','label'=>'Nota de venta','color'=>'gray'], 'ticket' => ['icon'=>'fa-ticket-alt','label'=>'Ticket','color'=>'purple']] as $tipo => $cfg)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-{{ $cfg['color'] }}-400 hover:bg-{{ $cfg['color'] }}-50 transition-all has-[:checked]:border-{{ $cfg['color'] }}-500 has-[:checked]:bg-{{ $cfg['color'] }}-50">
                            <input type="radio" name="tipo_comprobante" value="{{ $tipo }}"
                                   {{ old('tipo_comprobante', $venta->tipo_comprobante) === $tipo ? 'checked' : '' }}
                                   class="text-{{ $cfg['color'] }}-600">
                            <i class="fas {{ $cfg['icon'] }} text-{{ $cfg['color'] }}-500 text-xs"></i>
                            <span class="text-sm font-medium">{{ $cfg['label'] }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-amber-600 mt-1"><i class="fas fa-info-circle"></i> Solo disponible antes de enviar a SUNAT.</p>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}"
                           max="{{ date('Y-m-d') }}"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                @if(!$venta->es_credito)
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Método de pago</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['efectivo' => '💵 Efectivo', 'transferencia' => '🏦 Transferencia', 'yape' => '📱 Yape', 'plin' => '📱 Plin', 'mixto' => '🔀 Mixto'] as $metodo => $label)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="metodo_pago" value="{{ $metodo }}"
                                   {{ old('metodo_pago', $venta->metodo_pago) === $metodo ? 'checked' : '' }}
                                   class="text-blue-600">
                            <span class="text-sm font-medium">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Observaciones</label>
                    <textarea name="observaciones" rows="3"
                              placeholder="Notas adicionales sobre esta venta..."
                              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none">{{ old('observaciones', $venta->observaciones) }}</textarea>
                </div>
            </div>

            {{-- ── SECCIÓN 2: Datos de envío ── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4 mb-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2">
                    <i class="fas fa-truck text-green-400"></i> Datos de envío / traslado
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nro. Guía de Remisión</label>
                        <input type="text" name="guia_remision" maxlength="20"
                               value="{{ old('guia_remision', $venta->guia_remision) }}"
                               placeholder="Ej: T001-00000001"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Transportista</label>
                            <input type="text" name="transportista" maxlength="200"
                                   value="{{ old('transportista', $venta->transportista) }}"
                                   placeholder="Nombre del transportista"
                                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Placa del vehículo</label>
                            <input type="text" name="placa_vehiculo" maxlength="10"
                                   value="{{ old('placa_vehiculo', $venta->placa_vehiculo) }}"
                                   placeholder="Ej: ABC-123"
                                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent uppercase">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── SECCIÓN 3: Guía de Remisión detallada ── --}}
            @php $guia = $venta->guiaRemision; @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4 mb-4"
                 x-data="{ open: {{ $guia ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between text-sm font-semibold text-gray-500 uppercase tracking-wide">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-orange-400"></i> Guía de Remisión (detalle SUNAT)
                        @if($guia)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full normal-case font-medium">Configurada</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full normal-case font-medium">Opcional</span>
                        @endif
                    </span>
                    <i class="fas fa-chevron-down transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="open" x-cloak class="space-y-4 pt-2 border-t border-gray-100">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo de traslado</label>
                            <select name="guia[motivo_traslado]"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                                <option value="">— Seleccionar —</option>
                                @foreach(['VENTA'=>'Venta','COMPRA'=>'Compra','TRASLADO_ENTRE_ALMACENES'=>'Traslado entre almacenes','IMPORTACION'=>'Importación','EXPORTACION'=>'Exportación'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ old('guia.motivo_traslado', $guia?->motivo_traslado) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Modalidad</label>
                            <select name="guia[modalidad]"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                                <option value="">— Seleccionar —</option>
                                <option value="privado" {{ old('guia.modalidad', $guia?->modalidad) === 'privado' ? 'selected' : '' }}>Transporte Privado</option>
                                <option value="publico" {{ old('guia.modalidad', $guia?->modalidad) === 'publico' ? 'selected' : '' }}>Transporte Público</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de traslado</label>
                            <input type="date" name="guia[fecha_traslado]"
                                   value="{{ old('guia.fecha_traslado', $guia?->fecha_traslado?->format('Y-m-d')) }}"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Peso total (kg)</label>
                                <input type="number" name="guia[peso_total]" step="0.01" min="0"
                                       value="{{ old('guia.peso_total', $guia?->peso_total) }}"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Bultos</label>
                                <input type="number" name="guia[bultos]" min="0"
                                       value="{{ old('guia.bultos', $guia?->bultos) }}"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Dirección de partida</label>
                            <input type="text" name="guia[direccion_partida]" maxlength="300"
                                   value="{{ old('guia.direccion_partida', $guia?->direccion_partida) }}"
                                   placeholder="Punto de origen"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ubigeo partida</label>
                            <input type="text" name="guia[ubigeo_partida]" maxlength="6"
                                   value="{{ old('guia.ubigeo_partida', $guia?->ubigeo_partida) }}"
                                   placeholder="150101"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Dirección de llegada</label>
                            <input type="text" name="guia[direccion_llegada]" maxlength="300"
                                   value="{{ old('guia.direccion_llegada', $guia?->direccion_llegada) }}"
                                   placeholder="Punto de destino"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ubigeo llegada</label>
                            <input type="text" name="guia[ubigeo_llegada]" maxlength="6"
                                   value="{{ old('guia.ubigeo_llegada', $guia?->ubigeo_llegada) }}"
                                   placeholder="150101"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Datos del transportista (SUNAT)</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo doc.</label>
                                <select name="guia[transportista_tipo_doc]"
                                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                                    <option value="">—</option>
                                    <option value="6" {{ old('guia.transportista_tipo_doc', $guia?->transportista_tipo_doc) === '6' ? 'selected' : '' }}>RUC</option>
                                    <option value="1" {{ old('guia.transportista_tipo_doc', $guia?->transportista_tipo_doc) === '1' ? 'selected' : '' }}>DNI</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Nro. documento</label>
                                <input type="text" name="guia[transportista_doc]" maxlength="15"
                                       value="{{ old('guia.transportista_doc', $guia?->transportista_doc) }}"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre / Razón Social</label>
                                <input type="text" name="guia[transportista_nombre]" maxlength="200"
                                       value="{{ old('guia.transportista_nombre', $guia?->transportista_nombre) }}"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <a href="{{ route('ventas.show', $venta) }}"
                   class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-3 rounded-xl font-semibold text-sm transition-colors text-center">
                    Cancelar
                </a>
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold text-sm transition-colors">
                    <i class="fas fa-save mr-1"></i> Guardar cambios
                </button>
            </div>
        </form>

    </div>
</body>
</html>
