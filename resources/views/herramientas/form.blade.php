<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $herramienta ? 'Editar' : 'Nueva' }} herramienta - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php
        $v = fn ($campo, $defecto = null) => old($campo, $herramienta?->{$campo} ?? $defecto);
        $clase = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white';
    @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$herramienta ? 'Editar ' . $herramienta->codigo : 'Nueva herramienta'" subtitle="Datos de la herramienta y cuántas unidades se tienen" />

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $herramienta ? route('herramientas.update', $herramienta) : route('herramientas.store') }}" class="max-w-3xl bg-white rounded-lg shadow-md p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            @csrf
            @if($herramienta) @method('PUT') @endif

            <div class="md:col-span-2">
                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre <span class="text-red-500">*</span></label>
                <input id="nombre" type="text" name="nombre" value="{{ $v('nombre') }}" required maxlength="150" placeholder="Ej. Taladro percutor 800 W" class="{{ $clase }}">
            </div>
            <div>
                <label for="categoria" class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                <input id="categoria" type="text" name="categoria" value="{{ $v('categoria') }}" maxlength="80" placeholder="Ej. Eléctricas, de altura, de medición" class="{{ $clase }}">
            </div>
            <div>
                <label for="cantidad_total" class="block text-sm font-medium text-gray-700 mb-2">Cantidad que se tiene <span class="text-red-500">*</span></label>
                <input id="cantidad_total" type="number" name="cantidad_total" value="{{ $v('cantidad_total', 1) }}" required min="1" class="{{ $clase }}">
            </div>
            <div>
                <label for="ubicacion" class="block text-sm font-medium text-gray-700 mb-2">Dónde se guarda</label>
                <input id="ubicacion" type="text" name="ubicacion" value="{{ $v('ubicacion') }}" maxlength="120" class="{{ $clase }}">
            </div>
            @if($herramienta)
                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                    <select id="estado" name="estado" class="{{ $clase }}">
                        <option value="activa" {{ $v('estado') === 'activa' ? 'selected' : '' }}>Activa</option>
                        <option value="baja" {{ $v('estado') === 'baja' ? 'selected' : '' }}>De baja</option>
                    </select>
                </div>
            @endif
            <div class="md:col-span-2">
                <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000" class="{{ $clase }}">{{ $v('descripcion') }}</textarea>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3">
                <a href="{{ route('herramientas.index') }}" class="px-5 py-2.5 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Guardar</button>
            </div>
        </form>
    </div>
</body>
</html>
