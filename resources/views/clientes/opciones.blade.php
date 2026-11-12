<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opciones de los filtros de clientes</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />
<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Opciones de los filtros de clientes" subtitle="Las listas que aparecen al registrar un cliente y en los filtros del listado" />

    @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ $errors->first() }}</div>@endif

    <a href="{{ route('clientes.index') }}" class="inline-block mb-4 text-sm text-gray-500 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Volver a clientes</a>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" data-opciones-cliente>
        @foreach($grupos as $grupo => $etiqueta)
            <section class="bg-white rounded-xl shadow-sm p-5" data-grupo="{{ $grupo }}">
                <h2 class="font-semibold text-gray-800 mb-3">{{ $etiqueta }}</h2>
                <ul class="divide-y divide-gray-100 mb-4">
                    @forelse($opciones[$grupo] ?? [] as $op)
                        <li class="py-2 flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('clientes.opciones.update', $op) }}" class="flex flex-1 min-w-[12rem] items-center gap-2">
                                @csrf @method('PUT')
                                <input type="text" name="nombre" value="{{ $op->nombre }}" maxlength="80" required class="flex-1 rounded-lg border-gray-300 text-sm @if(!$op->activo) text-gray-400 line-through @endif">
                                <button class="text-xs font-semibold text-blue-700 hover:underline">Guardar</button>
                            </form>
                            <span class="text-xs text-gray-400">{{ $usos[$grupo][$op->nombre] ?? 0 }} cliente(s)</span>
                            <form method="POST" action="{{ route('clientes.opciones.update', $op) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="nombre" value="{{ $op->nombre }}">
                                <input type="hidden" name="activo" value="{{ $op->activo ? 0 : 1 }}">
                                <button class="text-xs text-gray-600 hover:underline">{{ $op->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                            <form method="POST" action="{{ route('clientes.opciones.destroy', $op) }}" data-confirm="¿Eliminar «{{ $op->nombre }}»?">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-400">Aún no hay opciones.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('clientes.opciones.store') }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="grupo" value="{{ $grupo }}">
                    <input type="text" name="nombre" maxlength="80" required placeholder="Nueva opción" class="flex-1 rounded-lg border-gray-300 text-sm">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-3 py-2 rounded-lg"><i class="fas fa-plus mr-1"></i>Agregar</button>
                </form>
            </section>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-4">Al renombrar una opción, los clientes que la tenían se actualizan. Una opción con clientes no se elimina: desactívala y deja de ofrecerse sin perder el dato.</p>
</div>
</body>
</html>
