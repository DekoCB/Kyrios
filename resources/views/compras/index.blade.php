<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Compras" 
            subtitle="Registro de compras a proveedores" 
        />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        {{-- Estadísticas --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Compras</p>
                        <p class="text-3xl font-bold text-gray-800">{{ $compras->count() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pagadas</p>
                        <p class="text-3xl font-bold text-gray-800">{{ $compras->filter(fn($c) => $c->cuentaPorPagar && $c->cuentaPorPagar->estado == 'pagado')->count() }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-500 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Por Pagar</p>
                        <p class="text-3xl font-bold text-gray-800">{{ $compras->filter(fn($c) => $c->cuentaPorPagar && $c->cuentaPorPagar->saldo_pendiente > 0)->count() }}</p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-500 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Invertido</p>
                        <p class="text-2xl font-bold text-gray-800">S/ {{ number_format($compras->sum('total'), 2) }}</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('compras.index') }}" class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                {{-- Búsqueda --}}
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Buscar factura / código</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="text" name="buscar" value="{{ request('buscar') }}"
                               placeholder="Nº factura o código..."
                               class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                {{-- Proveedor --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Proveedor</label>
                    <select name="proveedor_id" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        @foreach($proveedores as $prov)
                            <option value="{{ $prov->id }}" {{ request('proveedor_id') == $prov->id ? 'selected' : '' }}>
                                {{ $prov->razon_social }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                    <select name="tipo_compra" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="local"       {{ request('tipo_compra') === 'local'       ? 'selected' : '' }}>Local</option>
                        <option value="importacion" {{ request('tipo_compra') === 'importacion' ? 'selected' : '' }}>Importación</option>
                    </select>
                </div>

                {{-- Fecha desde --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Fecha hasta + botones --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="flex items-center gap-2 mt-3">
                <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>
                @if(request()->hasAny(['buscar','proveedor_id','tipo_compra','estado','fecha_desde','fecha_hasta']))
                    <a href="{{ route('compras.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-1"></i>Limpiar
                    </a>
                    <span class="text-xs text-blue-600 font-medium">
                        {{ $compras->total() }} resultado(s) encontrado(s)
                    </span>
                @endif
            </div>
        </form>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-list mr-2 text-blue-600"></i>Historial de Compras
                    <span class="text-sm font-normal text-gray-500 ml-2">({{ $compras->total() }} en total)</span>
                </h3>
                <a href="{{ route('compras.create') }}" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg transition-colors text-sm">
                    <i class="fas fa-plus mr-2"></i>Nueva Compra
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado Compra</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado Pago</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($compras as $compra)
                            @php
                                $cuenta = $compra->cuentaPorPagar;
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm font-mono font-semibold text-blue-700">{{ $compra->codigo }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $compra->proveedor->razon_social ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $compra->numero_factura }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $compra->fecha->format('d/m/Y') }}</td>

                                {{-- Tipo de Compra --}}
                                <td class="px-6 py-4">
                                    @php
                                        $tc = match($compra->tipo_compra ?? 'local') {
                                            'local'       => ['label' => 'Local',       'class' => 'bg-green-100 text-green-800',  'icon' => 'fa-store'],
                                            'importacion' => ['label' => 'Importación', 'class' => 'bg-orange-100 text-orange-800', 'icon' => 'fa-ship'],
                                            default       => ['label' => ucfirst($compra->tipo_compra ?? 'local'), 'class' => 'bg-gray-100 text-gray-800', 'icon' => 'fa-tag'],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full {{ $tc['class'] }}">
                                        <i class="fas {{ $tc['icon'] }} mr-1"></i>{{ $tc['label'] }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">S/ {{ number_format($compra->total, 2) }}</td>
                                
                                {{-- Estado Compra --}}
                                <td class="px-6 py-4">
                                    @php
                                        $ec = match($compra->estado) {
                                            'completado' => 'bg-green-100 text-green-800',
                                            'pendiente' => 'bg-yellow-100 text-yellow-800',
                                            'anulado' => 'bg-red-100 text-red-800',
                                            'registrado' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $ec }}">
                                        {{ ucfirst($compra->estado) }}
                                    </span>
                                </td>
                                
                                {{-- Estado Pago --}}
                                <td class="px-6 py-4">
                                    @if($cuenta)
                                        @if($cuenta->estado == 'pagado')
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Pagado
                                            </span>
                                        @elseif($cuenta->estado == 'pendiente')
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>Pendiente
                                            </span>
                                        @elseif($cuenta->estado == 'parcial')
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                <i class="fas fa-adjust mr-1"></i>Parcial
                                            </span>
                                        @elseif($cuenta->estado == 'vencido')
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Vencido
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Sin cuenta
                                        </span>
                                    @endif
                                </td>
                                
                                {{-- Saldo --}}
                                <td class="px-6 py-4 text-sm">
                                    @if($cuenta && $cuenta->saldo_pendiente > 0)
                                        <span class="font-semibold text-red-600">
                                            S/ {{ number_format($cuenta->saldo_pendiente, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                
                                {{-- Vencimiento --}}
                                <td class="px-6 py-4 text-sm">
                                    @if($cuenta && $cuenta->fecha_vencimiento)
                                        <span class="{{ $cuenta->esta_vencida ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                            {{ $cuenta->fecha_vencimiento->format('d/m/Y') }}
                                            @if($cuenta->esta_vencida)
                                                <i class="fas fa-exclamation-triangle text-red-500 ml-1" title="Vencida"></i>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                
                                {{-- Acciones --}}
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('compras.show', $compra) }}" 
                                           class="text-blue-600 hover:text-blue-800" 
                                           title="Ver detalle de compra">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($cuenta)
                                            <a href="{{ route('cuentas-por-pagar.show', $cuenta) }}" 
                                               class="text-green-600 hover:text-green-800 ml-2" 
                                               title="Ver cuenta por pagar">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-shopping-cart text-4xl mb-3 text-gray-300 block"></i>
                                    <p>No hay compras registradas</p>
                                    <a href="{{ route('compras.create') }}" class="text-blue-600 hover:underline mt-2 inline-block text-sm">Registrar primera compra</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Paginación --}}
            @if($compras->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $compras->links() }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>