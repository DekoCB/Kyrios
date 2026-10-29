<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Mis pedidos" subtitle="Bienvenido, {{ auth()->user()->name }}" />

        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                <i class="fas fa-box-open text-blue-900 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Mis pedidos: próximamente</h3>
            <p class="text-sm text-gray-500 mt-2 max-w-xl mx-auto">Desde aquí podrá hacer sus pedidos y seguir el estado de cada uno, paso a paso. Estamos terminando de prepararlo.</p>
            <a href="{{ route('profile.edit') }}" class="inline-block mt-6 border border-gray-200 rounded-lg px-4 py-2 text-sm hover:bg-gray-50"><i class="fas fa-user mr-2"></i>Mi perfil</a>
        </div>
    </div>
</body>
</html>
