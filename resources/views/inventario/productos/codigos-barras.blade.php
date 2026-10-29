<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Códigos de Barras - {{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Códigos de Barras</h1>
                    <p class="text-sm text-gray-600 mt-1">Gestiona los códigos de barras de {{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}</p>
                </div>
                <a href="{{ route('inventario.productos.show', $producto) }}" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i>Volver al producto
                </a>
            </div>
        </div>

        <!-- Mensajes -->
        @if(session('success'))
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-xl mr-3"></i>
                    <p>{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                    <p>{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Mostrar código principal actual del producto -->
        @if($producto->codigo_barras)
        <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Código principal del producto:</strong>
                    </p>
                    <p class="text-xl font-mono font-bold text-blue-900 mt-1">
                        {{ $producto->codigo_barras }}
                    </p>
                    <p class="text-xs text-blue-600 mt-1">
                        Este código se usa en facturas y búsquedas rápidas
                    </p>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>
                        Principal en producto
                    </span>
                </div>
            </div>
        </div>
        @endif

        <!-- Botón Imprimir Etiquetas -->
        <div class="flex justify-end mb-4">
            <button type="button"
                    onclick="abrirModalImpresion()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <i class="fas fa-print"></i>
                Imprimir Etiquetas
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Columna izquierda: Información del producto -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-900 px-4 py-3">
                        <h3 class="font-semibold text-white">
                            <i class="fas fa-box mr-2"></i>
                            Información del Producto
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="flex justify-center mb-4">
                            @if($producto->imagen)
                                <img src="{{ $producto->imagen_url }}" alt="{{ \App\Support\EtiquetaProducto::nombrePrincipal($producto) }}" class="h-32 w-32 object-cover rounded-lg border-2 border-gray-200">
                            @else
                                <div class="h-32 w-32 rounded-lg bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-box text-4xl text-gray-400"></i>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Código:</span>
                                <span class="text-gray-900 ml-2">{{ $producto->codigo }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Nombre:</span>
                                <span class="text-gray-900 ml-2">{{ \App\Support\EtiquetaProducto::nombre($producto) }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Categoría:</span>
                                <span class="text-gray-900 ml-2">{{ $producto->categoria->nombre ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Marca:</span>
                                <span class="text-gray-900 ml-2">{{ $producto->marca->nombre ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Modelo:</span>
                                <span class="text-gray-900 ml-2">{{ $producto->modelo->nombre ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Color:</span>
                                <span class="text-gray-900 ml-2">{{ $producto->color->nombre ?? 'N/A' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="bg-yellow-50 p-3 rounded-lg">
                                <p class="text-xs text-yellow-700">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Código principal:</strong> Se usará en facturas y búsquedas rápidas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Lista de códigos de barras -->
            <div class="md:col-span-2">
                <!-- Formulario para agregar nuevo código -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-green-600 px-4 py-3">
                        <h3 class="font-semibold text-white">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Agregar Nuevo Código de Barras
                        </h3>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('inventario.productos.codigos-barras.store', $producto) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="relative">
                                    <label for="codigo_barras" class="block text-sm font-medium text-gray-700 mb-2">
                                        Código de Barras <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex space-x-2">
                                        <div class="flex-1">
                                            <input type="text"
                                                   name="codigo_barras"
                                                   id="codigo_barras"
                                                   value="{{ old('codigo_barras') }}"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                                   placeholder="Ej: 1234567890123"
                                                   required>
                                        </div>
                                        <button type="button"
                                                id="btnGenerarCodigo"
                                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            <i class="fas fa-sync-alt mr-2"></i>
                                            Generar
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">
                                        Descripción
                                    </label>
                                    <input type="text"
                                           name="descripcion"
                                           id="descripcion"
                                           value="{{ old('descripcion') }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                           placeholder="Ej: Unidad, Caja x6, Pack">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="flex items-center">
                                        <input type="checkbox"
                                               name="es_principal"
                                               value="1"
                                               class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                               {{ old('es_principal') ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-700">
                                            Establecer como código principal
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                    <i class="fas fa-save mr-2"></i>
                                    Guardar Código
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de códigos existentes -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-900 px-4 py-3">
                        <h3 class="font-semibold text-white">
                            <i class="fas fa-list mr-2"></i>
                            Códigos de Barras Registrados
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Principal</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($codigosBarras as $codigo)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-mono">{{ $codigo->codigo_barras }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900">{{ $codigo->descripcion ?? '-' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($codigo->es_principal)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Principal
                                            </span>
                                        @else
                                            <form action="{{ route('inventario.productos.codigos-barras.principal', $codigo) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-gray-400 hover:text-green-600" title="Establecer como principal">
                                                    <i class="far fa-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <form action="{{ route('inventario.productos.codigos-barras.destroy', $codigo) }}"
                                              method="POST"
                                              class="inline"
                                              data-confirm="¿Estás seguro de eliminar este código de barras?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <i class="fas fa-barcode text-6xl mb-4"></i>
                                            <p class="text-lg font-medium">No hay códigos de barras registrados</p>
                                            <p class="text-sm text-gray-400 mt-1">Agrega el primer código usando el formulario</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Código principal actual -->
                    @php $principal = $codigosBarras->firstWhere('es_principal', true); @endphp
                    @if($principal)
                    <div class="px-6 py-4 bg-blue-50 border-t border-blue-200">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Código principal actual:</strong> {{ $principal->codigo_barras }}
                            @if($principal->descripcion)
                                ({{ $principal->descripcion }})
                            @endif
                        </p>
                    </div>
                    @endif
                </div>

                <!-- Botón de volver -->
                <div class="mt-6 flex justify-end">
                    <a href="{{ route('inventario.productos.show', $producto) }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver al Producto
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MODAL DE IMPRESIÓN ===================== --}}
    <div id="modalImpresion"
         class="fixed inset-0 z-50 hidden items-center justify-center p-4"
         style="display:none!important">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="cerrarModalImpresion()"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-print text-blue-600"></i>
                    Imprimir Etiquetas de Código de Barras
                </h3>
                <button onclick="cerrarModalImpresion()" class="text-gray-400 hover:text-gray-600 text-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 space-y-5 max-h-[75vh] overflow-y-auto">

                {{-- Selección de códigos --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-check-square mr-1 text-blue-500"></i>
                        Seleccionar códigos a imprimir
                    </label>
                    <div class="space-y-1.5 max-h-44 overflow-y-auto border rounded-lg p-3 bg-gray-50">
                        @forelse($codigosBarras as $codigo)
                        <label class="flex items-center gap-3 p-2 hover:bg-white rounded-lg cursor-pointer transition">
                            <input type="checkbox"
                                   name="codigos_imprimir[]"
                                   value="{{ $codigo->id }}"
                                   data-codigo="{{ $codigo->codigo_barras }}"
                                   data-desc="{{ $codigo->descripcion }}"
                                   class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                            <span class="flex-1 font-mono text-sm">{{ $codigo->codigo_barras }}</span>
                            @if($codigo->descripcion)
                                <span class="text-xs text-gray-500">({{ $codigo->descripcion }})</span>
                            @endif
                            @if($codigo->es_principal)
                                <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">Principal</span>
                            @endif
                        </label>
                        @empty
                        <p class="text-sm text-gray-400 text-center py-3">No hay códigos registrados.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Configuración --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-copy mr-1 text-blue-500"></i>
                            Copias por código
                        </label>
                        <input type="number" id="copias" value="1" min="1" max="100"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-ruler-combined mr-1 text-blue-500"></i>
                            Tamaño de etiqueta
                        </label>
                        <select id="tamano_etiqueta" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="small">Pequeña (2 × 1 cm)</option>
                            <option value="medium" selected>Mediana (4 × 2 cm)</option>
                            <option value="large">Grande (6 × 3 cm)</option>
                        </select>
                    </div>
                </div>

                {{-- Diseño de página --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-table mr-1 text-blue-500"></i>
                        Diseño de página
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach([
                            ['value' => '2x4',  'icon' => 'fa-th',      'cols' => '2 col.', 'rows' => '4 fil.', 'total' => '8/hoja'],
                            ['value' => '3x6',  'icon' => 'fa-th-large','cols' => '3 col.', 'rows' => '6 fil.', 'total' => '18/hoja'],
                            ['value' => '4x8',  'icon' => 'fa-th-list', 'cols' => '4 col.', 'rows' => '8 fil.', 'total' => '32/hoja'],
                        ] as $d)
                        <label class="diseno-card cursor-pointer border-2 border-gray-200 rounded-xl p-3 text-center hover:border-blue-300 transition {{ $loop->first ? 'border-blue-500 bg-blue-50' : '' }}">
                            <input type="radio" name="diseno" value="{{ $d['value'] }}" class="hidden" {{ $loop->first ? 'checked' : '' }}>
                            <i class="fas {{ $d['icon'] }} text-2xl text-gray-400 mb-1 block"></i>
                            <p class="text-xs font-medium text-gray-700">{{ $d['cols'] }} × {{ $d['rows'] }}</p>
                            <p class="text-xs text-gray-400">{{ $d['total'] }}</p>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Información en la etiqueta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-info-circle mr-1 text-blue-500"></i>
                        Información en la etiqueta
                    </label>
                    <div class="space-y-2 bg-gray-50 p-3 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="mostrar_nombre" checked class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            <span class="text-sm text-gray-700">Nombre del producto</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="mostrar_descripcion" checked class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            <span class="text-sm text-gray-700">Descripción del código</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="mostrar_codigo_texto" checked class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            <span class="text-sm text-gray-700">Número de código visible</span>
                        </label>
                    </div>
                </div>

                {{-- Resumen --}}
                <div class="bg-blue-50 rounded-lg p-4 flex items-center justify-between">
                    <div class="text-sm text-blue-800">
                        <i class="fas fa-tag mr-1"></i>
                        Total de etiquetas: <span id="totalEtiquetas" class="font-bold text-blue-900">0</span>
                    </div>
                    <div class="text-sm text-blue-600" id="resumenHojas"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-2xl">
                <button onclick="cerrarModalImpresion()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm">
                    Cancelar
                </button>
                <button onclick="imprimirEtiquetas()"
                        class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition flex items-center gap-2 text-sm">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
            </div>
        </div>
    </div>

    {{-- JsBarcode CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

    <script>
        // ======================== GENERAR CÓDIGO (formulario) ========================
        document.getElementById('btnGenerarCodigo')?.addEventListener('click', function() {
            const btn = this;
            const codigoInput = document.getElementById('codigo_barras');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generando...';

            const tipoProducto = 'accesorio';

            fetch('{{ route("inventario.productos.generar-codigo-barras") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ tipo: tipoProducto })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    codigoInput.value = data.codigo;
                    codigoInput.classList.add('border-green-500', 'bg-green-50');
                    setTimeout(() => codigoInput.classList.remove('border-green-500', 'bg-green-50'), 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('Error al generar código'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Generar';
            });
        });

        // Solo dígitos en el input
        document.getElementById('codigo_barras')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // ======================== MODAL IMPRESIÓN ========================
        function abrirModalImpresion() {
            const modal = document.getElementById('modalImpresion');
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            actualizarTotalEtiquetas();
        }

        function cerrarModalImpresion() {
            const modal = document.getElementById('modalImpresion');
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }

        // Diseño cards: resaltar seleccionado
        document.querySelectorAll('.diseno-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.diseno-card').forEach(c => {
                    c.classList.remove('border-blue-500', 'bg-blue-50');
                    c.classList.add('border-gray-200');
                });
                this.classList.add('border-blue-500', 'bg-blue-50');
                this.classList.remove('border-gray-200');
                this.querySelector('input[type=radio]').checked = true;
                actualizarTotalEtiquetas();
            });
        });

        function actualizarTotalEtiquetas() {
            const seleccionados = document.querySelectorAll('input[name="codigos_imprimir[]"]:checked').length;
            const copias        = parseInt(document.getElementById('copias').value) || 1;
            const diseno        = document.querySelector('input[name="diseno"]:checked')?.value || '2x4';
            const porHoja       = { '2x4': 8, '3x6': 18, '4x8': 32 }[diseno] ?? 8;

            const total = seleccionados * copias;
            const hojas = Math.ceil(total / porHoja) || 0;

            document.getElementById('totalEtiquetas').textContent = total;
            document.getElementById('resumenHojas').textContent   =
                hojas > 0 ? `≈ ${hojas} hoja${hojas > 1 ? 's' : ''}` : '';
        }

        document.querySelectorAll('input[name="codigos_imprimir[]"], #copias').forEach(el => {
            el.addEventListener('change', actualizarTotalEtiquetas);
        });
        document.getElementById('copias')?.addEventListener('input', actualizarTotalEtiquetas);

        // ======================== IMPRIMIR ========================
        function imprimirEtiquetas() {
            const seleccionados = Array.from(
                document.querySelectorAll('input[name="codigos_imprimir[]"]:checked')
            ).map(cb => ({
                codigo: cb.dataset.codigo,
                desc:   cb.dataset.desc || ''
            }));

            if (seleccionados.length === 0) {
                alert('Selecciona al menos un código de barras.');
                return;
            }

            const copias         = parseInt(document.getElementById('copias').value) || 1;
            const diseno         = document.querySelector('input[name="diseno"]:checked')?.value || '2x4';
            const tamano         = document.getElementById('tamano_etiqueta').value;
            const mostrarNombre  = document.getElementById('mostrar_nombre').checked;
            const mostrarDesc    = document.getElementById('mostrar_descripcion').checked;
            const mostrarTexto   = document.getElementById('mostrar_codigo_texto').checked;
            const productoNombre = @json(\App\Support\EtiquetaProducto::nombre($producto));

            // Expandir copias
            const etiquetas = [];
            seleccionados.forEach(s => {
                for (let i = 0; i < copias; i++) etiquetas.push(s);
            });

            // Configuración de tamaño
            const tamanos = {
                small:  { w: '5.5cm', h: '2cm',   bh: 25, fs: '6px' },
                medium: { w: '9cm',   h: '3.2cm',  bh: 40, fs: '7px' },
                large:  { w: '12cm',  h: '4.5cm',  bh: 55, fs: '9px' },
            };
            const cfg = tamanos[tamano] ?? tamanos.medium;

            // Columnas de página
            const cols = { '2x4': 2, '3x6': 3, '4x8': 4 }[diseno] ?? 2;

            // Generar SVGs para cada código usando JsBarcode en un canvas temporal
            function barcodeSVG(codigo) {
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                try {
                    JsBarcode(svg, codigo, {
                        format:      'CODE128',
                        height:      cfg.bh,
                        displayValue: false,
                        margin:       2,
                    });
                } catch (e) {
                    // Código inválido para barcode, devolver vacío
                    return '';
                }
                // Serializar SVG a string
                return new XMLSerializer().serializeToString(svg);
            }

            // Construir HTML de etiquetas
            const etiquetasHTML = etiquetas.map(e => `
                <div class="etiqueta">
                    ${mostrarNombre ? `<div class="prod-nombre">${productoNombre}</div>` : ''}
                    ${mostrarDesc && e.desc ? `<div class="prod-desc">${e.desc}</div>` : ''}
                    <div class="barcode-wrap">${barcodeSVG(e.codigo)}</div>
                    ${mostrarTexto ? `<div class="prod-codigo">${e.codigo}</div>` : ''}
                </div>
            `).join('');

            // Abrir ventana de impresión
            const win = window.open('', '_blank', 'width=900,height=700');
            win.document.write(`<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas - ${productoNombre}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #fff; }

  .grid-etiquetas {
    display: grid;
    grid-template-columns: repeat(${cols}, 1fr);
    gap: 4px;
    padding: 8px;
  }

  .etiqueta {
    width: ${cfg.w};
    height: ${cfg.h};
    border: 1px solid #ccc;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3px;
    overflow: hidden;
    page-break-inside: avoid;
  }

  .prod-nombre {
    font-size: ${cfg.fs};
    font-weight: bold;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    color: #111;
    line-height: 1.2;
    margin-bottom: 1px;
  }

  .prod-desc {
    font-size: calc(${cfg.fs} - 1px);
    color: #555;
    text-align: center;
    margin-bottom: 1px;
  }

  .barcode-wrap svg {
    max-width: 100%;
    height: auto;
  }

  .prod-codigo {
    font-size: calc(${cfg.fs} - 1px);
    font-family: monospace;
    color: #333;
    margin-top: 1px;
    letter-spacing: 0.5px;
  }

  @media print {
    body { margin: 0; }
    .grid-etiquetas { padding: 4px; gap: 2px; }
    @page { margin: 5mm; }
  }
</style>
</head>
<body>
<div class="grid-etiquetas">
${etiquetasHTML}
</div>
<script>
  window.onload = function() { window.print(); };
<\/script>
</body>
</html>`);
            win.document.close();
        }
    </script>
</body>
</html>
