<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva caja chica - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Nueva caja chica" subtitle="Asigne dinero a una persona o abra la caja general" />

        @include('cotizaciones.partials.mensajes')

        <form method="POST" action="{{ route('caja-chica.store') }}" class="bg-white rounded-xl shadow-sm p-6 max-w-2xl grid grid-cols-1 md:grid-cols-2 gap-4"
              x-data="{ tipo: '{{ old('tipo', 'asignada') }}' }">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                <select name="tipo" x-model="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="asignada">Asignada a una persona</option>
                    <option value="general">Caja general de la empresa</option>
                </select>
            </div>
            <div x-show="tipo === 'asignada'">
                <label class="block text-xs font-medium text-gray-600 mb-1">Responsable</label>
                <select name="responsable_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">— Elegir —</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" @selected(old('responsable_id') == $u->id)>{{ $u->name }} ({{ $u->role?->nombre }})</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                <input type="text" name="nombre" required maxlength="120" value="{{ old('nombre') }}" placeholder="Ej.: Caja chica técnico – Octubre"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Monto entregado (S/)</label>
                <input type="number" name="monto_inicial" step="0.01" min="0" value="{{ old('monto_inicial') }}" placeholder="100.00"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                <textarea name="observaciones" rows="2" maxlength="1000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('observaciones') }}</textarea>
            </div>
            <div class="md:col-span-2 flex justify-between">
                <a href="{{ route('caja-chica.index') }}" class="text-sm text-gray-500 hover:text-gray-800 py-2">Cancelar</a>
                <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Crear caja</button>
            </div>
        </form>
    </div>
</body>
</html>
