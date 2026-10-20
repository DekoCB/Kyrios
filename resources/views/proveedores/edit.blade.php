<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proveedor - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Editar Proveedor"
            subtitle="Actualiza la información del proveedor"
        />

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                {{-- Información del Proveedor --}}
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-edit mr-2"></i>Información del Proveedor
                    </h2>
                </div>

                <form action="{{ route('proveedores.update', $proveedor) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                RUC <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="ruc" maxlength="11" required
                                   placeholder="11 dígitos"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('ruc') border-red-500 @enderror"
                                   value="{{ old('ruc', $proveedor->ruc) }}">
                            @error('ruc')
                                <p class="text-red-600 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Razón Social <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="razon_social" required
                                   placeholder="Nombre de la empresa"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('razon_social') border-red-500 @enderror"
                                   value="{{ old('razon_social', $proveedor->razon_social) }}">
                            @error('razon_social')
                                <p class="text-red-600 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Comercial</label>
                            <input type="text" name="nombre_comercial"
                                   placeholder="Nombre comercial (opcional)"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('nombre_comercial', $proveedor->nombre_comercial) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                            <input type="text" name="direccion"
                                   placeholder="Dirección fiscal"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('direccion', $proveedor->direccion) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                            <input type="text" name="telefono" maxlength="20"
                                   placeholder="Número de teléfono"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('telefono', $proveedor->telefono) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email"
                                   placeholder="correo@ejemplo.com"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('email', $proveedor->email) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de Contacto</label>
                            <input type="text" name="contacto_nombre"
                                   placeholder="Persona de contacto"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('contacto_nombre', $proveedor->contacto_nombre) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select name="estado"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="activo" {{ old('estado', $proveedor->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="inactivo" {{ old('estado', $proveedor->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <a href="{{ route('proveedores.index') }}"
                           class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition-colors">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                        <button type="submit"
                                class="px-6 py-3 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800 transition-colors">
                            <i class="fas fa-save mr-2"></i>Actualizar Proveedor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
