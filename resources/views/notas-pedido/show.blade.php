<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $nota->codigo }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Nota de pedido {{ $nota->codigo }}" subtitle="De la cotización {{ $nota->cotizacion?->codigo }} · generada por {{ $nota->creador?->name ?? '—' }} el {{ $nota->created_at->format('d/m/Y') }}" />
        @include('cotizaciones.partials.mensajes')

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('notas-pedido.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            <a href="{{ route('notas-pedido.pdf', $nota) }}" target="_blank" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-pdf text-red-500"></i> Hoja de preparación</a>
            @if($puedeAtender)
                @foreach(['en_preparacion' => ['Empezar a preparar', 'fa-box-open', 'pendiente'], 'entregada' => ['Marcar entregada', 'fa-truck-ramp-box', 'lista']] as $destino => [$texto, $icono, $desde])
                    @if($nota->estado === $desde)
                        <form method="POST" action="{{ route('notas-pedido.estado', $nota) }}">@csrf <input type="hidden" name="estado" value="{{ $destino }}">
                            <button class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm"><i class="fas {{ $icono }}"></i> {{ $texto }}</button>
                        </form>
                    @endif
                @endforeach
                @if($nota->esta_abierta)
                    <form method="POST" action="{{ route('notas-pedido.estado', $nota) }}" class="ml-auto" data-confirm="¿Anular esta nota de pedido?">@csrf <input type="hidden" name="estado" value="anulada">
                        <button class="inline-flex items-center gap-2 border border-red-200 bg-white text-red-700 hover:bg-red-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-ban"></i> Anular</button>
                    </form>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Estado</p><p class="mt-1"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ \App\Models\NotaPedido::ESTADOS[$nota->estado][1] }}">{{ $nota->estado_nombre }}</span></p></div>
            <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Cliente</p><p class="font-semibold text-gray-900">{{ $nota->cliente?->nombre ?? '—' }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Almacén</p><p class="font-semibold text-gray-900">{{ $nota->almacen?->nombre ?? '—' }}</p></div>
            <div class="bg-white rounded-xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Entrega</p><p class="font-semibold text-gray-900">{{ $nota->fecha_entrega?->format('d/m/Y') ?? 'Por coordinar' }}</p></div>
        </div>

        @if($nota->observaciones)
            <p class="mb-4 text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><strong>Indicaciones:</strong> {{ $nota->observaciones }}</p>
        @endif

        @php $editable = $puedeAtender && in_array($nota->estado, ['pendiente', 'en_preparacion'], true); @endphp
        <form method="POST" action="{{ route('notas-pedido.preparar', $nota) }}" class="bg-white rounded-xl shadow-sm overflow-x-auto">
            @csrf
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pedido</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">En almacén</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Preparado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($nota->detalles as $d)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $d->producto ? \App\Support\EtiquetaProducto::documento($d->producto, $d->variante) : $d->descripcion }}@if($d->variante && $d->variante->nombre_completo !== 'Variante base') — {{ $d->variante->nombre_completo }}@endif</p>
                                @if($d->variante?->codigo_kyrios)<p class="text-xs font-mono text-gray-400">{{ $d->variante->codigo_kyrios }}</p>@endif
                            </td>
                            <td class="px-4 py-3 text-center font-semibold">{{ $d->cantidad }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($stock[$d->id] === null) <span class="text-gray-400">—</span>
                                @else <span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $stock[$d->id] >= $d->cantidad ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">{{ $stock[$d->id] }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($editable)
                                    <input type="number" name="preparado[{{ $d->id }}]" min="0" max="{{ $d->cantidad }}" value="{{ $d->preparado }}" class="w-20 px-2 py-1 border border-gray-300 rounded-lg text-center text-sm">
                                @else
                                    <span class="font-semibold {{ $d->completo ? 'text-green-700' : 'text-gray-700' }}">{{ $d->preparado }} / {{ $d->cantidad }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($editable)
                <div class="flex justify-end p-4 border-t border-gray-100">
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2 rounded-lg"><i class="fas fa-save mr-1"></i>Guardar preparación</button>
                </div>
            @endif
        </form>
        <p class="text-xs text-gray-400 mt-2">Cuando todas las líneas estén preparadas, la nota pasa sola a «Lista para entregar» y se avisa a quien la generó.</p>
    </div>
</body>
</html>
