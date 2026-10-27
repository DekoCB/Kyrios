<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $cotizacion->codigo }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php
        $c = $cotizacion;
        $cliente = $c->cliente;
        $esRuc = $cliente->tipo_documento === 'RUC' && strlen($cliente->numero_documento) === 11;
        $sinStock = $c->detalles->filter(fn ($d) => ($stock[$d->id] ?? 0) < $d->cantidad);
    @endphp

    <div class="md:ml-64 p-4 md:p-8" x-data="{ modal: null, estadoNuevo: '', tipo: '{{ $esRuc ? 'factura' : 'boleta' }}', condicion: 'contado' }">
        <x-header title="Cotización {{ $c->codigo }}" subtitle="Elaborada el {{ $c->fecha->format('d/m/Y') }} por {{ $c->vendedor?->name }}" />

        @include('cotizaciones.partials.mensajes')

        {{-- Barra de acciones --}}
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('cotizaciones.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="{{ route('cotizaciones.pdf', $c) }}" target="_blank" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                <i class="fas fa-file-pdf text-red-500"></i> PDF
            </a>
            @if($c->puede_editarse)
                <a href="{{ route('cotizaciones.edit', $c) }}" class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 text-yellow-800 hover:bg-yellow-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-edit"></i> Editar
                </a>
            @endif
            <form method="POST" action="{{ route('cotizaciones.duplicar', $c) }}" class="inline">@csrf
                <button class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-copy"></i> Duplicar
                </button>
            </form>
            @if(in_array($c->estado, ['pendiente', 'aceptada']))
                <button type="button" @click="modal = 'renovar'" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-calendar-plus"></i> Renovar validez
                </button>
            @endif
            @if($c->estado === 'pendiente')
                <form method="POST" action="{{ route('cotizaciones.estado', $c) }}" class="inline">@csrf <input type="hidden" name="estado" value="aceptada">
                    <button class="inline-flex items-center gap-2 border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                        <i class="fas fa-thumbs-up"></i> Marcar aceptada
                    </button>
                </form>
            @endif
            @if(in_array($c->estado, ['pendiente', 'aceptada']))
                <button type="button" @click="estadoNuevo = 'rechazada'; modal = 'estado'" class="inline-flex items-center gap-2 border border-red-200 bg-white text-red-700 hover:bg-red-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-thumbs-down"></i> Rechazada
                </button>
                <button type="button" @click="estadoNuevo = 'anulada'; modal = 'estado'" class="inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm">
                    <i class="fas fa-ban"></i> Anular
                </button>
            @endif
            @if($c->estado === 'rechazada')
                <form method="POST" action="{{ route('cotizaciones.estado', $c) }}" class="inline">@csrf <input type="hidden" name="estado" value="pendiente">
                    <button class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-undo"></i> Reabrir</button>
                </form>
            @endif
            @if(in_array($c->estado, ['pendiente', 'aceptada']))
                <button type="button" @if($c->puede_convertirse) @click="modal = 'convertir'" @else disabled title="Renueve la validez para poder convertirla" @endif
                        class="ml-auto inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2 rounded-xl text-sm font-semibold shadow-sm">
                    <i class="fas fa-file-invoice"></i> Convertir en venta
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-6">
                {{-- Detalle --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Productos cotizados</h3>
                        @include('cotizaciones.partials.badge', ['estado' => $c->estado_visible])
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2 text-left">Producto</th>
                                    <th class="px-4 py-2 text-center">Cant.</th>
                                    <th class="px-4 py-2 text-center">Stock</th>
                                    <th class="px-4 py-2 text-right">P. unit.</th>
                                    <th class="px-4 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($c->detalles as $d)
                                    @php $hay = $stock[$d->id] ?? 0; @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $d->descripcion }}</p>
                                            <p class="text-xs font-mono text-gray-400">{{ $d->codigo }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-center">{{ $d->cantidad }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="{{ $hay >= $d->cantidad ? 'text-emerald-600' : 'text-red-600 font-semibold' }}">{{ $hay }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">S/ {{ number_format($d->precio_con_igv, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">S/ {{ number_format($d->subtotal_con_igv, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="text-sm">
                                <tr><td colspan="4" class="px-4 pt-3 text-right text-gray-500">Op. gravada</td><td class="px-4 pt-3 text-right">S/ {{ number_format($c->subtotal, 2) }}</td></tr>
                                <tr><td colspan="4" class="px-4 text-right text-gray-500">IGV (18%)</td><td class="px-4 text-right">S/ {{ number_format($c->igv, 2) }}</td></tr>
                                <tr><td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td><td class="px-4 py-3 text-right text-lg font-bold text-gray-900">S/ {{ number_format($c->total, 2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                    @if(in_array($c->estado, ['pendiente', 'aceptada']) && $sinStock->isNotEmpty())
                        <div class="px-5 py-3 bg-amber-50 border-t border-amber-100 text-sm text-amber-800">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            {{ $sinStock->count() }} producto(s) no tienen stock suficiente en {{ $c->almacen->nombre }}. Para convertirla en venta debe ingresar mercadería.
                        </div>
                    @endif
                </div>

                @if(filled($c->condiciones))
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h3 class="font-semibold text-gray-900 mb-2">Condiciones comerciales</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $c->condiciones }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <h3 class="font-semibold text-gray-900 mb-3">Cliente</h3>
                    <p class="font-medium text-gray-900">{{ $cliente->nombre }}</p>
                    <p class="text-sm text-gray-500">{{ $cliente->tipo_documento }} {{ $cliente->numero_documento }}</p>
                    @if($cliente->telefono)<p class="text-sm text-gray-500 mt-1"><i class="fas fa-phone mr-1"></i>{{ $cliente->telefono }}</p>@endif
                    @if($cliente->email)<p class="text-sm text-gray-500"><i class="fas fa-envelope mr-1"></i>{{ $cliente->email }}</p>@endif
                    @if(Route::has('clientes.show'))
                        <a href="{{ route('clientes.show', $cliente) }}" class="inline-block mt-2 text-sm text-blue-600 hover:underline">Ver ficha del cliente</a>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 text-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">Datos</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-gray-500">Número</dt><dd class="font-mono font-medium">{{ $c->codigo }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Válida hasta</dt><dd class="{{ $c->esta_vencida ? 'text-red-600 font-semibold' : '' }}">{{ $c->fecha_vencimiento->format('d/m/Y') }} ({{ $c->validez_dias }} días)</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Almacén</dt><dd>{{ $c->almacen->nombre }}</dd></div>
                    </dl>
                    @if($c->motivo_estado)
                        <p class="mt-3 text-xs text-gray-500 bg-gray-50 rounded-lg p-3"><strong>Motivo:</strong> {{ $c->motivo_estado }}</p>
                    @endif
                    @if(filled($c->observaciones))
                        <p class="mt-3 text-xs text-gray-500 bg-gray-50 rounded-lg p-3"><strong>Notas internas:</strong> {{ $c->observaciones }}</p>
                    @endif
                </div>

                @if($c->estado === 'convertida' && $c->venta)
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm">
                        <p class="font-semibold text-blue-900 mb-1"><i class="fas fa-check-circle mr-1"></i>Convertida en venta</p>
                        <p class="text-blue-800">{{ ucfirst(str_replace('_', ' ', $c->tipo_comprobante_convertido)) }}
                            <span class="font-mono">{{ $c->venta->numero_documento ?? $c->venta->codigo }}</span></p>
                        <p class="text-xs text-blue-700 mt-1">{{ $c->convertida_en?->format('d/m/Y H:i') }}</p>
                        <a href="{{ route('ventas.show', $c->venta) }}" class="inline-block mt-3 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Ver la venta</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- ─── Modales ─── --}}
        {{-- Rechazar / anular --}}
        <div x-show="modal === 'estado'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="modal = null"></div>
            <form method="POST" action="{{ route('cotizaciones.estado', $c) }}" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                @csrf
                <input type="hidden" name="estado" :value="estadoNuevo">
                <h3 class="text-lg font-bold text-gray-900 mb-1" x-text="estadoNuevo === 'anulada' ? 'Anular cotización' : 'Marcar como rechazada'"></h3>
                <p class="text-sm text-gray-500 mb-4">Indique el motivo para que quede registrado.</p>
                <textarea name="motivo" rows="3" required maxlength="500" placeholder="Motivo…" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                <div class="flex gap-3 mt-5">
                    <button type="button" @click="modal = null" class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-100 py-2.5 rounded-xl font-semibold">Cancelar</button>
                    <button class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-semibold">Confirmar</button>
                </div>
            </form>
        </div>

        {{-- Renovar --}}
        <div x-show="modal === 'renovar'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="modal = null"></div>
            <form method="POST" action="{{ route('cotizaciones.renovar', $c) }}" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                @csrf
                <h3 class="text-lg font-bold text-gray-900 mb-1">Renovar validez</h3>
                <p class="text-sm text-gray-500 mb-4">Cuenta desde hoy.</p>
                <label for="renovar_dias" class="block text-sm font-medium text-gray-700 mb-1">Días de validez</label>
                <input type="number" id="renovar_dias" name="validez_dias" value="{{ $c->validez_dias }}" min="1" max="365" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <div class="flex gap-3 mt-5">
                    <button type="button" @click="modal = null" class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-100 py-2.5 rounded-xl font-semibold">Cancelar</button>
                    <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-semibold">Renovar</button>
                </div>
            </form>
        </div>

        {{-- Convertir en venta --}}
        <div x-show="modal === 'convertir'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="modal = null"></div>
            <form method="POST" action="{{ route('cotizaciones.convertir', $c) }}" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                @csrf
                <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-5 rounded-t-2xl">
                    <h3 class="text-lg font-bold text-white">Convertir en venta</h3>
                    <p class="text-blue-100 text-sm mt-0.5">{{ $c->codigo }} · S/ {{ number_format($c->total, 2) }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Comprobante</p>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="border rounded-xl p-3 cursor-pointer text-center text-sm" :class="tipo === 'nota_venta' ? 'border-blue-600 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="tipo_comprobante" value="nota_venta" x-model="tipo" class="sr-only"><i class="fas fa-file-alt block mb-1"></i>Nota de venta
                            </label>
                            <label class="border rounded-xl p-3 cursor-pointer text-center text-sm" :class="tipo === 'boleta' ? 'border-blue-600 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="tipo_comprobante" value="boleta" x-model="tipo" class="sr-only"><i class="fas fa-receipt block mb-1"></i>Boleta
                            </label>
                            <label class="border rounded-xl p-3 text-center text-sm {{ $esRuc ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}" :class="tipo === 'factura' ? 'border-blue-600 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="tipo_comprobante" value="factura" x-model="tipo" class="sr-only" @disabled(!$esRuc)><i class="fas fa-file-invoice block mb-1"></i>Factura
                            </label>
                        </div>
                        @unless($esRuc)
                            <p class="text-xs text-gray-500 mt-2">La factura requiere un cliente con RUC; este cliente {{ $cliente->tipo_documento ? 'tiene ' . $cliente->tipo_documento : 'no tiene documento' }}.</p>
                        @endunless
                        <p x-show="tipo === 'nota_venta'" x-cloak class="text-xs text-gray-500 mt-2">Documento interno: no se envía a SUNAT.</p>
                    </div>

                    @if($esVendedor)
                        <p class="text-sm bg-amber-50 border border-amber-100 text-amber-800 rounded-lg px-4 py-3">
                            <i class="fas fa-info-circle mr-1"></i>La venta quedará <strong>pendiente de cobro</strong>: el cajero la cobra.
                        </p>
                    @else
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Cobro</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="condicion" value="contado" x-model="condicion"> Cobrar ahora</label>
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="condicion" value="pendiente_cobro" x-model="condicion"> Dejar pendiente de cobro (lo cobra caja)</label>
                            </div>
                            <div x-show="condicion === 'contado'" class="mt-3">
                                <label for="metodo_pago" class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                                <select id="metodo_pago" name="metodo_pago" :required="condicion === 'contado'" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">Seleccione…</option>
                                    @foreach(['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'yape' => 'Yape', 'plin' => 'Plin', 'mixto' => 'Mixto'] as $v => $t)
                                        <option value="{{ $v }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Requiere tener la caja abierta.</p>
                            </div>
                        </div>
                    @endif

                    <p class="text-xs text-gray-500">Se descontará el stock de <strong>{{ $c->almacen->nombre }}</strong> con los precios de esta cotización.</p>

                    <div class="flex gap-3">
                        <button type="button" @click="modal = null" class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-100 py-2.5 rounded-xl font-semibold">Cancelar</button>
                        <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-bold"><i class="fas fa-check mr-1"></i>Convertir</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
