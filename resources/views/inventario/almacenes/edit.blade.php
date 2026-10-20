<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Almacén - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Editar Almacén" 
            subtitle="Actualiza la información de {{ $almacen->nombre }}" 
        />

        <div class="max-w-3xl mx-auto">
            <!-- Info del almacén -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Código:</span>
                        <span class="text-gray-900 ml-2">{{ $almacen->codigo }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Creado:</span>
                        <span class="text-gray-900 ml-2">{{ $almacen->created_at->format('d/m/Y') }}</span>
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

                <form action="{{ route('inventario.almacenes.update', $almacen) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre del Almacén <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $almacen->nombre) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>

                        <div>
                            <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo <span class="text-red-500">*</span>
                            </label>
                            @if($almacen->sucursal_id)
                                {{-- Almacén auto-creado con sucursal: tipo bloqueado --}}
                                <input type="hidden" name="tipo" value="{{ $almacen->tipo }}">
                                <div class="flex items-center gap-2 px-4 py-2 border border-orange-200 bg-orange-50 rounded-lg text-sm text-orange-700">
                                    <i class="fas fa-store"></i>
                                    <span>Tienda (vinculada a sucursal {{ $almacen->sucursal->nombre ?? '' }})</span>
                                    <span class="ml-auto text-xs text-orange-400">No editable</span>
                                </div>
                            @else
                                <select name="tipo" id="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                                    <option value="principal" {{ old('tipo', $almacen->tipo) == 'principal' ? 'selected' : '' }}>Principal (almacén central)</option>
                                    <option value="deposito" {{ old('tipo', $almacen->tipo) == 'deposito' ? 'selected' : '' }}>Depósito (almacén secundario)</option>
                                    <option value="temporal" {{ old('tipo', $almacen->tipo) == 'temporal' ? 'selected' : '' }}>Temporal</option>
                                </select>
                            @endif
                        </div>

                        <div>
                            <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select name="estado" id="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                                <option value="activo" {{ old('estado', $almacen->estado) == 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="inactivo" {{ old('estado', $almacen->estado) == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>

                        <!-- Encargado -->
                        <div>
                            <label for="encargado_id" class="block text-sm font-medium text-gray-700 mb-2">Encargado</label>
                            <select name="encargado_id" id="encargado_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Sin asignar</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->id }}" {{ old('encargado_id', $almacen->encargado_id) == $usuario->id ? 'selected' : '' }}>
                                        {{ $usuario->name }} ({{ $usuario->role->nombre }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="telefono" class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $almacen->telefono) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="md:col-span-2">
                            <label for="direccion" class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                            <textarea name="direccion" id="direccion" rows="2"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('direccion', $almacen->direccion) }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                        <a href="{{ route('inventario.almacenes.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                            <i class="fas fa-save mr-2"></i>Actualizar Almacén
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>