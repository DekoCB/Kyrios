{{-- Atributos que diferencian una variante de otra (solo los que tienen dato). Recibe $variante. --}}
@php
    $limpio = fn ($n) => rtrim(rtrim((string) $n, '0'), '.');
    $items = array_filter([
        'Lámpara'      => $variante->tipo_lampara,
        'Tonalidad'    => $variante->tonalidad_luz,
        'Ángulo'       => $variante->angulo_haz,
        'Regulación'   => $variante->protocolo_regulacion,
        'Eficiencia'   => $variante->eficiencia_luminica !== null ? $limpio($variante->eficiencia_luminica) . ' lm/W' : null,
        'Garantía'     => $variante->garantia_meses !== null ? $variante->garantia_meses . ' meses' : null,
        'Vida útil'    => $variante->vida_util_horas !== null ? number_format($variante->vida_util_horas, 0, ',', '.') . ' h' : null,
        'IP'           => $variante->ip,
        'CRI'          => $variante->cri,
        'Otros'        => $variante->otros,
    ], fn ($v) => filled($v));
@endphp
@if($items)
    <div class="flex items-center gap-x-3 gap-y-0.5 mt-1 text-xs text-gray-500 flex-wrap" data-atributos-variante>
        @foreach($items as $etiqueta => $valor)
            <span><span class="text-gray-400">{{ $etiqueta }}:</span> {{ $valor }}</span>
        @endforeach
    </div>
@endif
