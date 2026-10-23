<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $cotizacion ? 'Editar cotización ' . $cotizacion->codigo : 'Nueva cotización' }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$cotizacion ? 'Editar cotización ' . $cotizacion->codigo : 'Nueva cotización'"
                  subtitle="Elija el cliente, agregue los productos y revise los precios antes de guardar" />

        @include('cotizaciones.partials.mensajes')

        <form method="POST" id="formCotizacion"
              action="{{ $cotizacion ? route('cotizaciones.update', $cotizacion) : route('cotizaciones.store') }}"
              x-data="cotizacionForm()" x-init="init()" @submit="enviando = true">
            @csrf
            @if($cotizacion) @method('PUT') @endif

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                {{-- ───────────── Columna principal ───────────── --}}
                <div class="xl:col-span-2 space-y-6">

                    {{-- Cliente --}}
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-user mr-2 text-blue-700"></i>Cliente</h3>
                        <input type="hidden" name="cliente_id" :value="cliente ? cliente.id : ''">

                        <template x-if="cliente">
                            <div class="flex items-center justify-between gap-3 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate" x-text="cliente.nombre"></p>
                                    <p class="text-xs text-gray-500"><span x-text="cliente.tipo_documento"></span> <span x-text="cliente.numero_documento"></span></p>
                                </div>
                                <button type="button" @click="cliente = null; $nextTick(() => $refs.buscarCliente.focus())" class="text-sm text-blue-700 hover:underline shrink-0">Cambiar</button>
                            </div>
                        </template>

                        <div x-show="!cliente" x-cloak class="relative">
                            <div class="flex gap-2">
                                <input type="text" x-ref="buscarCliente" x-model="qCliente" @input.debounce.250ms="buscarClientes()"
                                       placeholder="Buscar por nombre, DNI o RUC…" autocomplete="off"
                                       class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <button type="button" @click="abrirNuevoCliente()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold whitespace-nowrap">
                                    <i class="fas fa-user-plus mr-1"></i>Nuevo
                                </button>
                            </div>
                            <div x-show="resultadosCliente.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="c in resultadosCliente" :key="c.id">
                                    <button type="button" @click="elegirCliente(c)" class="w-full text-left px-4 py-2.5 hover:bg-blue-50 border-b border-gray-50 last:border-0">
                                        <p class="text-sm font-medium text-gray-900" x-text="c.nombre"></p>
                                        <p class="text-xs text-gray-500"><span x-text="c.tipo_documento"></span> <span x-text="c.numero_documento"></span></p>
                                    </button>
                                </template>
                            </div>
                            <p x-show="qCliente.length >= 2 && !resultadosCliente.length && !buscandoCliente" x-cloak class="text-xs text-gray-500 mt-2">
                                Sin resultados. Use «Nuevo» para registrar al cliente.
                            </p>
                        </div>
                    </div>

                    {{-- Productos --}}
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <h3 class="font-semibold text-gray-900"><i class="fas fa-lightbulb mr-2 text-amber-500"></i>Productos</h3>
                            <div class="flex items-center gap-2">
                                <label for="almacen_id" class="text-xs text-gray-500">Stock en</label>
                                <select name="almacen_id" id="almacen_id" x-model="almacenId" @change="refrescarStock()" required
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="">Seleccione…</option>
                                    @foreach($almacenes as $a)
                                        <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="relative mb-4">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" x-model="q" @input.debounce.250ms="buscar()" @keydown.escape="resultados = []"
                                   placeholder="Buscar por nombre, código Kyrios, del fabricante o del Excel…" autocomplete="off"
                                   class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <div x-show="resultados.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-80 overflow-y-auto">
                                <template x-for="p in resultados" :key="p.producto_id + '-' + (p.variante_id || 0)">
                                    <button type="button" @click="agregar(p)" class="w-full flex items-center gap-3 text-left px-3 py-2.5 hover:bg-blue-50 border-b border-gray-50 last:border-0">
                                        <img x-show="p.imagen" :src="p.imagen" alt="" class="w-11 h-11 rounded-lg object-cover bg-gray-100 shrink-0">
                                        <div x-show="!p.imagen" class="w-11 h-11 rounded-lg bg-gray-100 flex items-center justify-center text-gray-300 shrink-0"><i class="fas fa-lightbulb"></i></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="p.nombre"></p>
                                            <p class="text-xs text-gray-500 truncate">
                                                <span class="font-mono" x-text="p.codigo"></span>
                                                <span x-show="p.detalle" x-text="' · ' + p.detalle"></span>
                                                <span x-show="p.marca" x-text="' · ' + p.marca"></span>
                                            </p>
                                            <p x-show="p.dano" class="text-[11px] text-amber-700"><i class="fas fa-exclamation-triangle"></i> Daño <span x-text="p.dano"></span></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-semibold" :class="p.precio ? 'text-gray-900' : 'text-gray-400'" x-text="p.precio ? 'S/ ' + Number(p.precio).toFixed(2) : 'sin precio'"></p>
                                            <p class="text-xs" :class="p.stock > 0 ? 'text-emerald-600' : 'text-red-500'" x-text="p.stock + ' en stock'"></p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="lineas.length === 0" class="text-center py-10 text-gray-400 border-2 border-dashed border-gray-200 rounded-lg">
                            <i class="fas fa-box-open text-3xl mb-2"></i>
                            <p class="text-sm">Busque un producto para agregarlo a la cotización</p>
                        </div>

                        <div x-show="lineas.length > 0" x-cloak class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase border-b">
                                        <th class="py-2 pr-3 text-left">Producto</th>
                                        <th class="py-2 px-2 text-center w-24">Cant.</th>
                                        <th class="py-2 px-2 text-right w-32">Precio unit. (con IGV)</th>
                                        <th class="py-2 px-2 text-right w-28">Total</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(l, i) in lineas" :key="i">
                                        <tr class="border-b border-gray-100 align-top">
                                            <td class="py-3 pr-3">
                                                <p class="font-medium text-gray-900" x-text="l.descripcion"></p>
                                                <p class="text-xs font-mono text-gray-400" x-text="l.codigo"></p>
                                                <p x-show="l.stock !== null && l.stock !== undefined && Number(l.cantidad) > l.stock" class="text-xs text-red-600 mt-0.5">
                                                    <i class="fas fa-exclamation-triangle"></i> Solo hay <span x-text="l.stock"></span> en stock (se puede cotizar igual)
                                                </p>
                                                <input type="hidden" :name="`detalles[${i}][producto_id]`" :value="l.producto_id">
                                                <input type="hidden" :name="`detalles[${i}][variante_id]`" :value="l.variante_id || ''">
                                                <input type="hidden" :name="`detalles[${i}][incluye_igv]`" value="1">
                                            </td>
                                            <td class="py-3 px-2">
                                                <input type="number" min="1" :name="`detalles[${i}][cantidad]`" x-model.number="l.cantidad" required
                                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-center">
                                            </td>
                                            <td class="py-3 px-2">
                                                <input type="number" min="0.01" step="0.01" :name="`detalles[${i}][precio_unitario]`" x-model.number="l.precio" required
                                                       :class="!(l.precio > 0) ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                                       class="w-full px-2 py-1.5 border rounded-lg text-right">
                                            </td>
                                            <td class="py-3 px-2 text-right font-semibold text-gray-900" x-text="'S/ ' + (Number(l.cantidad) * Number(l.precio || 0)).toFixed(2)"></td>
                                            <td class="py-3 text-right">
                                                <button type="button" @click="lineas.splice(i, 1)" class="text-red-400 hover:text-red-600" title="Quitar"><i class="fas fa-times"></i></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p x-show="lineas.some(l => !(l.precio > 0))" class="text-xs text-red-600 mt-2">
                                <i class="fas fa-exclamation-circle"></i> Hay productos sin precio: escríbalo en la casilla roja (los productos importados todavía no tienen precio de venta cargado).
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ───────────── Columna lateral ───────────── --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-calculator mr-2 text-emerald-600"></i>Resumen</h3>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-gray-500">Op. gravada</dt><dd class="font-medium" x-text="'S/ ' + subtotal.toFixed(2)"></dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">IGV (18%)</dt><dd class="font-medium" x-text="'S/ ' + igv.toFixed(2)"></dd></div>
                            <div class="flex justify-between border-t pt-2 text-base"><dt class="font-semibold">Total</dt><dd class="font-bold text-gray-900" x-text="'S/ ' + total.toFixed(2)"></dd></div>
                        </dl>
                        <p class="text-xs text-gray-400 mt-2">Los precios incluyen IGV. El stock no se descuenta hasta convertirla en venta.</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-5 space-y-4">
                        <div>
                            <label for="validez_dias" class="block text-sm font-medium text-gray-700 mb-1">Validez (días)</label>
                            <input type="number" min="1" max="365" id="validez_dias" name="validez_dias" value="{{ $validez }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-400 mt-1">Después de este plazo queda vencida (se puede renovar).</p>
                        </div>
                        <div>
                            <label for="condiciones" class="block text-sm font-medium text-gray-700 mb-1">Condiciones comerciales <span class="text-gray-400 font-normal">(se imprimen)</span></label>
                            <textarea id="condiciones" name="condiciones" rows="5" maxlength="3000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">{{ $condiciones }}</textarea>
                        </div>
                        <div>
                            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-1">Notas internas <span class="text-gray-400 font-normal">(no se imprimen)</span></label>
                            <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">{{ old('observaciones', $cotizacion?->observaciones) }}</textarea>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <a href="{{ $cotizacion ? route('cotizaciones.show', $cotizacion) : route('cotizaciones.index') }}"
                           class="flex-1 text-center border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-3 rounded-xl">Cancelar</a>
                        <button type="submit" :disabled="!puedeGuardar || enviando"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-xl">
                            <i class="fas fa-save mr-1"></i><span x-text="enviando ? 'Guardando…' : 'Guardar'"></span>
                        </button>
                    </div>
                    <p x-show="!puedeGuardar" x-cloak class="text-xs text-gray-500 text-center -mt-3" x-text="motivoNoGuardar"></p>
                </div>
            </div>

            {{-- Cliente nuevo (modal) --}}
            <div x-show="modalCliente" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="modalCliente = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Nuevo cliente</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <select x-model="nuevo.tipo_documento" class="px-2 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CE">CE</option>
                            </select>
                            <input type="text" x-model="nuevo.numero_documento" maxlength="11" placeholder="Documento" class="col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <input type="text" x-model="nuevo.nombre" placeholder="Nombre o razón social" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <input type="text" x-model="nuevo.direccion" placeholder="Dirección (opcional)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" x-model="nuevo.telefono" placeholder="Teléfono" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <input type="email" x-model="nuevo.email" placeholder="Correo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <p x-show="errorNuevo" x-cloak class="text-sm text-red-600" x-text="errorNuevo"></p>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button type="button" @click="modalCliente = false" class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-100 py-2.5 rounded-xl font-semibold">Cancelar</button>
                        <button type="button" @click="guardarNuevoCliente()" :disabled="guardandoCliente" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-semibold disabled:opacity-50">
                            <span x-text="guardandoCliente ? 'Guardando…' : 'Guardar cliente'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

@php
    $clienteJs = $cliente ? $cliente->only(['id', 'nombre', 'tipo_documento', 'numero_documento']) : null;
    $lineasJs = $lineas->map(fn ($l) => [
        'producto_id' => (int) $l['producto_id'],
        'variante_id' => !empty($l['variante_id']) ? (int) $l['variante_id'] : null,
        'codigo' => $l['codigo'] ?? '',
        'descripcion' => $l['descripcion'] ?? '',
        'cantidad' => (int) $l['cantidad'],
        'precio' => (float) ($l['precio_unitario'] ?? 0),
        'stock' => null,
    ])->values();
@endphp
<script>
function cotizacionForm() {
    const IGV = 1.18;
    const URL_PRODUCTOS = @json(route('cotizaciones.productos'));
    const URL_CLIENTES  = @json(route('cotizaciones.clientes'));
    const URL_NUEVO_CLIENTE = @json(route('clientes.store'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    return {
        almacenId: @json((string) ($almacenId ?? '')),
        cliente: @json($clienteJs),
        lineas: @json($lineasJs),
        q: '', resultados: [], qCliente: '', resultadosCliente: [], buscandoCliente: false,
        modalCliente: false, guardandoCliente: false, errorNuevo: '', enviando: false,
        nuevo: { tipo_documento: 'DNI', numero_documento: '', nombre: '', direccion: '', telefono: '', email: '' },

        init() { if (this.almacenId && this.lineas.length) this.refrescarStock(); },

        get total() { return this.lineas.reduce((s, l) => s + Number(l.cantidad || 0) * Number(l.precio || 0), 0); },
        get subtotal() { return this.total / IGV; },
        get igv() { return this.total - this.subtotal; },
        get motivoNoGuardar() {
            if (!this.cliente) return 'Elija un cliente.';
            if (!this.almacenId) return 'Elija el almacén.';
            if (!this.lineas.length) return 'Agregue al menos un producto.';
            if (this.lineas.some(l => !(l.precio > 0) || !(l.cantidad > 0))) return 'Complete cantidad y precio de todos los productos.';
            return '';
        },
        get puedeGuardar() { return this.motivoNoGuardar === ''; },

        async pedir(url) {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return r.ok ? r.json() : [];
        },
        async buscar() {
            if (this.q.trim().length < 2) { this.resultados = []; return; }
            this.resultados = await this.pedir(`${URL_PRODUCTOS}?q=${encodeURIComponent(this.q.trim())}&almacen_id=${this.almacenId || ''}`);
        },
        agregar(p) {
            const existente = this.lineas.find(l => l.producto_id === p.producto_id && (l.variante_id || null) === (p.variante_id || null));
            if (existente) { existente.cantidad = Number(existente.cantidad) + 1; existente.stock = p.stock; }
            else this.lineas.push({
                producto_id: p.producto_id, variante_id: p.variante_id || null, codigo: p.codigo,
                descripcion: p.nombre + (p.detalle ? ' — ' + p.detalle : ''), cantidad: 1, precio: p.precio || '', stock: p.stock,
            });
            this.q = ''; this.resultados = [];
        },
        async refrescarStock() {
            for (const l of this.lineas) {
                const r = await this.pedir(`${URL_PRODUCTOS}?q=${encodeURIComponent(l.codigo)}&almacen_id=${this.almacenId || ''}`);
                const m = r.find(x => x.producto_id === l.producto_id && (x.variante_id || null) === (l.variante_id || null));
                l.stock = m ? m.stock : null;
            }
        },

        async buscarClientes() {
            if (this.qCliente.trim().length < 2) { this.resultadosCliente = []; return; }
            this.buscandoCliente = true;
            this.resultadosCliente = await this.pedir(`${URL_CLIENTES}?q=${encodeURIComponent(this.qCliente.trim())}`);
            this.buscandoCliente = false;
        },
        elegirCliente(c) { this.cliente = c; this.resultadosCliente = []; this.qCliente = ''; },
        abrirNuevoCliente() {
            this.nuevo = { tipo_documento: 'DNI', numero_documento: /^\d+$/.test(this.qCliente) ? this.qCliente : '', nombre: /^\d+$/.test(this.qCliente) ? '' : this.qCliente, direccion: '', telefono: '', email: '' };
            this.errorNuevo = ''; this.modalCliente = true;
        },
        async guardarNuevoCliente() {
            this.guardandoCliente = true; this.errorNuevo = '';
            try {
                const r = await fetch(URL_NUEVO_CLIENTE, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ ...this.nuevo, estado: 'activo' }),
                });
                const d = await r.json();
                if (!r.ok) { this.errorNuevo = d.errors ? Object.values(d.errors)[0][0] : (d.message || 'No se pudo guardar el cliente.'); return; }
                this.elegirCliente({ id: d.id, nombre: d.nombre, tipo_documento: this.nuevo.tipo_documento, numero_documento: this.nuevo.numero_documento });
                this.modalCliente = false;
            } catch (e) { this.errorNuevo = 'Sin conexión con el servidor.'; }
            finally { this.guardandoCliente = false; }
        },
    };
}
</script>
</body>
</html>
