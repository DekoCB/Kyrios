<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $conteo->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8" x-data="conteoForm()">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <a href="{{ route('inventario-fisico.index') }}" class="hover:text-blue-700">
                        <i class="fas fa-arrow-left mr-1"></i> Conteos
                    </a>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $conteo->nombre }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $conteo->almacen->nombre }} · {{ now()->isoFormat('dddd, D [de] MMMM YYYY') }}
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('inventario-fisico.pdf', $conteo) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="{{ route('inventario-fisico.excel', $conteo) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>

        {{-- Info box --}}
        <div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-start gap-2">
                <i class="fas fa-info-circle mt-0.5 shrink-0"></i>
                <span>
                    <strong>¿Cómo funciona?</strong> Ingresa el stock físico de cada producto.
                    <strong>Se guarda automáticamente al salir del campo</strong> — puedes filtrar, buscar por categoría o cerrar el navegador sin perder nada.
                    Al terminar, exporta el reporte.
                </span>
            </div>
            @if($stats->contados > 0)
                <div class="flex items-center gap-1 text-green-700 font-medium shrink-0">
                    <i class="fas fa-check-circle"></i>
                    {{ $stats->contados }} productos contados
                    @php $ultimo = $conteo->detalles()->whereNotNull('contado_at')->orderByDesc('contado_at')->first(); @endphp
                    @if($ultimo?->contado_at) — Último: {{ $ultimo->contado_at->format('d/m/Y H:i') }} @endif
                </div>
            @endif
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-blue-700">{{ $stats->contados }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Productos contados</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ number_format($stats->total_faltante_unidades) }} und</p>
                <p class="text-xs text-gray-500 mt-0.5">Unidades faltantes (total)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">S/ {{ number_format($valorStats->valor_compra ?? 0, 2) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Valor a precio compra</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-yellow-500">S/ {{ number_format($valorStats->valor_venta ?? 0, 2) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Valor a precio venta</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('inventario-fisico.show', $conteo) }}" class="flex flex-wrap gap-3 mb-4 items-end">
            <input type="text" name="buscar" value="{{ request('buscar') }}"
                   placeholder="Buscar producto o código..."
                   class="flex-1 min-w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">

            <select name="categoria_id"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Todas las categorías</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->nombre }}
                    </option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
                <div class="relative">
                    <input type="checkbox" name="solo_faltantes" value="1" id="soloFaltantes"
                           {{ request('solo_faltantes') ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="w-10 h-6 bg-gray-300 rounded-full peer-checked:bg-blue-600 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                </div>
                Solo faltantes
            </label>

            <button type="submit"
                    class="px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg text-sm font-semibold transition-colors">
                <i class="fas fa-filter mr-1"></i> Filtrar
            </button>
            <a href="{{ route('inventario-fisico.show', $conteo) }}"
               class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                <i class="fas fa-times"></i>
            </a>
        </form>

        {{-- Reiniciar button --}}
        <div class="flex justify-end mb-3">
            <form action="{{ route('inventario-fisico.reiniciar', $conteo) }}" method="POST"
                  onsubmit="return confirm('¿Reiniciar todo el conteo? Se borrarán todos los valores ingresados.')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-pink-600 hover:bg-pink-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-redo"></i>
                    Reiniciar conteo ({{ $stats->contados }} productos)
                </button>
            </form>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-24">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Producto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoría</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase w-16">Mín.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase w-28">Stock Sistema</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-32">Stock Físico</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-24">Faltante</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase w-24">P. Compra</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase w-28">Valor Faltante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($detalles as $det)
                            <tr class="hover:bg-gray-50" id="row-{{ $det->id }}">
                                <td class="px-4 py-3">
                                    <span class="text-xs font-mono text-blue-700 font-medium">
                                        {{ $det->producto->codigo }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800">
                                        {{ $det->producto->nombre }}
                                        @if($det->variante)
                                            <span class="text-xs text-gray-500">({{ $det->variante->nombre_completo }})</span>
                                        @endif
                                    </p>
                                    @if($det->contado_at)
                                        <p class="text-xs text-green-600 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-check-circle"></i>
                                            Contado {{ $det->contado_at->format('d/m H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $det->producto->categoria?->nombre ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">
                                    {{ $det->producto->stock_minimo ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">
                                    {{ number_format($det->stock_sistema) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number"
                                           id="sf-{{ $det->id }}"
                                           min="0"
                                           value="{{ $det->stock_fisico }}"
                                           placeholder="—"
                                           @blur="autoSave({{ $det->id }}, $event.target.value)"
                                           class="w-24 text-center px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500
                                                  {{ $det->stock_fisico !== null ? 'border-green-400 bg-green-50' : 'border-gray-300' }}"
                                           :class="saved[{{ $det->id }}] ? 'border-green-400 bg-green-50' : ''">
                                    <span x-show="saving[{{ $det->id }}]" class="ml-1 text-gray-400">
                                        <i class="fas fa-spinner fa-spin text-xs"></i>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center" id="dif-{{ $det->id }}">
                                    @if($det->diferencia !== null)
                                        @if($det->diferencia < 0)
                                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                                                <i class="fas fa-caret-down text-xs"></i> {{ abs($det->diferencia) }}
                                            </span>
                                        @elseif($det->diferencia > 0)
                                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                                +{{ $det->diferencia }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">0</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600 text-xs">
                                    S/ {{ number_format($det->producto->costo_promedio ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium" id="vf-{{ $det->id }}">
                                    @if($det->faltante > 0)
                                        <span class="text-red-600">S/ {{ number_format($det->valor_faltante, 2) }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                                    <i class="fas fa-search text-3xl mb-3 block"></i>
                                    No se encontraron productos con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($detalles->count() > 0)
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total faltante (vista actual):</td>
                                <td class="px-4 py-2 text-center text-sm font-bold text-red-600">
                                    {{ number_format($detalles->sum(fn($d) => $d->faltante)) }} und
                                </td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-red-600">
                                    S/ {{ number_format($detalles->sum(fn($d) => $d->valor_faltante), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if($detalles->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $detalles->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
    function conteoForm() {
        return {
            saving: {},
            saved: {},

            autoSave(detalleId, stockFisico) {
                this.saving[detalleId] = true;
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                fetch(`/inventario-fisico/{{ $conteo->id }}/detalles/${detalleId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        stock_fisico: stockFisico !== '' ? parseInt(stockFisico) : null,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    this.saving[detalleId] = false;
                    if (data.ok) {
                        this.saved[detalleId] = true;

                        // Update diferencia cell
                        const difCell = document.getElementById(`dif-${detalleId}`);
                        if (difCell) {
                            const dif = data.diferencia;
                            if (dif === null || dif === undefined) {
                                difCell.innerHTML = '<span class="text-gray-300">—</span>';
                            } else if (dif < 0) {
                                difCell.innerHTML = `<span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white"><i class="fas fa-caret-down text-xs"></i> ${Math.abs(dif)}</span>`;
                            } else if (dif > 0) {
                                difCell.innerHTML = `<span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">+${dif}</span>`;
                            } else {
                                difCell.innerHTML = '<span class="text-xs text-gray-400">0</span>';
                            }
                        }

                        // Update valor faltante cell
                        const vfCell = document.getElementById(`vf-${detalleId}`);
                        if (vfCell && data.faltante > 0) {
                            vfCell.innerHTML = `<span class="text-red-600">S/ ${data.valor_faltante}</span>`;
                        } else if (vfCell) {
                            vfCell.innerHTML = '<span class="text-gray-300">—</span>';
                        }

                        // Update input styling
                        const input = document.getElementById(`sf-${detalleId}`);
                        if (input && stockFisico !== '') {
                            input.classList.add('border-green-400', 'bg-green-50');
                            input.classList.remove('border-gray-300');
                        }
                    }
                })
                .catch(() => {
                    this.saving[detalleId] = false;
                });
            }
        };
    }
    </script>
</body>
</html>
