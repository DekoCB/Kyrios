<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nuevo Cliente - Sistema de Importaciones</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header
            title="Registrar Nuevo Cliente"
            subtitle="Complete el formulario para agregar un nuevo cliente al sistema"
        />
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <a href="{{ route('clientes.index') }}" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h2 class="text-2xl font-bold text-gray-800">Registrar Cliente</h2>
            </div>

            {{-- Si hay errores de validación el form ya se mostró, necesitamos mantenerlo visible --}}
            @php $hayErrores = $errors->any() || old('nombre'); @endphp

            <div class="bg-white rounded-xl shadow-md p-6" x-data="clienteForm()">

                {{-- ── PASO 1: BÚSQUEDA ── --}}
                <div class="mb-6 p-4 bg-blue-50 rounded-xl border border-blue-200">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-semibold text-blue-800">
                            <i class="fas fa-search mr-1"></i>Buscar por documento
                        </label>
                        <button type="button"
                                @click="mostrarForm()"
                                x-show="!formVisible"
                                class="text-xs text-gray-500 hover:text-blue-700 underline underline-offset-2">
                            Ingresar manualmente
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <select x-model="tipoBuscar"
                                class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                        </select>
                        <input type="text"
                               x-model="numeroBuscar"
                               :maxlength="tipoBuscar === 'DNI' ? 8 : 11"
                               placeholder="Número de documento"
                               @keydown.enter.prevent="consultarDocumento()"
                               class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                        <button type="button"
                                @click="consultarDocumento()"
                                :disabled="cargando || numeroBuscar.length < 8"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 transition">
                            <span x-show="!cargando"><i class="fas fa-search mr-1"></i>Buscar</span>
                            <span x-show="cargando"><i class="fas fa-spinner fa-spin mr-1"></i>Buscando...</span>
                        </button>
                    </div>

                    {{-- Validación longitud DNI --}}
                    <p x-show="tipoBuscar === 'DNI' && numeroBuscar.length > 0 && numeroBuscar.length < 8"
                       class="text-xs text-red-500 mt-1.5">
                        El DNI debe tener 8 dígitos numéricos.
                    </p>

                    {{-- Mensaje resultado --}}
                    <div x-show="mensaje" x-transition class="mt-2 flex items-start gap-2 text-sm rounded-lg px-3 py-2"
                         :class="exito ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'">
                        <i class="mt-0.5 text-xs" :class="exito ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
                        <span x-text="mensaje"></span>
                    </div>
                </div>

                {{-- ── PASO 2: FORMULARIO (oculto hasta buscar o "Ingresar manualmente") ── --}}
                <div x-show="formVisible"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     @if(!$hayErrores) style="display:none" @endif>

                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">2</span>
                        <p class="text-sm font-semibold text-gray-700">Completa los datos del cliente</p>
                        <span x-show="!encontrado && formVisible"
                              class="ml-auto text-xs text-orange-600 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-full">
                            <i class="fas fa-pencil-alt mr-1"></i>Ingreso manual
                        </span>
                        <span x-show="encontrado"
                              class="ml-auto text-xs text-green-600 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i>Datos autocargados
                        </span>
                    </div>

                    <form action="{{ route('clientes.store') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo Documento *</label>
                                <select name="tipo_documento" x-model="tipoDocumento"
                                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="CE">CE</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número Documento *</label>
                                <input type="text" name="numero_documento" x-model="numeroDocumento" maxlength="11" required
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 @error('numero_documento') border-red-500 @enderror">
                                @error('numero_documento') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre / Razón Social *</label>
                                <input type="text" name="nombre" x-model="nombre" required
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 @error('nombre') border-red-500 @enderror"
                                       placeholder="Nombre completo o razón social">
                                @error('nombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <input type="text" name="direccion" x-model="direccion"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                                       placeholder="Av. / Jr. / Calle...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                                <select name="departamento" x-model="departamento"
                                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 bg-white">
                                    <option value="">— Seleccionar —</option>
                                    @foreach(['AMAZONAS','ÁNCASH','APURÍMAC','AREQUIPA','AYACUCHO','CAJAMARCA','CALLAO','CUSCO','HUANCAVELICA','HUÁNUCO','ICA','JUNÍN','LA LIBERTAD','LAMBAYEQUE','LIMA','LORETO','MADRE DE DIOS','MOQUEGUA','PASCO','PIURA','PUNO','SAN MARTÍN','TACNA','TUMBES','UCAYALI'] as $dep)
                                        <option value="{{ $dep }}">{{ $dep }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
                                <input type="text" name="provincia" x-model="provincia" maxlength="100"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                                       placeholder="Provincia">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
                                <input type="text" name="distrito" x-model="distrito" maxlength="100"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                                       placeholder="Distrito">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Ubigeo
                                    <span class="text-gray-400 font-normal">(código INEI, 6 dígitos)</span>
                                </label>
                                <input type="text" name="ubigeo" x-model="ubigeo" maxlength="6" inputmode="numeric" pattern="\d{0,6}"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 font-mono"
                                       placeholder="Ej: 150101">
                                <p class="text-[11px] text-gray-400 mt-1">Necesario para que las guías de remisión a este cliente lleguen correctamente a SUNAT.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input type="text" name="telefono" maxlength="20"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                                       value="{{ old('telefono') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email"
                                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200"
                                       value="{{ old('email') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <select name="estado"
                                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                            <a href="{{ route('clientes.index') }}"
                               class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-lg text-sm transition">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg text-sm transition">
                                <i class="fas fa-save mr-2"></i>Guardar
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Placeholder cuando aún no se buscó --}}
                <div x-show="!formVisible"
                     @if($hayErrores) style="display:none" @endif
                     class="text-center py-10 text-gray-400">
                    <i class="fas fa-user-plus text-4xl text-gray-200 block mb-3"></i>
                    <p class="text-sm">Busca el documento del cliente arriba para continuar</p>
                    <p class="text-xs mt-1 text-gray-300">o usa <span class="underline cursor-pointer hover:text-blue-500" @click="mostrarForm()">Ingresar manualmente</span></p>
                </div>

            </div>
        </div>
    </div>

    <script>
    function clienteForm() {
        return {
            tipoBuscar:      'DNI',
            numeroBuscar:    '',
            tipoDocumento:   '{{ old("tipo_documento", "DNI") }}',
            numeroDocumento: '{{ old("numero_documento") }}',
            nombre:          '{{ old("nombre") }}',
            direccion:       '{{ old("direccion") }}',
            distrito:        '{{ old("distrito") }}',
            provincia:       '{{ old("provincia") }}',
            departamento:    '{{ old("departamento") }}',
            ubigeo:          '{{ old("ubigeo") }}',
            cargando:        false,
            mensaje:         '',
            exito:           false,
            encontrado:      false,
            formVisible:     {{ $hayErrores ? 'true' : 'false' }},

            mostrarForm() {
                this.formVisible = true;
            },

            async consultarDocumento() {
                if (this.numeroBuscar.length < 8) return;
                this.cargando = true;
                this.mensaje  = '';
                try {
                    const res = await fetch('{{ route("clientes.consultar-documento") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ tipo: this.tipoBuscar, numero: this.numeroBuscar })
                    });
                    const data = await res.json();

                    if (data.error) {
                        this.tipoDocumento   = this.tipoBuscar;
                        this.numeroDocumento = this.numeroBuscar;
                        this.nombre          = '';
                        this.mensaje         = 'No encontrado. Completa los datos manualmente.';
                        this.exito           = false;
                        this.encontrado      = false;
                        this.formVisible     = true;
                    } else {
                        this.tipoDocumento   = this.tipoBuscar;
                        this.numeroDocumento = this.numeroBuscar;
                        this.nombre          = data.nombre || data.razon_social || '';
                        this.direccion       = data.direccion    || '';
                        this.distrito        = data.distrito     || '';
                        this.provincia       = data.provincia    || '';
                        this.departamento    = data.departamento || '';
                        this.ubigeo          = data.ubigeo       || '';
                        this.mensaje         = 'Datos encontrados y autocargados.';
                        this.exito           = true;
                        this.encontrado      = true;
                        this.formVisible     = true;
                    }
                } catch (e) {
                    this.mensaje     = 'Error de conexión. Intenta de nuevo.';
                    this.exito       = false;
                    this.formVisible = false;
                }
                this.cargando = false;
            }
        }
    }
    </script>
</body>
</html>
