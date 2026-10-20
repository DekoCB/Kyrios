<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Conteo - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Nuevo Conteo Físico" subtitle="Inicia un conteo de inventario para un almacén" />

        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2.5">
                        <i class="fas fa-clipboard-check text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Datos del Conteo</h2>
                        <p class="text-blue-200 text-xs">Se cargarán todos los productos del almacén seleccionado</p>
                    </div>
                </div>

                <form action="{{ route('inventario-fisico.store') }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Nombre del conteo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre"
                               value="{{ old('nombre', 'Conteo ' . now()->format('d/m/Y')) }}"
                               placeholder="Ej: Conteo mensual mayo 2026"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Almacén <span class="text-red-500">*</span>
                        </label>
                        <select name="almacen_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">— Selecciona un almacén —</option>
                            @foreach($almacenes as $a)
                                <option value="{{ $a->id }}" {{ old('almacen_id') == $a->id ? 'selected' : '' }}>
                                    {{ $a->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('almacen_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="{{ route('inventario-fisico.index') }}"
                           class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="flex-1 bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-play"></i> Iniciar Conteo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
