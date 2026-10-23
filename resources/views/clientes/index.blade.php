<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Clientes" subtitle="Personas y empresas a las que se vende" />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach([
                ['Clientes registrados', $stats['total'], 'fa-users', 'border-blue-500'],
                ['Activos', $stats['activos'], 'fa-user-check', 'border-emerald-500'],
                ['Empresas (RUC)', $stats['empresas'], 'fa-building', 'border-indigo-500'],
                ['Personas (DNI / CE)', $stats['personas'], 'fa-user', 'border-amber-400'],
            ] as [$titulo, $valor, $icono, $borde])
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $borde }}">
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><i class="fas {{ $icono }} mr-1"></i>{{ $titulo }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $valor }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filtros --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre, documento, teléfono, correo o contacto"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Documento</label>
                <select name="tipo_documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach(['DNI', 'RUC', 'CE'] as $t)
                        <option value="{{ $t }}" @selected(request('tipo_documento') === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="md:col-span-4 flex flex-wrap items-center justify-between gap-2">
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 hover:text-gray-800"><i class="fas fa-redo mr-1"></i>Limpiar</a>
                <div class="flex flex-wrap gap-2">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-search mr-1"></i>Buscar</button>
                    <a href="{{ route('clientes.exportar', request()->query()) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm font-semibold px-4 py-2 rounded-lg">
                        <i class="fas fa-file-csv mr-1"></i>Exportar
                    </a>
                    @if($canCreate)
                        <a href="{{ route('clientes.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-1"></i>Nuevo cliente
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ventas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total comprado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-700 mr-1">{{ $cliente->tipo_documento }}</span>
                                <span class="font-mono">{{ $cliente->numero_documento }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('clientes.show', $cliente) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $cliente->nombre }}</a>
                                @if($cliente->contacto)<div class="text-xs text-gray-400">Contacto: {{ $cliente->contacto }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($cliente->telefono)<div><i class="fas fa-phone text-xs mr-1"></i>{{ $cliente->telefono }}</div>@endif
                                @if($cliente->email)<div class="text-xs">{{ $cliente->email }}</div>@endif
                                @if(!$cliente->telefono && !$cliente->email)—@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-600">{{ $cliente->ventas_count }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">S/ {{ number_format((float) $cliente->total_comprado, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $cliente->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ ucfirst($cliente->estado) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <a href="{{ route('clientes.show', $cliente) }}" class="text-blue-600 hover:text-blue-800 mr-3" title="Ver ficha"><i class="fas fa-eye"></i></a>
                                @if($canEdit)
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-yellow-600 hover:text-yellow-800 mr-3" title="Editar"><i class="fas fa-edit"></i></a>
                                @endif
                                @if($canEdit)
                                    <a href="{{ route('cotizaciones.create', ['cliente_id' => $cliente->id]) }}" class="text-gray-500 hover:text-gray-800" title="Nueva cotización"><i class="fas fa-file-contract"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                                <p class="font-medium">No hay clientes con esos filtros</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $clientes->links() }}</div>
    </div>
</body>
</html>
