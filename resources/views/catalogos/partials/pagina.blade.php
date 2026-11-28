{{-- Una página del catálogo técnico. Recibe $q (la página armada por LibroCatalogoService), $idx, $catalogo, $libro y $qr. --}}
@php
    $t = $q['t'];
    $lado = $idx % 2 === 0 ? 'izq' : 'der';
    $d = $libro['diseno'];
    $portada = $d['portada'];
    $logo = asset('img/logo-kyrios-fondo-oscuro.png');
    $logoClaro = asset('img/logo-kyrios-fondo-claro.png');
    $fotoPortada = $portada['imagen'] ? \Illuminate\Support\Facades\Storage::disk('public')->url($portada['imagen']) : null;
    $estiloPortada = $portada['estilo'] === 'foto' && !$fotoPortada ? 'oscura' : $portada['estilo'];
    $precio = function (array $it) {
        $pen = $it['pen'] !== null ? 'S/ ' . number_format($it['pen'], 2) : 'Consultar';
        $usd = $it['usd'] !== null ? 'US$ ' . number_format($it['usd'], 2) : 'Consultar';
        return new \Illuminate\Support\HtmlString('<span class="cl-precio cl-pr"><span class="pen">' . e($pen) . '</span><span class="usd">' . e($usd) . '</span></span>');
    };
    // Foto principal y, si se eligieron varias: miniaturas (página de un producto) o una foto pequeña encima (dos y cuatro)
    $foto = function (array $it, string $extra = 'inset') {
        if (!$it['img']) {
            return new \Illuminate\Support\HtmlString('<span style="font-size:.6em;color:#999">Sin foto</span>');
        }
        $h = '<img class="principal" data-src="' . e($it['img']) . '" alt="' . e($it['nombre']) . '">';
        $otras = array_slice($it['fotos'], 1);
        if ($otras) {
            $h .= $extra === 'mini'
                ? '<div class="cl-mini">' . collect($otras)->take(3)->map(fn ($f) => '<img data-src="' . e($f['url']) . '" alt="">')->implode('') . '</div>'
                : '<img class="cl-inset" data-src="' . e($otras[0]['url']) . '" alt="">';
        }
        return new \Illuminate\Support\HtmlString($h);
    };
    $tope = new \Illuminate\Support\HtmlString('<div class="cl-tope"><span>' . ($lado === 'izq' ? 'Kyrios · Luces &amp; entorno' : e(mb_strtoupper($catalogo->etiquetaTemporada()))) . '</span><span>' . ($lado === 'izq' ? e(mb_strtoupper($catalogo->etiquetaTemporada())) : 'kyrios.com.pe') . '</span></div>');
    $pie = new \Illuminate\Support\HtmlString($idx === 0 ? '' : '<div class="cl-pie">' . ($lado === 'izq' ? '<span>' . $idx . '</span><span class="cl-igv">Precios sin IGV</span>' : '<span class="cl-igv">Precios sin IGV</span><span>' . $idx . '</span>') . '</div>');
    $lista = fn (array $it, array $claves) => collect($claves)->filter(fn ($k) => isset($it['specs'][$k]))->mapWithKeys(fn ($k) => [$k => $it['specs'][$k]]);
@endphp
@if($t === 'portada')
    <div class="cl-pg cl-portada est-{{ $estiloPortada }}" @if($estiloPortada === 'foto') style="background-image: linear-gradient(rgba(20,19,15,.5), rgba(20,19,15,.78)), url('{{ $fotoPortada }}'); background-size: cover; background-position: center;" @endif>
        @if($portada['logo'])<img class="logo" src="{{ in_array($estiloPortada, ['clara', 'acento'], true) ? $logoClaro : $logo }}" alt="Kyrios">@endif
        <div class="cl-barra-am"></div>
        <h3>{!! $portada['titulo'] ? nl2br(e($portada['titulo'])) : 'Catálogo<br>' . e($catalogo->temporada) !!}</h3>
        @if($portada['subtitulo'])<div class="subt">{{ $portada['subtitulo'] }}</div>@endif
        <div class="anio">{{ $catalogo->anio }}</div>
        @if($portada['lema'])<div class="lema">{{ $portada['lema'] }}</div>@endif
    </div>
@elseif($t === 'indice')
    <div class="cl-pg cl-indice">{{ $tope }}<h3>Contenido</h3>
        <ol>@forelse($libro['secciones'] as $s)<li data-ir="{{ $s['idx'] }}"><span>{{ $s['titulo'] }}</span><span>{{ $s['idx'] }}</span></li>@empty<li><span>Sin secciones todavía</span><span></span></li>@endforelse</ol>
        <p class="vigencia">{{ $catalogo->etiquetaTemporada() }}@if($catalogo->vigencia) · vigente hasta el {{ $catalogo->vigencia->format('d/m/Y') }}@endif</p>
        <div class="cl-pie"><span></span><span class="cl-igv">Precios sin IGV</span></div></div>
@elseif($t === 'notas')
    <div class="cl-pg cl-notas">{{ $tope }}<div class="renglones"></div>{{ $pie }}</div>
@elseif($t === 'contra')
    <div class="cl-pg cl-contra"><img class="logo" src="{{ $logo }}" alt="Kyrios"><p>administracion@kyrios.com.pe<br>www.kyrios.com.pe</p><div class="qr">{!! $qr !!}</div><span class="leyenda">Catálogo digital</span></div>
@elseif($t === 'seccion')
    @php($f = $q['items'][0] ?? null)
    <div class="cl-pg cl-seccion"><div class="fondo">@if($f && $f['img'])<img data-src="{{ $f['img'] }}" alt="">@endif</div><div class="velo"></div>
        <div class="texto"><div class="cl-barra-am"></div><h3>{{ $q['titulo'] }}</h3><small>{{ $catalogo->etiquetaTemporada() }}</small></div></div>
@elseif($t === 'uno')
    @php($a = $q['items'][0] ?? null)
    <div class="cl-pg cl-uno">{{ $tope }}
        @if($a)
            <div class="cl-foto tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}">{{ $foto($a, 'mini') }}</div><div class="cl-nombre">{{ $a['nombre'] }}</div><div class="cl-sub">{{ $a['sub'] }}</div>
            <dl>@foreach($a['specs'] as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
            <div class="cl-cod" style="margin-top:.8em">{{ $a['cod'] }}</div>
            <div style="text-align:right;font-size:1.5em;margin-top:.4em">{{ $precio($a) }}</div>
        @endif
        {{ $pie }}</div>
@elseif($t === 'dos')
    <div class="cl-pg cl-dos">{{ $tope }}
        @foreach($q['items'] as $a)
            <div class="fila"><div class="cl-foto tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}">{{ $foto($a) }}</div><div><div class="cl-nombre">{{ $a['nombre'] }}</div><div class="cl-sub">{{ $a['sub'] }}</div>
                <div class="mini-specs">@foreach($lista($a, ['Acabado', 'Potencia', 'IP', 'Diámetro']) as $k => $v){{ $k }}: {{ $v }}<br>@endforeach</div>
                <div class="cl-cod" style="margin-top:.6em">{{ $a['cod'] }}</div><div style="font-size:1.1em;margin-top:.5em">{{ $precio($a) }}</div></div></div>
        @endforeach
        {{ $pie }}</div>
@else
    <div class="cl-pg cl-cuatro">{{ $tope }}<div class="rejilla">
        @foreach($q['items'] as $a)
            <div class="celda"><div class="cl-foto tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}">{{ $foto($a) }}</div><div><div class="cl-nombre">{{ $a['nombre'] }}</div><div class="cl-cod">{{ $a['cod'] }}</div><div style="font-size:.85em">{{ $precio($a) }}</div></div></div>
        @endforeach
    </div>{{ $pie }}</div>
@endif
