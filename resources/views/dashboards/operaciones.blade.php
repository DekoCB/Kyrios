<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Operaciones - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Panel de Operaciones" subtitle="Tareas internas y externas, {{ auth()->user()->name }}" />

        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 mb-4">
                <i class="fas fa-tools text-amber-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Programación de tareas e instalaciones: próximamente</h3>
            <p class="text-sm text-gray-500 mt-2 max-w-xl mx-auto">Aquí se programarán las tareas internas y las instalaciones en obra. Mientras tanto puede consultar el stock, los traslados y las guías de remisión, y llevar su caja chica.</p>
            <div class="flex flex-wrap justify-center gap-3 mt-6 text-sm">
                <a href="{{ route('inventario.productos.index') }}" class="border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50"><i class="fas fa-boxes mr-2"></i>Productos y stock</a>
                <a href="{{ route('traslados.index') }}" class="border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50"><i class="fas fa-truck-moving mr-2"></i>Traslados</a>
                <a href="{{ route('guias-remision.index') }}" class="border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50"><i class="fas fa-file-alt mr-2"></i>Guías de remisión</a>
                <a href="{{ route('caja-chica.index') }}" class="border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50"><i class="fas fa-wallet mr-2"></i>Mi caja chica</a>
            </div>
        </div>
    </div>
</body>
</html>
