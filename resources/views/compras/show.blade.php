<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Compra #{{ $compra->numero_factura }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <!-- Breadcrumb + Header -->
        <div class="mb-6">
            <div class="flex items-center text-sm text-gray-500 mb-2">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-900">Dashboard</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="{{ route('compras.index') }}" class="hover:text-blue-900">Compras</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-gray-700 font-medium">Compra #{{ $compra->numero_factura }}</span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-file-invoice mr-3 text-blue-900"></i>
                        Detalle de Compra
                    </h1>
                    @php
                        $tipoLabel = ['local' => 'Local', 'importacion' => 'Importación'];
                        $tipoColor = ['local' => 'green', 'importacion' => 'orange'];
                        $tc = $compra->tipo_compra ?? 'local';
                        $color = $tipoColor[$tc] ?? 'gray';
                    @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        {{ $color === 'green' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                        @if($tc === 'importacion') <i class="fas fa-ship mr-1"></i>
                        @else <i class="fas fa-store mr-1"></i>
                        @endif
                        {{ $tipoLabel[$tc] ?? 'Local' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    @if($compra->estado != 'anulado')
                        <a href="{{ route('compras.edit', $compra) }}"
                           class="px-4 py-2 bg-yellow-500 text-white rounded-xl hover:bg-yellow-600 transition text-sm flex items-center gap-1.5">
                            <i class="fas fa-edit"></i>Editar
                        </a>
                        <button onclick="anularCompra({{ $compra->id }})"
                                class="px-4 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition text-sm flex items-center gap-1.5">
                            <i class="fas fa-ban"></i>Anular
                        </button>
                        <button onclick="eliminarCompra({{ $compra->id }})"
                                class="px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700 transition text-sm flex items-center gap-1.5">
                            <i class="fas fa-trash"></i>Eliminar
                        </button>
                    @endif
                    <a href="{{ route('compras.index') }}"
                       class="px-4 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition text-sm flex items-center gap-1.5">
                        <i class="fas fa-arrow-left"></i>Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        @if(session('success'))
            <div class="mb-5 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl flex items-start gap-3">
                <i class="fas fa-check-circle mt-0.5 text-lg"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl flex items-start gap-3">
                <i class="fas fa-exclamation-circle mt-0.5 text-lg"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- ═══════════════════════════════════════════════════
             FILA DE TARJETAS RESUMEN
             ═══════════════════════════════════════════════════ -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <!-- Estado -->
            <div class="bg-white rounded-xl shadow p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                    {{ $compra->estado === 'completado' ? 'bg-green-100' : ($compra->estado === 'anulado' ? 'bg-red-100' : 'bg-gray-100') }}">
                    <i class="fas fa-{{ $compra->estado === 'completado' ? 'check-circle text-green-600' : ($compra->estado === 'anulado' ? 'ban text-red-600' : 'clock text-gray-500') }}"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Estado</p>
                    <p class="font-semibold text-gray-900 text-sm capitalize">{{ $compra->estado }}</p>
                </div>
            </div>
            <!-- Código -->
            <div class="bg-white rounded-xl shadow p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-hashtag text-blue-700"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Código</p>
                    <p class="font-semibold text-gray-900 text-sm font-mono">{{ $compra->codigo }}</p>
                </div>
            </div>
            <!-- Fecha -->
            <div class="bg-white rounded-xl shadow p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar text-purple-700"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Fecha Emisión</p>
                    <p class="font-semibold text-gray-900 text-sm">{{ $compra->fecha->format('d/m/Y') }}</p>
                </div>
            </div>
            <!-- Total -->
            <div class="bg-gradient-to-br from-blue-900 to-blue-700 rounded-xl shadow p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center shrink-0">
                    <i class="fas fa-dollar-sign text-white"></i>
                </div>
                <div>
                    <p class="text-xs text-blue-200">Total</p>
                    <p class="font-bold text-white text-lg">{{ $compra->moneda_simbolo }} {{ number_format($compra->total, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             GRID PRINCIPAL
             ═══════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ─── COLUMNA IZQUIERDA ─── -->
            <div class="space-y-5">

                <!-- Información de la compra -->
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-5 py-4">
                        <h2 class="font-bold text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-info-circle"></i> Información de la Compra
                        </h2>
                    </div>
                    <div class="p-5 space-y-2.5 text-sm">
                        @php
                            $rows = [
                                ['N° Factura', $compra->numero_factura, 'font-mono font-semibold'],
                                ['Registrado por', $compra->usuario->name ?? '—', ''],
                                ['Tipo operación SUNAT', $compra->tipo_operacion_texto ?? $compra->tipo_operacion, 'text-xs'],
                                ['Tipo de compra', $tipoLabel[$tc] ?? 'Local', ''],
                            ];
                            if ($compra->fecha_vencimiento) {
                                $rows[] = ['Vencimiento', $compra->fecha_vencimiento->format('d/m/Y'), ''];
                            }
                            if ($compra->guia_remision) {
                                $rows[] = ['Guía de remisión', $compra->guia_remision, 'font-mono'];
                            }
                            if ($compra->transportista) {
                                $rows[] = ['Transportista', $compra->transportista, ''];
                            }
                            if ($compra->estado === 'anulado') {
                                $rows[] = ['Fecha anulación', $compra->fecha_anulacion ? \Carbon\Carbon::parse($compra->fecha_anulacion)->format('d/m/Y H:i') : '—', 'text-red-600'];
                                $rows[] = ['Motivo anulación', $compra->motivo_anulacion ?? '—', 'text-red-600'];
                            }
                        @endphp
                        @foreach($rows as [$label, $value, $cls])
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-500 shrink-0">{{ $label }}:</span>
                            <span class="font-medium text-gray-900 text-right {{ $cls }}">{{ $value }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Proveedor -->
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-5 py-4">
                        <h2 class="font-bold text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-truck"></i> Proveedor
                        </h2>
                    </div>
                    <div class="p-5 text-sm">
                        <p class="font-semibold text-gray-900 text-base">{{ $compra->proveedor->nombre_comercial ?? $compra->proveedor->razon_social }}</p>
                        @if(($compra->proveedor->nombre_comercial ?? '') !== ($compra->proveedor->razon_social ?? ''))
                            <p class="text-gray-500 mt-0.5">{{ $compra->proveedor->razon_social }}</p>
                        @endif
                        <p class="text-blue-700 font-mono mt-1">RUC: {{ $compra->proveedor->ruc }}</p>
                        @if($compra->proveedor->direccion)
                            <p class="text-gray-500 mt-1 text-xs">{{ $compra->proveedor->direccion }}</p>
                        @endif
                    </div>
                </div>

                <!-- Almacén + Pago -->
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-green-700 to-green-600 px-5 py-4">
                        <h2 class="font-bold text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-warehouse"></i> Almacén &amp; Pago
                        </h2>
                    </div>
                    <div class="p-5 space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Almacén destino:</span>
                            <span class="font-semibold text-gray-900">{{ $compra->almacen->nombre }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Forma de pago:</span>
                            <span class="font-medium text-gray-900 capitalize">{{ $compra->forma_pago }}</span>
                        </div>
                        @if($compra->forma_pago === 'credito' && $compra->condicion_pago)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Días de crédito:</span>
                            <span class="font-medium text-gray-900">{{ $compra->condicion_pago }} días</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Moneda:</span>
                            <span class="font-medium text-gray-900">{{ $compra->tipo_moneda }} ({{ $compra->moneda_simbolo }})</span>
                        </div>
                        @if($compra->tipo_moneda === 'USD' && $compra->tipo_cambio)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tipo de cambio:</span>
                            <span class="font-medium text-gray-900">S/ {{ number_format($compra->tipo_cambio, 3) }}</span>
                        </div>
                        @endif
                        <div class="pt-2 border-t border-gray-100 flex justify-between font-bold text-base">
                            <span class="text-gray-700">Total:</span>
                            <span class="text-blue-900">{{ $compra->moneda_simbolo }} {{ number_format($compra->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Datos Importación (solo si tipo_compra = importacion) -->
                @if($tc === 'importacion')
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-600 to-orange-500 px-5 py-4">
                        <h2 class="font-bold text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-ship"></i> Datos de Importación
                        </h2>
                    </div>
                    <div class="p-5 space-y-2.5 text-sm">
                        @if($compra->numero_dua)
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500 shrink-0">N° DUA:</span>
                            <span class="font-mono font-semibold text-gray-900">{{ $compra->numero_dua }}</span>
                        </div>
                        @endif
                        @if($compra->numero_manifiesto)
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500 shrink-0">N° Manifiesto:</span>
                            <span class="font-mono font-semibold text-gray-900">{{ $compra->numero_manifiesto }}</span>
                        </div>
                        @endif
                        @if($compra->agente_aduanas)
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500 shrink-0">Agente Aduanas:</span>
                            <span class="font-medium text-gray-900 text-right">{{ $compra->agente_aduanas }}</span>
                        </div>
                        @endif
                        <div class="pt-2 border-t border-orange-100 space-y-1.5">
                            @if(($compra->flete_usd ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Flete (USD):</span>
                                <span class="font-medium">$ {{ number_format($compra->flete_usd ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->seguro_usd ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Seguro (USD):</span>
                                <span class="font-medium">$ {{ number_format($compra->seguro_usd ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->otros_usd ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Otros (USD):</span>
                                <span class="font-medium">$ {{ number_format($compra->otros_usd ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->impuestos_usd ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Impuestos (USD):</span>
                                <span class="font-medium">$ {{ number_format($compra->impuestos_usd ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->impuestos_pen ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Impuestos (S/):</span>
                                <span class="font-medium">S/ {{ number_format($compra->impuestos_pen ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->transporte_local_pen ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Transporte local (S/):</span>
                                <span class="font-medium">S/ {{ number_format($compra->transporte_local_pen ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @if(($compra->percepcion_pen ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Percepción (S/):</span>
                                <span class="font-medium">S/ {{ number_format($compra->percepcion_pen ?? 0, 2) }}</span>
                            </div>
                            @endif
                            @php
                                $cifTotal = ($compra->flete_usd ?? 0) + ($compra->seguro_usd ?? 0)
                                          + ($compra->otros_usd ?? 0) + ($compra->impuestos_usd ?? 0)
                                          + ($compra->impuestos_pen ?? 0) + ($compra->transporte_local_pen ?? 0)
                                          + ($compra->percepcion_pen ?? 0);
                            @endphp
                            <div class="flex justify-between pt-1 border-t border-orange-200 font-bold text-orange-800">
                                <span>Total Costos Imp.:</span>
                                <span>{{ number_format($cifTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Cuenta por Pagar -->
                @if($compra->cuentaPorPagar)
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-red-700 to-red-600 px-5 py-4">
                        <h2 class="font-bold text-white flex items-center gap-2 text-sm">
                            <i class="fas fa-credit-card"></i> Cuenta por Pagar
                        </h2>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-500 mb-1">Monto Total</p>
                                <p class="text-base font-bold text-gray-900">{{ number_format($compra->cuentaPorPagar->monto_total, 2) }}</p>
                            </div>
                            <div class="bg-green-50 rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-500 mb-1">Pagado</p>
                                <p class="text-base font-bold text-green-600">{{ number_format($compra->cuentaPorPagar->monto_pagado, 2) }}</p>
                            </div>
                            <div class="bg-red-50 rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-500 mb-1">Saldo</p>
                                <p class="text-base font-bold text-red-600">{{ number_format($compra->cuentaPorPagar->saldo_pendiente, 2) }}</p>
                            </div>
                            <div class="bg-blue-50 rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-500 mb-1">Vencimiento</p>
                                <p class="text-sm font-bold text-blue-900">{{ $compra->cuentaPorPagar->fecha_vencimiento->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <a href="{{ route('cuentas-por-pagar.show', $compra->cuentaPorPagar) }}"
                           class="mt-4 block text-center px-4 py-2 bg-blue-900 text-white rounded-xl hover:bg-blue-800 text-sm transition">
                            Ver detalle de cuenta
                        </a>
                    </div>
                </div>
                @endif

            </div>{{-- /col-left --}}

            <!-- ─── COLUMNA DERECHA (productos + totales) ─── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Tabla de productos -->
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-bold text-white flex items-center gap-2">
                            <i class="fas fa-boxes"></i> Productos de la Compra
                        </h2>
                        <span class="bg-white/20 text-white text-xs font-semibold px-3 py-1 rounded-full">
                            {{ $compra->detalles->count() }} {{ $compra->detalles->count() === 1 ? 'ítem' : 'ítems' }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Marca / Modelo / Cap.</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Color</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Cant.</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">P. Unit.</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($compra->detalles as $detalle)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-900">{{ $detalle->producto->nombre }}</p>
                                        @if($detalle->codigo_barras)
                                            <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $detalle->codigo_barras }}</p>
                                        @endif
                                        @if($detalle->imeis_con_legacy->count())
                                            <p class="text-xs text-purple-600 mt-0.5">
                                                <i class="fas fa-microchip mr-1"></i>{{ $detalle->imeis_con_legacy->count() }} IMEI(s)
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @php
                                            $marca = $detalle->producto->marca?->nombre ?? null;
                                            $modelo = $detalle->modelo?->nombre ?? $detalle->producto->modelo?->nombre ?? null;
                                            $capacidad = $detalle->variante?->capacidad ?? null;
                                        @endphp
                                        <span class="text-gray-700">{{ $marca ?? '-' }}</span>
                                        @if($modelo)
                                            <span class="text-gray-400 mx-1">/</span>
                                            <span class="text-gray-600">{{ $modelo }}</span>
                                        @endif
                                        @if($capacidad)
                                            <span class="text-gray-400 mx-1">/</span>
                                            <span class="px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-medium">{{ $capacidad }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @php $color = $detalle->color?->nombre ?? $detalle->producto->color?->nombre ?? null; @endphp
                                        @if($color)
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">{{ $color }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-gray-900">{{ $detalle->cantidad }}</td>
                                    <td class="px-5 py-4 text-right text-gray-700">
                                        {{ $compra->moneda_simbolo }} {{ number_format($detalle->precio_unitario, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-blue-900">
                                        {{ $compra->moneda_simbolo }} {{ number_format($detalle->cantidad * $detalle->precio_unitario, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totales -->
                    <div class="border-t border-gray-200 px-6 py-5 bg-gray-50">
                        <div class="flex justify-end">
                            <div class="w-72 space-y-2 text-sm">
                                @php
                                    $sumaProductos = $compra->detalles->sum(fn($d) => $d->cantidad * $d->precio_unitario);
                                    $cifTotal = ($compra->flete_usd ?? 0) + ($compra->seguro_usd ?? 0)
                                              + ($compra->otros_usd ?? 0) + ($compra->impuestos_usd ?? 0)
                                              + ($compra->impuestos_pen ?? 0) + ($compra->transporte_local_pen ?? 0)
                                              + ($compra->percepcion_pen ?? 0);
                                @endphp

                                <div class="flex justify-between text-gray-600">
                                    <span>Productos:</span>
                                    <span>{{ $compra->moneda_simbolo }} {{ number_format($sumaProductos, 2) }}</span>
                                </div>

                                @if($tc === 'importacion' && $cifTotal > 0)
                                <div class="flex justify-between text-orange-700">
                                    <span>Costos de Importación:</span>
                                    <span>{{ number_format($cifTotal, 2) }}</span>
                                </div>
                                @endif

                                @if($compra->monto_adicional > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>{{ $compra->concepto_adicional ?? 'Monto adicional' }}:</span>
                                    <span>{{ $compra->moneda_simbolo }} {{ number_format($compra->monto_adicional, 2) }}</span>
                                </div>
                                @endif

                                <div class="flex justify-between pt-2 border-t border-gray-200 text-gray-700">
                                    <span>Subtotal (base):</span>
                                    <span class="font-semibold">{{ $compra->moneda_simbolo }} {{ number_format($compra->subtotal, 2) }}</span>
                                </div>

                                @if($compra->igv > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>IGV (18%):</span>
                                    <span>{{ $compra->moneda_simbolo }} {{ number_format($compra->igv, 2) }}</span>
                                </div>
                                @endif

                                <div class="flex justify-between pt-2 border-t-2 border-blue-200 font-bold text-base">
                                    <span class="text-gray-900">Total:</span>
                                    <span class="text-blue-900">{{ $compra->moneda_simbolo }} {{ number_format($compra->total, 2) }}</span>
                                </div>

                                @if($compra->tipo_moneda === 'USD' && $compra->tipo_cambio)
                                <div class="flex justify-between text-xs text-gray-500 pt-1">
                                    <span>Equivalente en soles (TC {{ number_format($compra->tipo_cambio, 3) }}):</span>
                                    <span class="font-medium text-gray-700">S/ {{ number_format($compra->total * $compra->tipo_cambio, 2) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    @if($compra->observaciones)
                    <div class="px-6 py-4 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                            <i class="fas fa-comment mr-1"></i>Observaciones
                        </p>
                        <p class="text-sm text-gray-700">{{ $compra->observaciones }}</p>
                    </div>
                    @endif
                </div>

                <!-- ══════════════════════════════════════════════════════════
                     PRORRATEO DE COSTOS DE IMPORTACIÓN
                     Solo se muestra cuando tipo_compra = importacion
                     ══════════════════════════════════════════════════════════ -->
                @if($tc === 'importacion')
                @php
                    $tcVal   = (float)($compra->tipo_cambio ?? 1);
                    $esUsd   = $compra->tipo_moneda === 'USD';

                    $gastosPen =
                        (((float)($compra->flete_usd     ?? 0))
                        + ((float)($compra->seguro_usd    ?? 0))
                        + ((float)($compra->otros_usd     ?? 0))
                        + ((float)($compra->impuestos_usd ?? 0))) * $tcVal
                        + (float)($compra->transporte_local_pen ?? 0)
                        + (float)($compra->impuestos_pen        ?? 0)
                        + (float)($compra->percepcion_pen       ?? 0);

                    $totalSubtotalPen = $compra->detalles->sum(
                        fn($d) => (float)$d->subtotal * ($esUsd ? $tcVal : 1)
                    );

                    $tieneProrrateo = $compra->detalles->first()?->costo_unitario_final_pen > 0;
                @endphp
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-600 to-amber-500 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-bold text-white flex items-center gap-2">
                            <i class="fas fa-calculator"></i>
                            Prorrateo de Costos de Importación
                        </h2>
                        <div class="flex items-center gap-3">
                            @if($gastosPen > 0)
                                <span class="text-orange-100 text-sm">
                                    Total gastos: <strong class="text-white">S/ {{ number_format($gastosPen, 2) }}</strong>
                                </span>
                            @endif
                            @if(auth()->user()->role->nombre === 'Administrador')
                            <form action="{{ route('compras.recalcular-prorrateo', $compra) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition flex items-center gap-1.5"
                                        title="Recalcula el prorrateo con los gastos actuales">
                                    <i class="fas fa-sync-alt"></i> Recalcular
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    @if($gastosPen <= 0)
                    <div class="px-6 py-5 flex items-center gap-3 text-amber-700 bg-amber-50">
                        <i class="fas fa-info-circle text-lg"></i>
                        <p class="text-sm">Esta compra no tiene gastos de importación registrados (flete, seguro, impuestos, etc.). El costo sugerido es el precio de compra convertido a soles.</p>
                    </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-orange-50 border-b border-orange-100">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-orange-800 uppercase">Producto</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-orange-800 uppercase">Cant.</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-orange-800 uppercase">
                                        Precio Unit.<br><span class="text-orange-500 normal-case font-normal">({{ $compra->tipo_moneda }})</span>
                                    </th>
                                    @if($esUsd)
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-orange-800 uppercase">
                                        Precio Unit.<br><span class="text-orange-500 normal-case font-normal">(PEN, TC {{ number_format($tcVal, 3) }})</span>
                                    </th>
                                    @endif
                                    @if($gastosPen > 0)
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-orange-800 uppercase">
                                        Gasto prorateado<br><span class="text-orange-500 normal-case font-normal">/unidad (S/)</span>
                                    </th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-orange-800 uppercase">% del total</th>
                                    @endif
                                    <th class="px-5 py-3 text-right text-xs font-bold text-green-800 uppercase bg-green-50">
                                        Costo sugerido<br><span class="text-green-600 normal-case font-normal">/unidad (S/)</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($compra->detalles as $detalle)
                                @php
                                    $subtotalPen    = (float)$detalle->subtotal * ($esUsd ? $tcVal : 1);
                                    $proporcion     = $totalSubtotalPen > 0 ? $subtotalPen / $totalSubtotalPen : 0;
                                    $gastoAsignado  = $proporcion * $gastosPen;
                                    $cantidad       = max(1, (int)$detalle->cantidad);
                                    $proUnitPen     = $gastoAsignado / $cantidad;
                                    $precioUnitPen  = (float)$detalle->precio_unitario * ($esUsd ? $tcVal : 1);
                                    // Preferir el valor guardado; si aún es 0, calcular en vivo
                                    $costoFinalPen  = (float)$detalle->costo_unitario_final_pen > 0
                                                        ? (float)$detalle->costo_unitario_final_pen
                                                        : ($precioUnitPen + $proUnitPen);
                                    $gastoSaved     = (float)$detalle->costo_prorateado_pen > 0
                                                        ? (float)$detalle->costo_prorateado_pen
                                                        : $proUnitPen;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-900">{{ $detalle->producto->nombre ?? '-' }}</p>
                                        @php
                                            $varLabel = trim(
                                                ($detalle->variante?->color?->nombre ?? $detalle->color?->nombre ?? '')
                                                . ($detalle->variante?->capacidad ? ' / ' . $detalle->variante->capacidad : '')
                                            );
                                        @endphp
                                        @if($varLabel)
                                            <p class="text-xs text-gray-400">{{ $varLabel }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ $detalle->cantidad }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">
                                        {{ $compra->moneda_simbolo }} {{ number_format($detalle->precio_unitario, 2) }}
                                    </td>
                                    @if($esUsd)
                                    <td class="px-5 py-3 text-right text-gray-700">
                                        S/ {{ number_format($precioUnitPen, 2) }}
                                    </td>
                                    @endif
                                    @if($gastosPen > 0)
                                    <td class="px-5 py-3 text-right text-orange-700 font-medium">
                                        + S/ {{ number_format($gastoSaved, 2) }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-500">
                                        {{ number_format($proporcion * 100, 1) }}%
                                    </td>
                                    @endif
                                    <td class="px-5 py-3 text-right font-bold text-green-800 bg-green-50">
                                        S/ {{ number_format($costoFinalPen, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            @if($gastosPen > 0)
                            <tfoot class="bg-orange-50 border-t-2 border-orange-200">
                                <tr>
                                    @php
                                        // cols: Producto + Cant + Precio(moneda) [+ Precio PEN si USD] [+ Gasto/ud + %]
                                        $colspanNota = 2 + ($esUsd ? 1 : 0) + ($gastosPen > 0 ? 2 : 0);
                                    @endphp
                                    <td colspan="{{ $colspanNota }}"
                                        class="px-5 py-3 text-xs text-orange-700">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Los gastos (S/ {{ number_format($gastosPen, 2) }}) se distribuyen en proporción al valor de cada línea.
                                        TC aplicado: {{ number_format($tcVal, 3) }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-xs font-semibold text-orange-800">Total gastos:</td>
                                    <td class="px-5 py-3 text-right font-bold text-orange-900 bg-orange-100">
                                        S/ {{ number_format($gastosPen, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>

                    @if(!$tieneProrrateo && $compra->detalles->isNotEmpty())
                    <div class="px-6 py-3 bg-yellow-50 border-t border-yellow-200 flex items-center gap-2 text-yellow-800 text-xs">
                        <i class="fas fa-exclamation-triangle"></i>
                        Los valores de costo sugerido se muestran calculados en tiempo real. Para guardarlos en el sistema usa <strong class="ml-1">Recalcular</strong>.
                    </div>
                    @endif
                </div>
                @endif

                <!-- IMEIs por detalle -->
                @php $detallesSerie = $compra->detalles->filter(fn($d) => $d->producto->tipo_inventario === 'serie'); @endphp
                @if($detallesSerie->isNotEmpty())
                <div class="bg-white rounded-2xl shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-bold text-white flex items-center gap-2">
                            <i class="fas fa-microchip"></i> IMEIs por Producto
                        </h2>
                        <span class="text-purple-200 text-sm">{{ $detallesSerie->sum(fn($d) => $d->imeis_con_legacy->count()) }} / {{ $detallesSerie->sum('cantidad') }} registrados</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($detallesSerie as $detalle)
                        @php
                            $registrados = $detalle->imeis_con_legacy->count();
                            $esperados   = $detalle->cantidad;
                            $pendientes  = $esperados - $registrados;
                            $completo    = $pendientes <= 0;
                        @endphp
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="font-semibold text-gray-800 text-sm">{{ $detalle->producto->nombre }}</p>
                                    @if($detalle->variante)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $detalle->variante->nombre_completo }}</p>
                                    @elseif($detalle->color)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $detalle->color->nombre }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm {{ $completo ? 'text-green-600 font-semibold' : 'text-orange-500 font-semibold' }}">
                                        {{ $registrados }}/{{ $esperados }} IMEIs
                                    </span>
                                    @if(!$completo && $compra->estado !== 'anulado')
                                    <button type="button"
                                            onclick="abrirModalImei({{ $detalle->id }}, '{{ addslashes($detalle->producto->nombre) }}', {{ $pendientes }}, '{{ route('compras.detalle.imeis.bulk', [$compra, $detalle]) }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded-lg transition">
                                        <i class="fas fa-plus text-xs"></i> Registrar IMEIs
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @if($registrados > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($detalle->imeis_con_legacy as $imei)
                                <span class="px-2.5 py-1 bg-purple-50 border border-purple-200 rounded-lg text-xs font-mono text-purple-800">
                                    {{ $imei->codigo_imei }}
                                </span>
                                @endforeach
                            </div>
                            @else
                            <p class="text-xs text-gray-400 italic">Sin IMEIs registrados aún.</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Modal Registrar IMEIs -->
                <div id="modalImei" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
                        <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i class="fas fa-microchip"></i>
                                <span id="modalImeiTitulo">Registrar IMEIs</span>
                            </h3>
                            <button onclick="cerrarModalImei()" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-500 mb-1">
                                Faltan <strong id="modalImeiPendientes" class="text-purple-700"></strong> IMEI(s) por registrar.
                            </p>
                            <p class="text-xs text-gray-400 mb-4">Puedes escribirlos uno por uno o pegar una lista (uno por línea).</p>

                            <!-- Pegar lista -->
                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Pegar lista de IMEIs (uno por línea)</label>
                                <textarea id="imeiPasteArea" rows="5"
                                    placeholder="123456789012345&#10;123456789012346&#10;123456789012347"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-400 focus:border-purple-400 resize-none"></textarea>
                                <button type="button" onclick="parsearPegado()"
                                    class="mt-1.5 text-xs text-purple-600 hover:text-purple-800 font-medium">
                                    <i class="fas fa-arrow-down mr-1"></i>Cargar en lista
                                </button>
                            </div>

                            <!-- Lista de IMEIs ingresados -->
                            <div id="imeiList" class="space-y-2 mb-4 max-h-48 overflow-y-auto"></div>
                            <button type="button" onclick="agregarFilaImei()"
                                class="text-xs text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1 mb-4">
                                <i class="fas fa-plus-circle"></i> Agregar IMEI manualmente
                            </button>

                            <div id="imeiError" class="hidden text-red-600 text-sm bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3"></div>

                            <div class="flex justify-end gap-3">
                                <button type="button" onclick="cerrarModalImei()"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="button" id="btnGuardarImeis" onclick="guardarImeis()"
                                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition flex items-center gap-2">
                                    <i class="fas fa-save"></i> Guardar IMEIs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /col-right --}}

        </div>{{-- /grid --}}
    </div>

    <script>
        /* ── Modal IMEIs ─────────────────────────────────────── */
        let _imeiUrl = '';
        let _imeiPendientes = 0;
        let _imeiContador = 0;

        function abrirModalImei(detalleId, nombre, pendientes, url) {
            _imeiUrl = url;
            _imeiPendientes = pendientes;
            _imeiContador = 0;
            document.getElementById('modalImeiTitulo').textContent = 'Registrar IMEIs — ' + nombre;
            document.getElementById('modalImeiPendientes').textContent = pendientes;
            document.getElementById('imeiList').innerHTML = '';
            document.getElementById('imeiPasteArea').value = '';
            document.getElementById('imeiError').classList.add('hidden');
            agregarFilaImei();
            document.getElementById('modalImei').classList.remove('hidden');
        }

        function cerrarModalImei() {
            document.getElementById('modalImei').classList.add('hidden');
        }

        function agregarFilaImei(valor = '') {
            const list = document.getElementById('imeiList');
            const id = 'imei_' + (++_imeiContador);
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.id = 'fila_' + id;
            div.innerHTML = `
                <input type="text" id="${id}" maxlength="15"
                       value="${valor}"
                       placeholder="15 dígitos"
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                       oninput="this.value=this.value.replace(/\\D/g,'')">
                <button type="button" onclick="document.getElementById('fila_${id}').remove()"
                        class="text-gray-400 hover:text-red-500 transition text-sm px-1">
                    <i class="fas fa-times"></i>
                </button>`;
            list.appendChild(div);
        }

        function parsearPegado() {
            const raw = document.getElementById('imeiPasteArea').value.trim();
            if (!raw) return;
            const lines = raw.split(/[\n,;]+/).map(l => l.trim()).filter(l => l.length > 0);
            document.getElementById('imeiList').innerHTML = '';
            _imeiContador = 0;
            lines.forEach(l => agregarFilaImei(l));
            document.getElementById('imeiPasteArea').value = '';
        }

        async function guardarImeis() {
            const inputs = document.querySelectorAll('#imeiList input[type=text]');
            const imeis = [];
            inputs.forEach(inp => {
                const v = inp.value.trim();
                if (v) imeis.push({ codigo_imei: v });
            });

            if (imeis.length === 0) {
                mostrarErrorImei('Ingresa al menos un IMEI.');
                return;
            }

            const btn = document.getElementById('btnGuardarImeis');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            document.getElementById('imeiError').classList.add('hidden');

            try {
                const res = await fetch(_imeiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ imeis }),
                });
                const data = await res.json();
                if (data.success) {
                    cerrarModalImei();
                    Swal.fire({ icon: 'success', title: 'IMEIs registrados', text: data.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    mostrarErrorImei(data.message || 'Error al guardar.');
                }
            } catch (e) {
                mostrarErrorImei('Error de conexión. Intenta nuevamente.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar IMEIs';
            }
        }

        function mostrarErrorImei(msg) {
            const el = document.getElementById('imeiError');
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        document.getElementById('modalImei').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalImei();
        });
        /* ── fin Modal IMEIs ─────────────────────────────────── */

        function anularCompra(id) {
            Swal.fire({
                title: 'Anular compra',
                html: `<p class="text-gray-600 mb-3">El stock ingresado se revertirá. Esta acción no se puede deshacer.</p>
                       <label class="block text-sm font-medium text-gray-700 text-left mb-1">Motivo de anulación <span class="text-red-500">*</span></label>
                       <textarea id="swal-motivo" class="swal2-textarea" rows="2" placeholder="Ej: Factura errónea, proveedor equivocado..."></textarea>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ea580c',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-ban mr-1"></i>Anular',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const motivo = document.getElementById('swal-motivo').value.trim();
                    if (!motivo) {
                        Swal.showValidationMessage('Debes ingresar un motivo de anulación');
                        return false;
                    }
                    return motivo;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/compras/${id}/anular`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ motivo: result.value })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: '¡Anulada!', text: 'La compra ha sido anulada y el stock revertido.', timer: 2000 })
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'No se pudo conectar al servidor', 'error'));
                }
            });
        }

        function eliminarCompra(id) {
            Swal.fire({
                title: 'Eliminar compra',
                html: `<p class="text-gray-600">Se revertirá el stock y se eliminará el registro permanentemente.</p>
                       <p class="mt-2 text-red-600 font-semibold">Esta acción no se puede deshacer.</p>`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-1"></i>Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/compras/${id}`;
                    form.innerHTML = `
                        <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                        <input type="hidden" name="_method" value="DELETE">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
