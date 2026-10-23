{{-- Datos comerciales del cliente (alta y edición). $cliente es opcional: null en el alta. --}}
@php
    $v = fn (string $campo, $defecto = null) => old($campo, $cliente?->{$campo} ?? $defecto);
    $clase = 'w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200';
@endphp

<div class="md:col-span-2 mt-2 pt-4 border-t border-gray-100">
    <p class="text-sm font-semibold text-gray-700"><i class="fas fa-briefcase mr-1 text-gray-400"></i>Datos comerciales</p>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Segmento</label>
    <select name="segmento" class="{{ $clase }} bg-white">
        <option value="">— Sin clasificar —</option>
        @foreach(\App\Models\Cliente::SEGMENTOS as $cod => $etiqueta)
            <option value="{{ $cod }}" @selected($v('segmento') === $cod)>{{ $etiqueta }} ({{ $cod }})</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Especialidad / rubro</label>
    <input type="text" name="especialidad" maxlength="150" value="{{ $v('especialidad') }}" class="{{ $clase }}" placeholder="Residencial, oficinas, hoteles…">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
    <input type="text" name="cargo" maxlength="120" value="{{ $v('cargo') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa donde trabaja</label>
    <input type="text" name="empresa" maxlength="190" value="{{ $v('empresa') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Cumpleaños</label>
    <input type="date" name="fecha_cumpleanos" max="{{ now()->subDay()->toDateString() }}" value="{{ $v('fecha_cumpleanos') instanceof \Carbon\CarbonInterface ? $v('fecha_cumpleanos')->toDateString() : $v('fecha_cumpleanos') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Comisión pactada (%)</label>
    <input type="number" name="comision" min="0" max="100" step="0.01" value="{{ $v('comision') }}" class="{{ $clase }}" placeholder="Solo aliados">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono fijo</label>
    <input type="text" name="telefono_fijo" maxlength="30" value="{{ $v('telefono_fijo') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Anexo</label>
    <input type="text" name="anexo" maxlength="15" value="{{ $v('anexo') }}" class="{{ $clase }}">
</div>
<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Correo de la empresa</label>
    <input type="email" name="email_empresa" maxlength="255" value="{{ $v('email_empresa') }}" class="{{ $clase }}">
</div>
<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Redes sociales</label>
    <input type="text" name="redes_sociales" maxlength="1000" value="{{ $v('redes_sociales') }}" class="{{ $clase }}" placeholder="Instagram, LinkedIn, web…">
</div>
<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Preferencias de producto</label>
    <input type="text" name="preferencias" maxlength="1000" value="{{ $v('preferencias') }}" class="{{ $clase }}" placeholder="Acabados, líneas o marcas que suele pedir">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Asesor que lo atiende</label>
    <input type="text" name="asesor" maxlength="80" value="{{ $v('asesor') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Medio de contacto / referido por</label>
    <input type="text" name="origen" maxlength="150" value="{{ $v('origen') }}" class="{{ $clase }}">
</div>
