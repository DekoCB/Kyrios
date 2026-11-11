<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coincidencias de homologación - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php $m = fn ($n) => $n === null ? null : number_format((float) $n, 2, '.', ''); @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Coincidencias encontradas" :subtitle="'Para ' . $cliente->nombre . ' · almacén ' . $almacen->nombre" />

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('cotizaciones.homologacion.generar') }}" class="max-w-5xl space-y-4">
            @csrf
            <input type="hidden" name="cliente_id" value="{{ $cliente->id }}">
            <input type="hidden" name="almacen_id" value="{{ $almacen->id }}">

            <p class="text-sm text-gray-600">Revisa la mejor coincidencia de cada fila, ajusta cantidad y precio (con IGV) y elige «No incluir» en lo que no quieras cotizar.</p>

            @foreach($resultados as $i => $r)
                @php
                    $fila = $r['fila'];
                    $mejor = $r['candidatos'][0] ?? null;
                    $porDefecto = $mejor && $mejor['puntaje'] >= 40 ? $mejor['producto_id'] . ':' . $mejor['variante_id'] : '';
                    $elegido = old("lineas.$i.elegido", $porDefecto);
                    $pedida = (int) preg_replace('/\D/', '', $fila['cantidad']) ?: 1;
                @endphp
                <div class="bg-white rounded-xl shadow-sm p-5" data-fila-homologacion>
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ $i + 1 }}. {{ $fila['descripcion'] ?: '(sin descripción)' }}</p>
                            <p class="text-xs text-gray-500">
                                {{ collect([
                                    $fila['tipo'] ?: null,
                                    $fila['potencia'] ? $fila['potencia'] . ' W' : null,
                                    $fila['temperatura'] ? $fila['temperatura'] . ' K' : null,
                                    $fila['ip'] ? 'IP ' . $fila['ip'] : null,
                                    $fila['color'] ?: null,
                                    $fila['medidas'] ? $fila['medidas'] . ' mm' : null,
                                ])->filter()->implode(' · ') }}
                            </p>
                            @if($fila['notas'])<p class="text-xs text-gray-500 italic">{{ $fila['notas'] }}</p>@endif
                        </div>
                        <p class="text-xs text-gray-500">Pidió: <span class="font-semibold">{{ $fila['cantidad'] ?: '1' }}</span></p>
                    </div>

                    <div class="space-y-2">
                        @foreach($r['candidatos'] as $c)
                            @php $valor = $c['producto_id'] . ':' . $c['variante_id']; @endphp
                            <label class="flex items-start gap-3 p-2.5 rounded-lg border {{ $elegido === $valor ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }} cursor-pointer">
                                <input type="radio" name="lineas[{{ $i }}][elegido]" value="{{ $valor }}" data-precio="{{ $m($c['precio']) }}" {{ $elegido === $valor ? 'checked' : '' }} class="mt-1" onchange="elegirCoincidencia(this)">
                                <span class="flex-1 min-w-0 text-sm">
                                    <span class="font-semibold text-gray-800">{{ $c['nombre'] }}</span>@if($c['detalle']) <span class="text-gray-600">— {{ $c['detalle'] }}</span>@endif
                                    <span class="block text-xs font-mono text-gray-500">{{ $c['codigo'] }}</span>
                                </span>
                                <span class="text-right text-xs shrink-0">
                                    <span class="block font-bold {{ $c['puntaje'] >= 70 ? 'text-green-700' : ($c['puntaje'] >= 40 ? 'text-yellow-700' : 'text-gray-500') }}">{{ $c['puntaje'] }}% coincide</span>
                                    <span class="block text-gray-600">{{ $c['precio'] !== null ? 'S/ ' . number_format($c['precio'], 2) : 'Sin precio' }}</span>
                                </span>
                            </label>
                        @endforeach
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $elegido === '' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }} cursor-pointer">
                            <input type="radio" name="lineas[{{ $i }}][elegido]" value="" {{ $elegido === '' ? 'checked' : '' }} onchange="elegirCoincidencia(this)">
                            <span class="text-sm text-gray-600">{{ $r['candidatos'] ? 'No incluir en la cotización' : 'No se encontró coincidencia · no incluir' }}</span>
                        </label>
                    </div>

                    <div class="mt-3 flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1" for="cant-{{ $i }}">Cantidad</label>
                            <input id="cant-{{ $i }}" type="number" name="lineas[{{ $i }}][cantidad]" value="{{ old("lineas.$i.cantidad", $pedida) }}" min="1" class="w-24 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1" for="precio-{{ $i }}">Precio unitario (con IGV)</label>
                            <input id="precio-{{ $i }}" type="number" step="0.01" min="0" name="lineas[{{ $i }}][precio]" value="{{ old("lineas.$i.precio", $mejor && $porDefecto !== '' ? $m($mejor['precio']) : '') }}" class="w-36 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="bg-white rounded-xl shadow-sm p-5 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1" for="validez_dias">Validez de la cotización (días)</label>
                    <input id="validez_dias" type="number" name="validez_dias" value="{{ old('validez_dias', $validez) }}" min="1" max="365" required class="w-32 py-1.5 px-2 text-sm border border-gray-300 rounded-lg bg-white">
                </div>
                <div class="ml-auto flex gap-3">
                    <a href="{{ route('cotizaciones.homologacion') }}" class="px-5 py-2.5 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50">Volver</a>
                    <button type="submit" class="px-5 py-2.5 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800"><i class="fas fa-file-invoice mr-2"></i>Generar cotización</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Al cambiar de coincidencia, el precio se actualiza con el de esa variante
        function elegirCoincidencia(radio) {
            const tarjeta = radio.closest('[data-fila-homologacion]');
            const precio = tarjeta.querySelector('input[name$="[precio]"]');
            precio.value = radio.dataset.precio ?? '';
            tarjeta.querySelectorAll('label').forEach(l => {
                const marcado = l.querySelector('input[type=radio]')?.checked;
                l.classList.toggle('border-blue-500', !!marcado);
                l.classList.toggle('bg-blue-50', !!marcado);
                l.classList.toggle('border-gray-200', !marcado);
            });
        }
    </script>
</body>
</html>
