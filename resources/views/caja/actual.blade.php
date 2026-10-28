<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Actual</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
    </style>
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8"
     x-data="{ showGastoModal: false, showIngresoModal: false, showCierreModal: false, montoReal: '', saldoSistema: {{ $arqueo['saldo_esperado'] }} }">

    <x-header title="Mi Caja" subtitle="{{ now()->locale('es')->isoFormat('dddd D [de] MMMM') }} — {{ auth()->user()->name }}" />

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex justify-between items-center">
            <span><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex justify-between items-center">
            <span><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</span>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    @endif

    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-900 to-blue-700 rounded-xl p-5 text-white shadow-lg">
            <p class="text-blue-200 text-xs font-medium uppercase tracking-wide">Saldo en Sistema</p>
            <p class="text-3xl font-bold mt-1">S/ {{ number_format($arqueo['saldo_esperado'], 2) }}</p>
            <p class="text-blue-200 text-xs mt-1">Inicial: S/ {{ number_format($arqueo['monto_inicial'], 2) }}</p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Ventas Totales</p>
            <p class="text-2xl font-bold text-green-600 mt-1">S/ {{ number_format($arqueo['total_ventas'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $arqueo['num_ventas'] }} venta(s)</p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Egresos del Día</p>
            <p class="text-2xl font-bold text-red-600 mt-1">S/ {{ number_format($arqueo['total_egresos'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $caja->movimientos->where('tipo', 'egreso')->count() }} gasto(s)</p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Almacén</p>
            <p class="text-lg font-bold text-gray-800 mt-1">{{ $caja->almacen->nombre }}</p>
            <p class="text-xs text-gray-400 mt-1">
                Abierta: {{ $caja->fecha_apertura ? $caja->fecha_apertura->format('H:i') : $caja->created_at->format('H:i') }}
            </p>
        </div>
    </div>

    {{-- Arqueo por método de pago --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
            <i class="fas fa-chart-pie mr-1"></i> Desglose de ventas por método de pago
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-green-50 rounded-lg border border-green-100">
                <i class="fas fa-money-bill-wave text-green-600 text-xl mb-1"></i>
                <p class="text-xs text-gray-500">Efectivo</p>
                <p class="text-lg font-bold text-green-700">S/ {{ number_format($arqueo['ventas_efectivo'], 2) }}</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg border border-purple-100">
                <i class="fas fa-mobile-alt text-purple-600 text-xl mb-1"></i>
                <p class="text-xs text-gray-500">Yape</p>
                <p class="text-lg font-bold text-purple-700">S/ {{ number_format($arqueo['ventas_yape'], 2) }}</p>
            </div>
            <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-100">
                <i class="fas fa-mobile text-blue-600 text-xl mb-1"></i>
                <p class="text-xs text-gray-500">Plin</p>
                <p class="text-lg font-bold text-blue-700">S/ {{ number_format($arqueo['ventas_plin'], 2) }}</p>
            </div>
            <div class="text-center p-3 bg-orange-50 rounded-lg border border-orange-100">
                <i class="fas fa-university text-orange-600 text-xl mb-1"></i>
                <p class="text-xs text-gray-500">Transferencia</p>
                <p class="text-lg font-bold text-orange-700">S/ {{ number_format($arqueo['ventas_transferencia'], 2) }}</p>
            </div>
        </div>
        @if(($arqueo['ventas_mixto'] ?? 0) > 0)
        <div class="grid grid-cols-1 gap-4 mt-3">
            <div class="text-center p-3 bg-gray-50 rounded-lg border border-gray-200">
                <i class="fas fa-layer-group text-gray-600 text-xl mb-1"></i>
                <p class="text-xs text-gray-500">Mixto (efectivo + otro)</p>
                <p class="text-lg font-bold text-gray-700">S/ {{ number_format($arqueo['ventas_mixto'], 2) }}</p>
            </div>
        </div>
        @endif
        @if($arqueo['ingresos_manual'] > 0)
            <p class="text-xs text-gray-400 mt-3 text-right">
                + S/ {{ number_format($arqueo['ingresos_manual'], 2) }} en ingresos manuales
            </p>
        @endif
    </div>

    {{-- Acciones rápidas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 no-print">
        <button @click="showIngresoModal = true"
                class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl p-4
                       flex items-center gap-4 transition-all shadow-sm">
            <div class="bg-white/20 p-3 rounded-lg">
                <i class="fas fa-plus-circle text-2xl"></i>
            </div>
            <div class="text-left">
                <p class="font-semibold text-lg">Registrar Ingreso</p>
                <p class="text-sm opacity-80">Cobros u otros ingresos</p>
            </div>
        </button>

        <button @click="showGastoModal = true"
                class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-xl p-4
                       flex items-center gap-4 transition-all shadow-sm">
            <div class="bg-white/20 p-3 rounded-lg">
                <i class="fas fa-minus-circle text-2xl"></i>
            </div>
            <div class="text-left">
                <p class="font-semibold text-lg">Registrar Gasto</p>
                <p class="text-sm opacity-80">Movilidad, insumos, etc.</p>
            </div>
        </button>

        <button @click="showCierreModal = true"
                class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white rounded-xl p-4
                       flex items-center gap-4 transition-all shadow-sm">
            <div class="bg-white/20 p-3 rounded-lg">
                <i class="fas fa-lock text-2xl"></i>
            </div>
            <div class="text-left">
                <p class="font-semibold text-lg">Cerrar Caja</p>
                <p class="text-sm opacity-80">Realizar el arqueo y cierre</p>
            </div>
        </button>
    </div>

    {{-- Comprobantes del día --}}
    @php
        $ventasDelDia = $caja->movimientos->where('tipo', 'ingreso')->whereNotNull('venta_id')->sortByDesc('created_at')->unique('venta_id');
    @endphp
    @if($ventasDelDia->count())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6 no-print">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-receipt mr-2 text-indigo-600"></i> Comprobantes del día
            </h3>
            <span class="text-xs text-gray-400">{{ $ventasDelDia->count() }} venta(s)</span>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($ventasDelDia as $mov)
            @php $v = $mov->venta; @endphp
            @if($v)
            <div class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-gray-800 font-mono">{{ $v->codigo }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 uppercase">{{ $v->tipo_comprobante }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $mov->created_at->format('H:i') }}
                        @if($v->cliente)· {{ $v->cliente->nombre }}@endif
                        · S/ {{ number_format($v->total, 2) }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($v->tipo_comprobante !== 'cotizacion')
                    <a href="{{ route('ventas.pdf', [$v, 'formato' => 'ticket']) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition">
                        <i class="fas fa-receipt"></i> Ticket
                    </a>
                    @endif
                    <a href="{{ route('ventas.pdf', [$v, 'formato' => 'a4']) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('ventas.show', $v) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-semibold rounded-lg transition">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tabla de movimientos --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-list-alt mr-2 text-blue-800"></i> Movimientos del día
            </h3>
            <span class="text-xs text-gray-400">{{ $caja->movimientos->count() }} registro(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Op.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase no-print">Reimprimir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($caja->movimientos->sortByDesc('created_at') as $mov)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $mov->created_at->format('H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1
                                    {{ $mov->tipo === 'ingreso' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                {{ ucfirst($mov->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-gray-800">{{ $mov->concepto }}</p>
                            @if($mov->venta)
                                <p class="text-xs text-blue-600 font-mono font-semibold">{{ $mov->venta->codigo }}</p>
                            @endif
                            @if($mov->referencia)
                                <p class="text-xs text-gray-400">Ref: {{ $mov->referencia }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php $mp = $mov->metodo_pago ?? 'efectivo'; @endphp
                            @php $mpColor = match($mp) {
                                'yape'          => 'text-purple-600',
                                'plin'          => 'text-teal-600',
                                'transferencia' => 'text-blue-600',
                                'mixto'         => 'text-orange-600',
                                default         => 'text-gray-500',
                            }; @endphp
                            <span class="text-xs font-medium {{ $mpColor }} capitalize">{{ $mp }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($mov->referencia)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-xs font-mono font-semibold">
                                    <i class="fas fa-hashtag text-[9px]"></i>{{ $mov->referencia }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-semibold {{ $mov->tipo === 'ingreso' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '-' }} S/ {{ number_format($mov->monto, 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center no-print">
                            @if($mov->venta && $mov->venta->tipo_comprobante !== 'cotizacion')
                                <a href="{{ route('ventas.pdf', [$mov->venta, 'formato' => 'ticket']) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs rounded-lg transition" title="Reimprimir ticket">
                                    <i class="fas fa-print text-xs"></i> Ticket
                                </a>
                            @elseif($mov->venta)
                                <a href="{{ route('ventas.pdf', [$mov->venta, 'formato' => 'a4']) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2 py-1 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs rounded-lg transition" title="Reimprimir PDF">
                                    <i class="fas fa-file-pdf text-xs"></i> PDF
                                </a>
                            @else
                                <span class="text-gray-200 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-receipt text-4xl mb-3 block text-gray-200"></i>
                            Sin movimientos aún en este turno.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ========== MODAL INGRESO ========== --}}
    <div x-show="showIngresoModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.away="showIngresoModal = false">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-plus-circle text-green-500 mr-2"></i>Registrar Ingreso</h3>
                <button @click="showIngresoModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form action="{{ route('caja.ingreso') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="caja_id" value="{{ $caja->id }}">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                        <input type="number" name="monto" step="0.01" min="0.01" required
                               class="pl-9 w-full py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago *</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'transferencia' => 'Transfer.'] as $val => $lbl)
                        <label class="flex flex-col items-center justify-center cursor-pointer">
                            <input type="radio" name="metodo_pago" value="{{ $val }}" class="sr-only peer"
                                   {{ $val === 'efectivo' ? 'checked' : '' }}>
                            <div class="w-full text-center py-2 px-1 border-2 border-gray-200 rounded-lg text-xs font-medium
                                        peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700
                                        hover:border-gray-300 transition-colors cursor-pointer">
                                {{ $lbl }}
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Concepto *</label>
                    <input type="text" name="concepto" required maxlength="255"
                           class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                           placeholder="Ej: Cobro de deuda, venta extra...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Referencia / Nro. operación</label>
                    <input type="text" name="referencia" maxlength="100"
                           class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm"
                           placeholder="Opcional">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm"
                              placeholder="Detalles adicionales..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showIngresoModal = false"
                            class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2 px-4 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-sm">
                        <i class="fas fa-save mr-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========== MODAL GASTO ========== --}}
    <div x-show="showGastoModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.away="showGastoModal = false">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-minus-circle text-red-500 mr-2"></i>Registrar Gasto</h3>
                <button @click="showGastoModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form action="{{ route('caja.gasto') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="caja_id" value="{{ $caja->id }}">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                        <input type="number" name="monto" step="0.01" min="0.01" required
                               class="pl-9 w-full py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría *</label>
                    <select name="categoria_gasto" required
                            class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 text-sm">
                        <option value="">Seleccione...</option>
                        <option value="operativo">Operativo (útiles, servicios)</option>
                        <option value="limpieza">Limpieza</option>
                        <option value="transporte">Transporte / Movilidad</option>
                        <option value="alimentacion">Alimentación / Refrigerio</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción *</label>
                    <input type="text" name="concepto" required maxlength="255"
                           class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 text-sm"
                           placeholder="Ej: Almuerzo equipo, taxi a proveedor...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 text-sm"
                              placeholder="Detalles adicionales..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showGastoModal = false"
                            class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2 px-4 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold text-sm">
                        <i class="fas fa-save mr-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========== MODAL CIERRE ========== --}}
    <div x-show="showCierreModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6" @click.away="showCierreModal = false">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-lock text-yellow-500 mr-2"></i>Cierre de Caja</h3>
                <button @click="showCierreModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Arqueo resumen --}}
            <div class="bg-gray-50 rounded-lg p-4 mb-5 text-sm space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-600">Monto inicial:</span>
                    <span class="font-medium">S/ {{ number_format($arqueo['monto_inicial'], 2) }}</span>
                </div>
                <div class="flex justify-between text-green-700">
                    <span>+ Ventas efectivo:</span>
                    <span class="font-medium">S/ {{ number_format($arqueo['ventas_efectivo'], 2) }}</span>
                </div>
                @if($arqueo['ingresos_manual'] > 0)
                <div class="flex justify-between text-green-700">
                    <span>+ Ingresos manuales:</span>
                    <span class="font-medium">S/ {{ number_format($arqueo['ingresos_manual'], 2) }}</span>
                </div>
                @endif
                @if($arqueo['total_egresos'] > 0)
                <div class="flex justify-between text-red-600">
                    <span>- Egresos:</span>
                    <span class="font-medium">S/ {{ number_format($arqueo['total_egresos'], 2) }}</span>
                </div>
                @endif
                <div class="border-t border-gray-200 pt-2 mt-2 flex justify-between font-bold text-blue-900">
                    <span>Saldo esperado (efectivo):</span>
                    <span>S/ {{ number_format($arqueo['saldo_esperado'], 2) }}</span>
                </div>
                @if($arqueo['ventas_yape'] + $arqueo['ventas_plin'] + $arqueo['ventas_transferencia'] > 0)
                <div class="mt-2 pt-2 border-t border-dashed border-gray-200 text-xs text-gray-500 space-y-1">
                    <p class="font-semibold text-gray-600 mb-1">Ventas digitales (no cuentan en efectivo):</p>
                    @if($arqueo['ventas_yape'] > 0)
                        <div class="flex justify-between"><span>Yape:</span><span>S/ {{ number_format($arqueo['ventas_yape'], 2) }}</span></div>
                    @endif
                    @if($arqueo['ventas_plin'] > 0)
                        <div class="flex justify-between"><span>Plin:</span><span>S/ {{ number_format($arqueo['ventas_plin'], 2) }}</span></div>
                    @endif
                    @if($arqueo['ventas_transferencia'] > 0)
                        <div class="flex justify-between"><span>Transferencia:</span><span>S/ {{ number_format($arqueo['ventas_transferencia'], 2) }}</span></div>
                    @endif
                </div>
                @endif

                {{-- Listado de operaciones digitales con N° referencia --}}
                @php
                    $opsDigitales = $caja->movimientos
                        ->where('tipo', 'ingreso')
                        ->whereNotNull('referencia')
                        ->whereIn('metodo_pago', ['yape', 'plin', 'transferencia'])
                        ->sortBy('created_at');
                @endphp
                @if($opsDigitales->isNotEmpty())
                <div class="mt-3 pt-2 border-t border-dashed border-amber-200">
                    <p class="text-xs font-bold text-amber-700 mb-2 flex items-center gap-1">
                        <i class="fas fa-list-ol text-[10px]"></i> Operaciones digitales registradas
                    </p>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        @foreach($opsDigitales as $op)
                        @php $mpCol = match($op->metodo_pago) {
                            'yape' => 'text-purple-700 bg-purple-50 border-purple-200',
                            'plin' => 'text-teal-700 bg-teal-50 border-teal-200',
                            default => 'text-blue-700 bg-blue-50 border-blue-200',
                        }; @endphp
                        <div class="flex items-center justify-between gap-2 rounded-lg border px-2 py-1 {{ $mpCol }}">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-[10px] font-bold capitalize shrink-0">{{ $op->metodo_pago }}</span>
                                <span class="font-mono text-[10px] truncate">#{{ $op->referencia }}</span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold text-xs">S/ {{ number_format($op->monto, 2) }}</span>
                                <span class="block text-[9px] opacity-60">{{ $op->created_at->format('H:i') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <form action="{{ route('caja.cerrar') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="caja_id" value="{{ $caja->id }}">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Monto real en caja (efectivo físico) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">S/</span>
                        <input type="number" name="monto_real_cierre" step="0.01" min="0" required
                               x-model="montoReal"
                               class="pl-9 w-full py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 font-semibold text-lg"
                               placeholder="0.00">
                    </div>
                </div>

                {{-- Diferencia en tiempo real --}}
                <div x-show="montoReal !== ''" class="rounded-lg p-3 text-center"
                     :class="Math.abs(parseFloat(montoReal) - saldoSistema) < 0.01 ? 'bg-green-50 border border-green-200' :
                             (parseFloat(montoReal) - saldoSistema) > 0 ? 'bg-blue-50 border border-blue-200' : 'bg-red-50 border border-red-200'">
                    <p class="text-sm font-medium"
                       :class="Math.abs(parseFloat(montoReal) - saldoSistema) < 0.01 ? 'text-green-700' :
                               (parseFloat(montoReal) - saldoSistema) > 0 ? 'text-blue-700' : 'text-red-700'">
                        <span x-show="Math.abs(parseFloat(montoReal) - saldoSistema) < 0.01">
                            <i class="fas fa-check-circle mr-1"></i> Cuadra perfectamente
                        </span>
                        <span x-show="(parseFloat(montoReal) - saldoSistema) > 0.005">
                            <i class="fas fa-arrow-up mr-1"></i> Sobrante: S/ <span x-text="(parseFloat(montoReal) - saldoSistema).toFixed(2)"></span>
                        </span>
                        <span x-show="(parseFloat(montoReal) - saldoSistema) < -0.005">
                            <i class="fas fa-arrow-down mr-1"></i> Faltante: S/ <span x-text="Math.abs(parseFloat(montoReal) - saldoSistema).toFixed(2)"></span>
                        </span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones del cierre</label>
                    <textarea name="observaciones_cierre" rows="2"
                              class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm"
                              placeholder="Notas sobre el cierre..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showCierreModal = false"
                            class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2 px-4 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-bold text-sm">
                        <i class="fas fa-lock mr-1"></i> Cerrar Caja
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>
