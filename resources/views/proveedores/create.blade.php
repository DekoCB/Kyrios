<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Proveedor - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Nuevo Proveedor"
            subtitle="Registra un nuevo proveedor en el sistema"
        />

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="proveedorForm()">
                {{-- Búsqueda SUNAT --}}
                <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-search mr-2"></i>Búsqueda por RUC (SUNAT)
                    </h2>
                </div>
                <div class="p-6 bg-blue-50 border-b border-blue-100">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Consultar datos del proveedor</label>
                    <div class="flex gap-3">
                        <input type="text" x-model="rucBuscar" maxlength="11" placeholder="Ingrese RUC de 11 dígitos"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <button type="button" @click="consultarSunat()" :disabled="cargando"
                                class="bg-blue-900 hover:bg-blue-800 text-white px-6 py-2 rounded-lg font-semibold disabled:opacity-50 transition-colors">
                            <span x-show="!cargando"><i class="fas fa-search mr-2"></i>Buscar en SUNAT</span>
                            <span x-show="cargando"><i class="fas fa-spinner fa-spin mr-2"></i>Buscando...</span>
                        </button>
                    </div>
                    <div x-show="mensajeSunat" class="mt-3">
                        <p x-text="mensajeSunat"
                           class="text-sm font-medium px-3 py-2 rounded-lg"
                           :class="sunatExito ? 'text-green-700 bg-green-50 border border-green-200' : 'text-red-700 bg-red-50 border border-red-200'"></p>
                        <div x-show="sunatExito" class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span x-show="estadoRuc" class="px-2 py-1 rounded-full font-medium"
                                  :class="estadoRuc === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                <i class="fas fa-circle mr-1"></i>
                                Estado: <span x-text="estadoRuc"></span>
                            </span>
                            <span x-show="condicionRuc" class="px-2 py-1 rounded-full font-medium"
                                  :class="condicionRuc === 'HABIDO' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'">
                                <i class="fas fa-check-circle mr-1"></i>
                                <span x-text="condicionRuc"></span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Información del Proveedor --}}
                <div class="bg-blue-900 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-building mr-2"></i>Información del Proveedor
                    </h2>
                </div>

                <form action="{{ route('proveedores.store') }}" method="POST" class="p-6">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                RUC <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="ruc" x-model="ruc" maxlength="11" required
                                   placeholder="11 dígitos"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('ruc') border-red-500 @enderror"
                                   value="{{ old('ruc') }}">
                            @error('ruc')
                                <p class="text-red-600 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Razón Social <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="razon_social" x-model="razonSocial" required
                                   placeholder="Nombre de la empresa"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('razon_social') border-red-500 @enderror"
                                   value="{{ old('razon_social') }}">
                            @error('razon_social')
                                <p class="text-red-600 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Comercial</label>
                            <input type="text" name="nombre_comercial" x-model="nombreComercial"
                                   placeholder="Nombre comercial (opcional)"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('nombre_comercial') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                            <input type="text" name="direccion" x-model="direccion"
                                   placeholder="Dirección fiscal"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('direccion') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                            <input type="text" name="telefono" maxlength="20"
                                   placeholder="Número de teléfono"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('telefono') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email"
                                   placeholder="correo@ejemplo.com"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('email') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de Contacto</label>
                            <input type="text" name="contacto_nombre"
                                   placeholder="Persona de contacto"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="{{ old('contacto_nombre') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select name="estado"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="activo" {{ old('estado', 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="inactivo" {{ old('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200 mt-6">
                        <a href="{{ route('proveedores.index') }}"
                           class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition-colors">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                        <button type="submit"
                                class="px-6 py-3 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800 transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar Proveedor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function proveedorForm() {
        return {
            rucBuscar: '',
            ruc: '{{ old('ruc') }}',
            razonSocial: '{{ old('razon_social') }}',
            nombreComercial: '{{ old('nombre_comercial') }}',
            direccion: '{{ old('direccion') }}',
            cargando: false,
            mensajeSunat: '',
            sunatExito: false,
            estadoRuc: '',
            condicionRuc: '',

            async consultarSunat() {
                const rucLimpio = this.rucBuscar.trim();
                if (rucLimpio.length !== 11 || !/^\d{11}$/.test(rucLimpio)) {
                    this.mensajeSunat = 'El RUC debe tener exactamente 11 dígitos numéricos.';
                    this.sunatExito = false;
                    return;
                }
                this.cargando = true;
                this.mensajeSunat = '';
                this.estadoRuc   = '';
                this.condicionRuc = '';
                try {
                    const response = await fetch('{{ route("proveedores.consultar-sunat") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ ruc: rucLimpio })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.ruc            = data.data.ruc;
                        this.razonSocial    = data.data.razon_social;
                        this.nombreComercial = data.data.nombre_comercial || '';
                        this.direccion      = data.data.direccion || '';
                        this.estadoRuc    = data.data.estado     || '';
                        this.condicionRuc = data.data.condicion || '';
                        this.mensajeSunat   = '✓ Datos encontrados en SUNAT';
                        this.sunatExito     = true;
                    } else {
                        this.mensajeSunat = data.message || 'RUC no encontrado.';
                        this.sunatExito   = false;
                    }
                } catch (e) {
                    this.mensajeSunat = 'Error de conexión con el servicio de consulta.';
                    this.sunatExito   = false;
                }
                this.cargando = false;
            }
        }
    }
    </script>
</body>
</html>
