<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación Electrónica</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Facturación Electrónica" subtitle="Control y estado de comprobantes electrónicos SUNAT" />

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center gap-2">
            <i class="fas fa-check-circle"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
            <i class="fas fa-times-circle"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-4">
            <p class="text-xs text-gray-500 uppercase font-medium">Total Emitidos</p>
            <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['total_emitidos']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-amber-400 p-4">
            <p class="text-xs text-gray-500 uppercase font-medium">Pendiente Envío</p>
            <p class="text-3xl font-bold text-amber-600">{{ $stats['pendiente_envio'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-4">
            <p class="text-xs text-gray-500 uppercase font-medium">Aceptados SUNAT</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['aceptados'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-4">
            <p class="text-xs text-gray-500 uppercase font-medium">Rechazados</p>
            <p class="text-3xl font-bold text-red-600">{{ $stats['rechazados'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-500 p-4">
            <p class="text-xs text-gray-500 uppercase font-medium">Emitidos Hoy</p>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['hoy'] }}</p>
        </div>
    </div>

    {{-- Accesos rápidos --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <a href="{{ route('facturacion.series') }}"
           class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition">
            <i class="fas fa-list-ol text-blue-600"></i> Gestionar Series
        </a>
        @if(auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
        <a href="{{ route('facturacion.configuracion') }}"
           class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition">
            <i class="fas fa-cog text-gray-600"></i> Configuración
        </a>
        @endif
        <a href="{{ route('ventas.index') }}"
           class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition">
            <i class="fas fa-external-link-alt text-gray-400"></i> Ir a Ventas
        </a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('facturacion.index') }}" class="grid grid-cols-1 md:grid-cols-8 gap-3">
            <div class="md:col-span-2">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       placeholder="Buscar cliente, RUC, serie..."
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <select name="estado_sunat" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos los estados</option>
                <option value="pendiente_envio" {{ request('estado_sunat') == 'pendiente_envio' ? 'selected' : '' }}>Pendiente Envío</option>
                <option value="enviado"         {{ request('estado_sunat') == 'enviado'         ? 'selected' : '' }}>Enviado</option>
                <option value="aceptado"        {{ request('estado_sunat') == 'aceptado'        ? 'selected' : '' }}>Aceptado</option>
                <option value="rechazado"       {{ request('estado_sunat') == 'rechazado'       ? 'selected' : '' }}>Rechazado</option>
                <option value="anulado_baja"    {{ request('estado_sunat') == 'anulado_baja'    ? 'selected' : '' }}>Anulado</option>
                <option value="no_aplica"       {{ request('estado_sunat') == 'no_aplica'       ? 'selected' : '' }}>No Aplica</option>
            </select>
            <select name="tipo_comprobante" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos los tipos</option>
                <option value="factura"    {{ request('tipo_comprobante') == 'factura'    ? 'selected' : '' }}>Factura</option>
                <option value="boleta"     {{ request('tipo_comprobante') == 'boleta'     ? 'selected' : '' }}>Boleta</option>
                <option value="nc_factura" {{ request('tipo_comprobante') == 'nc_factura' ? 'selected' : '' }}>Nota Crédito</option>
            </select>
            <select name="con_guia" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Con/Sin guía</option>
                <option value="1" {{ request('con_guia') == '1' ? 'selected' : '' }}>Con Guía de Remisión</option>
                <option value="0" {{ request('con_guia') == '0' ? 'selected' : '' }}>Sin Guía de Remisión</option>
            </select>
            <select name="estado_guia" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Estado Guía</option>
                <option value="pendiente"   {{ request('estado_guia') == 'pendiente'   ? 'selected' : '' }}>Pendiente</option>
                <option value="en_transito" {{ request('estado_guia') == 'en_transito' ? 'selected' : '' }}>En Tránsito</option>
                <option value="entregada"   {{ request('estado_guia') == 'entregada'   ? 'selected' : '' }}>Entregada</option>
                <option value="anulada"     {{ request('estado_guia') == 'anulada'     ? 'selected' : '' }}>Anulada</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white text-sm px-3 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>
                @if(request()->hasAny(['buscar','estado_sunat','tipo_comprobante','fecha_desde','fecha_hasta','sucursal_id','con_guia','estado_guia']))
                    <a href="{{ route('facturacion.index') }}"
                       class="flex-1 text-center text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-50 transition">
                        <i class="fas fa-times"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabla comprobantes --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comprobante</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente / RUC</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado SUNAT</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guía</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($comprobantes as $comp)
                @php
                    $estadoCss = match($comp->estado_sunat) {
                        'aceptado'       => 'bg-green-100 text-green-700',
                        'rechazado'      => 'bg-red-100 text-red-700',
                        'pendiente_envio'=> 'bg-amber-100 text-amber-700',
                        'enviado'        => 'bg-blue-100 text-blue-700',
                        'anulado_baja'   => 'bg-gray-100 text-gray-500',
                        default          => 'bg-gray-100 text-gray-500',
                    };
                    $estadoLabel = match($comp->estado_sunat) {
                        'aceptado'       => 'Aceptado',
                        'rechazado'      => 'Rechazado',
                        'pendiente_envio'=> 'Pendiente',
                        'enviado'        => 'Enviado',
                        'anulado_baja'   => 'Anulado',
                        'no_aplica'      => 'N/A',
                        default          => ucfirst($comp->estado_sunat),
                    };
                    $estadoIcono = match($comp->estado_sunat) {
                        'aceptado'       => 'fa-check-circle',
                        'rechazado'      => 'fa-times-circle',
                        'pendiente_envio'=> 'fa-clock',
                        'enviado'        => 'fa-paper-plane',
                        'anulado_baja'   => 'fa-ban',
                        default          => 'fa-minus-circle',
                    };
                    $tipoCss = match($comp->tipo_comprobante) {
                        'factura'    => 'bg-blue-100 text-blue-800',
                        'boleta'     => 'bg-purple-100 text-purple-800',
                        'nc_factura' => 'bg-orange-100 text-orange-700',
                        'nc_boleta'  => 'bg-orange-100 text-orange-700',
                        default      => 'bg-gray-100 text-gray-600',
                    };
                    $tipoLabel = match($comp->tipo_comprobante) {
                        'factura'    => 'Factura',
                        'boleta'     => 'Boleta',
                        'nc_factura' => 'NC Factura',
                        'nc_boleta'  => 'NC Boleta',
                        default      => ucfirst($comp->tipo_comprobante),
                    };
                    $tieneGuia = $comp->guiaRemision !== null;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        @if($comp->serieComprobante && $comp->correlativo)
                            <span class="font-mono font-semibold text-gray-800">
                                {{ $comp->serieComprobante->serie }}-{{ str_pad($comp->correlativo, 8, '0', STR_PAD_LEFT) }}
                            </span>
                        @else
                            <span class="font-mono text-gray-400 text-xs">#{{ str_pad($comp->id, 6, '0', STR_PAD_LEFT) }}</span>
                            <span class="block text-[10px] text-amber-500 font-medium leading-none mt-0.5">Sin serie asignada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $tipoCss }}">{{ $tipoLabel }}</span>
                        @if($tieneGuia)
                            <span class="block mt-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-teal-100 text-teal-700 w-fit">
                                <i class="fas fa-truck mr-0.5"></i>Guía Rem.
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 truncate max-w-[200px]">{{ $comp->cliente?->nombre ?? 'Sin cliente' }}</p>
                        <p class="text-xs text-gray-500">{{ $comp->cliente?->documento ?? '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($comp->fecha)->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-800">
                        S/ {{ number_format($comp->total, 2) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $estadoCss }}">
                            <i class="fas {{ $estadoIcono }}"></i>{{ $estadoLabel }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($tieneGuia && auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
                            <form action="{{ route('facturacion.guia-estado', $comp) }}" method="POST">
                                @csrf @method('PATCH')
                                <select name="estado" onchange="this.form.submit()"
                                        class="text-xs border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-teal-500 cursor-pointer
                                               {{ match($comp->guiaRemision->estado) {
                                                   'pendiente'   => 'bg-amber-50 text-amber-700 border-amber-300',
                                                   'en_transito' => 'bg-blue-50 text-blue-700 border-blue-300',
                                                   'entregada'   => 'bg-green-50 text-green-700 border-green-300',
                                                   'anulada'     => 'bg-gray-100 text-gray-500 border-gray-300',
                                                   default       => '',
                                               } }}">
                                    <option value="pendiente"   {{ $comp->guiaRemision->estado === 'pendiente'   ? 'selected' : '' }}>Pendiente</option>
                                    <option value="en_transito" {{ $comp->guiaRemision->estado === 'en_transito' ? 'selected' : '' }}>En Tránsito</option>
                                    <option value="entregada"   {{ $comp->guiaRemision->estado === 'entregada'   ? 'selected' : '' }}>Entregada</option>
                                    <option value="anulada"     {{ $comp->guiaRemision->estado === 'anulada'     ? 'selected' : '' }}>Anulada</option>
                                </select>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('ventas.show', $comp) }}" target="_blank"
                               class="text-blue-600 hover:text-blue-800 transition" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if(in_array($comp->estado_sunat, ['pendiente_envio', 'rechazado']))
                                <form action="{{ route('facturacion.enviar-sunat', $comp) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-amber-600 hover:text-amber-800 transition" title="Enviar a SUNAT"
                                            data-confirm="¿Enviar este comprobante a SUNAT?">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            @elseif($comp->estado_sunat === 'aceptado' && $comp->sunat_api_id)
                                <span class="text-green-500" title="{{ $comp->sunat_descripcion ?? 'Aceptado por SUNAT' }}">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            @endif
                            <a href="{{ route('ventas.pdf', $comp) }}" target="_blank"
                               class="text-red-500 hover:text-red-700 transition" title="Descargar PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('facturacion.xml', $comp) }}"
                               class="text-green-600 hover:text-green-800 transition" title="Descargar XML">
                                <i class="fas fa-file-code"></i>
                            </a>
                            @if($tieneGuia)
                                <a href="{{ route('ventas.guia-pdf', $comp) }}" target="_blank"
                                   class="text-teal-600 hover:text-teal-800 transition" title="Ver Guía de Remisión">
                                    <i class="fas fa-truck"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-file-invoice text-4xl mb-3 block"></i>
                        <p class="font-medium">No se encontraron comprobantes</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $comprobantes->withQueryString()->links() }}</div>
</div>
</body>
</html>
