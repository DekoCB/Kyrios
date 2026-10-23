<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <x-sidebar :role="auth()->user()->role->nombre" />

    <!-- Main Content -->
    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header -->
        <x-header 
            title="Editar Categoría" 
            subtitle="Actualiza la información de {{ $categoria->nombre }}" 
        />

        <!-- Formulario -->
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Header del formulario -->
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-edit mr-2"></i>
                        Editar Información
                    </h2>
                </div>

                <!-- Formulario -->
                <form action="{{ route('inventario.categorias.update', $categoria) }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Código (solo lectura) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Código
                        </label>
                        <input type="text" 
                               value="{{ $categoria->codigo }}"
                               class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed"
                               readonly>
                        <p class="mt-1 text-xs text-gray-500">El código no se puede modificar</p>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-6">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de la Categoría <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="nombre" 
                               id="nombre" 
                               value="{{ old('nombre', $categoria->nombre) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-500 @enderror"
                               required>
                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                

                    <!-- Imagen -->
                    <div class="mb-6">
                        <label for="imagen" class="block text-sm font-medium text-gray-700 mb-2">
                            Imagen de la Categoría
                        </label>
                        <div class="flex items-center space-x-4">
                            <!-- Imagen actual -->
                            @if($categoria->imagen)
                                <div id="current-image">
                                    <img src="{{ $categoria->imagen_url }}" alt="{{ $categoria->nombre }}" class="h-32 w-32 object-cover rounded-lg border-2 border-gray-300">
                                    <p class="text-xs text-gray-500 mt-1 text-center">Imagen actual</p>
                                </div>
                            @endif
                            
                            <!-- Preview de nueva imagen -->
                            <div id="preview-container" class="hidden">
                                <img id="preview-image" src="" alt="Vista previa" class="h-32 w-32 object-cover rounded-lg border-2 border-blue-500">
                                <p class="text-xs text-blue-600 mt-1 text-center">Nueva imagen</p>
                            </div>
                            
                            <div class="flex-1">
                                <input type="file" 
                                        name="imagen" 
                                        id="imagen" 
                                        accept="image/jpeg,image/jpg,image/png,image/webp"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                        onchange="previewImage(event)">
                                <p class="mt-1 text-xs text-gray-500">Formatos: JPG, JPEG, PNG, WEBP. Máximo 2MB</p>
                                @if($categoria->imagen)
                                    <p class="mt-1 text-xs text-gray-500">Dejar vacío para mantener la imagen actual</p>
                                @endif
                            </div>
                        </div>
                        @error('imagen')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Estado -->
                    <div class="mb-6">
                        <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">
                            Estado <span class="text-red-500">*</span>
                        </label>
                        <select name="estado" 
                                id="estado"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('estado') border-red-500 @enderror"
                                required>
                            <option value="activo" {{ old('estado', $categoria->estado) == 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado', $categoria->estado) == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                        @error('estado')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Información adicional -->
                    <div class="mb-6 bg-gray-50 border border-gray-200 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Información adicional:</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Productos asociados:</span> 
                                <span class="text-gray-900">{{ $categoria->productos->count() }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Fecha de creación:</span> 
                                <span class="text-gray-900">{{ $categoria->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="{{ route('inventario.categorias.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Actualizar Categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('preview-image');
            const container = document.getElementById('preview-container');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>