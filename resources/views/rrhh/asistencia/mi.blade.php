@extends('layouts.modulo')
@section('titulo', 'Mi asistencia')
@section('subtitulo', 'Marca tu entrada y tu salida del día con una selfie y tu ubicación')

@section('contenido')
    @if(!$empleado)
        <div class="max-w-xl bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5 text-sm">
            <i class="fas fa-circle-info mr-1"></i> Tu usuario todavía no está vinculado a una ficha de personal. Pide a Administración que lo vincule para poder marcar asistencia.
        </div>
    @else
        @php $sinMarcar = $hoy && in_array($hoy->estado, ['permiso', 'vacaciones', 'descanso_medico', 'licencia']); @endphp
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center"
                     x-data="marcacion({ exigir: @js($exigir), url: @js(route('rrhh.marcar')) })">
                    <p class="text-sm text-gray-500">{{ now()->isoFormat('dddd D [de] MMMM') }}</p>
                    <p class="text-4xl font-bold text-gray-900 my-2" x-data="{ h: '' }" x-init="const t = () => h = new Date().toLocaleTimeString('es-PE', {hour: '2-digit', minute: '2-digit', hour12: false}); t(); setInterval(t, 20000)" x-text="h"></p>
                    <p class="text-xs text-gray-400 mb-5">Tu jornada: {{ substr($empleado->hora_entrada, 0, 5) }} a {{ substr($empleado->hora_salida, 0, 5) }} · tolerancia {{ $empleado->tolerancia_min ?? \App\Services\AsistenciaService::tolerancia() }} min</p>

                    @if($sinMarcar)
                        <p class="rounded-lg bg-sky-50 text-sky-800 text-sm px-3 py-2">Hoy figuras con <strong>{{ mb_strtolower($hoy->estado_nombre) }}</strong>.</p>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="abrir('entrada')" @disabled($hoy?->hora_entrada) class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed"><i class="fas fa-right-to-bracket mr-1"></i> Marcar entrada</button>
                            <button type="button" @click="abrir('salida')" @disabled(!$hoy?->hora_entrada || $hoy?->hora_salida) class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"><i class="fas fa-right-from-bracket mr-1"></i> Marcar salida</button>
                        </div>
                        @if($exigir)
                            <p class="text-xs text-gray-400 mt-3"><i class="fas fa-camera mr-1"></i>Se te pedirá una selfie y permiso para saber dónde estás.@if($lugares === 0) <span class="block text-amber-700 mt-1">Aún no hay lugares de trabajo registrados: tu marcación quedará pendiente de revisión.</span>@endif</p>
                        @endif
                        @if($hoy?->hora_entrada)
                            <p class="mt-4 text-sm text-gray-600">Entrada <strong>{{ substr($hoy->hora_entrada, 0, 5) }}</strong>@if($hoy->hora_salida) · Salida <strong>{{ substr($hoy->hora_salida, 0, 5) }}</strong>@endif
                                @if($hoy->minutos_tarde)<span class="block text-amber-700 mt-1">Tardanza de {{ $hoy->minutos_tarde }} min</span>@endif
                                @if($hoy->minutos_extra)<span class="block text-emerald-700 mt-1">Horas extra: {{ intdiv($hoy->minutos_extra, 60) }} h {{ $hoy->minutos_extra % 60 }} min</span>@endif</p>
                        @endif
                    @endif

                    {{-- Verificación: selfie y ubicación --}}
                    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="marca-titulo" @keydown.escape.window="cerrar()">
                        <div class="absolute inset-0 bg-black/60" @click="cerrar()"></div>
                        <div class="relative bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl p-5 text-left max-h-[95vh] overflow-y-auto">
                            <h3 id="marca-titulo" class="font-semibold text-gray-900 mb-1" x-text="accion === 'entrada' ? 'Marcar entrada' : 'Marcar salida'"></h3>
                            <p class="text-xs text-gray-500 mb-3">Tómate una selfie: se guarda junto con tu ubicación para validar que estás en tu lugar de trabajo.</p>

                            <div class="relative bg-gray-900 rounded-xl overflow-hidden aspect-[3/4] max-h-80 mx-auto">
                                <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover -scale-x-100" x-show="!foto && camara"></video>
                                <img x-show="foto" :src="vista" alt="Tu selfie" class="w-full h-full object-cover -scale-x-100">
                                <div x-show="!foto && !camara" class="absolute inset-0 grid place-items-center text-center text-gray-300 text-sm p-4" x-text="mensajeCamara || 'Abriendo la cámara…'"></div>
                            </div>
                            <canvas x-ref="lienzo" class="hidden"></canvas>

                            {{-- Si la cámara no se puede abrir, se usa la cámara del teléfono con un selector de archivo --}}
                            <div x-show="!camara && !foto && errorCamara" x-cloak class="mt-3">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Tomar la selfie con la cámara del teléfono</label>
                                <input type="file" accept="image/*" capture="user" @change="elegirArchivo($event)" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold">
                            </div>

                            <div class="mt-3 text-sm flex items-start gap-2" :class="ubicacion ? 'text-emerald-700' : (errorUbicacion ? 'text-red-600' : 'text-gray-500')">
                                <i class="fas mt-0.5" :class="ubicacion ? 'fa-location-dot' : (errorUbicacion ? 'fa-triangle-exclamation' : 'fa-spinner fa-spin')"></i>
                                <span x-text="ubicacion ? 'Ubicación obtenida (precisión de unos ' + Math.round(ubicacion.precision) + ' m).' : (errorUbicacion || 'Obteniendo tu ubicación…')"></span>
                            </div>
                            <button type="button" x-show="errorUbicacion" x-cloak @click="pedirUbicacion()" class="mt-1 text-xs text-blue-700 hover:underline">Reintentar la ubicación</button>

                            <form x-ref="envio" method="POST" action="{{ route('rrhh.marcar') }}" enctype="multipart/form-data" class="mt-4">
                                @csrf
                                <input type="hidden" name="accion" :value="accion">
                                <input type="hidden" name="latitud" :value="ubicacion?.lat ?? ''">
                                <input type="hidden" name="longitud" :value="ubicacion?.lng ?? ''">
                                <input type="hidden" name="precision" :value="ubicacion?.precision ?? ''">
                                <input type="file" name="foto" x-ref="archivo" class="hidden" accept="image/*">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-show="!foto" @click="tomar()" :disabled="!camara" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-gray-900 hover:bg-black disabled:opacity-40"><i class="fas fa-camera mr-1"></i> Tomar foto</button>
                                    <button type="button" x-show="foto" x-cloak @click="repetir()" class="py-2.5 px-4 rounded-xl text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50">Repetir</button>
                                    <button type="button" @click="enviar()" :disabled="!puede" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-check mr-1"></i> Confirmar</button>
                                    <button type="button" @click="cerrar()" class="w-full py-2 text-sm text-gray-500 hover:text-gray-800">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <a href="{{ route('rrhh.mis-solicitudes') }}" class="block bg-white rounded-xl shadow-sm p-4 text-sm text-blue-700 hover:bg-blue-50"><i class="fas fa-umbrella-beach mr-2"></i>Pedir permiso o vacaciones</a>
            </div>

            <section class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
                <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Mi historial (últimos 31 registros)</h2>
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-left">Entrada</th><th class="px-4 py-2 text-left">Salida</th><th class="px-4 py-2 text-right">Horas</th><th class="px-4 py-2 text-right">Extra</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($historial as $a)
                            <tr>
                                <td class="px-4 py-2">{{ $a->fecha->isoFormat('ddd D/M') }}</td>
                                <td class="px-4 py-2"><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Asistencia::ESTADOS[$a->estado][1] }}">{{ $a->estado_nombre }}</span></td>
                                <td class="px-4 py-2">{{ $a->hora_entrada ? substr($a->hora_entrada, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2">{{ $a->hora_salida ? substr($a->hora_salida, 0, 5) : '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $a->horas ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-emerald-700">{{ $a->minutos_extra ? intdiv($a->minutos_extra, 60) . ':' . str_pad((string) ($a->minutos_extra % 60), 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aún no tienes registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </section>
        </div>

        <script>
            function marcacion(cfg) {
                return {
                    exigir: cfg.exigir, abierto: false, accion: 'entrada',
                    camara: false, errorCamara: false, mensajeCamara: '', flujo: null,
                    foto: null, vista: null,
                    ubicacion: null, errorUbicacion: '',
                    get puede() { return this.exigir ? !!(this.foto && this.ubicacion) : true; },
                    abrir(accion) {
                        this.accion = accion; this.abierto = true; this.foto = null; this.vista = null;
                        if (!this.exigir) { this.$nextTick(() => this.$refs.envio.submit()); return; }
                        this.abrirCamara(); this.pedirUbicacion();
                    },
                    cerrar() { this.detenerCamara(); this.abierto = false; },
                    async abrirCamara() {
                        this.camara = false; this.errorCamara = false; this.mensajeCamara = '';
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.errorCamara = true; this.mensajeCamara = 'Este navegador no deja abrir la cámara aquí.'; return;
                        }
                        try {
                            this.flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 1280 } }, audio: false });
                            this.$refs.video.srcObject = this.flujo; this.camara = true;
                        } catch (e) {
                            this.errorCamara = true; this.mensajeCamara = 'No se pudo abrir la cámara (¿negaste el permiso?).';
                        }
                    },
                    detenerCamara() { if (this.flujo) { this.flujo.getTracks().forEach(t => t.stop()); this.flujo = null; } this.camara = false; },
                    tomar() {
                        const v = this.$refs.video, c = this.$refs.lienzo;
                        const ancho = 720, alto = Math.round(ancho * (v.videoHeight || 960) / (v.videoWidth || 720));
                        c.width = ancho; c.height = alto;
                        c.getContext('2d').drawImage(v, 0, 0, ancho, alto);
                        c.toBlob(blob => { this.asignar(new File([blob], 'selfie.jpg', { type: 'image/jpeg' })); this.detenerCamara(); }, 'image/jpeg', 0.8);
                    },
                    elegirArchivo(ev) { const f = ev.target.files[0]; if (f) this.asignar(f); },
                    asignar(archivo) {
                        const dt = new DataTransfer(); dt.items.add(archivo); this.$refs.archivo.files = dt.files;
                        this.foto = archivo; this.vista = URL.createObjectURL(archivo);
                    },
                    repetir() { this.foto = null; this.vista = null; this.$refs.archivo.value = ''; this.abrirCamara(); },
                    pedirUbicacion() {
                        this.ubicacion = null; this.errorUbicacion = '';
                        if (!navigator.geolocation) { this.errorUbicacion = 'Este navegador no puede obtener tu ubicación.'; return; }
                        navigator.geolocation.getCurrentPosition(
                            p => { this.ubicacion = { lat: p.coords.latitude, lng: p.coords.longitude, precision: p.coords.accuracy }; },
                            e => { this.errorUbicacion = e.code === 1 ? 'Negaste el permiso de ubicación: actívalo en el navegador para poder marcar.' : 'No pudimos obtener tu ubicación. Revisa el GPS y reintenta.'; },
                            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                        );
                    },
                    enviar() { if (this.puede) this.$refs.envio.submit(); },
                };
            }
        </script>
    @endif
@endsection
