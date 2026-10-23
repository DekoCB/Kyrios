<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja chica - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Caja chica" :subtitle="$esAdmin ? 'Caja general y cajas asignadas al personal' : 'Las cajas que se le asignaron'" />

        @include('cotizaciones.partials.mensajes')

        @if($esAdmin)
            <div class="flex flex-wrap gap-2 mb-6">
                <a href="{{ route('caja-chica.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-plus mr-1"></i>Nueva caja chica</a>
                <a href="{{ route('caja-chica.index', ['estado' => 'activa']) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm px-4 py-2 rounded-lg">Activas</a>
                <a href="{{ route('caja-chica.index', ['estado' => 'cerrada']) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm px-4 py-2 rounded-lg">Cerradas</a>
                <a href="{{ route('caja-chica.index') }}" class="text-sm text-gray-500 hover:text-gray-800 px-2 py-2"><i class="fas fa-redo mr-1"></i>Todas</a>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($cajas as $c)
                @php($saldo = $c->saldo)
                <a href="{{ route('caja-chica.show', $c) }}" class="block bg-white rounded-xl shadow-sm p-5 border-l-4 {{ $c->esta_activa ? ($c->tipo === 'general' ? 'border-indigo-500' : 'border-emerald-500') : 'border-gray-300' }} hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-mono text-gray-400">{{ $c->codigo }}</p>
                            <h3 class="font-semibold text-gray-900">{{ $c->nombre }}</h3>
                            <p class="text-xs text-gray-500">{{ $c->tipo === 'general' ? 'Caja general' : 'Asignada a ' . ($c->responsable?->name ?? '—') }}</p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $c->esta_activa ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ $c->esta_activa ? 'Activa' : 'Cerrada' }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-4 uppercase tracking-wide">Saldo disponible</p>
                    <p class="text-2xl font-bold {{ $saldo < 0 ? 'text-red-600' : 'text-gray-900' }}">S/ {{ number_format($saldo, 2) }}</p>
                    @if($c->por_revisar > 0)
                        <p class="text-xs text-amber-700 mt-2"><i class="fas fa-hourglass-half mr-1"></i>{{ $c->por_revisar }} gasto(s) por revisar</p>
                    @endif
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3 bg-white rounded-xl shadow-sm px-6 py-14 text-center text-gray-500">
                    <i class="fas fa-wallet text-4xl mb-3 text-gray-300"></i>
                    <p class="font-medium">{{ $esAdmin ? 'Aún no hay cajas chicas' : 'No tiene ninguna caja chica asignada' }}</p>
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
