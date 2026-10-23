<style>
    .gap-x-4{column-gap:1rem}.gap-y-1\.5{row-gap:.375rem}
</style>

{{--
    Atributos a nivel de producto (modelo) de luminarias Kyrios. Compartido por crear y editar.
    $producto = Producto|null
--}}
@php
    $p = $producto ?? null;
    $v = fn (string $c) => old($c, $p?->{$c});
    $clase = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500';
    $textos = [
        ['nombre_fabrica', 'Nombre de fábrica'], ['nombre_kyrios', 'Nombre Kyrios'], ['linea', 'Línea'],
        ['procedencia', 'Procedencia'], ['fuente_luz', 'Fuente de luz'], ['uso', 'Uso (interior / exterior…)'],
        ['aplicacion_2', 'Aplicación 2'], ['material_1', 'Material 1'], ['material_2', 'Material 2'],
        ['nivel_potencia', 'Nivel de potencia'], ['voltaje', 'Voltaje'], ['angulo_apertura', 'Ángulo de apertura'],
        ['ip', 'IP'], ['ik', 'IK'], ['cri', 'CRI'], ['driver', 'Driver (incluido / no incluido)'],
        ['protocolo_regulacion', 'Protocolo de regulación'], ['ficha_tecnica', 'Ficha técnica (SI / URL)'],
    ];
    $listas = [
        'estilos' => 'Estilo', 'tipos_instalacion' => 'Tipo de instalación', 'tipos_proyecto' => 'Tipo de proyecto',
    ];
@endphp

<details class="mb-8 group border border-gray-200 rounded-xl" @if($p?->exists) open @endif>
    <summary class="flex items-center justify-between px-5 py-4 cursor-pointer select-none">
        <span class="text-lg font-semibold text-gray-900">
            <i class="fas fa-lightbulb mr-2 text-blue-900"></i>Datos técnicos y clasificación
            <span class="ml-2 text-sm font-normal text-gray-400">(opcional)</span>
        </span>
        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
    </summary>

    <div class="p-5 border-t border-gray-200 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($textos as [$c, $etiqueta])
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $etiqueta }}</label>
                    <input type="text" name="{{ $c }}" value="{{ $v($c) }}" class="{{ $clase }}">
                </div>
            @endforeach
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Posibilidad de regulación</label>
                <select name="regulable" class="{{ $clase }}">
                    @php $reg = old('regulable', $p?->regulable); @endphp
                    <option value="">—</option>
                    <option value="1" @selected($reg !== null && $reg !== '' && (string) (int) $reg === '1')>Sí</option>
                    <option value="0" @selected($reg !== null && $reg !== '' && (string) (int) $reg === '0')>No</option>
                </select>
            </div>
        </div>

        @foreach($listas as $campo => $titulo)
            @php
                $opciones = config("kyrios.{$campo}");
                $marcados = (array) old($campo, $p?->{$campo} ?? []);
            @endphp
            <fieldset class="border border-gray-200 rounded-lg p-3">
                <legend class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $titulo }}</legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1.5">
                    @foreach($opciones as $slug => $etiquetaOpcion)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="{{ $campo }}[]" value="{{ $slug }}" @checked(in_array($slug, $marcados, true))
                                   class="rounded border-gray-300 text-blue-900 focus:ring-blue-500">
                            {{ $etiquetaOpcion }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
            <textarea name="observaciones" rows="2" class="{{ $clase }}">{{ $v('observaciones') }}</textarea>
        </div>
    </div>
</details>
