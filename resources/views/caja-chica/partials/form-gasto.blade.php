{{-- Formulario de gasto: alta y edición. Variables: $action, $metodo (null|PUT), $mov (opcional), $boton --}}
@php($mov = $mov ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-3"
      x-data="gastoForm(@js([
          'tipo' => old('tipo_comprobante', $mov->tipo_comprobante ?? 'ticket'),
          'total' => (string) old('monto', $mov->monto ?? ''),
          'categoria' => old('categoria', $mov->categoria ?? 'otros'),
          'concepto' => old('concepto', $mov->concepto ?? ''),
          'emisor' => old('emisor', $mov->emisor ?? ''),
          'numero' => old('numero_comprobante', $mov->numero_comprobante ?? ''),
          'urlPrevios' => $mov ? null : route('caja-chica.gastos-previos'),
      ]))"
      @adjuntos-ocupado="ocupados += $event.detail"
      @pageshow.window="enviando = false"
      @submit="if (ocupados > 0) { $event.preventDefault(); return; } enviando = true">
    @csrf
    @if(($metodo ?? null) === 'PUT') @method('PUT') @endif

    @if(!$mov)
    {{-- Traer los datos de un gasto anterior para no escribirlos de nuevo --}}
    <div class="md:col-span-6 relative" @click.outside="previosAbierto = false">
        <button type="button" @click="abrirPrevios()" class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 hover:text-blue-900 border border-blue-200 bg-blue-50 hover:bg-blue-100 rounded-lg px-3 py-1.5">
            <i class="fas fa-clock-rotate-left"></i> Reutilizar un gasto anterior
        </button>
        <div x-show="previosAbierto" x-cloak class="absolute z-30 mt-1 w-full md:w-[34rem] bg-white border border-gray-200 rounded-xl shadow-lg">
            <div class="p-2 border-b border-gray-100">
                <input type="text" x-model="busquedaPrevios" @input.debounce.250ms="cargarPrevios()" placeholder="Buscar por concepto o emisor…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="max-h-64 overflow-y-auto">
                <template x-for="g in previos" :key="g.id">
                    <button type="button" @click="usarPrevio(g)" class="w-full text-left px-4 py-2.5 hover:bg-blue-50 border-b border-gray-50 last:border-0">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-900 truncate" x-text="g.conceptos.length ? g.conceptos.length + ' conceptos: ' + g.conceptos.map(c => c.concepto).join(' · ') : g.concepto"></p>
                            <span class="text-sm font-semibold text-gray-700 shrink-0" x-text="'S/ ' + g.monto.toFixed(2)"></span>
                        </div>
                        <p class="text-xs text-gray-400"><span x-text="g.fecha"></span><span x-show="g.emisor"> · <span x-text="g.emisor"></span></span></p>
                    </button>
                </template>
                <p x-show="cargandoPrevios" class="px-4 py-3 text-center text-xs text-gray-400">Buscando…</p>
                <p x-show="!cargandoPrevios && previos.length === 0" class="px-4 py-3 text-center text-xs text-gray-400">No hay gastos anteriores</p>
            </div>
        </div>
        <p x-show="aviso" x-text="aviso" x-cloak class="text-xs text-emerald-700 mt-1"></p>
    </div>
    @endif

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Fecha del comprobante</label>
        <input type="date" name="fecha" required max="{{ today()->toDateString() }}" value="{{ old('fecha', ($mov->fecha ?? today())->format('Y-m-d')) }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1"><span x-text="repartir ? 'Monto total del comprobante (S/)' : 'Monto (S/)'"></span></label>
        <input type="number" name="monto" step="0.01" min="0.01" required inputmode="decimal" x-model="total"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Categoría</label>
        <select name="categoria" x-model="categoria" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            @foreach(\App\Models\CajaChicaMovimiento::CATEGORIAS as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-6" x-show="!repartir">
        <label class="block text-xs font-medium text-gray-600 mb-1">¿En qué se gastó?</label>
        <input type="text" name="concepto" :required="!repartir" :disabled="repartir" maxlength="255" placeholder="Ej.: Gasolina para visita a obra" x-model="concepto"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    @if(!$mov)
    {{-- Un mismo comprobante repartido en varios conceptos: se crea un gasto por concepto hasta completar el monto total --}}
    <div class="md:col-span-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input type="checkbox" x-model="repartir" class="rounded border-gray-300"> Repartir en varios conceptos
        </label>
        <template x-if="repartir">
            <div class="mt-3 space-y-3">
                <template x-for="(l, i) in lineas" :key="l.uid">
                    <div class="rounded-lg border border-gray-200 bg-white p-2 space-y-2">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input type="text" :name="'conceptos[' + i + '][concepto]'" x-model="l.concepto" required maxlength="255" placeholder="Concepto" class="col-span-12 sm:col-span-6 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <select :name="'conceptos[' + i + '][categoria]'" x-model="l.categoria" class="col-span-6 sm:col-span-3 px-2 py-2 border border-gray-300 rounded-lg text-sm">
                                @foreach(\App\Models\CajaChicaMovimiento::CATEGORIAS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                            <input type="number" :name="'conceptos[' + i + '][monto]'" x-model="l.monto" step="0.01" min="0.01" required inputmode="decimal" placeholder="S/" class="col-span-4 sm:col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <button type="button" @click="lineas.length > 2 && lineas.splice(i, 1)" :disabled="lineas.length <= 2" class="col-span-2 sm:col-span-1 text-red-500 disabled:opacity-30" title="Quitar"><i class="fas fa-times"></i></button>
                        </div>
                        {{-- Foto o escaneo propio de este concepto (opcional) --}}
                        <div x-show="tipo !== 'sin_comprobante'">
                            <p class="text-[11px] text-gray-500 mb-1">Foto o escaneo de este concepto (opcional)</p>
                            @include('caja-chica.partials.adjuntos-campo', ['nombre' => "'conceptos[' + i + '][adjuntos][]'"])
                        </div>
                    </div>
                </template>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <button type="button" @click="lineas.length < 12 && lineas.push(nuevaLinea())" class="text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar concepto</button>
                    <span :class="Math.abs(falta) < 0.005 ? 'text-emerald-700' : 'text-amber-700'" class="font-medium">
                        <template x-if="Math.abs(falta) < 0.005"><span><i class="fas fa-check-circle mr-1"></i>Repartido por completo</span></template>
                        <template x-if="falta > 0.004"><span>Falta repartir S/ <span x-text="falta.toFixed(2)"></span></span></template>
                        <template x-if="falta < -0.004"><span>Se pasa por S/ <span x-text="(-falta).toFixed(2)"></span></span></template>
                    </span>
                </div>
            </div>
        </template>
    </div>
    @endif

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Comprobante</label>
        <select name="tipo_comprobante" x-model="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            @foreach(\App\Models\CajaChicaMovimiento::COMPROBANTES as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2" x-show="tipo !== 'sin_comprobante'">
        <label class="block text-xs font-medium text-gray-600 mb-1">N.º (ej. F001-123)</label>
        <input type="text" name="numero_comprobante" maxlength="40" x-model="numero"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="md:col-span-2" x-show="tipo !== 'sin_comprobante'">
        <label class="block text-xs font-medium text-gray-600 mb-1">Emisor (tienda / grifo)</label>
        <input type="text" name="emisor" maxlength="160" x-model="emisor"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    {{-- Foto o escaneo: en el celular «Tomar foto» abre la cámara; «Subir archivo» elige de la galería o un PDF escaneado --}}
    <div class="md:col-span-6" x-show="tipo !== 'sin_comprobante'">
        <label class="block text-xs font-medium text-gray-600 mb-1">
            <span x-text="repartir ? 'Foto o escaneo del comprobante completo' : 'Foto o escaneo del comprobante'"></span> (hasta 6 archivos)
        </label>
        @include('caja-chica.partials.adjuntos-campo', ['nombre' => "'adjuntos[]'"])
        <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP, HEIC o PDF · las fotos se reducen en tu dispositivo antes de subirse.</p>
    </div>

    <div class="md:col-span-6 flex justify-end items-center gap-3">
        <span x-show="ocupados > 0" x-cloak class="text-xs text-gray-500"><i class="fas fa-spinner fa-spin mr-1"></i>Preparando fotos…</span>
        <button :disabled="enviando || ocupados > 0" class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold px-5 py-2 rounded-lg">
            <i class="fas mr-1" :class="enviando ? 'fa-spinner fa-spin' : 'fa-save'"></i><span x-text="enviando ? 'Guardando…' : @js($boton)"></span>
        </button>
    </div>
</form>

@once
@include('caja-chica.partials.adjuntos-script')
<script>
function gastoForm(c) {
    let uid = 0;
    const nuevaLinea = (d = {}) => ({ uid: ++uid, concepto: d.concepto || '', monto: d.monto ?? '', categoria: d.categoria || 'otros' });
    return {
        tipo: c.tipo, total: c.total, categoria: c.categoria, concepto: c.concepto, emisor: c.emisor, numero: c.numero,
        repartir: false, enviando: false, ocupados: 0, aviso: '',
        lineas: [nuevaLinea(), nuevaLinea()],
        previosAbierto: false, previos: [], busquedaPrevios: '', cargandoPrevios: false,
        nuevaLinea,
        get suma() { return this.lineas.reduce((a, l) => a + (parseFloat(l.monto) || 0), 0); },
        get falta() { return Math.round(((parseFloat(this.total) || 0) - this.suma) * 100) / 100; },

        abrirPrevios() {
            this.previosAbierto = !this.previosAbierto;
            if (this.previosAbierto) this.cargarPrevios();
        },
        async cargarPrevios() {
            this.cargandoPrevios = true;
            try {
                const r = await fetch(c.urlPrevios + '?q=' + encodeURIComponent(this.busquedaPrevios.trim()), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                this.previos = r.ok ? await r.json() : [];
            } catch (e) {
                this.previos = [];
            } finally {
                this.cargandoPrevios = false;
            }
        },
        /* Se copian los datos del gasto; la fecha, el N.º del comprobante y las fotos son de cada comprobante y se llenan de nuevo */
        usarPrevio(g) {
            this.total = String(g.monto);
            this.categoria = g.categoria || 'otros';
            this.emisor = g.emisor || '';
            this.tipo = g.tipo_comprobante || 'ticket';
            this.numero = '';
            if (g.conceptos.length > 1) {
                this.repartir = true;
                this.lineas = g.conceptos.map(x => nuevaLinea(x));
                this.concepto = '';
            } else {
                this.repartir = false;
                this.concepto = g.concepto || '';
            }
            this.previosAbierto = false;
            this.aviso = 'Datos traídos del gasto del ' + g.fecha + '. Falta la fecha, el N.º del comprobante y la foto.';
        },
    };
}
</script>
@endonce
