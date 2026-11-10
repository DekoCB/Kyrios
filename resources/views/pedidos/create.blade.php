<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva orden de compra - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Nueva orden de compra"
            subtitle="Orden de compra a proveedor nacional o del exterior"
        />

        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <p class="font-medium"><i class="fas fa-exclamation-circle mr-2"></i>Se encontraron errores:</p>
                <ul class="mt-2 list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-w-5xl mx-auto" x-data="pedidoForm()">
            <form @submit.prevent="submitForm">
                {{-- Datos del Pedido --}}
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-900 px-6 py-4">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-info-circle mr-2"></i>Datos de la orden
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Proveedor (búsqueda en vivo) --}}
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Proveedor <span class="text-red-500">*</span></label>

                                <div x-show="!proveedorSeleccionado" class="relative">
                                    <input type="text" x-model="proveedorBusqueda" @input="buscarProveedor()"
                                           placeholder="RUC, razón social o nombre (mín. 2 car.)..."
                                           autocomplete="off"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                                    <div x-show="proveedorResultados.length > 0"
                                         class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 max-h-64 overflow-y-auto">
                                        <template x-for="p in proveedorResultados" :key="p.id">
                                            <div @click="seleccionarProveedor(p)"
                                                 class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0">
                                                <div class="font-medium text-gray-900 text-sm" x-text="p.nombre_comercial || p.razon_social"></div>
                                                <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-3">
                                                    <span x-show="p.nombre_comercial && p.nombre_comercial !== p.razon_social" x-text="p.razon_social"></span>
                                                    <span class="font-mono text-blue-600">RUC: <span x-text="p.ruc || '—'"></span></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="proveedorSeleccionado"
                                     class="p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <i class="fas fa-building text-blue-700 text-sm shrink-0"></i>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-blue-900 truncate" x-text="proveedorSeleccionado?.nombre_comercial || proveedorSeleccionado?.razon_social"></p>
                                            <p class="text-xs text-blue-600">RUC: <span x-text="proveedorSeleccionado?.ruc"></span></p>
                                        </div>
                                    </div>
                                    <button type="button" @click="quitarProveedor()"
                                            class="shrink-0 ml-2 text-xs text-blue-600 hover:text-red-600 border border-blue-300 hover:border-red-400 rounded-lg px-2 py-1">
                                        <i class="fas fa-times"></i> Cambiar
                                    </button>
                                </div>
                            </div>

                            {{-- Almacén de destino --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Almacén de destino <span class="text-red-500">*</span></label>
                                <select x-model="almacen_id" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                    <option value="">Seleccione almacén</option>
                                    @if($almacenesCentral->isNotEmpty())
                                        <optgroup label="── Almacenes Centrales">
                                            @foreach($almacenesCentral as $alm)
                                                <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($almacenesTienda->isNotEmpty())
                                        <optgroup label="── Almacenes de Tienda">
                                            @foreach($almacenesTienda as $alm)
                                                <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                                <p class="text-xs text-gray-400 mt-1">A dónde llegará la mercadería cuando se reciba.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha <span class="text-red-500">*</span></label>
                                <input type="date" x-model="fecha" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Esperada de Entrega</label>
                                <input type="date" x-model="fecha_esperada"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de orden <span class="text-red-500">*</span></label>
                                <select x-model="tipo_compra" @change="onTipoChange()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                    <option value="nacional">Nacional (proveedor en el Perú, con IGV)</option>
                                    <option value="internacional">Internacional (importación)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Moneda <span class="text-red-500">*</span></label>
                                <select x-model="moneda"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                    <option value="PEN">Soles (S/)</option>
                                    <option value="USD">Dólares (US$)</option>
                                </select>
                            </div>
                            <div x-show="moneda === 'USD'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de cambio <span x-show="tipo_compra === 'nacional'" class="text-red-500">*</span></label>
                                <input type="number" x-model="tipo_cambio" min="0.001" step="0.001" placeholder="Ej. 3.750"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div x-show="tipo_compra === 'internacional'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Incoterm</label>
                                <select x-model="incoterm"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                    <option value="">Sin definir</option>
                                    @foreach(\App\Models\Pedido::INCOTERMS as $inco)
                                        <option value="{{ $inco }}">{{ $inco }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Condición de pago</label>
                                <input type="text" x-model="condicion_pago" maxlength="120" placeholder="Ej. 30% adelanto, saldo contra embarque"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Lugar de entrega</label>
                                <input type="text" x-model="lugar_entrega" maxlength="255" placeholder="Ej. Almacén Lima, Callao"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                                <textarea x-model="observaciones" rows="2" placeholder="Notas para el proveedor..."
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Productos --}}
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-900 px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-boxes mr-2"></i>Productos de la orden
                        </h2>
                        <button type="button" @click="agregarDetalle()"
                                class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-plus mr-1"></i>Agregar Producto
                        </button>
                    </div>
                    <div class="p-6">
                        <template x-if="detalles.length === 0">
                            <div class="text-center py-12">
                                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                                <p class="text-lg font-medium text-gray-500">No hay productos agregados</p>
                                <p class="text-sm text-gray-400 mt-2">Haga clic en "Agregar Producto" para comenzar a crear la orden</p>
                            </div>
                        </template>

                        <template x-for="(detalle, index) in detalles" :key="detalle.uid">
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-5 mb-4 hover:border-blue-400 transition-colors">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-sm font-bold text-blue-900">
                                        <i class="fas fa-cube mr-2"></i>Producto #<span x-text="index + 1"></span>
                                    </span>
                                    <button type="button" @click="detalles.splice(index, 1)"
                                            class="text-red-600 hover:text-red-700 text-sm font-semibold transition-colors">
                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                    </button>
                                </div>

                                {{-- Buscador / producto seleccionado --}}
                                <div class="relative mb-3">
                                    <template x-if="!detalle.producto">
                                        <div class="relative">
                                            <input type="text" x-model="detalle.busqueda" @input="buscarProducto(detalle)"
                                                   placeholder="Buscar producto por nombre (mín. 2 car.)..."
                                                   autocomplete="off"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                            <div x-show="detalle.resultados.length > 0"
                                                 class="absolute z-40 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 max-h-56 overflow-y-auto">
                                                <template x-for="p in detalle.resultados" :key="p.id">
                                                    <div @click="seleccionarProducto(detalle, p)"
                                                         class="px-3 py-2.5 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0">
                                                        <div class="font-medium text-gray-900 text-sm" x-text="p.nombre"></div>
                                                        <div class="text-[11px] font-mono text-gray-400" x-text="p.codigos"></div>
                                                        <div class="text-xs text-gray-500" x-text="p.categoria + (p.tiene_variantes ? ' · tiene variantes' : '')"></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="detalle.producto">
                                        <div class="p-2.5 bg-white border border-gray-300 rounded-lg flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-900" x-text="detalle.producto.nombre"></span>
                                            <button type="button" @click="quitarProducto(detalle)"
                                                    class="text-xs text-blue-600 hover:text-red-600 border border-blue-300 hover:border-red-400 rounded-lg px-2 py-1">
                                                <i class="fas fa-times"></i> Cambiar
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                {{-- Variante (si el producto tiene) --}}
                                <div class="mb-3" x-show="detalle.producto && detalle.producto.tiene_variantes">
                                    <label class="block text-xs font-medium text-gray-700 mb-2">Variante <span class="text-red-500">*</span></label>
                                    <select x-model="detalle.variante_id" @change="onVarianteChange(detalle)"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option value="">Seleccione variante</option>
                                        <template x-for="v in (detalle.producto?.variantes || [])" :key="v.id">
                                            <option :value="v.id"
                                                    x-text="(v.etiqueta || [v.color_nombre, v.capacidad].filter(Boolean).join(' - ')) + ' (stock: ' + v.stock_actual + ')'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Cantidad <span class="text-red-500">*</span></label>
                                        <input type="number" x-model.number="detalle.cantidad" min="1" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-2">Precio unitario (<span x-text="moneda === 'USD' ? 'US$' : 'S/'"></span><span x-show="tipo_compra === 'nacional'"> sin IGV</span>)</label>
                                        <input type="number" x-model.number="detalle.precio_referencial" min="0" step="0.01" placeholder="0.00"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Totales --}}
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <dl class="max-w-xs ml-auto space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd x-text="simbolo + ' ' + subtotal.toFixed(2)"></dd></div>
                        <div class="flex justify-between" x-show="tipo_compra === 'nacional'"><dt class="text-gray-500">IGV (18%)</dt><dd x-text="simbolo + ' ' + igv.toFixed(2)"></dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold"><dt>Total</dt><dd x-text="simbolo + ' ' + total.toFixed(2)"></dd></div>
                    </dl>
                    <p class="text-xs text-gray-400 text-right mt-2" x-show="tipo_compra === 'internacional'">Los impuestos de importación se registran al recibir la mercadería.</p>
                </div>

                {{-- Botones --}}
                <div class="flex items-center justify-end space-x-4 pt-6">
                    <a href="{{ route('pedidos.index') }}"
                       class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" :disabled="guardando || detalles.length === 0"
                            class="px-6 py-3 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <span x-show="!guardando"><i class="fas fa-save mr-2"></i>Crear orden de compra</span>
                        <span x-show="guardando"><i class="fas fa-spinner fa-spin mr-2"></i>Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function pedidoForm() {
        return {
            catalogoProductos: @json($productos),
            catalogoProveedores: @json($proveedores),

            proveedor_id: '{{ old("proveedor_id") }}',
            proveedorBusqueda: '',
            proveedorResultados: [],
            proveedorSeleccionado: null,

            almacen_id: '{{ old("almacen_id") }}',
            fecha: '{{ old("fecha", date("Y-m-d")) }}',
            fecha_esperada: '{{ old("fecha_esperada") }}',
            observaciones: '{{ old("observaciones") }}',
            tipo_compra: '{{ old("tipo_compra", "nacional") }}',
            moneda: '{{ old("moneda", "PEN") }}',
            tipo_cambio: '{{ old("tipo_cambio") }}',
            incoterm: '{{ old("incoterm") }}',
            condicion_pago: '{{ old("condicion_pago") }}',
            lugar_entrega: '{{ old("lugar_entrega") }}',
            detalles: [],
            guardando: false,
            _uid: 0,

            buscarProveedor() {
                const t = this.proveedorBusqueda.trim().toLowerCase();
                if (t.length < 2) { this.proveedorResultados = []; return; }
                this.proveedorResultados = this.catalogoProveedores.filter(p =>
                    (p.ruc || '').toLowerCase().includes(t) ||
                    (p.razon_social || '').toLowerCase().includes(t) ||
                    (p.nombre_comercial || '').toLowerCase().includes(t)
                ).slice(0, 8);
            },
            seleccionarProveedor(p) {
                this.proveedor_id = p.id;
                this.proveedorSeleccionado = p;
                this.proveedorBusqueda = '';
                this.proveedorResultados = [];
            },
            quitarProveedor() {
                this.proveedor_id = '';
                this.proveedorSeleccionado = null;
            },

            // Una orden internacional se cotiza normalmente en dólares y no lleva IGV
            onTipoChange() {
                if (this.tipo_compra === 'internacional') { this.moneda = 'USD'; } else { this.incoterm = ''; }
            },
            get simbolo() { return this.moneda === 'USD' ? 'US$' : 'S/'; },
            get subtotal() {
                return this.detalles.reduce((s, d) => s + (Number(d.cantidad) || 0) * (Number(d.precio_referencial) || 0), 0);
            },
            get igv() { return this.tipo_compra === 'nacional' ? Math.round(this.subtotal * 18) / 100 : 0; },
            get total() { return this.subtotal + this.igv; },

            agregarDetalle() {
                this.detalles.push({
                    uid: this._uid++,
                    producto_id: '', variante_id: '', cantidad: 1, precio_referencial: 0,
                    producto: null, busqueda: '', resultados: [],
                });
            },
            buscarProducto(detalle) {
                const t = (detalle.busqueda || '').trim().toLowerCase();
                if (t.length < 2) { detalle.resultados = []; return; }
                detalle.resultados = this.catalogoProductos
                    .filter(p => (p.texto_busqueda || p.nombre.toLowerCase()).includes(t))
                    .slice(0, 8);
            },
            seleccionarProducto(detalle, p) {
                detalle.producto_id = p.id;
                detalle.producto = p;
                detalle.variante_id = '';
                detalle.busqueda = '';
                detalle.resultados = [];
                if (!p.tiene_variantes && p.ultimo_costo_compra) {
                    detalle.precio_referencial = p.ultimo_costo_compra;
                }
            },
            quitarProducto(detalle) {
                detalle.producto_id = '';
                detalle.producto = null;
                detalle.variante_id = '';
            },
            onVarianteChange(detalle) {
                const v = (detalle.producto?.variantes || []).find(v => v.id == detalle.variante_id);
                if (v && v.ultimo_costo_compra) {
                    detalle.precio_referencial = v.ultimo_costo_compra;
                }
            },

            submitForm() {
                if (!this.proveedor_id) { alert('Seleccione un proveedor'); return; }
                if (!this.almacen_id) { alert('Seleccione el almacén de destino'); return; }
                if (!this.fecha) { alert('Ingrese la fecha'); return; }
                if (this.moneda === 'USD' && this.tipo_compra === 'nacional' && !this.tipo_cambio) { alert('Indique el tipo de cambio: la orden está en dólares'); return; }
                if (this.detalles.length === 0) { alert('Agregue al menos un producto'); return; }
                for (let i = 0; i < this.detalles.length; i++) {
                    const d = this.detalles[i];
                    if (!d.producto_id) { alert(`Producto #${i+1}: Seleccione un producto`); return; }
                    if (d.producto?.tiene_variantes && !d.variante_id) { alert(`Producto #${i+1}: Seleccione una variante`); return; }
                    if (d.cantidad < 1) { alert(`Producto #${i+1}: Cantidad mayor a 0`); return; }
                }
                this.guardando = true;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("pedidos.store") }}';
                const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v ?? ''; form.appendChild(i); };
                add('_token', '{{ csrf_token() }}');
                add('proveedor_id', this.proveedor_id);
                add('almacen_id', this.almacen_id);
                add('fecha', this.fecha);
                add('fecha_esperada', this.fecha_esperada);
                add('observaciones', this.observaciones);
                add('tipo_compra', this.tipo_compra);
                add('moneda', this.moneda);
                add('tipo_cambio', this.moneda === 'USD' ? this.tipo_cambio : '');
                add('incoterm', this.tipo_compra === 'internacional' ? this.incoterm : '');
                add('condicion_pago', this.condicion_pago);
                add('lugar_entrega', this.lugar_entrega);
                this.detalles.forEach((d, i) => {
                    add(`detalles[${i}][producto_id]`, d.producto_id);
                    add(`detalles[${i}][variante_id]`, d.variante_id);
                    add(`detalles[${i}][cantidad]`, d.cantidad);
                    add(`detalles[${i}][precio_referencial]`, d.precio_referencial || 0);
                });
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
    </script>
</body>
</html>
