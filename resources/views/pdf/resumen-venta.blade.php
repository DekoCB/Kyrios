<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
/* sin «*»: un margin:0 universal anula el margen de @page en dompdf */
body, div, p, h1, h2, table, td, th, img { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 40mm 14mm 34mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1a1a1a; }

/* Cabecera y pie que se repiten en cada página */
.enc { position: fixed; top: -40mm; left: -14mm; right: -14mm; height: 36mm; }
.enc .logo { position: absolute; left: 14mm; top: 12mm; height: 18mm; }
.enc .banda { position: absolute; top: 0; right: 0; width: 82mm; background: #4a4a45; color: #f2c80f; text-align: right; padding: 5mm 6mm 5mm 14mm; font-size: 8.5pt; line-height: 1.35; border-bottom-left-radius: 9mm; }
.pie { position: fixed; bottom: -34mm; left: 0; right: 0; height: 30mm; }
.pie .asesor { position: absolute; right: 40mm; top: -5mm; font-size: 7pt; line-height: 1.4; color: #333; }
.pie .asesor b { font-size: 9pt; }
.pie .web { position: absolute; right: 0; bottom: 8mm; font-size: 9pt; color: #444; border-top: 2px solid #f2c80f; padding-top: 2mm; width: 100mm; text-align: right; }
.pie .pag { position: absolute; right: 0; bottom: 1mm; font-size: 9pt; }
.pag:after { content: "Página " counter(page); }

h1.titulo { font-size: 9.5pt; text-align: center; margin: 2mm 0 5mm; }
.fecha { text-align: right; font-size: 9pt; margin-bottom: 4mm; }
.cli { font-size: 9pt; margin-bottom: 1mm; }
.ref { font-size: 9pt; margin-bottom: 3mm; }
.intro { font-weight: bold; font-size: 9pt; margin-bottom: 2mm; }

table.items { width: 100%; border-collapse: collapse; border: 1.5px solid #4a4a45; }
table.items th { background: #f2c80f; color: #3f3d37; font-size: 9pt; padding: 3px 4px; border: 1px solid #4a4a45; text-align: center; }
table.items td { font-size: 8pt; padding: 4px 5px; border-left: 1px solid #4a4a45; border-right: 1px solid #4a4a45; border-bottom: 1px solid #d9d6cc; vertical-align: top; }
.c { text-align: center; } .r { text-align: right; }

table.tot { margin-left: auto; margin-top: 1mm; border-collapse: collapse; }
table.tot td { padding: 2px 6px; font-size: 9pt; text-align: right; }
table.tot td.v { font-weight: bold; width: 28mm; border: 1px solid #4a4a45; }
table.tot tr.sub td { font-weight: bold; font-size: 10pt; border: 0; }

h2.cond { font-size: 9.5pt; margin: 7mm 0 2mm; }
table.cond td { font-size: 7.5pt; vertical-align: top; padding: 1px 0; }
table.cond td.e { width: 28mm; }
p.notas b { font-size: 9pt; }
p.notas { font-size: 7.5pt; margin-top: 2mm; }

/* Anexo de imágenes */
.salto { page-break-before: always; }
h2.anexo { font-size: 10pt; margin-bottom: 3mm; }
.ax { page-break-inside: avoid; margin-bottom: 4mm; width: 100%; border-collapse: collapse; }
.ax td { vertical-align: top; }
.ax .cod { width: 28mm; font-size: 17pt; font-weight: bold; padding-top: 12mm; }
.ax .cod span { display: inline-block; width: 6px; height: 6px; background: #000; margin-right: 3px; }
.ax .fotos { width: 128mm; }
.ax .foto { display: inline-block; width: 46mm; height: 46mm; border: 1.5px solid #000; text-align: center; margin-right: 2mm; vertical-align: top; }
.ax .foto img { max-width: 42mm; max-height: 42mm; margin-top: 2mm; }
.ax .nombre { font-size: 8pt; margin-top: 1mm; }
.ax .specs { font-size: 6.5pt; line-height: 1.25; margin-top: 1mm; }
.ax .marca { text-align: right; vertical-align: bottom; }
.ax .marca img { height: 8mm; }

/* Condiciones comerciales */
.cc { page-break-before: always; font-size: 7pt; line-height: 1.3; }
.cc p { margin-bottom: 2.2mm; text-align: justify; }
.cc .cc-titulo { font-size: 11pt; text-align: center; margin-bottom: 3mm; }
.cc .cc-h { margin: 3mm 0 1mm; font-size: 7.6pt; }
.cc-firma { width: 100%; margin-top: 16mm; font-size: 8pt; }
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
    $fecha = \Carbon\Carbon::parse($venta->fecha);
    $fechaTexto = 'Lima, ' . $fecha->day . ' de ' . ucfirst($fecha->translatedFormat('F')) . ' del ' . $fecha->year;
    $cliente = $venta->cliente;
    $logo = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $numeroProforma = $venta->proforma_numero;
    $nombreAsesor = $venta->vendedor?->name;
    $web = preg_replace('#^https?://#', '', $empresa->web ?: 'www.kyrios.com.pe');

    $items = $venta->detalles->values()->map(function ($d, $i) {
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
            'cod' => 'L' . str_pad($i + 1, 2, '0', STR_PAD_LEFT),
            'n' => $i + 1,
            'd' => $d,
            'descripcion' => $descripcion,
            'specs' => $specs,
            'fotos' => $imagenes->map(fn ($im) => \App\Support\FichaProducto::imagenDataUri($im))->filter()->values(),
        ];
    });
@endphp

{{-- Cabecera y pie fijos --}}
<div class="enc">
    @if($logo)<img src="{{ $logo }}" class="logo" alt="Kyrios">@endif
    <div class="banda">
        {{ $empresa->razon_social }}<br>
        @if($empresa->ruc)RUC: {{ $empresa->ruc }}<br>@endif
        {{ $empresa->email }}<br>
        {{ $web }}
    </div>
</div>
<div class="pie">
    <div class="asesor">
        @if($nombreAsesor)<b>{{ $nombreAsesor }}</b><br>Asesor comercial<br>@endif
        {{ $empresa->email }}@if($empresa->telefono)<br>{{ $empresa->telefono }}@endif
    </div>
    <div class="web">{{ $web }}</div>
    <div class="pag"></div>
</div>

<h1 class="titulo">PROFORMA {{ $numeroProforma }}</h1>
<div class="fecha">{{ $fechaTexto }}</div>
<div class="cli">{{ mb_strtoupper($cliente?->nombre ?? 'SIN CLIENTE') }}</div>
<div class="ref">Referencia: <b>COTIZACIÓN {{ $venta->observaciones ? mb_strtoupper($venta->observaciones) : 'VENTA ' . $venta->codigo }}</b></div>

<div class="intro">De acuerdo a lo solicitado por Ud. le hacemos llegar el costo por los siguientes items</div>
<table class="items">
    <thead>
        <tr><th style="width:11mm">ITEM</th><th style="width:11mm">CANT</th><th>DESCRIPCION</th><th style="width:15mm">IMAGEN</th><th style="width:24mm">P.UNIT</th><th style="width:26mm">P.TOTAL</th></tr>
    </thead>
    <tbody>
        @foreach($items as $it)
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
    <tr class="sub"><td>Subtotal Artículos:</td><td>{{ $m($subtotalPdf) }}</td></tr>
    <tr><td>Subtotal Final:</td><td class="v">{{ $m($subtotalPdf) }}</td></tr>
    <tr><td>IGV:</td><td class="v">{{ $m($igvPdf) }}</td></tr>
    <tr><td>TOTAL:</td><td class="v">{{ $m($subtotalPdf + $igvPdf) }}</td></tr>
</table>

<h2 class="cond">CONDICIONES COMERCIALES:</h2>
<table class="cond">
    <tr><td colspan="2">Los precios están expresados en {{ $enDolares ? 'Dólares Americanos' : 'Soles' }} y NO INCLUYEN EL 18% I.G.V.@if($enDolares) Tipo de cambio de referencia: S/ {{ number_format($tc, 3) }} por dólar.@endif</td></tr>
    <tr><td class="e">Forma de Pago:</td><td>100% Con la orden de compra. Depósito o transferencia a Cta Cte del banco INTERBANK US$ 2003007861610 / CCI: US$ 003-200-003007861610-36 a nombre de la empresa KYRIOS LUCES Y ENTORNO S.A.C.</td></tr>
    <tr><td class="e">Tiempo de Entrega:</td><td>A coordinar / vigencia a partir del conocimiento de realizado el depósito y/o transferencia en cta.</td></tr>
</table>
<p class="notas"><b>Notas:</b><br>GARANTIA LUMINARIAS: 01 Año (En caso realicemos la instalación), Lámpara 75% vida útil de lámpara</p>

{{-- Anexo de imágenes --}}
<div class="salto"></div>
<h2 class="anexo">ANEXO DE IMAGENES</h2>
@foreach($items as $it)
    <table class="ax">
        <tr>
            <td class="cod"><span></span>{{ $it->cod }}</td>
            <td class="fotos">
                @forelse($it->fotos as $f)<div class="foto"><img src="{{ $f }}" alt=""></div>@empty<div class="foto" style="color:#999;padding-top:19mm">Sin foto</div>@endforelse
                <div class="nombre">{{ $it->descripcion }}</div>
                <div class="specs">@foreach(array_chunk(array_map(fn ($k, $val) => "{$k}: {$val}", array_keys($it->specs), $it->specs), 2) as $par){{ implode("   ·   ", $par) }}<br>@endforeach</div>
            </td>
            <td class="marca">@if($logo)<img src="{{ $logo }}" alt="">@endif</td>
        </tr>
    </table>
@endforeach

@include('pdf.partials.condiciones-comerciales')

</body>
</html>
