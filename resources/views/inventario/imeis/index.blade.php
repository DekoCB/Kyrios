<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de IMEIs - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .qr-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .qr-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .close:hover {
            color: #000;
        }
        .etiqueta-imei {
            border: 1px dashed #ccc;
            padding: 15px;
            border-radius: 8px;
            background: white;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #etiquetaImprimir, #etiquetaImprimir * {
                visibility: visible;
            }
            #etiquetaImprimir {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Gestión de IMEIs" 
            subtitle="Control individual de celulares por IMEI" 
        />

        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <!-- Estadísticas con tabs visuales -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-900 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('')">
                <p class="text-xs text-gray-600">Total</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('en_stock')">
                <p class="text-xs text-gray-600">Disponibles</p>
                <p class="text-2xl font-bold text-green-600">{{ $stats['disponibles'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('reservado')">
                <p class="text-xs text-gray-600">Reservados</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $stats['reservados'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('vendido')">
                <p class="text-xs text-gray-600">Vendidos</p>
                <p class="text-2xl font-bold text-red-600">{{ $stats['vendidos'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('garantia')">
                <p class="text-xs text-gray-600">Garantía</p>
                <p class="text-2xl font-bold text-purple-600">{{ $stats['garantia'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-gray-500 cursor-pointer hover:shadow-lg transition" onclick="filtrarPorEstado('devuelto')">
                <p class="text-xs text-gray-600">Devueltos</p>
                <p class="text-2xl font-bold text-gray-600">{{ $stats['devueltos'] ?? 0 }}</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form action="{{ route('inventario.imeis.index') }}" method="GET" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar IMEI/Serie</label>
                        <input type="text" name="buscar" value="{{ request('buscar') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Código IMEI o serie...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Producto</label>
                        <select name="producto_id" id="filtroProducto"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos los productos</option>
                            @foreach($productos as $producto)
                                <option value="{{ $producto->id }}" {{ request('producto_id') == $producto->id ? 'selected' : '' }}>
                                    {{ $producto->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Variante</label>
                        <select name="variante_id" id="filtroVariante"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas las variantes</option>
                            @foreach($productos as $producto)
                                @foreach($producto->variantesActivas ?? [] as $v)
                                    <option value="{{ $v->id }}"
                                            data-producto="{{ $producto->id }}"
                                            {{ request('variante_id') == $v->id ? 'selected' : '' }}
                                            class="variante-option-{{ $producto->id }}">
                                        {{ $producto->nombre }} — {{ trim(($v->color?->nombre ?? '') . ($v->capacidad ? ' / ' . $v->capacidad : '')) }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Almacén</label>
                        <select name="almacen_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos los almacenes</option>
                            @foreach($almacenes as $almacen)
                                <option value="{{ $almacen->id }}" {{ request('almacen_id') == $almacen->id ? 'selected' : '' }}>
                                    {{ $almacen->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select name="estado" id="estadoFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos los estados</option>
                            <option value="en_stock"    {{ request('estado') == 'en_stock'    ? 'selected' : '' }}>En Stock</option>
                            <option value="reservado"   {{ request('estado') == 'reservado'   ? 'selected' : '' }}>Reservado</option>
                            <option value="vendido"     {{ request('estado') == 'vendido'     ? 'selected' : '' }}>Vendido</option>
                            <option value="garantia"    {{ request('estado') == 'garantia'    ? 'selected' : '' }}>En Garantía</option>
                            <option value="devuelto"    {{ request('estado') == 'devuelto'    ? 'selected' : '' }}>Devuelto</option>
                            <option value="reemplazado" {{ request('estado') == 'reemplazado' ? 'selected' : '' }}>Reemplazado</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-4">
                    <a href="{{ route('inventario.imeis.index') }}" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-redo mr-2"></i>Limpiar
                    </a>
                    <button type="submit" class="bg-blue-900 text-white px-6 py-2 rounded-lg hover:bg-blue-800">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de IMEIs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-list mr-2 text-blue-900"></i>
                        Listado de IMEIs
                    </h2>
                    @if(auth()->user()->role->nombre != 'Tienda')
                        <div class="flex space-x-2">
                            <button onclick="generarEtiquetasMasivas()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-tags mr-2"></i>Etiquetas Masivas
                            </button>
                            <a href="{{ route('inventario.imeis.create') }}" class="bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800">
                                <i class="fas fa-plus mr-2"></i>Registrar IMEI
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <input type="checkbox" id="seleccionarTodos" onclick="toggleTodos()"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IMEI</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Variante</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proveedor</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fecha Registro</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($imeis as $imei)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <input type="checkbox" name="imei_seleccionado" value="{{ $imei->id }}" class="imei-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-mono font-bold text-gray-900">{{ $imei->codigo_imei }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $imei->producto->nombre ?? '-' }}</p>
                                <p class="text-xs text-gray-500">{{ $imei->producto->codigo ?? '-' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                @if($imei->variante)
                                    <div class="flex items-center gap-1.5">
                                        @if($imei->variante->color?->codigo_hex)
                                            <span class="w-3 h-3 rounded-full border border-gray-300 shrink-0"
                                                  style="background:{{ $imei->variante->color->codigo_hex }}"></span>
                                        @endif
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ trim(($imei->variante->color?->nombre ?? '') . ($imei->variante->capacidad ? ' / ' . $imei->variante->capacidad : '')) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 font-mono">{{ $imei->variante->sku }}</p>
                                @elseif($imei->color)
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 rounded-full border border-gray-300 shrink-0"
                                              style="background:{{ $imei->color->codigo_hex ?? '#ccc' }}"></span>
                                        <span class="text-sm text-gray-700">{{ $imei->color->nombre }}</span>
                                    </div>
                                    <p class="text-xs text-orange-400 italic">Sin variante asignada</p>
                                @else
                                    <span class="text-xs text-gray-400 italic">—</span>
                                @endif
                                @if($imei->serie)
                                    <p class="text-xs text-gray-400 mt-0.5">Serie: {{ $imei->serie }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900">{{ $imei->almacen->nombre ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($imei->compra?->proveedor)
                                    <a href="{{ route('compras.show', $imei->compra_id) }}"
                                       class="text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline"
                                       title="Ver compra {{ $imei->compra->numero_factura }}">
                                        {{ $imei->compra->proveedor->razon_social }}
                                    </a>
                                    <p class="text-xs text-gray-400">{{ $imei->compra->numero_factura }}</p>
                                @else
                                    <span class="text-xs text-gray-400 italic">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($imei->estado_imei == 'en_stock')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>En Stock
                                    </span>
                                @elseif($imei->estado_imei == 'reservado')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>Reservado
                                    </span>
                                @elseif($imei->estado_imei == 'vendido')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-shopping-cart mr-1"></i>Vendido
                                    </span>
                                @elseif($imei->estado_imei == 'garantia')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                        <i class="fas fa-shield-alt mr-1"></i>En Garantía
                                    </span>
                                @elseif($imei->estado_imei == 'devuelto')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">
                                        <i class="fas fa-undo mr-1"></i>Devuelto
                                    </span>
                                @elseif($imei->estado_imei == 'reemplazado')
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                        <i class="fas fa-exchange-alt mr-1"></i>Reemplazado
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        {{ $imei->estado_imei }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                {{ $imei->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- Ver detalles -->
                                    <a href="{{ route('inventario.imeis.show', $imei) }}" 
                                       class="text-blue-600 hover:text-blue-900" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Editar -->
                                    @if(auth()->user()->role->nombre != 'Tienda')
                                        <a href="{{ route('inventario.imeis.edit', $imei) }}" 
                                           class="text-green-600 hover:text-green-900" 
                                           title="Editar IMEI">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                    
                                    <!-- Ver QR -->
                                    <button onclick="verQR({{ $imei->id }})" 
                                            class="text-purple-600 hover:text-purple-900" 
                                            title="Ver código QR">
                                        <i class="fas fa-qrcode"></i>
                                    </button>
                                    
                                    <!-- Imprimir etiqueta -->
                                    <button onclick="imprimirEtiqueta({{ $imei->id }})" 
                                            class="text-gray-600 hover:text-gray-900" 
                                            title="Imprimir etiqueta">
                                        <i class="fas fa-tag"></i>
                                    </button>
                                    
                                    <!-- Cambiar estado rápido (solo para admin/almacenero) -->
                                    @if(auth()->user()->role->nombre != 'Tienda')
                                        <div class="relative inline-block">
                                            <button onclick="toggleEstadoMenu({{ $imei->id }})" 
                                                    class="text-yellow-600 hover:text-yellow-900"
                                                    title="Cambiar estado">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <div id="estado-menu-{{ $imei->id }}" 
                                                 class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                                <div class="py-1">
                                                    <button onclick="cambiarEstado({{ $imei->id }}, 'en_stock')" class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                                        <i class="fas fa-check-circle mr-2"></i>En Stock
                                                    </button>
                                                    <button onclick="cambiarEstado({{ $imei->id }}, 'reservado')" class="block w-full text-left px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50">
                                                        <i class="fas fa-clock mr-2"></i>Reservado
                                                    </button>
                                                    <button onclick="cambiarEstado({{ $imei->id }}, 'vendido')" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                        <i class="fas fa-shopping-cart mr-2"></i>Vendido
                                                    </button>
                                                    <button onclick="cambiarEstado({{ $imei->id }}, 'garantia')" class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                        <i class="fas fa-shield-alt mr-2"></i>Garantía
                                                    </button>
                                                    <button onclick="cambiarEstado({{ $imei->id }}, 'devuelto')" class="block w-full text-left px-4 py-2 text-sm text-orange-700 hover:bg-orange-50">
                                                        <i class="fas fa-undo mr-2"></i>Devuelto
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i class="fas fa-mobile-alt text-6xl text-gray-300 mb-4"></i>
                                <p class="text-lg font-medium text-gray-500">No hay IMEIs registrados</p>
                                @if(auth()->user()->role->nombre != 'Tienda')
                                    <a href="{{ route('inventario.imeis.create') }}" class="mt-4 inline-block bg-blue-900 text-white px-4 py-2 rounded-md hover:bg-blue-800">
                                        <i class="fas fa-plus mr-2"></i>Registrar Primer IMEI
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($imeis->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $imeis->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal para mostrar QR -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <span class="close" onclick="cerrarQR()">&times;</span>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-qrcode mr-2 text-purple-600"></i>
                Código QR del IMEI
            </h3>
            <div id="qrContainer" class="flex justify-center mb-4">
                <!-- Aquí se cargará el QR -->
            </div>
            <div class="flex justify-center space-x-3">
                <button onclick="descargarQR()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i>Descargar
                </button>
                <button onclick="imprimirQR()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-print mr-2"></i>Imprimir
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para etiqueta de impresión (oculto) -->
    <div id="etiquetaImprimir" style="display: none;"></div>

    <script>
        let currentImeiId = null;

        function toggleTodos() {
            const master = document.getElementById('seleccionarTodos');
            document.querySelectorAll('.imei-checkbox').forEach(cb => {
                cb.checked = master.checked;
            });
        }

        // Sincronizar checkbox maestro si se deselecciona uno individual
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('imei-checkbox')) {
                const total = document.querySelectorAll('.imei-checkbox').length;
                const checked = document.querySelectorAll('.imei-checkbox:checked').length;
                document.getElementById('seleccionarTodos').checked = (total === checked);
                document.getElementById('seleccionarTodos').indeterminate = (checked > 0 && checked < total);
            }
        });

        function filtrarPorEstado(estado) {
            document.getElementById('estadoFilter').value = estado;
            document.getElementById('filterForm').submit();
        }

        function toggleEstadoMenu(imeiId) {
            const menu = document.getElementById(`estado-menu-${imeiId}`);
            menu.classList.toggle('hidden');
            
            // Cerrar otros menús
            document.querySelectorAll('[id^="estado-menu-"]').forEach(m => {
                if (m.id !== `estado-menu-${imeiId}`) {
                    m.classList.add('hidden');
                }
            });
        }

        function cambiarEstado(imeiId, nuevoEstado) {
            if (!confirm(`¿Estás seguro de cambiar el estado a ${nuevoEstado}?`)) {
                return;
            }

            fetch(`/inventario/imeis/${imeiId}/estado`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ estado: nuevoEstado })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al cambiar el estado');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cambiar el estado');
            });
        }

        function verQR(imeiId) {
            currentImeiId = imeiId;
            const modal = document.getElementById('qrModal');
            const container = document.getElementById('qrContainer');
            
            container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-4xl text-gray-400"></i></div>';
            modal.style.display = 'block';
            
            fetch(`/inventario/imeis/${imeiId}/qr`)
                .then(response => response.blob())
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    container.innerHTML = `<img src="${url}" alt="QR IMEI" class="mx-auto w-48 h-48">`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="text-red-500">Error al cargar el QR</p>';
                });
        }

        function cerrarQR() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function descargarQR() {
            if (!currentImeiId) return;
            window.location.href = `/inventario/imeis/${currentImeiId}/qr/download`;
        }

        function imprimirQR() {
            if (!currentImeiId) return;
            window.location.href = `/inventario/imeis/${currentImeiId}/qr/print`;
        }

        function imprimirEtiqueta(imeiId) {
            fetch(`/inventario/imeis/${imeiId}/etiqueta`)
                .then(response => response.text())
                .then(html => {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(html);
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al generar la etiqueta');
                });
        }

        function generarEtiquetasMasivas() {
            const seleccionados = [];
            document.querySelectorAll('input[name="imei_seleccionado"]:checked').forEach(cb => {
                seleccionados.push(cb.value);
            });
            
            if (seleccionados.length === 0) {
                alert('Selecciona al menos un IMEI');
                return;
            }
            
            fetch('/inventario/imeis/etiquetas-masivas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ imeis: seleccionados })
            })
            .then(response => response.text())
            .then(html => {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(html);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            });
        }

        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[id^="estado-menu-"]') && !event.target.closest('button[onclick*="toggleEstadoMenu"]')) {
                document.querySelectorAll('[id^="estado-menu-"]').forEach(menu => menu.classList.add('hidden'));
            }
        });

        // Filtro dinámico de variantes según producto seleccionado
        document.getElementById('filtroProducto')?.addEventListener('change', function() {
            var pid = this.value;
            var sel = document.getElementById('filtroVariante');
            sel.querySelectorAll('option').forEach(function(opt) {
                if (!opt.value) return; // mantener "Todas"
                opt.style.display = (!pid || opt.dataset.producto === pid) ? '' : 'none';
            });
            // Resetear variante si no corresponde al producto
            if (pid && sel.value && sel.querySelector('option[value="' + sel.value + '"]')?.dataset.producto !== pid) {
                sel.value = '';
            }
        });
        // Disparar al cargar si viene filtro activo
        document.getElementById('filtroProducto')?.dispatchEvent(new Event('change'));
    </script>
</body>
</html>