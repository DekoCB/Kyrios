{{-- resources/views/users/show.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Usuario - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Detalle de Usuario" 
            subtitle="Información completa del usuario"
        />

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                {{-- Header con avatar --}}
                <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-8 text-white text-center">
                    <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center text-blue-900 font-bold text-3xl mx-auto mb-4">
                        {{ substr($user->name, 0, 2) }}
                    </div>
                    <h2 class="text-2xl font-bold">{{ $user->name }}</h2>
                    <p class="text-blue-200">{{ $user->email }}</p>
                    <span class="inline-flex mt-2 px-3 py-1 text-sm font-semibold rounded-full 
                        @if($user->role->nombre == 'Administrador') bg-purple-200 text-purple-900
                        @elseif($user->role->nombre == 'Almacenero') bg-blue-200 text-blue-900
                        @elseif($user->role->nombre == 'Tienda') bg-green-200 text-green-900
                        @elseif($user->role->nombre == 'Vendedor') bg-yellow-200 text-yellow-900
                        @else bg-gray-200 text-gray-900
                        @endif">
                        {{ $user->role->nombre }}
                    </span>
                </div>

                {{-- Información detallada --}}
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">ID de Usuario</p>
                            <p class="font-semibold text-lg">#{{ $user->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Estado</p>
                            <p class="font-semibold">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $user->estado == 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($user->estado) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">DNI</p>
                            <p class="font-semibold">{{ $user->dni ?? 'No registrado' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Teléfono</p>
                            <p class="font-semibold">{{ $user->telefono ?? 'No registrado' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Almacén/Tienda</p>
                            <p class="font-semibold">{{ $user->almacen->nombre ?? 'No asignado' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Fecha de Registro</p>
                            <p class="font-semibold">{{ $user->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500">Última Actualización</p>
                            <p class="font-semibold">{{ $user->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                        <a href="{{ route('users.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                        <a href="{{ route('users.edit', $user) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-lg">
                            <i class="fas fa-edit mr-2"></i>Editar
                        </a>
                    </div>
                </div>
            </div>

            {{-- Estadísticas adicionales del usuario --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="bg-white rounded-lg shadow p-4 text-center">
                    <p class="text-2xl font-bold text-blue-900">0</p>
                    <p class="text-xs text-gray-500">Ventas Realizadas</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">0</p>
                    <p class="text-xs text-gray-500">Compras Registradas</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4 text-center">
                    <p class="text-2xl font-bold text-purple-600">0</p>
                    <p class="text-xs text-gray-500">Movimientos</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>