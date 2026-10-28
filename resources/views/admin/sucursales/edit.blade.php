<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editar Sucursal — {{ $sucursal->nombre }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="{ tab: '{{ session('_tab', 'info') }}' }">

    {{-- Header --}}
    <div class="mb-6">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-1">
            <a href="{{ route('admin.sucursales.index') }}" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 break-words min-w-0">{{ $sucursal->nombre }}</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $sucursal->codigo }}</span>
            @if($sucursal->esAlmacen())
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-200">
                    <i class="fas fa-warehouse mr-1"></i>Almacén
                </span>
            @else
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    <i class="fas fa-store mr-1"></i>Tienda
                </span>
            @endif
            @if($sucursal->es_principal)
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800"><i class="fas fa-star mr-1"></i>Principal</span>
            @endif
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $sucursal->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                {{ ucfirst($sucursal->estado) }}
            </span>
        </div>
        <p class="text-sm text-gray-500 ml-7">{{ $sucursal->direccion }}</p>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center gap-2">
            <i class="fas fa-check-circle"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i>{{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center gap-2 font-semibold mb-1"><i class="fas fa-exclamation-triangle"></i> Error de validación</div>
            <ul class="list-disc list-inside text-sm space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="flex overflow-x-auto -mb-px">
                @foreach([
                    ['key'=>'info',      'label'=>'Info Sucursal',        'icon'=>'store'],
                    ['key'=>'almacenes', 'label'=>'Almacenes',            'icon'=>'warehouse'],
                    ['key'=>'series',    'label'=>'Series / Correlativos','icon'=>'list-ol'],
                    ['key'=>'pagos',     'label'=>'Yape / Plin / Pagos',  'icon'=>'qrcode'],
                ] as $t)
                    <button @click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}' ? 'border-blue-600 text-blue-600 bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="fas fa-{{ $t['icon'] }}"></i> {{ $t['label'] }}
                    </button>
                @endforeach
                <a href="{{ route('admin.sucursales.comprobantes', $sucursal) }}"
                    class="flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">
                    <i class="fas fa-file-invoice"></i> Comprobantes Emitidos
                </a>
            </nav>
        </div>

        {{-- ─── TAB: INFO ────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'info'" x-cloak class="p-6">
            <form action="{{ route('admin.sucursales.update', $sucursal) }}" method="POST">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Tipo (solo lectura, definido al crear) --}}
                    <div class="md:col-span-2">
                        @if($sucursal->esAlmacen())
                            <div class="flex items-center justify-between gap-3 p-4 rounded-xl border-2 border-orange-400 bg-orange-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-orange-500 flex items-center justify-center shrink-0">
                                        <i class="fas fa-warehouse text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-orange-800 text-sm">Almacén / Depósito</p>
                                        <p class="text-xs text-orange-600">Establecimiento anexo SUNAT. Gestiona stock y genera traslados internos.</p>
                                    </div>
                                </div>
                                <a href="{{ route('traslados.create') }}"
                                    class="shrink-0 flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-truck-moving"></i> Generar Traslado
                                </a>
                            </div>
                        @else
                            <div class="flex items-center gap-3 p-4 rounded-xl border-2 border-blue-400 bg-blue-50">
                                <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center shrink-0">
                                    <i class="fas fa-store text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-blue-800 text-sm">Tienda / Punto de Venta</p>
                                    <p class="text-xs text-blue-600">Gestiona su propio stock y emite comprobantes de pago.</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" name="nombre" value="{{ old('nombre', $sucursal->nombre) }}" maxlength="150" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion', $sucursal->direccion) }}" maxlength="300"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" name="departamento" value="{{ old('departamento', $sucursal->departamento) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
                        <input type="text" name="provincia" value="{{ old('provincia', $sucursal->provincia) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
                        <input type="text" name="distrito" value="{{ old('distrito', $sucursal->distrito) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
                        <input type="text" name="ubigeo" value="{{ old('ubigeo', $sucursal->ubigeo) }}" maxlength="6"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                        <input type="text" name="telefono" value="{{ old('telefono', $sucursal->telefono) }}" maxlength="20"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $sucursal->email) }}" maxlength="150"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Almacén Principal</label>
                        @if($sucursal->almacen)
                            <div class="flex items-center gap-2 px-3 py-2 bg-orange-50 border border-orange-200 rounded-lg">
                                <i class="fas fa-warehouse text-orange-500 text-sm"></i>
                                <span class="text-sm text-orange-800 font-medium">{{ $sucursal->almacen->nombre }}</span>
                                <span class="ml-auto text-xs text-orange-500 bg-orange-100 px-2 py-0.5 rounded-full">{{ $sucursal->almacen->codigo }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Generado automáticamente al crear la sucursal.</p>
                        @else
                            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-400 italic">
                                Sin almacén vinculado
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="activo" {{ old('estado', $sucursal->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado', $sucursal->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="es_principal" id="es_principal" value="1"
                            {{ old('es_principal', $sucursal->es_principal) ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="es_principal" class="text-sm font-medium text-gray-700">Sucursal Principal</label>
                    </div>
                </div>
                <div class="flex justify-end mt-6 pt-4 border-t">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Guardar Info
                    </button>
                </div>
            </form>
        </div>

        {{-- ─── TAB: ALMACENES ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'almacenes'" x-cloak class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-warehouse text-orange-500"></i> Almacenes de esta Sucursal
                </h3>
            </div>

            {{-- Listado de almacenes vinculados --}}
            @php $todosAlmacenes = $sucursal->almacenes; @endphp
            @if($todosAlmacenes->isEmpty())
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-warehouse text-4xl mb-3 block"></i>
                    No hay almacenes vinculados.
                </div>
            @else
                <div class="space-y-2 mb-6">
                    @foreach($todosAlmacenes as $alm)
                        <div class="flex items-center gap-3 px-4 py-3 border rounded-lg {{ $alm->estado === 'activo' ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-60' }}">
                            <div class="w-8 h-8 rounded-lg {{ $alm->tipo === 'tienda' ? 'bg-blue-100' : 'bg-orange-100' }} flex items-center justify-center shrink-0">
                                <i class="fas {{ $alm->tipo === 'tienda' ? 'fa-store text-blue-600' : 'fa-warehouse text-orange-600' }} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800">{{ $alm->nombre }}</p>
                                <p class="text-xs text-gray-400">{{ $alm->codigo }} &middot; {{ $alm->tipo_label }}</p>
                            </div>
                            @if($alm->id === $sucursal->almacen_id)
                                <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full font-medium">Principal</span>
                            @else
                                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Secundario</span>
                            @endif
                            <span class="text-xs {{ $alm->estado === 'activo' ? 'text-green-700 bg-green-100' : 'text-gray-500 bg-gray-200' }} px-2 py-0.5 rounded-full">
                                {{ ucfirst($alm->estado) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Formulario para agregar almacén secundario --}}
            <div class="border-t pt-5">
                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-orange-500"></i> Agregar Almacén Secundario
                </h4>
                <form action="{{ route('admin.sucursales.almacenes.store', $sucursal) }}" method="POST">
                    @csrf
                    <div class="flex gap-3 flex-wrap">
                        <div class="flex-1 min-w-48">
                            <input type="text" name="nombre" placeholder="Nombre del almacén" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div class="flex-1 min-w-48">
                            <input type="text" name="direccion" placeholder="Dirección (opcional)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <button type="submit"
                            class="bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors flex items-center gap-2 shrink-0">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">El almacén secundario se crea como depósito vinculado a esta sucursal.</p>
                </form>
            </div>
        </div>

        {{-- ─── TAB: SERIES ──────────────────────────────────────────────────── --}}
        <div x-show="tab === 'series'" x-cloak class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list-ol text-blue-600"></i> Series de Comprobantes
                </h3>
                <form action="{{ route('admin.sucursales.generar-series', $sucursal) }}" method="POST">
                    @csrf
                    <button type="submit"
                        data-confirm="¿Generar las series estándar faltantes (FA, BA, FC, FD, T, CO)?"
                        class="flex items-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-2 rounded-lg transition-colors border border-indigo-200">
                        <i class="fas fa-magic"></i> Generar series estándar
                    </button>
                </form>
            </div>

            @if($sucursal->series->isEmpty())
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-list-ol text-4xl mb-3 block"></i>
                    No hay series configuradas.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($sucursal->series->sortBy('tipo_comprobante') as $serie)
                        <div class="border rounded-xl overflow-hidden {{ $serie->activo ? 'border-gray-200' : 'border-gray-100 opacity-60' }}">
                            {{-- Cabecera de la serie --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-800">{{ $serie->tipo_nombre }}</span>
                                    <span class="text-xs text-gray-400 font-mono bg-gray-200 px-1.5 py-0.5 rounded">{{ $serie->tipo_comprobante }}</span>
                                    <span class="text-xs font-mono font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded">{{ $serie->serie }}</span>
                                </div>
                                {{-- Toggle activo --}}
                                <form action="{{ route('admin.sucursales.series.update', [$sucursal, $serie]) }}" method="POST">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="serie" value="{{ $serie->serie }}">
                                    <input type="hidden" name="correlativo_actual" value="{{ $serie->correlativo_actual }}">
                                    <input type="hidden" name="formato_impresion" value="{{ $serie->formato_impresion }}">
                                    <input type="hidden" name="activo" value="{{ $serie->activo ? '0' : '1' }}">
                                    <button type="submit"
                                        title="{{ $serie->activo ? 'Desactivar' : 'Activar' }}"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $serie->activo ? 'bg-green-500' : 'bg-gray-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $serie->activo ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                    </button>
                                </form>
                            </div>
                            {{-- Formulario de edición --}}
                            <form action="{{ route('admin.sucursales.series.update', [$sucursal, $serie]) }}" method="POST"
                                class="flex flex-wrap items-end gap-4 p-4">
                                @csrf @method('PUT')
                                <input type="hidden" name="activo" value="{{ $serie->activo ? '1' : '0' }}">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Serie</label>
                                    <input type="text" name="serie" value="{{ $serie->serie }}" maxlength="5"
                                        class="w-20 border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono uppercase focus:ring-2 focus:ring-blue-500 text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Correlativo Actual</label>
                                    <input type="number" name="correlativo_actual" value="{{ $serie->correlativo_actual }}" min="1"
                                        class="w-28 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Formato Impresión</label>
                                    <select name="formato_impresion"
                                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                                        <option value="A4" {{ $serie->formato_impresion === 'A4' ? 'selected' : '' }}>A4</option>
                                        <option value="ticket" {{ $serie->formato_impresion === 'ticket' ? 'selected' : '' }}>Ticket 80mm</option>
                                        <option value="A5" {{ $serie->formato_impresion === 'A5' ? 'selected' : '' }}>A5</option>
                                    </select>
                                </div>
                                <div class="ml-auto">
                                    <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                                        <i class="fas fa-save text-xs"></i> Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── TAB: PAGOS ───────────────────────────────────────────────────── --}}
        <div x-show="tab === 'pagos'" x-cloak class="p-6">
            <form action="{{ route('admin.sucursales.pagos.update', $sucursal) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @php
                        $metodosConfig = [
                            'yape'          => ['color'=>'purple', 'icon'=>'mobile-alt',  'label'=>'Yape',                'qr'=>true],
                            'plin'          => ['color'=>'green',  'icon'=>'mobile-alt',  'label'=>'Plin',                'qr'=>true],
                            'transferencia' => ['color'=>'blue',   'icon'=>'university',  'label'=>'Transferencia Bancaria','qr'=>false],
                            'pos'           => ['color'=>'orange', 'icon'=>'credit-card', 'label'=>'POS / Tarjeta',       'qr'=>false],
                        ];
                    @endphp
                    @foreach($metodosConfig as $tipo => $cfg)
                        @php $pago = $pagosIndexed->get($tipo); @endphp
                        <div x-data="{ activo: {{ ($pago && $pago->activo) ? 'true' : 'false' }} }"
                            class="border rounded-xl overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-{{ $cfg['icon'] }} text-{{ $cfg['color'] }}-600"></i>
                                    <span class="font-semibold text-gray-800">{{ $cfg['label'] }}</span>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="pagos[{{ $tipo }}][activo]" value="1"
                                        {{ ($pago && $pago->activo) ? 'checked' : '' }}
                                        x-model="activo"
                                        class="w-4 h-4 text-{{ $cfg['color'] }}-600 rounded border-gray-300 focus:ring-{{ $cfg['color'] }}-500">
                                    <span class="text-xs font-medium text-gray-600">Activar</span>
                                </label>
                            </div>
                            <div x-show="activo" class="p-4 space-y-3">
                                <input type="hidden" name="pagos[{{ $tipo }}][tipo_pago]" value="{{ $tipo }}">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Titular / Nombre</label>
                                    <input type="text" name="pagos[{{ $tipo }}][titular]"
                                        value="{{ old("pagos.{$tipo}.titular", $pago?->titular) }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-{{ $cfg['color'] }}-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        {{ in_array($tipo, ['yape','plin']) ? 'Número de Celular' : 'Número de Cuenta' }}
                                    </label>
                                    <input type="text" name="pagos[{{ $tipo }}][numero]"
                                        value="{{ old("pagos.{$tipo}.numero", $pago?->numero) }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-{{ $cfg['color'] }}-500">
                                </div>
                                @if($tipo === 'transferencia')
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Banco</label>
                                        <input type="text" name="pagos[{{ $tipo }}][banco]"
                                            value="{{ old("pagos.{$tipo}.banco", $pago?->banco) }}"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">CCI (Código Interbancario)</label>
                                        <input type="text" name="pagos[{{ $tipo }}][cci]"
                                            value="{{ old("pagos.{$tipo}.cci", $pago?->cci) }}"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                @endif
                                @if($cfg['qr'])
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Código QR</label>
                                        @if($pago && $pago->qr_imagen_path)
                                            <div class="mb-2">
                                                <img src="{{ $pago->qr_url }}" alt="QR {{ $cfg['label'] }}"
                                                    class="w-28 h-28 object-contain border rounded-lg p-1 bg-white shadow-sm">
                                            </div>
                                        @endif
                                        <input type="file" name="pagos[{{ $tipo }}][qr]" accept="image/*"
                                            class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-{{ $cfg['color'] }}-50 file:text-{{ $cfg['color'] }}-700 hover:file:bg-{{ $cfg['color'] }}-100">
                                    </div>
                                @endif
                            </div>
                            <div x-show="!activo" class="px-4 py-6 text-center text-gray-400 text-sm">
                                <i class="fas fa-toggle-off text-xl mb-1 block"></i>
                                Método desactivado
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end mt-6 pt-4 border-t">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Guardar Pagos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>
</body>
</html>
