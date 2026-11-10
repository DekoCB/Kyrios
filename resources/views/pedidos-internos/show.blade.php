<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido interno {{ $pedido->codigo }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="'Pedido interno ' . $pedido->codigo" subtitle="Nota de pedido interno entre almacenes" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        @php
            $ep = ['pendiente' => 'bg-yellow-100 text-yellow-800', 'atendido' => 'bg-green-100 text-green-800', 'cancelado' => 'bg-red-100 text-red-800'][$pedido->estado] ?? 'bg-gray-100 text-gray-800';
            $faltan = $pedido->detalles->filter(fn ($d) => ($stock[$d->id] ?? 0) < $d->cantidad)->count();
        @endphp

        <div class="max-w-5xl">
            <div class="mb-6 flex justify-between items-center">
                <a href="{{ route('pedidos-internos.index') }}" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-arrow-left mr-1"></i>Volver a pedidos internos</a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('pedidos-internos.pdf', $pedido) }}" target="_blank" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">
                        <i class="fas fa-file-pdf mr-1"></i>Imprimir PDF
                    </a>
                    <span class="inline-flex px-3 py-1.5 text-sm font-semibold rounded-full {{ $ep }}">{{ ucfirst($pedido->estado) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-info-circle mr-2 text-blue-600"></i>Datos del pedido</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Código:</dt><dd class="font-mono font-bold text-blue-900">{{ $pedido->codigo }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Lo pide:</dt><dd class="font-semibold">{{ $pedido->destino?->nombre }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Lo atiende:</dt><dd class="font-semibold">{{ $pedido->origen?->nombre }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Área / proyecto:</dt><dd>{{ $pedido->area ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Solicitante:</dt><dd>{{ $pedido->solicitante?->name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Fecha del pedido:</dt><dd>{{ $pedido->fecha->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Requerido para:</dt>
                            <dd class="{{ $pedido->vencido ? 'text-red-600 font-semibold' : '' }}">{{ $pedido->fecha_requerida?->format('d/m/Y') ?? '—' }}@if($pedido->vencido) (vencido)@endif</dd></div>
                        @if($pedido->estado === 'atendido')
                            <div class="flex justify-between"><dt class="text-gray-500">Traslado:</dt><dd class="font-mono">{{ $pedido->traslado_guia }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Atendido por:</dt><dd>{{ $pedido->atendidoPor?->name }} · {{ $pedido->atendido_at?->format('d/m/Y H:i') }}</dd></div>
                        @endif
                    </dl>
                    @if($pedido->observaciones)
                        <p class="mt-4 text-sm text-gray-600"><span class="font-semibold">Observaciones:</span> {{ $pedido->observaciones }}</p>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-500 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-tasks mr-2 text-purple-600"></i>Acciones</h3>
                    @if($pedido->estado === 'pendiente')
                        <div class="space-y-3">
                            @if($atiende)
                                @if($faltan > 0)
                                    <p class="text-sm rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2">
                                        <i class="fas fa-triangle-exclamation mr-1"></i>En {{ $pedido->origen?->nombre }} no alcanza el stock de {{ $faltan }} producto(s). Atenderlo fallará hasta que haya stock.
                                    </p>
                                @endif
                                <form method="POST" action="{{ route('pedidos-internos.atender', $pedido) }}"
                                      data-confirm="¿Atender este pedido? Se registrará el traslado de {{ $pedido->origen?->nombre }} a {{ $pedido->destino?->nombre }}.">
                                    @csrf
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg">
                                        <i class="fas fa-truck-ramp-box mr-2"></i>Atender pedido (generar traslado)
                                    </button>
                                </form>
                                @if(auth()->user()->role?->nombre !== 'Administración')
                                    <p class="text-xs text-gray-400">Como el traslado mueve stock, queda pendiente de aprobación del administrador.</p>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('pedidos-internos.cancelar', $pedido) }}" data-confirm="¿Cancelar este pedido? No se puede deshacer.">
                                @csrf
                                <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-700 font-semibold py-2.5 px-4 rounded-lg border border-red-200">
                                    <i class="fas fa-times mr-2"></i>Cancelar pedido
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 text-center py-6"><i class="fas fa-lock mr-1"></i>Pedido {{ $pedido->estado }}.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                <div class="bg-blue-900 px-6 py-4"><h2 class="text-lg font-bold text-white"><i class="fas fa-boxes mr-2"></i>Productos pedidos</h2></div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            @if($atiende && $pedido->estado === 'pendiente')
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock en {{ $pedido->origen?->nombre }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pedido->detalles as $i => $d)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="font-medium text-gray-900">{{ $d->producto ? \App\Support\EtiquetaProducto::nombre($d->producto) : '—' }}</span>
                                    <span class="block text-xs text-gray-400 font-mono">{{ $d->variante?->codigo_kyrios ?? $d->producto?->codigo }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-center font-bold">{{ $d->cantidad }}</td>
                                @if($atiende && $pedido->estado === 'pendiente')
                                    @php $hay = $stock[$d->id] ?? 0; @endphp
                                    <td class="px-6 py-4 text-sm text-center font-semibold {{ $hay >= $d->cantidad ? 'text-green-600' : 'text-red-600' }}">{{ $hay }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
