{{-- resources/views/catalogo/marcas/create.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Marca - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-4">
            <a href="{{ route('catalogo.marcas.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
                <i class="fas fa-arrow-left text-xs"></i> Volver a Marcas
            </a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h1 class="text-xl font-bold text-white">Nueva Marca</h1>
                    <p class="text-blue-200 text-sm">Registrar una nueva marca en el catálogo</p>
                </div>

                <form action="{{ route('catalogo.marcas.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    {{-- Nombre --}}
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre de la Marca <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre"
                               value="{{ old('nombre') }}" required
                               placeholder="Ej: Apple, Samsung, Xiaomi"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition @error('nombre') border-red-500 @enderror">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                        @enderror
                    </div>


                    {{-- Logo --}}
                    <div>
                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">Logo de la Marca</label>
                        <div class="flex items-center gap-4">
                            <div id="logoPreviewContainer" class="hidden">
                                <img id="logoPreview" src="" alt="Vista previa"
                                     class="h-20 w-20 object-contain rounded-lg border-2 border-gray-300 p-1">
                            </div>
                            <div class="flex-1">
                                <input type="file" name="logo" id="logo"
                                       accept="image/jpeg,image/jpg,image/png,image/webp,image/svg+xml"
                                       onchange="previewLogo(event)"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                              file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100">
                                <p class="mt-1 text-xs text-gray-400">JPG, PNG, WEBP, SVG. Máx 2MB</p>
                            </div>
                        </div>
                        @error('logo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" id="descripcion" rows="3"
                                  placeholder="Descripción de la marca..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">{{ old('descripcion') }}</textarea>
                    </div>

                    {{-- Categorías --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categorías</label>
                        <p class="text-xs text-gray-400 mb-2">Selecciona las categorías a las que pertenece esta marca</p>
                        @if($categorias->isEmpty())
                            <p class="text-sm text-gray-400 italic">No hay categorías activas registradas.</p>
                        @else
                            @php $selectedCategorias = old('categorias', []); @endphp
                            <div class="grid grid-cols-2 gap-2 border border-gray-200 rounded-lg p-3 max-h-48 overflow-y-auto">
                                @foreach($categorias as $categoria)
                                    <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded p-1">
                                        <input type="checkbox"
                                               name="categorias[]"
                                               value="{{ $categoria->id }}"
                                               {{ in_array($categoria->id, $selectedCategorias) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700">{{ $categoria->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('categorias')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="activo"   {{ old('estado', 'activo') == 'activo'   ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado') == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <a href="{{ route('catalogo.marcas.index') }}"
                           class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-save mr-2"></i>Guardar Marca
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewLogo(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                alert('El archivo supera el límite de 2MB');
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('logoPreview').src = e.target.result;
                document.getElementById('logoPreviewContainer').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
