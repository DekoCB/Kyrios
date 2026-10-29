<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $categoria->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$categoria->nombre" :subtitle="'Código ' . $categoria->codigo" />

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('inventario.categorias.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            @if(in_array(auth()->user()->role->nombre, ['Administración', 'Logística'], true))
                <a href="{{ route('inventario.categorias.edit', $categoria) }}" class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 text-yellow-800 hover:bg-yellow-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-edit"></i> Editar</a>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-5 text-sm self-start">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Datos de la categoría</h3>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $categoria->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ ucfirst($categoria->estado) }}</span>
                </div>
                <dl class="space-y-2">
                    <div><dt class="text-xs text-gray-400 uppercase">Código</dt><dd class="font-mono">{{ $categoria->codigo }}</dd></div>
                    <div><dt class="text-xs text-gray-400 uppercase">Productos activos</dt><dd>{{ $categoria->productos->count() }}</dd></div>
                </dl>
            </div>

            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-900">Productos de la categoría</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                            <th class="px-4 py-2 text-left">Código</th><th class="px-4 py-2 text-left">Producto</th><th class="px-4 py-2 text-right">Stock</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($categoria->productos as $producto)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-mono">{{ $producto->codigo }}</td>
                                    <td class="px-4 py-2"><a href="{{ route('inventario.productos.show', $producto) }}" class="font-medium text-blue-700 hover:underline">{{ \App\Support\EtiquetaProducto::nombre($producto) }}</a></td>
                                    <td class="px-4 py-2 text-right">{{ (int) $producto->stock_actual }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Esta categoría todavía no tiene productos activos</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
