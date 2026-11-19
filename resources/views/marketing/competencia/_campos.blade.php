@php $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<div><label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
    <select name="red" class="{{ $input }}">@foreach(\App\Support\Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(old('red', $competidor->red) === $k)>{{ $v }}</option>@endforeach</select></div>
<div><label class="block text-xs font-semibold text-gray-600 mb-1">Marca o empresa</label>
    <input name="nombre" value="{{ old('nombre', $competidor->nombre) }}" class="{{ $input }}" required>
    @error('nombre')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
<div><label class="block text-xs font-semibold text-gray-600 mb-1">Usuario (@)</label>
    <input name="usuario" value="{{ old('usuario', $competidor->usuario) }}" class="{{ $input }}"></div>
<div><label class="block text-xs font-semibold text-gray-600 mb-1">Enlace</label>
    <input name="url" value="{{ old('url', $competidor->url) }}" class="{{ $input }}" placeholder="https://…">
    @error('url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
<div><label class="block text-xs font-semibold text-gray-600 mb-1">Notas</label>
    <textarea name="notas" rows="2" class="{{ $input }}">{{ old('notas', $competidor->notas) }}</textarea></div>
