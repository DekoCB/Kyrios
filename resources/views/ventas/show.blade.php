<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta {{ $venta->codigo }} - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .md\:ml-64 { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" class="no-print" />

    <div class="md:ml-64 p-4 md:p-10">

        {{-- Modal de confirmación de venta nueva --}}
        @if(request()->has('nuevo'))
        <div x-data="{ show: true }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="show = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-6 text-center">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check text-white text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white">
                        @if($venta->tipo_comprobante === 'cotizacion')
                            ¡Cotización Guardada!
                        @else
                            ¡Venta Registrada!
                        @endif
                    </h3>
                    <p class="text-green-100 text-sm mt-1">{{ $venta->codigo }}</p>
                </div>
                <div class="p-6 text-center">
                    <div class="text-gray-600 dark:text-gray-300 text-sm mb-1">Total cobrado</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                        S/ {{ number_format($venta->total, 2) }}
                    </div>
                    @if($venta->tipo_comprobante !== 'cotizacion' && $venta->metodo_pago)
                    <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full px-3 py-1 text-xs font-medium mb-4">
                        <i class="fas fa-credit-card"></i>
                        {{ ucfirst($venta->metodo_pago) }}
                    </div>
                    @endif
                    <div class="flex gap-3">
                        @if($venta->tipo_comprobante !== 'cotizacion')
                        <a href="{{ route('ventas.pdf', [$venta, 'formato' => 'ticket']) }}" target="_blank"
                           class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-2.5 text-sm font-semibold transition flex items-center justify-center gap-2">
                            <i class="fas fa-receipt"></i> Ticket
                        </a>
                        @endif
                        <button @click="show = false"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">
                            Aceptar
                        </button>
                    </div>
                    <a href="{{ route('ventas.create') }}"
                       class="block mt-3 text-sm text-blue-600 hover:text-blue-700 font-medium transition">
                        <i class="fas fa-plus-circle mr-1"></i> Nueva Venta
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal de confirmación de edición --}}
        @if(request()->has('actualizado'))
        <div x-data="{ show: true }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="show = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                {{-- Header --}}
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-6 text-center">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-double text-white text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white">¡Cambios guardados!</h3>
                    <p class="text-blue-100 text-sm mt-1">{{ $venta->codigo }}</p>
                </div>
                {{-- Body --}}
                <div class="p-6">
                    {{-- Resumen de datos actualizados --}}
                    <div class="space-y-2 mb-5">
                        <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                            <span class="text-gray-500 flex items-center gap-2"><i class="fas fa-file-invoice w-4 text-center text-blue-400"></i> Tipo</span>
                            <span class="font-semibold text-gray-800 capitalize">{{ $venta->tipo_comprobante }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                            <span class="text-gray-500 flex items-center gap-2"><i class="fas fa-calendar w-4 text-center text-blue-400"></i> Fecha</span>
                            <span class="font-semibold text-gray-800">{{ $venta->fecha->format('d/m/Y') }}</span>
                        </div>
                        @if($venta->metodo_pago)
                        <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                            <span class="text-gray-500 flex items-center gap-2"><i class="fas fa-credit-card w-4 text-center text-blue-400"></i> Pago</span>
                            <span class="font-semibold text-gray-800 capitalize">{{ $venta->metodo_pago }}</span>
                        </div>
                        @endif
                        @if($venta->guia_remision)
                        <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                            <span class="text-gray-500 flex items-center gap-2"><i class="fas fa-truck w-4 text-center text-blue-400"></i> Guía</span>
                            <span class="font-semibold text-gray-800">{{ $venta->guia_remision }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between text-sm py-2">
                            <span class="text-gray-500 flex items-center gap-2"><i class="fas fa-money-bill w-4 text-center text-blue-400"></i> Total</span>
                            <span class="font-bold text-blue-600 text-base">S/ {{ number_format($venta->total, 2) }}</span>
                        </div>
                    </div>
                    {{-- Acciones --}}
                    <div class="flex gap-3">
                        <a href="{{ route('ventas.pdf', [$venta, 'formato' => 'a4']) }}" target="_blank"
                           class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl py-2.5 text-sm font-semibold transition flex items-center justify-center gap-2">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <button @click="show = false"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 text-sm font-semibold transition flex items-center justify-center gap-2">
                            <i class="fas fa-check"></i> Aceptar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Flash --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 shadow-sm">
                <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 shadow-sm">
                <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
            </div>
        @endif

        {{-- Page header --}}
        <div class="flex items-start justify-between mb-6 no-print">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
                    <a href="{{ route('ventas.index') }}" class="hover:text-blue-600 transition-colors">Ventas</a>
                    <i class="fas fa-chevron-right text-xs"></i>
                    <span class="text-gray-700 font-medium">{{ $venta->codigo }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Detalle de Venta</h1>
            </div>

            @php
                $waPhone = preg_replace('/\D/', '', $venta->cliente->telefono ?? '');
                if (strlen($waPhone) === 9) {
                    $waPhone = '51' . $waPhone;
                } elseif (strlen($waPhone) === 10 && str_starts_with($waPhone, '0')) {
                    $waPhone = '51' . substr($waPhone, 1);
                } elseif (strlen($waPhone) < 9) {
                    $waPhone = '';
                }

                $consultaLink = url('/consultas/' . $venta->codigo);

                $tipoDoc = match($venta->tipo_comprobante) {
                    'boleta'     => 'Boleta',
                    'factura'    => 'Factura',
                    'nota_venta' => 'Nota de Venta',
                    default      => ucfirst($venta->tipo_comprobante),
                };

                $waMsg = "Hola {$venta->cliente->nombre}, le compartimos su comprobante de compra 🧾\n\n"
                       . "📄 *{$tipoDoc} {$venta->codigo}*\n"
                       . "📅 " . $venta->fecha->format('d/m/Y') . "\n"
                       . "💰 Total: S/ " . number_format($venta->total, 2) . "\n\n"
                       . "Descargue su comprobante aquí:\n{$consultaLink}\n\n"
                       . "Gracias por su preferencia. 🙏";

                $waUrl = $waPhone ? 'https://wa.me/' . $waPhone . '?text=' . urlencode($waMsg) : null;
            @endphp

            <div class="flex items-center gap-2 flex-wrap">
                @if($venta->tipo_comprobante !== 'cotizacion')
                <a href="{{ route('ventas.pdf', [$venta, 'formato' => 'a4']) }}" target="_blank"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-file-pdf"></i> PDF A4
                </a>
                <a href="{{ route('ventas.pdf', [$venta, 'formato' => 'ticket']) }}" target="_blank"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-receipt"></i> Ticket 80mm
                </a>
                <button onclick="window.print()"
                        class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300 px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                @endif

                @if($waUrl)
                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                @else
                <div x-data='{
                    phone: "",
                    msg: @json($waMsg),
                    get digits() { return this.phone.replace(/\D/g, ""); },
                    get valid() { return this.digits.length === 9; },
                    send() {
                        if (!this.valid) return;
                        const n = "51" + this.digits;
                        window.open("https://wa.me/" + n + "?text=" + encodeURIComponent(this.msg), "_blank");
                    }
                }' class="flex items-center gap-0.5">
                    <span class="inline-flex items-center gap-1.5 bg-green-500 text-white pl-3 pr-2 py-2 rounded-l-xl text-sm font-medium shadow-sm select-none">
                        <i class="fab fa-whatsapp"></i> WA
                    </span>
                    <input x-model="phone" type="tel" placeholder="9xx xxx xxx"
                           maxlength="9" @keydown.enter="send()"
                           class="border-y border-gray-300 px-2 py-2 text-sm w-28 focus:outline-none focus:ring-1 focus:ring-green-400 focus:border-green-400">
                    <button @click="send()" :disabled="!valid"
                            :class="valid ? 'bg-green-500 hover:bg-green-600 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                            class="inline-flex items-center text-white px-3 py-2 rounded-r-xl text-sm font-medium transition-colors shadow-sm">
                        <i class="fas fa-paper-plane text-xs"></i>
                    </button>
                </div>
                @endif

                @if($venta->guiaRemision)
                <a href="{{ route('ventas.guia-pdf', $venta) }}" target="_blank"
                   class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-truck"></i> Ver guía
                </a>
                <a href="{{ route('ventas.guia-pdf', $venta) }}" target="_blank"
                   onclick="setTimeout(() => { const w = window.open(this.href, '_blank'); w && w.print(); }, 200); return false;"
                   class="inline-flex items-center gap-2 border border-teal-300 bg-teal-50 text-teal-700 hover:bg-teal-100 px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-print"></i> Imprimir guía
                </a>
                @endif
                <a href="{{ $venta->tipo_comprobante === 'cotizacion' ? route('ventas.cotizaciones') : route('ventas.index') }}"
                   class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>

                @if($venta->tipo_comprobante === 'cotizacion' && in_array(auth()->user()->role->nombre, ['Administrador', 'Tienda']))
                @php
                    $detallesSerieConvertir = $venta->detalles
                        ->filter(fn($d) => $d->producto?->tipo_inventario === 'serie')
                        ->map(fn($d) => [
                            'id'          => $d->id,
                            'producto_id' => $d->producto_id,
                            'variante_id' => $d->variante_id,
                            'nombre'      => trim(($d->producto->nombre ?? '') . ($d->variante?->nombre_completo ? ' — ' . $d->variante->nombre_completo : '')),
                            'cantidad'    => $d->cantidad,
                        ])
                        ->values();
                @endphp
                <div x-data="{
                        showConvertir: false,
                        enviando: false,
                        errMsg: '',
                        tipoComprobante: 'boleta',
                        metodoPago: '',
                        detallesSerie: {{ $detallesSerieConvertir->toJson() }},
                        almacenId: {{ $venta->almacen_id }},
                        imeisDisponibles: {},
                        imeisSeleccionados: {},
                        cargandoImeis: {},
                        async abrir() {
                            this.showConvertir = true;
                            this.errMsg = '';
                            for (const d of this.detallesSerie) {
                                this.imeisSeleccionados[d.id] = this.imeisSeleccionados[d.id] || [];
                                await this.cargarImeis(d);
                            }
                        },
                        async cargarImeis(d) {
                            this.cargandoImeis[d.id] = true;
                            try {
                                const params = new URLSearchParams({ producto_id: d.producto_id, almacen_id: this.almacenId });
                                if (d.variante_id) params.append('variante_id', d.variante_id);
                                const res = await fetch('{{ route('ventas.imeis-disponibles') }}?' + params.toString());
                                this.imeisDisponibles[d.id] = await res.json();
                            } catch { this.imeisDisponibles[d.id] = []; }
                            finally { this.cargandoImeis[d.id] = false; }
                        },
                        toggleImei(detalleId, imei) {
                            const sel = this.imeisSeleccionados[detalleId];
                            const idx = sel.findIndex(i => i.id === imei.id);
                            if (idx >= 0) sel.splice(idx, 1);
                            else sel.push({ id: imei.id, codigo_imei: imei.codigo_imei });
                        },
                        seleccionado(detalleId, imei) {
                            return this.imeisSeleccionados[detalleId].some(i => i.id === imei.id);
                        },
                        get faltanImeis() {
                            return this.detallesSerie.some(d => (this.imeisSeleccionados[d.id]?.length || 0) !== d.cantidad);
                        },
                        async enviar() {
                            if (!this.tipoComprobante || !this.metodoPago) return;
                            if (this.faltanImeis) { this.errMsg = 'Selecciona la cantidad exacta de IMEIs para cada producto.'; return; }
                            this.enviando = true; this.errMsg = '';
                            try {
                                const imeis = {};
                                for (const d of this.detallesSerie) {
                                    imeis[d.id] = this.imeisSeleccionados[d.id].map(i => i.codigo_imei);
                                }
                                const res = await fetch('{{ route('ventas.convertir', $venta) }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ tipo_comprobante: this.tipoComprobante, metodo_pago: this.metodoPago, imeis })
                                });
                                if (res.ok || res.redirected) {
                                    window.location.href = res.url || '{{ route('ventas.show', $venta) }}';
                                } else {
                                    const data = await res.json().catch(() => ({}));
                                    this.errMsg = data.message || Object.values(data.errors || {}).flat().join('. ') || 'Error al convertir la venta';
                                }
                            } catch { this.errMsg = 'Error de conexión'; }
                            finally { this.enviando = false; }
                        }
                     }">
                    <button @click="abrir()"
                            class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-file-invoice"></i> Convertir a Venta
                    </button>
                    <div x-show="showConvertir" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showConvertir = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden max-h-[90vh] flex flex-col">
                            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-5 shrink-0">
                                <h3 class="text-lg font-bold text-white">Convertir Cotización</h3>
                                <p class="text-purple-200 text-sm mt-0.5">{{ $venta->codigo }}</p>
                            </div>
                            <div class="p-6 overflow-y-auto">
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Comprobante *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
                                            <input type="radio" x-model="tipoComprobante" value="boleta" class="text-purple-600" required>
                                            <span class="text-sm font-medium">Boleta</span>
                                        </label>
                                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
                                            <input type="radio" x-model="tipoComprobante" value="factura" class="text-purple-600">
                                            <span class="text-sm font-medium">Factura</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-5">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Método de Pago *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach(['efectivo' => 'fa-money-bill-wave', 'transferencia' => 'fa-university', 'yape' => 'fa-mobile-alt', 'plin' => 'fa-mobile-alt'] as $metodo => $icono)
                                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
                                            <input type="radio" x-model="metodoPago" value="{{ $metodo }}" class="text-purple-600" required>
                                            <i class="fas {{ $icono }} text-gray-500 text-sm"></i>
                                            <span class="text-sm font-medium capitalize">{{ $metodo }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Selección de IMEIs para productos tipo serie --}}
                                <template x-if="detallesSerie.length > 0">
                                    <div class="mb-5 space-y-3">
                                        <label class="block text-sm font-semibold text-gray-700">Selecciona los IMEI a vender *</label>
                                        <template x-for="d in detallesSerie" :key="d.id">
                                            <div class="border border-gray-200 rounded-xl p-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-sm font-medium text-gray-800" x-text="d.nombre"></span>
                                                    <span class="text-xs font-semibold"
                                                          :class="(imeisSeleccionados[d.id]?.length || 0) === d.cantidad ? 'text-emerald-600' : 'text-amber-600'"
                                                          x-text="(imeisSeleccionados[d.id]?.length || 0) + ' / ' + d.cantidad"></span>
                                                </div>
                                                <div x-show="cargandoImeis[d.id]" class="text-xs text-gray-400">Cargando IMEIs disponibles...</div>
                                                <div x-show="!cargandoImeis[d.id] && (imeisDisponibles[d.id]?.length || 0) === 0" class="text-xs text-red-500">
                                                    No hay IMEIs disponibles en el almacén de esta venta.
                                                </div>
                                                <div class="max-h-32 overflow-y-auto space-y-1">
                                                    <template x-for="imei in (imeisDisponibles[d.id] || [])" :key="imei.id">
                                                        <label class="flex items-center gap-2 text-xs px-2 py-1 rounded-lg cursor-pointer hover:bg-gray-50"
                                                               :class="seleccionado(d.id, imei) ? 'bg-purple-50' : ''">
                                                            <input type="checkbox"
                                                                   :checked="seleccionado(d.id, imei)"
                                                                   @change="toggleImei(d.id, imei)"
                                                                   class="text-purple-600 rounded">
                                                            <span class="font-mono" x-text="imei.codigo_imei"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div x-show="errMsg" x-cloak class="mb-4 text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2" x-text="errMsg"></div>

                                <div class="bg-gray-50 rounded-xl px-4 py-3 mb-5 flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Total a cobrar</span>
                                    <span class="text-xl font-bold text-gray-900">S/ {{ number_format($venta->total, 2) }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" @click="showConvertir = false"
                                            class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                        Cancelar
                                    </button>
                                    <button type="button" @click="enviar()"
                                            :disabled="enviando || !tipoComprobante || !metodoPago || faltanImeis"
                                            class="flex-1 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                        <i class="fas" :class="enviando ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                                        <span x-text="enviando ? 'Procesando...' : 'Convertir'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($venta->estado_pago === 'pendiente' && in_array(auth()->user()->role->nombre, ['Administrador', 'Tienda']))
                <div x-data="{ showModal: false }">
                    <button @click="showModal = true"
                            class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-check-circle"></i> Confirmar Pago
                    </button>

                    {{-- Modal confirmar pago --}}
                    <div x-show="showModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-5">
                                <h3 class="text-lg font-bold text-white">Confirmar Pago</h3>
                                <p class="text-green-100 text-sm mt-0.5">Venta {{ $venta->codigo }}</p>
                            </div>
                            <form action="{{ route('ventas.confirmar-pago', $venta) }}" method="POST" class="p-6">
                                @csrf
                                <div class="mb-5">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Método de Pago *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach(['efectivo' => 'fa-money-bill-wave', 'transferencia' => 'fa-university', 'yape' => 'fa-mobile-alt', 'plin' => 'fa-mobile-alt'] as $metodo => $icono)
                                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-green-400 hover:bg-green-50 transition-all has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                            <input type="radio" name="metodo_pago" value="{{ $metodo }}" class="text-green-600 focus:ring-green-500" required>
                                            <i class="fas {{ $icono }} text-gray-500 text-sm"></i>
                                            <span class="text-sm font-medium capitalize">{{ $metodo }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-4 py-3 mb-5 flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Total a cobrar</span>
                                    <span class="text-xl font-bold text-gray-900">S/ {{ number_format($venta->total, 2) }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" @click="showModal = false"
                                            class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                        Cancelar
                                    </button>
                                    <button type="submit"
                                            class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                        <i class="fas fa-check mr-1"></i> Confirmar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Ver crédito --}}
                @if($venta->es_credito && $venta->estado_pago !== 'anulado')
                <a href="{{ route('ventas.credito.show', $venta) }}"
                   class="inline-flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                    <i class="fas fa-calendar-alt"></i> Ver Crédito
                </a>
                @endif

                {{-- Acciones: Editar / Anular / Eliminar (Admin y Tienda) --}}
                @php
                    $rolActual       = auth()->user()->role->nombre;
                    $puedeEditar     = in_array($rolActual, ['Administrador','Tienda'])
                                       && $venta->estado_pago !== 'anulado'
                                       && !$venta->es_nota_credito
                                       && $venta->created_at->diffInHours(now()) <= config('ventas.edit_window_hours', 24);
                    // Anular directo: solo si NO fue aceptado por SUNAT
                    $puedeAnular     = in_array($rolActual, ['Administrador','Tienda'])
                                       && !in_array($venta->estado_pago, ['anulado','cotizacion'])
                                       && !$venta->es_nota_credito
                                       && $venta->puede_anular_directo;
                    // Nota de Crédito: solo si fue aceptado por SUNAT y no es cotización/NC
                    $puedeGenerarNC  = in_array($rolActual, ['Administrador','Tienda'])
                                       && $venta->es_aceptado_sunat
                                       && !in_array($venta->estado_pago, ['anulado','cotizacion'])
                                       && !$venta->es_nota_credito;
                    $puedeEliminar   = in_array($rolActual, ['Administrador','Tienda'])
                                       && $venta->estado_pago !== 'cotizacion'
                                       && !$venta->es_aceptado_sunat; // no eliminar docs aceptados por SUNAT
                    $esTienda        = $rolActual === 'Tienda';
                @endphp

                @if($puedeEditar || $puedeAnular || $puedeEliminar || $puedeGenerarNC)
                <div x-data="{
                    showClave:    false,
                    showAnular:   false,
                    showEliminar: false,
                    pendingAccion: '',
                    clave: '',
                    claveError: '',
                    cargando: false,
                    esTienda: {{ $esTienda ? 'true' : 'false' }},

                    iniciarAccion(accion) {
                        if (this.esTienda) {
                            this.pendingAccion = accion;
                            this.clave = '';
                            this.claveError = '';
                            this.showClave = true;
                        } else {
                            this.ejecutarAccion(accion);
                        }
                    },

                    showNC: false,
                    motivoNc: '01',

                    ejecutarAccion(accion) {
                        if (accion === 'editar') {
                            window.location.href = '{{ route('ventas.edit', $venta) }}';
                        } else if (accion === 'anular') {
                            this.showAnular = true;
                        } else if (accion === 'eliminar') {
                            this.showEliminar = true;
                        } else if (accion === 'nota_credito') {
                            this.showNC = true;
                        }
                    },

                    async verificarClave() {
                        this.cargando = true;
                        this.claveError = '';
                        try {
                            const resp = await fetch('{{ route('ventas.verificar-clave', $venta) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                                },
                                body: JSON.stringify({ clave: this.clave })
                            });
                            const data = await resp.json();
                            if (data.ok) {
                                this.showClave = false;
                                this.ejecutarAccion(this.pendingAccion);
                            } else {
                                this.claveError = data.mensaje || 'Contraseña incorrecta.';
                            }
                        } catch(e) {
                            this.claveError = 'Error de conexión. Intente nuevamente.';
                        }
                        this.cargando = false;
                    }
                }">

                    {{-- Botón Editar --}}
                    @if($puedeEditar)
                    <button @click="iniciarAccion('editar')"
                            class="inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    @endif

                    {{-- Botón Nota de Crédito SUNAT (solo si doc ya fue aceptado) --}}
                    @if($puedeGenerarNC)
                    <button @click="iniciarAccion('nota_credito')"
                            class="inline-flex items-center gap-2 border border-indigo-400 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-file-minus"></i> Nota de Crédito
                    </button>
                    @endif

                    {{-- Botón Anular --}}
                    @if($puedeAnular)
                    <button @click="iniciarAccion('anular')"
                            class="inline-flex items-center gap-2 border border-red-300 bg-red-50 text-red-700 hover:bg-red-100 px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-ban"></i> Anular
                    </button>
                    @endif

                    {{-- Botón Eliminar --}}
                    @if($puedeEliminar)
                    <button @click="iniciarAccion('eliminar')"
                            class="inline-flex items-center gap-2 border border-red-700 bg-red-700 text-white hover:bg-red-800 px-4 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                        <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                    @endif

                    {{-- Modal: verificar contraseña (solo Tienda) --}}
                    <div x-show="showClave" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showClave = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-5">
                                <h3 class="text-lg font-bold text-white"><i class="fas fa-lock mr-2"></i>Verificación de seguridad</h3>
                                <p class="text-gray-300 text-sm mt-0.5">Ingresa tu contraseña para continuar</p>
                            </div>
                            <div class="p-6">
                                <p class="text-sm text-gray-600 mb-4">Para realizar esta acción debes confirmar tu identidad ingresando la contraseña de tu cuenta.</p>
                                <input type="password" x-model="clave" @keyup.enter="verificarClave()"
                                       placeholder="Contraseña"
                                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-gray-500 focus:border-gray-500 outline-none mb-2">
                                <p x-show="claveError" x-text="claveError" class="text-red-600 text-xs mb-3"></p>
                                <div class="flex gap-3">
                                    <button type="button" @click="showClave = false"
                                            class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                        Cancelar
                                    </button>
                                    <button type="button" @click="verificarClave()" :disabled="cargando || !clave"
                                            class="flex-1 bg-gray-800 hover:bg-gray-900 disabled:opacity-50 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                        <span x-show="!cargando">Confirmar</span>
                                        <span x-show="cargando"><i class="fas fa-spinner fa-spin mr-1"></i>Verificando...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal: confirmar Anular --}}
                    <div x-show="showAnular" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showAnular = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-5">
                                <h3 class="text-lg font-bold text-white">Anular Comprobante</h3>
                                <p class="text-red-100 text-sm mt-0.5">{{ $venta->codigo }}</p>
                            </div>
                            <div class="p-6">
                                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 text-sm text-red-700">
                                    <p class="font-semibold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Esta acción es irreversible</p>
                                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                                        <li>El stock será devuelto al almacén</li>
                                        <li>La venta quedará visible como "Anulado"</li>
                                        @if($venta->estado_pago === 'pagado')<li>Se registrará un egreso en caja</li>@endif
                                        @if($venta->es_credito)<li>La cuenta por cobrar será cancelada</li>@endif
                                    </ul>
                                </div>
                                <form action="{{ route('ventas.anular', $venta) }}" method="POST">
                                    @csrf
                                    <div class="flex gap-3">
                                        <button type="button" @click="showAnular = false"
                                                class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="submit"
                                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                            <i class="fas fa-ban mr-1"></i> Anular
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Modal: confirmar Eliminar --}}
                    <div x-show="showEliminar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showEliminar = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-800 to-red-900 px-6 py-5">
                                <h3 class="text-lg font-bold text-white">Eliminar Comprobante</h3>
                                <p class="text-red-200 text-sm mt-0.5">{{ $venta->codigo }}</p>
                            </div>
                            <div class="p-6">
                                <div class="bg-red-50 border border-red-300 rounded-xl p-4 mb-5 text-sm text-red-800">
                                    <p class="font-semibold mb-1"><i class="fas fa-skull-crossbones mr-1"></i>Eliminación permanente</p>
                                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                                        <li>La venta desaparecerá de todos los listados</li>
                                        <li>El stock será devuelto al almacén</li>
                                        @if($venta->estado_pago === 'pagado')<li>Se registrará un egreso en caja</li>@endif
                                        @if($venta->es_credito)<li>La cuenta por cobrar será cancelada</li>@endif
                                    </ul>
                                </div>
                                <form action="{{ route('ventas.destroy', $venta) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <div class="flex gap-3">
                                        <button type="button" @click="showEliminar = false"
                                                class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="submit"
                                                class="flex-1 bg-red-800 hover:bg-red-900 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                            <i class="fas fa-trash-alt mr-1"></i> Eliminar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Modal: Nota de Crédito SUNAT --}}
                    <div x-show="showNC" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showNC = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-6 py-5">
                                <h3 class="text-lg font-bold text-white"><i class="fas fa-file-minus mr-2"></i>Nota de Crédito Electrónica</h3>
                                <p class="text-indigo-200 text-sm mt-0.5">{{ $venta->numero_documento ?? $venta->codigo }}</p>
                            </div>
                            <div class="p-6">
                                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-5 text-sm text-indigo-800">
                                    <p class="font-semibold mb-1"><i class="fas fa-info-circle mr-1"></i>¿Por qué Nota de Crédito?</p>
                                    <p class="text-xs leading-relaxed">Este comprobante ya fue <strong>aceptado por SUNAT</strong>. La normativa peruana exige emitir una Nota de Crédito para anularlo — no se puede simplemente eliminar o marcar como anulado.</p>
                                </div>
                                <form action="{{ route('ventas.nota-credito', $venta) }}" method="POST">
                                    @csrf
                                    <div class="mb-5">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Motivo (Tabla 10 SUNAT) *</label>
                                        <select name="motivo_codigo" x-model="motivoNc"
                                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                            @foreach(\App\Services\VentaService::MOTIVOS_NC as $codigo => $desc)
                                            <option value="{{ $codigo }}">{{ $codigo }} — {{ $desc }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl px-4 py-3 mb-5 flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Monto a revertir</span>
                                        <span class="text-xl font-bold text-gray-900">S/ {{ number_format($venta->total, 2) }}</span>
                                    </div>
                                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5 text-xs text-amber-700">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Después de generar la NC debes enviarla a SUNAT (OSE/SOL) para que sea válida legalmente.
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="button" @click="showNC = false"
                                                class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="submit"
                                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                                            <i class="fas fa-file-minus mr-1"></i> Generar NC
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
                @endif

            </div>
        </div>

        {{-- Estado badge --}}
        @php
            $estadoConfig = [
                'pendiente'   => ['bg-amber-50 text-amber-700 border-amber-200', 'fa-clock', 'Pago Pendiente'],
                'pagado'      => ['bg-green-50 text-green-700 border-green-200', 'fa-check-circle', 'Pagado'],
                'cancelado'   => ['bg-red-50 text-red-700 border-red-200', 'fa-times-circle', 'Cancelado'],
                'anulado'     => ['bg-red-50 text-red-700 border-red-200', 'fa-ban', 'Anulado'],
                'cotizacion'  => ['bg-purple-50 text-purple-700 border-purple-200', 'fa-file-contract', 'Cotización'],
                'credito'     => ['bg-orange-50 text-orange-700 border-orange-200', 'fa-calendar-alt', 'Crédito'],
            ];
            [$badgeClass, $badgeIcon, $badgeLabel] = $estadoConfig[$venta->estado_pago] ?? ['bg-gray-50 text-gray-700 border-gray-200', 'fa-circle', ucfirst($venta->estado_pago)];
        @endphp
        <div class="flex flex-wrap items-center gap-3 mb-6">
            {{-- Badge estado de pago --}}
            <div class="inline-flex items-center gap-2 border {{ $badgeClass }} px-4 py-1.5 rounded-full text-sm font-semibold">
                <i class="fas {{ $badgeIcon }}"></i>
                {{ $badgeLabel }}
                @if($venta->estado_pago === 'pagado' && $venta->fecha_confirmacion)
                    <span class="text-xs opacity-70">· {{ $venta->fecha_confirmacion->format('d/m/Y H:i') }}</span>
                @endif
            </div>

            {{-- Badge estado SUNAT (solo boleta/factura/NC) --}}
            @if(!in_array($venta->tipo_comprobante, ['cotizacion']))
            @php
                $sunatConfig = [
                    'pendiente_envio' => ['bg-yellow-50 text-yellow-700 border-yellow-300', 'fa-clock', 'Pendiente envío SUNAT'],
                    'enviado'         => ['bg-blue-50 text-blue-700 border-blue-300',   'fa-paper-plane', 'Enviado a SUNAT'],
                    'aceptado'        => ['bg-green-50 text-green-700 border-green-300', 'fa-shield-alt', 'Aceptado por SUNAT'],
                    'rechazado'       => ['bg-red-50 text-red-700 border-red-300',       'fa-times-circle', 'Rechazado por SUNAT'],
                    'anulado_baja'    => ['bg-gray-50 text-gray-600 border-gray-300',   'fa-ban', 'Anulado (NC emitida)'],
                    'no_aplica'       => ['bg-gray-50 text-gray-500 border-gray-200',   'fa-minus-circle', 'No aplica SUNAT'],
                ];
                [$sBadge, $sIcon, $sLabel] = $sunatConfig[$venta->estado_sunat] ?? ['bg-gray-50 text-gray-500 border-gray-200', 'fa-question', $venta->estado_sunat ?? '—'];
            @endphp
            <div class="inline-flex items-center gap-2 border {{ $sBadge }} px-3 py-1.5 rounded-full text-xs font-semibold">
                <i class="fas {{ $sIcon }}"></i>
                {{ $sLabel }}
            </div>
            @endif

            {{-- Badge "Nota de Crédito" si es NC --}}
            @if($venta->es_nota_credito)
            <div class="inline-flex items-center gap-2 border bg-indigo-50 text-indigo-700 border-indigo-300 px-3 py-1.5 rounded-full text-xs font-semibold">
                <i class="fas fa-file-minus"></i>
                Nota de Crédito · {{ $venta->motivo_nc_codigo }} — {{ $venta->motivo_nc_descripcion }}
            </div>
            @if($venta->ventaOrigen)
            <div class="text-xs text-gray-500">
                Referencia: <a href="{{ route('ventas.show', $venta->ventaOrigen) }}" class="text-indigo-600 font-semibold hover:underline">{{ $venta->ventaOrigen->numero_documento ?? $venta->ventaOrigen->codigo }}</a>
            </div>
            @endif
            @endif
        </div>

        {{-- Info cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-7">
            {{-- Venta --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-receipt text-blue-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-700 uppercase tracking-wider">Venta</h3>
                </div>
                <dl class="space-y-3">
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Código</dt>
                        <dd class="font-mono font-bold text-blue-600">{{ $venta->codigo }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Fecha</dt>
                        <dd class="text-sm text-gray-700 font-medium">{{ $venta->fecha->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Hora</dt>
                        <dd class="text-sm text-gray-700">{{ $venta->created_at->format('H:i') }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Almacén</dt>
                        <dd class="text-sm text-gray-700 font-medium">{{ $venta->almacen->nombre }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Vendedor</dt>
                        <dd class="text-sm text-gray-700">{{ $venta->vendedor->name }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Cliente --}}
            @if($venta->cliente)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6"
                 x-data="{
                    showEdit: false,
                    saving: false,
                    errMsg: '',
                    cliente: {
                        id:               {{ $venta->cliente->id }},
                        tipo_documento:   '{{ $venta->cliente->tipo_documento }}',
                        numero_documento: '{{ $venta->cliente->numero_documento }}',
                        nombre:           '{{ addslashes($venta->cliente->nombre) }}',
                        telefono:         '{{ $venta->cliente->telefono ?? '' }}',
                        direccion:        '{{ addslashes($venta->cliente->direccion ?? '') }}',
                        departamento:     '{{ $venta->cliente->departamento ?? '' }}',
                        provincia:        '{{ addslashes($venta->cliente->provincia ?? '') }}',
                        distrito:         '{{ addslashes($venta->cliente->distrito ?? '') }}',
                    },
                    async guardar() {
                        this.saving = true; this.errMsg = '';
                        try {
                            const res = await fetch('/clientes/' + this.cliente.id, {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({
                                    tipo_documento:   this.cliente.tipo_documento,
                                    numero_documento: this.cliente.numero_documento,
                                    nombre:           this.cliente.nombre,
                                    telefono:         this.cliente.telefono   || null,
                                    direccion:        this.cliente.direccion  || null,
                                    departamento:     this.cliente.departamento || null,
                                    provincia:        this.cliente.provincia  || null,
                                    distrito:         this.cliente.distrito   || null,
                                    estado:           'activo',
                                })
                            });
                            const data = await res.json();
                            if (res.ok) { this.showEdit = false; }
                            else { this.errMsg = data.message || Object.values(data.errors || {}).flat().join('. ') || 'Error al guardar'; }
                        } catch { this.errMsg = 'Error de conexión'; }
                        finally { this.saving = false; }
                    }
                 }">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user text-indigo-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-700 uppercase tracking-wider flex-1">Cliente</h3>
                    <button @click="showEdit = true"
                            class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-indigo-50 transition">
                        <i class="fas fa-pencil-alt"></i> Editar
                    </button>
                </div>

                {{-- Datos del cliente (vista) --}}
                <dl class="space-y-2.5" x-show="!showEdit">
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Nombre</dt>
                        <dd class="text-sm text-gray-700 font-medium text-right max-w-[60%]" x-text="cliente.nombre"></dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Documento</dt>
                        <dd class="text-sm font-mono text-gray-700" x-text="cliente.tipo_documento + ' ' + cliente.numero_documento"></dd>
                    </div>
                    <template x-if="cliente.telefono">
                        <div class="flex justify-between items-center">
                            <dt class="text-sm text-gray-400">Teléfono</dt>
                            <dd class="text-sm text-gray-700" x-text="cliente.telefono"></dd>
                        </div>
                    </template>
                    <template x-if="cliente.direccion">
                        <div class="flex justify-between items-start">
                            <dt class="text-sm text-gray-400 shrink-0">Dirección</dt>
                            <dd class="text-sm text-gray-700 text-right max-w-[65%]" x-text="cliente.direccion"></dd>
                        </div>
                    </template>
                    <template x-if="cliente.departamento || cliente.provincia || cliente.distrito">
                        <div class="flex justify-between items-start">
                            <dt class="text-sm text-gray-400 shrink-0">Ubigeo</dt>
                            <dd class="text-sm text-gray-700 text-right max-w-[65%]">
                                <span x-text="[cliente.distrito, cliente.provincia, cliente.departamento].filter(Boolean).join(', ')"></span>
                            </dd>
                        </div>
                    </template>
                </dl>

                {{-- Formulario de edición inline --}}
                <div x-show="showEdit" x-cloak class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo Doc.</label>
                            <select x-model="cliente.tipo_documento" class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CE">CE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">N° Documento</label>
                            <input type="text" x-model="cliente.numero_documento" maxlength="11"
                                   class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre *</label>
                        <input type="text" x-model="cliente.nombre"
                               class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Teléfono</label>
                        <input type="text" x-model="cliente.telefono" maxlength="20"
                               class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dirección</label>
                        <input type="text" x-model="cliente.direccion"
                               class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500"
                               placeholder="Av. / Jr. / Calle...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Departamento</label>
                        <select x-model="cliente.departamento" class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                            <option value="">— Seleccionar —</option>
                            @foreach(['AMAZONAS','ÁNCASH','APURÍMAC','AREQUIPA','AYACUCHO','CAJAMARCA','CALLAO','CUSCO','HUANCAVELICA','HUÁNUCO','ICA','JUNÍN','LA LIBERTAD','LAMBAYEQUE','LIMA','LORETO','MADRE DE DIOS','MOQUEGUA','PASCO','PIURA','PUNO','SAN MARTÍN','TACNA','TUMBES','UCAYALI'] as $dep)
                                <option value="{{ $dep }}">{{ $dep }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Provincia</label>
                            <input type="text" x-model="cliente.provincia" maxlength="100"
                                   class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Distrito</label>
                            <input type="text" x-model="cliente.distrito" maxlength="100"
                                   class="w-full rounded-lg border-gray-200 text-xs py-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div x-show="errMsg" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="errMsg"></div>
                    <div class="flex gap-2 pt-1">
                        <button @click="showEdit = false; errMsg = ''"
                                class="flex-1 text-xs border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-lg py-2 font-semibold transition">
                            Cancelar
                        </button>
                        <button @click="guardar()" :disabled="saving || !cliente.nombre"
                                class="flex-1 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 font-bold transition disabled:opacity-50 flex items-center justify-center gap-1">
                            <i class="fas" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                            <span x-text="saving ? 'Guardando...' : 'Guardar'"></span>
                        </button>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user text-indigo-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-700 uppercase tracking-wider">Cliente</h3>
                </div>
                <div class="flex flex-col items-center justify-center h-24 text-gray-300">
                    <i class="fas fa-user-slash text-3xl mb-2"></i>
                    <p class="text-sm text-gray-400">Venta sin cliente</p>
                </div>
            </div>
            @endif

            {{-- Pago --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-credit-card text-green-600"></i>
                    </div>
                    <h3 class="font-bold text-gray-700 uppercase tracking-wider">Pago</h3>
                </div>
                <dl class="space-y-3">
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Subtotal</dt>
                        <dd class="text-sm text-gray-700">S/ {{ number_format($venta->subtotal, 2) }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">IGV (18%)</dt>
                        <dd class="text-sm text-gray-700">S/ {{ number_format($venta->igv, 2) }}</dd>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-100 pt-3">
                        <dt class="font-bold text-gray-700">Total</dt>
                        <dd class="text-2xl font-bold text-gray-900">S/ {{ number_format($venta->total, 2) }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Método</dt>
                        <dd class="text-sm text-gray-700 font-medium">
                            {{ $venta->metodo_pago ? ucfirst($venta->metodo_pago) : '—' }}
                        </dd>
                    </div>
                    @if($venta->confirmador)
                    <div class="flex justify-between items-center">
                        <dt class="text-sm text-gray-400">Confirmado por</dt>
                        <dd class="text-sm text-gray-700">{{ $venta->confirmador->name }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Observaciones --}}
        @if($venta->observaciones)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 mb-6 flex items-start gap-3">
            <i class="fas fa-sticky-note text-amber-500 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-amber-700">Observaciones</p>
                <p class="text-sm text-amber-600 mt-0.5">{{ $venta->observaciones }}</p>
            </div>
        </div>
        @endif

        {{-- Guía de Remisión card --}}
        @if($venta->guiaRemision)
        @php $guia = $venta->guiaRemision; @endphp
        <div class="bg-white rounded-2xl border border-teal-100 shadow-sm p-6 mb-7">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-truck text-teal-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-700 uppercase tracking-wider">Guía de Remisión</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <span class="inline-flex items-center gap-1 bg-teal-50 text-teal-700 border border-teal-200 rounded-full px-2 py-0.5 text-xs font-semibold">
                                {{ $guia->motivo_label }}
                            </span>
                            <span class="ml-2">{{ $guia->modalidad_label }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('ventas.guia-pdf', $venta) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 bg-teal-600 hover:bg-teal-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        <i class="fas fa-file-pdf"></i> Ver PDF
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                <div>
                    <dt class="text-xs text-gray-400 mb-1">Fecha de Traslado</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $guia->fecha_traslado?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-1">Peso Bruto</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $guia->peso_total ? number_format($guia->peso_total, 2) . ' kg' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-1">Nro. Bultos</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $guia->bultos ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 mb-1">Modalidad</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $guia->modalidad_label }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs text-gray-400 mb-1"><i class="fas fa-map-marker-alt text-green-500 mr-1"></i>Punto de Partida</dt>
                    <dd class="text-sm text-gray-700">{{ $guia->direccion_partida ?? '—' }}
                        @if($guia->ubigeo_partida)
                            <span class="text-xs text-gray-400 font-mono ml-1">({{ $guia->ubigeo_partida }})</span>
                        @endif
                    </dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs text-gray-400 mb-1"><i class="fas fa-flag-checkered text-red-500 mr-1"></i>Punto de Llegada</dt>
                    <dd class="text-sm text-gray-700">{{ $guia->direccion_llegada ?? '—' }}
                        @if($guia->ubigeo_llegada)
                            <span class="text-xs text-gray-400 font-mono ml-1">({{ $guia->ubigeo_llegada }})</span>
                        @endif
                    </dd>
                </div>
                @if($guia->transportista_nombre)
                <div class="col-span-2 md:col-span-4 pt-3 border-t border-gray-100">
                    <dt class="text-xs text-gray-400 mb-1"><i class="fas fa-id-card mr-1 text-gray-400"></i>Transportista</dt>
                    <dd class="text-sm font-semibold text-gray-700">
                        {{ $guia->transportista_nombre }}
                        @if($guia->transportista_doc)
                            <span class="text-xs text-gray-500 font-normal ml-2">({{ $guia->transportista_tipo_doc }}: {{ $guia->transportista_doc }})</span>
                        @endif
                    </dd>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Products table --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-100">
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-box text-purple-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $venta->tipo_comprobante === 'cotizacion' ? 'Productos cotizados' : 'Productos vendidos' }}</h3>
                    <p class="text-sm text-gray-400">{{ $venta->detalles->count() }} ítem(s)</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr style="background-color: #1e3a5f; color: #fff;">
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">ITEM</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">CÓDIGO</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">DESCRIPCIÓN</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">UNID.</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">CANTIDAD</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">P.UNITARIO</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($venta->detalles as $i => $detalle)
                        @php
                            $imeisDetalle = $venta->imeis->filter(fn($imei) =>
                                $imei->producto_id == $detalle->producto_id &&
                                ($detalle->variante_id ? $imei->variante_id == $detalle->variante_id : true)
                            );
                            if ($imeisDetalle->isEmpty() && $detalle->imei) {
                                $imeisDetalle = collect([$detalle->imei]);
                            }
                            $precioFinal = round((float) ($detalle->precio_con_igv ?? $detalle->precio_unitario * 1.18), 2);
                            $totalFinal  = round((float) ($detalle->subtotal_con_igv ?? $detalle->subtotal * 1.18), 2);
                        @endphp
                        <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50/20 transition-colors">
                            <td class="px-4 py-4 text-center text-sm text-gray-500 font-medium">{{ $i + 1 }}</td>
                            <td class="px-4 py-4 text-sm font-mono text-gray-700">{{ $detalle->producto->codigo ?? '—' }}</td>
                            <td class="px-4 py-4">
                                <span class="font-semibold text-gray-900">{{ $detalle->producto->nombre }}</span>
                                @if($detalle->variante)
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        @if($detalle->variante->color?->codigo_hex)
                                            <span class="w-2.5 h-2.5 rounded-full border border-gray-300 shrink-0"
                                                  style="background-color: {{ $detalle->variante->color->codigo_hex }}"></span>
                                        @endif
                                        <span class="text-xs text-indigo-600 font-medium">{{ $detalle->variante->nombre_completo }}</span>
                                        <span class="text-xs text-gray-400 font-mono">({{ $detalle->variante->sku }})</span>
                                    </div>
                                @elseif($detalle->producto->categoria)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $detalle->producto->categoria->nombre }}</div>
                                @endif
                                @foreach($imeisDetalle as $imei)
                                    <div class="mt-0.5">
                                        <span class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded font-mono">
                                            <i class="fas fa-microchip" style="font-size:9px"></i>
                                            {{ $imei->codigo_imei }}
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="px-4 py-4 text-center text-sm text-gray-600">{{ $detalle->producto->unidadMedida?->abreviatura ?? 'UNID.' }}</td>
                            <td class="px-4 py-4 text-center font-bold text-gray-700">{{ $detalle->cantidad }}</td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700">S/ {{ number_format($precioFinal, 2) }}</td>
                            <td class="px-4 py-4 text-right font-bold text-gray-900">S/ {{ number_format($totalFinal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Son + Totales --}}
            <div class="flex flex-col md:flex-row justify-between items-start gap-4 px-6 py-5 border-t border-gray-200 bg-gray-50">
                <div class="flex-1">
                    <div class="border border-gray-300 rounded-lg p-3 bg-white">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">Son:</div>
                        <div class="text-sm font-bold text-gray-800 uppercase">{{ montoEnLetras($venta->total) }}</div>
                    </div>
                </div>
                <div class="w-full md:w-72 shrink-0">
                    <div class="flex justify-between py-1.5 text-sm border-b border-gray-100">
                        <span class="text-gray-500">Gravada</span>
                        <span class="font-semibold text-gray-800">S/ {{ number_format($venta->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 text-sm border-b border-gray-100">
                        <span class="text-gray-500">IGV (18.00%)</span>
                        <span class="font-semibold text-gray-800">S/ {{ number_format($venta->igv, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 text-sm border-b border-gray-100">
                        <span class="text-gray-500">Descuento Total</span>
                        <span class="font-semibold text-gray-800">S/ 0.00</span>
                    </div>
                    <div class="flex justify-between py-2 mt-1">
                        <span class="font-bold text-gray-900 text-base">Total</span>
                        <span class="font-bold text-blue-600 text-xl">S/ {{ number_format($venta->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resumen de crédito --}}
        @if($venta->es_credito && $venta->cuentaPorCobrar)
        @php $cuenta = $venta->cuentaPorCobrar; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-6 no-print">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-orange-600 text-sm"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Crédito — Plan de Cuotas</h3>
                </div>
                <a href="{{ route('ventas.credito.show', $venta) }}"
                   class="text-sm text-orange-600 hover:text-orange-700 font-medium transition">
                    Ver detalle completo <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="p-6">
                {{-- Barra de progreso --}}
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-500 mb-1.5">
                        <span>Pagado: <span class="font-semibold text-gray-800">S/ {{ number_format($cuenta->monto_pagado, 2) }}</span></span>
                        <span>Total: <span class="font-semibold text-gray-800">S/ {{ number_format($cuenta->monto_total, 2) }}</span></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-orange-500 h-3 rounded-full transition-all"
                             style="width: {{ $cuenta->porcentaje_pagado }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>{{ $cuenta->porcentaje_pagado }}% pagado</span>
                        <span>Saldo: <strong class="text-orange-600">S/ {{ number_format($cuenta->saldo_pendiente, 2) }}</strong></span>
                    </div>
                </div>

                {{-- Lista cuotas --}}
                <div class="space-y-2">
                    @foreach($cuenta->cuotas->take(5) as $cuota)
                    @php
                        $hoy = now()->toDateString();
                        $vencida = $cuota->estado === 'pendiente' && $cuota->fecha_vencimiento->lt(now());
                    @endphp
                    <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-400 w-8">{{ $cuota->numero_cuota }}/{{ $cuota->total_cuotas }}</span>
                            <span class="text-sm text-gray-600">{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                            @if($vencida)
                            <span class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-semibold">Vencida</span>
                            @elseif($cuota->estado === 'pagado')
                            <span class="text-[10px] bg-green-100 text-green-600 px-1.5 py-0.5 rounded-full font-semibold">Pagada</span>
                            @endif
                        </div>
                        <span class="text-sm font-mono font-semibold {{ $cuota->estado === 'pagado' ? 'text-green-600 line-through' : ($vencida ? 'text-red-600' : 'text-gray-700') }}">
                            S/ {{ number_format($cuota->monto, 2) }}
                        </span>
                    </div>
                    @endforeach
                    @if($cuenta->cuotas->count() > 5)
                    <p class="text-xs text-gray-400 text-center pt-1">... y {{ $cuenta->cuotas->count() - 5 }} cuotas más</p>
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>
</body>
</html>
