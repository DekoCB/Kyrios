<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comisiones & Bonos</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="comisionesApp()">

    <x-header title="Comisiones & Bonos" subtitle="Configuración maestra de reglas de comisión y bonos por producto / categoría" />

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <i class="fas fa-percentage text-blue-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400">Comisiones pendientes</p>
                <p class="text-lg font-bold text-gray-800">S/ {{ number_format($totalComisionPendiente, 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <i class="fas fa-star text-amber-500"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400">Bonos pendientes</p>
                <p class="text-lg font-bold text-gray-800">S/ {{ number_format($totalBonusPendiente, 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                <i class="fas fa-list-check text-purple-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400">Reglas de comisión</p>
                <p class="text-lg font-bold text-gray-800">{{ $reglas->count() }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                <i class="fas fa-gift text-emerald-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400">Reglas de bono</p>
                <p class="text-lg font-bold text-gray-800">{{ $bonusReglas->count() }}</p>
            </div>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-5 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 mb-5 bg-white border border-gray-200 rounded-xl p-1 w-fit shadow-sm">
        <button @click="tab='comisiones'"
                :class="tab==='comisiones' ? 'bg-blue-700 text-white shadow' : 'text-gray-500 hover:text-blue-700'"
                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
            <i class="fas fa-percentage"></i> Comisiones
        </button>
        <button @click="tab='bonos'"
                :class="tab==='bonos' ? 'bg-amber-500 text-white shadow' : 'text-gray-500 hover:text-amber-600'"
                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
            <i class="fas fa-star"></i> Bonos
        </button>
    </div>

    {{-- Ayuda: cuándo usar Comisión vs Bono, cambia según la pestaña activa --}}
    <div x-show="tab==='comisiones'" x-cloak
         class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-800 flex gap-2.5">
        <i class="fas fa-lightbulb shrink-0 mt-0.5 text-blue-500"></i>
        <div>
            <p class="font-semibold mb-0.5">¿Cuándo usar Comisión?</p>
            <p>Para productos de <strong>buen valor unitario, como celulares</strong>: es un pago que se calcula automáticamente en cada venta (ej. "S/ 15 fijos por equipo" o "3% del margen"). Si vendes 1 celular, se genera 1 comisión.</p>
        </div>
    </div>
    <div x-show="tab==='bonos'" x-cloak
         class="mb-5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800 flex gap-2.5">
        <i class="fas fa-lightbulb shrink-0 mt-0.5 text-amber-500"></i>
        <div>
            <p class="font-semibold mb-0.5">¿Cuándo usar Bono?</p>
            <p>Para <strong>accesorios de bajo valor, como fundas y cargadores</strong>: pagarles centavos por unidad no motiva a nadie. En su lugar, usa <strong>"Bono por Meta"</strong> para premiar el volumen (ej. "vende 100 fundas este mes y gana S/ 80 extra"), o <strong>"Bono Fijo"</strong> para sumar un monto extra por unidad de un producto puntual que quieras liquidar rápido. El bono se suma aparte, encima de la comisión si la hubiera.</p>
        </div>
    </div>

    {{-- ══════════════════ TAB COMISIONES ══════════════════ --}}
    <div x-show="tab==='comisiones'" x-cloak>
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="bg-linear-to-r from-blue-900 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-percentage text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Reglas de Comisión</h2>
                        <p class="text-blue-200 text-xs">% sobre venta, % sobre margen de ganancia o monto fijo</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('comisiones.reporte') }}"
                       class="px-3 py-2 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition flex items-center gap-1.5">
                        <i class="fas fa-chart-bar"></i> Reporte
                    </a>
                    <form action="{{ route('comisiones.recalcular') }}" method="POST"
                          onsubmit="return confirm('Esto revisará todas las ventas ya pagadas y generará la comisión de las que todavía no tengan una (por ejemplo, ventas hechas antes de crear una regla nueva). No duplica ni modifica comisiones ya existentes. ¿Continuar?')">
                        @csrf
                        <button type="submit" title="Aplica las reglas activas a ventas antiguas que aún no tienen comisión calculada"
                                class="px-3 py-2 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition flex items-center gap-1.5">
                            <i class="fas fa-rotate"></i> Recalcular
                        </button>
                    </form>
                    <button @click="modalComision=true"
                            class="px-3 py-2 bg-white text-blue-800 hover:bg-blue-50 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                        <i class="fas fa-plus"></i> Nueva regla
                    </button>
                </div>
            </div>

            @if($reglas->isEmpty())
                <div class="py-12 text-center text-gray-400">
                    <i class="fas fa-percentage text-4xl mb-3 block opacity-30"></i>
                    <p class="text-sm">No hay reglas configuradas. Crea la primera.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Destino</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cálculo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Activo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($reglas as $regla)
                        <tr class="hover:bg-gray-50 transition-colors {{ $regla->activo ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $regla->nombre }}</td>
                            <td class="px-4 py-3">
                                @php $css = match($regla->tipo_aplicacion) {
                                    'usuario'          => 'bg-purple-100 text-purple-700',
                                    'categoria'        => 'bg-blue-100 text-blue-700',
                                    'producto'         => 'bg-orange-100 text-orange-700',
                                    'producto_usuario' => 'bg-pink-100 text-pink-700',
                                    'categoria_usuario'=> 'bg-teal-100 text-teal-700',
                                    default            => 'bg-gray-100 text-gray-600',
                                }; @endphp
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $css }}">
                                    {{ $regla->tipo_aplicacion_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs max-w-[160px] truncate">
                                @if($regla->tipo_aplicacion === 'producto_usuario')
                                    {{ $regla->producto?->nombre }} — {{ $regla->usuario?->name }}
                                @elseif($regla->tipo_aplicacion === 'categoria_usuario')
                                    {{ $regla->categoria?->nombre }} — {{ $regla->usuario?->name }}
                                @else
                                    {{ $regla->usuario?->name ?? $regla->categoria?->nombre ?? $regla->producto?->nombre ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($regla->tipo_calculo === 'porcentaje_margen')
                                    <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">
                                        <i class="fas fa-chart-line text-[10px]"></i> % sobre margen
                                    </span>
                                    @if($regla->producto)
                                        @php
                                            $precio = (float)($regla->producto->precios->first()?->precio ?? 0);
                                            $costo  = (float)($regla->producto->costo_promedio ?? 0);
                                            $margen = $precio - $costo;
                                            $pct    = $precio > 0 ? round($margen / $precio * 100, 1) : 0;
                                        @endphp
                                        <p class="mt-0.5 {{ $margen >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                            Gan: S/ {{ number_format($margen, 2) }} ({{ $pct }}%)
                                        </p>
                                    @endif
                                @elseif($regla->tipo_calculo === 'porcentaje')
                                    <span class="text-blue-600">% sobre venta</span>
                                @else
                                    <span class="text-gray-600">Monto fijo / u.</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-gray-800">{{ $regla->valor_formateado }}</td>
                            <td class="px-4 py-3 text-center">
                                <form action="{{ route('comisiones.toggle', $regla) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" title="{{ $regla->activo ? 'Desactivar' : 'Activar' }}"
                                            class="w-10 h-5 rounded-full transition-colors relative {{ $regla->activo ? 'bg-blue-500' : 'bg-gray-300' }}">
                                        <span class="absolute top-0.5 {{ $regla->activo ? 'right-0.5' : 'left-0.5' }} w-4 h-4 bg-white rounded-full shadow transition-all"></span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="abrirEditarComision({{ $regla->id }}, '{{ addslashes($regla->nombre) }}', '{{ $regla->tipo_calculo }}', {{ $regla->valor }})"
                                            class="text-blue-600 hover:text-blue-800 text-xs p-1.5 rounded-lg hover:bg-blue-50 transition">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="{{ route('comisiones.destroy', $regla) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar esta regla?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs p-1.5 rounded-lg hover:bg-red-50 transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-700 flex gap-2">
            <i class="fas fa-info-circle shrink-0 mt-0.5"></i>
            <span><strong>Prioridad:</strong> Producto+Vendedor › Producto › Categoría+Vendedor › Categoría › Usuario. Se usa siempre la regla más específica que aplique a la venta. La comisión <strong>sobre margen</strong> usa el costo promedio del producto.</span>
        </div>
    </div>

    {{-- ══════════════════ TAB BONOS ══════════════════ --}}
    <div x-show="tab==='bonos'" x-cloak>
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="bg-linear-to-r from-amber-600 to-amber-400 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-star text-white text-xl"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-white">Reglas de Bono</h2>
                        <p class="text-amber-100 text-xs">Bonos fijos por venta o bonos por meta de unidades en el período</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('comisiones.progreso-bonos') }}"
                       class="px-3 py-2 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition flex items-center gap-1.5">
                        <i class="fas fa-trophy"></i> Progreso de metas
                    </a>
                    <button @click="modalBonus=true"
                            class="px-3 py-2 bg-white text-amber-700 hover:bg-amber-50 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                        <i class="fas fa-plus"></i> Nuevo bono
                    </button>
                </div>
            </div>

            @if($bonusReglas->isEmpty())
                <div class="py-12 text-center text-gray-400">
                    <i class="fas fa-star text-4xl mb-3 block opacity-30"></i>
                    <p class="text-sm">No hay reglas de bono configuradas.</p>
                    <p class="text-xs mt-1">Los bonos son incentivos adicionales a la comisión base.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Aplica a</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Destino</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Meta</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Activo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($bonusReglas as $bonus)
                        <tr class="hover:bg-gray-50 transition-colors {{ $bonus->activo ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $bonus->nombre }}</td>
                            <td class="px-4 py-3">
                                @php $bonusCss = match($bonus->tipo_aplicacion) {
                                    'producto'         => 'bg-orange-100 text-orange-700',
                                    'producto_usuario' => 'bg-pink-100 text-pink-700',
                                    default            => 'bg-blue-100 text-blue-700',
                                }; @endphp
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $bonusCss }}">
                                    {{ $bonus->tipo_aplicacion_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs max-w-[150px] truncate">
                                @if($bonus->tipo_aplicacion === 'producto_usuario')
                                    {{ $bonus->producto?->nombre }} — {{ $bonus->usuario?->name }}
                                @else
                                    {{ $bonus->producto?->nombre ?? $bonus->categoria?->nombre ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($bonus->tipo_bonus === 'fijo')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                        <i class="fas fa-bolt text-[8px]"></i> Fijo
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700">
                                        <i class="fas fa-trophy text-[8px]"></i> Meta
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-gray-800">{{ $bonus->valor_formateado }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if($bonus->tipo_bonus === 'meta')
                                    <span class="text-purple-700 font-medium">{{ $bonus->descripcion_meta }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <form action="{{ route('comisiones.bonus.toggle', $bonus) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" title="{{ $bonus->activo ? 'Desactivar' : 'Activar' }}"
                                            class="w-10 h-5 rounded-full transition-colors relative {{ $bonus->activo ? 'bg-amber-500' : 'bg-gray-300' }}">
                                        <span class="absolute top-0.5 {{ $bonus->activo ? 'right-0.5' : 'left-0.5' }} w-4 h-4 bg-white rounded-full shadow transition-all"></span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="abrirEditarBonus({{ $bonus->id }}, '{{ addslashes($bonus->nombre) }}', '{{ $bonus->tipo_calculo }}', {{ $bonus->valor }}, {{ $bonus->meta_unidades ?? 'null' }}, '{{ $bonus->meta_periodo ?? 'mensual' }}')"
                                            class="text-amber-600 hover:text-amber-800 text-xs p-1.5 rounded-lg hover:bg-amber-50 transition">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="{{ route('comisiones.bonus.destroy', $bonus) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar este bono?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs p-1.5 rounded-lg hover:bg-red-50 transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-xs text-green-700 flex gap-2">
                <i class="fas fa-bolt shrink-0 mt-0.5 text-green-500"></i>
                <div><p class="font-semibold mb-0.5">Bono Fijo</p>Se suma automáticamente por cada unidad vendida del producto/categoría. Se acumula con la comisión base.</div>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 text-xs text-purple-700 flex gap-2">
                <i class="fas fa-trophy shrink-0 mt-0.5 text-purple-500"></i>
                <div><p class="font-semibold mb-0.5">Bono por Meta</p>Se genera una sola vez cuando el vendedor supera las unidades mínimas en el período configurado.</div>
            </div>
        </div>
    </div>

    {{-- ═══════════ MODAL: Nueva / Editar Comisión ═══════════ --}}
    <div x-show="modalComision" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="cerrarModalComision()">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-linear-to-r from-blue-900 to-blue-700 px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-percentage"></i>
                    <span x-text="editComisionId ? 'Editar regla de comisión' : 'Nueva regla de comisión'"></span>
                </h3>
                <button @click="cerrarModalComision()" class="text-blue-200 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            {{-- Crear --}}
            <form x-show="!editComisionId" action="{{ route('comisiones.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Nombre de la regla *</label>
                    <input type="text" name="nombre" required maxlength="100"
                           placeholder="Ej: Comisión iPhone 16, Comisión Celulares..."
                           class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Aplica a *</label>
                        <select name="tipo_aplicacion" x-model="tipoAplicacion"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="usuario">Vendedor específico</option>
                            <option value="categoria">Categoría</option>
                            <option value="producto">Producto específico</option>
                            <option value="producto_usuario">Producto + Vendedor específico</option>
                            <option value="categoria_usuario">Categoría + Vendedor específico</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                            <span x-text="tipoAplicacion==='usuario'?'Vendedor':(tipoAplicacion==='categoria'||tipoAplicacion==='categoria_usuario')?'Categoría':'Producto'"></span> *
                        </label>
                        <select x-show="tipoAplicacion==='usuario'" name="user_id"
                                x-model="vendedorSeleccionado"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($vendedores as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                        </select>
                        <select x-show="tipoAplicacion==='categoria' || tipoAplicacion==='categoria_usuario'" name="categoria_id"
                                x-model="categoriaSeleccionada"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($categorias as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                        </select>
                        <select x-show="tipoAplicacion==='producto' || tipoAplicacion==='producto_usuario'" name="producto_id"
                                x-model="productoSeleccionado"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($productos as $p)<option value="{{ $p->id }}">{{ $p->nombre }}{{ $p->codigo?' ('.$p->codigo.')':'' }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div x-show="tipoAplicacion==='producto_usuario'" x-cloak>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Vendedor *</label>
                    <select name="user_id_producto"
                            x-model="vendedorProductoSeleccionado"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">— Seleccione —</option>
                        @foreach($vendedores as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                <div x-show="tipoAplicacion==='categoria_usuario'" x-cloak>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Vendedor *</label>
                    <select name="user_id_categoria"
                            x-model="vendedorCategoriaSeleccionado"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">— Seleccione —</option>
                        @foreach($vendedores as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Tipo de cálculo *</label>
                        <select name="tipo_calculo" x-model="tipoCalculo"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="porcentaje">% sobre precio de venta</option>
                            <option value="porcentaje_margen">% sobre margen de ganancia</option>
                            <option value="monto_fijo">Monto fijo por unidad (S/)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                            Valor <span x-text="tipoCalculo==='monto_fijo'?'(S/)':'(%)'"></span> *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-400 text-sm" x-text="tipoCalculo==='monto_fijo'?'S/':'%'"></span>
                            <input type="number" name="valor" step="0.01" min="0.01" required
                                   x-model="valorComision"
                                   class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                {{-- Vista previa dinámica: explica en palabras simples qué hará la regla --}}
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2.5 text-xs text-indigo-800 flex gap-2">
                    <i class="fas fa-wand-magic-sparkles shrink-0 mt-0.5 text-indigo-500"></i>
                    <div>
                        <p class="font-semibold mb-0.5">Así se registrará esta regla:</p>
                        <p x-text="previewComision"></p>
                    </div>
                </div>
                <div class="text-[11px] text-gray-500 flex gap-1.5 items-start">
                    <i class="fas fa-circle-info shrink-0 mt-0.5"></i>
                    <span>Si un producto tiene varias reglas que le aplican, gana la más específica: <strong>Producto+Vendedor › Producto › Categoría+Vendedor › Categoría › Vendedor</strong>. La regla solo afecta ventas nuevas, no las ya registradas.</span>
                </div>

                <div x-show="tipoCalculo==='porcentaje_margen'" x-cloak
                     class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2.5 text-xs text-emerald-700 space-y-1.5">
                    <p class="flex gap-2 items-start">
                        <i class="fas fa-info-circle shrink-0 mt-0.5"></i>
                        Comisión calculada sobre: precio de venta − costo promedio del producto (máximo 0 si hay pérdida).
                    </p>
                    <template x-if="margenProducto">
                        <div class="border-t border-emerald-200 pt-1.5 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-[10px] text-emerald-500 uppercase font-semibold">Precio venta</p>
                                <p class="font-bold text-emerald-800" x-text="'S/ ' + margenProducto.precio.toFixed(2)"></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-emerald-500 uppercase font-semibold">Costo promedio</p>
                                <p class="font-bold text-emerald-800" x-text="'S/ ' + margenProducto.costo.toFixed(2)"></p>
                            </div>
                            <div :class="margenProducto.margen >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                <p class="text-[10px] uppercase font-semibold opacity-75">Ganancia real</p>
                                <p class="font-bold" x-text="'S/ ' + margenProducto.margen.toFixed(2) + ' (' + margenProducto.pct.toFixed(1) + '%)'"></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="tipoAplicacion === 'producto' && !productoSeleccionado">
                        <p class="text-emerald-500 italic">Selecciona un producto para ver su ganancia real.</p>
                    </template>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="cerrarModalComision()"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                            class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg flex items-center gap-2">
                        <i class="fas fa-save"></i> Guardar regla
                    </button>
                </div>
            </form>

            {{-- Editar comisión --}}
            <template x-if="editComisionId">
                <form :action="`{{ url('comisiones') }}/${editComisionId}`" method="POST" class="p-6 space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Nombre *</label>
                        <input type="text" name="nombre" :value="editComisionNombre" required maxlength="100"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Tipo de cálculo *</label>
                            <select name="tipo_calculo" x-model="editTipoCalculo"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="porcentaje">% sobre precio de venta</option>
                                <option value="porcentaje_margen">% sobre margen de ganancia</option>
                                <option value="monto_fijo">Monto fijo por unidad (S/)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                                Valor <span x-text="editTipoCalculo==='monto_fijo'?'(S/)':'(%)'"></span> *
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400 text-sm" x-text="editTipoCalculo==='monto_fijo'?'S/':'%'"></span>
                                <input type="number" name="valor" step="0.01" min="0.01" required :value="editComisionValor"
                                       class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="cerrarModalComision()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                        <button type="submit"
                                class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg flex items-center gap-2">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    {{-- ═══════════ MODAL: Nuevo / Editar Bono ═══════════ --}}
    <div x-show="modalBonus" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="cerrarModalBonus()">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-linear-to-r from-amber-600 to-amber-400 px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-star"></i>
                    <span x-text="editBonusId ? 'Editar bono' : 'Nuevo bono'"></span>
                </h3>
                <button @click="cerrarModalBonus()" class="text-amber-100 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            {{-- Crear bono --}}
            <form x-show="!editBonusId" action="{{ route('comisiones.bonus.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Nombre del bono *</label>
                    <input type="text" name="nombre" required maxlength="100"
                           placeholder="Ej: Bono Samsung Galaxy, Bono categoría Celulares..."
                           class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Aplica a *</label>
                        <select name="tipo_aplicacion" x-model="bonusTipoAplicacion"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="producto">Producto específico</option>
                            <option value="categoria">Categoría</option>
                            <option value="producto_usuario">Producto + Vendedor específico</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                            <span x-text="bonusTipoAplicacion==='categoria'?'Categoría':'Producto'"></span> *
                        </label>
                        <select x-show="bonusTipoAplicacion==='producto' || bonusTipoAplicacion==='producto_usuario'" name="producto_id"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($productos as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                        </select>
                        <select x-show="bonusTipoAplicacion==='categoria'" name="categoria_id"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="">— Seleccione —</option>
                            @foreach($categorias as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div x-show="bonusTipoAplicacion==='producto_usuario'" x-cloak>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Vendedor *</label>
                    <select name="user_id_producto"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="">— Seleccione —</option>
                        @foreach($vendedores as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Tipo de bono *</label>
                        <select name="tipo_bonus" x-model="bonusTipo"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="fijo">Fijo (por cada venta)</option>
                            <option value="meta">Por meta de unidades</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Cálculo del bono *</label>
                        <select name="tipo_calculo" x-model="bonusCalculo"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="monto_fijo">Monto fijo (S/)</option>
                            <option value="porcentaje_venta">% sobre la venta</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                        Valor <span x-text="bonusCalculo==='monto_fijo'?'(S/)':'(%)'"></span> *
                    </label>
                    <div class="relative w-48">
                        <span class="absolute left-3 top-2.5 text-gray-400 text-sm" x-text="bonusCalculo==='monto_fijo'?'S/':'%'"></span>
                        <input type="number" name="valor" step="0.01" min="0.01" required
                               class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>
                {{-- Meta --}}
                <div x-show="bonusTipo==='meta'" x-cloak class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                            <i class="fas fa-trophy text-purple-500 mr-1"></i>Unidades mínimas *
                        </label>
                        <input type="number" name="meta_unidades" min="1" step="1" placeholder="Ej: 5"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Período de evaluación</label>
                        <select name="meta_periodo"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 bg-white">
                            <option value="mensual">Mensual</option>
                            <option value="quincenal">Quincenal</option>
                            <option value="semanal">Semanal</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-purple-700 bg-purple-50 border border-purple-200 rounded-lg px-3 py-2.5">
                            <i class="fas fa-info-circle mr-1"></i>El bono se genera una sola vez cuando el vendedor cruza el umbral de unidades en el período.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="cerrarModalBonus()"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                            class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg flex items-center gap-2">
                        <i class="fas fa-star"></i> Guardar bono
                    </button>
                </div>
            </form>

            {{-- Editar bono --}}
            <template x-if="editBonusId">
                <form :action="`{{ url('comisiones/bonus') }}/${editBonusId}`" method="POST" class="p-6 space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Nombre *</label>
                        <input type="text" name="nombre" :value="editBonusNombre" required maxlength="100"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Cálculo *</label>
                            <select name="tipo_calculo" x-model="editBonusCalculo"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 bg-white">
                                <option value="monto_fijo">Monto fijo (S/)</option>
                                <option value="porcentaje_venta">% sobre la venta</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">
                                Valor <span x-text="editBonusCalculo==='monto_fijo'?'(S/)':'(%)'"></span> *
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400 text-sm" x-text="editBonusCalculo==='monto_fijo'?'S/':'%'"></span>
                                <input type="number" name="valor" step="0.01" min="0.01" required :value="editBonusValor"
                                       class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>
                    </div>
                    <template x-if="editBonusMetaUnidades !== null">
                        <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Unidades mínimas</label>
                                <input type="number" name="meta_unidades" min="1" :value="editBonusMetaUnidades"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1.5">Período</label>
                                <select name="meta_periodo"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 bg-white">
                                    <option value="mensual"   :selected="editBonusMetaPeriodo==='mensual'">Mensual</option>
                                    <option value="quincenal" :selected="editBonusMetaPeriodo==='quincenal'">Quincenal</option>
                                    <option value="semanal"   :selected="editBonusMetaPeriodo==='semanal'">Semanal</option>
                                </select>
                            </div>
                        </div>
                    </template>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="cerrarModalBonus()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                        <button type="submit"
                                class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg flex items-center gap-2">
                            <i class="fas fa-save"></i> Actualizar bono
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

</div>{{-- /x-data --}}
<script>
const productosMargen = @json($productos->mapWithKeys(fn($p) => [$p->id => [
    'precio'  => (float)($p->precio_venta ?? 0),
    'costo'   => (float)($p->costo_promedio ?? 0),
]]));
const nombresProductos  = @json($productos->pluck('nombre', 'id'));
const nombresCategorias = @json($categorias->pluck('nombre', 'id'));
const nombresVendedores = @json($vendedores->pluck('name', 'id'));

function comisionesApp() {
    return {
        tab: 'comisiones',

        // Modal comisión
        modalComision: false,
        tipoAplicacion: 'usuario',
        tipoCalculo: 'porcentaje',
        productoSeleccionado: '',
        vendedorSeleccionado: '',
        categoriaSeleccionada: '',
        vendedorProductoSeleccionado: '',
        vendedorCategoriaSeleccionado: '',
        valorComision: '',
        editComisionId: null,
        editComisionNombre: '',
        editTipoCalculo: 'porcentaje',
        editComisionValor: 0,

        get previewComision() {
            const valor = parseFloat(this.valorComision);
            const tieneValor = !isNaN(valor) && valor > 0;
            const calculo = !tieneValor
                ? '___'
                : this.tipoCalculo === 'monto_fijo'
                    ? `S/ ${valor.toFixed(2)} fijos`
                    : this.tipoCalculo === 'porcentaje_margen'
                        ? `${valor}% de la ganancia (venta − costo)`
                        : `${valor}% del precio de venta`;

            let quien = '';
            if (this.tipoAplicacion === 'usuario') {
                quien = this.vendedorSeleccionado
                    ? `por cada venta que registre ${nombresVendedores[this.vendedorSeleccionado]}, sin importar el producto`
                    : 'por cada venta del vendedor que selecciones, sin importar el producto';
            } else if (this.tipoAplicacion === 'categoria') {
                quien = this.categoriaSeleccionada
                    ? `por cada unidad vendida de la categoría "${nombresCategorias[this.categoriaSeleccionada]}", la venda quien la venda`
                    : 'por cada unidad vendida de la categoría que selecciones, la venda quien la venda';
            } else if (this.tipoAplicacion === 'producto') {
                quien = this.productoSeleccionado
                    ? `por cada unidad vendida de "${nombresProductos[this.productoSeleccionado]}", lo venda quien lo venda`
                    : 'por cada unidad vendida del producto que selecciones, lo venda quien lo venda';
            } else if (this.tipoAplicacion === 'producto_usuario') {
                const prod = this.productoSeleccionado ? nombresProductos[this.productoSeleccionado] : 'el producto que selecciones';
                const vend = this.vendedorProductoSeleccionado ? nombresVendedores[this.vendedorProductoSeleccionado] : 'el vendedor que selecciones';
                quien = `solo cuando ${vend} venda "${prod}" (no aplica si lo vende otro vendedor, ni si ${vend} vende otro producto)`;
            } else {
                const cat = this.categoriaSeleccionada ? nombresCategorias[this.categoriaSeleccionada] : 'la categoría que selecciones';
                const vend = this.vendedorCategoriaSeleccionado ? nombresVendedores[this.vendedorCategoriaSeleccionado] : 'el vendedor que selecciones';
                quien = `solo cuando ${vend} venda algo de la categoría "${cat}" (no aplica si lo vende otro vendedor, ni si ${vend} vende de otra categoría)`;
            }

            return `Se pagará ${calculo} ${quien}.`;
        },

        get margenProducto() {
            if (!this.productoSeleccionado || this.tipoCalculo !== 'porcentaje_margen') return null;
            const p = productosMargen[this.productoSeleccionado];
            if (!p || p.precio <= 0) return null;
            const margen = p.precio - p.costo;
            const pct    = p.precio > 0 ? (margen / p.precio * 100) : 0;
            return { precio: p.precio, costo: p.costo, margen, pct };
        },

        // Modal bono
        modalBonus: false,
        bonusTipoAplicacion: 'producto',
        bonusTipo: 'fijo',
        bonusCalculo: 'monto_fijo',
        editBonusId: null,
        editBonusNombre: '',
        editBonusCalculo: 'monto_fijo',
        editBonusValor: 0,
        editBonusMetaUnidades: null,
        editBonusMetaPeriodo: 'mensual',

        abrirEditarComision(id, nombre, tipoCalculo, valor) {
            this.editComisionId     = id;
            this.editComisionNombre = nombre;
            this.editTipoCalculo    = tipoCalculo;
            this.editComisionValor  = valor;
            this.modalComision      = true;
        },
        cerrarModalComision() {
            this.modalComision = false;
            this.editComisionId = null;
            this.tipoAplicacion = 'usuario';
            this.tipoCalculo = 'porcentaje';
            this.productoSeleccionado = '';
            this.vendedorSeleccionado = '';
            this.categoriaSeleccionada = '';
            this.vendedorProductoSeleccionado = '';
            this.vendedorCategoriaSeleccionado = '';
            this.valorComision = '';
        },

        abrirEditarBonus(id, nombre, tipoCalculo, valor, metaUnidades, metaPeriodo) {
            this.editBonusId          = id;
            this.editBonusNombre      = nombre;
            this.editBonusCalculo     = tipoCalculo;
            this.editBonusValor       = valor;
            this.editBonusMetaUnidades = metaUnidades;
            this.editBonusMetaPeriodo  = metaPeriodo;
            this.modalBonus           = true;
        },
        cerrarModalBonus() { this.modalBonus = false; this.editBonusId = null; },
    };
}
</script>
</body>
</html>
