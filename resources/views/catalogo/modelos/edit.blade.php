{{-- resources/views/catalogo/modelos/edit.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Modelo - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-4">
            <a href="{{ route('catalogo.modelos.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
                <i class="fas fa-arrow-left text-xs"></i> Volver a Modelos
            </a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h1 class="text-xl font-bold text-white">Editar Modelo</h1>
                    <p class="text-blue-200 text-sm">Modificar información del modelo</p>
                </div>

                <form action="{{ route('catalogo.modelos.update', $modelo) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="marca_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Marca <span class="text-red-500">*</span>
                        </label>
                        <select name="marca_id" id="marca_id" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Seleccione una marca...</option>
                            @foreach($marcas as $marca)
                                <option value="{{ $marca->id }}" {{ old('marca_id', $modelo->marca_id) == $marca->id ? 'selected' : '' }}>
                                    {{ $marca->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('marca_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                        <select name="categoria_id" id="categoria_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Sin categoría</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}" {{ old('categoria_id', $modelo->categoria_id) == $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('categoria_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre del Modelo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre"
                               value="{{ old('nombre', $modelo->nombre) }}" required
                               placeholder="Ej: Ring 30, Spot orientable"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="codigo_modelo" class="block text-sm font-medium text-gray-700 mb-1">Código de Modelo</label>
                        <div class="flex gap-2">
                            <input type="text" name="codigo_modelo" id="codigo_modelo"
                                   value="{{ old('codigo_modelo', $modelo->codigo_modelo) }}"
                                   placeholder="Ej: A2896, SM-S918B"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <button type="button" onclick="sugerirCodigo()"
                                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg text-xs text-gray-600 transition whitespace-nowrap">
                                <i class="fas fa-magic mr-1"></i>Sugerir
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Código de fábrica o referencia del modelo</p>
                        @error('codigo_modelo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="especificaciones_tecnicas" class="block text-sm font-medium text-gray-700 mb-1">
                            Especificaciones Técnicas
                        </label>
                        <textarea name="especificaciones_tecnicas" id="especificaciones_tecnicas" rows="3"
                                  placeholder="Ej: Pantalla 6.7&quot;, 256GB, Cámara 48MP..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">{{ old('especificaciones_tecnicas', $modelo->especificaciones_tecnicas) }}</textarea>
                    </div>

                    @if($modelo->imagen_referencia)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Imagen Actual</label>
                        <img src="{{ Storage::url($modelo->imagen_referencia) }}" alt="{{ $modelo->nombre }}"
                             class="h-24 w-24 object-cover rounded-lg border-2 border-gray-300">
                    </div>
                    @endif

                    <div>
                        <label for="imagen_referencia" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $modelo->imagen_referencia ? 'Cambiar Imagen' : 'Imagen de Referencia' }}
                        </label>
                        <div class="flex items-center gap-4">
                            <div id="imagePreviewContainer" class="hidden">
                                <img id="imagePreview" src="" alt="Vista previa"
                                     class="h-24 w-24 object-cover rounded-lg border-2 border-blue-300">
                            </div>
                            <div class="flex-1">
                                <input type="file" name="imagen_referencia" id="imagen_referencia"
                                       accept="image/jpeg,image/jpg,image/png,image/webp"
                                       onchange="previewImage(event)"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                              file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100">
                                <p class="mt-1 text-xs text-gray-400">JPG, PNG, WEBP. Máx 2MB. Dejar vacío para conservar la imagen actual.</p>
                            </div>
                        </div>
                        @error('imagen_referencia')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="activo"   {{ old('estado', $modelo->estado) == 'activo'   ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado', $modelo->estado) == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <a href="{{ route('catalogo.modelos.index') }}"
                           class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-save mr-2"></i>Actualizar Modelo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function sugerirCodigo() {
            const marca  = document.getElementById('marca_id');
            const texto  = marca.options[marca.selectedIndex]?.text || '';
            const prefijo = texto.substring(0, 3).toUpperCase().replace(/\s/g, '') || 'MOD';
            const num    = String(Math.floor(Math.random() * 9000) + 1000);
            document.getElementById('codigo_modelo').value = prefijo + '-' + num;
        }
    </script>
</body>
</html>
