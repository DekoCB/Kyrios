{{-- resources/views/catalogo/colores/edit.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Color - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-4">
            <a href="{{ route('catalogo.colores.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
                <i class="fas fa-arrow-left text-xs"></i> Volver a Colores
            </a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h1 class="text-xl font-bold text-white">Editar Color</h1>
                    <p class="text-blue-200 text-sm">Modificar información del color</p>
                </div>

                <form action="{{ route('catalogo.colores.update', $color) }}" method="POST" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')

                    {{-- Nombre --}}
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre del Color <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre"
                               value="{{ old('nombre', $color->nombre) }}" required
                               placeholder="Ej: Rojo Intenso, Azul Marino, Negro Mate"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition @error('nombre') border-red-500 @enderror">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Código --}}
                    <div>
                        <label for="codigo_color" class="block text-sm font-medium text-gray-700 mb-1">
                            Código de Color
                        </label>
                        <div class="flex gap-2">
                            <input type="text" name="codigo_color" id="codigo_color"
                                   value="{{ old('codigo_color', $color->codigo_color) }}"
                                   placeholder="Ej: COL-001"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <button type="button" onclick="sugerirCodigo()"
                                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg text-xs text-gray-600 transition whitespace-nowrap">
                                <i class="fas fa-magic mr-1"></i>Sugerir
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Código de referencia interno (opcional)</p>
                        @error('codigo_color')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Color picker + preview --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Visual</label>
                        <div class="flex items-start gap-4">
                            @php $hexVal = old('codigo_hex', $color->codigo_hex ?? '#44413A'); @endphp
                            <input type="color" id="colorPicker" value="{{ $hexVal }}"
                                   oninput="syncColor(this.value)"
                                   class="w-16 h-12 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <div class="flex-1">
                                <input type="text" name="codigo_hex" id="hexInput"
                                       value="{{ old('codigo_hex', $color->codigo_hex) }}"
                                       placeholder="#RRGGBB (opcional)"
                                       maxlength="7"
                                       oninput="syncFromHex(this.value)"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition @error('codigo_hex') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-400">Código hexadecimal del color (ej: #FF5733)</p>
                                @error('codigo_hex')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Preview card --}}
                        <div class="mt-4 p-4 rounded-xl border-2 border-gray-200 flex items-center gap-4 bg-gray-50">
                            <div id="colorSquare" class="w-16 h-16 rounded-xl shadow-md border border-gray-200 flex-shrink-0"
                                 style="background: {{ $hexVal }}"></div>
                            <div>
                                <p class="font-semibold text-gray-800" id="previewNombre">{{ old('nombre', $color->nombre) }}</p>
                                <p class="text-sm font-mono text-gray-500" id="previewHex">{{ old('codigo_hex', $color->codigo_hex) }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Editando</span>
                            </div>
                        </div>
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="activo"   {{ old('estado', $color->estado) == 'activo'   ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado', $color->estado) == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <a href="{{ route('catalogo.colores.index') }}"
                           class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-save mr-2"></i>Actualizar Color
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function syncColor(value) {
            document.getElementById('hexInput').value = value;
            document.getElementById('previewHex').textContent = value;
            document.getElementById('colorSquare').style.background = value;
        }

        function syncFromHex(value) {
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                document.getElementById('colorPicker').value = value;
                document.getElementById('colorSquare').style.background = value;
            }
            document.getElementById('previewHex').textContent = value;
        }

        document.getElementById('nombre').addEventListener('input', function () {
            document.getElementById('previewNombre').textContent = this.value || 'Sin nombre';
        });

        function sugerirCodigo() {
            const num = String(Math.floor(Math.random() * 900) + 100);
            document.getElementById('codigo_color').value = 'COL-' + num;
        }
    </script>
</body>
</html>
