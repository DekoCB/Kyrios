<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
<style>
/* sin «*»: un margin:0 universal anula el margen de @page en dompdf */
body, div, p, h1, h2, table, td, th, img { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 36mm 18mm 16mm 16mm; }
/* Helvetica = tipografía del formato original (Arial); DejaVu no se parece */
body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #000; }

/* Marca de agua, cabecera y pie que se repiten en cada página */
.fondo { position: fixed; top: -36mm; left: -16mm; width: 210mm; height: 297mm; }
.fondo img { width: 210mm; height: 297mm; }
.enc { position: fixed; top: -36mm; left: -16mm; width: 210mm; height: 24mm; }
.enc .logo { position: absolute; left: 14mm; top: 6mm; height: 28mm; }
.enc .banda { position: absolute; top: 0; right: 0; width: 92mm; height: 21mm; background: #4a4a45; color: #f2c80f; text-align: right; padding: 1.2mm 6mm 0 0; font-size: 11pt; line-height: 4.6mm; border-bottom-left-radius: 11mm; }
.pie { position: fixed; top: 242mm; left: 71mm; width: 108mm; height: 7mm; }
.pie img { width: 108mm; }

h1.titulo { font-size: 10pt; text-align: center; margin: 0 0 8mm; }
.fecha { text-align: right; font-size: 10pt; margin-bottom: 5mm; }
.cli { font-size: 10pt; margin-bottom: 1mm; }
.ref { font-size: 10pt; margin-bottom: 5mm; }
.intro { font-weight: bold; font-size: 10pt; margin-bottom: 2mm; }

table.items { width: 100%; border-collapse: collapse; border: 1px solid #000; }
table.items th { background: #f2c80f; color: #3f3d37; font-size: 10.5pt; padding: 1.4mm 1mm; border: 1px solid #000; text-align: center; }
table.items td { font-size: 8.7pt; padding: 1.6mm 1.2mm; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #d9d9d9; vertical-align: top; }
.c { text-align: center; } .r { text-align: right; }
table.items tr.sec td { font-weight: bold; }
table.items tr.vacia td { padding: 0; height: 4.5mm; }

table.tot { margin-left: auto; margin-top: 1mm; border-collapse: collapse; }
table.tot td { padding: 1.4mm 2mm; font-size: 9.5pt; font-weight: bold; text-align: right; }
table.tot td.v { width: 25mm; border: 1px solid #000; }
table.tot td.l { width: 24mm; border: 1px solid #000; font-size: 8.5pt; }
table.tot tr.sub td { font-size: 11.5pt; border: 0; padding-bottom: 1mm; }

h2.cond { font-size: 11.5pt; margin: 8mm 0 1.5mm; }
table.cond td { font-size: 8.5pt; vertical-align: top; padding: 0.4mm 0; }
table.cond td.e { width: 29mm; }
p.notas b { font-size: 11pt; }
p.notas { font-size: 8.5pt; margin-top: 0; }

/* Anexo de imágenes */
.salto { page-break-before: always; }
h2.anexo { font-size: 12pt; margin: 0 0 1mm; }
.ax { page-break-inside: avoid; margin-bottom: 3mm; width: 100%; height: 77mm; border-collapse: collapse; }
.ax td { vertical-align: top; }
.ax .cod { width: 62mm; font-size: 21pt; font-weight: bold; padding: 20mm 0 0 8mm; }
.ax .cod img { width: 6.5mm; margin-right: 1mm; vertical-align: middle; }
.ax .fotos { width: 116mm; }
.ax .foto { display: inline-block; width: 50mm; height: 49mm; border: 0.9mm solid #000; text-align: center; margin-right: 1.5mm; vertical-align: top; }
.ax .foto img { max-width: 47mm; max-height: 46.5mm; margin-top: 1mm; }
.ax .nombre { font-size: 8.5pt; margin-top: 0.8mm; }
.ax .specs { font-size: 7pt; line-height: 1.22; margin: 1mm 0 0 1mm; }
.ax .marca { text-align: right; margin: -13mm 1mm 0 0; }
.ax .marca img { width: 19mm; }

/* Condiciones comerciales */
.cc { page-break-before: always; font-size: 8.5pt; line-height: 1.18; }
.cc p { margin-bottom: 2.6mm; }
.cc b { font-weight: normal; }
.cc .cc-titulo { font-size: 14pt; text-align: center; margin: 0 0 4mm; }
.cc .cc-h { margin: 3.2mm 0 1mm; }
.cc-firma { width: 100%; margin-top: 16mm; font-size: 9pt; }
.cc-firma .linea { border-top: 1px solid #000; margin-bottom: 1mm; }
</style>
</head>
<body>

@php
    \Carbon\Carbon::setLocale('es');
    $moneda = $enDolares ? 'US$ ' : 'S/ ';
    $m = fn ($n) => $moneda . number_format((float) $n, 2);
    // Los importes se guardan en soles: en dólares se convierten con el tipo de cambio del día de la venta
    $conv = fn ($soles) => $enDolares ? round((float) $soles / $tc, 2) : round((float) $soles, 2);
    $fecha = \Carbon\Carbon::parse($doc->fecha);
    $fechaTexto = 'Lima, ' . $fecha->day . ' de ' . ucfirst($fecha->translatedFormat('F')) . ' del ' . $fecha->year;
    $cliente = $doc->cliente;
    $logo = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $numeroProforma = $doc->numero;
    $nombreAsesor = $doc->asesor;
    $web = preg_replace('#^https?://#', '', $empresa->web ?: 'www.kyrios.com.pe');

    $formato = $doc->formato ?? 'simple';
    $items = \App\Support\SeccionesProforma::ordenar($doc->detalles, $formato)->map(function ($d, $i) use ($formato) {
        $p = $d->producto;
        $v = $d->variante;
        $nombre = $p ? \App\Support\EtiquetaProducto::documento($p, $v) : 'Producto';
        $codigoFab = $v?->codigo_fabricante;
        $descripcion = trim($nombre . ($codigoFab ? " ({$codigoFab})" : '') . ($p?->procedencia ? ' ' . mb_strtoupper($p->procedencia) : ''));
        $datos = $p ? (\App\Support\FichaProducto::producto($p) + ($v ? \App\Support\FichaProducto::variante($v) : [])) : [];
        $mat = collect([$datos['Material principal'] ?? null, $datos['Material secundario'] ?? null])->filter()->implode(', ');
        $specs = array_filter([
            'Acabado' => $datos['Acabado'] ?? ($datos['Color'] ?? null),
            'Materiales' => $mat ?: null,
            'Largo' => $datos['Largo'] ?? null,
            'Alto' => $datos['Alto'] ?? null,
            'Ancho' => $datos['Ancho'] ?? null,
            'Profundidad' => $datos['Profundidad'] ?? null,
            'Diámetro' => $datos['Diámetro'] ?? null,
            'Potencia' => $datos['Potencia'] ?? null,
            'Temperatura de color' => $datos['Temperatura de color'] ?? null,
            'IP' => $datos['IP'] ?? null,
        ], fn ($x) => filled($x));
        $imagenes = collect();
        if ($v) {
            $imagenes = $v->imagenes()->orderByDesc('es_principal')->orderBy('orden')->limit(2)->get();
        }
        if ($imagenes->isEmpty() && $p) {
            $imagenes = $p->imagenes()->limit(2)->get();
        }
        return (object) [
            'seccion' => \App\Support\SeccionesProforma::titulo($formato, $d->piso ?? null, $d->ambiente ?? null),
            'cod' => 'L' . str_pad($i + 1, 2, '0', STR_PAD_LEFT),
            'n' => $i + 1,
            'd' => $d,
            'descripcion' => $descripcion,
            'specs' => $specs,
            'fotos' => $imagenes->map(fn ($im) => \App\Support\FichaProducto::imagenDataUri($im))->filter()->values(),
        ];
    });
@endphp

{{-- Marca de agua, cabecera y pie fijos --}}
<div class="fondo"><img src="{{ public_path('img/proforma/fondo.png') }}" alt=""></div>
<div class="enc">
    @if($logo)<img src="{{ $logo }}" class="logo" alt="Kyrios">@endif
    <div class="banda">
        {{ $empresa->razon_social }}<br>
        @if($empresa->ruc)RUC: {{ $empresa->ruc }}<br>@endif
        {{ $empresa->email }}<br>
        {{ $web }}
    </div>
</div>
<div class="pie"><img src="{{ public_path('img/proforma/pie.png') }}" alt=""></div>

<h1 class="titulo">PROFORMA {{ $numeroProforma }}</h1>
<div class="fecha">{{ $fechaTexto }}</div>
<div class="cli">{{ mb_strtoupper($cliente?->nombre ?? 'SIN CLIENTE') }}</div>
<div class="ref">Referencia: COTIZACIÓN {{ $doc->referencia }}</div>

<div class="intro">De acuerdo a lo solicitado por Ud. le hacemos llegar el costo por los siguientes items</div>
<table class="items">
    <thead>
        <tr><th style="width:11mm">ITEM</th><th style="width:11mm">CANT</th><th>DESCRIPCION</th><th style="width:17mm">IMAGEN</th><th style="width:23mm">P.UNIT</th><th style="width:23mm">P.TOTAL</th></tr>
    </thead>
    <tbody>
        <tr class="vacia"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @php $seccionActual = null; @endphp
        @foreach($items as $it)
            @if($it->seccion !== null && $it->seccion !== $seccionActual)
                @php $seccionActual = $it->seccion; @endphp
                <tr class="sec"><td></td><td></td><td>{{ $it->seccion }}</td><td></td><td></td><td></td></tr>
            @endif
            <tr>
                <td class="c">{{ $it->n }}</td>
                <td class="r">{{ $it->d->cantidad }}</td>
                <td>{{ $it->descripcion }}</td>
                <td class="c">{{ $it->cod }}</td>
                <td class="r">{{ $m($conv($it->d->precio_unitario)) }}</td>
                <td class="r">{{ $m($it->d->cantidad * $conv($it->d->precio_unitario)) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<table class="tot">
    @php
        $subtotalPdf = round($items->sum(fn ($it) => $it->d->cantidad * $conv($it->d->precio_unitario)), 2);
        $igvPdf = round($subtotalPdf * 0.18, 2);
    @endphp
    <tr class="sub"><td colspan="2" style="white-space:nowrap">Subtotal Artículos:&nbsp;&nbsp;&nbsp;&nbsp;{{ $m($subtotalPdf) }}</td></tr>
    <tr><td class="l">Subtotal Final:</td><td class="v">{{ $m($subtotalPdf) }}</td></tr>
    <tr><td class="l">IGV:</td><td class="v">{{ $m($igvPdf) }}</td></tr>
    <tr><td class="l">TOTAL:</td><td class="v">{{ $m($subtotalPdf + $igvPdf) }}</td></tr>
</table>

<h2 class="cond">CONDICIONES COMERCIALES:</h2>
<table class="cond">
    <tr><td colspan="2">Los precios están expresados en {{ $enDolares ? 'Dólares Americanos' : 'Soles' }} y NO INCLUYEN EL 18% I.G.V.@if($enDolares) Tipo de cambio de referencia: S/ {{ number_format($tc, 3) }} por dólar.@endif</td></tr>
    <tr><td class="e">Forma de Pago:</td><td>100% Con la orden de compra. Depósito o transferencia a Cta Cte del banco INTERBANK US$ 2003007861610 / CCI: US$ 003-200-003007861610-36 a nombre de la empresa KYRIOS LUCES Y ENTORNO S.A.C.</td></tr>
    <tr><td class="e">Tiempo de Entrega:</td><td>A coordinar / vigencia a partir del conocimiento de realizado el depósito y/o transferencia en cta.</td></tr>
</table>
<p class="notas"><b>Notas:</b><br>GARANTIA LUMINARIAS: 01 Año (En caso realicemos la instalación), Lámpara 75% vida útil de lámpara @if(filled($doc->notas ?? null))<br>{!! nl2br(e($doc->notas)) !!}@endif</p>

{{-- Anexo de imágenes --}}
<div class="salto"></div>
<h2 class="anexo">ANEXO DE IMAGENES</h2>
@foreach($items as $it)
    <table class="ax">
        <tr>
            <td class="cod"><img src="{{ public_path('img/proforma/cuadro.png') }}" alt="">{{ $it->cod }}</td>
            <td class="fotos">
                @forelse($it->fotos as $f)<div class="foto"><img src="{{ $f }}" alt=""></div>@empty<div class="foto" style="color:#999;padding-top:19mm">Sin foto</div>@endforelse
                <div class="nombre">{{ $it->descripcion }}</div>
                <div class="specs">@foreach($it->specs as $k => $val){{ $k }}: {{ $val }}<br>@endforeach</div>
                <div class="marca">@if($logo)<img src="{{ $logo }}" alt="">@endif</div>
            </td>
        </tr>
    </table>
@endforeach

@include('pdf.partials.condiciones-comerciales')

</body>
</html>
