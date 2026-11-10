<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos internos - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Pedidos internos" :subtitle="$atiende ? 'Lo que piden los almacenes y áreas de la empresa' : 'Tus pedidos a otros almacenes'" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-500 p-5">
                <p class="text-sm text-gray-500">Pendientes</p><p class="text-3xl font-bold text-gray-800">{{ $stats['pendiente'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-5">
                <p class="text-sm text-gray-500">Vencidos (pasó la fecha requerida)</p><p class="text-3xl font-bold text-gray-800">{{ $stats['vencido'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-5">
                <p class="text-sm text-gray-500">Atendidos</p><p class="text-3xl font-bold text-gray-800">{{ $stats['atendido'] }}</p>
            </div>
        </div>

        <form method="GET" class="bg-white rounded-xl shadow-sm p-5 mb-6 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <label for="buscar" class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                <input type="text" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="PI-00001, área o solicitante"
                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="estado" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                <select id="estado" name="estado" class="py-2 px-3 text-sm border border-gray-300 rounded-lg bg-white">
                    <option value="">Todos</option>
                    @foreach(['pendiente' => 'Pendiente', 'atendido' => 'Atendido', 'cancelado' => 'Cancelado'] as $k => $v)
                        <option value="{{ $k }}" {{ request('estado') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-search mr-1"></i>Filtrar</button>
            @if(request()->hasAny(['buscar', 'estado']))
                <a href="{{ route('pedidos-internos.index') }}" class="text-sm text-gray-500 px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50"><i class="fas fa-times mr-1"></i>Limpiar</a>
            @endif
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-dolly mr-2 text-blue-600"></i>Pedidos <span class="text-sm font-normal text-gray-500 ml-2">({{ $pedidos->total() }})</span></h3>
                <a href="{{ route('pedidos-internos.create') }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>Nuevo pedido interno</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach(['Código', 'Pide', 'Atiende', 'Área', 'Requerido para', 'Solicitante', 'Estado', ''] as $h)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($pedidos as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono font-semibold text-blue-700">{{ $p->codigo }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $p->destino?->nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $p->origen?->nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $p->area ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm {{ $p->vencido ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    {{ $p->fecha_requerida?->format('d/m/Y') ?? '—' }}@if($p->vencido) <span class="text-xs">(vencido)</span>@endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $p->solicitante?->name }}</td>
                                <td class="px-6 py-4">
                                    @php $ep = ['pendiente' => 'bg-yellow-100 text-yellow-800', 'atendido' => 'bg-green-100 text-green-800', 'cancelado' => 'bg-red-100 text-red-800'][$p->estado] ?? 'bg-gray-100 text-gray-800'; @endphp
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $ep }}">{{ ucfirst($p->estado) }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm"><a href="{{ route('pedidos-internos.show', $p) }}" class="text-blue-600 hover:text-blue-800" title="Ver"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No hay pedidos internos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $pedidos->links() }}</div>
        </div>
    </div>
</body>
</html>
