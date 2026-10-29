<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 6mm 5mm; }
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.5pt;
    color: #111;
    background: #fff;
    width: 100%;
}

/* CONTENEDOR PRINCIPAL CON MÁRGENES LATERALES */
.page-content {
    margin-left: 3mm;
    margin-right: 3mm;
    padding: 0;
    width: auto;
}

.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: bold; }

.divider { border-top: 1px dashed #999; margin: 5px 0; }
.divider-solid { border-top: 1px solid #333; margin: 5px 0; }

/* ─── HEADER ─── */
.empresa-logo { display: block; margin: 0 auto 4px; max-height: 50px; max-width: 90%; }
.empresa-nombre { font-size: 9.5pt; font-weight: bold; text-align: center; color: #44413A; margin-bottom: 2px; }
.empresa-info { text-align: center; font-size: 6.5pt; color: #444; line-height: 1.5; margin-bottom: 3px; }

/* ─── DOC TITLE ─── */
.doc-tipo { text-align: center; font-weight: bold; font-size: 8pt; text-transform: uppercase;
            border-top: 1px solid #333; border-bottom: 1px solid #333;
            padding: 3px 0; margin: 5px 0; letter-spacing: 0.5px; }
.doc-numero { text-align: center; font-size: 8pt; font-weight: bold; color: #44413A;
              margin-bottom: 5px; letter-spacing: 0.5px; }

/* ─── CLIENTE ─── */
.label { font-size: 6.5pt; color: #444; }
.value { font-size: 7.5pt; color: #111; }
.row { display: table; width: 100%; margin-bottom: 1px; }
.row-l { display: table-cell; font-size: 6.5pt; color: #444; width: 65px; }
.row-v { display: table-cell; font-size: 7.5pt; }

/* ─── ITEMS ─── */
table.items { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 4px 0; 
    table-layout: fixed; /* Para controlar mejor los anchos */
}
table.items thead th {
    font-size: 6.5pt; font-weight: bold; text-align: left;
    border-bottom: 1px solid #333; padding: 2px 1px;
}
table.items thead th.r { text-align: right; }
table.items thead th.c { text-align: center; }
table.items tbody td { font-size: 7pt; padding: 2px 1px; vertical-align: top; word-wrap: break-word; }
table.items tbody td.r { text-align: right; }
table.items tbody td.c { text-align: center; }
table.items tfoot td { font-size: 7pt; padding: 2px 1px; }

/* Ajuste de anchos de columna */
table.items th:first-child, table.items td:first-child { padding-left: 0; }
table.items th:last-child, table.items td:last-child { padding-right: 0; }

/* ─── TOTALES ─── */
.total-row { display: table; width: 100%; padding: 1px 0; }
.total-lbl { display: table-cell; font-size: 7.5pt; color: #333; }
.total-val { display: table-cell; font-size: 7.5pt; text-align: right; font-weight: bold; }
.total-final .total-lbl { font-size: 9pt; font-weight: bold; }
.total-final .total-val { font-size: 9pt; }

/* ─── FOOTER ─── */
.footer-text { font-size: 6pt; color: #555; text-align: center; line-height: 1.5; margin-top: 4px; }
.qr-wrap { text-align: center; margin: 6px 0; }
.qr-img  { width: 70px; height: 70px; }

/* ─── MÉTODOS DE PAGO ─── */
.pagos-sec-t { margin-top: 5px; }
.pagos-sec-titulo-t { font-size: 6pt; font-weight: bold; color: #555; text-transform: uppercase; text-align: center; border-top: 1px dashed #999; border-bottom: 1px dashed #999; padding: 2px 0; margin-bottom: 4px; }
.pago-bloque { margin-bottom: 5px; text-align: center; }
.logo-pill-t { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; color: #fff; letter-spacing: 0.5px; }
.pago-dato-t { font-size: 6.5pt; color: #222; line-height: 1.5; margin-top: 1px; }
.qr-pago-t { width: 60px; height: 60px; display: block; margin: 3px auto 0; }
.card-badge-t { display: inline-block; font-size: 5.5pt; font-weight: bold; padding: 1px 4px; border-radius: 2px; margin: 1px; color: #fff; }

/* ─── UTILIDADES ─── */
.px-1 { padding-left: 1px; padding-right: 1px; }
</style>
</head>
<body>
<div class="page-content">

{{-- ─── EMPRESA ─── --}}
@php
    $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
@endphp
@if($logoSrc)
    <div style="text-align:center; margin-bottom:4px;">
        <img src="{{ $logoSrc }}" style="height:46px; width:auto;" alt="Logo">
    </div>
@endif
<div class="empresa-nombre">{{ $empresa->razon_social }}</div>
<div class="empresa-info">
    R.U.C.: {{ $empresa->ruc }}<br>
    @if($empresa->direccion){{ $empresa->direccion }}<br>@endif
    @if($empresa->distrito){{ implode(' - ', array_filter([$empresa->distrito, $empresa->provincia, $empresa->departamento])) }}<br>@endif
    @if($empresa->telefono)&#9990; Telf: {{ $empresa->telefono }}<br>@endif
    @if($empresa->email)&#9993; Email: {{ $empresa->email }}<br>@endif
    @if($empresa->web)&#127760; Website: {{ $empresa->web }}@endif
</div>

{{-- ─── TIPO DOCUMENTO ─── --}}
@php
    $tiposNombre = [
        'factura'   => 'Factura de Venta Electrónica',
        'boleta'    => 'Boleta de Venta Electrónica',
        'nota_venta' => 'Nota de Venta',
        'cotizacion'=> 'Nota de Entrega / Cotización',
    ];
@endphp
<div class="doc-tipo">{{ $tiposNombre[$venta->tipo_comprobante] ?? ucfirst($venta->tipo_comprobante) }}</div>
<div class="doc-numero">{{ $venta->numero_documento ?? $venta->codigo }}</div>

{{-- ─── FECHA / PAGO ─── --}}
<div class="row">
    <div class="row-l">Fecha Emisión:</div>
    <div class="row-v">{{ $venta->fecha->format('d-m-Y') }} / {{ $venta->created_at->format('h:i A') }}</div>
</div>
@php $metodosLabel = ['efectivo'=>'Efectivo','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia','mixto'=>'Mixto']; @endphp
<div class="row">
    <div class="row-l">Forma de Pago:</div>
    <div class="row-v">{{ $metodosLabel[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago ?? '—') }}</div>
</div>

<div class="divider"></div>

{{-- ─── CLIENTE ─── --}}
@if($venta->cliente)
<div class="row"><div class="row-l">Razón Social:</div><div class="row-v bold">{{ $venta->cliente->nombre }}</div></div>
<div class="row"><div class="row-l">{{ $venta->tipo_comprobante === 'factura' ? 'R.U.C.' : 'Doc.' }}:</div><div class="row-v">{{ $venta->cliente->numero_documento }}</div></div>
@if($venta->cliente->direccion)
<div class="row"><div class="row-l">Dirección:</div><div class="row-v">{{ $venta->cliente->direccion }}</div></div>
@endif
<div class="divider"></div>
@endif

{{-- ─── ITEMS ─── --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:38px">Cód.</th>
            <th>Descripción</th>
            <th class="c" style="width:18px">Qty</th>
            <th class="r" style="width:36px">P.Unit</th>
            <th class="r" style="width:38px">Total</th>
        </tr>
    </thead>
    <tbody>
        @php $igvFactor = $venta->subtotal > 0 ? ($venta->total / $venta->subtotal) : 1.18; @endphp
        @foreach($venta->detalles as $d)
        @php
            $precioFinal = $d->precio_unitario * $igvFactor;
            $totalFinal  = $d->subtotal * $igvFactor;
        @endphp
        <tr>
            <td>{{ $d->producto?->codigo }}</td>
            <td>
                {{ \App\Support\EtiquetaProducto::nombre($d->producto) }}
                @if($d->variante)<br><span style="font-size:6pt;color:#555">{{ $d->variante->nombre_completo }}</span>@endif
            </td>
            <td class="c">{{ $d->cantidad }}</td>
            <td class="r">S/{{ number_format($precioFinal, 2) }}</td>
            <td class="r">S/{{ number_format($totalFinal, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="divider-solid"></div>

{{-- ─── SON ─── --}}
<div style="font-size:6.5pt;color:#444;margin-bottom:3px">
    Son: <strong>{{ montoEnLetras($venta->total) }}</strong>
</div>

{{-- ─── TOTALES ─── --}}
<div class="total-row">
    <div class="total-lbl">Gravado:</div>
    <div class="total-val">S/ {{ number_format($venta->subtotal, 2) }}</div>
</div>
<div class="total-row">
    <div class="total-lbl">IGV (18%):</div>
    <div class="total-val">S/ {{ number_format($venta->igv, 2) }}</div>
</div>
<div class="total-row">
    <div class="total-lbl">Descuento Total:</div>
    <div class="total-val">S/ 0.00</div>
</div>
<div class="divider-solid"></div>
<div class="total-row total-final">
    <div class="total-lbl">Total a Pagar:</div>
    <div class="total-val">S/ {{ number_format($venta->total, 2) }}</div>
</div>

{{-- ─── MÉTODOS DE PAGO CONFIGURADOS ─── --}}
@if($pagos->count() > 0)
<div class="pagos-sec-t">
    <div class="pagos-sec-titulo-t">Formas de pago aceptadas</div>
    @foreach($pagos as $tipo => $pg)
    @php
        [$pillColor, $pillLabel] = match($tipo) {
            'yape'          => ['#7c3aed', 'YAPE'],
            'plin'          => ['#16a34a', 'PLIN'],
            'transferencia' => ['#44413A', 'TRANSFERENCIA'],
            'pos'           => ['#b91c1c', 'POS / TARJETA'],
            default         => ['#374151', strtoupper($tipo)],
        };
        $qrFilePath = $pg->qr_imagen_path ? storage_path('app/public/' . $pg->qr_imagen_path) : null;
        $qrDataUri  = null;
        if ($qrFilePath && file_exists($qrFilePath)) {
            $ext       = strtolower(pathinfo($qrFilePath, PATHINFO_EXTENSION));
            $mime      = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : "image/$ext";
            $qrDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($qrFilePath));
        }
    @endphp
    <div class="pago-bloque">
        <span class="logo-pill-t" style="background:{{ $pillColor }}">{{ $pillLabel }}</span>
        @if($tipo === 'pos')
            <span class="card-badge-t" style="background:#1a1f71">VISA</span>
            <span class="card-badge-t" style="background:#eb001b">MC</span>
        @endif
        <div class="pago-dato-t">
            @if($pg->titular) {{ $pg->titular }}<br>@endif
            @if($pg->numero)  <strong>{{ $pg->numero }}</strong><br>@endif
            @if($pg->banco)   {{ strtoupper($pg->banco) }}<br>@endif
            @if($pg->cci)     CCI: {{ $pg->cci }}@endif
        </div>
        @if($qrDataUri)
            <img src="{{ $qrDataUri }}" class="qr-pago-t" alt="QR {{ $pillLabel }}">
        @endif
    </div>
    @if(!$loop->last)<div class="divider"></div>@endif
    @endforeach
</div>
@endif

<div class="divider"></div>

{{-- ─── QR CONSULTA ─── --}}
@php
    $qrUrl = route('ventas.show', $venta->id);
    $qrB64 = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(80)->generate($qrUrl));
@endphp
<div class="qr-wrap">
    <img src="data:image/svg+xml;base64,{{ $qrB64 }}" class="qr-img">
</div>

{{-- ─── FOOTER LEGAL ─── --}}
<div class="footer-text">
    Representación Impresa de la {{ $tiposNombre[$venta->tipo_comprobante] ?? 'Comprobante' }}<br>
    Consulte su Documento en:<br>
    @if($empresa->web)<strong>{{ $empresa->web }}</strong><br>@endif
    @if($venta->vendedor)VENDEDOR: {{ $venta->vendedor->name }}<br>@endif
    @if($venta->sucursal)Sucursal: {{ $venta->sucursal->nombre }}<br>@endif
    <br>
    <em>Generado en sistema {{ config('app.name') }}</em>
</div>

</div> {{-- Cierre de page-content --}}
</body>
</html>