<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura Remota de Caja</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 min-h-screen">
    {{-- Header --}}
    <div class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-0.5">
                <a href="{{ route('admin.cajas.dashboard') }}" class="hover:text-blue-600">Dashboard Cajas</a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Apertura Remota</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800">Apertura Remota de Caja</h1>
            <p class="text-sm text-gray-500">Abre una caja en cualquier sucursal desde aquí</p>
        </div>
        <a href="{{ route('admin.cajas.dashboard') }}"
           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
    </div>

    @if(session('error'))
        <div class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="p-6 max-w-2xl" x-data="aperturaRemotaApp()">

        <div class="bg-white rounded-xl shadow-sm p-6 space-y-5">

            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 flex gap-3">
                <i class="fas fa-info-circle mt-0.5 shrink-0"></i>
                <div>
                    Esta función abre una caja en nombre del cajero seleccionado.
                    Quedará registrado como apertura remota por <strong>{{ auth()->user()->name }}</strong>.
                </div>
            </div>

            <form method="POST" action="{{ route('admin.cajas.apertura-remota.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Sucursal <span class="text-red-500">*</span>
                    </label>
                    <select name="sucursal_id" required x-model="sucursalId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Selecciona una sucursal —</option>
                        @foreach($sucursales as $s)
                            <option value="{{ $s->id }}">
                                {{ $s->nombre }} ({{ $s->almacen?->nombre ?? 'Sin almacén' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Cajero <span class="text-red-500">*</span>
                    </label>
                    <select name="user_id" required x-model="userId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Selecciona un cajero —</option>
                        <template x-for="u in usuariosFiltrados" :key="u.id">
                            <option :value="u.id" x-text="u.name"></option>
                        </template>
                    </select>
                    <p x-show="sucursalId && usuariosFiltrados.length === 0"
                       class="text-xs text-orange-500 mt-1">
                        <i class="fas fa-exclamation-triangle"></i>
                        No hay usuarios asignados al almacén de esta sucursal.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Monto Inicial (S/) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium text-sm">S/</span>
                        <input type="number" name="monto_inicial" min="0" step="0.50" required
                               value="{{ old('monto_inicial', '0.00') }}"
                               class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500"
                              placeholder="Motivo de la apertura remota (opcional)...">{{ old('observaciones') }}</textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ route('admin.cajas.dashboard') }}"
                       class="flex-1 py-2.5 text-center border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                        Cancelar
                    </a>
                    <button type="submit" :disabled="!sucursalId || !userId"
                            class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-cash-register mr-2"></i> Abrir Caja Remotamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function aperturaRemotaApp() {
    return {
        sucursalId: '{{ old('sucursal_id') }}',
        userId:     '{{ old('user_id') }}',
        sucursales: @json($sucursales->map(fn($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'almacen_id' => $s->almacen_id])),
        usuarios:   @json($usuarios->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'almacen_id' => $u->almacen_id])),
        get usuariosFiltrados() {
            if (!this.sucursalId) return this.usuarios;
            const suc = this.sucursales.find(s => s.id == this.sucursalId);
            if (!suc) return this.usuarios;
            return this.usuarios.filter(u => u.almacen_id == suc.almacen_id);
        }
    };
}
</script>
</body>
</html>
