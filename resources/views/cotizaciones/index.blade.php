<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizaciones - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Cotizaciones" subtitle="Propuestas comerciales que se convierten en nota de venta, boleta o factura" />

        @include('cotizaciones.partials.mensajes')

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Vigentes</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['vigentes'] }}</p>
                <p class="text-xs text-gray-500">S/ {{ number_format($stats['monto_vigente'], 2) }} por cerrar</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Vencidas</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['vencidas'] }}</p>
                <p class="text-xs text-gray-500">se pueden renovar</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Convertidas en venta</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['convertidas'] }}</p>
                <p class="text-xs text-gray-500">S/ {{ number_format($stats['monto_convertido'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total emitidas</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500">&nbsp;</p>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Número, cliente o documento"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach(['pendiente' => 'Pendiente', 'aceptada' => 'Aceptada', 'vencida' => 'Vencida', 'rechazada' => 'Rechazada', 'convertida' => 'Convertida', 'anulada' => 'Anulada'] as $v => $t)
                        <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="md:col-span-5 flex items-center justify-between">
                <a href="{{ route('cotizaciones.index') }}" class="text-sm text-gray-500 hover:text-gray-800"><i class="fas fa-redo mr-1"></i>Limpiar</a>
                <div class="flex gap-2">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-search mr-1"></i>Filtrar</button>
                    <a href="{{ route('cotizaciones.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-1"></i>Nueva cotización
                    </a>
                </div>
            </div>
        </form>

        {{-- Listado --}}
        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elaborada por</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Válida hasta</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cotizaciones as $c)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-900">
                                <a href="{{ route('cotizaciones.show', $c) }}" class="hover:text-blue-700">{{ $c->codigo }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <div class="font-medium">{{ $c->cliente->nombre }}</div>
                                <div class="text-xs text-gray-400">{{ $c->cliente->tipo_documento }} {{ $c->cliente->numero_documento }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $c->vendedor?->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $c->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm {{ $c->esta_vencida ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ $c->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">S/ {{ number_format($c->total, 2) }}</td>
                            <td class="px-4 py-3">@include('cotizaciones.partials.badge', ['estado' => $c->estado_visible])</td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <a href="{{ route('cotizaciones.show', $c) }}" class="text-blue-600 hover:text-blue-800 mr-3" title="Ver"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('cotizaciones.pdf', $c) }}" target="_blank" class="text-gray-500 hover:text-gray-800 mr-3" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                @if($c->puede_editarse)
                                    <a href="{{ route('cotizaciones.edit', $c) }}" class="text-yellow-600 hover:text-yellow-800" title="Editar"><i class="fas fa-edit"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-14 text-center text-gray-500">
                                <i class="fas fa-file-contract text-4xl mb-3 text-gray-300"></i>
                                <p class="font-medium">No hay cotizaciones con esos filtros</p>
                                <a href="{{ route('cotizaciones.create') }}" class="inline-block mt-3 text-blue-600 hover:underline text-sm">Crear la primera cotización</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $cotizaciones->links() }}</div>
    </div>
</body>
</html>
