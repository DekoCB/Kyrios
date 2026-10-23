<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Facturación Electrónica</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8">
    <x-header title="Configuración de Facturación" subtitle="Parámetros del sistema de facturación electrónica" />

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('facturacion.index') }}" class="hover:text-blue-600 transition">Facturación Electrónica</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-800 font-medium">Configuración</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Panel principal --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Datos del emisor --}}
            @php $empresa = \App\Models\Empresa::instancia(); @endphp
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-building text-blue-600"></i> Datos del Emisor
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Razón Social</label>
                        <p class="font-semibold text-gray-800">{{ $empresa?->razon_social ?? 'No configurado' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">RUC</label>
                        <p class="font-mono font-semibold text-gray-800">{{ $empresa?->ruc ?? 'No configurado' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nombre Comercial</label>
                        <p class="text-gray-700">{{ $empresa?->nombre_comercial ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Dirección Fiscal</label>
                        <p class="text-gray-700">{{ $empresa?->direccion ?? 'No configurado' }}</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <a href="{{ route('admin.empresa.edit') }}"
                       class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-edit mr-1"></i>Editar datos de la empresa
                    </a>
                </div>
            </div>

            {{-- Series por sucursal --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list-ol text-blue-600"></i> Series por Sucursal
                    </h3>
                    <a href="{{ route('facturacion.series') }}"
                       class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-cog mr-1"></i>Gestionar series
                    </a>
                </div>

                @forelse($sucursales as $sucursal)
                <div class="mb-6 last:mb-0">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-store text-gray-400"></i> {{ $sucursal->nombre }}
                    </h4>
                    @if($sucursal->series && $sucursal->series->count())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($sucursal->series as $serie)
                            @php
                                $tipoCodes = ['01'=>'border-blue-200 bg-blue-50','03'=>'border-purple-200 bg-purple-50','07'=>'border-orange-200 bg-orange-50','08'=>'border-red-200 bg-red-50','09'=>'border-teal-200 bg-teal-50','NE'=>'border-gray-200 bg-gray-50'];
                                $serieCss = $tipoCodes[$serie->tipo_comprobante] ?? 'border-gray-200 bg-gray-50';
                            @endphp
                            <div class="border {{ $serieCss }} rounded-lg p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-mono font-bold text-gray-800">{{ $serie->serie }}</p>
                                    <p class="text-xs text-gray-500">{{ $serie->tipo_nombre }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Correlativo</p>
                                    <p class="font-mono text-sm font-semibold text-gray-700">{{ str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT) }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">No hay series configuradas para esta sucursal.
                            <a href="{{ route('facturacion.series') }}" class="text-blue-600 hover:underline">Agregar series</a>
                        </p>
                    @endif
                </div>
                @empty
                    <p class="text-gray-400 text-sm">No hay sucursales configuradas.</p>
                @endforelse
            </div>
        </div>

        {{-- Panel lateral --}}
        <div class="space-y-6">

            {{-- Estado de conexión SUNAT --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-plug text-blue-600"></i> Conexión SUNAT
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-circle text-amber-400 text-xs"></i>
                            <span class="font-medium text-amber-700">Modo Simulación</span>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">Activo</span>
                    </div>
                    <div class="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">
                        <i class="fas fa-info-circle mr-1 text-blue-400"></i>
                        La integración real con SUNAT (OSE/SOL) se activa configurando las credenciales y el certificado digital.
                    </div>
                </div>
            </div>

            {{-- Formatos disponibles --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-print text-blue-600"></i> Formatos PDF
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                        <div class="w-8 h-10 bg-red-100 border border-red-200 rounded flex items-center justify-center shrink-0">
                            <i class="fas fa-file-pdf text-red-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Formato A4</p>
                            <p class="text-xs text-gray-500">Facturas y notas de crédito</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                        <div class="w-8 h-10 bg-blue-100 border border-blue-200 rounded flex items-center justify-center shrink-0">
                            <i class="fas fa-receipt text-blue-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Ticket 80mm</p>
                            <p class="text-xs text-gray-500">Boletas de venta</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tipos de comprobante --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-file-invoice text-blue-600"></i> Tipos de Comprobante
                </h3>
                <div class="space-y-2">
                    @foreach(\App\Models\SerieComprobante::TIPOS as $codigo => $info)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded w-10 text-center">{{ $codigo }}</span>
                        <span class="text-gray-700">{{ $info['nombre'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
