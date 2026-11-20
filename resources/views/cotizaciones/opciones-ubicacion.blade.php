<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pisos y ambientes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Pisos y ambientes" subtitle="Las opciones que se sugieren al armar una cotización por piso o por ambiente" />
        @include('cotizaciones.partials.mensajes')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach(\App\Models\OpcionUbicacion::TIPOS as $tipo => $titulo)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <h2 class="font-bold text-gray-800 mb-3">{{ $titulo }}</h2>

                    <form method="POST" action="{{ route('cotizaciones.ubicaciones.store') }}" class="flex gap-2 mb-4">
                        @csrf
                        <input type="hidden" name="tipo" value="{{ $tipo }}">
                        <input name="nombre" maxlength="60" required placeholder="{{ $tipo === 'piso' ? 'Nuevo piso (ej. Mezanine)' : 'Nuevo ambiente (ej. Walk-in closet)' }}" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <button class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold px-4 rounded-lg"><i class="fas fa-plus mr-1"></i>Agregar</button>
                    </form>

                    <div class="divide-y divide-gray-100">
                        @forelse($opciones[$tipo] ?? [] as $o)
                            <div class="py-2 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('cotizaciones.ubicaciones.update', $o) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="number" name="orden" value="{{ $o->orden }}" min="0" class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm" title="Orden en la lista">
                                    <input name="nombre" value="{{ $o->nombre }}" maxlength="60" required class="flex-1 min-w-[8rem] border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                    <label class="text-xs text-gray-600 inline-flex items-center gap-1"><input type="checkbox" name="activo" value="1" @checked($o->activo)> Visible</label>
                                    <button class="text-blue-600 hover:text-blue-800 text-sm" title="Guardar"><i class="fas fa-save"></i></button>
                                </form>
                                <form method="POST" action="{{ route('cotizaciones.ubicaciones.destroy', $o) }}" data-confirm="¿Eliminar «{{ $o->nombre }}»? Las cotizaciones ya hechas no cambian.">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-sm" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 py-4">Sin opciones: se usarán las de fábrica.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-4">En la cotización siempre se puede escribir otro valor a mano; esta lista solo es la sugerencia.</p>
    </div>
</body>
</html>
