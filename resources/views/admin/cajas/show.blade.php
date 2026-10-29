<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja #{{ $caja->id }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen" x-data="{ modalForzar: false, modalAjustar: false }">

    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-0.5">
                <a href="{{ route('admin.cajas.index') }}" class="hover:text-blue-600">Historial</a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Caja #{{ $caja->id }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800">
                {{ $caja->usuario?->name ?? '—' }} — {{ $caja->sucursal?->nombre ?? 'Sin sucursal' }}
            </h1>
            <p class="text-sm text-gray-500">{{ $caja->fecha?->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($caja->estado === 'abierta' && auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
                <button @click="modalForzar = true"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
                    <i class="fas fa-lock mr-1"></i> Forzar Cierre
                </button>
            @endif
            @if(auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
            <button @click="modalAjustar = true"
                    class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm font-medium hover:bg-yellow-600 transition">
                <i class="fas fa-balance-scale mr-1"></i> Ajustar
            </button>
            @endif
            <a href="{{ route('admin.cajas.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-1"></i> Volver
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Columna izquierda --}}
        <div class="space-y-4">

            {{-- Info general --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-700">Información General</h2>
                    @if($caja->estado === 'abierta')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Abierta
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span> Cerrada
                        </span>
                    @endif
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Cajero</dt><dd class="font-medium text-gray-800">{{ $caja->usuario?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Sucursal</dt><dd class="font-medium text-gray-800">{{ $caja->sucursal?->nombre ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Almacén</dt><dd class="font-medium text-gray-800">{{ $caja->almacen?->nombre ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Fecha</dt><dd class="font-medium text-gray-800">{{ $caja->fecha?->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Apertura</dt><dd class="font-medium text-gray-800">{{ $caja->fecha_apertura?->format('H:i:s') ?? '—' }}</dd></div>
                    @if($caja->fecha_cierre)
                        <div class="flex justify-between"><dt class="text-gray-500">Cierre</dt><dd class="font-medium text-gray-800">{{ $caja->fecha_cierre->format('H:i:s') }}</dd></div>
                    @endif
                    @if($caja->observaciones_apertura)
                        <div class="pt-2 border-t"><dt class="text-gray-400 text-xs mb-1">Obs. apertura</dt><dd class="text-gray-700 text-xs">{{ $caja->observaciones_apertura }}</dd></div>
                    @endif
                    @if($caja->observaciones_cierre)
                        <div class="pt-2 border-t"><dt class="text-gray-400 text-xs mb-1">Obs. cierre</dt><dd class="text-gray-700 text-xs">{{ $caja->observaciones_cierre }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Arqueo --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Arqueo de Caja</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Monto inicial</dt><dd class="font-medium">S/ {{ number_format($arqueo['monto_inicial'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Ventas efectivo</dt><dd class="font-medium text-green-600">+ S/ {{ number_format($arqueo['ventas_efectivo'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Ventas Yape</dt><dd class="font-medium text-purple-600">+ S/ {{ number_format($arqueo['ventas_yape'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Ventas Plin</dt><dd class="font-medium text-teal-600">+ S/ {{ number_format($arqueo['ventas_plin'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Transferencias</dt><dd class="font-medium text-blue-600">+ S/ {{ number_format($arqueo['ventas_transferencia'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Ingresos manuales</dt><dd class="font-medium text-green-600">+ S/ {{ number_format($arqueo['ingresos_manual'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Egresos</dt><dd class="font-medium text-red-500">- S/ {{ number_format($arqueo['total_egresos'], 2) }}</dd></div>
                    <div class="flex justify-between pt-2 border-t font-semibold">
                        <dt class="text-gray-700">Saldo esperado (ef.)</dt>
                        <dd>S/ {{ number_format($arqueo['saldo_esperado'], 2) }}</dd>
                    </div>
                    @if($caja->monto_real_cierre !== null)
                        <div class="flex justify-between"><dt class="text-gray-500">Real al cierre</dt><dd class="font-medium">S/ {{ number_format($caja->monto_real_cierre, 2) }}</dd></div>
                        <div class="flex justify-between pt-2 border-t font-bold text-base">
                            <dt class="{{ ($caja->diferencia_cierre ?? 0) < 0 ? 'text-red-600' : 'text-gray-800' }}">Diferencia</dt>
                            <dd class="{{ ($caja->diferencia_cierre ?? 0) < 0 ? 'text-red-600' : (($caja->diferencia_cierre ?? 0) > 0 ? 'text-green-600' : 'text-gray-600') }}">
                                S/ {{ number_format($caja->diferencia_cierre ?? 0, 2) }}
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between text-xs text-gray-400 pt-1">
                        <dt># Ventas</dt><dd>{{ $arqueo['num_ventas'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Columna derecha: Movimientos --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-history text-gray-400 mr-2"></i>
                        Movimientos / Auditoría ({{ $caja->movimientos->count() }})
                    </h2>
                </div>
                <div class="max-h-[70vh] overflow-y-auto">
                    @forelse($movimientosPorFecha as $fecha => $movsDia)
                        @php
                            $ingresosDia = $movsDia->where('tipo', 'ingreso')->sum('monto');
                            $egresosDia  = $movsDia->where('tipo', 'egreso')->sum('monto');
                        @endphp
                        {{-- Sticky date header --}}
                        <div class="sticky top-0 z-10 bg-gray-50 border-y border-gray-200 px-5 py-2 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">
                                <i class="fas fa-calendar-day mr-1 text-gray-400"></i>
                                {{ \Carbon\Carbon::parse($fecha)->translatedFormat('l d \d\e F Y') }}
                            </span>
                            <div class="flex gap-3 text-xs">
                                <span class="text-green-700 font-semibold">+S/ {{ number_format($ingresosDia, 2) }}</span>
                                @if($egresosDia > 0)
                                    <span class="text-red-600 font-semibold">-S/ {{ number_format($egresosDia, 2) }}</span>
                                @endif
                                <span class="text-gray-500">{{ $movsDia->count() }} mov.</span>
                            </div>
                        </div>
                        @foreach($movsDia as $mov)
                        <div class="px-5 py-3 hover:bg-gray-50 transition-colors divide-y divide-gray-50">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5
                                        {{ $mov->tipo === 'ingreso' ? 'bg-green-100' : 'bg-red-100' }}">
                                        <i class="fas {{ $mov->tipo === 'ingreso' ? 'fa-arrow-down text-green-600' : 'fa-arrow-up text-red-600' }} text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $mov->concepto }}</p>
                                        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                            <span class="text-xs text-gray-400">{{ $mov->created_at->format('H:i') }}</span>
                                            @if($mov->metodo_pago)
                                                <span class="text-xs px-1.5 py-0.5 bg-gray-100 rounded text-gray-500">{{ ucfirst($mov->metodo_pago) }}</span>
                                            @endif
                                            @if($mov->venta_id)
                                                <span class="text-xs px-1.5 py-0.5 bg-blue-50 rounded text-blue-600">Venta #{{ $mov->venta_id }}</span>
                                            @endif
                                            @if($mov->usuario)
                                                <span class="text-xs text-gray-400">por {{ $mov->usuario->name }}</span>
                                            @endif
                                        </div>
                                        @if($mov->observaciones)
                                            <p class="text-xs text-gray-400 mt-0.5 italic">{{ $mov->observaciones }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-sm font-semibold {{ $mov->tipo === 'ingreso' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $mov->tipo === 'ingreso' ? '+' : '-' }} S/ {{ number_format($mov->monto, 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @empty
                        <div class="px-5 py-10 text-center text-gray-400">
                            <i class="fas fa-inbox text-2xl mb-2 block"></i> Sin movimientos.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Forzar cierre --}}
    <div x-show="modalForzar" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="font-bold text-gray-800"><i class="fas fa-lock text-red-500 mr-2"></i>Forzar Cierre de Caja</h3>
                <button @click="modalForzar = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.cajas.forzar-cierre', $caja) }}" class="p-6 space-y-4">
                @csrf
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Cerrará la caja sin arqueo. Queda registrado en auditoría.
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                    <textarea name="observaciones" rows="3" required minlength="10"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500"
                              placeholder="Explique el motivo (mín. 10 caracteres)..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="modalForzar = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Confirmar Cierre Forzado</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Ajustar diferencia --}}
    <div x-show="modalAjustar" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="font-bold text-gray-800"><i class="fas fa-balance-scale text-yellow-500 mr-2"></i>Ajustar Diferencia</h3>
                <button @click="modalAjustar = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.cajas.ajustar-diferencia', $caja) }}" class="p-6 space-y-4">
                @csrf
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Positivo = ingreso, negativo = egreso. Queda en auditoría.
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto (S/) <span class="text-red-500">*</span></label>
                    <input type="number" name="monto_ajuste" step="0.01" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500"
                           placeholder="Ej: 50 (ingreso) o -20 (egreso)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                    <input type="text" name="motivo_ajuste" required minlength="5"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500"
                           placeholder="Ej: Error en conteo inicial">
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="modalAjustar = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                            class="flex-1 py-2 bg-yellow-500 text-white rounded-lg text-sm font-semibold hover:bg-yellow-600">Aplicar Ajuste</button>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>
