<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progreso de Bonos por Meta</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="{ sel: 0 }">

    <x-header title="Progreso de Bonos por Meta" subtitle="Quiénes ya lograron su meta del período (listos para pagar) y quiénes van en camino" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <a href="{{ route('comisiones.index') }}"
           class="text-sm text-gray-500 hover:text-blue-700 flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Volver a configuración
        </a>
        <a href="{{ route('comisiones.reporte') }}"
           class="text-sm text-gray-500 hover:text-blue-700 flex items-center gap-1">
            <i class="fas fa-chart-bar"></i> Ver reporte de pagos
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('comisiones.marcar-bonus-pagado') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="bg-linear-to-r from-purple-700 to-purple-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 rounded-xl p-2.5"><i class="fas fa-trophy text-white text-xl"></i></div>
                <div>
                    <h2 class="text-base font-bold text-white">Metas del período actual</h2>
                    <p class="text-purple-200 text-xs">Unidades vendidas vs. meta de cada regla de bono activa, por vendedor</p>
                </div>
            </div>

            @if($progreso->isEmpty())
                <div class="py-12 text-center text-gray-400">
                    <i class="fas fa-trophy text-3xl mb-2 block opacity-30"></i>
                    <p class="text-sm">No hay ventas registradas todavía en el período actual de ninguna regla de bono por meta activa.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-10"></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vendedor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Regla de bono</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Progreso</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Bono</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($progreso as $fila)
                        @php
                            $regla = $fila['regla'];
                            $vendedor = $fila['vendedor'];
                            $liquidacion = $fila['liquidacion'];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                @if($fila['estado'] === 'logrado')
                                    <input type="checkbox" name="ids[]" value="{{ $liquidacion->id }}"
                                           @change="$event.target.checked ? sel++ : sel--"
                                           class="w-4 h-4 accent-purple-600 cursor-pointer">
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($vendedor->name ?? '?', 0, 1)) }}
                                    </div>
                                    <span class="text-gray-700 text-xs font-medium">{{ $vendedor->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <p class="font-medium text-gray-700">{{ $regla->nombre }}</p>
                                <p class="text-gray-400">
                                    {{ $regla->tipo_aplicacion === 'categoria' ? $regla->categoria?->nombre : $regla->producto?->nombre }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ $fila['periodo_inicio']->format('d/m') }} – {{ $fila['periodo_fin']->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 w-40">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $fila['estado'] === 'en_progreso' ? 'bg-amber-400' : 'bg-emerald-500' }}"
                                             style="width: {{ $fila['pct'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-600 shrink-0">{{ $fila['unidades'] }}/{{ $fila['meta'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($liquidacion)
                                    <span class="font-bold text-gray-800">S/ {{ number_format($liquidacion->monto_bonus, 2) }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php $estadoCss = match($fila['estado']) {
                                    'en_progreso' => 'bg-amber-100 text-amber-700',
                                    'logrado'     => 'bg-emerald-100 text-emerald-700',
                                    'pagado'      => 'bg-green-100 text-green-700',
                                }; @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $estadoCss }}">
                                    {{ match($fila['estado']) {
                                        'en_progreso' => 'En progreso',
                                        'logrado'     => 'Logrado',
                                        'pagado'      => 'Pagado',
                                    } }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Acción pagar bonos logrados --}}
        <div x-show="sel > 0" x-cloak
             class="fixed bottom-6 right-6 bg-white border border-gray-200 shadow-2xl rounded-2xl px-5 py-4 flex items-center gap-4 z-40">
            <div class="text-sm text-gray-700">
                <span class="font-bold text-purple-700" x-text="sel"></span> bonos seleccionados
            </div>
            <button type="submit"
                    class="px-5 py-2.5 bg-purple-700 hover:bg-purple-800 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition">
                <i class="fas fa-money-bill-wave"></i> Marcar como pagados
            </button>
        </div>
    </form>
</div>
</body>
</html>
