<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar salida de herramienta - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php $clase = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white'; @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Registrar salida de herramienta" subtitle="Qué herramienta se lleva el técnico y cuándo debe devolverla" />

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('herramientas.salidas.store') }}" class="max-w-3xl bg-white rounded-lg shadow-md p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            @csrf
            <div class="md:col-span-2">
                <label for="herramienta_id" class="block text-sm font-medium text-gray-700 mb-2">Herramienta <span class="text-red-500">*</span></label>
                <select id="herramienta_id" name="herramienta_id" required class="{{ $clase }}">
                    <option value="">Selecciona…</option>
                    @foreach($herramientas as $h)
                        <option value="{{ $h->id }}" {{ (string) old('herramienta_id', $herramientaId) === (string) $h->id ? 'selected' : '' }}>{{ $h->codigo }} · {{ $h->nombre }} ({{ $h->disponible }} disponible{{ $h->disponible === 1 ? '' : 's' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tecnico_id" class="block text-sm font-medium text-gray-700 mb-2">Técnico que se la lleva <span class="text-red-500">*</span></label>
                <select id="tecnico_id" name="tecnico_id" required class="{{ $clase }}">
                    <option value="">Selecciona…</option>
                    @foreach($tecnicos as $t)<option value="{{ $t->id }}" {{ (string) old('tecnico_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="cantidad" class="block text-sm font-medium text-gray-700 mb-2">Cantidad <span class="text-red-500">*</span></label>
                <input id="cantidad" type="number" name="cantidad" value="{{ old('cantidad', 1) }}" required min="1" class="{{ $clase }}">
            </div>
            <div>
                <label for="cronograma_evento_id" class="block text-sm font-medium text-gray-700 mb-2">Instalación o entrega</label>
                <select id="cronograma_evento_id" name="cronograma_evento_id" class="{{ $clase }}">
                    <option value="">Ninguna</option>
                    @foreach($eventos as $e)<option value="{{ $e->id }}" {{ (string) old('cronograma_evento_id') === (string) $e->id ? 'selected' : '' }}>{{ $e->fecha->format('d/m/Y') }} · {{ $e->titulo }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="fecha_devolucion_esperada" class="block text-sm font-medium text-gray-700 mb-2">Debe devolverla el</label>
                <input id="fecha_devolucion_esperada" type="date" name="fecha_devolucion_esperada" value="{{ old('fecha_devolucion_esperada') }}" min="{{ today()->format('Y-m-d') }}" class="{{ $clase }}">
            </div>
            <div class="md:col-span-2">
                <label for="destino" class="block text-sm font-medium text-gray-700 mb-2">Destino / obra</label>
                <input id="destino" type="text" name="destino" value="{{ old('destino') }}" maxlength="200" class="{{ $clase }}">
            </div>
            <div class="md:col-span-2">
                <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                <textarea id="notas" name="notas" rows="2" maxlength="1000" class="{{ $clase }}">{{ old('notas') }}</textarea>
            </div>
            <div class="md:col-span-2 flex justify-end gap-3">
                <a href="{{ route('herramientas.index') }}" class="px-5 py-2.5 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Registrar salida</button>
            </div>
        </form>
    </div>
</body>
</html>
