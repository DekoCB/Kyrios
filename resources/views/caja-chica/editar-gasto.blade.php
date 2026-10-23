<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar gasto - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Editar gasto" :subtitle="$caja->codigo . ' · ' . $caja->nombre" />

        @include('cotizaciones.partials.mensajes')

        @if($mov->motivo_revision)
            <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-900 p-4 mb-6 rounded text-sm"><strong>Observación:</strong> {{ $mov->motivo_revision }}</div>
        @endif

        <div class="bg-white rounded-xl shadow-sm p-6 max-w-3xl space-y-5">
            @if($mov->adjuntos->isNotEmpty())
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Archivos ya subidos</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach($mov->adjuntos as $a)
                            <div class="border rounded-lg p-2 text-xs text-center w-28">
                                <a href="{{ route('caja-chica.adjunto', $a) }}" target="_blank" class="block">
                                    @if($a->es_imagen)<img src="{{ route('caja-chica.adjunto', $a) }}" alt="" class="h-20 w-full object-cover rounded mb-1">
                                    @else<i class="fas fa-file-pdf text-3xl text-red-500 my-3"></i>@endif
                                    <span class="block truncate text-blue-700">{{ $a->nombre_original }}</span>
                                </a>
                                <form method="POST" action="{{ route('caja-chica.adjunto.destroy', $a) }}" onsubmit="return confirm('¿Quitar este archivo?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline mt-1">Quitar</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @include('caja-chica.partials.form-gasto', ['action' => route('caja-chica.gasto.update', $mov), 'metodo' => 'PUT', 'boton' => 'Guardar y reenviar a revisión'])
            <a href="{{ route('caja-chica.show', $caja) }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Volver a la caja</a>
        </div>
    </div>
</body>
</html>
