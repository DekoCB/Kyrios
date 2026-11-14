<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aprobaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    <div class="bg-white shadow-sm px-6 py-4">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-clipboard-check text-blue-700"></i> Aprobaciones de {{ $tipo === 'clientes' ? 'clientes' : 'productos' }}
            @if($pendientes > 0)
                <span class="text-sm font-semibold bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full">{{ $pendientes }} pendiente{{ $pendientes > 1 ? 's' : '' }}</span>
            @endif
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">
            @if($tipo === 'clientes')
                Eliminaciones de clientes solicitadas por otros usuarios. Nada se aplica hasta que lo apruebes.
            @else
                Productos nuevos, ediciones, eliminaciones, ingresos, compras y traslados registrados por otros usuarios. Nada se aplica hasta que lo apruebes.
            @endif
        </p>
        <div class="flex gap-2 mt-3" aria-label="Tipo de aprobación">
            <a href="{{ route('aprobaciones.index') }}" class="px-4 py-1.5 text-sm rounded-lg border {{ $tipo === 'productos' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                Productos @if($pendientesProductos > 0)<span class="ml-1 text-xs font-bold bg-red-600 text-white px-1.5 rounded-full">{{ $pendientesProductos }}</span>@endif
            </a>
            <a href="{{ route('aprobaciones.clientes') }}" class="px-4 py-1.5 text-sm rounded-lg border {{ $tipo === 'clientes' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                Clientes @if($pendientesClientes > 0)<span class="ml-1 text-xs font-bold bg-red-600 text-white px-1.5 rounded-full">{{ $pendientesClientes }}</span>@endif
            </a>
        </div>
    </div>

    <div class="p-6 max-w-5xl">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif
        @error('motivo')
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $message }}</div>
        @enderror

        <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Filtrar por estado">
            @foreach(['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'todas' => 'Todas'] as $clave => $texto)
                <a href="{{ route($tipo === 'clientes' ? 'aprobaciones.clientes' : 'aprobaciones.index', ['estado' => $clave]) }}"
                   class="px-4 py-1.5 text-sm rounded-full border {{ $estado === $clave ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $texto }}</a>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse($aprobaciones as $a)
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $a->titulo }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Registrado por <strong>{{ $a->solicitante?->name ?? 'usuario eliminado' }}</strong>
                                ({{ $a->solicitante?->role?->nombre }}) · {{ $a->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        @include('aprobaciones._estado', ['estado' => $a->estado])
                    </div>

                    @if(!empty($a->detalle))
                        <ul class="mt-3 text-sm text-gray-700 space-y-0.5 list-disc pl-5">
                            @foreach(array_slice($a->detalle, 0, 12) as $linea)
                                <li>{{ $linea }}</li>
                            @endforeach
                            @if(count($a->detalle) > 12)
                                <li class="text-gray-400">… y {{ count($a->detalle) - 12 }} más</li>
                            @endif
                        </ul>
                    @endif

                    @if($a->ultimo_error && $a->estado === 'pendiente')
                        <p class="mt-3 text-sm rounded-lg bg-red-50 border border-red-200 text-red-800 px-3 py-2">
                            <i class="fas fa-triangle-exclamation mr-1"></i> El último intento de aprobar falló: {{ $a->ultimo_error }}
                        </p>
                    @endif

                    @if($a->estado === 'pendiente')
                        <div class="mt-4 flex flex-wrap items-start gap-3">
                            <form method="POST" action="{{ route('aprobaciones.aprobar', $a) }}" data-confirm="¿Aprobar y aplicar esta solicitud?">
                                @csrf
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                                    <i class="fas fa-check mr-1"></i> Aprobar
                                </button>
                            </form>
                            <form method="POST" action="{{ route('aprobaciones.rechazar', $a) }}" class="flex flex-wrap items-start gap-2 flex-1 min-w-[16rem]">
                                @csrf
                                <input type="text" name="motivo" required minlength="3" maxlength="500" placeholder="Motivo del rechazo"
                                       class="flex-1 min-w-[10rem] rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm text-sm focus:border-red-400 focus:ring focus:ring-red-100">
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-red-700 border border-red-300 rounded-lg hover:bg-red-50">
                                    <i class="fas fa-xmark mr-1"></i> Rechazar
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="mt-3 text-xs text-gray-500">
                            {{ $a->estado === 'aprobada' ? 'Aprobada' : 'Rechazada' }} por {{ $a->resolutor?->name ?? '—' }} el {{ $a->resuelta_at?->format('d/m/Y H:i') }}
                            @if($a->motivo_rechazo) · Motivo: {{ $a->motivo_rechazo }} @endif
                        </p>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm px-5 py-10 text-center text-sm text-gray-500">
                    {{ $estado === 'pendiente' ? 'No hay solicitudes pendientes.' : 'No hay solicitudes en este estado.' }}
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $aprobaciones->links() }}</div>
    </div>
</div>
</body>
</html>
