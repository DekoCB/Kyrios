<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
@php
    // Cada página mide lo mismo que el libro en pantalla: ancho = 1 / 0.044 em. Toda posición y tamaño se da en pt
    // (dompdf mide las unidades em de un elemento con su propia letra, y eso descuadra las cajas); solo las letras van en em.
    $anchoPt = $ancho * 2.8346;
    $base = round($anchoPt * 0.044, 2);                  // pt por em
    $W = round(1 / 0.044, 2);                            // ancho de la página en em
    $H = round($alto / $ancho / 0.044, 2);               // alto de la página en em
    $e = fn ($v) => round($v * $base, 1) . 'pt';
    $hojaAncho = $doble ? $ancho * 2 : $ancho;
    $etq = mb_strtoupper($catalogo->etiquetaTemporada());
    $precio = function (array $it) use ($modo) {
        if ($modo === 'none') { return ''; }
        $v = $modo === 'pen' ? $it['pen'] : $it['usd'];
        return $v === null ? 'Consultar' : ($modo === 'pen' ? 'S/ ' : 'US$ ') . number_format($v, 2);
    };
    // Foto ajustada a su caja (ancho y alto en em) y centrada
    $foto = function (array $a, float $bw, float $bh) use ($e) {
        if (empty($a['img_data'])) { return ''; }
        $r = $a['img_ratio'] ?: 1;
        [$w, $h] = $bw / $bh > $r ? [$bh * $r, $bh] : [$bw, $bw / $r];
        return new \Illuminate\Support\HtmlString('<img src="' . $a['img_data'] . '" style="width:' . $e($w) . '; height:' . $e($h) . '; margin-top:' . $e(($bh - $h) / 2) . ';">');
    };
    $ancho1 = $W - 3.2;                                  // ancho útil (em)
@endphp
<style>
@page { margin: 0; }
body, div, p, table, td, img { margin: 0; padding: 0; }
body { font-family: Helvetica, Arial, sans-serif; color: #22221e; }
/* La hoja mide una fracción menos que el papel: si midiera justo lo mismo, dompdf agregaría una página en blanco entre hojas */
.hoja { position: relative; width: {{ $hojaAncho }}mm; height: {{ $alto - 0.7 }}mm; }
.hoja + .hoja { page-break-before: always; }
.pag { position: absolute; top: 0; width: {{ $ancho }}mm; height: {{ $alto }}mm; overflow: hidden; background: #fbfaf5; }
.mono { font-family: Courier, monospace; }
.nombre { font-weight: bold; line-height: 1.05; }
.suave { color: #6a695f; }
.caja { position: absolute; }
.foto { border: 1px solid #e8e4d8; background: #ffffff; text-align: center; overflow: hidden; }
</style>
</head>
<body>
@foreach($hojas as $h)
    <div class="hoja">
    @foreach(['izq', 'der'] as $lado)
        @continue(!isset($h[$lado]))
        @php
            $k = $h[$lado];                                   // posición en la secuencia: 0 portada, 1 índice, 2.. páginas, última contraportada
            $q = $secuencia[$k];
            $t = $q['t'];
            $idx = $k - 1;                                    // número de página del libro (el índice es 0)
            $left = $doble && $lado === 'der' ? $ancho : 0;
            $tapa = in_array($t, ['portada', 'contra'], true);
            $derecha = $idx % 2 === 1;
        @endphp
        <div class="pag" style="left: {{ $left }}mm; font-size: {{ $base }}pt; {{ $tapa ? 'background: #3f3d37; color: #f5f3ea;' : '' }}">
            @if($t === 'portada')
                <div class="caja" style="left:0; right:0; top:{{ $e($H * 0.17) }}; text-align:center;">
                    <img src="{{ $logoOscuro }}" style="width: {{ $e(15.9) }};">
                    <div style="width:{{ $e(3.6) }}; height:{{ $e(.34) }}; background:#f2c80f; margin: {{ $e(2.4) }} auto {{ $e(1.4) }};"></div>
                    <div style="font-size:1.65em; font-weight:bold; line-height:1.15;">Catálogo<br>{{ $catalogo->temporada }}</div>
                    <div class="mono" style="color:#f2c80f; font-size:.8em; letter-spacing:.3em; margin-top:{{ $e(.9) }};">{{ $catalogo->anio }}</div>
                </div>
                <div class="caja mono" style="left:0; right:0; bottom:{{ $e(2) }}; text-align:center; font-size:.55em; letter-spacing:.22em; color:#bdbaae;">LUCES &amp; ENTORNO</div>
            @elseif($t === 'contra')
                <div class="caja" style="left:0; right:0; top:{{ $e($H * 0.24) }}; text-align:center;">
                    <img src="{{ $logoOscuro }}" style="width: {{ $e(10.4) }};">
                    <div style="font-size:.72em; color:#d4d1c4; margin-top:{{ $e(1.2) }}; line-height:1.5;">administracion@kyrios.com.pe<br>www.kyrios.com.pe</div>
                    <div style="width:{{ $e(9) }}; height:{{ $e(9) }}; background:#ffffff; margin: {{ $e(1.6) }} auto 0;"><img src="{{ $qr }}" style="width:{{ $e(8) }}; height:{{ $e(8) }}; margin-top:{{ $e(.5) }};"></div>
                    <div class="mono" style="font-size:.52em; letter-spacing:.12em; color:#bdbaae; margin-top:{{ $e(1) }};">CATÁLOGO DIGITAL</div>
                </div>
            @else
                {{-- Encabezado --}}
                <table class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(1.5) }}; width:{{ $e($ancho1) }}; font-size:.56em; letter-spacing:.1em; color:#6a695f;">
                    <tr><td>{{ $derecha ? $etq : 'KYRIOS · LUCES & ENTORNO' }}</td><td style="text-align:right">{{ $derecha ? 'KYRIOS.COM.PE' : $etq }}</td></tr>
                </table>
                {{-- Pie: número de página y nota del IGV --}}
                @if($t !== 'seccion')
                    @if($idx > 0)<div class="caja mono" style="{{ $derecha ? 'right:' : 'left:' }}{{ $e(1.6) }}; bottom:{{ $e(1.4) }}; font-size:.56em; color:#6a695f;">{{ $idx }}</div>@endif
                    @if($modo !== 'none' || $t === 'indice')
                        @if($modo !== 'none')<div class="caja mono" style="{{ $derecha ? 'left:' : 'right:' }}{{ $e(1.6) }}; bottom:{{ $e(1.4) }}; font-size:.56em; color:#6a695f;">Precios sin IGV</div>@endif
                    @endif
                @endif

                @if($t === 'indice')
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(4.2) }}; width:{{ $e($ancho1) }};">
                        <div style="font-size:1.9em; font-weight:bold; margin-bottom:{{ $e(.7) }};">Contenido</div>
                        <table style="width:100%; font-size:.9em; font-weight:bold;">
                            @forelse($secciones as $s)
                                <tr><td style="padding:.35em 0; border-bottom:1px dotted #b8b3a1;">{{ $s['titulo'] }}</td><td class="mono suave" style="padding:.35em 0; border-bottom:1px dotted #b8b3a1; text-align:right; width:{{ $e(3) }}; font-size:.8em;">{{ $s['idx'] }}</td></tr>
                            @empty
                                <tr><td class="suave">Sin secciones todavía</td></tr>
                            @endforelse
                        </table>
                        <div class="suave" style="font-size:.66em; margin-top:{{ $e(1.6) }};">{{ $catalogo->etiquetaTemporada() }}@if($catalogo->vigencia) · vigente hasta el {{ $catalogo->vigencia->format('d/m/Y') }}@endif</div>
                    </div>
                @elseif($t === 'notas')
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(4.2) }}; width:{{ $e($ancho1) }};">
                        @for($i = 0; $i < 12; $i++)<div style="height:{{ $e(1.9) }}; border-bottom:1px solid #e3dfd2;"></div>@endfor
                    </div>
                @elseif($t === 'seccion')
                    @php($f = $q['items'][0] ?? null)
                    <div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H * 0.62) }}; background:#ffffff; text-align:center;">
                        @if($f){{ $foto($f, $W * 0.8, $H * 0.54) }}@endif
                    </div>
                    <div class="caja" style="left:0; bottom:0; width:{{ $e($W) }}; height:{{ $e($H * 0.38) }}; background:#3f3d37; color:#f5f3ea;">
                        <div class="caja" style="left:{{ $e(1.8) }}; right:{{ $e(1.8) }}; bottom:{{ $e(2.4) }};">
                            <div style="width:{{ $e(3.2) }}; height:{{ $e(.32) }}; background:#f2c80f; margin-bottom:{{ $e(.9) }};"></div>
                            <div style="font-size:2.5em; font-weight:bold; line-height:1;">{{ $q['titulo'] }}</div>
                            <div class="mono" style="font-size:.62em; letter-spacing:.1em; color:#d4d1c4; margin-top:{{ $e(.9) }};">{{ $etq }}</div>
                        </div>
                    </div>
                @elseif($t === 'uno')
                    @php($a = $q['items'][0] ?? null)
                    @if($a)
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(3.7) }}; width:{{ $e($ancho1) }};">
                        <div class="foto" style="width:{{ $e($ancho1) }}; height:{{ $e($H * 0.5) }};">{{ $foto($a, $ancho1 * 0.94, $H * 0.47) }}</div>
                        <div class="nombre" style="font-size:2em; margin-top:.55em;">{{ $a['nombre'] }}</div>
                        <div class="suave" style="font-size:.72em; margin-top:.15em;">{{ $a['sub'] }}</div>
                        <table style="font-size:.7em; margin-top:.8em;">
                            @foreach($a['specs'] as $kk => $vv)<tr><td class="suave" style="padding:.12em 1em .12em 0;">{{ $kk }}</td><td style="font-weight:bold;">{{ $vv }}</td></tr>@endforeach
                        </table>
                        <div class="mono suave" style="font-size:.62em; margin-top:.9em;">{{ $a['cod'] }}</div>
                        <div class="mono" style="font-size:1.5em; font-weight:bold; text-align:right;">{{ $precio($a) }}</div>
                    </div>
                    @endif
                @elseif($t === 'dos')
                    @php($filaH = ($H - 8.2) / 2)
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(3.7) }}; width:{{ $e($ancho1) }};">
                        @foreach($q['items'] as $a)
                            <table style="width:{{ $e($ancho1) }}; height:{{ $e($filaH) }}; border-top:1px solid #e3dfd2;"><tr>
                                <td style="width:{{ $e($ancho1 * 0.46) }}; vertical-align:middle;"><div class="foto" style="width:{{ $e($ancho1 * 0.42) }}; height:{{ $e($filaH * 0.78) }};">{{ $foto($a, $ancho1 * 0.4, $filaH * 0.74) }}</div></td>
                                <td style="vertical-align:middle;">
                                    <div class="nombre" style="font-size:1.35em;">{{ $a['nombre'] }}</div>
                                    <div class="suave" style="font-size:.66em; margin:.15em 0 .5em;">{{ $a['sub'] }}</div>
                                    <div class="suave" style="font-size:.64em; line-height:1.5;">@foreach(array_intersect_key($a['specs'], array_flip(['Acabado', 'Potencia', 'IP', 'Diámetro'])) as $kk => $vv){{ $kk }}: {{ $vv }}<br>@endforeach</div>
                                    <div class="mono suave" style="font-size:.62em; margin-top:.6em;">{{ $a['cod'] }}</div>
                                    <div class="mono" style="font-size:1.1em; font-weight:bold; margin-top:.5em;">{{ $precio($a) }}</div>
                                </td></tr></table>
                        @endforeach
                    </div>
                @else
                    @php($celdaH = ($H - 8.2) / 2)
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(3.7) }}; width:{{ $e($ancho1) }};">
                    <table style="width:{{ $e($ancho1) }};">
                        @foreach(array_chunk($q['items'], 2) as $par)
                            <tr>
                            @foreach($par as $j => $a)
                                <td style="width:{{ $e($ancho1 / 2) }}; height:{{ $e($celdaH) }}; vertical-align:top; padding-right:{{ $j === 0 ? $e(.5) : '0pt' }}; padding-left:{{ $j === 1 ? $e(.5) : '0pt' }};">
                                    <div class="foto" style="width:{{ $e($ancho1 / 2 - .5) }}; height:{{ $e($celdaH * 0.58) }};">{{ $foto($a, $ancho1 / 2 - .8, $celdaH * 0.54) }}</div>
                                    <div class="nombre" style="font-size:1.05em; margin-top:.5em;">{{ $a['nombre'] }}</div>
                                    <div class="mono suave" style="font-size:.62em;">{{ $a['cod'] }}</div>
                                    <div class="mono" style="font-size:.85em; font-weight:bold;">{{ $precio($a) }}</div>
                                </td>
                            @endforeach
                            </tr>
                        @endforeach
                    </table>
                    </div>
                @endif
            @endif
        </div>
    @endforeach
    </div>
@endforeach
</body>
</html>
