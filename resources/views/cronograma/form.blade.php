<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $evento ? 'Editar' : 'Programar' }} instalación o entrega - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php
        $defectos = $defectos ?? [];
        $v = fn ($campo, $defecto = null) => old($campo, $evento?->{$campo} ?? ($defectos[$campo] ?? $defecto));
        $clase = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white';
    @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$evento ? 'Editar programación' : 'Programar instalación o entrega'" subtitle="Quien quede como responsable recibe un aviso" />

        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $evento ? route('cronograma.update', $evento) : route('cronograma.store') }}" class="max-w-3xl bg-white rounded-lg shadow-md p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            @csrf
            @if($evento) @method('PUT') @endif

            <div>
                <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo <span class="text-red-500">*</span></label>
                <select id="tipo" name="tipo" required class="{{ $clase }}">
                    @foreach(\App\Models\CronogramaEvento::TIPOS as $k => $t)
                        <option value="{{ $k }}" {{ $v('tipo', 'instalacion') === $k ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
                <input id="titulo" type="text" name="titulo" value="{{ $v('titulo') }}" required maxlength="150" placeholder="Ej. Instalación luminarias Torre Lima" class="{{ $clase }}">
            </div>
            <div>
                <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">Fecha <span class="text-red-500">*</span></label>
                <input id="fecha" type="date" name="fecha" value="{{ $v('fecha') ? \Carbon\Carbon::parse($v('fecha'))->format('Y-m-d') : '' }}" required class="{{ $clase }}">
            </div>
            <div>
                <label for="hora" class="block text-sm font-medium text-gray-700 mb-2">Hora</label>
                <input id="hora" type="time" name="hora" value="{{ old('hora', $evento?->hora_corta) }}" class="{{ $clase }}">
            </div>
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                <select id="cliente_id" name="cliente_id" class="{{ $clase }}">
                    <option value="">Sin cliente</option>
                    @foreach($clientes as $c)<option value="{{ $c->id }}" {{ (string) $v('cliente_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="responsable_id" class="block text-sm font-medium text-gray-700 mb-2">Responsable</label>
                <select id="responsable_id" name="responsable_id" class="{{ $clase }}">
                    <option value="">Sin asignar</option>
                    @foreach($responsables as $u)<option value="{{ $u->id }}" {{ (string) $v('responsable_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="direccion" class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                <input id="direccion" type="text" name="direccion" value="{{ $v('direccion') }}" maxlength="255" class="{{ $clase }}">
            </div>
            <div class="md:col-span-2">
                <label for="venta_id" class="block text-sm font-medium text-gray-700 mb-2">Venta relacionada</label>
                <select id="venta_id" name="venta_id" class="{{ $clase }}">
                    <option value="">Ninguna</option>
                    @foreach($ventas as $vt)<option value="{{ $vt->id }}" {{ (string) $v('venta_id') === (string) $vt->id ? 'selected' : '' }}>{{ $vt->codigo }}@if($vt->cliente) · {{ $vt->cliente->nombre }}@endif</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="pedido_id" class="block text-sm font-medium text-gray-700 mb-2">Orden de compra relacionada</label>
                <select id="pedido_id" name="pedido_id" class="{{ $clase }}">
                    <option value="">Ninguna</option>
                    @foreach($ordenes as $o)<option value="{{ $o->id }}" {{ (string) $v('pedido_id') === (string) $o->id ? 'selected' : '' }}>{{ $o->codigo }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                <textarea id="notas" name="notas" rows="3" maxlength="2000" class="{{ $clase }}">{{ $v('notas') }}</textarea>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3">
                <a href="{{ route('cronograma.index') }}" class="px-5 py-2.5 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Guardar</button>
            </div>
        </form>
    </div>
</body>
</html>
