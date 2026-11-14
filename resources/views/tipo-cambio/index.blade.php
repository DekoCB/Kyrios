<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tipo de cambio</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    <div class="bg-white shadow-sm px-6 py-4">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-dollar-sign text-blue-700"></i> Tipo de cambio</h1>
        <p class="text-sm text-gray-500 mt-0.5">Los precios del catálogo se muestran en dólares y se convierten a soles con el tipo de cambio de venta.</p>
    </div>

    <div class="p-6 max-w-4xl space-y-6">
        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-medium">Vigente (venta)</p>
                <p class="text-3xl font-bold text-blue-700 mt-1">S/ {{ number_format($actual, 3) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    @if($vigente) del {{ $vigente->fecha->format('d/m/Y') }} · {{ $vigente->fuente === 'sunat' ? 'SUNAT' : 'manual' }}
                    @else aún no registrado: se usa el valor por defecto @endif
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-medium">Compra</p>
                <p class="text-3xl font-bold text-gray-700 mt-1">S/ {{ number_format((float) ($vigente?->compra ?? \App\Models\TipoCambio::POR_DEFECTO_COMPRA), 3) }}</p>
            </div>
            <form method="POST" action="{{ route('tipo-cambio.sunat') }}" class="bg-white rounded-xl shadow-sm p-5 flex flex-col justify-between">
                @csrf
                <p class="text-xs uppercase tracking-wide text-gray-400 font-medium">Traer de SUNAT</p>
                <button type="submit" class="mt-2 px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800"><i class="fas fa-cloud-arrow-down mr-1"></i> Consultar hoy</button>
            </form>
        </div>

        <form method="POST" action="{{ route('tipo-cambio.store') }}" class="bg-white rounded-xl shadow-sm p-5">
            @csrf
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Registrar o corregir un tipo de cambio</h2>
            <div class="grid sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="fecha">Fecha</label>
                    <input id="fecha" type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="compra">Compra</label>
                    <input id="compra" type="number" step="0.001" min="0.5" name="compra" value="{{ old('compra', $vigente?->compra) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="venta">Venta</label>
                    <input id="venta" type="number" step="0.001" min="0.5" name="venta" value="{{ old('venta', $vigente?->venta) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"><i class="fas fa-save mr-1"></i> Guardar</button>
            </div>
            @foreach(['fecha', 'compra', 'venta'] as $campo)
                @error($campo)<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            @endforeach
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <h2 class="text-sm font-semibold text-gray-800 px-5 py-3 border-b border-gray-100">Historial</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                    <th class="px-5 py-2 text-left">Fecha</th><th class="px-5 py-2 text-right">Compra</th><th class="px-5 py-2 text-right">Venta</th><th class="px-5 py-2 text-left">Origen</th><th class="px-5 py-2 text-left">Registró</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($historial as $t)
                        <tr>
                            <td class="px-5 py-2">{{ $t->fecha->format('d/m/Y') }}</td>
                            <td class="px-5 py-2 text-right">{{ number_format((float) $t->compra, 3) }}</td>
                            <td class="px-5 py-2 text-right font-semibold">{{ number_format((float) $t->venta, 3) }}</td>
                            <td class="px-5 py-2">{{ $t->fuente === 'sunat' ? 'SUNAT' : 'Manual' }}</td>
                            <td class="px-5 py-2 text-gray-500">{{ $t->usuario?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Todavía no hay registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
