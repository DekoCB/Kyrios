{{-- El libro 3D con sus hojas. Recibe $catalogo, $libro (LibroCatalogoService::construir), $modo (none|pen|usd) y $qr (SVG). --}}
@php
    $Q = $libro['paginas'];
    $M = intdiv(count($Q) - 1, 2) + 1;   // hojas: la portada + las interiores
    $d = $libro['diseno'];
    $c = $d['colores'];
    $parcial = $libro['tipo'] === 'brochure' ? 'catalogos.partials.pagina-brochure' : 'catalogos.partials.pagina';
    // El diseño del catálogo (tipografía y colores) llega a todas las páginas como variables de CSS
    $estilo = '--papel:' . $c['papel'] . ';--tinta:' . $c['tinta'] . ';--suave:' . $c['suave'] . ';--carbon:' . $c['portada'] . ';--amarillo:' . $c['acento']
        . ';--tinte:' . $c['tinte'] . ';--tinte-fuerte:' . $c['tinte_fuerte'] . ';--sobre-acento:' . $c['sobre_acento'] . ';--sobre-portada:' . $c['sobre_portada']
        . ';--fuente:' . $d['css']['fuente'] . ';--fuente-titulo:' . $d['css']['titulo'] . ';';
@endphp
<div class="cl-escena" id="cl-escena" style="{{ $estilo }}">
    <div class="cl-libro {{ $libro['tipo'] === 'brochure' ? 'cl-brochure' : '' }}" id="cl-libro" data-precios="{{ $modo }}" data-contenido="{{ $libro['contenido'] }}" tabindex="0" aria-label="Libro del catálogo. Flechas izquierda y derecha para pasar las páginas.">
        <div class="cl-base izq"></div><div class="cl-base der"></div>
        @for($k = 0; $k < $M; $k++)
            <div class="cl-hoja" data-k="{{ $k }}">
                <div class="cl-cara frente {{ $k === 0 ? 'tapa' : '' }}">
                    @if($k === 0) @include($parcial, ['q' => ['t' => 'portada'], 'idx' => 0, 'catalogo' => $catalogo, 'libro' => $libro, 'qr' => $qr])
                    @else @include($parcial, ['q' => $Q[2 * $k - 1], 'idx' => 2 * $k - 1, 'catalogo' => $catalogo, 'libro' => $libro, 'qr' => $qr]) @endif
                </div>
                <div class="cl-cara atras {{ $k === $M - 1 ? 'tapa' : '' }}">
                    @include($parcial, ['q' => $Q[2 * $k], 'idx' => 2 * $k, 'catalogo' => $catalogo, 'libro' => $libro, 'qr' => $qr])
                </div>
            </div>
        @endfor
    </div>
</div>
<div class="cl-controles">
    <button type="button" id="cl-ant" class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 px-4 py-2 rounded-lg text-sm font-semibold" aria-label="Página anterior">← Anterior</button>
    <span class="cl-estado" id="cl-estado" aria-live="polite"></span>
    <button type="button" id="cl-sig" class="bg-yellow-400 hover:bg-yellow-500 disabled:opacity-40 text-gray-900 px-4 py-2 rounded-lg text-sm font-semibold" aria-label="Página siguiente">Siguiente →</button>
</div>
<p class="ayuda text-xs text-gray-500 text-center mt-2">Haga clic en una página, use las flechas del teclado o arrastre para pasar las hojas.</p>
