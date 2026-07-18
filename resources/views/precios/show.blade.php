<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Precios · {{ $producto->nombre }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('precios.index') }}" class="hover:text-blue-700 transition-colors">Gestión de Precios</a>
        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
        <span class="text-gray-800 font-medium truncate">{{ $producto->nombre }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $producto->nombre }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gestión de precios y márgenes</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('precios.historial', $producto) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
                <i class="fas fa-history"></i> Historial
            </a>
            <a href="{{ route('precios.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl">
            <i class="fas fa-check-circle text-green-500"></i>
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <span class="text-sm">{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ====== COLUMNA IZQUIERDA: info + calculadora + formulario ====== --}}
        <div class="space-y-5"
             @precarga-precio.window="precargarPrecio($event.detail.varianteId, $event.detail.precioCompra, $event.detail.precioVenta, $event.detail.margen, $event.detail.precioMayorista, $event.detail.margenMayor)"
             x-data="{
                 proveedorId: '',
                 precioCompra: '',
                 margen: 30,
                 precioVenta: '',
                 modoCalculo: 'margen',
                 resultado: null,
                 margenMayor: 10,
                 precioMayorista: '',
                 modoCalculoMayor: 'margen',
                 resultadoMayor: null,
                 busquedaProv: '',
                 resultadosProv: [],
                 abiertoDropdown: false,
                 buscandoProv: false,
                 ultimaCompra: null,
                 cargandoCompra: false,
                 varianteId: '{{ request('variante_id', '') }}',
                 replicar: true,

                 async buscarProveedor() {
                     if (this.busquedaProv.length < 2) {
                         this.resultadosProv = [];
                         this.abiertoDropdown = false;
                         return;
                     }
                     this.buscandoProv = true;
                     const res = await fetch('{{ route('precios.proveedores.buscar') }}?q=' + encodeURIComponent(this.busquedaProv));
                     this.resultadosProv = await res.json();
                     this.abiertoDropdown = this.resultadosProv.length > 0;
                     this.buscandoProv = false;
                 },

                 async seleccionarProveedor(prov) {
                     this.proveedorId = prov.id;
                     this.busquedaProv = prov.razon_social;
                     this.abiertoDropdown = false;
                     this.resultadosProv = [];
                     this.resultado = null;
                     await this.fetchUltimaCompra();
                 },

                 async fetchUltimaCompra() {
                     if (!this.proveedorId) return;
                     this.cargandoCompra = true;
                     let url = '{{ route('precios.ultimo-precio-compra', $producto) }}?proveedor_id=' + this.proveedorId;
                     if (this.varianteId) url += '&variante_id=' + this.varianteId;
                     const res = await fetch(url);
                     const data = await res.json();
                     this.ultimaCompra = data.found ? data : null;
                     if (data.found) this.precioCompra = data.precio_unitario;
                     else this.precioCompra = '';
                     this.cargandoCompra = false;
                 },

                 async cambiarVariante() {
                     this.resultado = null;
                     await this.fetchUltimaCompra();
                 },

                 precargarPrecio(varianteId, precioCompra, precioVenta, margen, precioMayorista, margenMayor) {
                     this.varianteId   = varianteId ? String(varianteId) : '';
                     this.precioCompra = precioCompra;
                     this.precioVenta  = precioVenta;
                     this.margen       = margen;
                     this.modoCalculo  = 'margen';
                     this.resultado    = {
                         precio_final: parseFloat(precioVenta),
                     };
                     if (precioMayorista) {
                         this.precioMayorista    = precioMayorista;
                         this.margenMayor        = margenMayor || 10;
                         this.modoCalculoMayor   = 'margen';
                         this.resultadoMayor     = { precio_final: parseFloat(precioMayorista) };
                     } else {
                         this.precioMayorista  = '';
                         this.resultadoMayor   = null;
                     }
                     window.scrollTo({ top: 0, behavior: 'smooth' });
                 },

                 limpiarProveedor() {
                     this.proveedorId = '';
                     this.busquedaProv = '';
                     this.abiertoDropdown = false;
                     this.resultadosProv = [];
                     this.ultimaCompra = null;
                     this.precioCompra = '';
                     this.resultado = null;
                 },

                 calcular() {
                     const compra = parseFloat(this.precioCompra) || 0;
                     if (!compra) return;

                     if (this.modoCalculo === 'margen') {
                         const margen = parseFloat(this.margen) || 0;
                         this.precioVenta = Math.round(compra * (1 + margen / 100) * 100) / 100;
                     } else {
                         const venta = parseFloat(this.precioVenta) || 0;
                         if (!venta) return;
                         this.margen = Math.round(((venta - compra) / compra * 100) * 10) / 10;
                     }

                     const precioFinal = parseFloat(this.precioVenta) || 0;
                     this.resultado = {
                         precio_final: precioFinal,
                     };
                 },

                 calcularMayorista() {
                     const compra = parseFloat(this.precioCompra) || 0;

                     if (this.modoCalculoMayor === 'margen') {
                         if (!compra) return;
                         const margen = parseFloat(this.margenMayor) || 0;
                         this.precioMayorista = Math.round(compra * (1 + margen / 100) * 100) / 100;
                     } else {
                         const mayor = parseFloat(this.precioMayorista) || 0;
                         if (!mayor) return;
                         if (compra) {
                             this.margenMayor = Math.round(((mayor - compra) / compra * 100) * 10) / 10;
                         }
                     }

                     this.resultadoMayor = { precio_final: parseFloat(this.precioMayorista) || 0 };
                 }
             }">

            {{-- Info del producto --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-4">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-box"></i> Información del Producto
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        ['Código', $producto->codigo, 'font-mono text-xs bg-gray-100 px-2 py-0.5 rounded'],
                        ['Categoría', $producto->categoria->nombre ?? '—', ''],
                        ['Marca', $producto->marca->nombre ?? '—', ''],
                        ['Modelo', $producto->modelo->nombre ?? '—', ''],
                        ['Stock', ($producto->stock_actual ?? 0) . ' und.', 'font-semibold text-blue-700'],
                    ] as [$label, $value, $extra])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">{{ $label }}</span>
                        <span class="font-medium text-gray-900 {{ $extra }}">{{ $value }}</span>
                    </div>
                    @endforeach

                    @if($precioVentaMin !== null)
                    <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-3 mt-3">
                        <span class="text-gray-500 font-semibold">Precio de Venta</span>
                        <span class="font-bold text-emerald-700 text-base">
                            @if($precioVentaMin == $precioVentaMax)
                                S/ {{ number_format($precioVentaMin, 2) }}
                            @else
                                S/ {{ number_format($precioVentaMin, 2) }} – S/ {{ number_format($precioVentaMax, 2) }}
                            @endif
                        </span>
                    </div>
                    @if($precioVentaMin == $precioVentaMax)
                    <div class="flex items-center justify-between text-xs text-gray-400 mt-1">
                        <span>Base: S/ {{ number_format($precioVentaMin / 1.18, 2) }}</span>
                        <span>IGV: S/ {{ number_format($precioVentaMin - ($precioVentaMin / 1.18), 2) }}</span>
                    </div>
                    @else
                    <p class="text-xs text-gray-400 mt-1">Varía según la capacidad — ver detalle a la derecha.</p>
                    @endif
                    @elseif($producto->precio_venta > 0)
                    <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-3 mt-3">
                        <span class="text-gray-500 font-semibold">Precio de Venta</span>
                        <span class="font-bold text-emerald-700 text-base">S/ {{ number_format($producto->precio_venta, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400 mt-1">
                        <span>Base: S/ {{ number_format($producto->precio_venta / 1.18, 2) }}</span>
                        <span>IGV: S/ {{ number_format($producto->precio_venta - ($producto->precio_venta / 1.18), 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Calculadora + Formulario de Registro --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-700 to-emerald-500 px-5 py-4">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-calculator"></i> Registrar Precio
                    </h2>
                </div>

                <form method="POST" action="{{ route('precios.store', $producto) }}" class="p-5 space-y-4">
                    @csrf

                    {{-- Variante agrupada por capacidad --}}
                    @if($producto->variantes->isNotEmpty())
                    @php
                        $porCapacidad = $producto->variantes->groupBy(fn($v) => $v->capacidad ?? '');
                    @endphp
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                            Capacidad <span class="text-gray-400 normal-case font-normal">(el precio aplica a todos los colores de esa capacidad)</span>
                        </label>
                        <select name="variante_id" x-model="varianteId"
                                @change="cambiarVariante()"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Precio base del producto —</option>
                            @foreach($porCapacidad as $capacidad => $variantes)
                                <option value="{{ $variantes->first()->id }}">
                                    {{ $capacidad ?: 'Sin capacidad específica' }}
                                    ({{ $variantes->count() }} {{ $variantes->count() === 1 ? 'color' : 'colores' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                        <input type="hidden" name="variante_id" value="">
                    @endif

                    {{-- Búsqueda dinámica de proveedor --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                            Proveedor <span class="text-gray-400 normal-case font-normal">(opcional)</span>
                        </label>
                        <input type="hidden" name="proveedor_id" :value="proveedorId">
                        <div class="relative" @click.away="abiertoDropdown = false">
                            <input type="text"
                                   x-model="busquedaProv"
                                   @input.debounce.400ms="buscarProveedor()"
                                   @focus="if (resultadosProv.length) abiertoDropdown = true"
                                   placeholder="Buscar por nombre o RUC..."
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 pr-8 text-sm focus:ring-2 focus:ring-emerald-500">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                <i x-show="buscandoProv || cargandoCompra" class="fas fa-spinner fa-spin text-gray-400 text-xs"></i>
                                <i x-show="!buscandoProv && !cargandoCompra && proveedorId"
                                   @click="limpiarProveedor()"
                                   class="fas fa-times text-gray-400 text-xs cursor-pointer hover:text-red-500 transition-colors"></i>
                                <i x-show="!buscandoProv && !cargandoCompra && !proveedorId"
                                   class="fas fa-search text-gray-400 text-xs"></i>
                            </div>
                            <div x-show="abiertoDropdown" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <template x-for="prov in resultadosProv" :key="prov.id">
                                    <button type="button" @click="seleccionarProveedor(prov)"
                                            class="w-full text-left px-3 py-2.5 hover:bg-emerald-50 transition-colors border-b border-gray-50 last:border-0">
                                        <div class="text-sm font-medium text-gray-800" x-text="prov.razon_social"></div>
                                        <div class="text-xs text-gray-400" x-text="'RUC: ' + prov.ruc"></div>
                                    </button>
                                </template>
                            </div>
                            <div x-show="proveedorId && !abiertoDropdown" class="mt-1.5">
                                <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                    <i class="fas fa-check-circle text-[9px]"></i>
                                    <span x-text="busquedaProv"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Precio compra --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Precio Compra (S/)</label>
                        <input type="number" name="precio_compra" x-model="precioCompra"
                               step="0.01" min="0.01" placeholder="0.00" required
                               class="w-full rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 {{ $errors->has('precio_compra') ? 'border-2 border-red-400' : 'border border-gray-200' }}">
                        @error('precio_compra') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror

                        <div x-show="ultimaCompra" class="mt-1.5 flex items-start gap-1.5 text-xs text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1.5 rounded-lg">
                            <i class="fas fa-shopping-cart mt-0.5 shrink-0"></i>
                            <span>Última compra: <strong x-text="'S/ ' + Number(ultimaCompra?.precio_unitario).toFixed(2)"></strong>
                            · <span x-text="ultimaCompra?.fecha_compra"></span></span>
                        </div>
                    </div>

                    {{-- Toggle modo de cálculo --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Calcular desde</label>
                        <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="modoCalculo='margen'; calcular()"
                                    :class="modoCalculo==='margen' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    class="flex-1 py-2 text-xs font-semibold transition-colors border-r border-gray-200">
                                <i class="fas fa-percentage mr-1"></i> Margen %
                            </button>
                            <button type="button"
                                    @click="modoCalculo='precio'"
                                    :class="modoCalculo==='precio' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    class="flex-1 py-2 text-xs font-semibold transition-colors">
                                <i class="fas fa-tag mr-1"></i> Precio de venta
                            </button>
                        </div>
                    </div>

                    {{-- Margen % --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Margen %</label>
                        <input type="number" name="margen" x-model="margen"
                               @input="if(modoCalculo==='margen') calcular()"
                               :readonly="modoCalculo==='precio'"
                               :class="modoCalculo==='precio' ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                               step="0.1" min="0" max="1000"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                        <p x-show="modoCalculo==='precio'" class="text-xs text-gray-400 mt-1">Calculado según el precio ingresado</p>
                    </div>

                    {{-- Precio de venta (editable en modo 'precio') --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                            Precio de Venta (S/)
                            <span x-show="modoCalculo==='precio'" class="text-red-500">*</span>
                        </label>
                        <input type="number" x-model="precioVenta"
                               @input="if(modoCalculo==='precio') calcular()"
                               :readonly="modoCalculo==='margen'"
                               :class="modoCalculo==='margen' ? 'bg-gray-50 text-emerald-700 font-semibold cursor-not-allowed' : 'text-emerald-700 font-semibold'"
                               step="0.01" min="0.01" placeholder="0.00"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                        <p x-show="modoCalculo==='margen'" class="text-xs text-gray-400 mt-1">Calculado según el margen</p>
                        <p x-show="modoCalculo==='precio'" class="text-xs text-gray-400 mt-1">Ingresa el precio que quieres cobrar</p>
                    </div>

                    <input type="hidden" name="incluye_igv" value="1">

                    {{-- Botón calcular (para trigger explícito) --}}
                    <button type="button" @click="calcular()"
                            :disabled="!precioCompra || (modoCalculo==='precio' && !precioVenta)"
                            class="w-full py-2 bg-slate-100 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-200 transition-colors disabled:opacity-40 border border-slate-200">
                        <i class="fas fa-calculator mr-1"></i> Calcular
                    </button>

                    {{-- Resultado calculado --}}
                    <template x-if="resultado">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-semibold text-emerald-800 flex items-center gap-1.5">
                                    <i class="fas fa-tag text-emerald-500 text-xs"></i> Precio de Venta
                                </span>
                                <span class="font-bold text-emerald-800 text-lg" x-text="'S/ ' + resultado.precio_final.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 border-t border-emerald-200 pt-2">
                                <span>Margen</span>
                                <span class="font-semibold" x-text="(parseFloat(margen)||0).toFixed(1) + '%'"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Base sin IGV</span>
                                <span x-text="'S/ ' + (resultado.precio_final / 1.18).toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>IGV (18%)</span>
                                <span x-text="'S/ ' + (resultado.precio_final - resultado.precio_final / 1.18).toFixed(2)"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Campo oculto precio_venta --}}
                    <input type="hidden" name="precio_venta" :value="precioVenta || ''">

                    {{-- ── Sección Precio Mayorista ── --}}
                    <div class="border-t border-dashed border-amber-200 pt-4 mt-2 space-y-3">
                        <p class="text-xs font-bold text-amber-700 uppercase tracking-wide flex items-center gap-1.5">
                            <i class="fas fa-tags"></i> Precio Mayorista
                            <span class="text-gray-400 normal-case font-normal">(opcional)</span>
                        </p>

                        {{-- Toggle modo mayorista --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Calcular desde</label>
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                                <button type="button"
                                        @click="modoCalculoMayor='margen'; calcularMayorista()"
                                        :class="modoCalculoMayor==='margen' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="flex-1 py-2 text-xs font-semibold transition-colors border-r border-gray-200">
                                    <i class="fas fa-percentage mr-1"></i> Margen %
                                </button>
                                <button type="button"
                                        @click="modoCalculoMayor='precio'"
                                        :class="modoCalculoMayor==='precio' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="flex-1 py-2 text-xs font-semibold transition-colors">
                                    <i class="fas fa-tag mr-1"></i> Precio directo
                                </button>
                            </div>
                        </div>

                        {{-- Margen mayorista --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Margen Mayorista %</label>
                            <input type="number" x-model="margenMayor"
                                   @input="if(modoCalculoMayor==='margen') calcularMayorista()"
                                   :readonly="modoCalculoMayor==='precio'"
                                   :class="modoCalculoMayor==='precio' ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                   step="0.1" min="0" max="1000"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
                            <p x-show="modoCalculoMayor==='precio'" class="text-xs text-gray-400 mt-1">Calculado según el precio ingresado</p>
                        </div>

                        {{-- Precio mayorista --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                Precio Mayorista (S/)
                                <span x-show="modoCalculoMayor==='precio'" class="text-red-500">*</span>
                            </label>
                            <input type="number" x-model="precioMayorista"
                                   @input="if(modoCalculoMayor==='precio') calcularMayorista()"
                                   :readonly="modoCalculoMayor==='margen'"
                                   :class="modoCalculoMayor==='margen' ? 'bg-gray-50 text-amber-700 font-semibold cursor-not-allowed' : 'text-amber-700 font-semibold'"
                                   step="0.01" min="0.01" placeholder="0.00"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
                            <p x-show="modoCalculoMayor==='margen'" class="text-xs text-gray-400 mt-1">Calculado según el margen</p>
                        </div>

                        {{-- Botón calcular mayorista --}}
                        <button type="button" @click="calcularMayorista()"
                                :disabled="(modoCalculoMayor==='margen' && !precioCompra) || (modoCalculoMayor==='precio' && !precioMayorista)"
                                class="w-full py-2 bg-amber-50 text-amber-700 text-sm font-semibold rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-40 border border-amber-200">
                            <i class="fas fa-calculator mr-1"></i> Calcular Mayorista
                        </button>

                        {{-- Preview resultado mayorista --}}
                        <template x-if="resultadoMayor">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Margen mayorista</span>
                                    <span class="font-semibold text-amber-700" x-text="(parseFloat(margenMayor)||0).toFixed(1) + '%'"></span>
                                </div>
                                <div class="flex justify-between text-sm border-t border-amber-200 pt-2">
                                    <span class="font-semibold text-gray-700">Precio mayorista</span>
                                    <span class="font-bold text-amber-700 text-lg" x-text="'S/ ' + resultadoMayor.precio_final.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 pt-1" x-show="resultado">
                                    <span>vs. precio regular</span>
                                    <span x-text="resultado ? 'S/ ' + resultado.precio_final.toFixed(2) : ''"></span>
                                </div>
                            </div>
                        </template>

                        <input type="hidden" name="precio_mayorista" :value="precioMayorista || ''">
                        <input type="hidden" name="margen_mayorista" :value="resultadoMayor ? (parseFloat(margenMayor)||0) : ''">
                    </div>

                    {{-- Observaciones --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Observaciones</label>
                        <textarea name="observaciones" rows="2" placeholder="Motivo del precio, notas..."
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                    </div>

                    {{-- Replicar a tiendas --}}
                    <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                        <input type="checkbox" name="replicar_tiendas" value="1" x-model="replicar"
                               class="mt-0.5 h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">Replicar a todas las tiendas</p>
                            <p class="text-xs text-emerald-600 mt-0.5">
                                Se creará el mismo precio en cada sucursal activa.
                                Cada tienda podrá modificarlo después.
                            </p>
                        </div>
                    </label>

                    {{-- Botón guardar --}}
                    <button type="submit"
                            :disabled="!resultado"
                            class="w-full py-3 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed shadow-sm">
                        <i class="fas fa-save mr-2"></i>
                        Guardar Precio
                    </button>

                    <p x-show="!resultado" x-cloak class="text-xs text-center text-gray-400">
                        Completa los datos y haz clic en "Calcular" para habilitar el guardado.
                    </p>
                </form>
            </div>
        </div>

        {{-- ====== COLUMNA DERECHA: tabla de precios ====== --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- KPI cards --}}
            @php
                $precioGlobal        = $preciosGlobales->where('activo', true)->first();
                $porCapacidadGlobal  = $producto->variantes->isNotEmpty()
                    ? $producto->variantes->groupBy(fn($v) => $v->capacidad ?? '')
                    : collect();
                $conPrecioCount      = $porCapacidadGlobal->filter(
                    fn($vars) => isset($preciosGlobalesActivos[$vars->first()->id])
                )->count();
                $totalCapCount       = $porCapacidadGlobal->count();
                $varianteActual      = request('variante_id')
                    ? $producto->variantes->firstWhere('id', (int) request('variante_id'))
                    : null;
            @endphp

            @if($porCapacidadGlobal->isNotEmpty())
                {{-- Producto con variantes: tarjeta por capacidad --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 {{ $porCapacidadGlobal->count() >= 3 ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }} gap-3">
                    @foreach($porCapacidadGlobal as $cap => $vars)
                        @php
                            $varRep  = $vars->first();
                            $pCap    = $vars->map(fn($v) => $preciosGlobalesActivos[$v->id] ?? null)->filter()->sortByDesc('id')->first();
                            $pMay    = $vars->map(fn($v) => $preciosMayoristasActivos[$v->id] ?? null)->filter()->sortByDesc('id')->first();
                            $esActual = $varianteActual && $vars->contains('id', $varianteActual->id);
                            $pmVal   = $pMay?->precio ?? 'null';
                            $pmMrg   = $pMay?->margen ?? 10;
                        @endphp
                        <div class="bg-white rounded-xl border {{ $esActual ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-100' }} shadow-sm p-4 cursor-pointer hover:border-blue-300 transition-all"
                             onclick="window.dispatchEvent(new CustomEvent('precarga-precio', { detail: { varianteId: {{ $varRep->id }}, precioCompra: {{ $pCap?->precio_compra ?? 0 }}, precioVenta: {{ $pCap?->precio ?? 0 }}, margen: {{ $pCap?->margen ?? 0 }}, precioMayorista: {{ $pmVal }}, margenMayor: {{ $pmMrg }} } }))">
                            <div class="flex items-center justify-between mb-3">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                                    <i class="fas fa-microchip text-blue-500 text-xs"></i>
                                    {{ $cap ?: 'Sin capacidad' }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $vars->count() }} color{{ $vars->count() > 1 ? 'es' : '' }}</span>
                            </div>
                            @if($pCap)
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Compra</span>
                                        <span class="font-medium text-gray-700">{{ $pCap->precio_compra ? 'S/ ' . number_format($pCap->precio_compra, 2) : '—' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500">P. Venta</span>
                                        <span class="text-base font-bold text-blue-700">S/ {{ number_format($pCap->precio, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs border-t border-gray-100 pt-1.5 mt-1.5">
                                        <span class="text-gray-500">Margen</span>
                                        <span class="font-bold {{ ($pCap->margen ?? 0) >= 20 ? 'text-green-700' : 'text-yellow-600' }}">
                                            {{ $pCap->margen ? $pCap->margen . '%' : '—' }}
                                        </span>
                                    </div>
                                    @if($pMay)
                                        <div class="flex justify-between items-center border-t border-amber-100 pt-1.5 mt-1.5 bg-amber-50 -mx-1 px-1 rounded">
                                            <span class="flex items-center gap-1 text-xs text-amber-700 font-semibold">
                                                <i class="fas fa-tags text-[9px]"></i> Mayorista
                                            </span>
                                            <div class="text-right">
                                                <span class="text-sm font-bold text-amber-700">S/ {{ number_format($pMay->precio, 2) }}</span>
                                                @if($pMay->margen)
                                                    <span class="block text-[10px] text-amber-500">{{ $pMay->margen }}% mg.</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center justify-center py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-full">
                                        <i class="fas fa-exclamation-circle"></i> Sin precio — clic para registrar
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Producto sin variantes: KPIs + mayorista --}}
                @php $precioMayoristaGlobal = $preciosMayoristasActivos[null] ?? $preciosMayoristasActivos->first(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-blue-200 shadow-sm p-4 text-center">
                        <p class="text-xs text-blue-600 uppercase tracking-wide mb-1 font-semibold">Precio de Venta</p>
                        <p class="text-xl font-bold text-blue-700">
                            {{ $precioGlobal ? 'S/ ' . number_format($precioGlobal->precio, 2) : '—' }}
                        </p>
                        @if($precioGlobal)
                            <p class="text-[10px] text-gray-400 mt-0.5">Base: S/ {{ number_format($precioGlobal->precio / 1.18, 2) }} + IGV</p>
                        @endif
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Precio Compra</p>
                        <p class="text-xl font-bold text-gray-700">
                            {{ $precioGlobal ? 'S/ ' . number_format($precioGlobal->precio_compra, 2) : '—' }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Margen</p>
                        <p class="text-xl font-bold {{ ($precioGlobal?->margen ?? 0) >= 20 ? 'text-green-700' : 'text-yellow-600' }}">
                            {{ $precioGlobal ? $precioGlobal->margen . '%' : '—' }}
                        </p>
                    </div>
                    <div class="bg-white rounded-xl border {{ $precioMayoristaGlobal ? 'border-amber-200 bg-amber-50' : 'border-gray-100' }} shadow-sm p-4 text-center">
                        <p class="text-xs {{ $precioMayoristaGlobal ? 'text-amber-600' : 'text-gray-500' }} uppercase tracking-wide mb-1 flex items-center justify-center gap-1">
                            <i class="fas fa-tags text-[9px]"></i> Mayorista
                        </p>
                        <p class="text-xl font-bold {{ $precioMayoristaGlobal ? 'text-amber-700' : 'text-gray-400' }}">
                            {{ $precioMayoristaGlobal ? 'S/ ' . number_format($precioMayoristaGlobal->precio, 2) : '—' }}
                        </p>
                        @if($precioMayoristaGlobal?->margen)
                            <p class="text-xs text-amber-500 mt-1">{{ $precioMayoristaGlobal->margen }}% mg.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- PRECIOS GLOBALES (sin tienda) --}}
            {{-- $porCapacidadGlobal, $conPrecioCount, $totalCapCount already defined in KPI block above --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-globe"></i> Precio Global (todas las tiendas)
                    </h2>
                    @if($producto->variantes->isNotEmpty())
                        <span class="text-xs text-blue-200">{{ $conPrecioCount }}/{{ $totalCapCount }} capacidades con precio</span>
                    @else
                        <span class="text-xs text-blue-200">{{ $preciosGlobales->count() }} registro(s)</span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                    {{ $producto->variantes->isNotEmpty() ? 'Capacidad' : 'Variante' }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Proveedor</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">P. Compra</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">P. Venta</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Margen</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                            @if($producto->variantes->isNotEmpty())
                                {{-- Producto con variantes: una fila por capacidad --}}
                                @foreach($porCapacidadGlobal as $capacidad => $variantes)
                                    @php
                                        $varRep   = $variantes->first();
                                        $precio   = $variantes->map(fn($v) => $preciosGlobalesActivos[$v->id] ?? null)->filter()->sortByDesc('id')->first();
                                        $pMayHist = $variantes->map(fn($v) => $preciosMayoristasActivos[$v->id] ?? null)->filter()->sortByDesc('id')->first();
                                        $pmHVal   = $pMayHist?->precio ?? 'null';
                                        $pmHMrg   = $pMayHist?->margen ?? 10;
                                    @endphp
                                    <tr class="hover:bg-blue-50/30 transition-colors {{ $precio ? '' : 'bg-amber-50/30' }}">
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-medium text-gray-900">{{ $capacidad ?: 'Sin capacidad' }}</div>
                                            <div class="text-xs text-gray-400 mt-0.5">
                                                {{ $variantes->count() }} {{ $variantes->count() === 1 ? 'color' : 'colores' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $precio?->proveedor?->razon_social ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">
                                            {{ $precio?->precio_compra ? 'S/ ' . number_format($precio->precio_compra, 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($precio)
                                                <span class="text-sm font-bold text-blue-700">S/ {{ number_format($precio->precio, 2) }}</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                    <i class="fas fa-exclamation-circle text-[9px]"></i> Sin precio
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($precio?->margen)
                                                <span class="text-sm font-semibold {{ ($precio->margen ?? 0) >= 20 ? 'text-green-700' : 'text-yellow-700' }}">
                                                    {{ $precio->margen }}%
                                                </span>
                                            @else
                                                <span class="text-gray-400 text-sm">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($precio)
                                                @if($precio->activo)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                        <i class="fas fa-circle text-[6px]"></i> Activo
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                        <i class="fas fa-circle text-[6px]"></i> Inactivo
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                                @if($precio)
                                                    <button type="button"
                                                            onclick="window.dispatchEvent(new CustomEvent('precarga-precio', { detail: { varianteId: {{ $varRep->id }}, precioCompra: {{ $precio->precio_compra ?? 0 }}, precioVenta: {{ $precio->precio }}, margen: {{ $precio->margen ?? 0 }}, precioMayorista: {{ $pmHVal }}, margenMayor: {{ $pmHMrg }} } }))"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-medium hover:bg-emerald-100 transition-colors">
                                                        <i class="fas fa-sync-alt"></i> Actualizar
                                                    </button>
                                                    <a href="{{ route('precios.edit', ['producto' => $producto->id, 'precio' => $precio->id]) }}"
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-lg text-xs font-medium hover:bg-yellow-100 transition-colors">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </a>
                                                @else
                                                    <span class="text-xs text-gray-400 italic">Registra desde el formulario</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                            @else
                                {{-- Producto sin variantes: precio base --}}
                                @php $precioBase = $preciosGlobalesActivos->first(); @endphp
                                @if($precioBase)
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3 text-sm text-gray-400 italic">Base</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $precioBase->proveedor?->razon_social ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">
                                            {{ $precioBase->precio_compra ? 'S/ ' . number_format($precioBase->precio_compra, 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-sm font-bold text-blue-700">S/ {{ number_format($precioBase->precio, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-sm font-semibold {{ ($precioBase->margen ?? 0) >= 20 ? 'text-green-700' : 'text-yellow-700' }}">
                                                {{ $precioBase->margen ? $precioBase->margen . '%' : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                <i class="fas fa-circle text-[6px]"></i> Activo
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button"
                                                        onclick="window.dispatchEvent(new CustomEvent('precarga-precio', { detail: { varianteId: null, precioCompra: {{ $precioBase->precio_compra ?? 0 }}, precioVenta: {{ $precioBase->precio }}, margen: {{ $precioBase->margen ?? 0 }}, precioMayorista: {{ $precioMayoristaGlobal?->precio ?? 'null' }}, margenMayor: {{ $precioMayoristaGlobal?->margen ?? 10 }} } }))"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-medium hover:bg-emerald-100 transition-colors">
                                                    <i class="fas fa-sync-alt"></i> Actualizar
                                                </button>
                                                <a href="{{ route('precios.edit', ['producto' => $producto->id, 'precio' => $precioBase->id]) }}"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-lg text-xs font-medium hover:bg-yellow-100 transition-colors">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="8" class="py-12 text-center">
                                            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                                <i class="fas fa-globe text-2xl text-gray-400"></i>
                                            </div>
                                            <p class="text-gray-500 font-medium text-sm">Sin precio global registrado</p>
                                            <p class="text-gray-400 text-xs mt-1">Usa el formulario de la izquierda para registrar el primer precio.</p>
                                        </td>
                                    </tr>
                                @endif
                            @endif

                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PRECIOS POR TIENDA --}}
            {{-- PRECIOS POR TIENDA --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="{
        expandedStores: {},
        selectedPrices: [],

        /* ── Modal de confirmación ── */
        modal: { show: false, type: 'confirm', title: '', body: '', icon: '', iconColor: '', confirmLabel: 'Confirmar', confirmColor: 'bg-blue-600 hover:bg-blue-700', onConfirm: null },
        /* ── Modal de input ── */
        inputModal: { show: false, label: '', placeholder: '', value: '', onConfirm: null },
        /* ── Toast de resultado ── */
        toast: { show: false, success: true, message: '' },

        showToast(success, message) {
            this.toast = { show: true, success, message };
            setTimeout(() => this.toast.show = false, 3500);
        },

        openConfirm(title, body, icon, iconColor, confirmLabel, confirmColor, callback) {
            this.modal = { show: true, type: 'confirm', title, body, icon, iconColor, confirmLabel, confirmColor, onConfirm: callback };
        },

        openInput(label, placeholder, callback) {
            this.inputModal = { show: true, label, placeholder, value: '', onConfirm: callback };
        },

        toggleStore(storeId) {
            this.expandedStores[storeId] = !this.expandedStores[storeId];
        },

        toggleAllInStore(key, priceIds) {
            const allSelected = priceIds.every(id => this.selectedPrices.includes(id));
            if (allSelected) {
                this.selectedPrices = this.selectedPrices.filter(id => !priceIds.includes(id));
            } else {
                priceIds.forEach(id => { if (!this.selectedPrices.includes(id)) this.selectedPrices.push(id); });
            }
        },

        async applyToSelected(action, value = null, overrideIds = null) {
            const ids = overrideIds ?? this.selectedPrices;
            if (ids.length === 0) {
                this.openConfirm('Sin selección', 'Debes seleccionar al menos un precio para continuar.', 'fa-info-circle', 'text-blue-500', 'Entendido', 'bg-blue-600 hover:bg-blue-700', null);
                return;
            }
            try {
                const res  = await fetch('{{ route("precios.bulk-action", $producto) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ action, price_ids: ids, value })
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast(true, data.message ?? 'Acción aplicada correctamente');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.showToast(false, data.message ?? 'Ocurrió un error al procesar la acción');
                }
            } catch {
                this.showToast(false, 'No se pudo conectar con el servidor. Intenta nuevamente.');
            }
        },

        bulkUpdatePrice() {
            this.openInput('Nuevo precio de venta (S/)', 'Ej: 950.00', (val) => {
                const v = parseFloat(val);
                if (!isNaN(v) && v > 0) this.applyToSelected('update_price', v);
                else this.showToast(false, 'Ingresa un precio válido mayor a 0');
            });
        },

        bulkActivate() {
            this.openConfirm('Activar precios', '¿Activar todos los precios seleccionados?', 'fa-check-circle', 'text-green-500', 'Sí, activar', 'bg-green-600 hover:bg-green-700',
                () => this.applyToSelected('activate'));
        },

        bulkDeactivate() {
            this.openConfirm('Desactivar precios', '¿Desactivar los precios seleccionados? Quedarán inactivos.', 'fa-ban', 'text-red-500', 'Sí, desactivar', 'bg-red-600 hover:bg-red-700',
                () => this.applyToSelected('deactivate'));
        },

        bulkRestoreGlobal() {
            this.openConfirm('Restaurar precio global', 'Se reemplazarán los precios personalizados seleccionados con el precio global actual. ¿Continuar?', 'fa-undo-alt', 'text-purple-500', 'Sí, restaurar', 'bg-purple-600 hover:bg-purple-700',
                () => this.applyToSelected('restore_global'));
        }
     }">

    {{-- ══ Modal de confirmación ══ --}}
    <div x-show="modal.show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="modal.show = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 flex flex-col gap-4"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                    <i :class="'fas ' + modal.icon + ' text-lg ' + modal.iconColor"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900" x-text="modal.title"></h3>
                    <p class="text-sm text-gray-500 mt-1" x-text="modal.body"></p>
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-1">
                <button type="button" @click="modal.show = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                    Cancelar
                </button>
                <button type="button"
                        @click="modal.show = false; if (modal.onConfirm) modal.onConfirm()"
                        :class="modal.confirmColor"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-colors"
                        x-text="modal.confirmLabel">
                </button>
            </div>
        </div>
    </div>

    {{-- ══ Modal de input (cambiar precio) ══ --}}
    <div x-show="inputModal.show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="inputModal.show = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 flex flex-col gap-4"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-dollar-sign text-blue-600"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900" x-text="inputModal.label"></h3>
            </div>
            <input type="number" x-model="inputModal.value" :placeholder="inputModal.placeholder"
                   step="0.01" min="0.01"
                   @keydown.enter="inputModal.show = false; inputModal.onConfirm && inputModal.onConfirm(inputModal.value)"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autofocus>
            <div class="flex gap-2 justify-end">
                <button type="button" @click="inputModal.show = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                    Cancelar
                </button>
                <button type="button"
                        @click="inputModal.show = false; inputModal.onConfirm && inputModal.onConfirm(inputModal.value)"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    Aplicar
                </button>
            </div>
        </div>
    </div>

    {{-- ══ Toast de resultado ══ --}}
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         :class="toast.success ? 'bg-green-600' : 'bg-red-600'"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl text-white text-sm font-medium max-w-xs">
        <i :class="toast.success ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
        <span x-text="toast.message"></span>
    </div>
    
    <div class="bg-gradient-to-r from-indigo-700 to-indigo-500 px-5 py-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
            <i class="fas fa-store"></i> Precio por Tienda / Sucursal
        </h2>
        <div class="flex gap-2">
            <span class="text-xs text-indigo-200">{{ $preciosPorTienda->count() }} registro(s)</span>
        </div>
    </div>

    @if($preciosPorTienda->count())
        {{-- Barra de acciones masivas --}}
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-500 mr-2">
                <i class="fas fa-check-square"></i> Acciones masivas:
            </span>
            <button type="button" @click="bulkUpdatePrice()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-dollar-sign"></i> Cambiar Precio
            </button>
            <button type="button" @click="bulkActivate()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-50 text-green-700 text-xs font-medium rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-check-circle"></i> Activar
            </button>
            <button type="button" @click="bulkDeactivate()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors">
                <i class="fas fa-ban"></i> Desactivar
            </button>
            <button type="button" @click="bulkRestoreGlobal()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-50 text-purple-700 text-xs font-medium rounded-lg hover:bg-purple-100 transition-colors">
                <i class="fas fa-undo-alt"></i> Restaurar Global
            </button>
            <span x-show="selectedPrices.length > 0" class="text-xs text-emerald-600 ml-auto" x-text="selectedPrices.length + ' seleccionado(s)'"></span>
        </div>

        {{-- Vista agrupada por tienda → capacidad --}}
                <div class="divide-y divide-gray-100">
                    @php
                        $preciosPorTiendaAgrupados = $preciosPorTienda->groupBy(fn($p) => $p->almacen?->nombre ?? 'Sin tienda');
                    @endphp

                    @foreach($preciosPorTiendaAgrupados as $nombreTienda => $preciosTienda)
                        @php
                            $storeKey  = Str::slug($nombreTienda);
                            $priceIds  = $preciosTienda->pluck('id')->toArray();
                            // Group this store's prices by capacity
                            $capsTienda = $preciosTienda->groupBy(fn($p) => $p->variante?->capacidad ?? '');
                        @endphp

                        <div class="store-group">
                            {{-- Header de tienda --}}
                            <div class="bg-gray-50/50 hover:bg-gray-100/50 transition-colors cursor-pointer"
                                 @click="toggleStore('{{ $storeKey }}')">
                                <div class="px-5 py-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            @click.stop
                                            @change="toggleAllInStore('{{ $storeKey }}', {{ json_encode($priceIds) }})"
                                            :checked="selectedPrices.length > 0 && {{ json_encode($priceIds) }}.every(id => selectedPrices.includes(id))"
                                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                                            <i class="fas fa-store text-indigo-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">{{ $nombreTienda }}</p>
                                            @php
                                                $capsReales      = $porCapacidadGlobal->count() ?: $capsTienda->count();
                                                $variantesReales = $porCapacidadGlobal->sum(fn($v) => $v->count()) ?: $preciosTienda->count();
                                            @endphp
                                            <p class="text-xs text-gray-400">
                                                {{ $capsReales }} {{ $capsReales === 1 ? 'capacidad' : 'capacidades' }}
                                                · {{ $variantesReales }} {{ $variantesReales === 1 ? 'variante' : 'variantes' }}
                                            </p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform"
                                       :class="{'rotate-180': expandedStores['{{ $storeKey }}']}"></i>
                                </div>
                            </div>

                            {{-- Detalle agrupado por capacidad --}}
                            <div x-show="expandedStores['{{ $storeKey }}']" x-cloak>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full">
                                        <thead>
                                            <tr class="border-b border-gray-100 bg-indigo-50/30">
                                                <th class="pl-14 pr-4 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Capacidad</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Proveedor</th>
                                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">P. Compra</th>
                                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">P. Venta</th>
                                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Margen</th>
                                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            @foreach($capsTienda as $cap => $preciosCap)
                                                @php
                                                    $pRep    = $preciosCap->first();
                                                    $capIds  = $preciosCap->pluck('id')->toArray();
                                                @endphp
                                                <tr class="hover:bg-indigo-50/30 transition-colors {{ $pRep->activo ? '' : 'opacity-60' }}">
                                                    <td class="pl-14 pr-4 py-2.5">
                                                        <div class="flex items-center gap-2">
                                                            <input type="checkbox"
                                                                @click.stop
                                                                @change="toggleAllInStore('{{ $storeKey }}_cap', {{ json_encode($capIds) }})"
                                                                :checked="{{ json_encode($capIds) }}.every(id => selectedPrices.includes(id))"
                                                                class="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                            <i class="fas fa-microchip text-indigo-400 text-xs"></i>
                                                            <span class="text-sm font-medium text-gray-800">{{ $cap ?: 'Sin capacidad' }}</span>
                                                            @php $coloresReales = $porCapacidadGlobal->get($cap)?->count() ?? $preciosCap->count(); @endphp
                                                            <span class="text-xs text-gray-400">({{ $coloresReales }} {{ $coloresReales === 1 ? 'color' : 'colores' }})</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-sm text-gray-500">
                                                        {{ $pRep->proveedor?->razon_social ?? '—' }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-sm text-right text-gray-600">
                                                        {{ $pRep->precio_compra ? 'S/ ' . number_format($pRep->precio_compra, 2) : '—' }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right">
                                                        <span class="text-sm font-bold text-indigo-700">S/ {{ number_format($pRep->precio, 2) }}</span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right">
                                                        @if($pRep->margen !== null)
                                                            <span class="text-sm font-semibold {{ ($pRep->margen ?? 0) >= 20 ? 'text-green-700' : 'text-yellow-700' }}">
                                                                {{ $pRep->margen }}%
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 text-center">
                                                        @if($pRep->activo)
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                                <i class="fas fa-circle text-[6px]"></i> Activo
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                                <i class="fas fa-circle text-[6px]"></i> Inactivo
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 text-center">
                                                        <div class="flex items-center justify-center gap-1.5">
                                                            <a href="{{ route('precios.edit', ['producto' => $producto->id, 'precio' => $pRep->id]) }}"
                                                               class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-medium hover:bg-indigo-100 transition-colors">
                                                                <i class="fas fa-edit"></i> Editar
                                                            </a>
                                                            @php
                                                                // Find matching global price for this capacity
                                                                $globalCap = $preciosGlobalesActivos[$pRep->variante_id] ?? null;
                                                            @endphp
                                                            @if($globalCap)
                                                                <button type="button"
                                                                        @click="openConfirm(
                                                                            'Restaurar precio global',
                                                                            'Se aplicará S/ {{ number_format($globalCap->precio, 2) }} ({{ $cap ?: 'base' }}) en {{ $nombreTienda }}. ¿Continuar?',
                                                                            'fa-undo-alt', 'text-purple-500',
                                                                            'Sí, restaurar', 'bg-purple-600 hover:bg-purple-700',
                                                                            () => applyToSelected('restore_global', null, {{ json_encode($capIds) }})
                                                                        )"
                                                                        class="inline-flex items-center gap-1 px-2 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors"
                                                                        title="Restaurar precio global">
                                                                    <i class="fas fa-undo-alt"></i>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
                    <span class="text-xs text-gray-500">Mostrando {{ $preciosPorTienda->count() }} registro(s)</span>
                </div>
            @else
                <div class="py-10 text-center">
                    <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-store text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium text-sm">Sin precios por tienda</p>
                    <p class="text-gray-400 text-xs mt-1">
                        Activa "Replicar a todas las tiendas" al guardar un precio global para crear precios individuales por sucursal.
                    </p>
                </div>
            @endif
        </div>

        </div>
    </div>
</div>
</body>
</html>
