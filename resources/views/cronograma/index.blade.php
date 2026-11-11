<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php
        \Carbon\Carbon::setLocale('es');
        $anterior = $mes->copy()->subMonth()->format('Y-m');
        $siguiente = $mes->copy()->addMonth()->format('Y-m');
        $filtros = request()->only(['tipo', 'estado', 'vista']);
        $estilos = ['programado' => 'bg-yellow-100 text-yellow-800', 'realizado' => 'bg-green-100 text-green-800', 'cancelado' => 'bg-gray-100 text-gray-500'];
    @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Cronograma de instalaciones y entregas" :subtitle="$programa ? 'Programa y sigue las visitas a clientes' : 'Lo que tienes asignado'" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 max-w-3xl">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-5">
                <p class="text-sm text-gray-500">Atrasados (pasó la fecha y siguen programados)</p><p class="text-3xl font-bold text-gray-800">{{ $atrasados }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5">
                <p class="text-sm text-gray-500">Próximos 7 días</p><p class="text-3xl font-bold text-gray-800">{{ $estaSemana }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('cronograma.index', ['mes' => $anterior] + $filtros) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50" aria-label="Mes anterior"><i class="fas fa-chevron-left"></i></a>
            <h2 class="text-lg font-bold text-gray-800 capitalize min-w-[9rem] text-center">{{ $mes->translatedFormat('F Y') }}</h2>
            <a href="{{ route('cronograma.index', ['mes' => $siguiente] + $filtros) }}" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50" aria-label="Mes siguiente"><i class="fas fa-chevron-right"></i></a>
            <a href="{{ route('cronograma.index', ['vista' => $vista]) }}" class="text-sm text-blue-700 hover:underline">Hoy</a>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm" role="group" aria-label="Vista">
                <a href="{{ route('cronograma.index', array_merge($filtros, ['mes' => $mes->format('Y-m'), 'vista' => 'lista'])) }}" class="px-3 py-1.5 {{ $vista === 'lista' ? 'bg-blue-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-list mr-1"></i>Lista</a>
                <a href="{{ route('cronograma.index', array_merge($filtros, ['mes' => $mes->format('Y-m'), 'vista' => 'calendario'])) }}" class="px-3 py-1.5 border-l border-gray-300 {{ $vista === 'calendario' ? 'bg-blue-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-calendar-days mr-1"></i>Calendario</a>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2 ml-auto">
                <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
                <input type="hidden" name="vista" value="{{ $vista }}">
                <select name="tipo" aria-label="Tipo" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                    <option value="">Instalaciones y entregas</option>
                    @foreach(\App\Models\CronogramaEvento::TIPOS as $k => $v)<option value="{{ $k }}" {{ request('tipo') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </select>
                <select name="estado" aria-label="Estado" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                    <option value="">Todos los estados</option>
                    @foreach(['programado' => 'Programado', 'realizado' => 'Realizado', 'cancelado' => 'Cancelado'] as $k => $v)<option value="{{ $k }}" {{ request('estado') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </select>
                <button type="submit" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">Filtrar</button>
            </form>
            @if($programa)
                <a href="{{ route('cronograma.create') }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>Programar</a>
            @endif
        </div>

        @if($programa && $ventasPorProgramar->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm mb-6 max-w-4xl" x-data="{ abierto: true }" data-ventas-por-programar>
                <button type="button" @click="abierto = !abierto" class="w-full px-5 py-3 flex items-center justify-between text-left">
                    <span class="text-sm font-bold text-gray-800"><i class="fas fa-cart-shopping mr-2 text-blue-700"></i>Ventas por programar <span class="ml-1 text-xs font-semibold bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">{{ $ventasPorProgramar->count() }}</span></span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs" :class="abierto ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="abierto" class="divide-y divide-gray-100 border-t border-gray-100 max-h-72 overflow-y-auto">
                    @foreach($ventasPorProgramar as $vt)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="min-w-0 text-sm">
                                <a href="{{ route('ventas.show', $vt->id) }}" class="font-semibold text-blue-700 hover:underline">{{ $vt->codigo }}</a>
                                <span class="text-gray-600">· {{ $vt->cliente?->nombre ?? 'Sin cliente' }}</span>
                                <span class="text-xs text-gray-400">· {{ \Carbon\Carbon::parse($vt->fecha)->format('d/m/Y') }}</span>
                            </div>
                            <a href="{{ route('cronograma.create', ['venta_id' => $vt->id]) }}" class="shrink-0 text-xs font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-2.5 py-1"><i class="fas fa-calendar-plus mr-1"></i>Programar instalación</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($vista === 'calendario')
            @php
                $primero = $mes->copy()->startOfMonth();
                $desfase = $primero->dayOfWeekIso - 1;
                $diasMes = $mes->daysInMonth;
                $celdas = (int) (ceil(($desfase + $diasMes) / 7) * 7);
                $chip = ['programado' => 'bg-blue-100 text-blue-900', 'realizado' => 'bg-green-100 text-green-800 line-through', 'cancelado' => 'bg-gray-100 text-gray-400 line-through'];
            @endphp
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto" data-calendario>
                <div class="min-w-[44rem]">
                    <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-100 text-xs font-bold text-gray-600 text-center">
                        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dn)<div class="py-2">{{ $dn }}</div>@endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @for($i = 0; $i < $celdas; $i++)
                            @php
                                $n = $i - $desfase + 1;
                                $dentro = $n >= 1 && $n <= $diasMes;
                                $dia = $dentro ? $primero->copy()->addDays($n - 1) : null;
                                $delDia = $dia ? ($porDia[$dia->format('Y-m-d')] ?? collect()) : collect();
                            @endphp
                            <div class="min-h-[6.5rem] border-b border-r border-gray-100 p-1.5 {{ $dentro ? '' : 'bg-gray-50' }}">
                                @if($dentro)
                                    <div class="text-xs mb-1 {{ $dia->isToday() ? 'inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-900 text-white font-bold' : 'text-gray-500' }}">{{ $n }}</div>
                                    @foreach($delDia as $ev)
                                        @php $atras = $ev->estado === 'programado' && $ev->dias_restantes < 0; @endphp
                                        <a href="{{ $programa && $ev->estado === 'programado' ? route('cronograma.edit', $ev) : '#' }}"
                                           class="block text-[11px] leading-tight rounded px-1.5 py-1 mb-1 truncate {{ $atras ? 'bg-red-100 text-red-800' : $chip[$ev->estado] }}"
                                           title="{{ $ev->titulo }}{{ $ev->cliente ? ' · ' . $ev->cliente->nombre : '' }} · {{ $ev->responsable?->name ?? 'sin asignar' }}">
                                            <i class="fas {{ $ev->tipo === 'instalacion' ? 'fa-screwdriver-wrench' : 'fa-truck' }} mr-0.5"></i>@if($ev->hora_corta){{ $ev->hora_corta }} @endif{{ $ev->titulo }}
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
            @if($porDia->isEmpty())<p class="text-center text-gray-500 mt-4 text-sm">No hay instalaciones ni entregas en {{ $mes->translatedFormat('F Y') }}.</p>@endif
        @else
        <div class="max-w-4xl space-y-4">
            @forelse($porDia as $dia => $eventos)
                @php $fecha = \Carbon\Carbon::parse($dia); @endphp
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-700">{{ ucfirst($fecha->translatedFormat('l j \d\e F')) }}</h3>
                        @if($fecha->isToday())<span class="text-xs font-semibold bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Hoy</span>@endif
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($eventos as $ev)
                            @php $atras = $ev->estado === 'programado' && $ev->dias_restantes < 0; @endphp
                            <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800">
                                        <i class="fas {{ $ev->tipo === 'instalacion' ? 'fa-screwdriver-wrench' : 'fa-truck' }} mr-1 text-blue-700"></i>
                                        {{ $ev->titulo }}
                                        @if($ev->hora_corta)<span class="font-normal text-gray-500">· {{ $ev->hora_corta }}</span>@endif
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ \App\Models\CronogramaEvento::TIPOS[$ev->tipo] }}
                                        @if($ev->cliente) · {{ $ev->cliente->nombre }}@endif
                                        @if($ev->direccion) · {{ $ev->direccion }}@endif
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Responsable: {{ $ev->responsable?->name ?? 'sin asignar' }}
                                        @if($ev->pedido) · Orden <a href="{{ route('pedidos.show', $ev->pedido) }}" class="text-blue-600 hover:underline">{{ $ev->pedido->codigo }}</a>@endif
                                        @if($ev->venta && $programa) · Venta <a href="{{ route('ventas.show', $ev->venta->id) }}" class="text-blue-600 hover:underline">{{ $ev->venta->codigo }}</a>@elseif($ev->venta) · Venta {{ $ev->venta->codigo }}@endif
                                    </p>
                                    @if($ev->notas)<p class="text-xs text-gray-600 mt-1">{{ $ev->notas }}</p>@endif
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $estilos[$ev->estado] }}">{{ ucfirst($ev->estado) }}@if($atras) · atrasado @endif</span>
                                    @if($ev->estado === 'programado')
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('cronograma.realizar', $ev) }}" data-confirm="¿Marcar como realizado «{{ $ev->titulo }}»?">
                                                @csrf
                                                <button type="submit" class="text-xs font-semibold text-green-700 border border-green-300 hover:bg-green-50 rounded-lg px-2.5 py-1"><i class="fas fa-check mr-1"></i>Realizado</button>
                                            </form>
                                            @if($programa)
                                                <a href="{{ route('cronograma.edit', $ev) }}" class="text-xs text-blue-700 hover:underline">Editar</a>
                                                <form method="POST" action="{{ route('cronograma.cancelar', $ev) }}" data-confirm="¿Cancelar «{{ $ev->titulo }}»?">
                                                    @csrf
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Cancelar</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm px-6 py-12 text-center text-gray-500">No hay instalaciones ni entregas en {{ $mes->translatedFormat('F Y') }}.</div>
            @endforelse
        </div>
        @endif
    </div>
</body>
</html>
