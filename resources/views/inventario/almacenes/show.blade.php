<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $almacen->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">

<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('inventario.almacenes.index') }}" class="hover:text-blue-700 transition-colors">Almacenes</a>
        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
        <span class="text-gray-800 font-medium truncate">{{ $almacen->nombre }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            @php
                $tipoIcono = match($almacen->tipo) {
                    'principal' => ['bg-purple-100', 'fa-star text-purple-600'],
                    'tienda'    => ['bg-orange-100', 'fa-store text-orange-600'],
                    'deposito'  => ['bg-teal-100',   'fa-boxes text-teal-600'],
                    default     => ['bg-gray-100',   'fa-warehouse text-gray-600'],
                };
                $tipoBadgeShow = match($almacen->tipo) {
                    'principal' => 'bg-purple-100 text-purple-700',
                    'tienda'    => 'bg-orange-100 text-orange-700',
                    'deposito'  => 'bg-teal-100 text-teal-700',
                    default     => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $tipoIcono[0] }}">
                <i class="fas {{ $tipoIcono[1] }} text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $almacen->nombre }}</h1>
                <p class="text-sm text-gray-500 mt-0.5 flex items-center gap-2">
                    <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $almacen->codigo }}</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $tipoBadgeShow }}">
                        {{ $almacen->tipo_label }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $almacen->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        <i class="fas {{ $almacen->estado === 'activo' ? 'fa-check-circle' : 'fa-times-circle' }} text-[10px]"></i>
                        {{ ucfirst($almacen->estado) }}
                    </span>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('inventario.movimientos.create') }}?almacen_id={{ $almacen->id }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white text-sm font-semibold rounded-xl hover:bg-blue-800 transition-colors shadow-sm">
                <i class="fas fa-plus"></i> Nuevo Movimiento
            </a>
            <a href="{{ route('inventario.almacenes.edit', $almacen) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="{{ route('inventario.almacenes.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 mb-6">

        {{-- Info del almacén --}}
        <div class="xl:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-linear-to-r from-blue-900 to-blue-700 px-5 py-3">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-warehouse"></i> Información
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        ['Código',    $almacen->codigo,       'font-mono text-xs bg-gray-100 px-2 py-0.5 rounded'],
                        ['Tipo',      $almacen->tipo_label,   ''],
                        ['Sucursal',  $almacen->sucursal?->nombre ?? '—', 'text-blue-700'],
                        ['Estado',    ucfirst($almacen->estado), $almacen->estado === 'activo' ? 'text-green-700 font-semibold' : 'text-gray-500'],
                        ['Creado',    $almacen->created_at->format('d/m/Y'), 'text-gray-500 text-xs'],
                    ] as [$label, $value, $extra])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">{{ $label }}</span>
                        <span class="font-medium text-gray-900 {{ $extra }}">{{ $value }}</span>
                    </div>
                    @endforeach

                    @if($almacen->encargado)
                    <div class="border-t border-gray-100 pt-3 mt-3">
                        <p class="text-xs text-gray-500 mb-1">Encargado</p>
                        <p class="text-sm font-semibold text-gray-800">{{ $almacen->nombre_encargado }}</p>
                        <p class="text-xs text-gray-400">{{ $almacen->encargado->role->nombre }}</p>
                    </div>
                    @endif

                    @if($almacen->telefono)
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="fas fa-phone text-gray-400 text-xs"></i>
                        {{ $almacen->telefono }}
                    </div>
                    @endif

                    @if($almacen->direccion)
                    <div class="flex items-start gap-2 text-sm text-gray-600">
                        <i class="fas fa-map-marker-alt text-gray-400 text-xs mt-0.5"></i>
                        <span class="leading-tight">{{ $almacen->direccion }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Resumen rápido --}}
            @php
                $totalProductos = count($stockDetalle);
                $totalUnidades  = collect($stockDetalle)->sum('stock');
                $movHoy         = $almacen->movimientos->filter(fn($m) => $m->created_at->isToday())->count();
            @endphp
            <div class="grid grid-cols-1 gap-3">
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-blue-700"></i>
                    </div>
                    <div>
                        <p class="text-xs text-blue-600">Productos con stock</p>
                        <p class="text-xl font-bold text-blue-900">{{ $totalProductos }}</p>
                    </div>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cubes text-emerald-700"></i>
                    </div>
                    <div>
                        <p class="text-xs text-emerald-600">Unidades totales</p>
                        <p class="text-xl font-bold text-emerald-900">{{ number_format($totalUnidades) }}</p>
                    </div>
                </div>
                <div class="bg-purple-50 border border-purple-100 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-purple-700"></i>
                    </div>
                    <div>
                        <p class="text-xs text-purple-600">Movimientos hoy</p>
                        <p class="text-xl font-bold text-purple-900">{{ $movHoy }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stock por producto --}}
        <div class="xl:col-span-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-linear-to-r from-emerald-700 to-emerald-500 px-5 py-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-boxes"></i> Stock actual en esta tienda
                    </h2>
                    <span class="text-xs text-emerald-100">{{ $totalProductos }} producto(s)</span>
                </div>

                @if(count($stockDetalle) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Categoría</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock en tienda</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock global</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($stockDetalle as $item)
                            @php
                                $stockBajo = $item['stock'] <= 3;
                                $unidad    = $item['producto']->unidadMedida?->abreviatura ?? 'UND';
                            @endphp
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                                            @if($item['producto']->imagen)
                                                <img src="{{ $item['producto']->imagen_url }}"
                                                     alt="{{ $item['producto']->nombre }}"
                                                     class="w-full h-full object-cover">
                                            @else
                                                <i class="fas fa-box text-gray-400 text-sm"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $item['producto']->nombre }}</p>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="text-xs text-gray-400 font-mono">{{ $item['producto']->codigo }}</span>
                                            </div>
                                            @foreach(($item['variantes'] ?? collect()) as $v)
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    <span class="font-mono">{{ $v['codigo'] }}</span>
                                                    @if($v['detalle'] && $v['detalle'] !== 'Variante base') · {{ $v['detalle'] }} @endif
                                                    · <strong class="text-gray-700">{{ $v['cantidad'] }}</strong>
                                                    @if($v['dano'] && $v['dano'] !== 'ninguno') <span class="text-yellow-700">(daño {{ $v['dano'] }})</span> @endif
                                                </p>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs text-gray-500">{{ $item['producto']->categoria?->nombre ?? '—' }}</span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold
                                        {{ $stockBajo ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                        @if($stockBajo)
                                            <i class="fas fa-exclamation-triangle text-xs"></i>
                                        @endif
                                        {{ number_format($item['stock'], 0) }}
                                        <span class="text-xs font-normal">{{ $unidad }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="text-sm text-gray-500">
                                        {{ $item['producto']->stock_actual }}
                                        <span class="text-xs text-gray-400">{{ $unidad }}</span>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="px-5 py-14 text-center">
                    <div class="flex flex-col items-center gap-3 text-gray-400">
                        <i class="fas fa-inbox text-5xl"></i>
                        <p class="font-medium">Sin stock registrado en esta tienda</p>
                        <p class="text-sm">Los productos aparecen aquí cuando se registran movimientos de inventario</p>
                        <a href="{{ route('inventario.movimientos.create') }}"
                           class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors">
                            <i class="fas fa-plus"></i> Registrar primer movimiento
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Últimos movimientos --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-linear-to-r from-gray-700 to-gray-600 px-5 py-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-history"></i> Últimos 20 movimientos
            </h2>
            <a href="{{ route('inventario.movimientos.index') }}?almacen_id={{ $almacen->id }}"
               class="text-xs text-gray-300 hover:text-white transition-colors">
                Ver todos <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        @if($almacen->movimientos->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Cantidad</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Motivo</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($almacen->movimientos as $mov)
                    @php
                        $badgeClass = match($mov->tipo_movimiento) {
                            'ingreso'       => 'bg-green-50 text-green-700 border-green-200',
                            'salida'        => 'bg-red-50 text-red-700 border-red-200',
                            'ajuste'        => 'bg-blue-50 text-blue-700 border-blue-200',
                            'transferencia' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'devolucion'    => 'bg-orange-50 text-orange-700 border-orange-200',
                            default         => 'bg-gray-100 text-gray-600 border-gray-200',
                        };
                        $esIngreso = in_array($mov->tipo_movimiento, ['ingreso', 'devolucion']);
                        $esSalida  = in_array($mov->tipo_movimiento, ['salida', 'merma']);
                    @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <p class="text-sm text-gray-800">{{ $mov->created_at->format('d/m/Y') }}</p>
                            <p class="text-xs text-gray-400">{{ $mov->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                {{ ucfirst($mov->tipo_movimiento) }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-gray-900">{{ \App\Support\EtiquetaProducto::nombre($mov->producto) }}</p>
                            <p class="text-[11px] font-mono text-gray-400">{{ \App\Support\EtiquetaProducto::codigos($mov->producto, $mov->variante) }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $mov->producto->codigo }}</p>
                        </td>
                        <td class="px-5 py-3 text-center whitespace-nowrap">
                            <span class="text-sm font-bold
                                {{ $esIngreso ? 'text-green-600' : ($esSalida ? 'text-red-600' : 'text-blue-600') }}">
                                {{ $esIngreso ? '+' : ($esSalida ? '−' : '') }}{{ $mov->cantidad }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-sm text-gray-600">{{ Str::limit($mov->motivo, 50) }}</span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('inventario.movimientos.show', $mov) }}"
                               class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-gray-50 text-gray-500 hover:bg-blue-50 hover:text-blue-700 transition-colors border border-gray-200"
                               title="Ver detalle">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-5 py-12 text-center">
            <div class="flex flex-col items-center gap-3 text-gray-400">
                <i class="fas fa-history text-5xl"></i>
                <p class="font-medium">Sin movimientos registrados en este almacén</p>
            </div>
        </div>
        @endif
    </div>

</div>
</body>
</html>
