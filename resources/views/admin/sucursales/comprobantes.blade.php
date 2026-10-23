<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobantes — {{ $sucursal->nombre }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.sucursales.edit', $sucursal) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Comprobantes Emitidos</h1>
            <p class="text-sm text-gray-500">{{ $sucursal->nombre }} ({{ $sucursal->codigo }})</p>
        </div>
    </div>

    {{-- Tarjetas resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                <i class="fas fa-file-invoice text-blue-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totales['total']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                <i class="fas fa-receipt text-green-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Boletas</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totales['boleta']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                <i class="fas fa-file-alt text-purple-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Facturas</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totales['factura']) }}</p>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 mb-5 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">Buscar cliente / número</label>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Nombre, DNI, RUC o código..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
            <select name="tipo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todos</option>
                <option value="boleta"      {{ request('tipo') === 'boleta'      ? 'selected' : '' }}>Boleta</option>
                <option value="factura"     {{ request('tipo') === 'factura'     ? 'selected' : '' }}>Factura</option>
                <option value="nota_credito"{{ request('tipo') === 'nota_credito'? 'selected' : '' }}>Nota de Crédito</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Estado de pago</label>
            <select name="estado" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todos</option>
                <option value="pagado"   {{ request('estado') === 'pagado'   ? 'selected' : '' }}>Pagado</option>
                <option value="pendiente"{{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="anulado"  {{ request('estado') === 'anulado'  ? 'selected' : '' }}>Anulado</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <i class="fas fa-search mr-1"></i> Filtrar
            </button>
            @if(request()->hasAny(['q','tipo','estado']))
                <a href="{{ route('admin.sucursales.comprobantes', $sucursal) }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times mr-1"></i> Limpiar
                </a>
            @endif
        </div>
    </form>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-file-invoice text-blue-600"></i>
                Documentos SUNAT emitidos en esta sucursal
            </h3>
            <span class="text-sm text-gray-500">{{ $ventas->total() }} comprobante(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número / Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado SUNAT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado Pago</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($ventas as $venta)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono font-medium text-gray-900">
                                <a href="{{ route('ventas.show', $venta) }}" class="text-blue-600 hover:underline">
                                    {{ $venta->numero_documento ?? $venta->codigo }}
                                </a>
                                @if($venta->serieComprobante)
                                    <div class="text-xs text-gray-400">Serie: {{ $venta->serieComprobante->serie }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $tipoBadge = match($venta->tipo_comprobante) {
                                        'boleta'       => ['bg-green-100 text-green-800', 'Boleta'],
                                        'factura'      => ['bg-purple-100 text-purple-800', 'Factura'],
                                        'nota_credito' => ['bg-orange-100 text-orange-800', 'N. Crédito'],
                                        default        => ['bg-gray-100 text-gray-600', ucfirst($venta->tipo_comprobante)],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tipoBadge[0] }}">
                                    {{ $tipoBadge[1] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $venta->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                <div>{{ $venta->cliente?->nombre ?? 'Cliente genérico' }}</div>
                                @if($venta->cliente?->numero_documento)
                                    <div class="text-xs text-gray-400">{{ $venta->cliente->tipo_documento }}: {{ $venta->cliente->numero_documento }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                S/ {{ number_format($venta->total, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                {{-- Estado SUNAT: pendiente hasta integrar API --}}
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-clock mr-1"></i> Pendiente envío
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($venta->estado_pago === 'pagado')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Pagado
                                    </span>
                                @elseif($venta->estado_pago === 'anulado')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i> Anulado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                        <i class="fas fa-hourglass-half mr-1"></i> Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('ventas.show', $venta) }}"
                                       class="inline-flex items-center px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100 transition-colors"
                                       title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('ventas.pdf', $venta) }}"
                                       target="_blank"
                                       class="inline-flex items-center px-2 py-1 text-xs bg-gray-50 text-gray-700 rounded hover:bg-gray-100 transition-colors"
                                       title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                <i class="fas fa-file-invoice text-4xl mb-3 block opacity-30"></i>
                                <p class="font-medium">No hay comprobantes emitidos en esta sucursal.</p>
                                @if(request()->hasAny(['q','tipo','estado']))
                                    <p class="text-sm mt-1">Intenta ajustar los filtros de búsqueda.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ventas->hasPages())
            <div class="px-4 py-4 border-t">{{ $ventas->links() }}</div>
        @endif
    </div>

    {{-- Nota sobre integración SUNAT --}}
    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex gap-3">
        <i class="fas fa-info-circle text-amber-500 mt-0.5 shrink-0"></i>
        <div class="text-sm text-amber-800">
            <strong>Estado SUNAT:</strong> La integración con la API de SUNAT (envío automático y consulta de estado) está pendiente de configuración.
            Los comprobantes aparecen como "Pendiente envío" hasta que se configure la conexión con el proveedor OSE/PSE en los ajustes de empresa.
        </div>
    </div>

</div>
</body>
</html>
