<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $producto->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$producto->nombre" :subtitle="'Stock en todos los almacenes · ' . $producto->codigo" />

        <div class="mb-6">
            <a href="{{ route('tienda.inventario.ver') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver al inventario</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h3 class="font-semibold text-gray-900">Existencias</h3>
                <p class="text-xs text-gray-500">
                    {{ collect([$producto->categoria?->nombre, $producto->marca?->nombre])->filter()->implode(' · ') }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                        <th class="px-4 py-2 text-left">Almacén</th>
                        <th class="px-4 py-2 text-left">Variante</th>
                        <th class="px-4 py-2 text-right">Cantidad</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stocks as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ $s->almacen?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if($s->variante)
                                        <span class="font-mono">{{ $s->variante->codigo_kyrios ?? $s->variante->sku }}</span>
                                        <span class="text-gray-500">{{ $s->variante->nombre_completo }}</span>
                                    @else
                                        <span class="text-gray-400">Sin variante</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-semibold">{{ (int) $s->cantidad }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Este producto no tiene existencias registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
