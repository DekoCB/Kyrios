<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva Devolución - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Registrar Devolución"
            subtitle="Devuelve productos al inventario mediante una guía de remisión"
        />

        <div class="flex flex-wrap gap-3 mb-6">
            <a href="{{ route('devoluciones.index') }}" class="text-sm text-gray-500 hover:text-blue-700 flex items-center gap-1">
                <i class="fas fa-arrow-left text-xs"></i> Historial
            </a>
            <span class="text-gray-300">|</span>
            <span class="text-sm font-semibold text-red-600 flex items-center gap-1">
                <i class="fas fa-undo-alt"></i> Nueva Devolución
            </span>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
             PASO 1: BUSCAR CLIENTE (combobox con filtro live)
        ══════════════════════════════════════════════════════ --}}
        <script>
            window._devClientes   = @json($clientes->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'doc' => $c->numero_documento ?? '']));
            window._devClienteNom = @json($clienteId ? ($clientes->firstWhere('id', $clienteId)?->nombre ?? '') : '');
            window._devCreateUrl  = @json(route('devoluciones.create'));
        </script>
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6"
             x-data="{
                 buscar: window._devClienteNom,
                 open: false,
                 clients: window._devClientes,
                 get filtered() {
                     if (!this.buscar.trim()) return this.clients;
                     const q = this.buscar.toLowerCase();
                     return this.clients.filter(c =>
                         c.nombre.toLowerCase().includes(q) || c.doc.includes(q)
                     );
                 },
                 select(client) {
                     this.buscar = client.nombre;
                     this.open = false;
                     window.location.href = window._devCreateUrl + '?cliente_id=' + client.id;
                 }
             }"
             @click.outside="open = false">

            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">1</span>
                Seleccionar Cliente
                @if($clienteId)
                    <span class="ml-2 text-xs font-normal text-green-600 bg-green-100 px-2 py-0.5 rounded-full">
                        <i class="fas fa-check-circle mr-1"></i>Seleccionado
                    </span>
                @endif
            </h2>

            <div class="relative max-w-md">
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <i class="fas fa-search text-sm"></i>
                    </span>
                    <input
                        type="text"
                        x-model="buscar"
                        @focus="open = true"
                        @input="open = true"
                        @keydown.escape="open = false"
                        placeholder="Buscar cliente por nombre o DNI/RUC..."
                        class="w-full pl-9 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                        autocomplete="off"
                    />
                    <button x-show="buscar" @click="buscar = ''; open = false; window.location.href = window._devCreateUrl"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-red-500">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                {{-- Dropdown de resultados --}}
                <div x-show="open && filtered.length > 0"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto"
                     style="display:none">
                    <template x-for="c in filtered" :key="c.id">
                        <button type="button"
                                @click="select(c)"
                                class="w-full text-left px-4 py-2.5 hover:bg-blue-50 flex items-center justify-between gap-3 border-b border-gray-100 last:border-0 transition-colors">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-user text-gray-300 text-xs"></i>
                                <span x-text="c.nombre" class="text-sm font-medium text-gray-800"></span>
                            </span>
                            <span x-text="c.doc" class="text-xs font-mono text-gray-400 shrink-0"></span>
                        </button>
                    </template>
                </div>

                <div x-show="open && buscar && filtered.length === 0"
                     class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-400"
                     style="display:none">
                    <i class="fas fa-search mr-2"></i>Sin resultados para "<span x-text="buscar"></span>"
                </div>
            </div>
        </div>

        @if($clienteId && $ventas->isNotEmpty())
        {{-- ══════════════════════════════════════════════════════
             PASO 2: SELECCIONAR PRODUCTOS
        ══════════════════════════════════════════════════════ --}}
        <form action="{{ route('devoluciones.store') }}" method="POST" id="form-devolucion"
              x-data="{
                  filtro: '',
                  totalUnidades: 0,
                  updateTotal() {
                      let t = 0;
                      document.querySelectorAll('.qty-input').forEach(i => { t += parseInt(i.value) || 0; });
                      this.totalUnidades = t;
                  },
                  setMax(ventaId) {
                      document.querySelectorAll('.qty-input[data-venta=\'' + ventaId + '\']').forEach(i => {
                          i.value = i.max;
                      });
                      this.updateTotal();
                  },
                  clearVenta(ventaId) {
                      document.querySelectorAll('.qty-input[data-venta=\'' + ventaId + '\']').forEach(i => {
                          i.value = 0;
                      });
                      this.updateTotal();
                  }
              }"
              @input="updateTotal()">
            @csrf
            <input type="hidden" name="cliente_id" value="{{ $clienteId }}">

            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-orange-500 text-white text-xs font-bold flex items-center justify-center">2</span>
                        Indicar Cantidades a Devolver
                    </h2>
                    <span class="text-sm text-gray-500">
                        Total a devolver: <span class="font-bold text-orange-600" x-text="totalUnidades"></span> unidad(es)
                    </span>
                </div>

                {{-- Filtro --}}
                <div class="mb-4">
                    <div class="relative max-w-sm">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                            <i class="fas fa-filter text-xs"></i>
                        </span>
                        <input type="text"
                               x-model.debounce.150ms="filtro"
                               placeholder="Filtrar por producto o código de venta..."
                               class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-400 bg-gray-50">
                    </div>
                </div>

                {{-- Ventas --}}
                @foreach($ventas as $venta)
                @php $detallesDisponibles = $venta->detalles->filter(fn($d) => $d->cantidad_disponible > 0); @endphp
                <div class="mb-4 border border-gray-200 rounded-xl overflow-hidden"
                     x-show="!filtro || '{{ strtolower($venta->codigo) }}'.includes(filtro.toLowerCase()) || {{ $venta->detalles->map(fn($d) => "'" . strtolower(str_replace("'", "\\'", $d->producto?->nombre ?? '')) . "'") ->implode(' + ') ?: "''" }}.includes(filtro.toLowerCase())"
                     x-cloak>

                    {{-- Cabecera --}}
                    <div class="bg-gray-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-semibold text-gray-800 text-sm">{{ $venta->codigo }}</span>
                            <span class="text-gray-400 text-xs ml-2">{{ $venta->fecha->format('d/m/Y') }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <span><i class="fas fa-box mr-1"></i>{{ $detallesDisponibles->count() }} ítem(s) disponibles</span>
                            <span class="font-semibold text-gray-700">S/ {{ number_format($venta->total, 2) }}</span>
                            <button type="button" @click="setMax('{{ $venta->id }}')"
                                    class="px-2 py-1 bg-orange-50 hover:bg-orange-100 text-orange-700 rounded font-medium transition">
                                Todo
                            </button>
                            <button type="button" @click="clearVenta('{{ $venta->id }}')"
                                    class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded font-medium transition">
                                Nada
                            </button>
                        </div>
                    </div>

                    {{-- Tabla --}}
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Producto</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Vendido</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Ya devuelto</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 w-36">Cant. a devolver</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">P. unit.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($venta->detalles as $detalle)
                            @php
                                $yaDevuelto  = $detalle->cantidad_devuelta;
                                $disponible  = $detalle->cantidad_disponible;
                                $esImei      = (bool) $detalle->imei_id;
                                $oldVal      = old("cantidades.{$detalle->id}", 0);
                            @endphp
                            <tr class="{{ $disponible === 0 ? 'opacity-40 bg-gray-50' : 'hover:bg-orange-50' }} transition-colors"
                                data-nombre="{{ strtolower(($detalle->producto?->nombre ?? '') . ' ' . ($detalle->variante?->nombre_completo ?? '')) }}"
                                x-show="!filtro || $el.dataset.nombre.includes(filtro.toLowerCase())">

                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ $detalle->producto?->nombre }}</span>
                                    @if($detalle->variante)
                                        <span class="text-xs text-indigo-600 ml-1 font-medium">{{ $detalle->variante->nombre_completo }}</span>
                                    @endif
                                    @if($detalle->imei)
                                        <span class="block text-xs font-mono text-purple-600 mt-0.5">
                                            <i class="fas fa-mobile-alt mr-1"></i>{{ $detalle->imei->codigo_imei }}
                                        </span>
                                    @endif
                                    @if($disponible === 0)
                                        <span class="block text-xs text-green-600 font-medium mt-0.5">
                                            <i class="fas fa-check-circle mr-1"></i>Totalmente devuelto
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center font-semibold text-gray-700">
                                    {{ $detalle->cantidad }}
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if($yaDevuelto > 0)
                                        <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
                                            {{ $yaDevuelto }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if($disponible > 0)
                                        <input type="number"
                                               name="cantidades[{{ $detalle->id }}]"
                                               class="qty-input w-20 text-center border border-gray-300 rounded-lg py-1.5 px-2 text-sm font-semibold focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
                                               min="0"
                                               max="{{ $disponible }}"
                                               value="{{ old("cantidades.{$detalle->id}", 0) }}"
                                               data-venta="{{ $venta->id }}">
                                        <p class="text-xs text-gray-400 mt-0.5">máx {{ $disponible }}</p>
                                    @else
                                        <input type="hidden" name="cantidades[{{ $detalle->id }}]" value="0">
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right text-gray-500">
                                    S/ {{ number_format($detalle->precio_con_igv, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach

                {{-- Aviso sin resultados de filtro --}}
                <div x-show="filtro && document.querySelectorAll('[data-nombre]').length > 0 &&
                             [...document.querySelectorAll('[data-nombre]')].every(el => el.style.display === 'none')"
                     class="text-center py-8 text-gray-400 text-sm" style="display:none">
                    <i class="fas fa-search text-2xl block mb-2 text-gray-200"></i>
                    Sin productos que coincidan con "<span x-text="filtro"></span>"
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
                 PASO 3: DESTINO Y GUÍA DE REMISIÓN
            ══════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center">3</span>
                    Destino y Guía de Remisión
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Almacén Destino <span class="text-red-500">*</span>
                        </label>
                        <select name="almacen_id" required
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 bg-white">
                            <option value="">— Seleccione almacén —</option>
                            @foreach($almacenes as $alm)
                                <option value="{{ $alm->id }}" {{ old('almacen_id') == $alm->id ? 'selected' : '' }}>
                                    {{ $alm->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                        <input type="text" name="observaciones" maxlength="255"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                               placeholder="Motivo de la devolución..."
                               value="{{ old('observaciones') }}">
                    </div>
                </div>

                {{-- Guía de remisión --}}
                <details class="border border-gray-200 rounded-xl overflow-hidden" {{ old('guia.fecha_traslado') ? 'open' : '' }}>
                    <summary class="px-4 py-3 text-sm font-medium text-gray-600 cursor-pointer hover:bg-gray-50 flex items-center gap-2 select-none">
                        <i class="fas fa-file-invoice text-emerald-500"></i>
                        Datos de Guía de Remisión
                        <span class="ml-1 text-xs text-gray-400 font-normal">(opcional)</span>
                        <i class="fas fa-chevron-down ml-auto text-gray-400 text-xs"></i>
                    </summary>
                    <div class="p-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 bg-gray-50">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Modalidad</label>
                            <select name="guia[modalidad]"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-emerald-400">
                                <option value="privado" {{ old('guia.modalidad','privado')==='privado' ? 'selected':'' }}>Transporte Privado</option>
                                <option value="publico" {{ old('guia.modalidad')==='publico' ? 'selected':'' }}>Transporte Público</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Fecha Traslado</label>
                            <input type="date" name="guia[fecha_traslado]"
                                   value="{{ old('guia.fecha_traslado') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dirección Partida</label>
                            <input type="text" name="guia[direccion_partida]"
                                   value="{{ old('guia.direccion_partida') }}"
                                   placeholder="Dirección del cliente"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dirección Llegada</label>
                            <input type="text" name="guia[direccion_llegada]"
                                   value="{{ old('guia.direccion_llegada') }}"
                                   placeholder="Dirección del almacén"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">DNI Conductor</label>
                            <input type="text" name="guia[conductor_dni]" maxlength="8"
                                   value="{{ old('guia.conductor_dni') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nombre Conductor</label>
                            <input type="text" name="guia[conductor_nombre]"
                                   value="{{ old('guia.conductor_nombre') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Licencia</label>
                            <input type="text" name="guia[conductor_licencia]" maxlength="20"
                                   value="{{ old('guia.conductor_licencia') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Placa Vehículo</label>
                            <input type="text" name="guia[placa_vehiculo]" maxlength="20"
                                   value="{{ old('guia.placa_vehiculo') }}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-400 font-mono uppercase">
                        </div>
                    </div>
                </details>
            </div>

            {{-- Barra de acciones --}}
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500" x-show="totalUnidades > 0">
                    <i class="fas fa-info-circle text-orange-400 mr-1"></i>
                    <span x-text="totalUnidades"></span> unidad(es) lista(s) para devolver
                </div>
                <div class="flex gap-3 ml-auto">
                    <a href="{{ route('devoluciones.index') }}"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2.5 px-6 rounded-xl transition">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center gap-2 transition disabled:opacity-50"
                            :disabled="totalUnidades === 0">
                        <i class="fas fa-undo-alt"></i>
                        Registrar Devolución
                        <span x-show="totalUnidades > 0" class="bg-red-800 text-xs px-1.5 py-0.5 rounded-full" x-text="totalUnidades"></span>
                    </button>
                </div>
            </div>
        </form>

        @elseif($clienteId && $ventas->isEmpty())
            <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-5 rounded-xl flex items-center gap-3">
                <i class="fas fa-info-circle text-yellow-500 text-xl shrink-0"></i>
                <div>
                    <p class="font-semibold text-sm">Sin ventas disponibles</p>
                    <p class="text-sm mt-0.5">Este cliente no tiene ventas pagadas registradas.</p>
                </div>
            </div>
        @endif
    </div>

</body>
</html>
