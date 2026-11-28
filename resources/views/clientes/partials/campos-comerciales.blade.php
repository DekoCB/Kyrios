{{-- Datos comerciales del cliente (alta y edición). $cliente es opcional: null en el alta. --}}
@php
    $v = fn (string $campo, $defecto = null) => old($campo, $cliente?->{$campo} ?? $defecto);
    $clase = 'w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring focus:ring-blue-200';
@endphp

<div class="md:col-span-2 mt-2 pt-4 border-t border-gray-100">
    <p class="text-sm font-semibold text-gray-700"><i class="fas fa-briefcase mr-1 text-gray-400"></i>Datos comerciales</p>
</div>

@if($cliente?->codigo)
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ID de cliente</label>
        <input type="text" value="{{ $cliente->codigo }}" readonly class="{{ $clase }} bg-gray-50 font-mono font-semibold text-blue-700">
    </div>
@else
    <p class="text-xs text-gray-400 md:col-span-2">El ID de cliente (KYR001, KYR002…) se asigna solo al guardar.</p>
@endif
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">DNI adicional</label>
    <input type="text" name="dni_complementario" maxlength="8" inputmode="numeric" value="{{ $v('dni_complementario') }}" class="{{ $clase }}" placeholder="Si el documento principal es RUC">
    @error('dni_complementario') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">RUC adicional</label>
    <input type="text" name="ruc_complementario" maxlength="11" inputmode="numeric" value="{{ $v('ruc_complementario') }}" class="{{ $clase }}" placeholder="Si el documento principal es DNI">
    @error('ruc_complementario') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
<div class="md:col-span-2">
    @php $marcadas = (array) old('listas_difusion', $cliente?->listas_difusion ?? []); @endphp
    <span class="block text-sm font-medium text-gray-700 mb-1">Listas de difusión (WhatsApp)</span>
    {{-- Campo vacío para que al desmarcar todas se guarde «ninguna» --}}
    <input type="hidden" name="listas_difusion[]" value="">
    <div class="flex flex-wrap gap-x-5 gap-y-2" role="group" aria-label="Listas de difusión de WhatsApp">
        @foreach(\App\Models\Cliente::LISTAS_DIFUSION as $clave => $etiqueta)
            <label for="lista_{{ $clave }}" class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" id="lista_{{ $clave }}" name="listas_difusion[]" value="{{ $clave }}" @checked(in_array($clave, $marcadas, true))
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-300">
                {{ $etiqueta }}
            </label>
        @endforeach
    </div>
    <p class="text-[11px] text-gray-400 mt-1">Un contacto puede estar en varias listas a la vez (por ejemplo arquitecta, mamá y mujer).</p>
</div>
@foreach(['clasificacion' => 'Clasificación', 'como_ingreso' => 'Cómo ingresó', 'preferencia_contacto' => 'Preferencia de contacto', 'especialidad' => 'Especialidad / rubro'] as $campo => $etiqueta)
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $etiqueta }}</label>
        <select name="{{ $campo }}" class="{{ $clase }} bg-white">
            <option value="">— Sin dato —</option>
            @foreach(\App\Models\OpcionCliente::paraSelect($campo, $cliente?->{$campo}) as $opcion)
                <option value="{{ $opcion }}" @selected($v($campo) === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    </div>
@endforeach
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
    <input type="text" name="cargo" maxlength="120" value="{{ $v('cargo') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa donde trabaja</label>
    <input type="text" name="empresa" maxlength="190" value="{{ $v('empresa') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de empresa</label>
    <select name="tipo_empresa" class="{{ $clase }} bg-white">
        <option value="">— Sin dato —</option>
        @foreach(\App\Models\OpcionCliente::paraSelect('tipo_empresa', $cliente?->tipo_empresa) as $opcion)
            <option value="{{ $opcion }}" @selected($v('tipo_empresa') === $opcion)>{{ $opcion }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
    <input type="date" name="fecha_cumpleanos" id="cli_nacimiento" max="{{ now()->subDay()->toDateString() }}" value="{{ $v('fecha_cumpleanos') instanceof \Carbon\CarbonInterface ? $v('fecha_cumpleanos')->toDateString() : $v('fecha_cumpleanos') }}" class="{{ $clase }}">
</div>
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Edad <span class="text-gray-400 font-normal">(se calcula con la fecha)</span></label>
    <input type="number" name="edad" id="cli_edad" min="0" max="120" value="{{ $v('edad') }}" class="{{ $clase }} @error('edad') border-red-500 @enderror" placeholder="Años">
    @error('edad') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>
<script>
    document.getElementById('cli_nacimiento')?.addEventListener('change', function () {
        const edad = document.getElementById('cli_edad');
        if (!this.value || !edad) return;
        const n = new Date(this.value), h = new Date();
        let a = h.getFullYear() - n.getFullYear();
        if (h.getMonth() < n.getMonth() || (h.getMonth() === n.getMonth() && h.getDate() < n.getDate())) a--;
        edad.value = a >= 0 ? a : '';
    });
</script>
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
@php
    $redesIniciales = array_values((array) old('redes', $cliente?->redes ?? []));
    $opcionesRedes = collect(\App\Models\Cliente::REDES)->map(fn ($r, $k) => ['clave' => $k, 'etiqueta' => $r['etiqueta']])->values();
@endphp
<div class="md:col-span-2" x-data="{ filas: @js($redesIniciales ?: [['red' => 'instagram', 'usuario' => '']]), opciones: @js($opcionesRedes) }">
    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-share-nodes mr-1 text-gray-400"></i>Redes sociales <span class="text-gray-400 font-normal">(puede agregar varias)</span></label>
    <template x-for="(f, i) in filas" :key="i">
        <div class="flex gap-2 mb-2">
            <select :name="`redes[${i}][red]`" x-model="f.red" class="{{ $clase }} bg-white !w-44 shrink-0" aria-label="Red social">
                <template x-for="o in opciones" :key="o.clave"><option :value="o.clave" x-text="o.etiqueta" :selected="o.clave === f.red"></option></template>
            </select>
            <input type="text" :name="`redes[${i}][usuario]`" x-model="f.usuario" maxlength="190" class="{{ $clase }}" placeholder="@usuario o enlace del perfil">
            <button type="button" @click="filas.splice(i, 1)" class="px-3 text-gray-400 hover:text-red-600" title="Quitar esta red" aria-label="Quitar esta red"><i class="fas fa-xmark"></i></button>
        </div>
    </template>
    <button type="button" @click="filas.push({ red: 'instagram', usuario: '' })" class="text-sm font-medium text-blue-600 hover:text-blue-800"><i class="fas fa-plus mr-1"></i>Agregar otra red</button>
    @if($v('redes_sociales'))
        <div class="mt-3">
            <label class="block text-xs font-medium text-gray-500 mb-1">Redes anotadas antes como texto libre</label>
            <input type="text" name="redes_sociales" maxlength="1000" value="{{ $v('redes_sociales') }}" class="{{ $clase }}">
        </div>
    @endif
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
