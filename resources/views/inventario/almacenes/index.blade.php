<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almacenes y Tiendas - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Almacenes"
            subtitle="Almacenes centrales y almacenes de tienda"
        />

        @if(session('success'))
            <div id="flash-ok" class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl shadow-sm">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl shadow-sm">
                <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Estadísticas --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-900">
                <p class="text-xs text-gray-500 font-medium">Total</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-warehouse mr-1"></i>Ubicaciones</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 font-medium">Activos</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['activos'] }}</p>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-check-circle mr-1"></i>En operación</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                <p class="text-xs text-gray-500 font-medium">Principal</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['principal'] }}</p>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-star mr-1"></i>Almacén central</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                <p class="text-xs text-gray-500 font-medium">Tiendas</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['tiendas'] }}</p>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-store mr-1"></i>Puntos de venta</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-teal-500">
                <p class="text-xs text-gray-500 font-medium">Centrales</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['depositos'] }}</p>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-warehouse mr-1"></i>Almacenes</p>
            </div>
        </div>

        {{-- Filtro + botón nuevo --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex items-center gap-4">
            <form action="{{ route('inventario.almacenes.index') }}" method="GET" class="flex items-center gap-3 flex-1">
                <label class="text-sm font-medium text-gray-600 shrink-0">Filtrar por estado:</label>
                <select name="estado" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <option value="activo"   {{ request('estado') === 'activo'   ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ request('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
                @if(request('estado'))
                    <a href="{{ route('inventario.almacenes.index') }}" class="text-xs text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times mr-1"></i>Limpiar
                    </a>
                @endif
            </form>
            @if($canCreate)
                <button onclick="openAlmacenCreate()"
                    class="bg-blue-900 hover:bg-blue-800 active:scale-95 text-white text-sm font-semibold px-4 py-2 rounded-xl flex items-center gap-2 transition-all shadow-md">
                    <i class="fas fa-plus"></i>Nuevo
                </button>
            @endif
        </div>

        {{-- Almacenes de Tiendas (auto-creados con sucursales) --}}
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-store text-orange-600"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Almacenes de Tiendas</h2>
                    <p class="text-xs text-gray-400">Stock de cada sucursal — se crean automáticamente al crear una sucursal</p>
                </div>
                <span class="ml-auto text-xs font-semibold px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-700">
                    {{ $tiendas->count() }} registro(s)
                </span>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                @if($tiendas->isEmpty())
                    <div class="py-14 text-center text-gray-400">
                        <i class="fas fa-store text-4xl mb-3 block opacity-30"></i>
                        <p class="text-sm">No hay almacenes de tienda. Se crean al crear una sucursal.</p>
                        @if($canCreate)
                            <button onclick="openAlmacenCreate()" class="inline-flex items-center gap-2 mt-4 text-sm text-blue-600 hover:underline">
                                <i class="fas fa-plus"></i>Crear punto de venta
                            </button>
                        @endif
                    </div>
                @else
                    @include('inventario.almacenes._tabla', ['items' => $tiendas, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                @endif
            </div>
        </div>

        {{-- Almacenes Centrales --}}
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center">
                    <i class="fas fa-warehouse text-teal-600"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Almacenes Centrales</h2>
                    <p class="text-xs text-gray-400">Bodegas de distribución — distribuyen mercadería a las sucursales</p>
                </div>
                <span class="ml-auto text-xs font-semibold px-2.5 py-0.5 rounded-full bg-teal-100 text-teal-700">
                    {{ $depositos->count() }} registro(s)
                </span>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                @if($depositos->isEmpty())
                    <div class="py-14 text-center text-gray-400">
                        <i class="fas fa-warehouse text-4xl mb-3 block opacity-30"></i>
                        <p class="text-sm">No hay almacenes registrados.</p>
                        @if($canCreate)
                            <button onclick="openAlmacenCreate()" class="inline-flex items-center gap-2 mt-4 text-sm text-blue-600 hover:underline">
                                <i class="fas fa-plus"></i>Crear almacén
                            </button>
                        @endif
                    </div>
                @else
                    @include('inventario.almacenes._tabla', ['items' => $depositos, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                @endif
            </div>
        </div>
    </div>

    {{-- =====================================================================
         MODAL: CREAR ALMACÉN
    ====================================================================== --}}
    <div id="modal-alm-crear" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAlmacenCreate()"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 py-8">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl">

                <div class="bg-gradient-to-r from-blue-900 to-blue-600 rounded-t-2xl px-6 py-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white/20 border-2 border-white/30 flex items-center justify-center">
                        <i class="fas fa-warehouse text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white text-xl font-bold">Nuevo Almacén Central</h2>
                        <p class="text-blue-200 text-sm mt-0.5">Bodega de distribución independiente</p>
                    </div>
                    <button onclick="closeAlmacenCreate()" class="ml-auto text-white/70 hover:text-white">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <form action="{{ route('inventario.almacenes.store') }}" method="POST">
                    @csrf
                    <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input type="text" name="nombre" id="c-nombre" value="{{ old('nombre') }}" required
                                       placeholder="Ej. Almacén Central Lima"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('nombre')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado <span class="text-red-500">*</span></label>
                                <select name="estado" required
                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                    <option value="activo"   {{ old('estado','activo') === 'activo'   ? 'selected' : '' }}>Activo</option>
                                    <option value="inactivo" {{ old('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input type="text" name="telefono" value="{{ old('telefono') }}"
                                       placeholder="999 000 000"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <textarea name="direccion" rows="2" placeholder="Dirección completa"
                                          class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('direccion') }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-700">
                            <i class="fas fa-info-circle mt-0.5 shrink-0"></i>
                            <span>El código se generará automáticamente. Los almacenes de tienda se crean automáticamente con cada sucursal.</span>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                        <button type="button" onclick="closeAlmacenCreate()"
                                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 active:scale-95 transition-all shadow-md">
                            <i class="fas fa-plus"></i>Guardar Almacén
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         MODAL: EDITAR ALMACÉN
    ====================================================================== --}}
    <div id="modal-alm-editar" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAlmacenEdit()"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 py-8">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl">

                <div class="bg-gradient-to-r from-amber-700 to-amber-500 rounded-t-2xl px-6 py-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white/20 border-2 border-white/30 flex items-center justify-center">
                        <i class="fas fa-pen text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white text-xl font-bold">Editar Almacén</h2>
                        <p class="text-amber-100 text-sm mt-0.5" id="e-alm-subtitle">Modifica la información</p>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <span id="e-alm-codigo" class="text-amber-200 text-xs font-mono bg-amber-800/30 px-2 py-0.5 rounded"></span>
                        <button onclick="closeAlmacenEdit()" class="text-white/70 hover:text-white">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>

                <form id="e-alm-form" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">

                        {{-- Badge informativo solo para almacenes de tienda --}}
                        <div id="e-alm-tienda-badge" class="hidden flex items-center gap-2 px-3 py-2.5 bg-orange-50 border border-orange-200 rounded-xl text-xs text-orange-800">
                            <i class="fas fa-store text-orange-500"></i>
                            <span>Almacén de tienda — vinculado a la sucursal <strong id="e-alm-sucursal-nombre"></strong>. Creado automáticamente.</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input type="text" name="nombre" id="e-alm-nombre" required
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado <span class="text-red-500">*</span></label>
                                <select name="estado" id="e-alm-estado" required
                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input type="text" name="telefono" id="e-alm-telefono"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <textarea name="direccion" id="e-alm-direccion" rows="2"
                                          class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                        <button type="button" onclick="closeAlmacenEdit()"
                                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 active:scale-95 transition-all shadow-md">
                            <i class="fas fa-save"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const almacenesData = @json($almacenesData);
    const updateBaseUrl = "{{ url('inventario/almacenes') }}";

    /* ---- Modal Crear ---- */
    function openAlmacenCreate() {
        document.getElementById('modal-alm-crear').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => document.getElementById('c-nombre')?.focus(), 80);
    }
    function closeAlmacenCreate() {
        document.getElementById('modal-alm-crear').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    /* ---- Modal Editar ---- */
    function openAlmacenEdit(id) {
        const a = almacenesData[id];
        if (!a) return;

        // Header
        document.getElementById('e-alm-subtitle').textContent = 'Editando: ' + a.nombre;
        document.getElementById('e-alm-codigo').textContent   = a.codigo;

        // Campos
        document.getElementById('e-alm-nombre').value    = a.nombre;
        document.getElementById('e-alm-telefono').value  = a.telefono;
        document.getElementById('e-alm-direccion').value = a.direccion;
        document.getElementById('e-alm-estado').value    = a.estado;

        // Badge sucursal (solo para almacenes de tienda)
        const badge = document.getElementById('e-alm-tienda-badge');
        if (a.es_tienda) {
            document.getElementById('e-alm-sucursal-nombre').textContent = a.sucursal_nombre;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }

        // Action
        document.getElementById('e-alm-form').action = updateBaseUrl + '/' + id;

        document.getElementById('modal-alm-editar').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => document.getElementById('e-alm-nombre')?.focus(), 80);
    }
    function closeAlmacenEdit() {
        document.getElementById('modal-alm-editar').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeAlmacenCreate(); closeAlmacenEdit(); }
    });

    // Abrir crear si hay errores de validación (store)
    @if($errors->any())
        openAlmacenCreate();
    @endif

    setTimeout(() => document.getElementById('flash-ok')?.remove(), 4000);
    </script>
</body>
</html>
