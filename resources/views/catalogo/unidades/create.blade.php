{{-- resources/views/catalogo/unidades/create.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Unidad de Medida - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-4">
            <a href="{{ route('catalogo.unidades.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
                <i class="fas fa-arrow-left text-xs"></i> Volver a Unidades
            </a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h1 class="text-xl font-bold text-white">Nueva Unidad de Medida</h1>
                    <p class="text-blue-200 text-sm">Registrar una nueva unidad de medida</p>
                </div>

                <form action="{{ route('catalogo.unidades.store') }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    {{-- Tipo (radio cards) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Medida <span class="text-red-500">*</span>
                        </label>
                        @php $tipoOld = old('categoria', 'unidad'); @endphp
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                            @php
                                $tipos = [
                                    'unidad'   => ['icono' => 'fa-box',             'label' => 'Unidad'],
                                    'peso'     => ['icono' => 'fa-weight-hanging',  'label' => 'Peso'],
                                    'volumen'  => ['icono' => 'fa-flask',            'label' => 'Volumen'],
                                    'longitud' => ['icono' => 'fa-ruler',            'label' => 'Longitud'],
                                    'otros'    => ['icono' => 'fa-ellipsis-h',       'label' => 'Otros'],
                                ];
                            @endphp
                            @foreach($tipos as $valor => $info)
                                <label class="cursor-pointer">
                                    <input type="radio" name="categoria" value="{{ $valor }}"
                                           {{ $tipoOld == $valor ? 'checked' : '' }}
                                           class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-gray-200
                                                peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                                hover:border-blue-300 transition text-gray-500 text-center">
                                        <i class="fas {{ $info['icono'] }} text-xl mb-1"></i>
                                        <span class="text-xs font-medium">{{ $info['label'] }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('categoria')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nombre --}}
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre"
                               value="{{ old('nombre') }}" required
                               placeholder="Ej: Kilogramo, Metro, Litro, Unidad"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Abreviatura --}}
                    <div>
                        <label for="abreviatura" class="block text-sm font-medium text-gray-700 mb-1">
                            Abreviatura <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="abreviatura" id="abreviatura"
                               value="{{ old('abreviatura') }}" required maxlength="10"
                               placeholder="Ej: kg, m, L, und"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <p class="mt-1 text-xs text-gray-400">Símbolo corto que aparecerá en documentos (máx. 10 caracteres)</p>
                        @error('abreviatura')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" id="descripcion" rows="2"
                                  placeholder="Descripción de la unidad de medida..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">{{ old('descripcion') }}</textarea>
                    </div>

                    {{-- Configuración --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Configuración</h3>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="permite_decimales" value="0">
                            <input type="checkbox" name="permite_decimales" id="permite_decimales" value="1"
                                   {{ old('permite_decimales') ? 'checked' : '' }}
                                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Permite decimales</span>
                                <p class="text-xs text-gray-400">Activa si esta unidad admite cantidades fraccionarias (ej: 1.5 kg, 2.75 m)</p>
                            </div>
                        </label>
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
                        <a href="{{ route('catalogo.unidades.index') }}"
                           class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-save mr-2"></i>Guardar Unidad
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
