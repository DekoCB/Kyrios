<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Finanzas" subtitle="Resumen de lo que se debe a proveedores" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach([
                ['Por pagar', $stats['por_pagar'], 'fa-file-invoice-dollar', 'border-blue-500'],
                ['Vencido', $stats['vencido'], 'fa-triangle-exclamation', 'border-red-500'],
                ['Pagado este mes', $stats['pagado_mes'], 'fa-circle-check', 'border-emerald-500'],
                ['Por vencer en 30 días', $stats['proyeccion_30_dias'], 'fa-calendar-days', 'border-amber-400'],
            ] as [$titulo, $valor, $icono, $borde])
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $borde }}">
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><i class="fas {{ $icono }} mr-1"></i>{{ $titulo }}</p>
                    <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($valor, 2) }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Vencen en los próximos 7 días</h3>
                <a href="{{ route('cuentas-por-pagar.index') }}" class="text-sm text-blue-700 hover:underline">Ver todas las cuentas</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                        <th class="px-4 py-2 text-left">Proveedor</th>
                        <th class="px-4 py-2 text-left">Factura</th>
                        <th class="px-4 py-2 text-left">Vence</th>
                        <th class="px-4 py-2 text-right">Saldo</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($proximosVencimientos as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ $c->proveedor?->razon_social ?? '—' }}</td>
                                <td class="px-4 py-2"><a href="{{ route('cuentas-por-pagar.show', $c) }}" class="font-mono text-blue-700 hover:underline">{{ $c->numero_factura }}</a></td>
                                <td class="px-4 py-2 text-gray-500">{{ \Illuminate\Support\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($c->saldo_pendiente, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No hay cuentas por vencer esta semana</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
