<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-bell text-blue-700"></i> Avisos
                @if($sinLeer > 0)
                    <span class="text-sm font-semibold bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full">{{ $sinLeer }} sin leer</span>
                @endif
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">Alertas del sistema: stock mínimo, aprobaciones y fechas de entrega.</p>
        </div>
        @if($sinLeer > 0)
            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-medium text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-50">
                    Marcar todos como leídos
                </button>
            </form>
        @endif
    </div>

    <div class="p-6 max-w-4xl">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
            @forelse($notificaciones as $n)
                @php
                    $nivel = $n->data['nivel'] ?? 'info';
                    $icono = $nivel === 'alerta' ? 'fa-triangle-exclamation text-amber-500' : 'fa-circle-info text-blue-500';
                @endphp
                <a href="{{ route('notificaciones.abrir', $n->id) }}"
                   class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 {{ $n->read_at ? 'opacity-60' : '' }}">
                    <i class="fas {{ $icono }} mt-1"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm {{ $n->read_at ? 'font-medium' : 'font-semibold' }} text-gray-800">{{ $n->data['titulo'] ?? 'Aviso' }}</p>
                        <p class="text-sm text-gray-600">{{ $n->data['mensaje'] ?? '' }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @unless($n->read_at)
                        <span class="mt-1 w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0" title="Sin leer"></span>
                    @endunless
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-gray-500">No tienes avisos por ahora.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $notificaciones->links() }}</div>
    </div>
</div>
</body>
</html>
