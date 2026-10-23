<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guías de Remisión</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">

    <x-header title="Guías de Remisión" subtitle="Listado y gestión de guías emitidas desde este módulo" />

    {{-- Acciones --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('guias-remision.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition">
                <i class="fas fa-plus"></i> Nueva Guía
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <input type="text" name="buscar" value="{{ request('buscar') }}"
                   placeholder="N° Guía..."
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <select name="estado" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos los estados</option>
                <option value="pendiente"   {{ request('estado') === 'pendiente'   ? 'selected' : '' }}>Pendiente</option>
                <option value="en_transito" {{ request('estado') === 'en_transito' ? 'selected' : '' }}>En Tránsito</option>
                <option value="entregada"   {{ request('estado') === 'entregada'   ? 'selected' : '' }}>Entregada</option>
                <option value="anulada"     {{ request('estado') === 'anulada'     ? 'selected' : '' }}>Anulada</option>
            </select>
            <select name="motivo" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos los motivos</option>
                <option value="VENTA"                    {{ request('motivo') === 'VENTA'                    ? 'selected' : '' }}>Venta</option>
                <option value="COMPRA"                   {{ request('motivo') === 'COMPRA'                   ? 'selected' : '' }}>Compra</option>
                <option value="TRASLADO_ENTRE_ALMACENES" {{ request('motivo') === 'TRASLADO_ENTRE_ALMACENES' ? 'selected' : '' }}>Traslado</option>
                <option value="CONSIGNACION"             {{ request('motivo') === 'CONSIGNACION'             ? 'selected' : '' }}>Consignación</option>
            </select>
            <select name="origen" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos los orígenes</option>
                <option value="venta"    {{ request('origen') === 'venta'    ? 'selected' : '' }}>Venta</option>
                <option value="traslado" {{ request('origen') === 'traslado' ? 'selected' : '' }}>Traslado</option>
                <option value="manual"   {{ request('origen') === 'manual'   ? 'selected' : '' }}>Manual</option>
            </select>
            <button type="submit"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-lg transition">
                <i class="fas fa-search mr-1"></i> Filtrar
            </button>
        </div>
    </form>

    {{-- Tabla --}}
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        @if($guias->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <i class="fas fa-file-invoice text-4xl mb-3 block"></i>
                <p class="text-sm">No hay guías registradas.</p>
                <a href="{{ route('guias-remision.create') }}" class="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                    <i class="fas fa-plus-circle"></i> Crear primera guía
                </a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">N° Guía</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Origen</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Motivo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Almacén → Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">SUNAT</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($guias as $guia)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-mono font-semibold text-gray-800">{{ $guia->numero_guia }}</td>
                        <td class="px-4 py-3">
                            @if($guia->venta_id)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                    <i class="fas fa-shopping-cart text-[8px]"></i> Venta
                                </span>
                            @elseif($guia->movimientos_count > 0)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700">
                                    <i class="fas fa-exchange-alt text-[8px]"></i> Traslado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600">
                                    <i class="fas fa-pencil-alt text-[8px]"></i> Manual
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $guia->motivo_label }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 text-xs text-gray-700">
                                <span class="font-medium">{{ $guia->almacen?->nombre ?? '—' }}</span>
                                <i class="fas fa-arrow-right text-gray-400"></i>
                                <span class="font-medium">{{ $guia->destinatario_nombre }}</span>
                            </div>
                            <span class="text-[10px] text-gray-400">{{ $guia->tipo_destino_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $guia->fecha_traslado?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $guia->estado_css }}">
                                {{ $guia->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($guia->sunat_estado === 'aceptado')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700"
                                      title="{{ $guia->sunat_descripcion }}">
                                    <i class="fas fa-check-circle"></i> Aceptado
                                </span>
                            @elseif($guia->sunat_estado === 'enviado')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700"
                                      title="{{ $guia->sunat_descripcion }}">
                                    <i class="fas fa-clock"></i> Enviado
                                </span>
                            @elseif($guia->sunat_estado === 'rechazado')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700"
                                      title="{{ $guia->sunat_descripcion }}">
                                    <i class="fas fa-times-circle"></i> Rechazado
                                </span>
                            @elseif($guia->sunat_estado === 'error')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-600"
                                      title="{{ $guia->sunat_descripcion }}">
                                    <i class="fas fa-exclamation-triangle"></i> Error
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">
                                    <i class="fas fa-minus-circle"></i> No enviado
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('guias-remision.show', $guia) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('guias-remision.pdf', $guia) }}" target="_blank"
                                   class="text-red-500 hover:text-red-700 text-xs font-medium" title="Descargar PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                @if($guia->puedeEnviarSunat())
                                    <form action="{{ route('guias-remision.enviar-sunat', $guia) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-amber-600 hover:text-amber-800 text-xs font-medium transition"
                                                title="Enviar a SUNAT"
                                                onclick="return confirm('¿Enviar esta guía a SUNAT?')">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                @elseif(in_array($guia->sunat_estado, ['enviado']))
                                    <form action="{{ route('guias-remision.consultar-sunat', $guia) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-blue-600 hover:text-blue-800 text-xs font-medium transition"
                                                title="Consultar estado SUNAT">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $guias->links() }}
            </div>
        @endif
    </div>
</div>
</body>
</html>
