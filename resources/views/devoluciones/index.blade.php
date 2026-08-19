<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Devoluciones de Clientes"
            subtitle="Registro de productos devueltos por clientes mediante guía de remisión"
        />

        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500 flex items-center gap-4">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-undo-alt text-red-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">Total Devoluciones</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_guias']) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500 flex items-center gap-4">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-boxes text-orange-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">Unidades Devueltas</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_unidades']) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500 flex items-center gap-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar-day text-blue-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">Devoluciones Hoy</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['hoy']) }}</p>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
            <form method="GET" action="{{ route('devoluciones.index') }}" class="flex flex-wrap gap-3 items-end">

                {{-- Búsqueda --}}
                <div class="flex-1 min-w-50">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Buscar
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="text" name="buscar" value="{{ request('buscar') }}"
                               placeholder="N° guía, documento, producto, observación..."
                               class="w-full pl-8 pr-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-red-400">
                    </div>
                </div>

                {{-- Almacén --}}
                <div class="min-w-45">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Almacén destino
                    </label>
                    <select name="almacen_id"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-red-400 bg-white">
                        <option value="">Todos los almacenes</option>
                        @foreach($almacenes as $alm)
                            <option value="{{ $alm->id }}" {{ request('almacen_id') == $alm->id ? 'selected' : '' }}>
                                {{ $alm->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha desde --}}
                <div class="min-w-40">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Desde
                    </label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                           class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-red-400">
                </div>

                {{-- Fecha hasta --}}
                <div class="min-w-40">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Hasta
                    </label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-red-400">
                </div>

                {{-- Botones --}}
                <div class="flex gap-2 shrink-0">
                    <button type="submit"
                            class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    @if(request()->hasAny(['buscar','almacen_id','fecha_desde','fecha_hasta']))
                        <a href="{{ route('devoluciones.index') }}"
                           class="px-4 py-2.5 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm rounded-lg flex items-center gap-2 transition">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Cabecera de tabla + botón nuevo --}}
        <div class="flex justify-between items-center mb-3">
            <p class="text-sm text-gray-500">
                {{ $devoluciones->total() }} resultado(s)
                @if(request()->hasAny(['buscar','almacen_id','fecha_desde','fecha_hasta']))
                    <span class="text-red-500 font-medium">· filtro activo</span>
                @endif
            </p>
            <a href="{{ route('devoluciones.create') }}"
               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition">
                <i class="fas fa-undo-alt"></i> Nueva Devolución
            </a>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">N° Guía</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Ítems</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Unidades</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Almacén Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Registrado por</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Observación</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($devoluciones as $dev)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-gray-700 bg-gray-100 px-2 py-1 rounded">
                                    {{ $dev->numero_guia }}
                                </span>
                                @if($dev->anulada)
                                    <span class="ml-1 bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded">Anulada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 bg-orange-100 text-orange-700 text-xs font-bold rounded-full">
                                    {{ $dev->total_items }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700">
                                {{ number_format($dev->total_cantidad) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $almacenesMap[$dev->almacen_id] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $usersMap[$dev->user_id] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($dev->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs max-w-40 truncate" title="{{ $dev->observaciones }}">
                                {{ $dev->observaciones ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('devoluciones.show', $dev->id) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg transition">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <i class="fas fa-undo-alt text-4xl text-gray-200 block mb-3"></i>
                                @if(request()->hasAny(['buscar','almacen_id','fecha_desde','fecha_hasta']))
                                    <p class="text-gray-500 font-medium">Sin resultados para los filtros aplicados</p>
                                    <a href="{{ route('devoluciones.index') }}" class="text-sm text-red-600 hover:underline mt-1 inline-block">
                                        Limpiar filtros
                                    </a>
                                @else
                                    <p class="text-gray-400 font-medium">No hay devoluciones registradas</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $devoluciones->links() }}
        </div>
    </div>
</body>
</html>
