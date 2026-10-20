{{--
    Atributos de variante para luminarias Kyrios.
    $modo   = 'nuevo'  → inputs con old()/valor inicial
              'editar' → inputs enlazados con Alpine (x-model="form.campo")
    $prefijo = prefijo del name (p. ej. 'variante_inicial[' + ']' al crear el producto)
--}}
@php
    $modo = $modo ?? 'nuevo';
    $prefijo = $prefijo ?? '';
    $nombre = fn (string $c) => $prefijo === '' ? $c : $prefijo . '[' . $c . ']';
    $oldKey = fn (string $c) => $prefijo === '' ? $c : $prefijo . '.' . $c;
    $acabados = \Illuminate\Support\Facades\Cache::remember('acabados_variantes', 300, fn () => \Illuminate\Support\Facades\DB::table('producto_variantes')
        ->whereNotNull('acabado_1')->distinct()->orderBy('acabado_1')->pluck('acabado_1'));
    $grupos = [
        'Acabado y tamaño' => [
            ['acabado_1', 'Color / Acabado 1', 'text', 'acabados-kyrios'],
            ['acabado_2', 'Color / Acabado 2', 'text', 'acabados-kyrios'],
            ['tamano', 'Tamaño', 'select', ['chico(a)', 'mediano(a)', 'grande']],
        ],
        'Dimensiones (mm)' => [
            ['diametro', 'Diámetro / lado', 'number'], ['alto', 'Alto', 'number'], ['ancho', 'Ancho', 'number'],
            ['profundidad', 'Profundidad', 'number'], ['alto_suspendido', 'Alto suspendido', 'number'],
            ['diametro_agujero', 'Diám. agujero', 'number'],
        ],
        'Lámpara y luz' => [
            ['socket', 'Socket', 'text'], ['num_lamparas', 'N° de lámparas (x1, x2…)', 'text'],
            ['potencia_w', 'Potencia (W)', 'number'], ['lumenes', 'Lúmenes', 'number'],
            ['temperatura_color', 'T° color (K)', 'number'], ['tonalidad_luz', 'Tonalidad de luz', 'text'],
        ],
        'Códigos' => [
            ['codigo_fabricante', 'Código del fabricante', 'text'],
            ['codigo_excel', '2° código (Excel anterior)', 'text'],
        ],
    ];
    $clase = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500';
@endphp

<datalist id="acabados-kyrios">
    @foreach($acabados as $a)<option value="{{ $a }}">@endforeach
</datalist>

@foreach($grupos as $titulo => $campos)
    <fieldset class="border border-gray-200 rounded-lg p-3">
        <legend class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $titulo }}</legend>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach($campos as $campo)
                @php [$c, $etiqueta, $tipo] = $campo; $extra = $campo[3] ?? null; @endphp
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $etiqueta }}</label>
                    @if($tipo === 'select')
                        <select name="{{ $nombre($c) }}" {!! $modo === 'editar' ? 'x-model="form.' . $c . '"' : '' !!} class="{{ $clase }}">
                            <option value="">—</option>
                            @foreach($extra as $op)
                                <option value="{{ $op }}" @selected($modo === 'nuevo' && old($oldKey($c)) === $op)>{{ ucfirst($op) }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $tipo }}" @if($tipo === 'number') step="0.01" min="0" @endif
                               name="{{ $nombre($c) }}"
                               @if($extra && !is_array($extra)) list="{{ $extra }}" @endif
                               @if($modo === 'editar') x-model="form.{{ $c }}" @else value="{{ old($oldKey($c)) }}" @endif
                               class="{{ $clase }}">
                    @endif
                </div>
            @endforeach
        </div>
    </fieldset>
@endforeach

<fieldset class="border border-gray-200 rounded-lg p-3">
    <legend class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</legend>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Estado de daño</label>
            <select name="{{ $nombre('estado_dano') }}" {!! $modo === 'editar' ? 'x-model="form.estado_dano"' : '' !!} class="{{ $clase }}">
                @foreach(['ninguno' => 'Sin daño', 'leve' => 'Daño leve', 'moderado' => 'Daño moderado', 'grave' => 'Daño grave'] as $k => $e)
                    <option value="{{ $k }}" @selected($modo === 'nuevo' && old($oldKey('estado_dano'), 'ninguno') === $k)>{{ $e }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Observación del daño</label>
            <input type="text" name="{{ $nombre('observacion_dano') }}"
                   @if($modo === 'editar') x-model="form.observacion_dano" @else value="{{ old($oldKey('observacion_dano')) }}" @endif
                   class="{{ $clase }}">
        </div>
    </div>
</fieldset>
