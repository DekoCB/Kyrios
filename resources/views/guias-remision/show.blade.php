<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía {{ $guia->numero_guia }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="{ showAnular: false }">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ route('guias-remision.index') }}" class="text-gray-400 hover:text-blue-600 transition">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-900 font-mono">{{ $guia->numero_guia }}</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $guia->estado_css }}">
                    {{ $guia->estado_label }}
                </span>
            </div>
            <p class="text-sm text-gray-500 ml-7">{{ $guia->motivo_label }} &middot; {{ $guia->fecha_traslado?->format('d/m/Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('guias-remision.pdf', $guia) }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">
                <i class="fas fa-file-pdf"></i> PDF
            </a>

            {{-- WhatsApp --}}
            @php
                $pdfUrl = route('guias-remision.pdf', $guia);
                $destinatario = $guia->destinatario_nombre;
                $empresaNombre = $empresa?->razon_social ?? config('app.name');
                $waMsg = "Guia de Remision {$guia->numero_guia}\n"
                       . "De: {$empresaNombre}\n"
                       . "Para: {$destinatario}\n"
                       . "Motivo: {$guia->motivo_label}\n"
                       . "Fecha: " . ($guia->fecha_traslado?->format('d/m/Y') ?? '-') . "\n"
                       . "Productos: {$guia->detalles->count()} item(s)\n\n"
                       . "Descargue el PDF aqui:\n{$pdfUrl}";

                $waPhone = null;
                if ($guia->cliente?->telefono) {
                    $tel = preg_replace('/\D/', '', $guia->cliente->telefono);
                    if (strlen($tel) === 9) $waPhone = '51' . $tel;
                    elseif (strlen($tel) >= 11) $waPhone = $tel;
                }
            @endphp

            @if($waPhone)
                <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode($waMsg) }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg transition">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            @else
                <div x-data='{
                    phone: "",
                    msg: @json($waMsg),
                    get digits() { return this.phone.replace(/\D/g, ""); },
                    get valid() { return this.digits.length === 9; },
                    send() {
                        if (!this.valid) return;
                        window.open("https://wa.me/51" + this.digits + "?text=" + encodeURIComponent(this.msg), "_blank");
                    }
                }' class="flex items-center gap-0.5">
                    <span class="inline-flex items-center gap-1.5 bg-green-500 text-white pl-3 pr-2 py-2 rounded-l-lg text-sm font-medium select-none">
                        <i class="fab fa-whatsapp"></i> WA
                    </span>
                    <input x-model="phone" type="tel" placeholder="9xx xxx xxx"
                           maxlength="9" @keydown.enter="send()"
                           class="border-y border-gray-300 px-2 py-2 text-sm w-28 focus:outline-none focus:ring-1 focus:ring-green-400">
                    <button @click="send()" :disabled="!valid"
                            class="bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white px-3 py-2 rounded-r-lg text-sm transition">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            @endif

            @if($guia->puedeConfirmar())
                @if($guia->estado === 'pendiente')
                <form action="{{ route('guias-remision.update-estado', $guia) }}" method="POST">
                    @csrf @method('PATCH')
                    <input type="hidden" name="estado" value="en_transito">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        <i class="fas fa-truck"></i> Marcar En Tránsito
                    </button>
                </form>
                @endif
                <form action="{{ route('guias-remision.update-estado', $guia) }}" method="POST">
                    @csrf @method('PATCH')
                    <input type="hidden" name="estado" value="entregada">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                        <i class="fas fa-check-circle"></i> Confirmar Entrega
                    </button>
                </form>
            @endif

            @if($guia->puedeAnular())
            <button @click="showAnular = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-lg transition">
                <i class="fas fa-ban"></i> Anular
            </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Col izq: datos --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Origen → Destino --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Recorrido</h3>
                <div class="flex items-center gap-4">
                    <div class="flex-1 bg-orange-50 border border-orange-200 rounded-xl p-3 text-center">
                        <i class="fas fa-warehouse text-orange-500 text-lg mb-1 block"></i>
                        <p class="text-xs text-gray-500 mb-0.5">Origen</p>
                        <p class="font-semibold text-sm text-gray-800">{{ $guia->almacen?->nombre ?? '—' }}</p>
                        @if($guia->direccion_partida)
                            <p class="text-[10px] text-gray-400 mt-1">{{ $guia->direccion_partida }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 text-gray-300 text-xl"><i class="fas fa-arrow-right"></i></div>
                    <div class="flex-1 bg-teal-50 border border-teal-200 rounded-xl p-3 text-center">
                        <i class="fas fa-map-marker-alt text-teal-500 text-lg mb-1 block"></i>
                        <p class="text-xs text-gray-500 mb-0.5">{{ $guia->tipo_destino_label }}</p>
                        <p class="font-semibold text-sm text-gray-800">{{ $guia->destinatario_nombre }}</p>
                        @if($guia->direccion_llegada)
                            <p class="text-[10px] text-gray-400 mt-1">{{ $guia->direccion_llegada }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Productos --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Productos</h3>
                    <span class="text-xs text-gray-400">{{ $guia->detalles->count() }} ítem(s)</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Producto</th>
                            <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">Cant.</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Descripción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($guia->detalles as $det)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $det->producto?->nombre }}</p>
                                @if($det->variante)
                                    <p class="text-xs text-gray-500">{{ $det->variante->nombre_completo }}</p>
                                @endif
                                <p class="text-[10px] text-gray-400 font-mono">{{ $det->producto?->codigo }}</p>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ $det->cantidad }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $det->descripcion ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">Sin productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Col der: detalles guía --}}
        <div class="space-y-5">

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Datos de la Guía</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Motivo</dt>
                        <dd class="font-medium text-gray-800">{{ $guia->motivo_label }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Modalidad</dt>
                        <dd class="font-medium text-gray-800">{{ $guia->modalidad_label }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Fecha traslado</dt>
                        <dd class="font-medium text-gray-800">{{ $guia->fecha_traslado?->format('d/m/Y') }}</dd>
                    </div>
                    @if($guia->peso_total)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Peso total</dt>
                        <dd class="font-medium text-gray-800">{{ number_format($guia->peso_total, 2) }} kg</dd>
                    </div>
                    @endif
                    @if($guia->bultos)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Bultos</dt>
                        <dd class="font-medium text-gray-800">{{ $guia->bultos }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            @if($guia->conductor_nombre || $guia->transportista_nombre)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-2">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Transporte</h3>
                @if($guia->transportista_nombre)
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Transportista</p>
                    <p class="text-sm font-medium text-gray-800">{{ $guia->transportista_nombre }}</p>
                    @if($guia->transportista_doc) <p class="text-xs text-gray-500 font-mono">{{ $guia->transportista_tipo_doc }}: {{ $guia->transportista_doc }}</p> @endif
                </div>
                @endif
                @if($guia->conductor_nombre)
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Conductor</p>
                    <p class="text-sm font-medium text-gray-800">{{ $guia->conductor_nombre }}</p>
                    <p class="text-xs text-gray-500 font-mono">
                        DNI: {{ $guia->conductor_dni }}
                        @if($guia->conductor_licencia) · Lic: {{ $guia->conductor_licencia }} @endif
                    </p>
                    @if($guia->placa_vehiculo)
                        <span class="inline-block mt-1 bg-gray-100 text-gray-700 font-mono font-bold text-xs px-2 py-0.5 rounded">
                            {{ $guia->placa_vehiculo }}
                        </span>
                    @endif
                </div>
                @endif
            </div>
            @endif

            {{-- SUNAT Info --}}
            <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-5 space-y-3">
                <h3 class="text-sm font-bold text-amber-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <i class="fas fa-landmark"></i> SUNAT
                </h3>

                {{-- Estado SUNAT --}}
                <div class="flex items-center justify-between px-3 py-2.5 rounded-lg {{ $guia->sunat_estado === 'aceptado' ? 'bg-green-50 border border-green-200' : ($guia->sunat_estado === 'error' || $guia->sunat_estado === 'rechazado' ? 'bg-red-50 border border-red-200' : ($guia->sunat_estado === 'enviado' ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200')) }}">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {{ $guia->sunat_estado_css }}">
                        @if($guia->sunat_estado === 'aceptado')
                            <i class="fas fa-check-circle"></i>
                        @elseif($guia->sunat_estado === 'enviado')
                            <i class="fas fa-clock"></i>
                        @elseif($guia->sunat_estado === 'rechazado' || $guia->sunat_estado === 'error')
                            <i class="fas fa-times-circle"></i>
                        @else
                            <i class="fas fa-minus-circle"></i>
                        @endif
                        {{ $guia->sunat_estado_label }}
                    </span>
                </div>

                @if($guia->sunat_descripcion)
                <div class="px-3 py-2 bg-gray-50 rounded-lg">
                    <p class="text-[11px] text-gray-600">{{ $guia->sunat_descripcion }}</p>
                </div>
                @endif

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">RUC Emisor</dt>
                        <dd class="font-mono font-medium text-gray-800">{{ $empresa?->ruc ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">N° Guía</dt>
                        <dd class="font-mono font-bold text-amber-700">{{ $guia->numero_guia }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Tipo Doc.</dt>
                        <dd class="text-gray-800">09 - Guía Remisión Remitente</dd>
                    </div>
                    @if($guia->sunat_ticket)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ticket</dt>
                        <dd class="font-mono text-xs text-gray-700">{{ $guia->sunat_ticket }}</dd>
                    </div>
                    @endif
                    @if($guia->sunat_enviado_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Enviado</dt>
                        <dd class="text-xs text-gray-700">{{ $guia->sunat_enviado_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @endif
                </div>

                {{-- Acciones SUNAT --}}
                <div class="space-y-2 pt-1">
                    @if($guia->puedeEnviarSunat())
                    <form action="{{ route('guias-remision.enviar-sunat', $guia) }}" method="POST">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('¿Enviar esta guía a SUNAT?')"
                                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition">
                            <i class="fas fa-paper-plane"></i>
                            {{ in_array($guia->sunat_estado, ['error', 'rechazado']) ? 'Reintentar envío a SUNAT' : 'Enviar a SUNAT' }}
                        </button>
                    </form>
                    @endif

                    @if($guia->sunat_estado === 'enviado')
                    <form action="{{ route('guias-remision.consultar-sunat', $guia) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-lg transition">
                            <i class="fas fa-sync-alt"></i> Consultar estado
                        </button>
                    </form>
                    @endif

                    <a href="https://e-factura.sunat.gob.pe/ol-ti-itconsvalid/allowOp.htm" target="_blank" rel="noopener noreferrer"
                       class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-sm font-semibold rounded-lg border border-amber-200 transition">
                        <i class="fas fa-external-link-alt text-xs"></i> Verificar en portal SUNAT
                    </a>
                </div>

                <p class="text-[10px] text-gray-400 text-center">
                    RUC {{ $empresa?->ruc ?? '' }} · Tipo 09 · {{ $guia->numero_guia }}
                </p>
            </div>

        </div>
    </div>

    {{-- Modal Anular --}}
    <div x-show="showAnular" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAnular = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Anular Guía</h3>
            <p class="text-sm text-gray-500 mb-4">El stock se revertirá automáticamente al almacén origen.</p>
            <form action="{{ route('guias-remision.update-estado', $guia) }}" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="estado" value="anulada">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Motivo de anulación *</label>
                    <textarea name="motivo_anulacion" required rows="3"
                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 resize-none"
                              placeholder="Describe el motivo..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="showAnular = false"
                            class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                        Confirmar Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>
