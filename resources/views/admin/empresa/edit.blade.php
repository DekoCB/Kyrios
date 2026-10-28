<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configuración de Empresa</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="empresaForm()">
    <x-header title="Configuración de Empresa" subtitle="Datos generales, logos y configuración SUNAT" />

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button @click="tab = 'datos'"
                    :class="tab === 'datos' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors">
                    <i class="fas fa-building"></i> Datos Iniciales
                </button>
                <button @click="tab = 'graficos'"
                    :class="tab === 'graficos' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors">
                    <i class="fas fa-image"></i> Gráficos / Logos
                </button>
                <button @click="tab = 'redes'"
                    :class="tab === 'redes' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors">
                    <i class="fas fa-share-alt"></i> Redes Sociales
                </button>
                <button @click="tab = 'api'"
                    :class="tab === 'api' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors">
                    <i class="fas fa-plug"></i> Integración API / SUNAT
                </button>
            </nav>
        </div>

        <form action="{{ route('admin.empresa.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- TAB: DATOS INICIALES --}}
            <div x-show="tab === 'datos'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- RUC con botón SUNAT --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">RUC *</label>
                        <div class="flex gap-2">
                            <input type="text" name="ruc" x-model="ruc" maxlength="11"
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('ruc') border-red-500 @enderror"
                                placeholder="20XXXXXXXXX"
                                @keydown.enter.prevent="consultarRuc()">
                            <button type="button" @click="consultarRuc()"
                                :disabled="buscando || ruc.length !== 11"
                                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg transition-colors whitespace-nowrap">
                                <i class="fas fa-search" x-show="!buscando"></i>
                                <i class="fas fa-spinner fa-spin" x-show="buscando"></i>
                                <span x-text="buscando ? 'Consultando...' : 'Buscar en SUNAT'"></span>
                            </button>
                        </div>
                        @error('ruc')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

                        {{-- Alerta resultado SUNAT --}}
                        <div x-show="sunatMsg" x-cloak class="mt-2 px-3 py-2 rounded-lg text-sm flex items-center gap-2"
                            :class="sunatOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
                            <i :class="sunatOk ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
                            <span x-text="sunatMsg"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social *</label>
                        <input type="text" name="razon_social" x-model="razon_social" maxlength="200"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('razon_social') border-red-500 @enderror">
                        @error('razon_social')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" x-model="nombre_comercial" maxlength="200"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Régimen Tributario</label>
                        <select name="regimen" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            @foreach(['RER' => 'Régimen Especial de Renta', 'RG' => 'Régimen General', 'RMT' => 'Régimen MYPE Tributario', 'RUS' => 'Nuevo RUS'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('regimen', $empresa->regimen) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Fiscal</label>
                        <input type="text" name="direccion" x-model="direccion" maxlength="300"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" name="departamento" x-model="departamento"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
                        <input type="text" name="provincia" x-model="provincia"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
                        <input type="text" name="distrito" x-model="distrito"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
                        <input type="text" name="ubigeo" x-model="ubigeo" maxlength="6"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                        <input type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}" maxlength="20"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $empresa->email) }}" maxlength="150"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sitio Web</label>
                        <input type="url" name="web" value="{{ old('web', $empresa->web) }}" maxlength="200"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="https://www.ejemplo.com">
                    </div>
                </div>
            </div>

            {{-- TAB: GRÁFICOS --}}
            <div x-show="tab === 'graficos'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Logo para fondo oscuro</label>
                        <div class="mb-3 p-3 rounded-lg border border-gray-200 inline-flex" style="background:#44413A;">
                            <img src="{{ $empresa->logo_url }}" alt="Logo fondo oscuro" class="h-20 object-contain">
                        </div>
                        <p class="text-xs text-gray-500 mb-2">{{ $empresa->logo_path ? 'Logo subido por la empresa.' : 'Logo oficial de Kyrios (letras blancas). Se usa hasta que suba otro.' }}</p>
                        <input type="file" name="logo" accept="image/*"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">PNG con fondo transparente y letras claras — máx. 2MB. Aparece en la barra lateral y en la pantalla de acceso.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Logo para fondo claro / Comprobantes</label>
                        <div class="mb-3 p-3 bg-white rounded-lg border border-gray-200 inline-flex">
                            <img src="{{ $empresa->logo_pdf_url }}" alt="Logo comprobantes" class="h-20 object-contain">
                        </div>
                        <p class="text-xs text-gray-500 mb-2">{{ $empresa->logo_pdf_path ? 'Logo subido por la empresa.' : 'Logo oficial de Kyrios (letras oscuras). Se usa hasta que suba otro.' }}</p>
                        <input type="file" name="logo_pdf" accept="image/*"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">PNG con fondo transparente y letras oscuras — máx. 2MB. Aparece en boletas, facturas, notas de venta, cotizaciones y guías.</p>
                    </div>
                </div>
            </div>

            {{-- TAB: REDES SOCIALES --}}
            <div x-show="tab === 'redes'" class="p-6">
                <div class="space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0">
                            <i class="fab fa-facebook-f text-white"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Facebook</label>
                            <input type="text" name="facebook" value="{{ old('facebook', $empresa->facebook) }}" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                placeholder="https://facebook.com/mi-empresa">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center flex-shrink-0">
                            <i class="fab fa-instagram text-white"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                            <input type="text" name="instagram" value="{{ old('instagram', $empresa->instagram) }}" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                placeholder="https://instagram.com/mi-empresa">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-black flex items-center justify-center flex-shrink-0">
                            <i class="fab fa-tiktok text-white"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">TikTok</label>
                            <input type="text" name="tiktok" value="{{ old('tiktok', $empresa->tiktok) }}" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                placeholder="https://tiktok.com/@mi-empresa">
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: INTEGRACIÓN API --}}
            <div x-show="tab === 'api'" class="p-6 space-y-6">

                {{-- 1. Credenciales SUNAT --}}
                <div>
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-landmark text-amber-600"></i> Credenciales SUNAT (Clave SOL)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SOL</label>
                            <input type="text" name="sunat_usuario_sol" value="{{ old('sunat_usuario_sol', $empresa->sunat_usuario_sol) }}" maxlength="100"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                placeholder="MODDATOS">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Clave SOL</label>
                            <input type="password" name="sunat_clave_sol" maxlength="100"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                placeholder="Dejar vacío para no cambiar">
                            <p class="text-xs text-gray-400 mt-1">Dejar vacío para mantener la clave actual.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modo SUNAT</label>
                            <select name="sunat_modo" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="beta" {{ old('sunat_modo', $empresa->sunat_modo) === 'beta' ? 'selected' : '' }}>Beta / Pruebas</option>
                                <option value="produccion" {{ old('sunat_modo', $empresa->sunat_modo) === 'produccion' ? 'selected' : '' }}>Producción</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- 2. Certificado Digital --}}
                <div class="border-t pt-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-certificate text-purple-600"></i> Certificado Digital (.PFX)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Certificado (.pfx)</label>
                            @if($empresa->certificado_pfx_path)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-sm">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <span class="text-green-700 font-medium">Certificado cargado</span>
                                    <span class="text-green-500 text-xs font-mono">{{ basename($empresa->certificado_pfx_path) }}</span>
                                </div>
                            @endif
                            <input type="file" name="certificado_pfx" accept=".pfx,.p12"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            <p class="text-xs text-gray-400 mt-1">Archivo .pfx o .p12 del certificado digital de SUNAT. Se reemplazará el anterior si sube uno nuevo.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña del Certificado</label>
                            <input type="password" name="certificado_pfx_password" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500"
                                placeholder="Dejar vacío para no cambiar">
                            <p class="text-xs text-gray-400 mt-1">Contraseña del archivo .pfx. Dejar vacío para mantener la actual.</p>
                        </div>
                    </div>
                </div>

                {{-- 3. Credenciales GRE (Guías de Remisión Electrónicas) --}}
                <div class="border-t pt-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-truck text-teal-600"></i> Credenciales GRE (Guías de Remisión)
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Credenciales API para el envío de Guías de Remisión Electrónicas. En modo <strong>Beta</strong> se usan credenciales de prueba automáticamente.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client ID (GRE)</label>
                            <input type="text" name="gre_client_id" value="{{ old('gre_client_id', $empresa->gre_client_id) }}" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-teal-500"
                                placeholder="{{ ($empresa->sunat_modo ?? 'beta') !== 'produccion' ? 'Opcional en modo Beta' : 'Requerido en producción' }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret (GRE)</label>
                            <input type="password" name="gre_client_secret" maxlength="200"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-teal-500"
                                placeholder="Dejar vacío para no cambiar">
                        </div>
                    </div>
                </div>

                {{-- 4. Estado de configuración --}}
                <div class="border-t pt-5">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Estado de configuración</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg border {{ $empresa->tieneCredencialesSunat() ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                            <i class="fas {{ $empresa->tieneCredencialesSunat() ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-400' }}"></i>
                            <span class="text-sm {{ $empresa->tieneCredencialesSunat() ? 'text-green-700' : 'text-red-600' }}">Clave SOL</span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg border {{ $empresa->tieneCertificado() ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                            <i class="fas {{ $empresa->tieneCertificado() ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-400' }}"></i>
                            <span class="text-sm {{ $empresa->tieneCertificado() ? 'text-green-700' : 'text-red-600' }}">Certificado Digital</span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg border bg-blue-50 border-blue-200">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span class="text-sm text-blue-700">Modo: {{ ($empresa->sunat_modo ?? 'beta') === 'produccion' ? 'Producción' : 'Beta / Pruebas' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botón guardar --}}
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function empresaForm() {
    return {
        tab: 'datos',
        buscando: false,
        sunatMsg: '',
        sunatOk: false,

        // Campos reactivos para autocompletar desde SUNAT
        ruc:             @json(old('ruc', $empresa->ruc) ?? ''),
        razon_social:    @json(old('razon_social', $empresa->razon_social) ?? ''),
        nombre_comercial:@json(old('nombre_comercial', $empresa->nombre_comercial) ?? ''),
        direccion:       @json(old('direccion', $empresa->direccion) ?? ''),
        departamento:    @json(old('departamento', $empresa->departamento) ?? ''),
        provincia:       @json(old('provincia', $empresa->provincia) ?? ''),
        distrito:        @json(old('distrito', $empresa->distrito) ?? ''),
        ubigeo:          @json(old('ubigeo', $empresa->ubigeo) ?? ''),

        async consultarRuc() {
            if (this.ruc.length !== 11) return;
            this.buscando = true;
            this.sunatMsg = '';

            try {
                const res = await fetch(`/admin/consultar-ruc/${this.ruc}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();

                if (!res.ok) {
                    this.sunatOk = false;
                    this.sunatMsg = data.error ?? 'Error al consultar SUNAT.';
                    return;
                }

                // Autocompletar campos
                this.razon_social     = data.razon_social     || this.razon_social;
                this.nombre_comercial = data.nombre_comercial || this.nombre_comercial;
                this.direccion        = data.direccion        || this.direccion;
                this.departamento     = data.departamento     || this.departamento;
                this.provincia        = data.provincia        || this.provincia;
                this.distrito         = data.distrito         || this.distrito;
                this.ubigeo           = data.ubigeo           || this.ubigeo;

                const estado = data.estado ? ` · Estado: ${data.estado}` : '';
                const condicion = data.condicion ? ` · Condición: ${data.condicion}` : '';
                this.sunatOk  = true;
                this.sunatMsg = `Datos cargados correctamente${estado}${condicion}`;
            } catch (e) {
                this.sunatOk  = false;
                this.sunatMsg = 'Error de conexión al consultar SUNAT.';
            } finally {
                this.buscando = false;
            }
        }
    }
}
</script>
</body>
</html>
