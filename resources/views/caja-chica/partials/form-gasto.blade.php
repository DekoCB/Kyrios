{{-- Formulario de gasto: alta y edición. Variables: $action, $metodo (null|PUT), $mov (opcional), $boton --}}
@php($mov = $mov ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-3"
      x-data="gastoForm('{{ old('tipo_comprobante', $mov->tipo_comprobante ?? 'ticket') }}', '{{ old('monto', $mov->monto ?? '') }}')">
    @csrf
    @if(($metodo ?? null) === 'PUT') @method('PUT') @endif

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
        <select name="categoria" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            @foreach(\App\Models\CajaChicaMovimiento::CATEGORIAS as $k => $v)
                <option value="{{ $k }}" @selected(old('categoria', $mov->categoria ?? 'otros') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-6" x-show="!repartir">
        <label class="block text-xs font-medium text-gray-600 mb-1">¿En qué se gastó?</label>
        <input type="text" name="concepto" :required="!repartir" :disabled="repartir" maxlength="255" placeholder="Ej.: Gasolina para visita a obra" value="{{ old('concepto', $mov->concepto ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    @if(!$mov)
    {{-- Un mismo comprobante repartido en varios conceptos: se crea un gasto por concepto hasta completar el monto total --}}
    <div class="md:col-span-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input type="checkbox" x-model="repartir" class="rounded border-gray-300"> Repartir en varios conceptos
        </label>
        <template x-if="repartir">
            <div class="mt-3 space-y-2">
                <template x-for="(l, i) in lineas" :key="i">
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <input type="text" :name="'conceptos[' + i + '][concepto]'" x-model="l.concepto" required maxlength="255" placeholder="Concepto" class="col-span-12 sm:col-span-6 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <select :name="'conceptos[' + i + '][categoria]'" x-model="l.categoria" class="col-span-6 sm:col-span-3 px-2 py-2 border border-gray-300 rounded-lg text-sm">
                            @foreach(\App\Models\CajaChicaMovimiento::CATEGORIAS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <input type="number" :name="'conceptos[' + i + '][monto]'" x-model="l.monto" step="0.01" min="0.01" required inputmode="decimal" placeholder="S/" class="col-span-4 sm:col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <button type="button" @click="lineas.length > 2 && lineas.splice(i, 1)" :disabled="lineas.length <= 2" class="col-span-2 sm:col-span-1 text-red-500 disabled:opacity-30" title="Quitar"><i class="fas fa-times"></i></button>
                    </div>
                </template>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <button type="button" @click="lineas.length < 12 && lineas.push({ concepto: '', monto: '', categoria: 'otros' })" class="text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Agregar concepto</button>
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
        <input type="text" name="numero_comprobante" maxlength="40" value="{{ old('numero_comprobante', $mov->numero_comprobante ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="md:col-span-2" x-show="tipo !== 'sin_comprobante'">
        <label class="block text-xs font-medium text-gray-600 mb-1">Emisor (tienda / grifo)</label>
        <input type="text" name="emisor" maxlength="160" value="{{ old('emisor', $mov->emisor ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>

    {{-- Foto o escaneo: en el celular «Tomar foto» abre la cámara; «Subir archivo» elige de la galería o un PDF escaneado --}}
    <div class="md:col-span-6" x-show="tipo !== 'sin_comprobante'">
        <label class="block text-xs font-medium text-gray-600 mb-1">Foto o escaneo del comprobante (hasta 6 archivos)</label>
        <div class="flex flex-wrap gap-2">
            <label class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg cursor-pointer">
                <i class="fas fa-camera"></i> Tomar foto
                <input type="file" name="adjuntos[]" accept="image/*" capture="environment" class="hidden"
                       @change="nombres = nombres.concat(Array.from($event.target.files).map(f => f.name))">
            </label>
            <label class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm font-semibold px-4 py-2 rounded-lg cursor-pointer">
                <i class="fas fa-file-upload"></i> Subir archivo / escaneo
                <input type="file" name="adjuntos[]" accept="image/*,application/pdf" multiple class="hidden"
                       @change="nombres = nombres.concat(Array.from($event.target.files).map(f => f.name))">
            </label>
        </div>
        <p class="text-xs text-gray-500 mt-1" x-show="nombres.length" x-text="'Se subirá: ' + nombres.join(', ')"></p>
        <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP, HEIC o PDF · máx. 15 MB c/u. Las fotos se reducen automáticamente.</p>
    </div>

    <div class="md:col-span-6 flex justify-end">
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2 rounded-lg"><i class="fas fa-save mr-1"></i>{{ $boton }}</button>
    </div>
</form>

@once
<script>
function gastoForm(tipo, total) {
    return {
        tipo: tipo, nombres: [], total: total, repartir: false,
        lineas: [{ concepto: '', monto: '', categoria: 'otros' }, { concepto: '', monto: '', categoria: 'otros' }],
        get suma() { return this.lineas.reduce((a, l) => a + (parseFloat(l.monto) || 0), 0); },
        get falta() { return Math.round(((parseFloat(this.total) || 0) - this.suma) * 100) / 100; },
    };
}
</script>
@endonce
