<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Traslado - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Detalle del Traslado" subtitle="Trazabilidad completa del traslado" />

        @php
            $colores   = [
                'pendiente'  => 'bg-yellow-100 text-yellow-800',
                'confirmado' => 'bg-green-100 text-green-800',
                'anulado'    => 'bg-red-100 text-red-800',
            ];
            $esGuia    = !str_starts_with($traslado->numero_guia ?? 'id:', 'id:');
            $titulo    = $esGuia ? $traslado->numero_guia : '# ' . $traslado->id;
            $puedeAnular = $traslado->estado === 'pendiente';
        @endphp

        <div class="flex items-center justify-between mb-6" x-data="{ showAnular: false }">
            <div class="flex items-center gap-3">
                <a href="{{ route('traslados.index') }}" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h2 class="text-2xl font-bold text-gray-800">Traslado {{ $titulo }}</h2>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $colores[$traslado->estado] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucfirst($traslado->estado) }}
                </span>
            </div>

            @if($puedeAnular)
            <button @click="showAnular = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-semibold rounded-lg transition-colors">
                <i class="fas fa-ban"></i> Anular Traslado
            </button>

            {{-- Modal de anulación --}}
            <div x-show="showAnular" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAnular = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-ban text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Anular Traslado</h3>
                            <p class="text-sm text-gray-500">{{ $titulo }}</p>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Se revertira todo el stock: las cantidades volveran al almacen origen.
                    </div>

                    <form action="{{ route('traslados.anular', $traslado) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Motivo de anulacion
                            </label>
                            <textarea name="motivo" rows="3"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 resize-none"
                                      placeholder="Describe el motivo de la anulacion..."></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="showAnular = false"
                                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                                <i class="fas fa-ban mr-1"></i> Confirmar Anulacion
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- Botón Guía de Remisión --}}
        @if($guia)
        <div class="flex items-center gap-3 mb-5 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
            <i class="fas fa-file-invoice text-emerald-600 text-lg"></i>
            <div class="flex-1">
                <p class="text-sm font-semibold text-emerald-800">Guía de Remisión registrada</p>
                <p class="text-xs text-emerald-600">
                    Motivo: {{ $guia->motivo_label }} · {{ $guia->modalidad_label }} · {{ $guia->fecha_traslado?->format('d/m/Y') }}
                </p>
            </div>
            <a href="{{ route('traslados.guia-pdf', $traslado->id) }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold rounded-lg transition-colors">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </a>
        </div>
        @else
        <div class="flex items-center gap-3 mb-5 p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <i class="fas fa-exclamation-triangle text-amber-500 text-lg"></i>
            <p class="text-sm text-amber-800">Este traslado no tiene guía de remisión registrada.</p>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            {{-- Datos del traslado --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4 flex items-center gap-2">
                    <i class="fas fa-exchange-alt text-blue-400"></i> Datos del Traslado
                </h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">N° Guía</dt>
                        <dd class="font-mono font-semibold text-blue-700">{{ $traslado->numero_guia ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Origen</dt>
                        <dd class="text-gray-800 flex items-center gap-1">
                            <i class="fas fa-warehouse text-orange-400 text-xs"></i>
                            {{ $traslado->almacen->nombre }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Destino</dt>
                        <dd class="text-gray-800 flex items-center gap-1">
                            <i class="fas fa-store text-green-400 text-xs"></i>
                            {{ $traslado->almacenDestino->nombre ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Total productos</dt>
                        <dd class="font-semibold text-gray-800">{{ $todosProductos->count() }}</dd>
                    </div>
                    @if($traslado->transportista)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Transportista</dt>
                        <dd class="text-gray-700">{{ $traslado->transportista }}</dd>
                    </div>
                    @endif
                    @if($traslado->observaciones)
                    <div class="pt-1">
                        <dt class="text-gray-500 text-sm mb-1">Observaciones</dt>
                        <dd class="text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 italic text-xs">
                            "{{ $traslado->observaciones }}"
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Estado y seguimiento --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4 flex items-center gap-2">
                    <i class="fas fa-route text-green-400"></i> Estado y Seguimiento
                </h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Estado</dt>
                        <dd>
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $colores[$traslado->estado] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($traslado->estado) }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Creado por</dt>
                        <dd class="text-gray-700">{{ $traslado->usuario->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Fecha envío</dt>
                        <dd class="text-gray-700">
                            {{ $traslado->fecha_traslado
                                ? \Carbon\Carbon::parse($traslado->fecha_traslado)->format('d/m/Y')
                                : $traslado->created_at->format('d/m/Y H:i') }}
                        </dd>
                    </div>
                    @if($traslado->usuarioConfirma)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Confirmado por</dt>
                        <dd class="text-gray-700">{{ $traslado->usuarioConfirma->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 text-sm">Fecha recepción</dt>
                        <dd class="text-gray-700">{{ $traslado->fecha_recepcion }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- ══ Detalle por producto ══ --}}
        <div class="space-y-4">

            @foreach($todosProductos as $i => $mov)
            <div class="bg-white rounded-xl shadow-md overflow-hidden">

                {{-- Header producto --}}
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-blue-50">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700">
                            <i class="fas fa-box mr-1"></i>Producto
                        </span>
                        <span class="font-semibold text-gray-800">{{ $mov->producto->nombre }}</span>
                        <span class="font-mono text-xs text-gray-400">{{ $mov->producto->codigo }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-gray-700">
                            {{ $mov->cantidad }} unid.
                        </span>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $colores[$mov->estado] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($mov->estado) }}
                        </span>
                    </div>
                </div>

                <div class="p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Cantidad</p>
                                <p class="font-bold text-gray-800">{{ $mov->cantidad }} unidades</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Stock origen</p>
                                <p class="text-gray-700">
                                    <span class="line-through text-gray-400">{{ $mov->stock_anterior }}</span>
                                    → <strong class="text-orange-600">{{ $mov->stock_nuevo }}</strong>
                                </p>
                            </div>
                            @if($mov->estado === 'confirmado')
                            <div>
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Destino</p>
                                <p class="text-green-700 font-medium flex items-center gap-1">
                                    <i class="fas fa-check-circle text-green-500 text-xs"></i>
                                    Acreditado en {{ $mov->almacenDestino->nombre ?? '—' }}
                                </p>
                            </div>
                            @endif
                        </div>
                </div>
            </div>
            @endforeach
        </div>

    </div>
</body>
</html>
