<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla de homologación - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php $clase = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white'; @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Plantilla de homologación" subtitle="El cliente describe lo que necesita y el sistema busca los productos que mejor coinciden" />

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <ul class="list-disc list-inside text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="max-w-3xl space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-800 mb-1"><span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-900 text-white text-xs mr-2">1</span>Entrega la plantilla al cliente</h2>
                <p class="text-sm text-gray-600 mb-4">El cliente anota un producto por fila: {{ strtolower(implode(', ', $columnas)) }}.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('cotizaciones.homologacion.plantilla-pdf') }}" target="_blank" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-pdf text-red-500"></i> Descargar PDF (para imprimir)</a>
                    <a href="{{ route('cotizaciones.homologacion.plantilla-excel') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-excel text-green-600"></i> Descargar Excel (para llenar en pantalla)</a>
                </div>
                <p class="text-xs text-gray-500 mt-3">Para cargarla al sistema, la plantilla debe volver en Excel (.xlsx) o CSV. Si el cliente la llenó en papel, transcríbela en el Excel.</p>
            </div>

            <form method="POST" action="{{ route('cotizaciones.homologacion.analizar') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                @csrf
                <h2 class="md:col-span-2 text-base font-bold text-gray-800"><span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-900 text-white text-xs mr-2">2</span>Sube la plantilla llena</h2>
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-2">Cliente <span class="text-red-500">*</span></label>
                    <select id="cliente_id" name="cliente_id" required class="{{ $clase }}">
                        <option value="">Selecciona…</option>
                        @foreach($clientes as $c)<option value="{{ $c->id }}" {{ (string) old('cliente_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="almacen_id" class="block text-sm font-medium text-gray-700 mb-2">Almacén <span class="text-red-500">*</span></label>
                    <select id="almacen_id" name="almacen_id" required class="{{ $clase }}">
                        <option value="">Selecciona…</option>
                        @foreach($almacenes as $a)<option value="{{ $a->id }}" {{ (string) $almacenId === (string) $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>@endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="archivo" class="block text-sm font-medium text-gray-700 mb-2">Archivo (.xlsx, .csv) <span class="text-red-500">*</span></label>
                    <input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv,.txt" required class="{{ $clase }}">
                </div>
                <div class="md:col-span-2 flex justify-end gap-3">
                    <a href="{{ route('cotizaciones.index') }}" class="px-5 py-2.5 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">Cancelar</a>
                    <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800"><i class="fas fa-magnifying-glass mr-2"></i>Buscar coincidencias</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
