<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Caja #{{ $caja->id }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .md\:ml-64 { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('caja.index') }}"
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-3">
                <i class="fas fa-arrow-left mr-2"></i> Volver al historial
            </a>
            <h1 class="text-2xl font-bold text-gray-900">
                Caja del {{ \Carbon\Carbon::parse($caja->fecha)->format('d/m/Y') }}
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">
                {{ $caja->usuario->name ?? '—' }} &bull; {{ $caja->almacen->nombre ?? '—' }}
                @if($caja->sucursal) &bull; {{ $caja->sucursal->nombre }} @endif
            </p>
        </div>
        <div class="flex items-center gap-3 no-print">
            <button onclick="window.print()"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                <i class="fas fa-print"></i> Imprimir
            </button>
            @if($caja->estado === 'abierta')
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>Abierta
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-gray-100 text-gray-600">
                    <span class="w-2 h-2 rounded-full bg-gray-400 mr-2"></span>Cerrada
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Columna izquierda: Arqueo --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Resumen financiero --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
                    <i class="fas fa-calculator mr-1"></i> Arqueo de caja
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Monto inicial</span>
                        <span class="font-medium">S/ {{ number_format($arqueo['monto_inicial'], 2) }}</span>
                    </div>
                    <div class="flex justify-between py-1 text-green-700">
                        <span>+ Ventas efectivo</span>
                        <span class="font-medium">S/ {{ number_format($arqueo['ventas_efectivo'], 2) }}</span>
                    </div>
                    @if($arqueo['ingresos_manual'] > 0)
                    <div class="flex justify-between py-1 text-green-600">
                        <span>+ Ingresos manuales</span>
                        <span class="font-medium">S/ {{ number_format($arqueo['ingresos_manual'], 2) }}</span>
                    </div>
                    @endif
                    @if($arqueo['total_egresos'] > 0)
                    <div class="flex justify-between py-1 text-red-600">
                        <span>- Egresos</span>
                        <span class="font-medium">S/ {{ number_format($arqueo['total_egresos'], 2) }}</span>
                    </div>
                    @endif
                    <div class="border-t border-gray-200 pt-2 mt-1 flex justify-between font-bold text-blue-900">
                        <span>Saldo esperado (efectivo)</span>
                        <span>S/ {{ number_format($arqueo['saldo_esperado'], 2) }}</span>
                    </div>

                    @if($caja->monto_real_cierre !== null)
                    <div class="border-t border-dashed border-gray-200 pt-2 mt-1">
                        <div class="flex justify-between py-1">
                            <span class="text-gray-600">Efectivo real contado</span>
                            <span class="font-semibold">S/ {{ number_format($caja->monto_real_cierre, 2) }}</span>
                        </div>
                        @php $dif = (float) $caja->diferencia_cierre; @endphp
                        <div class="flex justify-between py-1 font-bold
                            {{ abs($dif) < 0.01 ? 'text-green-700' : ($dif > 0 ? 'text-blue-700' : 'text-red-700') }}">
                            <span>Diferencia</span>
                            <span>{{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}</span>
                        </div>
                        @if(abs($dif) < 0.01)
                            <p class="text-xs text-green-600 text-right mt-1"><i class="fas fa-check-circle mr-1"></i>Cuadra perfectamente</p>
                        @elseif($dif > 0)
                            <p class="text-xs text-blue-600 text-right mt-1"><i class="fas fa-arrow-up mr-1"></i>Sobrante</p>
                        @else
                            <p class="text-xs text-red-600 text-right mt-1"><i class="fas fa-arrow-down mr-1"></i>Faltante</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            {{-- Ventas por método de pago --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
                    <i class="fas fa-credit-card mr-1"></i> Ventas por método
                </h3>
                <div class="space-y-3">
                    @foreach(['ventas_efectivo' => ['Efectivo', 'text-green-700', 'bg-green-50'],
                              'ventas_yape'     => ['Yape',     'text-purple-700', 'bg-purple-50'],
                              'ventas_plin'     => ['Plin',     'text-blue-700',   'bg-blue-50'],
                              'ventas_transferencia' => ['Transferencia', 'text-orange-700', 'bg-orange-50'],
                              'ventas_mixto'    => ['Mixto',    'text-gray-700',   'bg-gray-50']] as $key => [$label, $color, $bg])
                    @if(($arqueo[$key] ?? 0) > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg {{ $bg }}">
                        <span class="text-sm {{ $color }} font-medium">{{ $label }}</span>
                        <span class="text-sm font-bold {{ $color }}">S/ {{ number_format($arqueo[$key], 2) }}</span>
                    </div>
                    @endif
                    @endforeach
                    <div class="border-t border-gray-200 pt-2 flex justify-between text-sm font-bold text-gray-800">
                        <span>Total ventas</span>
                        <span>S/ {{ number_format($arqueo['total_ventas'], 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ $arqueo['num_ventas'] }} venta(s) registrada(s)</p>
                </div>
            </div>

            {{-- Tiempos --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
                    <i class="fas fa-clock mr-1"></i> Tiempos del turno
                </h3>
                <div class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Apertura</span>
                        <span class="font-medium">
                            {{ $caja->fecha_apertura ? $caja->fecha_apertura->format('H:i') : '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Cierre</span>
                        <span class="font-medium">
                            {{ $caja->fecha_cierre ? $caja->fecha_cierre->format('H:i') : '—' }}
                        </span>
                    </div>
                    @if($caja->fecha_apertura && $caja->fecha_cierre)
                    <div class="flex justify-between font-medium">
                        <span class="text-gray-500">Duración</span>
                        <span>{{ $caja->fecha_apertura->diffForHumans($caja->fecha_cierre, true) }}</span>
                    </div>
                    @endif
                </div>
                @if($caja->observaciones_apertura)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400 mb-1">Obs. apertura:</p>
                        <p class="text-sm text-gray-600">{{ $caja->observaciones_apertura }}</p>
                    </div>
                @endif
                @if($caja->observaciones_cierre)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400 mb-1">Obs. cierre:</p>
                        <p class="text-sm text-gray-600">{{ $caja->observaciones_cierre }}</p>
                    </div>
                @endif
            </div>

        </div>

        {{-- Columna derecha: Movimientos --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">
                        <i class="fas fa-list-alt mr-2 text-blue-800"></i> Movimientos
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
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($caja->movimientos->sortBy('created_at') as $mov)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $mov->created_at->format('H:i') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($mov->tipo) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-800">{{ $mov->concepto }}</p>
                                    @if($mov->venta_id)
                                        <p class="text-xs text-blue-500">Venta #{{ $mov->venta_id }}</p>
                                    @endif
                                    @if($mov->referencia)
                                        <p class="text-xs text-gray-400">Ref: {{ $mov->referencia }}</p>
                                    @endif
                                    @if($mov->usuario && $isAdmin)
                                        <p class="text-xs text-gray-400">por {{ $mov->usuario->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 capitalize">
                                    {{ $mov->metodo_pago ?? 'efectivo' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold {{ $mov->tipo === 'ingreso' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $mov->tipo === 'ingreso' ? '+' : '-' }} S/ {{ number_format($mov->monto, 2) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-3 block text-gray-200"></i>
                                    Sin movimientos registrados.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($caja->movimientos->count() > 0)
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Saldo en sistema:</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-blue-900">
                                    S/ {{ number_format($caja->monto_final, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
