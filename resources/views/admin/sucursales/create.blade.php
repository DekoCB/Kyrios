<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Sucursal</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Nueva Sucursal" subtitle="Se creará automáticamente con sus series de comprobantes y un almacén vinculado" />

    <div class="max-w-2xl">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
            <div class="text-sm text-blue-700">
                <strong>Proceso automático:</strong> Al crear la sucursal, el sistema generará automáticamente un código único (S001, S002…) y un almacén vinculado. Si es <strong>Tienda</strong>, también se crean las series de comprobantes estándar (FA, BA, FC, FD, T, CO). Todos los establecimientos anexos deben estar registrados en SUNAT.
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <form action="{{ route('admin.sucursales.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Selector de tipo: Tienda o Almacén --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Establecimiento SUNAT *</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {{-- Opción Tienda --}}
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo" value="tienda" class="hidden peer"
                                    {{ old('tipo', 'tienda') === 'tienda' ? 'checked' : '' }}>
                                <div class="flex items-center gap-3 p-4 rounded-xl border-2 border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300 transition-colors">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                        <i class="fas fa-store text-blue-600 text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">Tienda / Punto de Venta</p>
                                        <p class="text-xs text-gray-500">Emite comprobantes, gestiona caja y ventas al público</p>
                                    </div>
                                </div>
                            </label>
                            {{-- Opción Almacén --}}
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo" value="almacen" class="hidden peer"
                                    {{ old('tipo') === 'almacen' ? 'checked' : '' }}>
                                <div class="flex items-center gap-3 p-4 rounded-xl border-2 border-gray-200 peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:border-orange-300 transition-colors">
                                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                                        <i class="fas fa-warehouse text-orange-600 text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">Almacén / Depósito</p>
                                        <p class="text-xs text-gray-500">Establecimiento anexo SUNAT, genera traslados internos</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        @error('tipo')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Sucursal *</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" maxlength="150" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-500 @enderror"
                            placeholder="Ej: Sucursal Centro, Tienda Miraflores…">
                        @error('nombre')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion') }}" maxlength="300"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" name="departamento" value="{{ old('departamento') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
                        <input type="text" name="provincia" value="{{ old('provincia') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
                        <input type="text" name="distrito" value="{{ old('distrito') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
                        <input type="text" name="ubigeo" value="{{ old('ubigeo') }}" maxlength="6"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="150101">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                        <input type="text" name="telefono" value="{{ old('telefono') }}" maxlength="20"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="150"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($esPrimera)
                            <input type="checkbox" name="es_principal" id="es_principal" value="1" checked disabled
                                class="w-4 h-4 text-yellow-500 border-gray-300 rounded cursor-not-allowed opacity-70">
                            {{-- campo oculto para que el valor se envíe aunque el checkbox esté disabled --}}
                            <input type="hidden" name="es_principal" value="1">
                            <label for="es_principal" class="text-sm font-medium text-yellow-700 flex items-center gap-1.5">
                                <i class="fas fa-star text-yellow-500 text-xs"></i>
                                Primera sucursal — se marcará como Principal automáticamente
                            </label>
                        @else
                            <input type="checkbox" name="es_principal" id="es_principal" value="1" {{ old('es_principal') ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="es_principal" class="text-sm font-medium text-gray-700">Marcar como Sucursal Principal</label>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3 justify-end mt-6 pt-5 border-t">
                    <a href="{{ route('admin.sucursales.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-5 rounded-lg transition-colors">
                        Cancelar
                    </a>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-plus"></i> Crear Sucursal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
