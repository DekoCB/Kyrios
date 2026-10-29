<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Clientes" subtitle="Personas y empresas a las que se vende" />

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
        @endif

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            @foreach([
                ['Clientes registrados', $stats['total'], 'fa-users', 'border-blue-500'],
                ['Activos', $stats['activos'], 'fa-user-check', 'border-emerald-500'],
                ['Empresas (RUC)', $stats['empresas'], 'fa-building', 'border-indigo-500'],
                ['Personas (DNI / CE)', $stats['personas'], 'fa-user', 'border-amber-400'],
                ['Prospectos (sin documento)', $stats['prospectos'], 'fa-user-clock', 'border-rose-400'],
            ] as [$titulo, $valor, $icono, $borde])
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $borde }}">
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><i class="fas {{ $icono }} mr-1"></i>{{ $titulo }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $valor }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filtros --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre, documento, teléfono, correo o contacto"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Documento</label>
                <select name="tipo_documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach(['DNI', 'RUC', 'CE'] as $t)
                        <option value="{{ $t }}" @selected(request('tipo_documento') === $t)>{{ $t }}</option>
                    @endforeach
                    <option value="SIN" @selected(request('tipo_documento') === 'SIN')>Sin documento (prospectos)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Segmento</label>
                <select name="segmento" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Cliente::SEGMENTOS as $cod => $etiqueta)
                        <option value="{{ $cod }}" @selected(request('segmento') === $cod)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_lista" class="block text-xs font-medium text-gray-600 mb-1">Lista de difusión</label>
                <select id="filtro_lista" name="lista" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas</option>
                    @foreach(\App\Models\Cliente::LISTAS_DIFUSION as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('lista') === $clave)>{{ $etiqueta }} ({{ $conteoListas[$clave] ?? 0 }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="md:col-span-6 flex flex-wrap items-center justify-between gap-2">
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 hover:text-gray-800"><i class="fas fa-redo mr-1"></i>Limpiar</a>
                <div class="flex flex-wrap gap-2">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i class="fas fa-search mr-1"></i>Buscar</button>
                    <a href="{{ route('clientes.exportar', request()->query()) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm font-semibold px-4 py-2 rounded-lg">
                        <i class="fas fa-file-csv mr-1"></i>Exportar
                    </a>
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('abrir-difusion'))" class="border border-emerald-300 text-emerald-800 hover:bg-emerald-50 text-sm font-semibold px-4 py-2 rounded-lg">
                        <i class="fab fa-whatsapp mr-1"></i>Exportar lista de difusión
                    </button>
                    @if($canCreate)
                        <a href="{{ route('clientes.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-1"></i>Nuevo cliente
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Cuántos clientes hay en cada lista de difusión (clic para filtrar) --}}
        <div class="flex flex-wrap items-center gap-2 mb-4" aria-label="Listas de difusión">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide mr-1"><i class="fab fa-whatsapp mr-1"></i>Listas</span>
            @foreach(\App\Models\Cliente::LISTAS_DIFUSION as $clave => $etiqueta)
                <a href="{{ route('clientes.index', ['lista' => $clave]) }}"
                   class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border {{ request('lista') === $clave ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400' }}">
                    {{ $etiqueta }} <span class="font-semibold">{{ $conteoListas[$clave] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ventas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total comprado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                @if($cliente->es_prospecto)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-rose-100 text-rose-700" title="Aún sin documento">Prospecto</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-700 mr-1">{{ $cliente->tipo_documento }}</span>
                                    <span class="font-mono">{{ $cliente->numero_documento }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('clientes.show', $cliente) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $cliente->nombre }}</a>
                                @if($cliente->contacto)<div class="text-xs text-gray-400">Contacto: {{ $cliente->contacto }}</div>@endif
                                @if($cliente->etiquetas_difusion)
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @foreach(array_slice($cliente->etiquetas_difusion, 0, 4) as $etiqueta)
                                            <span class="inline-flex px-1.5 py-0 text-[10px] font-medium rounded-full bg-emerald-100 text-emerald-800">{{ $etiqueta }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if($cliente->segmento || $cliente->empresa)<div class="text-xs text-gray-400">{{ collect([$cliente->segmento, $cliente->empresa])->filter()->implode(' · ') }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($cliente->telefono)<div><i class="fas fa-phone text-xs mr-1"></i>{{ $cliente->telefono }}</div>@endif
                                @if($cliente->email)<div class="text-xs">{{ $cliente->email }}</div>@endif
                                @if(!$cliente->telefono && !$cliente->email)—@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-600">{{ $cliente->ventas_count }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">S/ {{ number_format((float) $cliente->total_comprado, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $cliente->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">{{ ucfirst($cliente->estado) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <a href="{{ route('clientes.show', $cliente) }}" class="text-blue-600 hover:text-blue-800 mr-3" title="Ver ficha"><i class="fas fa-eye"></i></a>
                                @if($canEdit)
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-yellow-600 hover:text-yellow-800 mr-3" title="Editar"><i class="fas fa-edit"></i></a>
                                @endif
                                @if($canEdit)
                                    <a href="{{ route('cotizaciones.create', ['cliente_id' => $cliente->id]) }}" class="text-gray-500 hover:text-gray-800" title="Nueva cotización"><i class="fas fa-file-contract"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                                <p class="font-medium">No hay clientes con esos filtros</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $clientes->links() }}</div>

        {{-- Exportar una lista de difusión para WhatsApp: muestra cuántos entran antes de descargar --}}
        <div x-data="difusion()" @abrir-difusion.window="abrir()" x-show="visible" style="display:none" @keydown.escape.window="visible = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="difusionTitulo">
            <div class="absolute inset-0 bg-black/50" @click="visible = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <h3 id="difusionTitulo" class="text-lg font-bold text-gray-900"><i class="fab fa-whatsapp text-emerald-600 mr-1"></i>Exportar lista de difusión</h3>
                <p class="text-sm text-gray-500 mt-1">Elija una o varias listas. Se descarga un CSV con nombre y celular (+51) de quienes tienen un celular válido.</p>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-4">
                    @foreach(\App\Models\Cliente::LISTAS_DIFUSION as $clave => $etiqueta)
                        <label for="dif_{{ $clave }}" class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="dif_{{ $clave }}" value="{{ $clave }}" x-model="listas" @change="resumir()"
                                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-300">
                            {{ $etiqueta }} <span class="text-xs text-gray-400">({{ $conteoListas[$clave] ?? 0 }})</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 text-sm rounded-lg bg-gray-50 border border-gray-200 p-3 min-h-[3.5rem]" aria-live="polite">
                    <template x-if="!listas.length"><p class="text-gray-500">Marque al menos una lista.</p></template>
                    <template x-if="listas.length && cargando"><p class="text-gray-500">Calculando…</p></template>
                    <template x-if="listas.length && !cargando && resumen">
                        <div>
                            <p><strong x-text="resumen.incluidos"></strong> contactos con celular válido entrarán a la lista.</p>
                            <p x-show="resumen.sin_celular > 0" class="text-amber-700"><strong x-text="resumen.sin_celular"></strong> no tienen celular válido y no se incluirán.</p>
                            <p x-show="resumen.repetidos > 0" class="text-gray-500"><strong x-text="resumen.repetidos"></strong> repiten un número ya incluido.</p>
                        </div>
                    </template>
                </div>
                <div class="flex gap-3 mt-5">
                    <button type="button" @click="visible = false" class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-100 py-2.5 rounded-xl font-semibold">Cancelar</button>
                    <a :href="enlace()" :class="(!listas.length || !resumen || resumen.incluidos === 0) ? 'opacity-50 pointer-events-none' : ''"
                       class="flex-1 text-center bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl font-semibold" @click="visible = false">
                        <i class="fas fa-file-csv mr-1"></i>Descargar CSV
                    </a>
                </div>
            </div>
        </div>
        <script>
        function difusion() {
            return {
                visible: false, listas: [], resumen: null, cargando: false,
                abrir() { this.visible = true; if (this.listas.length) this.resumir(); },
                parametros() { return this.listas.map(l => 'listas[]=' + encodeURIComponent(l)).join('&'); },
                enlace() { return @json(route('clientes.difusion.exportar')) + '?' + this.parametros(); },
                async resumir() {
                    this.resumen = null;
                    if (!this.listas.length) return;
                    this.cargando = true;
                    try {
                        const r = await fetch(@json(route('clientes.difusion.resumen')) + '?' + this.parametros(), { headers: { 'Accept': 'application/json' } });
                        this.resumen = await r.json();
                    } catch (e) { this.resumen = null; }
                    this.cargando = false;
                },
            };
        }
        </script>
    </div>
</body>
</html>
