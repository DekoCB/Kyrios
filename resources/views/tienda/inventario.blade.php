<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario Tiendas - {{ config('app.name') }}</title>
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
                <i class="fas fa-store-alt text-blue-900 mr-2"></i>
                Inventario entre Tiendas
            </h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('tienda.inventario.solicitudes') }}" class="text-sm text-blue-700 hover:underline flex items-center gap-1">
                    <i class="fas fa-clipboard-list"></i> Mis solicitudes
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
    @if(session('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <!-- Información de tienda actual -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-6">
        <div class="flex items-center">
            <i class="fas fa-store text-blue-600 mr-3 text-xl"></i>
            <div>
                <p class="text-sm text-gray-600">Tu tienda actual:</p>
                <p class="font-semibold text-blue-900">{{ $tiendaActual->nombre }}</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar producto</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Nombre o código">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="categoria_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Filtrar
                </button>
                <a href="{{ route('tienda.inventario.ver') }}" class="ml-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla de productos -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Variante</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase" colspan="{{ $almacenes->count() }}">
                        Stock por Tienda
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
                <tr class="bg-gray-100">
                    <th colspan="3"></th>
                    @foreach($almacenes as $almacen)
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-600 border-l">
                            {{ $almacen->nombre }}
                        </th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($productos as $producto)
                    @php $numFilas = $producto->filas->count(); @endphp
                    @foreach($producto->filas as $i => $fila)
                    <tr class="hover:bg-gray-50">
                        @if($i === 0)
                        <td class="px-6 py-4 align-top" rowspan="{{ $numFilas }}">
                            <div class="text-sm font-medium text-gray-900">{{ $producto->nombre }}</div>
                            <div class="text-xs text-gray-500">{{ $producto->codigo }}</div>
                        </td>
                        @endif

                        <td class="px-6 py-4 text-sm">
                            @if($fila->variante)
                                <div class="flex items-center gap-1.5">
                                    @if($fila->variante->color?->codigo_hex)
                                        <span class="inline-block w-2.5 h-2.5 rounded-full border border-gray-300 shrink-0"
                                              style="background-color: {{ $fila->variante->color->codigo_hex }}"></span>
                                    @endif
                                    <span class="text-gray-700">{{ $fila->variante->nombre_completo }}</span>
                                </div>
                                <div class="text-[11px] text-gray-400">{{ $fila->variante->sku }}</div>
                            @elseif($producto->es_serie)
                                <span class="text-xs italic text-gray-400">Sin variante asignada</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        @if($i === 0)
                        <td class="px-6 py-4 text-sm text-gray-500 align-top" rowspan="{{ $numFilas }}">{{ $producto->categoria->nombre }}</td>
                        @endif

                        @foreach($almacenes as $almacen)
                            @php
                                $stock = $fila->stocks[$almacen->id] ?? null;
                                $cantidad = $stock ? $stock->cantidad : 0;
                                $esMiTienda = $almacen->id == $tiendaActual->id;
                                $esSerie = $producto->es_serie ?? false;
                            @endphp
                            <td class="px-3 py-4 text-center">
                                @if($esMiTienda)
                                    @if($cantidad > 0)
                                        <span class="font-bold text-blue-600">
                                            {{ $cantidad }}
                                            @if($esSerie)
                                                <span class="text-[10px] font-normal text-purple-500 ml-0.5">IMEI</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="font-bold text-red-400">0</span>
                                    @endif
                                @else
                                    <span class="{{ $cantidad > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ $cantidad }}
                                    </span>
                                @endif
                            </td>
                        @endforeach

                        <td class="px-6 py-4 text-center">
                            @php
                                $otrasTiendas = collect($fila->stocks)
                                    ->filter(fn($stock, $almacenId) => $almacenId != $tiendaActual->id && $stock->cantidad > 0);
                                $etiqueta = $producto->nombre . ($fila->variante ? ' — ' . $fila->variante->nombre_completo : '');
                            @endphp

                            @if($otrasTiendas->count() > 0)
                                <button onclick="abrirModalTraslado({{ $producto->id }}, {{ $fila->variante->id ?? 'null' }}, '{{ addslashes($etiqueta) }}')"
                                        class="text-blue-600 hover:text-blue-800"
                                        title="Solicitar traslado">
                                    <i class="fas fa-truck"></i>
                                </button>
                            @else
                                <span class="text-gray-400" title="No hay stock en otras tiendas">
                                    <i class="fas fa-truck"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $productos->links() }}
    </div>
    </div>{{-- /p-6 --}}

<!-- Modal de solicitud de traslado -->
<div id="modalTraslado" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="cerrarModalTraslado()"></div>
    
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4 rounded-t-2xl">
            <h3 class="text-lg font-bold text-white flex items-center">
                <i class="fas fa-truck mr-2"></i>
                Solicitar Traslado
            </h3>
        </div>
        
        <form id="formTraslado" class="p-6">
            @csrf
            <input type="hidden" name="producto_id" id="traslado_producto_id">
            <input type="hidden" name="variante_id" id="traslado_variante_id">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                    <p id="traslado_producto_nombre" class="text-gray-900 font-medium"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén de origen</label>
                    <select name="almacen_origen_id" id="traslado_almacen_origen" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            required>
                        <option value="">Seleccionar...</option>
                        @foreach($almacenes as $almacen)
                            @if($almacen->id != $tiendaActual->id)
                                <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                    <input type="number" name="cantidad" id="traslado_cantidad" 
                           min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo (opcional)</label>
                    <textarea name="motivo" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Ej: Venta programada, stock bajo..."></textarea>
                </div>
                
                <div class="bg-blue-50 p-3 rounded-lg">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        El traslado quedará pendiente hasta que el almacén de origen lo confirme.
                    </p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="cerrarModalTraslado()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                    <i class="fas fa-paper-plane mr-2"></i>Solicitar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalTraslado(productoId, varianteId, productoNombre) {
    document.getElementById('traslado_producto_id').value = productoId;
    document.getElementById('traslado_variante_id').value = varianteId ?? '';
    document.getElementById('traslado_producto_nombre').textContent = productoNombre;
    document.getElementById('modalTraslado').classList.remove('hidden');
    document.getElementById('modalTraslado').classList.add('flex');
}

function cerrarModalTraslado() {
    document.getElementById('modalTraslado').classList.add('hidden');
    document.getElementById('modalTraslado').classList.remove('flex');
    document.getElementById('formTraslado').reset();
}

document.getElementById('formTraslado').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('{{ route("tienda.inventario.solicitar-traslado") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Solicitud enviada!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                cerrarModalTraslado();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo conectar al servidor'
        });
    });
});
</script>
    </div>{{-- /md:ml-64 --}}
</body>
</html>