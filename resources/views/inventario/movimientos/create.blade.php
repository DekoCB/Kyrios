<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Movimiento - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

@php
$catalogoJson = $productos->map(fn($p) => [
    'id'              => $p->id,
    'codigo'          => $p->codigo,
    'nombre'          => \App\Support\EtiquetaProducto::nombre($p),
    'codigos'         => \App\Support\EtiquetaProducto::codigos(['codigo' => $p->codigo, 'variantes' => $p->variantesActivas]),
    // Todo lo que se puede teclear para encontrarlo: nombres y códigos del producto y de sus variantes
    'busqueda'        => mb_strtolower(implode(' ', array_filter(array_merge(
        [$p->nombre, $p->nombre_kyrios, $p->nombre_fabrica, $p->codigo],
        $p->variantesActivas->flatMap(fn ($v) => [$v->codigo_kyrios, $v->codigo_fabricante, $v->codigo_excel, $v->sku])->all()
    )))),
    'stock_actual'    => (int)($p->stock_actual ?? 0),
    'unidad'          => $p->unidadMedida?->abreviatura ?? 'UND',
    'variantes'       => $p->variantesActivas->map(fn ($v) => [
        'id'      => $v->id,
        'codigo'  => \App\Support\EtiquetaProducto::codigos($p, $v),
        'detalle' => $v->nombre_completo,
        'stock'   => $stockVariantes[$v->id] ?? [],
    ])->values(),
])->values();
@endphp

<div class="md:ml-64 p-4 md:p-8"
     x-data="movimientoForm({{ Js::from([
         'tipo' => old('tipo_movimiento', ''),
         'almacen' => (string) old('almacen_id', ''),
         'motivo' => (string) old('motivo_movimiento_id', ''),
         'catalogo' => $catalogoJson,
         'motivos' => $motivos,
         'lineas' => old('lineas', old('producto_id') ? [['producto_id' => old('producto_id'), 'variante_id' => old('variante_id'), 'cantidad' => old('cantidad', 1)]] : []),
     ]) }})"
     x-init="init()">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('inventario.movimientos.index') }}" class="hover:text-blue-700 transition-colors">Movimientos</a>
        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
        <span class="text-gray-800 font-medium">Nuevo Movimiento</span>
    </nav>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Registrar Movimiento</h1>
            <p class="text-sm text-gray-500 mt-0.5">Ingreso, salida, ajuste o transferencia de inventario</p>
        </div>
        <a href="{{ route('inventario.movimientos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    {{-- Alertas de validación --}}
    @if($errors->any())
    <div class="mb-5 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
        <div class="flex items-center gap-2 mb-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i>
            <span class="text-sm font-semibold">Corrige los siguientes errores:</span>
        </div>
        <ul class="list-disc list-inside space-y-0.5 text-sm">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        <i class="fas fa-times-circle text-red-500"></i> {{ session('error') }}
    </div>
    @endif

    <form action="{{ route('inventario.movimientos.store') }}" method="POST"
          @submit.prevent="if(!formularioValido){ errorGeneral='Completa todos los campos requeridos: tipo, almacén, motivo y cada producto con su variante y cantidad.'; return; } enviando=true; errorGeneral=''; $el.submit()">
        @csrf

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- ====== Columna izquierda: tipo + info ====== --}}
            <div class="space-y-5">

                {{-- Tipo de movimiento --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-linear-to-r from-blue-900 to-blue-700 px-5 py-3">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-list-ul"></i> Tipo de Movimiento
                        </h2>
                    </div>
                    <div class="p-4 grid grid-cols-2 gap-2">
                        @foreach([
                            ['ingreso',       'Ingreso',       'fa-arrow-circle-down', 'green',  'Entrada de stock'],
                            ['salida',        'Salida',        'fa-arrow-circle-up',   'red',    'Salida de stock'],
                            ['ajuste',        'Ajuste',        'fa-sliders-h',         'blue',   'Corrección manual'],
                            ['transferencia', 'Transferencia', 'fa-exchange-alt',      'purple', 'Entre almacenes'],
                            ['devolucion',    'Devolución',    'fa-undo',              'orange', 'Retorno de producto'],
                            ['merma',         'Merma',         'fa-exclamation-triangle','gray', 'Pérdida/deterioro'],
                        ] as [$val, $label, $icon, $color, $desc])
                        <label class="cursor-pointer">
                            <input type="radio" name="tipo_movimiento" value="{{ $val }}"
                                   x-model="tipoMovimiento"
                                   @change="onTipoChange()"
                                   class="peer hidden" {{ old('tipo_movimiento') === $val ? 'checked' : '' }}>
                            <div class="border-2 border-gray-200 rounded-xl p-3 text-center hover:border-{{ $color }}-400
                                        peer-checked:border-{{ $color }}-500 peer-checked:bg-{{ $color }}-50
                                        transition-all cursor-pointer select-none">
                                <i class="fas {{ $icon }} text-2xl text-{{ $color }}-500 mb-1"></i>
                                <p class="text-xs font-bold text-gray-800">{{ $label }}</p>
                                <p class="text-[10px] text-gray-400 leading-tight mt-0.5">{{ $desc }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Resumen de los productos del movimiento --}}
                <div x-show="lineasCompletas.length" x-cloak
                     class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-linear-to-r from-emerald-700 to-emerald-500 px-5 py-3">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-box"></i> Productos del movimiento
                        </h2>
                    </div>
                    <div class="p-5 space-y-2">
                        <template x-for="l in lineasCompletas" :key="l.id">
                            <div class="flex items-start justify-between gap-2 text-sm">
                                <span class="text-gray-800 min-w-0 truncate" x-text="l.nombre"></span>
                                <span class="font-bold text-emerald-700 shrink-0" x-text="'× ' + (parseInt(l.cantidad) || 0)"></span>
                            </div>
                        </template>
                        <div class="flex items-center justify-between text-sm pt-2 border-t border-gray-100">
                            <span class="text-gray-500" x-text="lineasCompletas.length + (lineasCompletas.length === 1 ? ' producto' : ' productos')"></span>
                            <span class="font-bold text-gray-900" x-text="unidadesTotal + ' unidades'"></span>
                        </div>
                    </div>
                </div>

                {{-- Nota importante --}}
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p class="text-xs font-semibold text-amber-800 mb-1.5 flex items-center gap-1">
                        <i class="fas fa-exclamation-triangle"></i> Importante
                    </p>
                    <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
                        <li>Los movimientos <strong>no se pueden eliminar</strong></li>
                        <li>Las transferencias requieren número de guía de remisión</li>
                    </ul>
                </div>

            </div>

            {{-- ====== Columna derecha: formulario ====== --}}
            <div class="xl:col-span-2">

                {{-- Panel de redirección para Transferencia --}}
                <div x-show="tipoMovimiento === 'transferencia'" x-cloak
                     class="bg-white rounded-xl shadow-sm border border-purple-200 overflow-hidden">
                    <div class="bg-linear-to-r from-purple-700 to-purple-500 px-5 py-3">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-exchange-alt"></i> Transferencia entre Almacenes
                        </h2>
                    </div>
                    <div class="p-10 flex flex-col items-center text-center gap-5">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-3xl text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-base font-semibold text-gray-800 mb-1">
                                Las transferencias se gestionan en el módulo de Traslados
                            </p>
                            <p class="text-sm text-gray-500 max-w-sm">
                                Desde ahí puedes trasladar múltiples productos a la vez y hacer seguimiento hasta la confirmación de recepción.
                            </p>
                        </div>
                        <a href="{{ route('traslados.create') }}"
                           class="inline-flex items-center gap-2 px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                            <i class="fas fa-arrow-right"></i> Ir a Nuevo Traslado
                        </a>
                        <a href="{{ route('traslados.index') }}"
                           class="text-xs text-gray-400 hover:text-purple-600 transition-colors">
                            Ver historial de traslados →
                        </a>
                    </div>
                </div>

                <div x-show="tipoMovimiento !== 'transferencia'" x-cloak
                     class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
                     style="display: block">
                <div class="bg-linear-to-r from-gray-700 to-gray-600 px-5 py-3">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-edit"></i> Datos del Movimiento
                        </h2>
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Productos: se busca escribiendo o se elige de la lista desplegable; se pueden agregar varios --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
                                Productos <span class="text-red-500">*</span>
                            </label>

                            <div class="space-y-3">
                                <template x-for="(l, idx) in lineas" :key="l.id">
                                    <div class="rounded-xl border border-gray-200 p-3 space-y-2" data-linea-movimiento>
                                        <div class="flex items-start gap-2">
                                            <div class="relative flex-1 min-w-0" @click.outside="l.abierto = false">
                                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                                <input type="text" x-model="l.query"
                                                       @input.debounce.150ms="buscar(l)"
                                                       @focus="if (!l.productoId && !l.query) listar(l)"
                                                       :readonly="!!l.productoId"
                                                       :class="l.productoId ? 'bg-gray-50 text-gray-500 cursor-default' : ''"
                                                       placeholder="Escribe o elige un producto…"
                                                       class="w-full pl-9 pr-16 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                                <div class="absolute right-1 top-0 h-full flex items-center">
                                                    <button x-show="l.productoId" type="button" @click="limpiar(l)" class="px-2 text-gray-400 hover:text-red-500" title="Cambiar producto"><i class="fas fa-times text-sm"></i></button>
                                                    <button x-show="!l.productoId" type="button" data-lista-productos @click="l.abierto ? l.abierto = false : listar(l)" class="px-3 h-full text-gray-500 hover:text-blue-600" title="Ver lista de productos"><i class="fas fa-chevron-down text-xs"></i></button>
                                                </div>

                                                <div x-show="l.abierto" x-cloak class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto">
                                                    <template x-for="p in l.resultados" :key="p.id">
                                                        <button type="button" @click="seleccionar(l, p)" class="w-full text-left px-4 py-2.5 hover:bg-blue-50 border-b border-gray-50 last:border-0 transition-colors">
                                                            <div class="flex items-center justify-between">
                                                                <div class="min-w-0">
                                                                    <p class="text-sm font-medium text-gray-900 truncate" x-text="p.nombre"></p>
                                                                    <p class="text-xs text-gray-400 font-mono truncate" x-text="p.codigos || p.codigo"></p>
                                                                </div>
                                                                <span class="text-xs font-semibold text-emerald-700 shrink-0 ml-3" x-text="'Stock: ' + p.stock_actual + ' ' + p.unidad"></span>
                                                            </div>
                                                        </button>
                                                    </template>
                                                    <p x-show="l.resultados.length === 0" class="px-4 py-3 text-center text-xs text-gray-400">Sin resultados</p>
                                                </div>
                                            </div>
                                            <button type="button" @click="quitar(idx)" :disabled="lineas.length === 1" class="mt-2 px-2 text-gray-400 hover:text-red-500 disabled:opacity-30" title="Quitar producto"><i class="fas fa-trash-can"></i></button>
                                        </div>

                                        <input type="hidden" :name="'lineas[' + idx + '][producto_id]'" :value="l.productoId">

                                        <div class="grid grid-cols-3 gap-2" x-show="l.productoId" x-cloak>
                                            <div class="col-span-2" x-show="l.variantes.length > 0">
                                                <label class="block text-[11px] text-gray-500 mb-1">Variante *</label>
                                                <select :name="'lineas[' + idx + '][variante_id]'" x-model="l.varianteId" :disabled="l.variantes.length === 0"
                                                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                                    <option value="">— Selecciona la variante —</option>
                                                    <template x-for="v in l.variantes" :key="v.id">
                                                        <option :value="v.id" :selected="v.id == l.varianteId"
                                                                x-text="v.codigo + ' · ' + v.detalle + (almacenId ? ' · stock aquí: ' + ((v.stock || {})[almacenId] || 0) : '')"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div :class="l.variantes.length === 0 ? 'col-span-3' : ''">
                                                <label class="block text-[11px] text-gray-500 mb-1">Cantidad *</label>
                                                <input type="number" :name="'lineas[' + idx + '][cantidad]'" x-model="l.cantidad" min="1"
                                                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <button type="button" @click="agregar()" class="mt-3 inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                                <i class="fas fa-plus-circle"></i> Agregar otro producto
                            </button>
                            @error('lineas') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error('lineas.*.producto_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error('lineas.*.cantidad') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Almacén --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                <span x-text="tipoMovimiento ? labelAlmacen : 'Almacén'"></span>
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="almacen_id" x-model="almacenId"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">— Selecciona un almacén —</option>
                                @if($almacenesCentral->isNotEmpty())
                                    <optgroup label="── Almacenes Centrales">
                                        @foreach($almacenesCentral as $alm)
                                            <option value="{{ $alm->id }}" {{ old('almacen_id') == $alm->id ? 'selected' : '' }}>
                                                {{ $alm->nombre }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($almacenesTienda->isNotEmpty())
                                    <optgroup label="── Almacenes de Tienda">
                                        @foreach($almacenesTienda as $alm)
                                            <option value="{{ $alm->id }}" {{ old('almacen_id') == $alm->id ? 'selected' : '' }}>
                                                {{ $alm->nombre }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            @error('almacen_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Motivo --}}
                        <div x-show="tipoMovimiento && tipoMovimiento !== 'transferencia'" x-cloak>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
                                Motivo <span class="text-red-500">*</span>
                            </label>
                            <template x-if="motivosFiltrados.length === 0">
                                <div class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2.5 rounded-xl">
                                    <i class="fas fa-exclamation-triangle shrink-0"></i>
                                    <span>Sin motivos para este tipo. <a href="{{ route('catalogo.motivos.create') }}" class="underline font-semibold">Crear motivo</a></span>
                                </div>
                            </template>
                            <div x-show="motivosFiltrados.length > 0" class="grid grid-cols-1 gap-2">
                                <template x-for="m in motivosFiltrados" :key="m.id">
                                    <label class="cursor-pointer block">
                                        <input type="radio" name="motivo_movimiento_id" :value="m.id"
                                               x-model="motivoId" class="peer hidden">
                                        <div class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl
                                                    peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-all">
                                            <i class="fas fa-tag text-gray-300 shrink-0"></i>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-gray-800" x-text="m.nombre"></p>
                                                <p class="text-xs text-gray-400 truncate" x-text="m.descripcion"></p>
                                            </div>
                                            <i x-show="motivoId == m.id" class="fas fa-check-circle text-blue-600 shrink-0"></i>
                                        </div>
                                    </label>
                                </template>
                            </div>
                            @error('motivo_movimiento_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Observaciones --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                Observaciones <span class="text-gray-400 normal-case font-normal">(opcional)</span>
                            </label>
                            <textarea name="observaciones" rows="2"
                                      placeholder="Información adicional..."
                                      class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('observaciones') }}</textarea>
                        </div>

                        {{-- Error general --}}
                        <p x-show="errorGeneral" x-text="errorGeneral" x-cloak
                           class="text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg"></p>

                        {{-- Botones --}}
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <a href="{{ route('inventario.movimientos.index') }}"
                               class="px-5 py-2.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </a>
                            <button type="submit"
                                    :disabled="enviando"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-900 text-white text-sm font-semibold rounded-xl hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                <i x-show="!enviando" class="fas fa-save"></i>
                                <i x-show="enviando" class="fas fa-spinner fa-spin"></i>
                                <span x-text="enviando ? 'Guardando...' : 'Registrar Movimiento'"></span>
                            </button>
                        </div>

                    </div>
                </div>
                </div>{{-- /x-show transferencia !== --}}
            </div>

        </div>
    </form>
</div>
<script>
function movimientoForm(c) {
    let contador = 0;
    const nueva = () => ({ id: ++contador, query: '', abierto: false, resultados: [], productoId: null, nombre: '', stock: 0, unidad: '', variantes: [], varianteId: '', cantidad: 1 });
    return {
        tipoMovimiento: c.tipo, almacenId: c.almacen, motivoId: c.motivo, catalogo: c.catalogo, motivos: c.motivos,
        lineas: [nueva()], enviando: false, errorGeneral: '',

        get motivosFiltrados() { return this.tipoMovimiento ? (this.motivos[this.tipoMovimiento] ?? []) : []; },
        get labelAlmacen() { return this.tipoMovimiento === 'ingreso' ? 'Almacén Destino' : 'Almacén Origen'; },
        get lineasCompletas() { return this.lineas.filter(l => l.productoId); },
        get unidadesTotal() { return this.lineasCompletas.reduce((a, l) => a + (parseInt(l.cantidad) || 0), 0); },
        get formularioValido() {
            if (!this.tipoMovimiento || !this.almacenId || !this.motivoId) return false;
            return this.lineas.length > 0 && this.lineas.every(l => l.productoId && (parseInt(l.cantidad) || 0) >= 1 && (l.variantes.length === 0 || l.varianteId));
        },

        buscar(l) {
            const q = (l.query || '').toLowerCase();
            if (q.length < 1) { l.resultados = []; l.abierto = false; return; }
            l.resultados = this.catalogo
                .filter(p => (p.busqueda || '').includes(q) || p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q) || (p.variantes || []).some(v => v.codigo.toLowerCase().includes(q)))
                .slice(0, 12);
            l.abierto = true;
        },
        /* Lista desplegable: todo el catálogo por orden alfabético, sin escribir */
        listar(l) {
            l.resultados = this.catalogo.slice().sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')).slice(0, 80);
            l.abierto = true;
        },
        seleccionar(l, p) {
            l.productoId = p.id; l.nombre = p.nombre; l.stock = p.stock_actual; l.unidad = p.unidad;
            l.variantes = p.variantes || [];
            l.varianteId = l.variantes.length === 1 ? l.variantes[0].id : '';
            l.query = p.nombre + ' [' + p.codigo + ']';
            l.abierto = false; l.resultados = [];
        },
        limpiar(l) {
            Object.assign(l, { productoId: null, nombre: '', stock: 0, unidad: '', variantes: [], varianteId: '', query: '', resultados: [], abierto: false });
        },
        agregar() { this.lineas.push(nueva()); },
        quitar(i) { if (this.lineas.length > 1) this.lineas.splice(i, 1); },
        onTipoChange() { this.motivoId = ''; },

        init() {
            /* Restaurar los productos si el formulario volvió con errores */
            const previas = (c.lineas || []).filter(x => x && x.producto_id);
            if (previas.length) {
                this.lineas = previas.map(x => {
                    const l = nueva();
                    const p = this.catalogo.find(k => k.id == x.producto_id);
                    if (p) { this.seleccionar(l, p); l.varianteId = x.variante_id || l.varianteId; }
                    l.cantidad = x.cantidad || 1;
                    return l;
                });
            }
        },
    };
}
</script>
</body>
</html>
