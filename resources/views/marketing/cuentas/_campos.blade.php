@php $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
    <select name="red" class="{{ $input }}" required>
        @foreach(\App\Support\Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(old('red', $cuenta->red) === $k)>{{ $v }}</option>@endforeach
    </select>
    @error('red')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre de la cuenta</label>
    <input name="nombre" value="{{ old('nombre', $cuenta->nombre) }}" class="{{ $input }}" placeholder="Kyrios luces &amp; entorno" required>
    @error('nombre')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Usuario (@)</label>
        <input name="usuario" value="{{ old('usuario', $cuenta->usuario) }}" class="{{ $input }}" placeholder="kyrios_lighting">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Datos vía</label>
        <input name="origen" value="{{ old('origen', $cuenta->origen) }}" class="{{ $input }}" placeholder="Metricool">
    </div>
</div>
<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1">Enlace de la cuenta</label>
    <input name="url" value="{{ old('url', $cuenta->url) }}" class="{{ $input }}" placeholder="https://www.instagram.com/…">
    @error('url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Estado de conexión</label>
        <select name="estado" class="{{ $input }}">
            @foreach(\App\Models\Marketing\Cuenta::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(old('estado', $cuenta->estado) === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Responsable</label>
        <select name="responsable_id" class="{{ $input }}">
            <option value="">Sin asignar</option>
            @foreach($responsables as $u)<option value="{{ $u->id }}" @selected((int) old('responsable_id', $cuenta->responsable_id) === $u->id)>{{ $u->name }}</option>@endforeach
        </select>
    </div>
</div>
<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1">Notas</label>
    <textarea name="notas" rows="2" class="{{ $input }}">{{ old('notas', $cuenta->notas) }}</textarea>
</div>
