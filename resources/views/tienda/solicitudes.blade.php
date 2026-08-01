<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 min-h-screen bg-gray-100">
    {{-- Top Bar --}}
    <div class="bg-white shadow-sm sticky top-0 z-10">
        <div class="px-6 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-800">
                <i class="fas fa-clipboard-list text-blue-900 mr-2"></i>
                Mis Solicitudes de Traslado
            </h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('tienda.inventario.ver') }}" class="text-sm text-blue-700 hover:underline flex items-center gap-1">
                    <i class="fas fa-boxes"></i> Ver inventario
                </a>
                <div class="w-9 h-9 bg-gradient-to-r from-blue-900 to-blue-700 rounded-full flex items-center justify-center text-white font-bold text-sm">
                    {{ substr(auth()->user()->name, 0, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="p-6">
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <option value="pendiente" {{ request('estado') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="aprobado" {{ request('estado') == 'aprobado' ? 'selected' : '' }}>Aprobado</option>
                    <option value="en_transito" {{ request('estado') == 'en_transito' ? 'selected' : '' }}>En tránsito</option>
                    <option value="completado" {{ request('estado') == 'completado' ? 'selected' : '' }}>Completado</option>
                    <option value="cancelado" {{ request('estado') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Filtrar
                </button>
                <a href="{{ route('tienda.inventario.solicitudes') }}" class="ml-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla de solicitudes -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Solicitud</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($solicitudes as $solicitud)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">
                        {{ $solicitud->documento_referencia ?? ('SOL-' . str_pad($solicitud->id, 6, '0', STR_PAD_LEFT)) }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $solicitud->producto->nombre }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $solicitud->producto->codigo }}
                            @if($solicitud->variante)
                                &middot; {{ $solicitud->variante->nombre_completo }}
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $solicitud->almacen->nombre ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $solicitud->cantidad }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $solicitud->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4">
                        @if($solicitud->estado == 'pendiente')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pendiente</span>
                        @elseif($solicitud->estado == 'aprobado')
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Aprobado</span>
                        @elseif($solicitud->estado == 'en_transito')
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">En tránsito</span>
                        @elseif($solicitud->estado == 'completado')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Completado</span>
                        @elseif($solicitud->estado == 'cancelado')
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Cancelado</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($solicitud->estado == 'pendiente')
                            <button onclick="cancelarSolicitud({{ $solicitud->id }})"
                                    class="text-red-600 hover:text-red-800 mx-1"
                                    title="Cancelar solicitud">
                                <i class="fas fa-times"></i>
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-clipboard-list text-5xl text-gray-300 mb-4"></i>
                        <p>No hay solicitudes de traslado</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $solicitudes->links() }}
    </div>
    </div>{{-- /p-6 --}}
    </div>{{-- /md:ml-64 --}}

<script>
function cancelarSolicitud(id) {
    Swal.fire({
        title: '¿Cancelar solicitud?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/tienda/solicitudes/${id}/cancelar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Cancelada', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
            });
        }
    });
}
</script>
</body>
</html>