<!DOCTYPE html>
<html lang="es" x-data="{ showPagoModal: false, cuotaId: null, montoCuota: 0 }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crédito — {{ $venta->codigo }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-10">

        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <a href="{{ route('ventas.index') }}" class="hover:text-blue-600 transition-colors">Ventas</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <a href="{{ route('ventas.show', $venta) }}" class="hover:text-blue-600 transition-colors">{{ $venta->codigo }}</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-gray-700 font-medium">Crédito</span>
        </div>

        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Gestión de Crédito</h1>
                <p class="text-gray-500 text-sm mt-0.5">{{ $venta->cliente?->nombre }} · {{ $venta->codigo }}</p>
            </div>
            @if(in_array(auth()->user()->role->nombre, ['Administración', 'Contador']) && $cuenta->estado !== 'pagado' && $cuenta->estado !== 'anulado')
            <button @click="showPagoModal = true; cuotaId = null; montoCuota = {{ $cuenta->saldo_pendiente }};"
                    class="inline-flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                <i class="fas fa-plus-circle"></i> Registrar Pago
            </button>
            @endif
        </div>

        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
        </div>
        @endif

        {{-- Resumen de cuenta --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total crédito</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($cuenta->monto_total, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $cuenta->numero_cuotas }} cuotas · c/{{ $cuenta->dias_entre_cuotas }}d</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Pagado</p>
                <p class="text-2xl font-bold text-green-600">S/ {{ number_format($cuenta->monto_pagado, 2) }}</p>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $cuenta->porcentaje_pagado }}%"></div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-{{ $cuenta->esta_vencida ? 'red' : 'orange' }}-100 p-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Saldo pendiente</p>
                <p class="text-2xl font-bold text-{{ $cuenta->esta_vencida ? 'red' : 'orange' }}-600">
                    S/ {{ number_format($cuenta->saldo_pendiente, 2) }}
                </p>
                <p class="text-xs text-gray-400 mt-1">Vence: {{ $cuenta->fecha_vencimiento_final->format('d/m/Y') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Timeline de cuotas --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-bold text-gray-900">Plan de Cuotas</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($cuenta->cuotas as $cuota)
                    @php $vencida = $cuota->estado === 'pendiente' && $cuota->fecha_vencimiento->lt(now()); @endphp
                    <div class="px-6 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                {{ $cuota->estado === 'pagado' ? 'bg-green-100 text-green-600' : ($vencida ? 'bg-red-100 text-red-600' : 'bg-orange-100 text-orange-600') }}">
                                {{ $cuota->numero_cuota }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</p>
                                @if($cuota->estado === 'pagado' && $cuota->fecha_pago_real)
                                <p class="text-xs text-green-500">Pagado {{ $cuota->fecha_pago_real->format('d/m/Y') }}</p>
                                @elseif($vencida)
                                <p class="text-xs text-red-500 font-medium">Vencida hace {{ $cuota->fecha_vencimiento->diffInDays(now()) }}d</p>
                                @else
                                <p class="text-xs text-gray-400">Vence en {{ now()->diffInDays($cuota->fecha_vencimiento) }}d</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-mono font-semibold {{ $cuota->estado === 'pagado' ? 'text-green-600 line-through' : ($vencida ? 'text-red-600' : 'text-gray-700') }}">
                                S/ {{ number_format($cuota->monto, 2) }}
                            </span>
                            @if($cuota->estado === 'pendiente' && in_array(auth()->user()->role->nombre, ['Administración', 'Contador']) && $cuenta->estado !== 'anulado')
                            <button @click="showPagoModal = true; cuotaId = {{ $cuota->id }}; montoCuota = {{ $cuota->monto }};"
                                    class="text-xs bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200 px-2.5 py-1 rounded-lg font-semibold transition">
                                Pagar
                            </button>
                            @elseif($cuota->estado === 'pagado')
                            <span class="text-xs bg-green-50 text-green-600 border border-green-200 px-2.5 py-1 rounded-lg font-semibold">
                                <i class="fas fa-check mr-1"></i>Pagada
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Historial de pagos --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-bold text-gray-900">Historial de Pagos</h2>
                </div>
                @if($cuenta->pagos->isEmpty())
                <div class="px-6 py-10 text-center text-gray-400">
                    <i class="fas fa-receipt text-3xl mb-2 opacity-30"></i>
                    <p class="text-sm">Sin pagos registrados aún</p>
                </div>
                @else
                <div class="divide-y divide-gray-50">
                    @foreach($cuenta->pagos as $pago)
                    <div class="px-6 py-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">S/ {{ number_format($pago->monto, 2) }}</p>
                                <p class="text-xs text-gray-400">{{ $pago->fecha_pago->format('d/m/Y') }} · {{ ucfirst($pago->metodo_pago) }}</p>
                                @if($pago->referencia)
                                <p class="text-xs text-gray-400">Ref: {{ $pago->referencia }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400">Registrado por</p>
                                <p class="text-xs font-medium text-gray-600">{{ $pago->usuario?->name ?? '—' }}</p>
                            </div>
                        </div>
                        @if($pago->observaciones)
                        <p class="text-xs text-gray-400 mt-1 italic">{{ $pago->observaciones }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

        </div>

    </div>

    {{-- Modal registrar pago --}}
    <div x-show="showPagoModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPagoModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-5">
                <h3 class="text-lg font-bold text-white">Registrar Pago</h3>
                <p class="text-orange-100 text-sm mt-0.5">{{ $venta->codigo }} · {{ $venta->cliente?->nombre }}</p>
            </div>
            <form action="{{ route('ventas.credito.pago', $venta) }}" method="POST" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="cuota_cobro_id" :value="cuotaId">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Monto a pagar *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">S/</span>
                        <input type="number" name="monto" step="0.01" min="0.01"
                               :value="montoCuota.toFixed(2)"
                               max="{{ $cuenta->saldo_pendiente }}"
                               class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                               required>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Saldo pendiente: S/ {{ number_format($cuenta->saldo_pendiente, 2) }}</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Método de pago *</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['efectivo' => '💵 Efectivo', 'transferencia' => '🏦 Transf.', 'yape' => '📱 Yape', 'plin' => '📱 Plin'] as $metodo => $label)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl p-2.5 cursor-pointer hover:border-orange-400 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 transition-all">
                            <input type="radio" name="metodo_pago" value="{{ $metodo }}" {{ $metodo === 'efectivo' ? 'checked' : '' }} required class="text-orange-600">
                            <span class="text-xs font-medium">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Fecha de pago *</label>
                    <input type="date" name="fecha_pago" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Referencia <span class="font-normal text-gray-400">(opcional)</span></label>
                    <input type="text" name="referencia" placeholder="Nro. operación, voucher..."
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Observaciones <span class="font-normal text-gray-400">(opcional)</span></label>
                    <textarea name="observaciones" rows="2"
                              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showPagoModal = false"
                            class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 py-2.5 rounded-xl font-semibold text-sm transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-2.5 rounded-xl font-bold text-sm transition-colors">
                        <i class="fas fa-check mr-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
