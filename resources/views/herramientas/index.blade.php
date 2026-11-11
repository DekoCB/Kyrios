<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herramientas - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Herramientas de instalación" subtitle="Qué herramientas hay, cuáles salieron con los técnicos y cuáles ya volvieron" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 max-w-3xl">
            <a href="{{ route('herramientas.salidas') }}" class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5 block hover:bg-gray-50">
                <p class="text-sm text-gray-500">Salidas sin devolver</p><p class="text-3xl font-bold text-gray-800">{{ $fueraAhora }}</p>
            </a>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-5">
                <p class="text-sm text-gray-500">Con la devolución vencida</p><p class="text-3xl font-bold text-gray-800">{{ $atrasadas }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, código o categoría" aria-label="Buscar" class="py-1.5 px-3 text-sm border border-gray-300 rounded-lg bg-white w-72">
                <select name="estado" aria-label="Estado" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                    <option value="activa" {{ request('estado', 'activa') === 'activa' ? 'selected' : '' }}>Activas</option>
                    <option value="todas" {{ request('estado') === 'todas' ? 'selected' : '' }}>Activas y de baja</option>
                </select>
                <button type="submit" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">Buscar</button>
            </form>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('herramientas.salidas') }}" class="text-sm font-semibold text-gray-700 border border-gray-300 hover:bg-gray-50 rounded-lg px-3 py-2"><i class="fas fa-list mr-2"></i>Salidas y retornos</a>
                @if($gestiona)
                    <a href="{{ route('herramientas.salidas.create') }}" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-2"><i class="fas fa-arrow-right-from-bracket mr-2"></i>Registrar salida</a>
                    <a href="{{ route('herramientas.create') }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>Nueva herramienta</a>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Código</th>
                        <th class="px-4 py-3 font-semibold">Herramienta</th>
                        <th class="px-4 py-3 font-semibold text-center">Total</th>
                        <th class="px-4 py-3 font-semibold text-center">Disponibles</th>
                        <th class="px-4 py-3 font-semibold text-center">Fuera</th>
                        <th class="px-4 py-3 font-semibold text-center">Dañadas</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($herramientas as $h)
                        <tr class="{{ $h->estado === 'baja' ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $h->codigo }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ $h->nombre }}@if($h->estado === 'baja') <span class="ml-1 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">De baja</span>@endif</p>
                                <p class="text-xs text-gray-500">{{ $h->categoria }}@if($h->categoria && $h->ubicacion) · @endif{{ $h->ubicacion }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $h->cantidad_total }}</td>
                            <td class="px-4 py-3 text-center font-bold {{ $h->disponible > 0 ? 'text-green-700' : 'text-red-600' }}">{{ $h->disponible }}</td>
                            <td class="px-4 py-3 text-center">{{ $h->en_uso }}</td>
                            <td class="px-4 py-3 text-center">
                                {{ $h->cantidad_danada }}
                                @if($gestiona && $h->cantidad_danada > 0)
                                    <form method="POST" action="{{ route('herramientas.reparar', $h) }}" class="inline" data-confirm="¿Marcar {{ $h->cantidad_danada }} unidad(es) de «{{ $h->nombre }}» como reparadas?">
                                        @csrf<input type="hidden" name="cantidad" value="{{ $h->cantidad_danada }}">
                                        <button type="submit" class="ml-1 text-xs text-blue-700 hover:underline">Reparadas</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($gestiona)
                                    @if($h->estado === 'activa' && $h->disponible > 0)<a href="{{ route('herramientas.salidas.create', ['herramienta_id' => $h->id]) }}" class="text-xs text-blue-700 hover:underline mr-3">Sacar</a>@endif
                                    <a href="{{ route('herramientas.edit', $h) }}" class="text-xs text-blue-700 hover:underline">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No hay herramientas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $herramientas->links() }}</div>
    </div>
</body>
</html>
