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
    // Diseño del catálogo: colores y tipografía
    $c = $diseno['colores'];
    $letra = $diseno['pdf']['fuente'];
    $letraTitulo = $diseno['pdf']['titulo'];
    $portada = $diseno['portada'];
    $estiloPortada = $portada['estilo'] === 'foto' && !$fotoPortada ? ($brochure ? 'acento' : 'oscura') : $portada['estilo'];
    $precio = function (array $it) use ($modo) {
        if ($modo === 'none') { return ''; }
        $v = $modo === 'pen' ? $it['pen'] : $it['usd'];
        return $v === null ? 'Consultar' : ($modo === 'pen' ? 'S/ ' : 'US$ ') . number_format($v, 2);
    };
    // Foto ajustada a su caja (ancho y alto en em) y centrada; `$a['esc']` es el tamaño elegido para este producto
    $foto = function (array $a, float $bw, float $bh) use ($e) {
        if (empty($a['img_data'])) { return ''; }
        $r = $a['img_ratio'] ?: 1;
        $f = ($a['esc'] ?? 0.92) / 0.92;
        [$bw, $bh] = [$bw * $f, $bh * $f];
        [$w, $h] = $bw / $bh > $r ? [$bh * $r, $bh] : [$bw, $bw / $r];
        return new \Illuminate\Support\HtmlString('<img src="' . $a['img_data'] . '" style="width:' . $e($w) . '; height:' . $e($h) . '; margin-top:' . $e(max(0, ($bh - $h) / 2)) . ';">');
    };
    // Fotos adicionales del producto (hasta 2), pequeñas en la esquina de la caja
    $extras = function (array $a, float $bw, float $bh) use ($e) {
        $h = '';
        foreach (array_slice($a['fotos'] ?? [], 1, 2) as $i => $f) {
            if (empty($f['data'])) { continue; }
            $lado = $bw * 0.26;
            $r = $f['ratio'] ?: 1;
            [$w, $hh] = $r >= 1 ? [$lado, $lado / $r] : [$lado * $r, $lado];
            $h .= '<div style="position:absolute; right:' . $e(0.4 + $i * ($lado + 0.3)) . '; bottom:' . $e(0.4) . '; width:' . $e($lado) . '; height:' . $e($lado) . '; background:#ffffff; border:1px solid #e8e4d8; text-align:center;"><img src="' . $f['data'] . '" style="width:' . $e($w) . '; height:' . $e($hh) . '; margin-top:' . $e(($lado - $hh) / 2) . ';"></div>';
        }
        return new \Illuminate\Support\HtmlString($h);
    };
    // Una foto que cubre toda una caja (recorta lo que sobra, como «cover»); la página recorta con overflow
    $cubrir = function (?array $img, float $bw, float $bh) use ($e) {
        if (!$img || empty($img['data'])) { return ''; }
        $r = $img['ratio'] ?: 1;
        [$w, $h] = $bw / $bh > $r ? [$bw, $bw / $r] : [$bh * $r, $bh];
        return new \Illuminate\Support\HtmlString('<img src="' . $img['data'] . '" style="position:absolute; width:' . $e($w) . '; height:' . $e($h) . '; left:' . $e(($bw - $w) / 2) . '; top:' . $e(($bh - $h) / 2) . ';">');
    };
    $ancho1 = $W - 3.2;                                  // ancho útil (em)

    // ── Brochure: recuadro con fondo, disco de color, foto del producto y código ──
    $fondosBro = ['tinte' => $c['tinte'], 'acento' => $c['tinte_fuerte'], 'papel' => $c['papel']];
    $ordenFondos = ['tinte', 'acento', 'papel', 'tinte'];
    $codigo = fn (string $cod, float $tam = .78, ?string $fondo = null, ?string $letraColor = null) => new \Illuminate\Support\HtmlString(
        '<span style="display:inline-block; font-family:Courier, monospace; font-weight:bold; letter-spacing:.05em; white-space:nowrap; font-size:' . $tam . 'em; background:' . ($fondo ?? $c['tinta']) . '; color:' . ($letraColor ?? $c['acento']) . '; padding:' . $e(.3) . ' ' . $e(.75) . ';">' . e($cod) . '</span>');
    $recuadro = function (array $a, float $x, float $y, float $w, float $h, string $fondo, float $codX, float $codY, float $tamCod = .78, bool $disco = true) use ($e, $c, $foto, $codigo, $extras) {
        $lado = min($w, $h * 0.78) * 0.8;
        $o = '<div style="position:absolute; left:' . $e($x) . '; top:' . $e($y) . '; width:' . $e($w) . '; height:' . $e($h) . '; background:' . $fondo . '; overflow:hidden;">';
        if ($disco && ($a['tam'] ?? '') !== 'completo') {
            $o .= '<div style="position:absolute; left:' . $e(($w - $lado) / 2) . '; top:' . $e(($h * 0.82 - $lado) / 2 + $h * 0.03) . '; width:' . $e($lado) . '; height:' . $e($lado) . '; background:' . $c['acento'] . '; border-radius:' . $e($lado / 2) . ';"></div>';
        }
        $o .= '<div style="position:absolute; left:0; top:' . $e($h * 0.05) . '; width:' . $e($w) . '; text-align:center;">' . $foto($a, $w * 0.86, $h * 0.72) . '</div>';
        $o .= $extras($a, $w, $h);
        $o .= '<div style="position:absolute; left:' . $e($codX) . '; top:' . $e($codY) . ';">' . $codigo($a['cod'], $tamCod) . '</div></div>';
        return new \Illuminate\Support\HtmlString($o);
    };
@endphp
<style>
@page { margin: 0; }
body, div, p, table, td, img { margin: 0; padding: 0; }
body { font-family: {!! $letra !!}; color: {{ $c['tinta'] }}; }
/* La hoja mide una fracción menos que el papel: si midiera justo lo mismo, dompdf agregaría una página en blanco entre hojas */
.hoja { position: relative; width: {{ $hojaAncho }}mm; height: {{ $alto - 0.7 }}mm; }
.hoja + .hoja { page-break-before: always; }
.pag { position: absolute; top: 0; width: {{ $ancho }}mm; height: {{ $alto }}mm; overflow: hidden; background: {{ $c['papel'] }}; }
.mono { font-family: Courier, monospace; }
.nombre { font-weight: bold; line-height: 1.05; font-family: {!! $letraTitulo !!}; }
.suave { color: {{ $c['suave'] }}; }
.caja { position: absolute; }
.foto { position: relative; border: 1px solid #e8e4d8; background: #ffffff; text-align: center; overflow: hidden; }
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
            // Colores de la página: portada y contraportada llevan el color de portada
            $fondoPag = $tapa ? ($t === 'portada' ? ['oscura' => $c['portada'], 'clara' => $c['papel'], 'acento' => $c['acento'], 'foto' => $c['portada']][$estiloPortada] : $c['portada']) : $c['papel'];
            $textoPag = $tapa ? ($t === 'portada' ? ['oscura' => $c['sobre_portada'], 'clara' => $c['tinta'], 'acento' => $c['sobre_acento'], 'foto' => '#ffffff'][$estiloPortada] : $c['sobre_portada']) : $c['tinta'];
            $logoPag = $t === 'portada' && in_array($estiloPortada, ['clara', 'acento'], true) ? $logoClaro : $logoOscuro;
            // Brochure: el contenido va sobre un tinte y la página de cierre sobre el color de acento
            if ($brochure && $t === 'indice') { $fondoPag = $c['tinte']; $textoPag = $c['tinta']; }
            if ($brochure && $t === 'notas') { $fondoPag = $c['acento']; $textoPag = $c['sobre_acento']; }
        @endphp
        <div class="pag" style="left: {{ $left }}mm; font-size: {{ $base }}pt; {{ $tapa || ($brochure && in_array($t, ['indice', 'notas'], true)) ? 'background: ' . $fondoPag . '; color: ' . $textoPag . ';' : '' }}">
            @if($brochure)
                {{-- ═══════════════ BROCHURE: solo fotos y código, con color y formas ═══════════════ --}}
                @if($t === 'portada')
                    <div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H) }}; background:{{ $fondoPag }};">
                        @if($estiloPortada === 'foto'){{ $cubrir($fotoPortada, $W, $H) }}<div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H) }}; background:#14130f; opacity:.45;"></div>@endif
                        <div class="caja" style="left:{{ $e($W * .38) }}; top:{{ $e(-$W * .5) }}; width:{{ $e($W * 1.18) }}; height:{{ $e($W * 1.18) }}; background:{{ $estiloPortada === 'oscura' ? $c['acento'] : $c['portada'] }}; border-radius:{{ $e($W * .59) }};"></div>
                        <div class="caja" style="left:{{ $e($W * .74) }}; top:{{ $e($H * .36) }}; width:{{ $e($W * .12) }}; height:{{ $e($W * .12) }}; background:{{ $c['tinta'] }}; border-radius:{{ $e($W * .06) }};"></div>
                    </div>
                    @if($portada['logo'])<img class="caja" src="{{ $logoPag }}" style="left:{{ $e(1.8) }}; top:{{ $e(1.8) }}; width:{{ $e($W * .34) }};">@endif
                    <div class="caja mono" style="right:{{ $e(1.6) }}; top:{{ $e(1.7) }}; font-weight:bold; letter-spacing:.2em; font-size:.95em; color:{{ $estiloPortada === 'oscura' ? $c['sobre_acento'] : $c['sobre_portada'] }};">{{ $catalogo->anio }}</div>
                    <div class="caja" style="left:{{ $e(1.8) }}; right:{{ $e(1.8) }}; bottom:{{ $e(2.2) }};">
                        <div style="font-family:{!! $letraTitulo !!}; font-size:3.4em; font-weight:bold; line-height:.98; text-transform:uppercase;">{!! $portada['titulo'] ? nl2br(e($portada['titulo'])) : 'Brochure<br>' . e($catalogo->temporada) !!}</div>
                        @if($portada['subtitulo'])<div style="font-size:.9em; margin-top:{{ $e(.8) }};">{{ $portada['subtitulo'] }}</div>@endif
                        @if($portada['lema'])<div style="margin-top:{{ $e(1.1) }};"><span class="mono" style="display:inline-block; font-size:.6em; letter-spacing:.24em; text-transform:uppercase; background:{{ $estiloPortada === 'oscura' ? $c['acento'] : $c['tinta'] }}; color:{{ $estiloPortada === 'oscura' ? $c['sobre_acento'] : $c['acento'] }}; padding:{{ $e(.5) }} {{ $e(1) }};">{{ $portada['lema'] }}</span></div>@endif
                    </div>
                @elseif($t === 'contra')
                    <div class="caja" style="left:0; right:0; top:{{ $e($H * 0.24) }}; text-align:center;">
                        <img src="{{ $logoOscuro }}" style="width: {{ $e(10.4) }};">
                        <div style="font-size:.72em; color:#d4d1c4; margin-top:{{ $e(1.2) }}; line-height:1.5;">administracion@kyrios.com.pe<br>www.kyrios.com.pe</div>
                        <div style="width:{{ $e(9) }}; height:{{ $e(9) }}; background:#ffffff; margin: {{ $e(1.6) }} auto 0;"><img src="{{ $qr }}" style="width:{{ $e(8) }}; height:{{ $e(8) }}; margin-top:{{ $e(.5) }};"></div>
                        <div class="mono" style="font-size:.52em; letter-spacing:.12em; color:#bdbaae; margin-top:{{ $e(1) }};">CATÁLOGO DIGITAL</div>
                    </div>
                @elseif($t === 'indice')
                    <div class="caja" style="left:{{ $e($W * .3) }}; top:{{ $e($H * .62) }}; width:{{ $e($W * .9) }}; height:{{ $e($W * .9) }}; background:{{ $c['acento'] }}; border-radius:{{ $e($W * .45) }};"></div>
                    <div class="caja" style="left:{{ $e(1.8) }}; top:{{ $e(2.2) }}; width:{{ $e($W - 3.6) }};">
                        <div style="font-family:{!! $letraTitulo !!}; font-size:2.6em; font-weight:bold; text-transform:uppercase; margin-bottom:{{ $e(.8) }};">Contenido</div>
                        <table style="width:100%; font-size:1.05em; font-weight:bold; text-transform:uppercase;">
                            @forelse($secciones as $n => $s)
                                <tr><td style="width:{{ $e(2.6) }}; padding:.3em 0;"><span class="mono" style="display:inline-block; background:{{ $c['tinta'] }}; color:{{ $c['acento'] }}; font-size:.7em; padding:{{ $e(.25) }} {{ $e(.5) }};">{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }}</span></td><td style="padding:.3em 0;">{{ $s['titulo'] }}</td><td class="mono suave" style="text-align:right; width:{{ $e(3) }}; font-size:.75em;">{{ $s['idx'] }}</td></tr>
                            @empty
                                <tr><td class="suave">Sin secciones todavía</td></tr>
                            @endforelse
                        </table>
                        <div class="mono suave" style="font-size:.62em; margin-top:{{ $e(1.6) }};">{{ $catalogo->etiquetaTemporada() }}@if($catalogo->vigencia) · hasta el {{ $catalogo->vigencia->format('d/m/Y') }}@endif</div>
                    </div>
                @elseif($t === 'notas')
                    <div class="caja" style="left:{{ $e(-$W * .3) }}; top:{{ $e(-$H * .12) }}; width:{{ $e($W * .8) }}; height:{{ $e($W * .8) }}; background:{{ $c['portada'] }}; border-radius:{{ $e($W * .4) }};"></div>
                    <div class="caja" style="left:{{ $e(1.8) }}; right:{{ $e(1.8) }}; top:{{ $e($H * .4) }}; font-family:{!! $letraTitulo !!}; font-size:2.6em; font-weight:bold; line-height:.98; text-transform:uppercase; color:{{ $c['sobre_acento'] }};">{{ $portada['lema'] ?: 'Luces & entorno' }}</div>
                @elseif($t === 'seccion')
                    @php($f = $q['items'][0] ?? null)
                    <div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H * .66) }}; background:{{ $c['tinte'] }}; text-align:center;">
                        @if($f)<div style="padding-top:{{ $e($H * .04) }};">{{ $foto($f, $W * .86, $H * .56) }}</div>@endif
                    </div>
                    <div class="caja" style="left:0; bottom:0; width:{{ $e($W) }}; height:{{ $e($H * .34) }}; background:{{ $c['acento'] }}; color:{{ $c['sobre_acento'] }};">
                        <div class="caja" style="right:{{ $e(-$W * .05) }}; top:{{ $e(-$W * .16) }}; width:{{ $e($W * .26) }}; height:{{ $e($W * .26) }}; background:{{ $c['tinta'] }}; border-radius:{{ $e($W * .13) }};"></div>
                        <div class="caja" style="left:{{ $e(1.8) }}; right:{{ $e(1.8) }}; bottom:{{ $e(2.2) }};">
                            <div style="font-family:{!! $letraTitulo !!}; font-size:3em; font-weight:bold; line-height:.95; text-transform:uppercase;">{{ $q['titulo'] }}</div>
                            <div class="mono" style="font-size:.6em; letter-spacing:.14em; text-transform:uppercase; margin-top:{{ $e(.6) }};">{{ $etq }}</div>
                        </div>
                    </div>
                @elseif($t === 'uno')
                    @php($a = $q['items'][0] ?? null)
                    @if($a)
                        {{ $recuadro($a, 0, 0, $W, $H * .79, $c['tinte'], $W / 2 - 3, $H * .79 + 1.6, 1.5) }}
                        <div class="caja" style="left:0; top:{{ $e($H * .79) }}; width:{{ $e($W) }}; height:{{ $e($H * .21) }}; background:{{ $c['tinta'] }}; text-align:center;">
                            <div style="padding-top:{{ $e($H * .07) }};">{{ $codigo($a['cod'], 1.5, $c['acento'], $c['sobre_acento']) }}</div>
                        </div>
                    @endif
                @elseif($t === 'dos')
                    @foreach($q['items'] as $i => $a)
                        @php($y = $i * $H / 2)
                        {{ $recuadro($a, 0, $y, $W, $H / 2, $fondosBro[$ordenFondos[$i % 4]], $i % 2 ? 1.4 : $W - 12.5, $H / 2 - 4.2, 1.1) }}
                    @endforeach
                @elseif($t === 'tres')
                    @foreach($q['items'] as $i => $a)
                        @if($i === 0)
                            {{ $recuadro($a, 0, 0, $W * .58, $H, $fondosBro[$ordenFondos[0]], 1.4, $H - 3.6, 1.05) }}
                        @else
                            {{ $recuadro($a, $W * .58, ($i - 1) * $H / 2, $W * .42, $H / 2, $fondosBro[$ordenFondos[$i]], .7, $H / 2 - 3.2, .62) }}
                        @endif
                    @endforeach
                @else
                    @foreach($q['items'] as $i => $a)
                        @php([$cx, $cy] = [($i % 2) * $W / 2, intdiv($i, 2) * $H / 2])
                        {{ $recuadro($a, $cx, $cy, $W / 2, $H / 2, $i === 1 ? $c['acento'] : $fondosBro[$ordenFondos[$i % 4]], 1.2, $H / 2 - 3.2, .78, $i !== 1) }}
                    @endforeach
                @endif
                @if(!$tapa && $idx > 0)<div class="caja mono" style="{{ $derecha ? 'right:' : 'left:' }}{{ $e(1.1) }}; top:{{ $e(1) }}; background:{{ $c['tinta'] }}; color:{{ $c['acento'] }}; font-weight:bold; font-size:.62em; padding:{{ $e(.25) }} {{ $e(.6) }};">{{ $idx }}</div>@endif
            @else
            {{-- ═══════════════ CATÁLOGO TÉCNICO ═══════════════ --}}
            @if($t === 'portada')
                @if($estiloPortada === 'foto')<div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H) }};">{{ $cubrir($fotoPortada, $W, $H) }}<div class="caja" style="left:0; top:0; width:{{ $e($W) }}; height:{{ $e($H) }}; background:#14130f; opacity:.55;"></div></div>@endif
                <div class="caja" style="left:0; right:0; top:{{ $e($H * 0.17) }}; text-align:center;">
                    @if($portada['logo'])<img src="{{ $logoPag }}" style="width: {{ $e(15.9) }};">@endif
                    <div style="width:{{ $e(3.6) }}; height:{{ $e(.34) }}; background:{{ $estiloPortada === 'acento' ? $c['sobre_acento'] : $c['acento'] }}; margin: {{ $e(2.4) }} auto {{ $e(1.4) }};"></div>
                    <div style="font-family:{!! $letraTitulo !!}; font-size:1.65em; font-weight:bold; line-height:1.15;">{!! $portada['titulo'] ? nl2br(e($portada['titulo'])) : 'Catálogo<br>' . e($catalogo->temporada) !!}</div>
                    @if($portada['subtitulo'])<div style="font-size:.78em; margin-top:{{ $e(.9) }};">{{ $portada['subtitulo'] }}</div>@endif
                    <div class="mono" style="color:{{ $estiloPortada === 'acento' ? $c['sobre_acento'] : ($estiloPortada === 'clara' ? $c['suave'] : $c['acento']) }}; font-size:.8em; letter-spacing:.3em; margin-top:{{ $e(.9) }};">{{ $catalogo->anio }}</div>
                </div>
                @if($portada['lema'])<div class="caja mono" style="left:0; right:0; bottom:{{ $e(2) }}; text-align:center; font-size:.55em; letter-spacing:.22em; text-transform:uppercase; opacity:.8;">{{ $portada['lema'] }}</div>@endif
            @elseif($t === 'contra')
                <div class="caja" style="left:0; right:0; top:{{ $e($H * 0.24) }}; text-align:center;">
                    <img src="{{ $logoOscuro }}" style="width: {{ $e(10.4) }};">
                    <div style="font-size:.72em; color:#d4d1c4; margin-top:{{ $e(1.2) }}; line-height:1.5;">administracion@kyrios.com.pe<br>www.kyrios.com.pe</div>
                    <div style="width:{{ $e(9) }}; height:{{ $e(9) }}; background:#ffffff; margin: {{ $e(1.6) }} auto 0;"><img src="{{ $qr }}" style="width:{{ $e(8) }}; height:{{ $e(8) }}; margin-top:{{ $e(.5) }};"></div>
                    <div class="mono" style="font-size:.52em; letter-spacing:.12em; color:#bdbaae; margin-top:{{ $e(1) }};">CATÁLOGO DIGITAL</div>
                </div>
            @else
                {{-- Encabezado --}}
                <table class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(1.5) }}; width:{{ $e($ancho1) }}; font-size:.56em; letter-spacing:.1em; color:{{ $c['suave'] }};">
                    <tr><td>{{ $derecha ? $etq : 'KYRIOS · LUCES & ENTORNO' }}</td><td style="text-align:right">{{ $derecha ? 'KYRIOS.COM.PE' : $etq }}</td></tr>
                </table>
                {{-- Pie: número de página y nota del IGV --}}
                @if($t !== 'seccion')
                    @if($idx > 0)<div class="caja mono" style="{{ $derecha ? 'right:' : 'left:' }}{{ $e(1.6) }}; bottom:{{ $e(1.4) }}; font-size:.56em; color:{{ $c['suave'] }};">{{ $idx }}</div>@endif
                    @if($modo !== 'none' || $t === 'indice')
                        @if($modo !== 'none')<div class="caja mono" style="{{ $derecha ? 'left:' : 'right:' }}{{ $e(1.6) }}; bottom:{{ $e(1.4) }}; font-size:.56em; color:{{ $c['suave'] }};">Precios sin IGV</div>@endif
                    @endif
                @endif

                @if($t === 'indice')
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(4.2) }}; width:{{ $e($ancho1) }};">
                        <div style="font-family:{!! $letraTitulo !!}; font-size:1.9em; font-weight:bold; margin-bottom:{{ $e(.7) }};">Contenido</div>
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
                    <div class="caja" style="left:0; bottom:0; width:{{ $e($W) }}; height:{{ $e($H * 0.38) }}; background:{{ $c['portada'] }}; color:{{ $c['sobre_portada'] }};">
                        <div class="caja" style="left:{{ $e(1.8) }}; right:{{ $e(1.8) }}; bottom:{{ $e(2.4) }};">
                            <div style="width:{{ $e(3.2) }}; height:{{ $e(.32) }}; background:{{ $c['acento'] }}; margin-bottom:{{ $e(.9) }};"></div>
                            <div style="font-family:{!! $letraTitulo !!}; font-size:2.5em; font-weight:bold; line-height:1;">{{ $q['titulo'] }}</div>
                            <div class="mono" style="font-size:.62em; letter-spacing:.1em; color:#d4d1c4; margin-top:{{ $e(.9) }};">{{ $etq }}</div>
                        </div>
                    </div>
                @elseif($t === 'uno')
                    @php($a = $q['items'][0] ?? null)
                    @if($a)
                    <div class="caja" style="left:{{ $e(1.6) }}; top:{{ $e(3.7) }}; width:{{ $e($ancho1) }};">
                        <div class="foto" style="width:{{ $e($ancho1) }}; height:{{ $e($H * 0.5) }};">{{ $foto($a, $ancho1 * 0.94, $H * 0.47) }}{{ $extras($a, $ancho1, $H * 0.5) }}</div>
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
                                <td style="width:{{ $e($ancho1 * 0.46) }}; vertical-align:middle;"><div class="foto" style="width:{{ $e($ancho1 * 0.42) }}; height:{{ $e($filaH * 0.78) }};">{{ $foto($a, $ancho1 * 0.4, $filaH * 0.74) }}{{ $extras($a, $ancho1 * 0.42, $filaH * 0.78) }}</div></td>
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
                                    <div class="foto" style="width:{{ $e($ancho1 / 2 - .5) }}; height:{{ $e($celdaH * 0.58) }};">{{ $foto($a, $ancho1 / 2 - .8, $celdaH * 0.54) }}{{ $extras($a, $ancho1 / 2 - .5, $celdaH * 0.58) }}</div>
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
            @endif
        </div>
    @endforeach
    </div>
@endforeach
</body>
</html>
