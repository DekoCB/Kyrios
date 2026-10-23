{{-- resources/views/users/index.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Gestión de Usuarios"
            subtitle="Administra los usuarios del sistema"
        />

        @if(session('success'))
            <div id="flash-ok" class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 p-4 mb-6 rounded-xl shadow-sm">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 p-4 mb-6 rounded-xl shadow-sm">
                <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Barra superior --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Lista de Usuarios</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $users->total() }} usuario(s) registrado(s)</p>
            </div>
            <button onclick="openCreate()"
                    class="inline-flex items-center gap-2 bg-blue-900 hover:bg-blue-800 active:scale-95 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-md">
                <i class="fas fa-user-plus"></i>Nuevo Usuario
            </button>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gradient-to-r from-blue-900 to-blue-700">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-blue-100 uppercase tracking-wider">Usuario</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-blue-100 uppercase tracking-wider hidden md:table-cell">Email</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-blue-100 uppercase tracking-wider">Rol</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-blue-100 uppercase tracking-wider hidden lg:table-cell">Sucursal / Almacén</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-blue-100 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold text-blue-100 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($users as $user)
                    @php
                        $avatarColors = ['from-blue-600 to-blue-400','from-purple-600 to-purple-400','from-emerald-600 to-emerald-400','from-orange-500 to-orange-300','from-rose-500 to-rose-300','from-teal-600 to-teal-400'];
                        $roleBadge = [
                            'Administrador' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'Almacenero'    => 'bg-blue-100 text-blue-800 border-blue-200',
                            'Cajero'        => 'bg-orange-100 text-orange-800 border-orange-200',
                            'Proveedor'     => 'bg-teal-100 text-teal-800 border-teal-200',
                            'Tienda'        => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'Vendedor'      => 'bg-amber-100 text-amber-800 border-amber-200',
                        ];
                        $roleIcon = [
                            'Administrador' => 'fa-crown',
                            'Almacenero'    => 'fa-warehouse',
                            'Cajero'        => 'fa-cash-register',
                            'Proveedor'     => 'fa-truck',
                            'Tienda'        => 'fa-store',
                            'Vendedor'      => 'fa-handshake',
                        ];
                        $rn  = $user->role?->nombre ?? '';
                        $rc  = $roleBadge[$rn] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                        $ri  = $roleIcon[$rn]   ?? 'fa-user';
                    @endphp
                    <tr class="hover:bg-blue-50/40 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br {{ $avatarColors[$user->id % count($avatarColors)] }} flex items-center justify-center text-white text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm leading-tight">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-400">DNI: {{ $user->dni ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 hidden md:table-cell">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            @if($user->role)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border {{ $rc }}">
                                    <i class="fas {{ $ri }} text-[9px]"></i>{{ $rn }}
                                </span>
                            @else
                                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full border bg-red-100 text-red-700 border-red-200">Sin rol</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm hidden lg:table-cell">
                            @if($user->almacen)
                                <p class="font-medium text-gray-800 leading-tight">{{ $user->almacen->sucursal?->nombre ?? '—' }}</p>
                                <p class="text-xs text-gray-400"><i class="fas fa-warehouse mr-1"></i>{{ $user->almacen->nombre }}</p>
                            @else
                                <span class="text-gray-400 italic text-xs">Sin asignar</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border
                                {{ $user->estado === 'activo' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $user->estado === 'activo' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                {{ ucfirst($user->estado) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('users.show', $user) }}"
                                   class="p-2 rounded-lg text-blue-500 hover:bg-blue-100 transition-colors" title="Ver">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <button onclick="openEdit({{ $user->id }})"
                                        class="p-2 rounded-lg text-amber-500 hover:bg-amber-100 transition-colors" title="Editar">
                                    <i class="fas fa-pen text-sm"></i>
                                </button>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline"
                                      data-confirm="¿Eliminar a {{ $user->name }}? Esta acción no se puede deshacer.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-red-400 hover:bg-red-100 transition-colors" title="Eliminar">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-users text-3xl text-gray-300"></i>
                                </div>
                                <p class="font-medium text-gray-500">No hay usuarios registrados</p>
                                <button onclick="openCreate()" class="text-blue-600 hover:underline text-sm">Crear el primero</button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $users->links() }}</div>
    </div>

    {{-- ====================================================================
         MODAL: CREAR USUARIO
    ===================================================================== --}}
    <div id="modal-crear" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCreate()"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 py-8">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">

                <div class="bg-gradient-to-r from-blue-900 to-blue-600 rounded-t-2xl px-6 py-5 flex items-center gap-4">
                    <div id="c-avatar"
                         class="w-14 h-14 rounded-full bg-white/20 border-2 border-white/40 flex items-center justify-center text-white text-xl font-bold shrink-0">
                        <i class="fas fa-user text-white/60"></i>
                    </div>
                    <div>
                        <h2 class="text-white text-xl font-bold">Nuevo Usuario</h2>
                        <p class="text-blue-200 text-sm mt-0.5">Completa los datos para registrar al usuario</p>
                    </div>
                    <button onclick="closeCreate()" class="ml-auto text-white/70 hover:text-white"><i class="fas fa-times text-lg"></i></button>
                </div>

                <form action="{{ route('users.store') }}" method="POST">
                    @csrf
                    <div class="px-6 py-5 space-y-5 max-h-[72vh] overflow-y-auto">

                        {{-- Info personal --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-id-card mr-1.5"></i>Información personal</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" id="c-name" value="{{ old('name') }}" required
                                           oninput="updateAvatar('c-avatar',this.value)"
                                           placeholder="Ej. Juan Pérez García"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="correo@ejemplo.com"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">DNI</label>
                                    <input type="text" name="dni" value="{{ old('dni') }}" placeholder="12345678" maxlength="20"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    @error('dni')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                    <input type="text" name="telefono" value="{{ old('telefono') }}" placeholder="999 000 000"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado <span class="text-red-500">*</span></label>
                                    <select name="estado" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option value="activo"   {{ old('estado','activo') === 'activo'   ? 'selected' : '' }}>Activo</option>
                                        <option value="inactivo" {{ old('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Contraseña --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-lock mr-1.5"></i>Seguridad</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password" id="c-pwd" required minlength="8" placeholder="Mínimo 8 caracteres"
                                               class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <button type="button" onclick="togglePwd('c-pwd','c-eye1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i id="c-eye1" class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password_confirmation" id="c-pwd2" required placeholder="Repite la contraseña"
                                               class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <button type="button" onclick="togglePwd('c-pwd2','c-eye2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i id="c-eye2" class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Rol --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-shield-halved mr-1.5"></i>Rol del usuario <span class="text-red-500">*</span></p>
                            <input type="hidden" name="role_id" id="c-role-hidden" value="{{ old('role_id') }}">
                            @error('role_id')<p class="text-red-500 text-xs mb-2">{{ $message }}</p>@enderror
                            <div class="grid grid-cols-3 gap-3">
                                @foreach($roles as $role)
                                @php
                                    $ccfg = [
                                        'Administrador' => ['icon'=>'fa-crown',        'border'=>'hover:border-purple-400','checked'=>'peer-checked:border-purple-500 peer-checked:bg-purple-50','badge'=>'bg-purple-500','text'=>'text-purple-700'],
                                        'Almacenero'    => ['icon'=>'fa-warehouse',     'border'=>'hover:border-blue-400',  'checked'=>'peer-checked:border-blue-500 peer-checked:bg-blue-50',  'badge'=>'bg-blue-500',  'text'=>'text-blue-700'],
                                        'Cajero'        => ['icon'=>'fa-cash-register', 'border'=>'hover:border-orange-400','checked'=>'peer-checked:border-orange-500 peer-checked:bg-orange-50','badge'=>'bg-orange-500','text'=>'text-orange-700'],
                                        'Proveedor'     => ['icon'=>'fa-truck',         'border'=>'hover:border-teal-400',  'checked'=>'peer-checked:border-teal-500 peer-checked:bg-teal-50',  'badge'=>'bg-teal-500',  'text'=>'text-teal-700'],
                                        'Tienda'        => ['icon'=>'fa-store',         'border'=>'hover:border-emerald-400','checked'=>'peer-checked:border-emerald-500 peer-checked:bg-emerald-50','badge'=>'bg-emerald-500','text'=>'text-emerald-700'],
                                        'Vendedor'      => ['icon'=>'fa-handshake',     'border'=>'hover:border-amber-400', 'checked'=>'peer-checked:border-amber-500 peer-checked:bg-amber-50', 'badge'=>'bg-amber-500', 'text'=>'text-amber-700'],
                                    ][$role->nombre] ?? ['icon'=>'fa-user','border'=>'hover:border-gray-400','checked'=>'peer-checked:border-gray-500 peer-checked:bg-gray-50','badge'=>'bg-gray-500','text'=>'text-gray-700'];
                                @endphp
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="_c_role_card" value="{{ $role->id }}" class="peer sr-only"
                                           {{ old('role_id') == $role->id ? 'checked' : '' }}
                                           onchange="document.getElementById('c-role-hidden').value=this.value">
                                    <div class="border-2 border-gray-200 rounded-xl p-3 text-center transition-all {{ $ccfg['border'] }} {{ $ccfg['checked'] }} peer-checked:shadow-md">
                                        <div class="w-9 h-9 rounded-full {{ $ccfg['badge'] }} mx-auto mb-1.5 flex items-center justify-center">
                                            <i class="fas {{ $ccfg['icon'] }} text-white text-sm"></i>
                                        </div>
                                        <p class="text-xs font-semibold {{ $ccfg['text'] }} leading-tight">{{ $role->nombre }}</p>
                                    </div>
                                    <div class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-blue-600 hidden peer-checked:flex items-center justify-center">
                                        <i class="fas fa-check text-white text-[8px]"></i>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Asignación --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-building mr-1.5"></i>Asignación</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                                    <select id="c-suc" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                            onchange="onSucChange('c',this.value)">
                                        <option value="">— Sin sucursal —</option>
                                        @foreach($sucursales as $suc)
                                            <option value="{{ $suc->id }}"
                                                {{ old('almacen_id') && $suc->almacenes->contains('id', old('almacen_id')) ? 'selected' : '' }}>
                                                {{ $suc->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén / Tienda</label>
                                    <div id="c-alm-wrap" class="hidden">
                                        <select id="c-alm-sel" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                                onchange="document.getElementById('c-alm-hidden').value=this.value">
                                            <option value="">Seleccione un almacén</option>
                                        </select>
                                    </div>
                                    <div id="c-alm-unico" class="hidden items-center gap-2 px-3 py-2.5 bg-blue-50 border border-blue-200 rounded-xl text-sm">
                                        <i class="fas fa-warehouse text-blue-500"></i>
                                        <span id="c-alm-unico-nombre" class="font-medium text-blue-900 text-sm"></span>
                                        <span class="text-xs text-blue-400 ml-auto">Auto</span>
                                    </div>
                                    <div id="c-alm-empty" class="px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-400 italic">
                                        Selecciona primero la sucursal
                                    </div>
                                    <input type="hidden" name="almacen_id" id="c-alm-hidden" value="{{ old('almacen_id') }}">
                                    @error('almacen_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                        <button type="button" onclick="closeCreate()"
                                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 active:scale-95 transition-all shadow-md">
                            <i class="fas fa-user-plus"></i>Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ====================================================================
         MODAL: EDITAR USUARIO
    ===================================================================== --}}
    <div id="modal-editar" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeEdit()"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 py-8">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">

                <div class="bg-gradient-to-r from-amber-700 to-amber-500 rounded-t-2xl px-6 py-5 flex items-center gap-4">
                    <div id="e-avatar"
                         class="w-14 h-14 rounded-full bg-white/20 border-2 border-white/40 flex items-center justify-center text-white text-xl font-bold shrink-0">
                        <i class="fas fa-user text-white/60"></i>
                    </div>
                    <div>
                        <h2 class="text-white text-xl font-bold">Editar Usuario</h2>
                        <p class="text-amber-100 text-sm mt-0.5" id="e-subtitle">Modifica los datos del usuario</p>
                    </div>
                    <button onclick="closeEdit()" class="ml-auto text-white/70 hover:text-white"><i class="fas fa-times text-lg"></i></button>
                </div>

                <form id="e-form" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="px-6 py-5 space-y-5 max-h-[72vh] overflow-y-auto">

                        {{-- Info personal --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-id-card mr-1.5"></i>Información personal</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" id="e-name" required
                                           oninput="updateAvatar('e-avatar',this.value)"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" id="e-email" required
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">DNI</label>
                                    <input type="text" name="dni" id="e-dni" maxlength="20"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                    <input type="text" name="telefono" id="e-telefono"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado <span class="text-red-500">*</span></label>
                                    <select name="estado" id="e-estado" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Contraseña opcional --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1"><i class="fas fa-lock mr-1.5"></i>Cambiar contraseña</p>
                            <p class="text-xs text-gray-400 mb-3">Deja en blanco para conservar la contraseña actual</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                                    <div class="relative">
                                        <input type="password" name="password" id="e-pwd" minlength="8" placeholder="Mínimo 8 caracteres"
                                               class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                        <button type="button" onclick="togglePwd('e-pwd','e-eye1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i id="e-eye1" class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                                    <div class="relative">
                                        <input type="password" name="password_confirmation" id="e-pwd2" placeholder="Repite la contraseña"
                                               class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                        <button type="button" onclick="togglePwd('e-pwd2','e-eye2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i id="e-eye2" class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Rol --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-shield-halved mr-1.5"></i>Rol del usuario <span class="text-red-500">*</span></p>
                            <input type="hidden" name="role_id" id="e-role-hidden">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach($roles as $role)
                                @php
                                    $ecfg = [
                                        'Administrador' => ['icon'=>'fa-crown',        'border'=>'hover:border-purple-400','checked'=>'peer-checked:border-purple-500 peer-checked:bg-purple-50','badge'=>'bg-purple-500','text'=>'text-purple-700'],
                                        'Almacenero'    => ['icon'=>'fa-warehouse',     'border'=>'hover:border-blue-400',  'checked'=>'peer-checked:border-blue-500 peer-checked:bg-blue-50',  'badge'=>'bg-blue-500',  'text'=>'text-blue-700'],
                                        'Cajero'        => ['icon'=>'fa-cash-register', 'border'=>'hover:border-orange-400','checked'=>'peer-checked:border-orange-500 peer-checked:bg-orange-50','badge'=>'bg-orange-500','text'=>'text-orange-700'],
                                        'Proveedor'     => ['icon'=>'fa-truck',         'border'=>'hover:border-teal-400',  'checked'=>'peer-checked:border-teal-500 peer-checked:bg-teal-50',  'badge'=>'bg-teal-500',  'text'=>'text-teal-700'],
                                        'Tienda'        => ['icon'=>'fa-store',         'border'=>'hover:border-emerald-400','checked'=>'peer-checked:border-emerald-500 peer-checked:bg-emerald-50','badge'=>'bg-emerald-500','text'=>'text-emerald-700'],
                                        'Vendedor'      => ['icon'=>'fa-handshake',     'border'=>'hover:border-amber-400', 'checked'=>'peer-checked:border-amber-500 peer-checked:bg-amber-50', 'badge'=>'bg-amber-500', 'text'=>'text-amber-700'],
                                    ][$role->nombre] ?? ['icon'=>'fa-user','border'=>'hover:border-gray-400','checked'=>'peer-checked:border-gray-500 peer-checked:bg-gray-50','badge'=>'bg-gray-500','text'=>'text-gray-700'];
                                @endphp
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="_e_role_card" value="{{ $role->id }}" class="peer sr-only"
                                           onchange="document.getElementById('e-role-hidden').value=this.value">
                                    <div class="border-2 border-gray-200 rounded-xl p-3 text-center transition-all {{ $ecfg['border'] }} {{ $ecfg['checked'] }} peer-checked:shadow-md">
                                        <div class="w-9 h-9 rounded-full {{ $ecfg['badge'] }} mx-auto mb-1.5 flex items-center justify-center">
                                            <i class="fas {{ $ecfg['icon'] }} text-white text-sm"></i>
                                        </div>
                                        <p class="text-xs font-semibold {{ $ecfg['text'] }} leading-tight">{{ $role->nombre }}</p>
                                    </div>
                                    <div class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-amber-500 hidden peer-checked:flex items-center justify-center">
                                        <i class="fas fa-check text-white text-[8px]"></i>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <hr class="border-gray-100">

                        {{-- Asignación --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3"><i class="fas fa-building mr-1.5"></i>Asignación</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                                    <select id="e-suc" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white"
                                            onchange="onSucChange('e',this.value)">
                                        <option value="">— Sin sucursal —</option>
                                        @foreach($sucursales as $suc)
                                            <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén / Tienda</label>
                                    <div id="e-alm-wrap" class="hidden">
                                        <select id="e-alm-sel" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white"
                                                onchange="document.getElementById('e-alm-hidden').value=this.value">
                                            <option value="">Seleccione un almacén</option>
                                        </select>
                                    </div>
                                    <div id="e-alm-unico" class="hidden items-center gap-2 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-xl text-sm">
                                        <i class="fas fa-warehouse text-amber-500"></i>
                                        <span id="e-alm-unico-nombre" class="font-medium text-amber-900 text-sm"></span>
                                        <span class="text-xs text-amber-400 ml-auto">Auto</span>
                                    </div>
                                    <div id="e-alm-empty" class="px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-400 italic">
                                        Selecciona primero la sucursal
                                    </div>
                                    <input type="hidden" name="almacen_id" id="e-alm-hidden">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                        <button type="button" onclick="closeEdit()"
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

    {{-- ==================== SCRIPTS ==================== --}}
    @php
        $sucMap = $sucursales->mapWithKeys(fn($s) => [
            $s->id => $s->almacenes->map(fn($a) => ['id' => $a->id, 'nombre' => $a->nombre])->values()
        ]);
        $usersMap = $users->getCollection()->map(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'dni'        => $u->dni ?? '',
            'telefono'   => $u->telefono ?? '',
            'estado'     => $u->estado,
            'role_id'    => $u->role_id,
            'almacen_id' => $u->almacen_id,
            'sucursal_id'=> $u->almacen?->sucursal_id,
        ])->keyBy('id');
    @endphp

    <script>
    const sucMap   = @json($sucMap);
    const usersMap = @json($usersMap);
    const updateRouteBase = "{{ url('users') }}";

    /* ---- Utilidades ---- */
    function updateAvatar(id, val) {
        const el = document.getElementById(id);
        const ini = val.trim().split(/\s+/).slice(0,2).map(w => w[0]?.toUpperCase() ?? '').join('');
        el.innerHTML = ini ? ini : '<i class="fas fa-user text-white/60"></i>';
    }

    function togglePwd(inputId, iconId) {
        const inp = document.getElementById(inputId);
        const ico = document.getElementById(iconId);
        inp.type = inp.type === 'password' ? 'text' : 'password';
        ico.classList.toggle('fa-eye');
        ico.classList.toggle('fa-eye-slash');
    }

    /* ---- Cascada sucursal → almacén (genérica, prefijo c o e) ---- */
    function onSucChange(p, sucursalId) {
        const wrap   = document.getElementById(p+'-alm-wrap');
        const sel    = document.getElementById(p+'-alm-sel');
        const unico  = document.getElementById(p+'-alm-unico');
        const nombre = document.getElementById(p+'-alm-unico-nombre');
        const empty  = document.getElementById(p+'-alm-empty');
        const hidden = document.getElementById(p+'-alm-hidden');

        wrap.classList.add('hidden');
        unico.classList.add('hidden'); unico.classList.remove('flex');
        empty.classList.add('hidden');
        sel.innerHTML = '<option value="">Seleccione un almacén</option>';
        hidden.value  = '';

        if (!sucursalId) { empty.classList.remove('hidden'); return; }

        const almacenes = sucMap[sucursalId] || [];
        if (!almacenes.length)         { empty.classList.remove('hidden'); }
        else if (almacenes.length === 1) {
            hidden.value = almacenes[0].id;
            nombre.textContent = almacenes[0].nombre;
            unico.classList.remove('hidden'); unico.classList.add('flex');
        } else {
            almacenes.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id; opt.textContent = a.nombre;
                sel.appendChild(opt);
            });
            wrap.classList.remove('hidden');
        }
    }

    function setSucAlm(p, sucursalId, almacenId) {
        const sucSel = document.getElementById(p+'-suc');
        sucSel.value = sucursalId ?? '';
        onSucChange(p, sucursalId);
        if (almacenId) {
            const almSel = document.getElementById(p+'-alm-sel');
            const hidden = document.getElementById(p+'-alm-hidden');
            if (almSel) almSel.value = almacenId;
            hidden.value = almacenId;
        }
    }

    /* ---- Modal CREAR ---- */
    function openCreate() {
        document.getElementById('modal-crear').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => document.getElementById('c-name')?.focus(), 80);
    }
    function closeCreate() {
        document.getElementById('modal-crear').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    /* ---- Modal EDITAR ---- */
    function openEdit(userId) {
        const u = usersMap[userId];
        if (!u) return;

        // Header
        updateAvatar('e-avatar', u.name);
        document.getElementById('e-subtitle').textContent = 'Editando: ' + u.name;

        // Datos básicos
        document.getElementById('e-name').value     = u.name;
        document.getElementById('e-email').value    = u.email;
        document.getElementById('e-dni').value      = u.dni;
        document.getElementById('e-telefono').value = u.telefono;
        document.getElementById('e-estado').value   = u.estado;

        // Contraseña: limpiar
        document.getElementById('e-pwd').value  = '';
        document.getElementById('e-pwd2').value = '';

        // Rol: marcar la tarjeta correcta
        document.getElementById('e-role-hidden').value = u.role_id ?? '';
        document.querySelectorAll('input[name="_e_role_card"]').forEach(r => {
            r.checked = (parseInt(r.value) === parseInt(u.role_id));
        });

        // Sucursal / almacén
        setSucAlm('e', u.sucursal_id, u.almacen_id);

        // Acción del form
        document.getElementById('e-form').action = updateRouteBase + '/' + userId;

        document.getElementById('modal-editar').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => document.getElementById('e-name')?.focus(), 80);
    }
    function closeEdit() {
        document.getElementById('modal-editar').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeCreate(); closeEdit(); }
    });

    // Abrir modal crear si hubo errores de validación (store)
    @if($errors->any())
        openCreate();
        (function(){
            const nm = document.getElementById('c-name');
            if (nm && nm.value) updateAvatar('c-avatar', nm.value);
            const suc = document.getElementById('c-suc');
            if (suc && suc.value) {
                onSucChange('c', suc.value);
                const prev = '{{ old('almacen_id') }}';
                if (prev) {
                    const sel = document.getElementById('c-alm-sel');
                    const hid = document.getElementById('c-alm-hidden');
                    if (sel) sel.value = prev;
                    if (hid) hid.value = prev;
                }
            }
        })();
    @endif

    // Auto-cerrar flash de éxito
    setTimeout(() => document.getElementById('flash-ok')?.remove(), 4000);
    </script>
</body>
</html>
