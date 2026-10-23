{{-- Formulario de gasto: alta y edición. Variables: $action, $metodo (null|PUT), $mov (opcional), $boton --}}
@php($mov = $mov ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-3"
      x-data="{ tipo: '{{ old('tipo_comprobante', $mov->tipo_comprobante ?? 'ticket') }}', nombres: [] }">
    @csrf
    @if(($metodo ?? null) === 'PUT') @method('PUT') @endif

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Fecha del comprobante</label>
        <input type="date" name="fecha" required max="{{ today()->toDateString() }}" value="{{ old('fecha', ($mov->fecha ?? today())->format('Y-m-d')) }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1">Monto (S/)</label>
        <input type="number" name="monto" step="0.01" min="0.01" required inputmode="decimal" value="{{ old('monto', $mov->monto ?? '') }}"
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
    <div class="md:col-span-6">
        <label class="block text-xs font-medium text-gray-600 mb-1">¿En qué se gastó?</label>
        <input type="text" name="concepto" required maxlength="255" placeholder="Ej.: Gasolina para visita a obra" value="{{ old('concepto', $mov->concepto ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
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
