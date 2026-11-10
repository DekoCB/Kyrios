<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Orden de compra {{ $pedido->codigo }}" 
            :subtitle="$pedido->es_internacional ? 'Proveedor del exterior' : 'Proveedor nacional'" 
        />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <div class="max-w-5xl">
            <div class="mb-6 flex justify-between items-center">
                <a href="{{ route('pedidos.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Volver a órdenes de compra
                </a>
                @php
                    $ep = match($pedido->estado) {
                        'pendiente' => 'bg-yellow-100 text-yellow-800',
                        'aprobado' => 'bg-blue-100 text-blue-800',
                        'recibido' => 'bg-green-100 text-green-800',
                        'cancelado' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                @endphp
                <div class="flex items-center gap-3">
                    <a href="{{ route('pedidos.pdf', $pedido) }}" target="_blank"
                       class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">
                        <i class="fas fa-file-pdf mr-1"></i>Imprimir PDF
                    </a>
                    <span class="inline-flex px-3 py-1.5 text-sm font-semibold rounded-full {{ $ep }}">
                        {{ ucfirst($pedido->estado) }}
                    </span>
                </div>
            </div>

            {{-- Info y Cambio de Estado --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>Información de la orden
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Código:</dt><dd class="font-mono font-bold text-blue-900">{{ $pedido->codigo }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Proveedor:</dt>
                            <dd class="font-semibold">
                                <a href="{{ route('proveedores.show', $pedido->proveedor) }}" class="text-blue-600 hover:underline">
                                    {{ $pedido->proveedor->razon_social ?? '-' }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-gray-500">Tipo:</dt><dd class="font-semibold">{{ $pedido->es_internacional ? 'Internacional' : 'Nacional' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Moneda:</dt><dd>{{ $pedido->moneda === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)' }}@if($pedido->tipo_cambio) · T.C. {{ number_format((float) $pedido->tipo_cambio, 3) }}@endif</dd></div>
                        @if($pedido->incoterm)
                            <div class="flex justify-between"><dt class="text-gray-500">Incoterm:</dt><dd>{{ $pedido->incoterm }}</dd></div>
                        @endif
                        @if($pedido->condicion_pago)
                            <div class="flex justify-between"><dt class="text-gray-500">Condición de pago:</dt><dd>{{ $pedido->condicion_pago }}</dd></div>
                        @endif
                        @if($pedido->lugar_entrega)
                            <div class="flex justify-between"><dt class="text-gray-500">Lugar de entrega:</dt><dd>{{ $pedido->lugar_entrega }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-gray-500">Almacén destino:</dt><dd class="font-semibold">{{ $pedido->almacen->nombre ?? 'No especificado' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fecha:</dt><dd>{{ $pedido->fecha->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fecha Esperada:</dt><dd>{{ $pedido->fecha_esperada ? $pedido->fecha_esperada->format('d/m/Y') : 'No especificada' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Creado por:</dt><dd>{{ $pedido->usuario->name ?? '-' }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-exchange-alt mr-2 text-purple-600"></i>Cambiar Estado
                    </h3>

                    @if($pedido->estado !== 'cancelado' && $pedido->estado !== 'recibido')
                        <div class="space-y-3">
                            @if($pedido->estado === 'pendiente')
                                <form action="{{ route('pedidos.cambiar-estado', $pedido) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="aprobado">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors"
                                            data-confirm="¿Aprobar este pedido?">
                                        <i class="fas fa-check mr-2"></i>Aprobar Pedido
                                    </button>
                                </form>
                            @endif

                            @if($pedido->estado === 'aprobado')
                                <a href="{{ route('compras.create', ['pedido_id' => $pedido->id]) }}"
                                   class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors">
                                    <i class="fas fa-box-open mr-2"></i>Recibir Mercadería
                                </a>
                                <p class="text-xs text-gray-400 text-center -mt-1">
                                    Te lleva a registrar la Compra real (factura, precios finales). El pedido pasará a "Recibido" automáticamente al guardarla.
                                </p>
                            @endif

                            <form action="{{ route('pedidos.cambiar-estado', $pedido) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="cancelado">
                                <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-700 font-semibold py-2.5 px-4 rounded-lg transition-colors border border-red-200"
                                        data-confirm="¿Cancelar este pedido? Esta acción no se puede deshacer.">
                                    <i class="fas fa-times mr-2"></i>Cancelar Pedido
                                </button>
                            </form>
                        </div>
                    @elseif($pedido->estado === 'recibido')
                        <div class="text-center py-6">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-2"></i>
                            <p class="text-sm text-gray-500">Mercadería recibida.</p>
                            @if($pedido->compra)
                                <a href="{{ route('compras.show', $pedido->compra) }}" class="inline-block mt-2 text-sm text-blue-600 hover:underline">
                                    <i class="fas fa-file-invoice mr-1"></i>Ver Compra {{ $pedido->compra->numero_factura }}
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-6 text-gray-400">
                            <i class="fas fa-lock text-3xl mb-2"></i>
                            <p class="text-sm">Pedido {{ $pedido->estado }}.<br>No se puede cambiar.</p>
                        </div>
                    @endif

                    {{-- Timeline de estados --}}
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <p class="text-xs font-semibold text-gray-400 mb-3 uppercase tracking-wider">Flujo de Estados</p>
                        <div class="flex items-center justify-between text-xs">
                            @php
                                $estados = ['pendiente', 'aprobado', 'recibido'];
                                $estadoActualIdx = array_search($pedido->estado, $estados);
                                if ($pedido->estado === 'cancelado') $estadoActualIdx = -1;
                            @endphp
                            @foreach($estados as $idx => $estado)
                                <div class="flex flex-col items-center">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm
                                        {{ $idx <= $estadoActualIdx ? 'bg-green-500' : 'bg-gray-300' }}">
                                        @if($idx <= $estadoActualIdx)
                                            <i class="fas fa-check"></i>
                                        @else
                                            {{ $idx + 1 }}
                                        @endif
                                    </div>
                                    <span class="mt-1.5 font-medium {{ $idx <= $estadoActualIdx ? 'text-green-600' : 'text-gray-400' }}">
                                        {{ ucfirst($estado) }}
                                    </span>
                                </div>
                                @if(!$loop->last)
                                    <div class="flex-1 h-1 rounded {{ $idx < $estadoActualIdx ? 'bg-green-500' : 'bg-gray-200' }} mx-2 mb-5"></div>
                                @endif
                            @endforeach
                        </div>
                        @if($pedido->estado === 'cancelado')
                            <p class="text-center text-red-500 text-xs mt-3 font-bold">
                                <i class="fas fa-ban mr-1"></i>Pedido cancelado
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detalle de Productos --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-lg font-bold text-white">
                        <i class="fas fa-boxes mr-2"></i>Productos de la orden
                    </h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">P. unitario</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pedido->detalles as $i => $detalle)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="font-medium text-gray-900">{{ $detalle->producto ? \App\Support\EtiquetaProducto::nombre($detalle->producto) : '-' }}</span>
                                    <span class="text-gray-400 text-xs ml-1">({{ $detalle->producto->codigo ?? '' }})</span>
                                    @if($detalle->variante)
                                        <span class="block text-xs text-indigo-600 mt-0.5">
                                            <i class="fas fa-tag mr-1"></i>{{ $detalle->variante->nombre_completo }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-center font-bold">{{ $detalle->cantidad }}</td>
                                <td class="px-6 py-4 text-sm text-right">
                                    @if($detalle->precio_referencial)
                                        {{ $pedido->simbolo_moneda }} {{ number_format($detalle->precio_referencial, 2) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-right">{{ $pedido->simbolo_moneda }} {{ number_format($detalle->cantidad * (float) $detalle->precio_referencial, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 text-sm">
                        <tr><td colspan="4" class="px-6 py-2 text-right text-gray-500">Subtotal</td><td class="px-6 py-2 text-right">{{ $pedido->simbolo_moneda }} {{ number_format((float) $pedido->subtotal, 2) }}</td></tr>
                        @unless($pedido->es_internacional)
                            <tr><td colspan="4" class="px-6 py-2 text-right text-gray-500">IGV (18%)</td><td class="px-6 py-2 text-right">{{ $pedido->simbolo_moneda }} {{ number_format((float) $pedido->igv, 2) }}</td></tr>
                        @endunless
                        <tr><td colspan="4" class="px-6 py-3 text-right font-bold text-gray-800">Total</td><td class="px-6 py-3 text-right font-bold text-gray-900">{{ $pedido->simbolo_moneda }} {{ number_format((float) $pedido->total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Seguimiento por etapas --}}
            @if($pedido->etapas->isNotEmpty())
                @php $actual = $pedido->etapa_actual; @endphp
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6" id="seguimiento">
                    <div class="bg-blue-900 px-6 py-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-bold text-white"><i class="fas fa-route mr-2"></i>Seguimiento de la orden</h2>
                        <span class="text-sm text-blue-100">
                            @if($actual)
                                Va en: <strong>{{ $actual->nombre }}</strong>
                                @if($actual->dias_restantes !== null)
                                    · {{ $actual->dias_restantes < 0 ? 'atrasada ' . abs($actual->dias_restantes) . ' día(s)' : ($actual->dias_restantes === 0 ? 'es hoy' : 'faltan ' . $actual->dias_restantes . ' día(s)') }}
                                @endif
                            @else
                                Todas las etapas cumplidas
                            @endif
                        </span>
                    </div>

                    <form method="POST" action="{{ route('pedidos.seguimiento.guardar', $pedido) }}">
                        @csrf @method('PUT')
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Etapa</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha prevista</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situación</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($pedido->etapas as $e)
                                        <tr class="{{ $e->completada ? 'bg-green-50/50' : '' }}">
                                            <td class="px-4 py-3 font-medium text-gray-800">
                                                @if($e->completada)<i class="fas fa-check-circle text-green-500 mr-1"></i>@else<i class="far fa-circle text-gray-300 mr-1"></i>@endif
                                                {{ $e->nombre }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="date" name="etapas[{{ $e->id }}][fecha_prevista]" value="{{ $e->fecha_prevista?->format('Y-m-d') }}"
                                                       aria-label="Fecha prevista de {{ $e->nombre }}"
                                                       class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($e->completada)
                                                    <span class="text-green-700">Cumplida el {{ $e->fecha_real->format('d/m/Y') }}</span>
                                                @elseif($e->dias_restantes === null)
                                                    <span class="text-gray-400">Sin fecha</span>
                                                @elseif($e->dias_restantes < 0)
                                                    <span class="text-red-600 font-semibold">Atrasada {{ abs($e->dias_restantes) }} día(s)</span>
                                                @elseif($e->dias_restantes === 0)
                                                    <span class="text-amber-600 font-semibold">Es hoy</span>
                                                @else
                                                    <span class="text-gray-600">Faltan {{ $e->dias_restantes }} día(s)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" name="etapas[{{ $e->id }}][notas]" value="{{ $e->notas }}" maxlength="255" aria-label="Notas de {{ $e->nombre }}"
                                                       class="w-full min-w-[10rem] py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                                            </td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                @if($e->completada)
                                                    <button type="submit" form="reabrir-{{ $e->id }}" class="text-xs text-gray-500 hover:text-gray-800 underline">Reabrir</button>
                                                @else
                                                    <button type="submit" form="completar-{{ $e->id }}" class="text-xs font-semibold text-green-700 border border-green-300 hover:bg-green-50 rounded-lg px-2.5 py-1">
                                                        <i class="fas fa-check mr-1"></i>Cumplida hoy
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                                <i class="fas fa-save mr-1"></i>Guardar fechas y notas
                            </button>
                        </div>
                    </form>

                    @foreach($pedido->etapas as $e)
                        @if($e->completada)
                            <form id="reabrir-{{ $e->id }}" method="POST" action="{{ route('pedidos.seguimiento.reabrir', [$pedido, $e]) }}">@csrf</form>
                        @else
                            <form id="completar-{{ $e->id }}" method="POST" action="{{ route('pedidos.seguimiento.completar', [$pedido, $e]) }}">@csrf</form>
                        @endif
                    @endforeach
                    <p class="px-6 pb-4 text-xs text-gray-400">El sistema avisa cuando una etapa está a 3 días de su fecha prevista y cuando se atrasa.</p>
                </div>
            @endif

            @if($pedido->observaciones)
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-yellow-400">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Observaciones
                    </h3>
                    <p class="text-gray-600 text-sm">{{ $pedido->observaciones }}</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>