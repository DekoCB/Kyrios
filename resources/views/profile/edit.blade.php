<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Mi perfil" subtitle="Sus datos de contacto y su contraseña" />

        @include('cotizaciones.partials.mensajes')

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-5xl">
            <form method="POST" action="{{ route('profile.update') }}" class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                @csrf @method('PATCH')
                <h3 class="font-semibold text-gray-900"><i class="fas fa-id-card mr-2 text-gray-400"></i>Datos personales</h3>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                    <input type="text" name="name" required maxlength="255" value="{{ old('name', $user->name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required maxlength="255" value="{{ old('email', $user->email) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Teléfono</label>
                    <input type="text" name="telefono" maxlength="20" value="{{ old('telefono', $user->telefono) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm bg-gray-50 rounded-lg p-3">
                    <div><dt class="text-xs text-gray-400 uppercase">Rol</dt><dd class="font-medium text-gray-800">{{ $user->role?->nombre }}</dd></div>
                    <div><dt class="text-xs text-gray-400 uppercase">DNI</dt><dd class="font-medium text-gray-800">{{ $user->dni ?: '—' }}</dd></div>
                </dl>
                <div class="flex justify-end">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg"><i class="fas fa-save mr-1"></i>Guardar</button>
                </div>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="bg-white rounded-xl shadow-sm p-6 space-y-4 self-start">
                @csrf @method('PUT')
                <h3 class="font-semibold text-gray-900"><i class="fas fa-key mr-2 text-gray-400"></i>Cambiar contraseña</h3>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Contraseña actual</label>
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nueva contraseña (mín. 8 caracteres)</label>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Repita la nueva contraseña</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg"><i class="fas fa-lock mr-1"></i>Actualizar contraseña</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
