<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cliente->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$cliente->nombre" :subtitle="$cliente->tipo_documento . ' ' . $cliente->numero_documento" />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            @if($canEdit)
                <a href="{{ route('clientes.edit', $cliente) }}" class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 text-yellow-800 hover:bg-yellow-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-edit"></i> Editar</a>
                <a href="{{ route('cotizaciones.create', ['cliente_id' => $cliente->id]) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm"><i class="fas fa-file-contract"></i> Nueva cotización</a>
            @endif
            @if($canDelete)
                <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="inline ml-auto" onsubmit="return confirm('¿Eliminar este cliente?')">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center gap-2 border border-red-200 bg-white text-red-700 hover:bg-red-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-trash"></i> Eliminar</button>
                </form>
            @endif
        </div>

        {{-- Resumen comercial --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Compras</p>
                <p class="text-2xl font-bold text-gray-900">{{ $resumen['ventas'] }}</p>
                <p class="text-xs text-gray-500">{{ $resumen['ultima_compra'] ? 'Última: ' . \Illuminate\Support\Carbon::parse($resumen['ultima_compra'])->format('d/m/Y') : 'Sin compras aún' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total comprado</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['total_comprado'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Ticket promedio</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['ticket_promedio'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Cotizaciones vigentes</p>
                <p class="text-2xl font-bold text-gray-900">{{ $resumen['cotizaciones_vigentes'] }}</p>
                <p class="text-xs text-gray-500">S/ {{ number_format($resumen['monto_cotizado'], 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Datos --}}
            <div class="bg-white rounded-xl shadow-sm p-5 text-sm self-start">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Datos del cliente</h3>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $cliente->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ ucfirst($cliente->estado) }}</span>
                </div>
                <dl class="space-y-2">
                    <div><dt class="text-xs text-gray-400 uppercase">{{ $cliente->tipo_documento }}</dt><dd class="font-mono">{{ $cliente->numero_documento }}</dd></div>
                    @if($cliente->contacto)<div><dt class="text-xs text-gray-400 uppercase">Contacto</dt><dd>{{ $cliente->contacto }}</dd></div>@endif
                    @if($cliente->telefono)<div><dt class="text-xs text-gray-400 uppercase">Teléfono</dt><dd><i class="fas fa-phone text-xs mr-1"></i>{{ $cliente->telefono }}</dd></div>@endif
                    @if($cliente->email)<div><dt class="text-xs text-gray-400 uppercase">Correo</dt><dd class="break-all">{{ $cliente->email }}</dd></div>@endif
                    @if($cliente->direccion)
                        <div><dt class="text-xs text-gray-400 uppercase">Dirección</dt>
                            <dd>{{ $cliente->direccion }}@if($cliente->distrito || $cliente->provincia || $cliente->departamento)<br><span class="text-gray-500">{{ collect([$cliente->distrito, $cliente->provincia, $cliente->departamento])->filter()->implode(', ') }}</span>@endif</dd></div>
                    @endif
                </dl>
                @if($cliente->notas)
                    <div class="mt-4 bg-amber-50 border border-amber-100 rounded-lg p-3 text-amber-900 whitespace-pre-line"><p class="text-xs font-semibold uppercase mb-1">Notas</p>{{ $cliente->notas }}</div>
                @endif
                <p class="text-xs text-gray-400 mt-4">Registrado el {{ $cliente->created_at?->format('d/m/Y') }}</p>
            </div>

            <div class="xl:col-span-2 space-y-6">
                {{-- Ventas --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-900">Últimas ventas</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                                <th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($ventas as $v)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <a href="{{ route('ventas.show', $v) }}" class="font-mono font-medium text-blue-700 hover:underline">{{ $v->numero_documento ?? $v->codigo }}</a>
                                            <span class="text-xs text-gray-400 ml-1">{{ ucfirst(str_replace('_', ' ', $v->tipo_comprobante)) }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500">{{ $v->fecha?->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ ucfirst($v->estado_pago) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($v->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Todavía no tiene ventas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Cotizaciones --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-900">Cotizaciones</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                                <th class="px-4 py-2 text-left">Número</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($cotizaciones as $c)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2"><a href="{{ route('cotizaciones.show', $c) }}" class="font-mono font-medium text-blue-700 hover:underline">{{ $c->codigo }}</a></td>
                                        <td class="px-4 py-2 text-gray-500">{{ $c->fecha->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2">@include('cotizaciones.partials.badge', ['estado' => $c->estado_visible])</td>
                                        <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($c->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin cotizaciones</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
