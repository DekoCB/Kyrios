<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traslados - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Gestión de Traslados"
            subtitle="Historial de traslados entre almacenes"
        />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        {{-- Nav --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <span class="text-sm font-semibold text-blue-700 flex items-center gap-1">
                <i class="fas fa-exchange-alt"></i> Historial
            </span>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.pendientes') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1.5">
                <i class="fas fa-clock"></i> Pendientes
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.stock') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-boxes"></i> Stock por Almacén
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('traslados.create') }}" class="text-sm text-gray-600 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-plus-circle"></i> Nuevo Traslado
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Guía</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Productos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creado por</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $colores = ['pendiente' => 'bg-yellow-100 text-yellow-800', 'confirmado' => 'bg-green-100 text-green-800', 'anulado' => 'bg-red-100 text-red-800'];
                        $rolActual = auth()->user()->role->nombre;
                        $puedeGestionarAnulacion = in_array($rolActual, ['Administrador', 'Almacenero']);
                        $puedeEliminar = $rolActual === 'Administrador';
                    @endphp

                    @forelse($traslados as $guia => $movimientos)
                    @php
                        $primero   = $movimientos->first();
                        $esGuia    = !str_starts_with($guia, 'id:');
                        $todosPendientes = $movimientos->every(fn($m) => $m->estado === 'pendiente');
                        $todosConfirmados = $movimientos->every(fn($m) => $m->estado === 'confirmado');
                        $estado = $todosConfirmados ? 'confirmado' : ($todosPendientes ? 'pendiente' : 'mixto');
                        $puedeAnular = $todosPendientes && $puedeGestionarAnulacion;
                    @endphp
                        <tr class="hover:bg-gray-50" @if($puedeAnular || $puedeEliminar) x-data="{ showAnular: false, showEliminar: false }" @endif>
                            <td class="px-6 py-4 text-sm font-mono font-semibold text-blue-600">
                                {{ $esGuia ? $guia : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">
                                    <i class="fas fa-boxes text-[10px]"></i>
                                    {{ $movimientos->count() }} producto(s)
                                </span>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach($movimientos as $mov)
                                        <span class="text-[11px] text-gray-500 font-medium">{{ $mov->producto->nombre }}@if(!$loop->last),@endif</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <i class="fas fa-warehouse text-orange-400 mr-1 text-xs"></i>
                                {{ $primero->almacen->nombre }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <i class="fas fa-store text-green-400 mr-1 text-xs"></i>
                                {{ $primero->almacenDestino->nombre ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $primero->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $primero->usuario->name }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $colores[$estado] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($estado) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('traslados.show', $primero) }}"
                                       title="Ver detalle"
                                       class="text-blue-600 hover:text-blue-800 font-medium">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($puedeAnular)
                                    <button type="button" @click="showAnular = true" title="Anular traslado"
                                            class="text-red-600 hover:text-red-800 font-medium">
                                        <i class="fas fa-ban"></i>
                                    </button>

                                    {{-- Modal de anulación --}}
                                    <div x-show="showAnular" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="display: none;">
                                        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAnular = false"></div>
                                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 text-left">
                                            <div class="flex items-center gap-3 mb-4">
                                                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                                                    <i class="fas fa-ban text-red-600"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">Anular Traslado</h3>
                                                    <p class="text-sm text-gray-500">{{ $esGuia ? $guia : '#' . $primero->id }}</p>
                                                </div>
                                            </div>

                                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Se revertirá todo el stock: los IMEIs volverán a "En Stock" en el almacén origen y las cantidades de accesorios se devolverán. La guía de remisión asociada quedará anulada.
                                            </div>

                                            <form action="{{ route('traslados.anular', $primero) }}" method="POST">
                                                @csrf
                                                <div class="mb-4">
                                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                                        Motivo de anulación
                                                    </label>
                                                    <textarea name="motivo" rows="3"
                                                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 resize-none"
                                                              placeholder="Describe el motivo de la anulación..."></textarea>
                                                </div>
                                                <div class="flex gap-3">
                                                    <button type="button" @click="showAnular = false"
                                                            class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                                        Cancelar
                                                    </button>
                                                    <button type="submit"
                                                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                                                        <i class="fas fa-ban mr-1"></i> Confirmar Anulación
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endif

                                    @if($puedeEliminar)
                                    <button type="button" @click="showEliminar = true" title="Eliminar del historial"
                                            class="text-gray-500 hover:text-gray-800 font-medium">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                    {{-- Modal de eliminación --}}
                                    <div x-show="showEliminar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="display: none;">
                                        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showEliminar = false"></div>
                                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 text-left">
                                            <div class="flex items-center gap-3 mb-4">
                                                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                                                    <i class="fas fa-trash-alt text-gray-600"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">Eliminar del Historial</h3>
                                                    <p class="text-sm text-gray-500">{{ $esGuia ? $guia : '#' . $primero->id }}</p>
                                                </div>
                                            </div>

                                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                @if($estado === 'pendiente')
                                                    Este traslado sigue pendiente: se devolverá el stock reservado al almacén origen y la guía de remisión asociada quedará anulada.
                                                @elseif($estado === 'anulado')
                                                    El stock de este traslado ya fue devuelto al anularse, así que no se vuelve a tocar. Solo desaparecerá de este listado; el registro se conserva internamente para auditoría.
                                                @else
                                                    Se intentará devolver al almacén origen todo el stock/IMEIs que siga en tránsito o en el almacén destino. Si alguna unidad ya se vendió o se movió de nuevo desde destino, la eliminación fallará con un aviso — no se puede devolver lo que ya no está ahí.
                                                @endif
                                            </div>

                                            <form action="{{ route('traslados.eliminar', $primero) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="mb-4">
                                                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                                        Motivo de eliminación
                                                    </label>
                                                    <textarea name="motivo" rows="3"
                                                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 resize-none"
                                                              placeholder="Describe el motivo de la eliminación..."></textarea>
                                                </div>
                                                <div class="flex gap-3">
                                                    <button type="button" @click="showEliminar = false"
                                                            class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                                        Cancelar
                                                    </button>
                                                    <button type="submit"
                                                            class="flex-1 px-4 py-2.5 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg transition">
                                                        <i class="fas fa-trash-alt mr-1"></i> Confirmar Eliminación
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-truck-loading text-4xl mb-3 text-gray-300 block"></i>
                                <p>No hay traslados registrados</p>
                                <a href="{{ route('traslados.create') }}"
                                   class="mt-4 inline-flex items-center gap-2 px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition-colors">
                                    <i class="fas fa-plus-circle"></i> Crear primer traslado
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
