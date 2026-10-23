<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cola de Caja</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8"
         x-data="colaApp()"
         x-init="iniciarPolling()">

        <div class="flex items-center justify-between mb-1">
            <a href="{{ route('cajero.dashboard') }}" class="text-sm text-gray-500 hover:text-blue-700">
                <i class="fas fa-arrow-left mr-1"></i> Mi Panel
            </a>
        </div>
        <x-header title="Cola de Caja" subtitle="Ventas pendientes de cobro — se actualiza automáticamente cada 30 s" />

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
            </div>
        @endif

        {{-- Counter badge --}}
        <div class="flex items-center gap-3 mb-6">
            <span class="inline-flex items-center gap-2 bg-amber-100 text-amber-800 text-sm font-semibold px-4 py-2 rounded-full">
                <i class="fas fa-clock"></i>
                <span x-text="ventas.length"></span> venta(s) en cola
            </span>
            <span class="text-xs text-gray-400">
                <i class="fas fa-sync-alt mr-1"></i>Última actualización: <span x-text="ultimaActualizacion"></span>
            </span>
        </div>

        {{-- Grid de ventas --}}
        <div x-show="ventas.length === 0" x-cloak
             class="bg-white rounded-2xl shadow-sm p-12 text-center text-gray-400">
            <i class="fas fa-check-circle text-4xl text-green-400 block mb-3"></i>
            <p class="font-semibold text-gray-600">Cola vacía</p>
            <p class="text-sm mt-1">No hay ventas pendientes de cobro en este momento.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="v in ventas" :key="v.id">
                <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition">
                    {{-- Header card --}}
                    <div class="bg-gradient-to-r from-amber-500 to-amber-400 px-4 py-3 flex items-center justify-between">
                        <span class="font-mono text-white font-bold text-sm" x-text="v.codigo"></span>
                        <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full" x-text="v.hora_creacion"></span>
                    </div>
                    <div class="p-4 space-y-3">
                        {{-- Cliente --}}
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0"
                                 x-text="(v.cliente ?? '?').charAt(0).toUpperCase()"></div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm" x-text="v.cliente || 'Sin cliente'"></p>
                                <p class="text-xs text-gray-400" x-text="'Vendedor: ' + v.vendedor"></p>
                            </div>
                        </div>

                        {{-- Productos --}}
                        <div class="text-xs text-gray-600 bg-gray-50 rounded-lg p-2 max-h-24 overflow-y-auto">
                            <template x-for="d in v.detalles" :key="d.id">
                                <div class="flex justify-between py-0.5">
                                    <span x-text="d.cantidad + 'x ' + d.producto"></span>
                                    <span class="font-mono font-semibold" x-text="'S/ ' + d.subtotal"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Total --}}
                        <div class="flex items-center justify-between pt-1 border-t border-gray-100">
                            <span class="text-sm text-gray-600 font-medium">Total</span>
                            <span class="text-xl font-bold text-gray-800" x-text="'S/ ' + v.total.toFixed(2)"></span>
                        </div>

                        {{-- Acciones --}}
                        <div class="flex gap-2">
                            <button type="button"
                                    @click="abrirModalCobro(v)"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 rounded-xl transition flex items-center justify-center gap-2">
                                <i class="fas fa-money-bill-wave"></i> Cobrar
                            </button>
                            <a :href="'/ventas/' + v.id + '/pdf'" target="_blank"
                               class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition" title="Ver comprobante">
                                <i class="fas fa-file-pdf text-red-500"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ══ MODAL DE COBRO COMPLETO ══ --}}
        <div x-show="modalCobro" x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-2 sm:p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="!procesandoCobro && cerrarModal()"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 flex flex-col max-h-[95vh]">

                {{-- Header --}}
                <div class="bg-gradient-to-r from-green-700 to-green-600 px-5 py-4 rounded-t-2xl flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-cash-register"></i>
                            <span x-show="!cobroExitoso">Cobrar venta</span>
                            <span x-show="cobroExitoso" x-cloak>¡Pago registrado!</span>
                        </h3>
                        <p class="text-green-200 text-xs mt-0.5 font-mono" x-text="ventaActual?.codigo"></p>
                    </div>
                    <button @click="cerrarModal()" :disabled="procesandoCobro"
                            class="text-white/70 hover:text-white disabled:opacity-40 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- ── ESTADO: ÉXITO ── --}}
                <div x-show="cobroExitoso" x-cloak class="p-6 text-center space-y-4">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-800">Pago confirmado</p>
                        <p class="text-sm text-gray-500 mt-1" x-text="'Venta ' + ventaActual?.codigo + ' cobrada exitosamente'"></p>
                    </div>
                    <div class="flex gap-3">
                        <a :href="printUrl" target="_blank"
                           class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-print"></i> Imprimir
                        </a>
                        <button @click="cerrarModal()"
                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-semibold">
                            Cerrar
                        </button>
                    </div>
                </div>

                {{-- ── ESTADO: FORMULARIO ── --}}
                <div x-show="!cobroExitoso" class="flex-1 overflow-y-auto">
                    <div class="p-5 space-y-4">

                        {{-- Resumen de la venta --}}
                        <div class="bg-gray-50 rounded-2xl p-4 space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Cliente</p>
                                    <p class="text-sm font-bold text-gray-800" x-text="ventaActual?.cliente || 'Consumidor final'"></p>
                                    <p class="text-xs text-gray-400" x-text="'Vendedor: ' + (ventaActual?.vendedor ?? '—')"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total a cobrar</p>
                                    <p class="text-2xl font-bold text-green-700" x-text="'S/ ' + ventaActual?.total"></p>
                                </div>
                            </div>
                            {{-- Ítems --}}
                            <div class="border-t border-gray-200 pt-2 space-y-0.5 max-h-28 overflow-y-auto">
                                <template x-for="d in ventaActual?.detalles ?? []" :key="d.id">
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span x-text="d.cantidad + '× ' + d.producto" class="truncate mr-2"></span>
                                        <span class="font-mono font-semibold whitespace-nowrap" x-text="'S/ ' + parseFloat(d.subtotal).toFixed(2)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Formato impresión --}}
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Formato de impresión</p>
                            <div class="flex gap-2">
                                <button @click="setFormato('ticket')"
                                        :class="formato === 'ticket' ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                        class="flex-1 py-2 border-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                                    <i class="fas fa-receipt"></i> Ticket 80mm
                                </button>
                                <button @click="setFormato('a4')"
                                        :class="formato === 'a4' ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                        class="flex-1 py-2 border-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                                    <i class="fas fa-file-alt"></i> A4
                                </button>
                            </div>
                        </div>

                        {{-- Método de pago --}}
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Método de pago</p>

                            {{-- Filas de pago (una por cada pago agregado) --}}
                            <div class="space-y-3">
                                <template x-for="(p, i) in pagos" :key="i">
                                    <div class="rounded-2xl border border-gray-200 p-3 space-y-2 bg-gray-50/50">

                                        {{-- Header fila: etiqueta + monto + quitar --}}
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide shrink-0"
                                                  x-text="pagos.length > 1 ? 'Pago ' + (i+1) : 'Método'"></span>
                                            <div class="flex-1"></div>
                                            <input type="number" x-model.number="p.monto" step="0.50" min="0"
                                                   :placeholder="pagos.length === 1 ? totalVenta.toFixed(2) : '0.00'"
                                                   class="w-28 bg-white border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono font-bold text-gray-800 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <button x-show="pagos.length > 1" @click="pagos.splice(i,1)" x-cloak
                                                    class="w-7 h-7 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition shrink-0">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>

                                        {{-- Botones de método --}}
                                        <div class="grid grid-cols-4 gap-1.5">
                                            <template x-for="m in metodos" :key="m.k">
                                                <button @click="p.metodo = m.k; p.referencia = ''"
                                                        :class="p.metodo === m.k
                                                            ? 'border-green-600 bg-green-600 text-white shadow-sm'
                                                            : 'border-gray-200 bg-white text-gray-500 hover:border-green-300 hover:text-green-700'"
                                                        class="flex flex-col items-center gap-1 py-2 rounded-xl border-2 text-[10px] font-bold transition">
                                                    <i class="fas text-sm" :class="p.metodo === m.k ? m.icon + ' text-white' : m.icon + ' text-' + m.color + '-500'"></i>
                                                    <span x-text="m.label"></span>
                                                </button>
                                            </template>
                                        </div>

                                        {{-- Monto rápido (efectivo, 1 pago) --}}
                                        <div x-show="p.metodo === 'efectivo' && pagos.length === 1" x-cloak>
                                            <div class="flex flex-wrap gap-1">
                                                <button @click="p.monto = parseFloat(totalVenta.toFixed(2))"
                                                        class="px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 rounded-lg text-[10px] font-bold hover:bg-green-100 transition">
                                                    Exacto
                                                </button>
                                                <template x-for="amt in [10,20,50,100,200,500]" :key="amt">
                                                    <button x-show="amt >= Math.floor(totalVenta)"
                                                            @click="p.monto = amt"
                                                            class="px-2 py-0.5 bg-gray-100 text-gray-600 border border-gray-200 rounded-lg text-[10px] font-bold hover:bg-gray-200 transition"
                                                            x-text="'S/ ' + amt"></button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- N° operación (métodos digitales) --}}
                                        <div x-show="p.metodo !== 'efectivo'" x-cloak>
                                            <input type="text" x-model="p.referencia"
                                                   :placeholder="p.metodo === 'transferencia' ? 'N° operación bancaria' : 'Código de operación ' + p.metodo"
                                                   maxlength="100"
                                                   class="w-full bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 placeholder-amber-400 focus:ring-2 focus:ring-amber-400">
                                        </div>

                                    </div>
                                </template>
                            </div>

                            {{-- Agregar pago --}}
                            <button x-show="pagos.length < 3" @click="agregarPago()" x-cloak
                                    class="mt-2 w-full py-2 border-2 border-dashed border-gray-300 rounded-xl text-xs font-semibold text-gray-500 hover:border-green-400 hover:text-green-600 hover:bg-green-50 transition flex items-center justify-center gap-1.5">
                                <i class="fas fa-plus text-[9px]"></i> Agregar otro método de pago
                            </button>

                            {{-- Totales / Vuelto / Falta --}}
                            <div class="mt-3 pt-3 border-t border-gray-200 space-y-1.5">
                                <template x-for="(p, i) in pagos" x-show="pagos.length > 1" :key="'tot'+i">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span x-text="'Pago ' + (i+1) + ' (' + p.metodo + ')'"></span>
                                        <span class="font-mono" x-text="'S/ ' + (parseFloat(p.monto)||0).toFixed(2)"></span>
                                    </div>
                                </template>
                                <div class="flex justify-between text-sm font-bold text-gray-800">
                                    <span>Total a cobrar</span>
                                    <span class="font-mono" x-text="'S/ ' + totalVenta.toFixed(2)"></span>
                                </div>
                                <div x-show="vuelto > 0" x-cloak class="flex justify-between text-base font-bold text-green-700">
                                    <span><i class="fas fa-arrow-left text-xs mr-1"></i>Vuelto</span>
                                    <span class="font-mono" x-text="'S/ ' + vuelto.toFixed(2)"></span>
                                </div>
                                <div x-show="falta > 0 && totalPagado > 0" x-cloak class="flex justify-between text-sm font-bold text-red-600">
                                    <span>Falta</span>
                                    <span class="font-mono" x-text="'S/ ' + falta.toFixed(2)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Error --}}
                        <div x-show="errorCobro" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 flex items-start gap-2">
                            <i class="fas fa-exclamation-circle mt-0.5 shrink-0"></i>
                            <span x-text="errorCobro"></span>
                        </div>

                    </div>
                </div>

                {{-- Footer sticky --}}
                <div x-show="!cobroExitoso" class="px-5 pb-5 pt-3 border-t border-gray-100 shrink-0 flex gap-3">
                    <button type="button" @click="cerrarModal()" :disabled="procesandoCobro"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 font-semibold transition">
                        Cancelar
                    </button>
                    <button type="button" @click="procesarCobro()"
                            :disabled="!puedeCobrar || procesandoCobro"
                            class="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed text-white py-3 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2">
                        <span x-show="!procesandoCobro"><i class="fas fa-check mr-1"></i> Confirmar cobro</span>
                        <span x-show="procesandoCobro" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Procesando...</span>
                    </button>
                </div>

            </div>
        </div>

    </div>

@php
$ventasJson = $ventas->map(fn($v) => [
    'id'            => $v->id,
    'codigo'        => $v->codigo,
    'total'         => round((float) $v->total, 2),
    'cliente'       => $v->cliente ? $v->cliente->nombre . ' ' . ($v->cliente->apellido ?? '') : null,
    'vendedor'      => $v->vendedor?->name ?? '—',
    'hora_creacion' => $v->created_at->format('H:i'),
    'detalles'      => $v->detalles->map(fn($d) => [
        'id'       => $d->id,
        'cantidad' => $d->cantidad,
        'producto' => trim(($d->producto?->nombre ?? '—') . ($d->variante ? ' · ' . $d->variante->nombre_completo : '')),
        'subtotal' => round((float) $d->subtotal_con_igv, 2),
    ])->values()->all(),
])->values()->all();
@endphp
<script>
function colaApp() {
    const ventasData = @json($ventasJson);
    const csrfToken  = document.querySelector('meta[name="csrf-token"]').content;

    return {
        ventas:              ventasData,
        modalCobro:          false,
        ventaActual:         null,
        pagos:               [{ metodo: 'efectivo', monto: 0, referencia: '' }],
        formato:             localStorage.getItem('cajero_formato') ?? 'ticket',
        procesandoCobro:     false,
        cobroExitoso:        false,
        printUrl:            '',
        errorCobro:          '',
        metodos: [
            { k: 'efectivo',      icon: 'fa-money-bill-wave', color: 'green',  label: 'Efectivo' },
            { k: 'yape',          icon: 'fa-mobile-alt',       color: 'purple', label: 'Yape'     },
            { k: 'plin',          icon: 'fa-mobile-alt',       color: 'teal',   label: 'Plin'     },
            { k: 'transferencia', icon: 'fa-university',        color: 'blue',   label: 'Transf.'  },
        ],
        ultimaActualizacion: new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'}),
        pollingInterval:     null,

        get totalVenta() {
            return parseFloat((this.ventaActual?.total ?? '0').toString().replace(',', ''));
        },
        get totalPagado() {
            return this.pagos.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0);
        },
        get vuelto() {
            return Math.max(0, this.totalPagado - this.totalVenta);
        },
        get falta() {
            return Math.max(0, this.totalVenta - this.totalPagado);
        },
        get puedeCobrar() {
            if (this.procesandoCobro) return false;
            // Efectivo con monto 0 = monto exacto
            if (this.pagos.length === 1 && this.pagos[0].metodo === 'efectivo' && parseFloat(this.pagos[0].monto) === 0) return true;
            return Math.round(this.totalPagado * 100) >= Math.round(this.totalVenta * 100);
        },

        iniciarPolling() {
            this.pollingInterval = setInterval(() => this.recargar(), 30000);
        },

        async recargar() {
            try {
                const resp = await fetch('{{ route('cajero.cola.json') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (resp.ok) {
                    this.ventas = await resp.json();
                    this.ultimaActualizacion = new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
                }
            } catch {}
        },

        abrirModalCobro(venta) {
            this.ventaActual    = venta;
            this.pagos          = [{ metodo: 'efectivo', monto: 0, referencia: '' }];
            this.procesandoCobro = false;
            this.cobroExitoso   = false;
            this.errorCobro     = '';
            this.printUrl       = '';
            this.modalCobro     = true;
        },

        cerrarModal() {
            if (this.cobroExitoso) {
                // Reload to show updated queue
                window.location.reload();
                return;
            }
            this.modalCobro = false;
        },

        setFormato(f) {
            this.formato = f;
            localStorage.setItem('cajero_formato', f);
        },

        agregarPago() {
            if (this.pagos.length < 3) this.pagos.push({ metodo: 'efectivo', monto: 0, referencia: '' });
        },

        async procesarCobro() {
            if (!this.puedeCobrar) return;
            this.errorCobro      = '';
            this.procesandoCobro = true;

            const esMixto    = this.pagos.length > 1;
            const metodoPago = esMixto ? 'mixto' : this.pagos[0].metodo;

            const pagosDetalle = esMixto
                ? this.pagos.map(p => ({
                    metodo:     p.metodo,
                    monto:      parseFloat(p.monto) || 0,
                    referencia: p.referencia?.trim() || null,
                }))
                : (this.pagos[0].referencia?.trim()
                    ? [{ metodo: this.pagos[0].metodo, monto: parseFloat(this.pagos[0].monto) || this.totalVenta, referencia: this.pagos[0].referencia.trim() }]
                    : null);

            try {
                const res = await fetch(`/ventas/${this.ventaActual.id}/confirmar-pago`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        metodo_pago:       metodoPago,
                        pagos_detalle:     pagosDetalle,
                        formato_impresion: this.formato,
                    }),
                });

                const data = await res.json();
                if (res.ok) {
                    this.cobroExitoso = true;
                    this.printUrl     = data.print_url;
                    this.ventas       = this.ventas.filter(v => v.id !== this.ventaActual.id);
                } else {
                    this.errorCobro = data.error || data.message || 'Error al procesar el cobro';
                }
            } catch {
                this.errorCobro = 'Error de conexión. Intenta de nuevo.';
            }

            this.procesandoCobro = false;
        },
    };
}
</script>
</body>
</html>
