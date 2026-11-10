<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo pedido interno - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Nuevo pedido interno" subtitle="Pide mercadería a otro almacén de la empresa" />

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

        <div class="max-w-5xl mx-auto" x-data="pedidoInternoForm()">
            <form method="POST" action="{{ route('pedidos-internos.store') }}" @submit="validar($event)">
                @csrf

                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-900 px-6 py-4">
                        <h2 class="text-xl font-bold text-white"><i class="fas fa-info-circle mr-2"></i>Datos del pedido</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="almacen_destino_id" class="block text-sm font-medium text-gray-700 mb-2">Quién lo pide (almacén) <span class="text-red-500">*</span></label>
                            <select id="almacen_destino_id" name="almacen_destino_id" x-model="destino" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">Seleccione almacén</option>
                                @foreach($almacenes as $a)
                                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">A este almacén llegará la mercadería.</p>
                        </div>
                        <div>
                            <label for="almacen_origen_id" class="block text-sm font-medium text-gray-700 mb-2">A quién se lo pide (almacén) <span class="text-red-500">*</span></label>
                            <select id="almacen_origen_id" name="almacen_origen_id" x-model="origen" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">Seleccione almacén</option>
                                @foreach($almacenes as $a)
                                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">De este almacén saldrá la mercadería.</p>
                        </div>
                        <div>
                            <label for="area" class="block text-sm font-medium text-gray-700 mb-2">Área o proyecto</label>
                            <input type="text" id="area" name="area" x-model="area" maxlength="100" placeholder="Ej. Instalación Torre Lima, Showroom"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="fecha_requerida" class="block text-sm font-medium text-gray-700 mb-2">Fecha en que lo necesitas</label>
                            <input type="date" id="fecha_requerida" name="fecha_requerida" x-model="fecha_requerida" min="{{ date('Y-m-d') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" x-model="observaciones" rows="2" maxlength="1000"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-blue-900 px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white"><i class="fas fa-boxes mr-2"></i>Productos que necesitas</h2>
                        <button type="button" @click="agregarDetalle()"
                                class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-plus mr-1"></i>Agregar producto
                        </button>
                    </div>
                    <div class="p-6">
                        <template x-if="detalles.length === 0">
                            <div class="text-center py-10">
                                <i class="fas fa-box-open text-5xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500">Aún no agregaste productos.</p>
                            </div>
                        </template>

                        <template x-for="(d, index) in detalles" :key="d.uid">
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-5 mb-4">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-sm font-bold text-blue-900"><i class="fas fa-cube mr-2"></i>Producto #<span x-text="index + 1"></span></span>
                                    <button type="button" @click="detalles.splice(index, 1)" class="text-red-600 hover:text-red-700 text-sm font-semibold">
                                        <i class="fas fa-trash mr-1"></i>Quitar
                                    </button>
                                </div>

                                <div class="relative mb-3">
                                    <template x-if="!d.producto">
                                        <div class="relative">
                                            <input type="text" x-model="d.busqueda" @input="buscarProducto(d)" autocomplete="off"
                                                   placeholder="Buscar por nombre o código (mín. 2 car.)..."
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                            <div x-show="d.resultados.length > 0"
                                                 class="absolute z-40 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 max-h-56 overflow-y-auto">
                                                <template x-for="p in d.resultados" :key="p.id">
                                                    <div @click="seleccionarProducto(d, p)" class="px-3 py-2.5 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0">
                                                        <div class="font-medium text-gray-900 text-sm" x-text="p.nombre"></div>
                                                        <div class="text-[11px] font-mono text-gray-400" x-text="p.codigos"></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="d.producto">
                                        <div class="p-2.5 bg-white border border-gray-300 rounded-lg flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-900" x-text="d.producto.nombre"></span>
                                            <button type="button" @click="quitarProducto(d)"
                                                    class="text-xs text-blue-600 hover:text-red-600 border border-blue-300 hover:border-red-400 rounded-lg px-2 py-1">
                                                <i class="fas fa-times"></i> Cambiar
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                <div class="mb-3" x-show="d.producto && d.producto.tiene_variantes">
                                    <label class="block text-xs font-medium text-gray-700 mb-2">Variante <span class="text-red-500">*</span></label>
                                    <select x-model="d.variante_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                        <option value="">Seleccione variante</option>
                                        <template x-for="v in (d.producto?.variantes || [])" :key="v.id">
                                            <option :value="v.id" x-text="(v.etiqueta || [v.color_nombre, v.capacidad].filter(Boolean).join(' - ')) + ' (stock total: ' + v.stock_actual + ')'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="max-w-xs">
                                    <label class="block text-xs font-medium text-gray-700 mb-2">Cantidad <span class="text-red-500">*</span></label>
                                    <input type="number" x-model.number="d.cantidad" min="1" max="100000" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                </div>

                                <input type="hidden" :name="`detalles[${index}][producto_id]`" :value="d.producto_id">
                                <input type="hidden" :name="`detalles[${index}][variante_id]`" :value="d.variante_id">
                                <input type="hidden" :name="`detalles[${index}][cantidad]`" :value="d.cantidad">
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4 pt-2">
                    <a href="{{ route('pedidos-internos.index') }}" class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" :disabled="detalles.length === 0"
                            class="px-6 py-3 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar pedido
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function pedidoInternoForm() {
        return {
            catalogoProductos: @json($productos),
            destino: '{{ old("almacen_destino_id", $almacenUsuario) }}',
            origen: '{{ old("almacen_origen_id") }}',
            area: @json(old('area', '')),
            fecha_requerida: '{{ old("fecha_requerida") }}',
            observaciones: @json(old('observaciones', '')),
            detalles: [],
            _uid: 0,

            agregarDetalle() {
                this.detalles.push({ uid: this._uid++, producto_id: '', variante_id: '', cantidad: 1, producto: null, busqueda: '', resultados: [] });
            },
            buscarProducto(d) {
                const t = (d.busqueda || '').trim().toLowerCase();
                if (t.length < 2) { d.resultados = []; return; }
                d.resultados = this.catalogoProductos.filter(p => (p.texto_busqueda || p.nombre.toLowerCase()).includes(t)).slice(0, 8);
            },
            seleccionarProducto(d, p) { d.producto_id = p.id; d.producto = p; d.variante_id = ''; d.busqueda = ''; d.resultados = []; },
            quitarProducto(d) { d.producto_id = ''; d.producto = null; d.variante_id = ''; },

            validar(e) {
                if (this.origen && this.origen === this.destino) { e.preventDefault(); alert('El almacén que pide debe ser distinto del que atiende.'); return false; }
                for (let i = 0; i < this.detalles.length; i++) {
                    const d = this.detalles[i];
                    if (!d.producto_id) { e.preventDefault(); alert('Producto #' + (i + 1) + ': seleccione un producto'); return false; }
                    if (d.producto?.tiene_variantes && !d.variante_id) { e.preventDefault(); alert('Producto #' + (i + 1) + ': seleccione una variante'); return false; }
                    if (!(d.cantidad >= 1)) { e.preventDefault(); alert('Producto #' + (i + 1) + ': la cantidad debe ser mayor a 0'); return false; }
                }
                return true;
            },
        }
    }
    </script>
</body>
</html>
