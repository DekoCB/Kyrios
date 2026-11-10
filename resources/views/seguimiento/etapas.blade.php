<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etapas de seguimiento - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Etapas de seguimiento" subtitle="Los pasos por los que pasa una orden de compra" />

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="max-w-4xl">
            <p class="text-sm text-gray-600 mb-4">
                Edita, ordena o desactiva las etapas. Los cambios aplican a las órdenes de compra que se creen desde ahora;
                las que ya están en curso conservan las suyas. Se ordenan de menor a mayor número.
            </p>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                <div class="divide-y divide-gray-100">
                    @foreach($etapas as $e)
                        <form method="POST" action="{{ route('seguimiento.etapas.update', $e) }}" class="p-4 flex flex-wrap items-center gap-3">
                            @csrf @method('PUT')
                            <input type="number" name="orden" value="{{ $e->orden }}" min="0" max="9999" aria-label="Orden de {{ $e->nombre }}"
                                   class="w-20 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                            <input type="text" name="nombre" value="{{ $e->nombre }}" maxlength="80" required aria-label="Nombre de la etapa"
                                   class="flex-1 min-w-[12rem] py-1.5 px-3 text-sm border border-gray-300 rounded-lg bg-white">
                            <select name="tipo_compra" aria-label="Tipo de orden" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                                @foreach($tipos as $k => $v)
                                    <option value="{{ $k }}" {{ $e->tipo_compra === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                <input type="checkbox" name="activo" value="1" {{ $e->activo ? 'checked' : '' }} class="rounded border-gray-300"> Activa
                            </label>
                            <button type="submit" class="text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50 rounded-lg px-3 py-1.5">Guardar</button>
                            <button type="submit" form="borrar-{{ $e->id }}" class="text-sm text-red-600 hover:text-red-800 px-2" title="Eliminar" aria-label="Eliminar {{ $e->nombre }}"><i class="fas fa-trash"></i></button>
                        </form>
                        <form id="borrar-{{ $e->id }}" method="POST" action="{{ route('seguimiento.etapas.destroy', $e) }}" data-confirm="¿Eliminar la etapa «{{ $e->nombre }}»? Las órdenes ya iniciadas conservan la suya.">@csrf @method('DELETE')</form>
                    @endforeach
                </div>
            </div>

            <form method="POST" action="{{ route('seguimiento.etapas.store') }}" class="bg-white rounded-xl shadow-sm p-5 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="nuevo_orden" class="block text-xs font-medium text-gray-600 mb-1">Orden</label>
                    <input id="nuevo_orden" type="number" name="orden" value="{{ ($etapas->max('orden') ?? 0) + 10 }}" min="0" max="9999" class="w-20 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label for="nuevo_nombre" class="block text-xs font-medium text-gray-600 mb-1">Nueva etapa</label>
                    <input id="nuevo_nombre" type="text" name="nombre" maxlength="80" required placeholder="Ej. Inspección de calidad" class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg bg-white">
                </div>
                <div>
                    <label for="nuevo_tipo" class="block text-xs font-medium text-gray-600 mb-1">Aplica a</label>
                    <select id="nuevo_tipo" name="tipo_compra" class="py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                        @foreach($tipos as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-plus mr-1"></i>Agregar</button>
            </form>
        </div>
    </div>
</body>
</html>
