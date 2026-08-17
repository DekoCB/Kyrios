<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cajas</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Historial de Cajas" subtitle="Registro de aperturas y cierres de caja por turno" />

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Acciones superiores --}}
    <div class="flex justify-between items-center mb-6">
        <a href="{{ route('caja.abrir') }}"
           class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
            <i class="fas fa-lock-open"></i> Abrir Caja
        </a>
        @if($cajas->total() > 0)
            <span class="text-sm text-gray-500">{{ $cajas->total() }} registro(s)</span>
        @endif
    </div>

    {{-- Filtros (solo admin) --}}
    @if($isAdmin)
    <form method="GET" action="{{ route('caja.index') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sucursal</label>
                <select name="sucursal_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    @foreach($sucursales as $s)
                        <option value="{{ $s->id }}" {{ request('sucursal_id') == $s->id ? 'selected' : '' }}>
                            {{ $s->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Usuario</label>
                <select name="user_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                <select name="estado" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <option value="abierta" {{ request('estado') === 'abierta' ? 'selected' : '' }}>Abierta</option>
                    <option value="cerrada" {{ request('estado') === 'cerrada' ? 'selected' : '' }}>Cerrada</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex flex-col">
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <div class="flex gap-2">
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        @if(request()->hasAny(['sucursal_id', 'user_id', 'estado', 'fecha_desde', 'fecha_hasta']))
            <div class="mt-2 text-right">
                <a href="{{ route('caja.index') }}" class="text-xs text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times mr-1"></i>Limpiar filtros
                </a>
            </div>
        @endif
    </form>
    @endif

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    @if($isAdmin)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Inicial</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Final</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ver</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cajas as $caja)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-gray-800">{{ \Carbon\Carbon::parse($caja->fecha)->format('d/m/Y') }}</p>
                        @if($caja->fecha_apertura)
                            <p class="text-xs text-gray-400">{{ $caja->fecha_apertura->format('H:i') }}
                                @if($caja->fecha_cierre) — {{ $caja->fecha_cierre->format('H:i') }}@endif
                            </p>
                        @endif
                        @if($caja->devoluciones_count > 0)
                            <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-orange-50 text-orange-600">
                                <i class="fas fa-rotate-left"></i> {{ $caja->devoluciones_count }} devolución(es)/anulación(es)
                            </span>
                        @endif
                    </td>
                    @if($isAdmin)
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $caja->usuario->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $caja->sucursal->nombre ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $caja->almacen->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-sm text-gray-700">S/ {{ number_format($caja->monto_inicial, 2) }}</td>
                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">S/ {{ number_format($caja->monto_final, 2) }}</td>
                    <td class="px-4 py-3 text-right text-sm">
                        @if($caja->diferencia_cierre !== null)
                            @php $dif = (float) $caja->diferencia_cierre; @endphp
                            <span class="font-semibold {{ abs($dif) < 0.01 ? 'text-green-600' : ($dif > 0 ? 'text-blue-600' : 'text-red-600') }}">
                                {{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($caja->estado === 'abierta')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5 animate-pulse"></span>Abierta
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span>Cerrada
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($caja->estado === 'abierta' && $caja->user_id === auth()->id())
                            <a href="{{ route('caja.actual') }}"
                               class="text-xs bg-green-50 hover:bg-green-100 text-green-700 font-medium px-3 py-1.5 rounded-lg flex items-center gap-1 justify-center">
                                <i class="fas fa-cash-register"></i> Mi Caja
                            </a>
                        @else
                            <a href="{{ route('caja.show', $caja) }}"
                               class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-3 py-1.5 rounded-lg flex items-center gap-1 justify-center">
                                <i class="fas fa-eye"></i> Detalle
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 9 : 6 }}" class="px-4 py-16 text-center text-gray-400">
                        <i class="fas fa-cash-register text-4xl mb-3 block text-gray-200"></i>
                        No hay cajas registradas.
                        <a href="{{ route('caja.abrir') }}" class="text-blue-600 hover:underline ml-1">Abrir la primera</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if($cajas->hasPages())
        <div class="mt-4">
            {{ $cajas->links() }}
        </div>
    @endif

</div>
</body>
</html>
