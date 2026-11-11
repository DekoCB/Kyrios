<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salidas y retornos de herramientas - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Salidas y retornos de herramientas" :subtitle="$gestiona ? 'Quién tiene qué y cómo volvió' : 'Lo que tienes a tu nombre'" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('herramientas.index') }}" class="text-sm text-blue-700 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Herramientas</a>
            <form method="GET" class="flex flex-wrap items-center gap-2 ml-auto">
                <select name="estado" aria-label="Estado" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                    <option value="en_uso" {{ $estado === 'en_uso' ? 'selected' : '' }}>Sin devolver</option>
                    <option value="devuelta" {{ $estado === 'devuelta' ? 'selected' : '' }}>Devueltas</option>
                    <option value="todas" {{ $estado === 'todas' ? 'selected' : '' }}>Todas</option>
                </select>
                @if($gestiona)
                    <select name="tecnico_id" aria-label="Técnico" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                        <option value="">Todos los técnicos</option>
                        @foreach($tecnicos as $t)<option value="{{ $t->id }}" {{ (string) request('tecnico_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
                    </select>
                @endif
                <button type="submit" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">Filtrar</button>
            </form>
            @if($gestiona)
                <a href="{{ route('herramientas.salidas.create') }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>Registrar salida</a>
            @endif
        </div>

        <div class="max-w-5xl space-y-3">
            @forelse($salidas as $s)
                <div class="bg-white rounded-xl shadow-sm p-5" x-data="{ abierto: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $s->herramienta->nombre }} <span class="font-normal text-gray-500">×{{ $s->cantidad }}</span> <span class="font-mono text-xs text-gray-400">{{ $s->herramienta->codigo }}</span></p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Técnico: <span class="font-medium text-gray-700">{{ $s->tecnico->name }}</span>
                                · Salió {{ $s->fecha_salida->format('d/m/Y H:i') }}
                                @if($s->destino) · {{ $s->destino }}@endif
                                @if($s->evento) · {{ $s->evento->titulo }}@endif
                            </p>
                            @if($s->fecha_devolucion_esperada)
                                <p class="text-xs {{ $s->atrasada ? 'text-red-600 font-semibold' : 'text-gray-500' }}">Devolución esperada: {{ $s->fecha_devolucion_esperada->format('d/m/Y') }}@if($s->atrasada) · atrasada @endif</p>
                            @endif
                            @if($s->notas)<p class="text-xs text-gray-600 mt-1">{{ $s->notas }}</p>@endif
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            @if($s->estado === 'en_uso')
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $s->atrasada ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">Sin devolver</span>
                                @if($gestiona)
                                    <button type="button" @click="abierto = !abierto" class="text-xs font-semibold text-green-700 border border-green-300 hover:bg-green-50 rounded-lg px-2.5 py-1"><i class="fas fa-arrow-rotate-left mr-1"></i>Registrar retorno</button>
                                @endif
                            @else
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-green-100 text-green-800">Devuelta {{ $s->fecha_retorno?->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($s->estado === 'devuelta')
                        <p class="text-xs text-gray-600 mt-2">
                            Volvieron: {{ $s->cantidad_buena }} en buen estado
                            @if($s->cantidad_danada) · <span class="text-orange-700">{{ $s->cantidad_danada }} dañada(s)</span>@endif
                            @if($s->cantidad_perdida) · <span class="text-red-600">{{ $s->cantidad_perdida }} perdida(s)</span>@endif
                            @if($s->receptor) · recibió {{ $s->receptor->name }}@endif
                            @if($s->notas_retorno) · {{ $s->notas_retorno }}@endif
                        </p>
                    @elseif($gestiona)
                        <form x-show="abierto" x-cloak style="display:none" method="POST" action="{{ route('herramientas.salidas.retorno', $s) }}" class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-500 mb-1" for="buena-{{ $s->id }}">En buen estado</label>
                                <input id="buena-{{ $s->id }}" type="number" name="cantidad_buena" value="{{ $s->cantidad }}" min="0" max="{{ $s->cantidad }}" required class="w-24 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1" for="danada-{{ $s->id }}">Dañadas</label>
                                <input id="danada-{{ $s->id }}" type="number" name="cantidad_danada" value="0" min="0" max="{{ $s->cantidad }}" required class="w-24 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1" for="perdida-{{ $s->id }}">Perdidas</label>
                                <input id="perdida-{{ $s->id }}" type="number" name="cantidad_perdida" value="0" min="0" max="{{ $s->cantidad }}" required class="w-24 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                            </div>
                            <div class="flex-1 min-w-[12rem]">
                                <label class="block text-xs text-gray-500 mb-1" for="nr-{{ $s->id }}">Notas</label>
                                <input id="nr-{{ $s->id }}" type="text" name="notas_retorno" maxlength="1000" class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg bg-white">
                            </div>
                            <button type="submit" class="text-sm font-semibold bg-green-700 hover:bg-green-800 text-white rounded-lg px-4 py-1.5">Confirmar retorno</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm px-6 py-12 text-center text-gray-500">No hay salidas {{ $estado === 'en_uso' ? 'sin devolver' : 'registradas' }}.</div>
            @endforelse
            <div>{{ $salidas->links() }}</div>
        </div>
    </div>
</body>
</html>
