{{-- Una página del brochure: solo fotos y código del producto, con color y formas grandes. Recibe $q, $idx, $catalogo, $libro y $qr. --}}
@php
    $t = $q['t'];
    $d = $libro['diseno'];
    $portada = $d['portada'];
    $logo = asset('img/logo-kyrios-fondo-oscuro.png');
    $logoClaro = asset('img/logo-kyrios-fondo-claro.png');
    $fotoPortada = $portada['imagen'] ? \Illuminate\Support\Facades\Storage::disk('public')->url($portada['imagen']) : null;
    $estiloPortada = $portada['estilo'] === 'foto' && !$fotoPortada ? 'acento' : $portada['estilo'];
    // Cada mosaico alterna sus fondos para que la página se vea viva
    $fondos = ['tinte', 'acento', 'papel', 'tinte'];
    $numero = $idx > 0 ? new \Illuminate\Support\HtmlString('<span class="cb-num">' . $idx . '</span>') : '';
    // La foto del producto dentro de su recuadro; si se eligieron varias, la segunda va como detalle en la esquina
    $foto = function (array $it) {
        if (!$it['img']) {
            return new \Illuminate\Support\HtmlString('<span class="cb-sin">Sin foto</span>');
        }
        $h = '<img class="cb-img" data-src="' . e($it['img']) . '" alt="' . e($it['cod']) . '">';
        if (isset($it['fotos'][1])) {
            $h .= '<img class="cb-detalle" data-src="' . e($it['fotos'][1]['url']) . '" alt="">';
        }
        return new \Illuminate\Support\HtmlString($h);
    };
@endphp
@if($t === 'portada')
    <div class="cl-pg cb cb-portada est-{{ $estiloPortada }}" @if($estiloPortada === 'foto') style="background-image: linear-gradient(rgba(20,19,15,.35), rgba(20,19,15,.8)), url('{{ $fotoPortada }}'); background-size: cover; background-position: center;" @endif>
        <span class="forma f1"></span><span class="forma f2"></span><span class="forma f3"></span>
        @if($portada['logo'])<img class="logo" src="{{ in_array($estiloPortada, ['clara', 'acento'], true) ? $logoClaro : $logo }}" alt="Kyrios">@endif
        <div class="anio">{{ $catalogo->anio }}</div>
        <div class="titulo">
            <h3>{!! $portada['titulo'] ? nl2br(e($portada['titulo'])) : 'Brochure<br>' . e($catalogo->temporada) !!}</h3>
            @if($portada['subtitulo'])<p>{{ $portada['subtitulo'] }}</p>@endif
            @if($portada['lema'])<span class="lema">{{ $portada['lema'] }}</span>@endif
        </div>
    </div>
@elseif($t === 'indice')
    <div class="cl-pg cb cb-indice">
        <span class="forma f1"></span>
        <h3>Contenido</h3>
        <ol>@forelse($libro['secciones'] as $n => $s)<li data-ir="{{ $s['idx'] }}"><b>{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }}</b><span>{{ $s['titulo'] }}</span><i>{{ $s['idx'] }}</i></li>@empty<li><span>Sin secciones todavía</span></li>@endforelse</ol>
        <p class="vig">{{ $catalogo->etiquetaTemporada() }}@if($catalogo->vigencia) · hasta el {{ $catalogo->vigencia->format('d/m/Y') }}@endif</p>
    </div>
@elseif($t === 'notas')
    <div class="cl-pg cb cb-cierre"><span class="forma f1"></span><span class="forma f2"></span>
        <div class="frase">{{ $portada['lema'] ?: 'Luces & entorno' }}</div></div>
@elseif($t === 'contra')
    <div class="cl-pg cl-contra cb-contra"><img class="logo" src="{{ $logo }}" alt="Kyrios"><p>administracion@kyrios.com.pe<br>www.kyrios.com.pe</p><div class="qr">{!! $qr !!}</div><span class="leyenda">Catálogo digital</span></div>
@elseif($t === 'seccion')
    @php($f = $q['items'][0] ?? null)
    <div class="cl-pg cb cb-seccion">
        <div class="foto">@if($f && $f['img'])<img data-src="{{ $f['img'] }}" alt="">@endif</div>
        <div class="bloque"><span class="forma f1"></span><div class="rotulo"><h3>{{ $q['titulo'] }}</h3><small>{{ $catalogo->etiquetaTemporada() }}</small></div></div>
        {{ $numero }}
    </div>
@elseif($t === 'uno')
    @php($a = $q['items'][0] ?? null)
    <div class="cl-pg cb cb-uno">
        @if($a)
            <div class="escena tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}"><span class="disco"></span>{{ $foto($a) }}</div>
            <div class="franja"><span class="cb-cod grande">{{ $a['cod'] }}</span></div>
        @endif
        {{ $numero }}
    </div>
@elseif($t === 'dos')
    <div class="cl-pg cb cb-dos">
        @foreach($q['items'] as $i => $a)
            <div class="banda {{ $i % 2 ? 'inv' : '' }} fondo-{{ $fondos[$i % 4] }}">
                <div class="escena tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}"><span class="disco"></span>{{ $foto($a) }}</div>
                <span class="cb-cod">{{ $a['cod'] }}</span>
            </div>
        @endforeach
        {{ $numero }}
    </div>
@elseif($t === 'tres')
    <div class="cl-pg cb cb-tres">
        @foreach($q['items'] as $i => $a)
            <div class="tile t{{ $i }} fondo-{{ $fondos[$i % 4] }}">
                <div class="escena tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}"><span class="disco"></span>{{ $foto($a) }}</div>
                <span class="cb-cod">{{ $a['cod'] }}</span>
            </div>
        @endforeach
        {{ $numero }}
    </div>
@else
    <div class="cl-pg cb cb-cuatro">
        @foreach($q['items'] as $i => $a)
            <div class="tile fondo-{{ $fondos[$i % 4] }}">
                <div class="escena tam-{{ $a['tam'] }}" style="--esc: {{ $a['esc'] }}"><span class="disco"></span>{{ $foto($a) }}</div>
                <span class="cb-cod">{{ $a['cod'] }}</span>
            </div>
        @endforeach
        {{ $numero }}
    </div>
@endif
