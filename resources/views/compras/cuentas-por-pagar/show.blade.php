<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta por Pagar - CORPORACIÓN ADIVON SAC</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">

        <!-- Breadcrumb + título -->
        <div class="mb-6">
            <div class="flex items-center text-sm text-gray-500 mb-2">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-900">Dashboard</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="{{ route('cuentas-por-pagar.index') }}" class="hover:text-blue-900">Cuentas por Pagar</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-gray-700 font-medium">Factura {{ $cuenta->numero_factura }}</span>
            </div>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-credit-card mr-3 text-blue-900"></i>
                    Detalle de Cuenta por Pagar
                </h1>
                <div class="flex gap-2">
                    <a href="{{ route('compras.show', $cuenta->compra_id) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                        <i class="fas fa-file-invoice mr-1"></i>Ver Compra
                    </a>
                    <a href="{{ route('cuentas-por-pagar.index') }}"
                       class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Volver
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <!-- Grid 3 columnas -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ============================= --}}
            {{-- COLUMNA IZQUIERDA             --}}
            {{-- ============================= --}}
            <div class="lg:col-span-1 space-y-5">

                <!-- Estado de la cuenta -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>Estado de la Cuenta
                        </h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Estado:</span>
                            @php
                                $badges = [
                                    'pagado'   => 'bg-green-100 text-green-800',
                                    'pendiente'=> 'bg-yellow-100 text-yellow-800',
                                    'parcial'  => 'bg-orange-100 text-orange-800',
                                    'vencido'  => 'bg-red-100 text-red-800',
                                ];
                                $icons = [
                                    'pagado'   => 'fa-check-circle',
                                    'pendiente'=> 'fa-clock',
                                    'parcial'  => 'fa-adjust',
                                    'vencido'  => 'fa-exclamation-circle',
                                ];
                            @endphp
                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $badges[$cuenta->estado] ?? '' }}">
                                <i class="fas {{ $icons[$cuenta->estado] ?? '' }} mr-1"></i>
                                {{ ucfirst($cuenta->estado) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Factura:</span>
                            <span class="font-medium">{{ $cuenta->numero_factura }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Emisión:</span>
                            <span class="font-medium">{{ $cuenta->fecha_emision->format('d/m/Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Vencimiento:</span>
                            <span class="font-medium {{ $cuenta->esta_vencida ? 'text-red-600' : '' }}">
                                {{ $cuenta->fecha_vencimiento->format('d/m/Y') }}
                            </span>
                        </div>
                        @if($cuenta->dias_credito)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Crédito:</span>
                            <span class="font-medium">{{ $cuenta->dias_credito }} días</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Moneda:</span>
                            <span class="font-medium">{{ $cuenta->moneda }}</span>
                        </div>
                        @if($cuenta->tipo_cambio && $cuenta->tipo_cambio != 1)
                        <div class="flex justify-between">
                            <span class="text-gray-500">T.C.:</span>
                            <span class="font-medium">{{ $cuenta->tipo_cambio }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Proveedor -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-700 to-purple-600 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-building mr-2"></i>Proveedor
                        </h2>
                    </div>
                    <div class="p-5">
                        <p class="font-semibold text-gray-900">{{ $cuenta->proveedor->razon_social }}</p>
                        <p class="text-sm text-gray-500 mt-1">RUC: {{ $cuenta->proveedor->ruc }}</p>
                        @if($cuenta->proveedor->telefono)
                            <p class="text-sm text-gray-500 mt-1"><i class="fas fa-phone mr-1"></i>{{ $cuenta->proveedor->telefono }}</p>
                        @endif
                    </div>
                </div>

                <!-- Acciones rápidas -->
                @if($cuenta->saldo_pendiente > 0)
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-green-700 to-green-600 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-bolt mr-2"></i>Acciones
                        </h2>
                    </div>
                    <div class="p-5 space-y-2">
                        <button onclick="abrirModalPagoLibre()"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-4 rounded-lg transition text-sm flex items-center justify-center">
                            <i class="fas fa-credit-card mr-2"></i>Registrar Pago Libre
                        </button>
                        <button onclick="document.getElementById('seccionCuotas').scrollIntoView({behavior:'smooth'})"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg transition text-sm flex items-center justify-center">
                            <i class="fas fa-th-list mr-2"></i>Gestionar Cuotas
                        </button>
                    </div>
                </div>
                @endif
            </div>

            {{-- ============================= --}}
            {{-- COLUMNA DERECHA (2/3)         --}}
            {{-- ============================= --}}
            <div class="lg:col-span-2 space-y-6">

                <!-- Resumen financiero -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-chart-pie mr-2"></i>Información Financiera
                        </h2>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-4 bg-gray-50 rounded-xl">
                                <p class="text-xs text-gray-500 mb-1">Monto Total</p>
                                <p class="text-xl font-bold text-gray-900">S/ {{ number_format($cuenta->monto_total, 2) }}</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-xl">
                                <p class="text-xs text-gray-500 mb-1">Pagado</p>
                                <p class="text-xl font-bold text-green-600">S/ {{ number_format($cuenta->monto_pagado, 2) }}</p>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-xl">
                                <p class="text-xs text-gray-500 mb-1">Saldo</p>
                                <p class="text-xl font-bold text-red-600">S/ {{ number_format($cuenta->saldo_pendiente, 2) }}</p>
                            </div>
                        </div>

                        @if($cuenta->saldo_pendiente > 0)
                        @php
                            $dias = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($cuenta->fecha_vencimiento)->startOfDay(), false);
                        @endphp
                        <div class="mt-4 p-3 rounded-lg {{ $dias < 0 ? 'bg-red-50 text-red-700' : ($dias <= 7 ? 'bg-yellow-50 text-yellow-700' : 'bg-blue-50 text-blue-700') }}">
                            @if($dias < 0)
                                <i class="fas fa-exclamation-triangle mr-2"></i>Vencida hace {{ abs($dias) }} días
                            @elseif($dias == 0)
                                <i class="fas fa-clock mr-2"></i>Vence hoy
                            @else
                                <i class="fas fa-calendar-check mr-2"></i>Vence en {{ $dias }} días ({{ $cuenta->fecha_vencimiento->format('d/m/Y') }})
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ===================================== --}}
                {{-- SECCIÓN DE CUOTAS                    --}}
                {{-- ===================================== --}}
                <div id="seccionCuotas" class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-5 py-4 flex items-center justify-between">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-th-list mr-2"></i>
                            Programación de Cuotas
                        </h2>
                        @if($cuenta->saldo_pendiente > 0)
                        <button onclick="document.getElementById('formGenerarCuotas').classList.toggle('hidden')"
                                class="flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-magic"></i> Generar cuotas
                        </button>
                        @endif
                    </div>

                    {{-- Formulario para generar cuotas (oculto por defecto si ya hay cuotas) --}}
                    <div id="formGenerarCuotas" class="{{ $cuenta->cuotas->count() > 0 ? 'hidden' : '' }} p-5 bg-blue-50 border-b border-blue-100">
                        <p class="text-sm text-blue-700 mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Días de crédito: <strong>{{ $cuenta->dias_credito ?? 30 }}</strong> días.
                            El sistema calculará las fechas y montos automáticamente.
                        </p>
                        <div class="flex gap-3 items-end">
                            <div class="flex-1 max-w-xs">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de cuotas
                                </label>
                                <input type="number" id="inputNumCuotas" min="1" max="48" value="3"
                                       class="w-full px-4 py-2 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            </div>
                            <button onclick="generarCuotas()"
                                    class="px-5 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800 font-medium text-sm flex items-center gap-2">
                                <i class="fas fa-calculator"></i> Calcular y guardar
                            </button>
                        </div>
                        @if($cuenta->cuotas->where('estado','pendiente')->count() > 0)
                        <p class="text-xs text-orange-600 mt-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Ya existen {{ $cuenta->cuotas->where('estado','pendiente')->count() }} cuotas pendientes.
                            Generar nuevas las reemplazará.
                        </p>
                        @endif
                    </div>

                    {{-- Tabla de cuotas --}}
                    @if($cuenta->cuotas->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuota</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Pago</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($cuenta->cuotas as $cuota)
                                @php
                                    $hoy = now()->startOfDay();
                                    $venc = $cuota->fecha_vencimiento->startOfDay();
                                    $diasCuota = $hoy->diffInDays($venc, false);
                                    $rowClass = '';
                                    if ($cuota->estado === 'pagado') $rowClass = 'bg-green-50';
                                    elseif ($diasCuota < 0) $rowClass = 'bg-red-50';
                                    elseif ($diasCuota <= 3) $rowClass = 'bg-yellow-50';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="px-4 py-3 font-semibold text-gray-700">
                                        {{ $cuota->numero_cuota }}/{{ $cuota->total_cuotas }}
                                    </td>
                                    <td class="px-4 py-3 {{ $cuota->estado !== 'pagado' && $diasCuota < 0 ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                        {{ $cuota->fecha_vencimiento->format('d/m/Y') }}
                                        @if($cuota->estado === 'pendiente' && $diasCuota < 0)
                                            <span class="text-xs text-red-500 block">Vencida</span>
                                        @elseif($cuota->estado === 'pendiente' && $diasCuota <= 3 && $diasCuota >= 0)
                                            <span class="text-xs text-yellow-600 block">{{ $diasCuota == 0 ? 'Hoy' : "En {$diasCuota} días" }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                        S/ {{ number_format($cuota->monto, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($cuota->estado === 'pagado')
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                                <i class="fas fa-check mr-1"></i>Pagado
                                            </span>
                                        @elseif($diasCuota < 0)
                                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Vencida
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                                                <i class="fas fa-clock mr-1"></i>Pendiente
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        @if($cuota->pago)
                                            {{ $cuota->pago->fecha_pago->format('d/m/Y') }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($cuota->pago && $cuota->pago->comprobante_path)
                                            <a href="{{ Storage::url($cuota->pago->comprobante_path) }}"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-xs">
                                                <img src="{{ Storage::url($cuota->pago->comprobante_path) }}"
                                                     alt="voucher"
                                                     class="w-8 h-8 object-cover rounded border border-blue-200">
                                                Ver
                                            </a>
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($cuota->estado === 'pendiente')
                                            <button onclick="abrirModalPagarCuota({{ $cuota->id }}, {{ $cuota->monto }}, {{ $cuota->numero_cuota }}, {{ $cuota->total_cuotas }})"
                                                    class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-medium transition flex items-center gap-1 mx-auto">
                                                <i class="fas fa-check"></i> Pagar
                                            </button>
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right text-xs font-semibold text-gray-600">
                                        Total cuotas:
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm font-bold text-gray-900">
                                        S/ {{ number_format($cuenta->cuotas->sum('monto'), 2) }}
                                    </td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @else
                    <div class="p-10 text-center text-gray-400">
                        <i class="fas fa-th-list text-4xl mb-3 block opacity-30"></i>
                        <p class="text-sm">No hay cuotas generadas.</p>
                        <p class="text-xs mt-1">Usa el botón <strong>"Generar cuotas"</strong> para crear el plan de pagos.</p>
                    </div>
                    @endif
                </div>

                {{-- ===================================== --}}
                {{-- HISTORIAL DE PAGOS                   --}}
                {{-- ===================================== --}}
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-700 to-gray-600 px-5 py-4">
                        <h2 class="text-base font-bold text-white flex items-center">
                            <i class="fas fa-history mr-2"></i>Historial de Pagos
                            <span class="ml-2 bg-white/20 text-white text-xs px-2 py-0.5 rounded-full">
                                {{ $cuenta->pagos->where('estado','procesado')->count() }}
                            </span>
                        </h2>
                    </div>

                    @if($cuenta->pagos->where('estado','procesado')->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuota</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registrado por</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Voucher</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($cuenta->pagos->where('estado','procesado') as $pago)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-green-600">
                                        S/ {{ number_format($pago->monto, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($pago->numero_cuota && $pago->total_cuotas)
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs">
                                                {{ $pago->numero_cuota }}/{{ $pago->total_cuotas }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 capitalize">{{ $pago->metodo_pago }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if($pago->sucursal)
                                            <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full text-xs">
                                                {{ $pago->sucursal->nombre }}
                                            </span>
                                            @if(!$pago->movimiento_caja_id)
                                                <span class="block text-xs text-amber-600 mt-0.5" title="No se registró egreso en caja">
                                                    <i class="fas fa-triangle-exclamation"></i> sin caja
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $pago->referencia ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $pago->usuario->name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($pago->comprobante_path)
                                            <button onclick="verVoucher('{{ Storage::url($pago->comprobante_path) }}', '{{ $pago->comprobante_original_name ?? 'Voucher' }}')"
                                                    class="inline-flex flex-col items-center group" title="Ver voucher">
                                                <img src="{{ Storage::url($pago->comprobante_path) }}"
                                                     alt="voucher"
                                                     class="w-10 h-10 object-cover rounded-lg border-2 border-blue-200 group-hover:border-blue-500 transition">
                                                <span class="text-xs text-blue-600 mt-0.5 group-hover:underline">Ver</span>
                                            </button>
                                        @else
                                            <span class="text-gray-300 text-xs">Sin voucher</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Total pagado:</td>
                                    <td class="px-4 py-2 text-right font-bold text-green-600">
                                        S/ {{ number_format($cuenta->pagos->where('estado','procesado')->sum('monto'), 2) }}
                                    </td>
                                    <td colspan="6"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @else
                    <div class="p-10 text-center text-gray-400">
                        <i class="fas fa-receipt text-4xl mb-3 block opacity-30"></i>
                        <p class="text-sm">No hay pagos registrados aún.</p>
                    </div>
                    @endif
                </div>

            </div>{{-- fin col-derecha --}}
        </div>{{-- fin grid --}}
    </div>{{-- fin container --}}

    {{-- ================================================== --}}
    {{-- MODAL: PAGAR CUOTA ESPECÍFICA                      --}}
    {{-- ================================================== --}}
    <div id="modalPagarCuota" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="bg-gradient-to-r from-green-700 to-green-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    Pagar Cuota <span id="labelCuota" class="ml-1 opacity-80 text-base"></span>
                </h3>
                <button type="button" onclick="cerrarModalPagarCuota()" class="text-white hover:text-green-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="formPagarCuota" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <input type="hidden" id="cuotaId">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Monto <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="monto" id="montoCuota" step="0.01" min="0.01"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha de pago <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="fecha_pago" id="fechaPagoCuota"
                               value="{{ now()->format('Y-m-d') }}"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100"
                               required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Método de pago <span class="text-red-500">*</span>
                        </label>
                        <select name="metodo_pago" id="metodoPagoCuota"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100" required>
                            <option value="transferencia">Transferencia</option>
                            <option value="cheque">Cheque</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Sucursal (de dónde sale el dinero) <span class="text-red-500">*</span>
                        </label>
                        <select name="sucursal_id" id="sucursalCuota"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100" required>
                            <option value="">Seleccionar...</option>
                            @foreach($sucursales as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Se registrará como egreso en la caja abierta de esa sucursal.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Referencia / N° operación</label>
                    <input type="text" name="referencia"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100"
                           placeholder="N° transferencia, cheque, etc.">
                </div>

                <!-- Subida de voucher -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Voucher / Evidencia de pago
                        <span class="text-gray-400 text-xs font-normal">(foto o imagen)</span>
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-green-400 transition cursor-pointer"
                         onclick="document.getElementById('voucherInput').click()">
                        <div id="voucherPreview" class="hidden mb-2">
                            <img id="voucherImg" src="" alt="preview" class="max-h-32 mx-auto rounded-lg object-contain">
                        </div>
                        <div id="voucherPlaceholder">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 mb-1"></i>
                            <p class="text-sm text-gray-500">Toca para seleccionar foto o imagen</p>
                            <p class="text-xs text-gray-400 mt-0.5">JPG, PNG, GIF, WEBP — máx. 5 MB</p>
                        </div>
                        <p id="voucherNombre" class="text-xs text-green-600 mt-1 hidden"></p>
                    </div>
                    <input type="file" name="comprobante" id="voucherInput" accept="image/*"
                           class="hidden" onchange="previewVoucher(this)">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-100"
                              placeholder="Notas adicionales..."></textarea>
                </div>
            </form>
            <div class="px-6 pb-6 flex justify-end gap-3">
                <button type="button" onclick="cerrarModalPagarCuota()"
                        class="px-5 py-2.5 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium text-sm">
                    Cancelar
                </button>
                <button type="button" id="btnEnviarPagoCuota" onclick="enviarPagoCuota()"
                        class="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 font-medium text-sm flex items-center gap-2">
                    <i class="fas fa-check"></i> Confirmar Pago
                </button>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- MODAL: PAGO LIBRE (sin cuota)                      --}}
    {{-- ================================================== --}}
    <div id="modalPagoLibre" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-credit-card mr-2"></i>Registrar Pago
                </h3>
                <button type="button" onclick="cerrarModalPagoLibre()" class="text-white hover:text-blue-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="formPagoLibre" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Monto <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="monto" id="montoLibre" step="0.01" min="0.01"
                               max="{{ $cuenta->saldo_pendiente }}" value="{{ $cuenta->saldo_pendiente }}"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha de pago <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="fecha_pago" value="{{ now()->format('Y-m-d') }}"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Método de pago <span class="text-red-500">*</span>
                        </label>
                        <select name="metodo_pago"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                            <option value="transferencia">Transferencia</option>
                            <option value="cheque">Cheque</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Sucursal (de dónde sale el dinero) <span class="text-red-500">*</span>
                        </label>
                        <select name="sucursal_id"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                            <option value="">Seleccionar...</option>
                            @foreach($sucursales as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Se registrará como egreso en la caja abierta de esa sucursal.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Referencia</label>
                    <input type="text" name="referencia"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                           placeholder="N° operación">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Voucher
                        <span class="text-gray-400 text-xs font-normal">(opcional)</span>
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-blue-400 transition cursor-pointer"
                         onclick="document.getElementById('voucherLibreInput').click()">
                        <div id="voucherLibrePreview" class="hidden mb-2">
                            <img id="voucherLibreImg" src="" alt="preview" class="max-h-28 mx-auto rounded-lg object-contain">
                        </div>
                        <div id="voucherLibrePlaceholder">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 mb-1"></i>
                            <p class="text-sm text-gray-500">Toca para seleccionar foto</p>
                        </div>
                        <p id="voucherLibreNombre" class="text-xs text-blue-600 mt-1 hidden"></p>
                    </div>
                    <input type="file" name="comprobante" id="voucherLibreInput" accept="image/*"
                           class="hidden" onchange="previewVoucherLibre(this)">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                              placeholder="Notas..."></textarea>
                </div>
            </form>
            <div class="px-6 pb-6 flex justify-end gap-3">
                <button type="button" onclick="cerrarModalPagoLibre()"
                        class="px-5 py-2.5 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 font-medium text-sm">
                    Cancelar
                </button>
                <button type="button" id="btnEnviarPagoLibre" onclick="enviarPagoLibre()"
                        class="px-5 py-2.5 bg-blue-700 text-white rounded-xl hover:bg-blue-800 font-medium text-sm flex items-center gap-2">
                    <i class="fas fa-check"></i> Registrar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal para ver voucher ampliado --}}
    <div id="modalVoucher" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"
         onclick="document.getElementById('modalVoucher').classList.add('hidden'); document.getElementById('modalVoucher').classList.remove('flex')">
        <div class="max-w-2xl w-full text-center" onclick="event.stopPropagation()">
            <img id="voucherAmpliado" src="" alt="Voucher de pago"
                 class="max-h-[80vh] mx-auto rounded-xl shadow-2xl object-contain">
            <p id="voucherAmpliadoNombre" class="text-white text-sm mt-3 opacity-70"></p>
            <a id="voucherAmpliadoLink" href="" target="_blank"
               class="inline-block mt-2 text-blue-300 hover:text-blue-100 text-sm">
                <i class="fas fa-external-link-alt mr-1"></i>Abrir en nueva pestaña
            </a>
            <button onclick="document.getElementById('modalVoucher').classList.add('hidden'); document.getElementById('modalVoucher').classList.remove('flex')"
                    class="block mx-auto mt-3 text-white/60 hover:text-white text-sm">
                <i class="fas fa-times mr-1"></i>Cerrar
            </button>
        </div>
    </div>

<script>
const CSRF = '{{ csrf_token() }}';

// ─── Generar cuotas ─────────────────────────────────────────────────────────
function generarCuotas() {
    const num = parseInt(document.getElementById('inputNumCuotas').value);
    if (!num || num < 1 || num > 48) {
        Swal.fire({ icon: 'warning', title: 'Número inválido', text: 'Ingresa entre 1 y 48 cuotas.', confirmButtonColor: '#1e3a8a' });
        return;
    }

    Swal.fire({
        title: `¿Generar ${num} cuotas?`,
        text: 'Las cuotas pendientes existentes serán reemplazadas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1d4ed8',
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch('{{ route("cuentas-por-pagar.generar-cuotas", $cuenta) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ num_cuotas: num }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: data.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#d33' });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#d33' }));
    });
}

// ─── Modal pagar cuota ───────────────────────────────────────────────────────
function abrirModalPagarCuota(cuotaId, monto, numCuota, totalCuotas) {
    document.getElementById('cuotaId').value = cuotaId;
    document.getElementById('montoCuota').value = monto;
    document.getElementById('labelCuota').textContent = `${numCuota}/${totalCuotas}`;
    // limpiar voucher
    document.getElementById('voucherInput').value = '';
    document.getElementById('voucherPreview').classList.add('hidden');
    document.getElementById('voucherPlaceholder').classList.remove('hidden');
    document.getElementById('voucherNombre').classList.add('hidden');

    const m = document.getElementById('modalPagarCuota');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function cerrarModalPagarCuota() {
    const m = document.getElementById('modalPagarCuota');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function enviarPagoCuota() {
    const cuotaId = document.getElementById('cuotaId').value;
    const form    = document.getElementById('formPagarCuota');
    const formData = new FormData(form);
    formData.append('_method', 'POST');

    const btn = document.getElementById('btnEnviarPagoCuota');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';

    fetch(`/cuentas-por-pagar/cuotas/${cuotaId}/pagar`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirmar Pago';
        if (data.success) {
            cerrarModalPagarCuota();
            Swal.fire({ icon: 'success', title: '¡Cuota pagada!', text: data.message, showConfirmButton: true })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#d33' });
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirmar Pago';
        Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#d33' });
    });
}

// ─── Modal pago libre ────────────────────────────────────────────────────────
function abrirModalPagoLibre() {
    document.getElementById('voucherLibreInput').value = '';
    document.getElementById('voucherLibrePreview').classList.add('hidden');
    document.getElementById('voucherLibrePlaceholder').classList.remove('hidden');
    document.getElementById('voucherLibreNombre').classList.add('hidden');
    const m = document.getElementById('modalPagoLibre');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function cerrarModalPagoLibre() {
    const m = document.getElementById('modalPagoLibre');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function enviarPagoLibre() {
    const form     = document.getElementById('formPagoLibre');
    const formData = new FormData(form);

    const btn = document.getElementById('btnEnviarPagoLibre');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';

    fetch('{{ route("cuentas-por-pagar.registrar-pago", $cuenta) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Registrar';
        if (data.success) {
            cerrarModalPagoLibre();
            Swal.fire({ icon: 'success', title: '¡Pago registrado!', text: data.message, showConfirmButton: true })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#d33' });
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Registrar';
        Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#d33' });
    });
}

// ─── Preview de voucher ──────────────────────────────────────────────────────
function previewVoucher(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('voucherImg').src = e.target.result;
        document.getElementById('voucherPreview').classList.remove('hidden');
        document.getElementById('voucherPlaceholder').classList.add('hidden');
        const nom = document.getElementById('voucherNombre');
        nom.textContent = file.name;
        nom.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}
function previewVoucherLibre(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('voucherLibreImg').src = e.target.result;
        document.getElementById('voucherLibrePreview').classList.remove('hidden');
        document.getElementById('voucherLibrePlaceholder').classList.add('hidden');
        const nom = document.getElementById('voucherLibreNombre');
        nom.textContent = file.name;
        nom.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

// ─── Ver voucher ampliado ────────────────────────────────────────────────────
function verVoucher(url, nombre) {
    document.getElementById('voucherAmpliado').src = url;
    document.getElementById('voucherAmpliadoNombre').textContent = nombre;
    document.getElementById('voucherAmpliadoLink').href = url;
    const m = document.getElementById('modalVoucher');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
</script>
</body>
</html>
