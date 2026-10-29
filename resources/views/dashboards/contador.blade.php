<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Contador - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Panel del Contador" subtitle="Comprobantes, cobranzas y pagos, {{ auth()->user()->name }}" />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Pendientes de envío a SUNAT</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $pendientes_sunat }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $rechazados_sunat }} rechazado(s)</p>
                    </div>
                    <div class="bg-amber-100 rounded-full p-3">
                        <i class="fas fa-paper-plane text-amber-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Ventas del mes</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">S/ {{ number_format($ventas_mes_total, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $ventas_mes_cantidad }} comprobante(s)</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-chart-line text-blue-900 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Por cobrar vencidas</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $por_cobrar_vencidas }}</p>
                        <p class="text-xs text-gray-500 mt-1">Saldo S/ {{ number_format($por_cobrar_monto, 2) }}</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-hand-holding-usd text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-rose-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Por pagar vencidas</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $por_pagar_vencidas }}</p>
                        <p class="text-xs text-gray-500 mt-1">Saldo S/ {{ number_format($por_pagar_monto, 2) }}</p>
                    </div>
                    <div class="bg-rose-100 rounded-full p-3">
                        <i class="fas fa-file-invoice-dollar text-rose-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Comprobantes por enviar o corregir</h3>
                <a href="{{ route('facturacion.index') }}" class="text-sm text-blue-700 hover:underline">Ir a Facturación</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr><th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado SUNAT</th><th class="px-4 py-2 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($ultimos_pendientes as $v)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $v->numero_documento ?? $v->codigo }}</td>
                                <td class="px-4 py-2">{{ $v->cliente->nombre ?? 'Sin cliente' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $v->fecha?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ ucfirst(str_replace('_', ' ', $v->estado_sunat)) }}</td>
                                <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($v->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No hay comprobantes pendientes de envío.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
