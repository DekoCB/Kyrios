<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura de Caja</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Apertura de Caja" subtitle="Inicia tu turno registrando el monto inicial en efectivo" />

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="max-w-xl mx-auto">

        {{-- ⚠ BLOQUEO: CAJA DE DÍA ANTERIOR SIN CERRAR --}}
        @if(isset($cajaAtrasada) && $cajaAtrasada)
        @php
            $fechaCajaAtrasada = \Carbon\Carbon::parse($cajaAtrasada->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
            $diasAtraso = \Carbon\Carbon::parse($cajaAtrasada->fecha)->diffInDays(now());
        @endphp
        <div class="bg-red-50 border-2 border-red-400 rounded-xl p-6 mb-6 shadow-md">
            <div class="flex items-start gap-4 mb-4">
                <div class="shrink-0 bg-red-100 rounded-full p-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-red-800 font-bold text-xl mb-1">
                        No puedes abrir una nueva caja
                    </h3>
                    <p class="text-red-700 text-sm">
                        Tienes una caja del <strong>{{ $fechaCajaAtrasada }}</strong> que no fue cerrada
                        (hace <strong>{{ $diasAtraso }} {{ $diasAtraso == 1 ? 'día' : 'días' }}</strong>).
                        Debes cerrarla primero para poder abrir la caja de hoy.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-red-200 p-4 mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Fecha de apertura</p>
                        <p class="font-semibold text-gray-800">{{ $fechaCajaAtrasada }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Almacén</p>
                        <p class="font-semibold text-gray-800">{{ $cajaAtrasada->almacen->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Monto inicial</p>
                        <p class="font-semibold text-gray-800">S/ {{ number_format($cajaAtrasada->monto_inicial, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Saldo actual</p>
                        <p class="font-semibold text-gray-800">S/ {{ number_format($cajaAtrasada->monto_final, 2) }}</p>
                    </div>
                </div>
            </div>

            <a href="{{ route('caja.actual') }}"
               class="w-full flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition-colors text-base">
                <i class="fas fa-lock"></i>
                Ir a Cerrar la Caja del {{ $fechaCajaAtrasada }}
            </a>
        </div>
        @endif

        {{-- Info del usuario/turno --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
                <i class="fas fa-id-badge mr-1"></i> Información de tu turno
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-400">Cajero</p>
                    <p class="font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Fecha de apertura</p>
                    <p class="font-semibold text-gray-800">{{ now()->locale('es')->isoFormat('D [de] MMMM, YYYY') }}</p>
                </div>
                @if($almacen)
                    <div>
                        <p class="text-xs text-gray-400">Almacén asignado</p>
                        <p class="font-semibold text-gray-800">
                            <i class="fas fa-warehouse text-blue-500 mr-1 text-xs"></i>{{ $almacen->nombre }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Sucursal</p>
                        <p class="font-semibold text-gray-800">
                            @if($almacen->sucursal ?? null)
                                <i class="fas fa-store text-purple-500 mr-1 text-xs"></i>{{ $almacen->sucursal->nombre }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </p>
                    </div>
                @elseif($almacenes->isNotEmpty())
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-400 mb-1">
                            <i class="fas fa-warehouse mr-1"></i> Seleccionar almacén para este turno
                            <span class="text-red-500">*</span>
                        </label>
                        <select name="almacen_id" form="formAbrirCaja" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
                                       focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">— Elige un almacén —</option>
                            @foreach($almacenes as $a)
                                <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-blue-600 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Como administrador puedes abrir caja en cualquier almacén.
                        </p>
                    </div>
                @else
                    <div class="col-span-2">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            No hay almacenes activos. Crea uno antes de abrir caja.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Formulario de apertura --}}
        @if($almacen || $almacenes->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">

            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-5">
                <i class="fas fa-cash-register mr-1"></i> Monto de apertura
            </h3>

            <form action="{{ route('caja.store') }}" method="POST" id="formAbrirCaja"
                  onsubmit="return confirmarApertura(this)">
                @csrf

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Efectivo en caja al iniciar <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold text-lg">S/</span>
                        <input type="number" name="monto_inicial" id="montoInicial" step="0.01" min="0"
                               @class([
                                   'w-full pl-10 pr-4 py-3 border rounded-lg text-xl font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                   'border-red-400' => $errors->has('monto_inicial'),
                                   'border-gray-300' => !$errors->has('monto_inicial'),
                               ])
                               value="{{ old('monto_inicial', '0.00') }}"
                               placeholder="0.00" required autofocus>
                    </div>
                    @error('monto_inicial')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400 mt-1">Cuenta el efectivo físico presente en caja antes de atender.</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                              placeholder="Notas al abrir la caja...">{{ old('observaciones') }}</textarea>
                </div>

                <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg
                               transition-colors flex items-center justify-center gap-2 text-base">
                    <i class="fas fa-lock-open"></i> Abrir Caja
                </button>

            </form>
        </div>
        @endif

    </div>
</div>
<script>
function confirmarApertura(form) {
    if (form.dataset.confirmado) {
        return true;
    }
    var monto = parseFloat(document.getElementById('montoInicial').value) || 0;
    // El selector de almacén (administrador) está fuera del <form> y se asocia con form="…": se lee por form.elements.
    var selectEl = form.elements['almacen_id'];
    var almacenNombre = @json($almacen->nombre ?? '');
    if (selectEl && selectEl.tagName === 'SELECT') {
        almacenNombre = selectEl.options[selectEl.selectedIndex] ? selectEl.options[selectEl.selectedIndex].text : '';
    }
    window.kyrConfirm(
        'Se abrirá la caja con S/ ' + monto.toFixed(2) + (almacenNombre ? '\n\nAlmacén: ' + almacenNombre : ''),
        { titulo: 'Confirmar apertura de caja', aceptar: 'Abrir caja', peligro: false }
    ).then(function (ok) {
        if (ok) {
            form.dataset.confirmado = '1';
            form.requestSubmit();
        }
    });
    return false;
}
</script>
</body>
</html>
