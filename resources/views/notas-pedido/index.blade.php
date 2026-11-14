<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notas de pedido - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Notas de pedido" subtitle="Lo que almacén debe dejar preparado cuando una cotización se confirma" />
        @include('cotizaciones.partials.mensajes')

        <div class="flex flex-wrap gap-2 mb-4">
            <a href="{{ route('notas-pedido.index') }}" class="px-4 py-1.5 text-sm rounded-full border {{ $estado === 'abiertas' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                Abiertas @if($abiertas)<span class="ml-1 text-xs font-bold">({{ $abiertas }})</span>@endif
            </a>
            @foreach(\App\Models\NotaPedido::ESTADOS as $k => [$nombre])
                <a href="{{ route('notas-pedido.index', ['estado' => $k]) }}" class="px-4 py-1.5 text-sm rounded-full border {{ $estado === $k ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $nombre }}</a>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrega</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Líneas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($notas as $n)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $n->codigo }}<p class="text-xs font-normal text-gray-400">Cotización {{ $n->cotizacion?->codigo }}</p></td>
                            <td class="px-4 py-3 text-gray-700">{{ $n->cliente?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $n->almacen?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $n->fecha_entrega?->format('d/m/Y') ?? 'Por coordinar' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $n->detalles_count }}</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ \App\Models\NotaPedido::ESTADOS[$n->estado][1] }}">{{ $n->estado_nombre }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('notas-pedido.show', $n) }}" class="text-blue-600 hover:text-blue-800" title="Ver"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No hay notas de pedido en esta vista. Se generan desde una cotización aceptada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $notas->links() }}</div>
    </div>
</body>
</html>
