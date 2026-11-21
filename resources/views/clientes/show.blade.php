<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cliente->nombre }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$cliente->nombre" :subtitle="$cliente->es_prospecto ? 'Prospecto — aún sin documento' : $cliente->tipo_documento . ' ' . $cliente->numero_documento" />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            @if($canEdit)
                <a href="{{ route('clientes.edit', $cliente) }}" class="inline-flex items-center gap-2 border border-yellow-300 bg-yellow-50 text-yellow-800 hover:bg-yellow-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-edit"></i> Editar</a>
                <a href="{{ route('cotizaciones.create', ['cliente_id' => $cliente->id]) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm"><i class="fas fa-file-contract"></i> Nueva cotización</a>
            @endif
            @if($canDelete)
                <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="inline ml-auto" data-confirm="{{ $soloSolicita ? '¿Solicitar la eliminación de este cliente? El administrador debe aprobarla.' : '¿Eliminar este cliente?' }}">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center gap-2 border border-red-200 bg-white text-red-700 hover:bg-red-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-trash"></i> {{ $soloSolicita ? 'Solicitar eliminación' : 'Eliminar' }}</button>
                </form>
            @endif
        </div>

        {{-- Resumen comercial --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Compras</p>
                <p class="text-2xl font-bold text-gray-900">{{ $resumen['ventas'] }}</p>
                <p class="text-xs text-gray-500">{{ $resumen['ultima_compra'] ? 'Última: ' . \Illuminate\Support\Carbon::parse($resumen['ultima_compra'])->format('d/m/Y') : 'Sin compras aún' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total comprado</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['total_comprado'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Ticket promedio</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['ticket_promedio'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Cotizaciones vigentes</p>
                <p class="text-2xl font-bold text-gray-900">{{ $resumen['cotizaciones_vigentes'] }}</p>
                <p class="text-xs text-gray-500">S/ {{ number_format($resumen['monto_cotizado'], 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Datos --}}
            <div class="bg-white rounded-xl shadow-sm p-5 text-sm self-start">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Datos del cliente</h3>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $cliente->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ ucfirst($cliente->estado) }}</span>
                </div>
                <dl class="space-y-2">
                    @if($cliente->es_prospecto)
                        <div><dt class="text-xs text-gray-400 uppercase">ID de cliente</dt><dd class="font-mono font-semibold text-blue-700">{{ $cliente->codigo ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400 uppercase">Documento</dt><dd><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-rose-100 text-rose-700">Prospecto</span> <span class="text-xs text-gray-500">completar al editar</span></dd></div>
                    @else
                        @php $fuente = $cliente->tipo_documento === 'RUC' ? 'SUNAT' : 'RENIEC'; @endphp
                        <div><dt class="text-xs text-gray-400 uppercase">ID de cliente</dt><dd class="font-mono font-semibold text-blue-700">{{ $cliente->codigo }}</dd></div>
                        @if($cliente->ruc && $cliente->tipo_documento !== 'RUC')<div><dt class="text-xs text-gray-400 uppercase">RUC</dt><dd class="font-mono">{{ $cliente->ruc }}</dd></div>@endif
                        @if($cliente->dni && $cliente->tipo_documento !== 'DNI')<div><dt class="text-xs text-gray-400 uppercase">DNI</dt><dd class="font-mono">{{ $cliente->dni }}</dd></div>@endif
                        <div><dt class="text-xs text-gray-400 uppercase">{{ $cliente->tipo_documento }}</dt><dd class="font-mono">{{ $cliente->numero_documento }}
                            @if($cliente->verificacion === 'ok')<span class="ml-1 text-xs text-green-600 font-sans" title="Verificado en {{ $fuente }} el {{ $cliente->verificado_at?->format('d/m/Y') }}"><i class="fas fa-check-circle"></i> verificado</span>
                            @elseif($cliente->verificacion === 'discrepancia')<span class="ml-1 text-xs text-amber-600 font-sans" title="El nombre difiere del registro oficial; revise las notas"><i class="fas fa-exclamation-triangle"></i> revisar nombre</span>
                            @elseif($cliente->verificacion === 'no_encontrado')<span class="ml-1 text-xs text-red-600 font-sans" title="No figura en {{ $fuente }}"><i class="fas fa-times-circle"></i> no encontrado</span>@endif
                        </dd></div>
                    @endif
                    @if($cliente->etiquetas_difusion)
                        <div><dt class="text-xs text-gray-400 uppercase">Listas de difusión</dt>
                            <dd class="flex flex-wrap gap-1 mt-0.5">
                                @foreach($cliente->etiquetas_difusion as $etiqueta)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800"><i class="fab fa-whatsapp mr-1"></i>{{ $etiqueta }}</span>
                                @endforeach
                            </dd></div>
                    @endif
                    @if($cliente->segmento)<div><dt class="text-xs text-gray-400 uppercase">Segmento</dt><dd>{{ \App\Models\Cliente::SEGMENTOS[$cliente->segmento] ?? $cliente->segmento }}@if($cliente->especialidad) · {{ $cliente->especialidad }}@endif</dd></div>@endif
                    @if($cliente->clasificacion || $cliente->como_ingreso || $cliente->preferencia_contacto)<div><dt class="text-xs text-gray-400 uppercase">Historial</dt><dd>{{ collect([$cliente->clasificacion, $cliente->como_ingreso ? 'ingresó por ' . $cliente->como_ingreso : null, $cliente->preferencia_contacto ? 'prefiere ' . $cliente->preferencia_contacto : null])->filter()->implode(' · ') }}</dd></div>@endif
                    @if($cliente->empresa)<div><dt class="text-xs text-gray-400 uppercase">Empresa</dt><dd>{{ $cliente->empresa }}@if($cliente->tipo_empresa) <span class="text-gray-500">({{ $cliente->tipo_empresa }})</span>@endif @if($cliente->cargo) <span class="text-gray-500">— {{ $cliente->cargo }}</span>@endif</dd></div>@elseif($cliente->cargo)<div><dt class="text-xs text-gray-400 uppercase">Cargo</dt><dd>{{ $cliente->cargo }}</dd></div>@endif
                    @if($cliente->contacto)<div><dt class="text-xs text-gray-400 uppercase">Contacto</dt><dd>{{ $cliente->contacto }}</dd></div>@endif
                    @if($cliente->telefono)<div><dt class="text-xs text-gray-400 uppercase">Teléfono</dt><dd><i class="fas fa-phone text-xs mr-1"></i>{{ $cliente->telefono }}</dd></div>@endif
                    @if($cliente->telefono_fijo)<div><dt class="text-xs text-gray-400 uppercase">Teléfono fijo</dt><dd>{{ $cliente->telefono_fijo }}@if($cliente->anexo) <span class="text-gray-500">anexo {{ $cliente->anexo }}</span>@endif</dd></div>@endif
                    @if($cliente->email)<div><dt class="text-xs text-gray-400 uppercase">Correo</dt><dd class="break-all">{{ $cliente->email }}</dd></div>@endif
                    @if($cliente->email_empresa)<div><dt class="text-xs text-gray-400 uppercase">Correo de la empresa</dt><dd class="break-all">{{ $cliente->email_empresa }}</dd></div>@endif
                    @if($cliente->redes_sociales)<div><dt class="text-xs text-gray-400 uppercase">Redes</dt><dd class="break-all">{{ $cliente->redes_sociales }}</dd></div>@endif
                    @if($cliente->fecha_cumpleanos)<div><dt class="text-xs text-gray-400 uppercase">Cumpleaños</dt><dd><i class="fas fa-birthday-cake text-xs mr-1"></i>{{ $cliente->fecha_cumpleanos->format('d/m') }}</dd></div>@endif
                    @if($cliente->comision !== null)<div><dt class="text-xs text-gray-400 uppercase">Comisión pactada</dt><dd>{{ rtrim(rtrim(number_format((float) $cliente->comision, 2), '0'), '.') }}%</dd></div>@endif
                    @if($cliente->preferencias)<div><dt class="text-xs text-gray-400 uppercase">Preferencias</dt><dd>{{ $cliente->preferencias }}</dd></div>@endif
                    @if($cliente->asesor || $cliente->origen)<div><dt class="text-xs text-gray-400 uppercase">Atención</dt><dd>{{ collect([$cliente->asesor, $cliente->origen ? 'vía ' . $cliente->origen : null])->filter()->implode(' · ') }}</dd></div>@endif
                    @if($cliente->direccion)
                        <div><dt class="text-xs text-gray-400 uppercase">Dirección</dt>
                            <dd>{{ $cliente->direccion }}@if($cliente->distrito || $cliente->provincia || $cliente->departamento)<br><span class="text-gray-500">{{ collect([$cliente->distrito, $cliente->provincia, $cliente->departamento])->filter()->implode(', ') }}</span>@endif</dd></div>
                    @endif
                </dl>
                @if($cliente->notas)
                    <div class="mt-4 bg-amber-50 border border-amber-100 rounded-lg p-3 text-amber-900 whitespace-pre-line"><p class="text-xs font-semibold uppercase mb-1">Notas</p>{{ $cliente->notas }}</div>
                @endif
                <p class="text-xs text-gray-400 mt-4">Registrado el {{ $cliente->created_at?->format('d/m/Y') }}</p>
            </div>

            <div class="xl:col-span-2 space-y-6">
                {{-- Ventas --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-900">Últimas ventas</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                                <th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($ventas as $v)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <a href="{{ route('ventas.show', $v) }}" class="font-mono font-medium text-blue-700 hover:underline">{{ $v->numero_documento ?? $v->codigo }}</a>
                                            <span class="text-xs text-gray-400 ml-1">{{ ucfirst(str_replace('_', ' ', $v->tipo_comprobante)) }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500">{{ $v->fecha?->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ ucfirst($v->estado_pago) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($v->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Todavía no tiene ventas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Cotizaciones --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-900">Cotizaciones</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                                <th class="px-4 py-2 text-left">Número</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($cotizaciones as $c)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2"><a href="{{ route('cotizaciones.show', $c) }}" class="font-mono font-medium text-blue-700 hover:underline">{{ $c->codigo }}</a></td>
                                        <td class="px-4 py-2 text-gray-500">{{ $c->fecha->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2">@include('cotizaciones.partials.badge', ['estado' => $c->estado_visible])</td>
                                        <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($c->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin cotizaciones</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Proyectos --}}
                @php
                    $inp = 'w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-yellow-500 focus:ring-yellow-500';
                    $proyectosJs = $proyectos->mapWithKeys(fn ($p) => [$p->id => [
                        'codigo' => $p->codigo, 'nombre' => $p->nombre, 'persona_a_cargo' => $p->persona_a_cargo, 'prioridad' => $p->prioridad,
                        'fecha_recepcion' => $p->fecha_recepcion?->toDateString(), 'fecha_entrega_aprox' => $p->fecha_entrega_aprox?->toDateString(), 'fecha_entrega_real' => $p->fecha_entrega_real?->toDateString(),
                        'moneda' => $p->moneda ?: 'USD', 'monto_presupuestado' => $p->monto_presupuestado, 'monto_vendido' => $p->monto_vendido, 'resultado' => $p->resultado, 'seguimiento' => $p->seguimiento,
                    ]]);
                    $seguimientosJs = $seguimientos->mapWithKeys(fn ($x) => [$x->id => [
                        'fecha' => $x->fecha?->toDateString(), 'atendido_por' => $x->atendido_por, 'tipo' => $x->tipo, 'situacion' => $x->situacion, 'busca' => $x->busca,
                        'ultimo_estado' => $x->ultimo_estado, 'moneda' => $x->moneda ?: 'USD', 'monto_presupuestado' => $x->monto_presupuestado, 'monto_comprado' => $x->monto_comprado,
                        'probabilidad' => $x->probabilidad, 'medio_contacto' => $x->medio_contacto, 'observaciones' => $x->observaciones,
                    ]]);
                @endphp
                <div id="proyectos" class="bg-white rounded-xl shadow-sm overflow-hidden" x-data="{
                    abierto: false, editando: null, proyectos: @js($proyectosJs),
                    vacio: { codigo: '', nombre: '', persona_a_cargo: '', prioridad: '', fecha_recepcion: '', fecha_entrega_aprox: '', fecha_entrega_real: '', moneda: 'USD', monto_presupuestado: '', monto_vendido: '', resultado: '', seguimiento: '' },
                    f: {},
                    nuevo() { this.editando = null; this.f = { ...this.vacio }; this.abierto = true; },
                    editar(id) { this.editando = id; this.f = { ...this.vacio, ...Object.fromEntries(Object.entries(this.proyectos[id]).map(([k, v]) => [k, v ?? ''])) }; this.abierto = true; $nextTick(() => $refs.formProyecto.scrollIntoView({ behavior: 'smooth', block: 'center' })); }
                }">
                    <div class="px-5 py-4 border-b flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">Proyectos <span class="ml-1 text-xs font-normal text-gray-400">{{ $proyectos->count() }}</span></h3>
                        @if($canEdit)<button type="button" @click="nuevo()" class="text-sm font-semibold text-yellow-700 hover:text-yellow-800"><i class="fas fa-plus mr-1"></i>Agregar proyecto</button>@endif
                    </div>

                    @if($canEdit)
                    <form x-ref="formProyecto" x-show="abierto" x-cloak method="POST" :action="editando ? '{{ url('clientes/' . $cliente->id . '/proyectos') }}/' + editando : '{{ route('clientes.proyectos.store', $cliente) }}'" class="px-5 py-4 border-b bg-gray-50 grid grid-cols-1 md:grid-cols-3 gap-3">
                        @csrf
                        <template x-if="editando"><input type="hidden" name="_method" value="PUT"></template>
                        <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Nombre del proyecto *</label><input name="nombre" x-model="f.nombre" required maxlength="190" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Código (IL-…)</label><input name="codigo" x-model="f.codigo" maxlength="40" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Persona a cargo</label><input name="persona_a_cargo" x-model="f.persona_a_cargo" maxlength="80" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Prioridad</label><select name="prioridad" x-model="f.prioridad" class="{{ $inp }}"><option value="">—</option><option>Alta</option><option>Media</option><option>Baja</option></select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Resultado</label><select name="resultado" x-model="f.resultado" class="{{ $inp }}"><option value="">—</option>@foreach(array_keys(\App\Models\ClienteProyecto::RESULTADOS) as $r)<option>{{ $r }}</option>@endforeach</select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Recepción</label><input type="date" name="fecha_recepcion" x-model="f.fecha_recepcion" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Entrega aprox.</label><input type="date" name="fecha_entrega_aprox" x-model="f.fecha_entrega_aprox" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Entrega real</label><input type="date" name="fecha_entrega_real" x-model="f.fecha_entrega_real" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Moneda</label><select name="moneda" x-model="f.moneda" class="{{ $inp }}"><option value="USD">Dólares (US$)</option><option value="PEN">Soles (S/)</option></select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Monto presupuestado</label><input type="number" step="0.01" min="0" name="monto_presupuestado" x-model="f.monto_presupuestado" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Monto vendido</label><input type="number" step="0.01" min="0" name="monto_vendido" x-model="f.monto_vendido" class="{{ $inp }}"></div>
                        <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-600 mb-1">Seguimiento / observaciones</label><textarea name="seguimiento" x-model="f.seguimiento" rows="2" maxlength="2000" class="{{ $inp }}"></textarea></div>
                        <div class="md:col-span-3 flex gap-2 justify-end">
                            <button type="button" @click="abierto = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</button>
                            <button class="px-4 py-2 text-sm font-semibold rounded-lg bg-yellow-400 hover:bg-yellow-500 text-gray-900" x-text="editando ? 'Guardar cambios' : 'Agregar proyecto'"></button>
                        </div>
                    </form>
                    @endif

                    @if($proyectos->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-gray-400">Sin proyectos</p>
                    @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                                <th class="px-4 py-2 text-left">Proyecto</th><th class="px-4 py-2 text-left">A cargo</th><th class="px-4 py-2 text-left">Recibido</th><th class="px-4 py-2 text-left">Entrega</th><th class="px-4 py-2 text-right">Presupuesto</th><th class="px-4 py-2 text-right">Vendido</th><th class="px-4 py-2 text-left">Resultado</th>@if($canEdit)<th class="px-4 py-2"></th>@endif
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($proyectos as $p)
                                    <tr class="hover:bg-gray-50 align-top">
                                        <td class="px-4 py-2"><span class="font-medium text-gray-900">{{ $p->nombre ?? 'Sin nombre' }}</span>@if($p->codigo)<span class="block text-xs font-mono text-gray-400">{{ $p->codigo }}</span>@endif @if($p->seguimiento)<span class="block text-xs text-gray-500 mt-0.5">{{ $p->seguimiento }}</span>@endif</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $p->persona_a_cargo ?? '—' }}@if($p->prioridad)<span class="block text-xs text-gray-400">Prioridad {{ strtolower($p->prioridad) }}</span>@endif</td>
                                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $p->fecha_recepcion?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ ($p->fecha_entrega_real ?? $p->fecha_entrega_aprox)?->format('d/m/Y') ?? '—' }}@if(!$p->fecha_entrega_real && $p->fecha_entrega_aprox)<span class="block text-xs text-gray-400">aprox.</span>@endif</td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $p->monto_presupuestado !== null ? $p->simbolo . ' ' . number_format($p->monto_presupuestado, 2) : '—' }}</td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap font-semibold">{{ $p->monto_vendido !== null ? $p->simbolo . ' ' . number_format($p->monto_vendido, 2) : '—' }}</td>
                                        <td class="px-4 py-2">@if($p->resultado)<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\ClienteProyecto::RESULTADOS[$p->resultado] ?? 'bg-gray-100 text-gray-700' }}">{{ $p->resultado }}</span>@else — @endif</td>
                                        @if($canEdit)
                                        <td class="px-4 py-2 whitespace-nowrap text-right">
                                            <button type="button" @click="editar({{ $p->id }})" class="text-gray-400 hover:text-yellow-600" title="Editar"><i class="fas fa-pen"></i></button>
                                            <form method="POST" action="{{ route('clientes.proyectos.destroy', [$cliente, $p]) }}" class="inline ml-2" data-confirm="¿Eliminar este proyecto?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                                        </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- Seguimiento comercial --}}
                <div id="seguimiento" class="bg-white rounded-xl shadow-sm overflow-hidden" x-data="{
                    abierto: false, editando: null, seguimientos: @js($seguimientosJs),
                    vacio: { fecha: '{{ now()->toDateString() }}', tipo: '', situacion: '', busca: '', ultimo_estado: '', moneda: 'USD', monto_presupuestado: '', monto_comprado: '', probabilidad: '', medio_contacto: '', atendido_por: @js(auth()->user()->name), observaciones: '' },
                    f: {},
                    nuevo() { this.editando = null; this.f = { ...this.vacio }; this.abierto = true; },
                    editar(id) { this.editando = id; this.f = { ...this.vacio, ...Object.fromEntries(Object.entries(this.seguimientos[id]).map(([k, v]) => [k, v ?? ''])) }; this.abierto = true; $nextTick(() => $refs.formSeguimiento.scrollIntoView({ behavior: 'smooth', block: 'center' })); }
                }">
                    <div class="px-5 py-4 border-b flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">Seguimiento comercial <span class="ml-1 text-xs font-normal text-gray-400">{{ $seguimientos->count() }}</span></h3>
                        @if($canEdit)<button type="button" @click="nuevo()" class="text-sm font-semibold text-yellow-700 hover:text-yellow-800"><i class="fas fa-plus mr-1"></i>Agregar seguimiento</button>@endif
                    </div>

                    @if($canEdit)
                    <form x-ref="formSeguimiento" x-show="abierto" x-cloak method="POST" :action="editando ? '{{ url('clientes/' . $cliente->id . '/seguimientos') }}/' + editando : '{{ route('clientes.seguimientos.store', $cliente) }}'" class="px-5 py-4 border-b bg-gray-50 grid grid-cols-1 md:grid-cols-3 gap-3">
                        @csrf
                        <template x-if="editando"><input type="hidden" name="_method" value="PUT"></template>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Fecha</label><input type="date" name="fecha" x-model="f.fecha" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label><input name="tipo" x-model="f.tipo" list="seg-tipos" maxlength="40" class="{{ $inp }}" placeholder="Proyecto, Puntual - Decorativa…"><datalist id="seg-tipos"><option value="Proyecto"><option value="Puntual - Decorativa"><option value="Puntual - Técnica"><option value="Casacor"></datalist></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Situación</label><input name="situacion" x-model="f.situacion" list="seg-situaciones" maxlength="60" class="{{ $inp }}" placeholder="En seguimiento…"><datalist id="seg-situaciones"><option value="En seguimiento"><option value="En proceso"><option value="Ganado"><option value="Perdido"></datalist></div>
                        <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-600 mb-1">Qué busca</label><input name="busca" x-model="f.busca" maxlength="255" class="{{ $inp }}" placeholder="Luminarias decorativas, cinta LED…"></div>
                        <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-600 mb-1">Último estado (qué pasó)</label><textarea name="ultimo_estado" x-model="f.ultimo_estado" rows="2" maxlength="2000" class="{{ $inp }}"></textarea></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Moneda</label><select name="moneda" x-model="f.moneda" class="{{ $inp }}"><option value="USD">Dólares (US$)</option><option value="PEN">Soles (S/)</option></select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Monto presupuestado</label><input type="number" step="0.01" min="0" name="monto_presupuestado" x-model="f.monto_presupuestado" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Monto comprado</label><input type="number" step="0.01" min="0" name="monto_comprado" x-model="f.monto_comprado" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Probabilidad de venta (%)</label><input type="number" min="0" max="100" name="probabilidad" x-model="f.probabilidad" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Llegó por / referido por</label><input name="medio_contacto" x-model="f.medio_contacto" maxlength="120" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Atendió</label><input name="atendido_por" x-model="f.atendido_por" maxlength="80" class="{{ $inp }}"></div>
                        <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label><textarea name="observaciones" x-model="f.observaciones" rows="2" maxlength="2000" class="{{ $inp }}"></textarea></div>
                        <div class="md:col-span-3 flex gap-2 justify-end">
                            <button type="button" @click="abierto = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</button>
                            <button class="px-4 py-2 text-sm font-semibold rounded-lg bg-yellow-400 hover:bg-yellow-500 text-gray-900" x-text="editando ? 'Guardar cambios' : 'Agregar seguimiento'"></button>
                        </div>
                    </form>
                    @endif

                    @if($seguimientos->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-gray-400">Sin seguimiento comercial</p>
                    @else
                    <div class="divide-y divide-gray-100 text-sm">
                        @foreach($seguimientos as $s)
                            <div class="px-5 py-3">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    @if($s->tipo)<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">{{ $s->tipo }}</span>@endif
                                    @if($s->situacion)<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">{{ $s->situacion }}</span>@endif
                                    @if($s->busca)<span class="text-gray-800">{{ $s->busca }}</span>@endif
                                    <span class="ml-auto text-xs text-gray-400">{{ collect([$s->atendido_por ? 'Atendió ' . $s->atendido_por : null, $s->fecha?->format('d/m/Y')])->filter()->implode(' · ') }}</span>
                                    @if($canEdit)<button type="button" @click="editar({{ $s->id }})" class="text-gray-300 hover:text-yellow-600" title="Editar"><i class="fas fa-pen text-xs"></i></button><form method="POST" action="{{ route('clientes.seguimientos.destroy', [$cliente, $s]) }}" class="inline" data-confirm="¿Eliminar este seguimiento?">@csrf @method('DELETE')<button class="text-gray-300 hover:text-red-600" title="Eliminar"><i class="fas fa-trash text-xs"></i></button></form>@endif
                                </div>
                                @if($s->ultimo_estado)<p class="text-gray-600 mt-1">{{ $s->ultimo_estado }}</p>@endif
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ collect([
                                        $s->monto_presupuestado !== null ? 'Presupuestado ' . $s->simbolo . ' ' . number_format($s->monto_presupuestado, 2) : null,
                                        $s->monto_comprado !== null ? 'Comprado ' . $s->simbolo . ' ' . number_format($s->monto_comprado, 2) : null,
                                        $s->probabilidad !== null ? 'Probabilidad de venta ' . $s->probabilidad . '%' : null,
                                        $s->resumen_visita ? 'Visita: ' . $s->resumen_visita : null,
                                        $s->medio_contacto ? 'Llegó por: ' . $s->medio_contacto : null,
                                    ])->filter()->implode(' · ') }}
                                </p>
                                @if($s->observaciones)<p class="text-xs text-gray-400 mt-0.5">{{ $s->observaciones }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
