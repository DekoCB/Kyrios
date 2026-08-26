<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - CORPORACIÓN ADIVON SAC</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Editar Producto" subtitle="Actualiza la información de {{ $producto->nombre }}" />

        <div class="max-w-5xl mx-auto">
            <!-- Resumen del producto -->
            <div class="mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-500">Código:</span>
                        <span class="block text-gray-900 font-mono font-bold">{{ $producto->codigo }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Tipo:</span>
                        <span class="block mt-1">
                            @if($producto->tipo_inventario == 'serie')
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                    <i class="fas fa-mobile-alt mr-1"></i>Serie/IMEI
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-boxes mr-1"></i>Cantidad
                                </span>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Stock Actual:</span>
                        <span class="block text-gray-900 font-bold text-lg">{{ $producto->stock_actual }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Creado:</span>
                        <span class="block text-gray-900">{{ $producto->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-edit mr-2"></i>
                        Editar Información
                    </h2>
                </div>

                <form action="{{ route('inventario.productos.update', $producto) }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf
                    @method('PUT')

                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-300 rounded-lg p-4">
                            <p class="text-sm font-semibold text-red-700 mb-2">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                Por favor corrige los siguientes errores:
                            </p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li class="text-sm text-red-600">{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- TIPO DE INVENTARIO (solo lectura) -->
                    <div class="mb-8 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                @if($producto->tipo_inventario == 'serie')
                                    <i class="fas fa-mobile-alt text-3xl text-blue-600 mr-3"></i>
                                    <div>
                                        <p class="font-semibold text-gray-900">Tipo: Stock por Serie/IMEI</p>
                                        <p class="text-sm text-gray-500">El stock se controla por IMEI individual</p>
                                    </div>
                                @else
                                    <i class="fas fa-boxes text-3xl text-green-600 mr-3"></i>
                                    <div>
                                        <p class="font-semibold text-gray-900">Tipo: Stock por Cantidad</p>
                                        <p class="text-sm text-gray-500">El stock se controla numéricamente</p>
                                    </div>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">
                                <i class="fas fa-lock mr-1"></i>No editable
                            </span>
                        </div>
                        {{-- Campo oculto para que el tipo se envíe en el form --}}
                        <input type="hidden" name="tipo_inventario" value="{{ $producto->tipo_inventario }}">
                    </div>

                    <!-- GARANTÍA (solo para Serie/IMEI) -->
                    @if($producto->tipo_inventario == 'serie')
                    <div class="mb-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-3">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Garantía
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="dias_garantia" class="block text-sm font-medium text-gray-700 mb-2">
                                    Días de Garantía
                                </label>
                                <input type="number" name="dias_garantia" id="dias_garantia"
                                       value="{{ old('dias_garantia', $producto->dias_garantia ?? 365) }}" min="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="tipo_garantia" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Garantía
                                </label>
                                <select name="tipo_garantia" id="tipo_garantia"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="proveedor" {{ old('tipo_garantia', $producto->tipo_garantia) == 'proveedor' ? 'selected' : '' }}>Proveedor</option>
                                    <option value="tienda"    {{ old('tipo_garantia', $producto->tipo_garantia) == 'tienda'    ? 'selected' : '' }}>Tienda</option>
                                    <option value="fabricante"{{ old('tipo_garantia', $producto->tipo_garantia) == 'fabricante'? 'selected' : '' }}>Fabricante</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- INFORMACIÓN BÁSICA -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-info-circle mr-2 text-blue-900"></i>
                            Información Básica
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div class="md:col-span-2">
                                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Producto <span class="text-red-500">*</span>
                                </label>
                                <div class="flex space-x-2">
                                    <input type="text" name="nombre" id="nombre"
                                           value="{{ old('nombre', $producto->nombre) }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           required>
                                    <button type="button" id="btnSugerirNombre"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">
                                        <i class="fas fa-magic mr-2"></i>Sugerir
                                    </button>
                                </div>
                                @error('nombre')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Categoría <span class="text-red-500">*</span>
                                </label>
                                <select name="categoria_id" id="categoria_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="">Seleccione una categoría</option>
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria->id }}"
                                                {{ old('categoria_id', $producto->categoria_id) == $categoria->id ? 'selected' : '' }}>
                                            {{ $categoria->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('categoria_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Marca (filtrada por categoría) -->
                            <div>
                                <label for="marca_id" class="block text-sm font-medium text-gray-700 mb-2">Marca</label>
                                <select name="marca_id" id="marca_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Cargando marcas...</option>
                                </select>
                                @error('marca_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Modelo (filtrado por marca) -->
                            <div>
                                <label for="modelo_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Modelo
                                    @if($producto->tipo_inventario == 'serie')
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-gray-400 text-xs font-normal">(opcional)</span>
                                    @endif
                                </label>
                                <select name="modelo_id" id="modelo_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        {{ $producto->tipo_inventario == 'serie' ? 'required' : '' }}>
                                    <option value="">Cargando modelos...</option>
                                </select>
                                @error('modelo_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>


                            <!-- Unidad de Medida -->
                            <div>
                                <label for="unidad_medida_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Unidad de Medida <span class="text-red-500">*</span>
                                </label>
                                <select name="unidad_medida_id" id="unidad_medida_id"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required>
                                    @foreach($unidades as $unidad)
                                        <option value="{{ $unidad->id }}" {{ old('unidad_medida_id', $producto->unidad_medida_id) == $unidad->id ? 'selected' : '' }}>
                                            {{ $unidad->nombre }} ({{ $unidad->abreviatura }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('unidad_medida_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Código de Barras -->
                            <div class="md:col-span-2">
                                <label for="codigo_barras" class="block text-sm font-medium text-gray-700 mb-2">
                                    Código de Barras
                                </label>
                                <div class="flex space-x-2">
                                    <input type="text" name="codigo_barras" id="codigo_barras"
                                           value="{{ old('codigo_barras', $producto->codigo_barras) }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           placeholder="Código único del producto">
                                    <button type="button" id="btnGenerarCodigo"
                                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 whitespace-nowrap">
                                        <i class="fas fa-sync-alt mr-2"></i>Generar
                                    </button>
                                </div>
                                @if($producto->codigo_barras)
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        También puedes gestionar múltiples códigos en
                                        <a href="{{ route('inventario.productos.codigos-barras', $producto) }}" class="text-blue-600 hover:underline">
                                            Gestión de Códigos de Barras
                                        </a>
                                    </p>
                                @endif
                                @error('codigo_barras')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Código de Producto SUNAT (UNSPSC) -->
                            <div class="md:col-span-2" x-data="{
                                query: '{{ $productoSunatSeleccionado ? addslashes($productoSunatSeleccionado->codigo . ' — ' . $productoSunatSeleccionado->producto) : '' }}',
                                resultados: [],
                                mostrarDropdown: false,
                                seleccionado: {{ $productoSunatSeleccionado ? '{codigo: ' . json_encode($productoSunatSeleccionado->codigo) . ', producto: ' . json_encode($productoSunatSeleccionado->producto) . '}' : 'null' }},
                                buscando: false,
                                buscar() {
                                    if (this.query.trim().length < 2) { this.resultados = []; this.mostrarDropdown = false; return; }
                                    this.buscando = true;
                                    fetch(`{{ route('catalogo.productos-sunat.buscar') }}?q=${encodeURIComponent(this.query.trim())}`)
                                        .then(r => r.json())
                                        .then(data => { this.resultados = data; this.mostrarDropdown = true; this.buscando = false; })
                                        .catch(() => { this.buscando = false; });
                                },
                                elegir(r) {
                                    this.seleccionado = r;
                                    this.query = r.codigo + ' — ' + r.producto;
                                    this.mostrarDropdown = false;
                                },
                                quitar() {
                                    this.seleccionado = null;
                                    this.query = '';
                                    this.resultados = [];
                                }
                            }">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Código de Producto SUNAT (UNSPSC)
                                    <span class="ml-1 text-xs font-normal text-gray-400">— opcional</span>
                                    <a href="https://cpe.sunat.gob.pe/informacion_general/codigoproducto" target="_blank" rel="noopener"
                                       class="ml-2 text-xs font-normal text-blue-600 hover:underline">
                                        <i class="fas fa-external-link-alt text-[10px] mr-0.5"></i>Ver catálogo oficial SUNAT
                                    </a>
                                </label>
                                <div class="relative">
                                    <input type="text"
                                           x-model="query"
                                           @input.debounce.400ms="buscar()"
                                           @focus="if (resultados.length) mostrarDropdown = true"
                                           @click.outside="mostrarDropdown = false"
                                           autocomplete="off"
                                           placeholder="Buscar por código o descripción (ej: diesel, oro, teléfono...)"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <template x-if="seleccionado">
                                        <button type="button" @click="quitar()"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </template>

                                    <div x-show="mostrarDropdown && resultados.length > 0" x-cloak
                                         class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                        <template x-for="r in resultados" :key="r.id">
                                            <button type="button" @click="elegir(r)"
                                                    class="w-full text-left px-4 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0">
                                                <span class="font-mono text-xs text-blue-700" x-text="r.codigo"></span>
                                                <span class="block text-sm text-gray-800" x-text="r.producto"></span>
                                                <span class="block text-xs text-gray-400" x-text="[r.segmento, r.familia, r.clase].filter(Boolean).join(' › ')"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <input type="hidden" name="codigo_sunat" :value="seleccionado ? seleccionado.codigo : ''">
                                @error('codigo_sunat')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Busca por descripción o código (ej: "diesel", "oro", "teléfono"). Opcional para la mayoría de productos; SUNAT lo exige desde agosto 2026 solo para ciertos bienes regulados (metales preciosos, combustibles, bienes con detracción/percepción).
                                </p>
                            </div>

                            <!-- Descripción -->
                            <div class="md:col-span-2">
                                <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                                <textarea name="descripcion" id="descripcion" rows="2"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('descripcion', $producto->descripcion) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- CONTROL DE STOCK -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-boxes mr-2 text-blue-900"></i>
                            Control de Stock
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="stock_minimo" class="block text-sm font-medium text-gray-700 mb-2">
                                    Stock Mínimo <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="stock_minimo" id="stock_minimo"
                                       value="{{ old('stock_minimo', $producto->stock_minimo) }}" min="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                                @error('stock_minimo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="stock_maximo" class="block text-sm font-medium text-gray-700 mb-2">
                                    Stock Máximo <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="stock_maximo" id="stock_maximo"
                                       value="{{ old('stock_maximo', $producto->stock_maximo) }}" min="1"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                                @error('stock_maximo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- IMAGEN Y ESTADO -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="imagen" class="block text-sm font-medium text-gray-700 mb-2">
                                Imagen del Producto
                            </label>
                            @if($producto->imagen)
                                <div class="mb-2 flex items-center space-x-3">
                                    <img src="{{ $producto->imagen_url }}" alt="{{ $producto->nombre }}"
                                         class="h-20 w-20 object-cover rounded-lg border-2 border-gray-200">
                                    <span class="text-xs text-gray-500">Imagen actual</span>
                                </div>
                            @endif
                            <div class="flex items-center space-x-4">
                                <div id="imagePreviewContainer" class="hidden">
                                    <img id="imagePreview" src="" alt="Vista previa" class="h-20 w-20 object-cover rounded-lg border">
                                </div>
                                <input type="file" name="imagen" id="imagen" accept="image/*"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                       onchange="previewImage(event)">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Deja vacío para conservar la imagen actual</p>
                        </div>

                        <div>
                            <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select name="estado" id="estado"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    required>
                                <option value="activo"        {{ old('estado', $producto->estado) == 'activo'        ? 'selected' : '' }}>Activo</option>
                                <option value="inactivo"      {{ old('estado', $producto->estado) == 'inactivo'      ? 'selected' : '' }}>Inactivo</option>
                                <option value="descontinuado" {{ old('estado', $producto->estado) == 'descontinuado' ? 'selected' : '' }}>Descontinuado</option>
                            </select>
                            @error('estado')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <a href="{{ route('inventario.productos.codigos-barras', $producto) }}"
                           class="px-4 py-2 text-sm text-blue-700 border border-blue-300 rounded-lg hover:bg-blue-50">
                            <i class="fas fa-barcode mr-2"></i>Gestionar Códigos de Barras
                        </a>
                        <div class="flex space-x-3">
                            <a href="{{ route('inventario.productos.show', $producto) }}"
                               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                                <i class="fas fa-save mr-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Preview de imagen nueva
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        // Carga de marcas filtradas por categoría
        function cargarMarcasPorCategoria(categoriaId, marcaSeleccionada = null) {
            const marcaSelect = document.getElementById('marca_id');
            const modeloSelect = document.getElementById('modelo_id');

            marcaSelect.innerHTML = '<option value="">Cargando marcas...</option>';
            marcaSelect.disabled = true;
            modeloSelect.innerHTML = '<option value="">Primero seleccione una marca</option>';
            modeloSelect.disabled = true;

            if (!categoriaId) {
                marcaSelect.innerHTML = '<option value="">Seleccione una categoría primero</option>';
                marcaSelect.disabled = false;
                return;
            }

            fetch(`/catalogo/marcas-por-categoria/${categoriaId}`)
                .then(response => response.json())
                .then(data => {
                    marcaSelect.innerHTML = '<option value="">Sin marca</option>';
                    data.forEach(marca => {
                        const selected = (marcaSeleccionada && marca.id == marcaSeleccionada) ? 'selected' : '';
                        marcaSelect.innerHTML += `<option value="${marca.id}" ${selected}>${marca.nombre}</option>`;
                    });
                    marcaSelect.disabled = false;

                    if (marcaSeleccionada) {
                        cargarModelosPorMarca(marcaSeleccionada);
                    }
                })
                .catch(() => {
                    marcaSelect.innerHTML = '<option value="">Error al cargar marcas</option>';
                    marcaSelect.disabled = false;
                });
        }

        // Carga de modelos filtrados por marca
        function cargarModelosPorMarca(marcaId, modeloSeleccionado = null) {
            const modeloSelect = document.getElementById('modelo_id');

            modeloSelect.innerHTML = '<option value="">Cargando modelos...</option>';
            modeloSelect.disabled = true;

            if (!marcaId) {
                modeloSelect.innerHTML = '<option value="">Sin modelo</option>';
                modeloSelect.disabled = false;
                return;
            }

            fetch(`/catalogo/modelos-por-marca/${marcaId}`)
                .then(response => response.json())
                .then(data => {
                    modeloSelect.innerHTML = '<option value="">Sin modelo</option>';
                    data.forEach(modelo => {
                        const selected = (modeloSeleccionado && modelo.id == modeloSeleccionado) ? 'selected' : '';
                        modeloSelect.innerHTML += `<option value="${modelo.id}" ${selected}>${modelo.nombre}</option>`;
                    });
                    modeloSelect.disabled = false;
                })
                .catch(() => {
                    modeloSelect.innerHTML = '<option value="">Error al cargar modelos</option>';
                    modeloSelect.disabled = false;
                });
        }

        // Sugerir nombre desde marca + modelo
        function sugerirNombre() {
            const marca = document.getElementById('marca_id').selectedOptions[0]?.text || '';
            const modelo = document.getElementById('modelo_id').selectedOptions[0]?.text || '';

            let partes = [];
            if (marca && marca !== 'Sin marca') partes.push(marca);
            if (modelo && modelo !== 'Sin modelo') partes.push(modelo);

            if (partes.length > 0) {
                document.getElementById('nombre').value = partes.join(' ');
            } else {
                alert('Selecciona al menos marca o modelo para generar una sugerencia');
            }
        }

        // Botón Generar código de barras
        document.getElementById('btnGenerarCodigo')?.addEventListener('click', function() {
            const btn = this;
            const codigoInput = document.getElementById('codigo_barras');
            const tipoBarras = '{{ $producto->tipo_inventario === "serie" ? "celular" : "accesorio" }}';

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generando...';

            fetch('{{ route("inventario.productos.generar-codigo-barras") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ tipo: tipoBarras })
            })
            .then(response => response.json())
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
                btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Generar';
            });
        });

        document.getElementById('btnSugerirNombre')?.addEventListener('click', sugerirNombre);

        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const categoriaId = {{ old('categoria_id', $producto->categoria_id ?? 'null') }};
            const marcaId     = {{ old('marca_id',     $producto->marca_id     ?? 'null') }};
            const modeloId    = {{ old('modelo_id',    $producto->modelo_id    ?? 'null') }};

            // Evento cambio de categoría → recargar marcas
            document.getElementById('categoria_id')?.addEventListener('change', function() {
                cargarMarcasPorCategoria(this.value);
            });

            // Evento cambio de marca → recargar modelos
            document.getElementById('marca_id')?.addEventListener('change', function() {
                cargarModelosPorMarca(this.value);
            });

            // Pre-cargar marcas y modelos del producto actual
            if (categoriaId) {
                cargarMarcasPorCategoria(categoriaId, marcaId);
                // Los modelos se cargan en cascada dentro de cargarMarcasPorCategoria
                // pero necesitamos pasar el modelo seleccionado
                if (marcaId) {
                    // Sobrescribir para pasar modeloId también
                    const originalCargarMarcas = cargarMarcasPorCategoria;
                    fetch(`/catalogo/marcas-por-categoria/${categoriaId}`)
                        .then(r => r.json())
                        .then(data => {
                            const marcaSelect = document.getElementById('marca_id');
                            marcaSelect.innerHTML = '<option value="">Sin marca</option>';
                            data.forEach(marca => {
                                const selected = marca.id == marcaId ? 'selected' : '';
                                marcaSelect.innerHTML += `<option value="${marca.id}" ${selected}>${marca.nombre}</option>`;
                            });
                            marcaSelect.disabled = false;
                            if (marcaId) cargarModelosPorMarca(marcaId, modeloId);
                        });
                }
            } else {
                document.getElementById('marca_id').innerHTML = '<option value="">Sin marca</option>';
                document.getElementById('marca_id').disabled = false;
                document.getElementById('modelo_id').innerHTML = '<option value="">Sin modelo</option>';
                document.getElementById('modelo_id').disabled = false;
            }
        });
    </script>
</body>
</html>
