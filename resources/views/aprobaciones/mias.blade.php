<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis solicitudes</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    <div class="bg-white shadow-sm px-6 py-4">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-clipboard-check text-blue-700"></i> Mis solicitudes</h1>
        <p class="text-sm text-gray-500 mt-0.5">Lo que registraste y espera la aprobación del administrador. Se aplica recién cuando lo aprueba.</p>
    </div>

    <div class="p-6 max-w-4xl">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="space-y-3">
            @forelse($aprobaciones as $a)
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $a->titulo }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Enviada el {{ $a->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @include('aprobaciones._estado', ['estado' => $a->estado])
                    </div>
                    @if(!empty($a->detalle))
                        <ul class="mt-3 text-sm text-gray-700 space-y-0.5 list-disc pl-5">
                            @foreach(array_slice($a->detalle, 0, 8) as $linea)
                                <li>{{ $linea }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if($a->estado !== 'pendiente')
                        <p class="mt-3 text-xs text-gray-500">
                            {{ $a->estado === 'aprobada' ? 'Aprobada' : 'Rechazada' }} por {{ $a->resolutor?->name ?? '—' }} el {{ $a->resuelta_at?->format('d/m/Y H:i') }}
                            @if($a->motivo_rechazo) · <strong>Motivo:</strong> {{ $a->motivo_rechazo }} @endif
                        </p>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm px-5 py-10 text-center text-sm text-gray-500">No has enviado solicitudes.</div>
            @endforelse
        </div>

        <div class="mt-4">{{ $aprobaciones->links() }}</div>
    </div>
</div>
</body>
</html>
