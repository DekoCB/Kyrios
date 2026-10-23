<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traslados Pendientes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Traslados Pendientes"
            subtitle="Confirma la recepción de cada traslado"
        />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        {{-- Nav --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <a href="{{ route('traslados.index') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-exchange-alt"></i> Historial
            </a>
            <span class="text-gray-300">|</span>
            <span class="text-sm font-semibold text-yellow-600 flex items-center gap-1.5">
                <i class="fas fa-clock"></i> Pendientes
                @if($grupos->count() > 0)
                    <span class="bg-yellow-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold">
                        {{ $grupos->count() }}
                    </span>
                @endif
            </span>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.stock') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-boxes"></i> Stock por Almacén
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.create') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-plus-circle"></i> Nuevo Traslado
            </a>
        </div>

        @forelse($grupos as $guia => $movimientos)
        @php
            $primero        = $movimientos->first();
            $diasEnTransito = $primero->created_at->diffInDays(now());
            $urgente        = $diasEnTransito >= 3;
            $tieneImeis     = $movimientos->contains(fn($m) => $m->producto->tipo_inventario === 'serie');
            $sinImeis       = $movimientos->filter(fn($m) => $m->producto->tipo_inventario === 'serie')
                                          ->contains(fn($m) => $m->imeisTrasladados->isEmpty());
        @endphp

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-4 overflow-hidden"
             x-data="{ detalleAbierto: false }">

            {{-- ── Cabecera ── --}}
            <div class="px-6 py-4">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">

                    <div class="flex-1 min-w-0">
                        {{-- Badges --}}
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="font-mono font-bold text-blue-700 text-sm bg-blue-50 px-2.5 py-0.5 rounded-lg border border-blue-200">
                                <i class="fas fa-file-alt mr-1 text-blue-400 text-xs"></i>{{ str_starts_with($guia, 'id:') ? 'Sin guía' : $guia }}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-purple-100 text-purple-700">
                                <i class="fas fa-boxes mr-1"></i>{{ $movimientos->count() }} producto(s)
                            </span>
                            @if($tieneImeis)
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-purple-100 text-purple-700">
                                    <i class="fas fa-barcode mr-1"></i>Con IMEI
                                </span>
                            @endif
                            @if($urgente)
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-red-100 text-red-600">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $diasEnTransito }}d en tránsito
                                </span>
                            @endif
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-yellow-100 text-yellow-700">
                                <i class="fas fa-clock mr-1"></i>Pendiente
                            </span>
                        </div>

                        {{-- Ruta --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-warehouse text-orange-400 text-xs"></i>
                                <strong class="text-gray-800">{{ $primero->almacen->nombre }}</strong>
                            </span>
                            <i class="fas fa-long-arrow-alt-right text-gray-300"></i>
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-store text-green-400 text-xs"></i>
                                <strong class="text-gray-800">{{ $primero->almacenDestino->nombre ?? '—' }}</strong>
                            </span>
                            <span class="text-gray-300">·</span>
                            <span class="text-xs text-gray-400">
                                <i class="fas fa-calendar mr-1"></i>{{ $primero->created_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-gray-300">·</span>
                            <span class="text-xs text-gray-400">
                                Por <strong>{{ $primero->usuario->name }}</strong>
                            </span>
                        </div>

                        {{-- Resumen de productos --}}
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($movimientos as $mov)
                                @php $esS = $mov->producto->tipo_inventario === 'serie'; @endphp
                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-lg border font-medium
                                    {{ $esS ? 'bg-purple-50 border-purple-200 text-purple-700' : 'bg-blue-50 border-blue-200 text-blue-700' }}">
                                    @if($esS)
                                        <i class="fas fa-barcode text-[10px]"></i>
                                    @else
                                        <i class="fas fa-box text-[10px]"></i>
                                    @endif
                                    {{ $mov->producto->nombre }}
                                    <span class="font-mono text-gray-400">
                                        @if($esS)
                                            ({{ $mov->imeisTrasladados->count() }} IMEI)
                                        @else
                                            ×{{ $mov->cantidad }}
                                        @endif
                                    </span>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <button type="button"
                                @click="detalleAbierto = !detalleAbierto"
                                class="flex items-center gap-1.5 px-3.5 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-info-circle text-blue-400 text-xs"></i>
                            <span x-text="detalleAbierto ? 'Ocultar' : 'Ver detalle'"></span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" :class="{ 'rotate-180': detalleAbierto }"></i>
                        </button>

                        <form action="{{ route('traslados.confirmar', $primero) }}" method="POST"
                              onsubmit="return confirm('¿Confirmar la recepción del traslado {{ str_starts_with($guia, "id:") ? "" : $guia }}?\n\n{{ $movimientos->count() }} producto(s) de {{ $primero->almacen->nombre }} → {{ $primero->almacenDestino->nombre ?? "destino" }}')">
                            @csrf
                            @if($sinImeis)
                                <button type="button" disabled title="Hay productos IMEI sin IMEIs asignados"
                                        class="flex items-center gap-1.5 bg-gray-300 cursor-not-allowed text-white font-semibold px-5 py-2 rounded-lg text-sm">
                                    <i class="fas fa-exclamation-triangle"></i> Sin IMEIs
                                </button>
                            @else
                                <button type="submit"
                                        class="flex items-center gap-1.5 bg-green-500 hover:bg-green-600 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                                    <i class="fas fa-check-double"></i> Confirmar Recepción
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── Panel detalle ── --}}
            <div x-show="detalleAbierto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0"
                 class="border-t border-gray-100 bg-gray-50/50"
                 style="display:none">

                <div class="px-6 py-5 space-y-4">

                    @foreach($movimientos as $mov)
                    @php $esS = $mov->producto->tipo_inventario === 'serie'; @endphp
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        {{-- Producto header --}}
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50">
                            <div class="flex items-center gap-2">
                                @if($esS)
                                    <span class="text-xs font-semibold bg-purple-100 text-purple-700 px-2 py-0.5 rounded">
                                        <i class="fas fa-barcode mr-1"></i>IMEI
                                    </span>
                                @else
                                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                                        <i class="fas fa-box mr-1"></i>Accesorio
                                    </span>
                                @endif
                                <span class="font-semibold text-sm text-gray-800">{{ $mov->producto->nombre }}</span>
                                <span class="font-mono text-xs text-gray-400">{{ $mov->producto->codigo }}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-700">
                                {{ $mov->cantidad }} {{ $esS ? 'IMEI(s)' : 'unid.' }}
                            </span>
                        </div>

                        <div class="px-4 py-3">
                            @if($esS)
                                {{-- IMEIs asignados --}}
                                @if($mov->imeisTrasladados->isEmpty())
                                    <p class="text-xs text-red-600 flex items-center gap-1">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Sin IMEIs asignados — no se puede confirmar.
                                    </p>
                                @else
                                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                        IMEIs asignados → se moverán a {{ $mov->almacenDestino->nombre ?? 'destino' }}
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($mov->imeisTrasladados as $ti)
                                            <span class="font-mono text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-1 rounded-lg flex items-center gap-1">
                                                <i class="fas fa-barcode text-[9px] text-indigo-400"></i>
                                                {{ $ti->imei->codigo_imei ?? '—' }}
                                                @if($ti->imei?->serie)
                                                    <span class="text-indigo-400">· {{ $ti->imei->serie }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-cubes mr-1 text-gray-400"></i>
                                    <strong>{{ $mov->cantidad }}</strong> unidades de
                                    <strong>{{ $mov->almacen->nombre }}</strong> → <strong>{{ $mov->almacenDestino->nombre ?? '—' }}</strong>
                                    <span class="text-gray-400 ml-1">(stock ya descontado en origen)</span>
                                </p>
                            @endif
                        </div>
                    </div>
                    @endforeach

                    @if($primero->transportista)
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-truck mr-1 text-gray-400"></i>
                            Transportista: <strong>{{ $primero->transportista }}</strong>
                        </p>
                    @endif
                    @if($primero->observaciones)
                        <p class="text-xs text-gray-500 italic">
                            <i class="fas fa-comment-alt mr-1 text-gray-400"></i>
                            "{{ $primero->observaciones }}"
                        </p>
                    @endif

                    {{-- Urgente aviso --}}
                    @if($urgente)
                        <div class="flex items-center gap-2 text-xs text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">
                            <i class="fas fa-exclamation-triangle shrink-0"></i>
                            {{ $diasEnTransito }} día(s) en tránsito — confirmar urgente.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-2xl shadow-sm p-14 text-center border border-gray-100">
            <div class="bg-green-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-4xl text-green-400"></i>
            </div>
            <p class="text-lg font-semibold text-gray-600">No hay traslados pendientes</p>
            <p class="text-sm text-gray-400 mt-1">Todos los traslados han sido confirmados</p>
            <a href="{{ route('traslados.create') }}"
               class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition-colors">
                <i class="fas fa-plus-circle"></i>Nuevo Traslado
            </a>
        </div>
        @endforelse
    </div>
</body>
</html>
