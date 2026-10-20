{{-- resources/views/users/create.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header 
            title="Crear Nuevo Usuario" 
            subtitle="Registra un nuevo usuario en el sistema"
        />

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Nombre --}}
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                            <input type="password" name="password" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña *</label>
                            <input type="password" name="password_confirmation" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        {{-- Rol --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rol *</label>
                            <select name="role_id" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Seleccione un rol</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                        {{ $role->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sucursal --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                            <select id="sucursal_sel"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    onchange="onSucursalChange(this.value)">
                                <option value="">— Sin sucursal —</option>
                                @foreach($sucursales as $suc)
                                    @php
                                        $preselect = old('almacen_id')
                                            ? ($suc->almacenes->contains('id', old('almacen_id')) ? 'selected' : '')
                                            : '';
                                    @endphp
                                    <option value="{{ $suc->id }}" {{ $preselect }}>{{ $suc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Almacén (cascada) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Almacén / Tienda</label>

                            {{-- Dropdown cuando hay 2+ almacenes --}}
                            <div id="almacen_wrap" class="{{ old('almacen_id') ? '' : 'hidden' }}">
                                <select id="almacen_sel"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        onchange="document.getElementById('almacen_id_hidden').value = this.value">
                                    <option value="">Seleccione un almacén</option>
                                </select>
                            </div>

                            {{-- Info cuando hay 1 único almacén --}}
                            <div id="almacen_unico_info" class="hidden items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm">
                                <i class="fas fa-warehouse text-blue-500"></i>
                                <span id="almacen_unico_nombre" class="font-medium text-blue-900"></span>
                                <span class="text-xs text-blue-400 ml-auto">Auto-seleccionado</span>
                            </div>

                            {{-- Siempre enviado al servidor --}}
                            <input type="hidden" name="almacen_id" id="almacen_id_hidden" value="{{ old('almacen_id') }}">
                            <p class="text-xs text-gray-500 mt-1">Selecciona primero la sucursal para filtrar almacenes</p>
                            @error('almacen_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Estado --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado *</label>
                            <select name="estado" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="activo" {{ old('estado') == 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="inactivo" {{ old('estado') == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>

                        {{-- Teléfono --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono (opcional)</label>
                            <input type="text" name="telefono" value="{{ old('telefono') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        {{-- Dirección --}}
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección (opcional)</label>
                            <textarea name="direccion" rows="2"
                                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('direccion') }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                        <a href="{{ route('users.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg">
                            Cancelar
                        </a>
                        <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-6 rounded-lg">
                            <i class="fas fa-save mr-2"></i>Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const sucursalAlmacenesMap = @json(
            $sucursales->mapWithKeys(fn($s) => [
                $s->id => $s->almacenes->map(fn($a) => ['id' => $a->id, 'nombre' => $a->nombre])->values()
            ])
        );

        function onSucursalChange(sucursalId) {
            const wrap        = document.getElementById('almacen_wrap');
            const sel         = document.getElementById('almacen_sel');
            const infoUnico   = document.getElementById('almacen_unico_info');
            const nombreUnico = document.getElementById('almacen_unico_nombre');
            const hidden      = document.getElementById('almacen_id_hidden');

            wrap.classList.add('hidden');
            infoUnico.classList.add('hidden');
            infoUnico.classList.remove('flex');
            sel.innerHTML = '<option value="">Seleccione un almacén</option>';
            hidden.value  = '';

            if (!sucursalId) return;

            const almacenes = sucursalAlmacenesMap[sucursalId] || [];

            if (almacenes.length === 1) {
                hidden.value          = almacenes[0].id;
                nombreUnico.textContent = almacenes[0].nombre;
                infoUnico.classList.remove('hidden');
                infoUnico.classList.add('flex');

            } else if (almacenes.length > 1) {
                almacenes.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.textContent = a.nombre;
                    sel.appendChild(opt);
                });
                wrap.classList.remove('hidden');
            }
        }

        // Re-disparar si venimos de validación fallida
        (function () {
            const sel = document.getElementById('sucursal_sel');
            if (sel && sel.value) {
                onSucursalChange(sel.value);
                const prev = '{{ old('almacen_id') }}';
                if (prev) {
                    const almSel = document.getElementById('almacen_sel');
                    const hidden = document.getElementById('almacen_id_hidden');
                    if (almSel) almSel.value = prev;
                    if (hidden) hidden.value  = prev;
                }
            }
        })();
    </script>
</body>
</html>