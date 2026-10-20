
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg text-center">
            <h1 class="text-3xl font-bold text-blue-900 mb-4">{{ config('app.name') }}</h1>
            <p class="text-gray-600 mb-6">Sistema de Gestión de Importaciones</p>
            <div class="space-y-2">
                <a href="/login" class="block w-full bg-blue-900 text-white px-4 py-2 rounded hover:bg-blue-800">Iniciar Sesión</a>
                <p class="text-sm text-gray-500 mt-4">Laravel funcionando correctamente ✓</p>
            </div>
        </div>
    </div>
</body>
</html>
