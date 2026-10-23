<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 18mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1a1a1a; background: #fff; margin: 18mm 16mm; }

.header { display: table; width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.header-left  { display: table-cell; width: 60%; vertical-align: top; padding-right: 12px; }
.header-right { display: table-cell; width: 40%; vertical-align: top; }
.logo { height: 64px; width: auto; margin-bottom: 6px; }
.empresa-nombre { font-size: 13pt; font-weight: bold; color: #3f3d37; margin-bottom: 2px; }
.empresa-sub { font-size: 7.5pt; color: #555; margin-bottom: 5px; }
.empresa-dir { font-size: 7.5pt; color: #333; line-height: 1.45; }

.doc-ruc { background: #3f3d37; color: #fff; font-weight: bold; font-size: 11pt; text-align: center; padding: 6px 10px; border-radius: 3px 3px 0 0; }
.doc-tipo { background: #f2c80f; color: #3f3d37; font-weight: bold; font-size: 10pt; text-align: center; padding: 5px 10px; letter-spacing: 1px; }
.doc-numero { background: #faf9f5; border: 1.5px solid #f2c80f; font-size: 10pt; font-weight: bold; text-align: center; padding: 6px 10px; border-radius: 0 0 3px 3px; color: #3f3d37; letter-spacing: 0.5px; }

.info-row { display: table; width: 100%; border: 1px solid #d9d6cc; margin-bottom: 8px; border-radius: 3px; }
.info-cell { display: table-cell; border-right: 1px solid #d9d6cc; padding: 5px 8px; }
.info-cell:last-child { border-right: none; }
.info-label { font-size: 6.5pt; color: #555; text-transform: uppercase; font-weight: bold; }
.info-value { font-size: 8pt; font-weight: bold; color: #1a1a1a; margin-top: 1px; }

.cliente-box { border: 1px solid #d9d6cc; border-radius: 3px; padding: 7px 10px; margin-bottom: 8px; }
.cliente-grid { display: table; width: 100%; }
.cliente-row { display: table-row; }
.cliente-label { display: table-cell; font-size: 7.5pt; color: #555; font-weight: bold; width: 100px; padding: 1.5px 0; }
.cliente-val   { display: table-cell; font-size: 7.5pt; color: #1a1a1a; padding: 1.5px 0; }

table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 7.5pt; }
table.items thead tr { background: #3f3d37; color: #fff; }
table.items thead th { padding: 5px 6px; text-align: left; font-weight: bold; font-size: 7pt; letter-spacing: 0.3px; }
table.items thead th.center { text-align: center; }
table.items thead th.right  { text-align: right; }
table.items tbody tr:nth-child(even) { background: #faf9f5; }
table.items tbody td { padding: 4px 6px; border-bottom: 1px solid #e8e5dc; vertical-align: top; }
table.items tbody td.center { text-align: center; }
table.items tbody td.right  { text-align: right; }

.bottom-section { display: table; width: 100%; margin-top: 4px; }
.bottom-left  { display: table-cell; width: 58%; vertical-align: top; padding-right: 12px; }
.bottom-right { display: table-cell; width: 42%; vertical-align: top; }
.son-box { border: 1px solid #d9d6cc; border-radius: 3px; padding: 5px 8px; margin-bottom: 8px; }
.son-label { font-size: 6.5pt; color: #555; font-weight: bold; text-transform: uppercase; }
.son-texto { font-size: 7.5pt; font-weight: bold; margin-top: 2px; }
.caja-titulo { font-size: 6.5pt; font-weight: bold; color: #777; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #d9d6cc; padding-bottom: 2px; margin-bottom: 4px; }
.caja-texto { font-size: 7.5pt; color: #333; line-height: 1.5; white-space: pre-line; }
.tot-table { width: 100%; border-collapse: collapse; }
.tot-table td { padding: 3px 4px; font-size: 8pt; }
.tot-table td.val { text-align: right; font-weight: bold; }
.tot-table tr.total td { border-top: 2px solid #3f3d37; font-size: 10pt; font-weight: bold; padding-top: 5px; }
.aviso { margin-top: 14px; font-size: 6.8pt; color: #666; border-top: 1px solid #d9d6cc; padding-top: 6px; line-height: 1.5; }
.estado-sello { display: inline-block; font-size: 8pt; font-weight: bold; padding: 2px 8px; border: 1.5px solid #b91c1c; color: #b91c1c; border-radius: 3px; margin-bottom: 6px; }
</style>
</head>
<body>

@php
    $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $cliente = $cotizacion->cliente;
@endphp

<div class="header">
    <div class="header-left">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
        @if($empresa->nombre_comercial)
            <div class="empresa-sub">{{ $empresa->nombre_comercial }}</div>
        @endif
        <div class="empresa-dir">
            {{ $empresa->direccion }}<br>
            @if($empresa->telefono)Telf: {{ $empresa->telefono }}<br>@endif
            @if($empresa->email){{ $empresa->email }}@endif
        </div>
    </div>
    <div class="header-right">
        <div class="doc-ruc">R.U.C.: {{ $empresa->ruc }}</div>
        <div class="doc-tipo">COTIZACIÓN</div>
        <div class="doc-numero">Nro. {{ $cotizacion->codigo }}</div>
    </div>
</div>

@if(in_array($cotizacion->estado, ['anulada', 'rechazada']))
    <div class="estado-sello">{{ strtoupper($cotizacion->estado) }}</div>
@endif

<div class="info-row">
    <div class="info-cell"><div class="info-label">Fecha de emisión</div><div class="info-value">{{ $cotizacion->fecha->format('d/m/Y') }}</div></div>
    <div class="info-cell"><div class="info-label">Válida hasta</div><div class="info-value">{{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}</div></div>
    <div class="info-cell"><div class="info-label">Moneda</div><div class="info-value">Soles (PEN)</div></div>
    <div class="info-cell"><div class="info-label">Atendido por</div><div class="info-value">{{ $cotizacion->vendedor?->name ?? '—' }}</div></div>
</div>

<div class="cliente-box">
    <div class="cliente-grid">
        <div class="cliente-row"><div class="cliente-label">{{ $cliente->tipo_documento === 'RUC' ? 'R.U.C.' : $cliente->tipo_documento }}:</div><div class="cliente-val">{{ $cliente->numero_documento }}</div></div>
        <div class="cliente-row"><div class="cliente-label">{{ $cliente->tipo_documento === 'RUC' ? 'Razón social' : 'Cliente' }}:</div><div class="cliente-val">{{ $cliente->nombre }}</div></div>
        @if($cliente->direccion)
            <div class="cliente-row"><div class="cliente-label">Dirección:</div><div class="cliente-val">{{ $cliente->direccion }}</div></div>
        @endif
        @if($cliente->telefono || $cliente->email)
            <div class="cliente-row"><div class="cliente-label">Contacto:</div><div class="cliente-val">{{ collect([$cliente->telefono, $cliente->email])->filter()->implode(' · ') }}</div></div>
        @endif
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th class="center" style="width:24px">Ítem</th>
            <th style="width:78px">Código</th>
            <th>Descripción</th>
            <th class="center" style="width:34px">Cant.</th>
            <th class="right" style="width:62px">P. unit.</th>
            <th class="right" style="width:66px">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cotizacion->detalles as $i => $d)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $d->codigo ?? '—' }}</td>
            <td>{{ $d->descripcion }}</td>
            <td class="center">{{ $d->cantidad }}</td>
            <td class="right">S/ {{ number_format($d->precio_con_igv, 2) }}</td>
            <td class="right">S/ {{ number_format($d->subtotal_con_igv, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="bottom-section">
    <div class="bottom-left">
        <div class="son-box">
            <div class="son-label">Son:</div>
            <div class="son-texto">{{ montoEnLetras($cotizacion->total) }}</div>
        </div>

        @if(filled($cotizacion->condiciones))
            <div style="margin-bottom:8px">
                <div class="caja-titulo">Condiciones comerciales</div>
                <div class="caja-texto">{{ $cotizacion->condiciones }}</div>
            </div>
        @endif

        @if($pagos->count() > 0)
            <div>
                <div class="caja-titulo">Formas de pago</div>
                <div class="caja-texto">@foreach($pagos as $tipo => $pg){{ strtoupper($tipo) }}: {{ collect([$pg->banco, $pg->numero ? 'N° ' . $pg->numero : null, $pg->cci ? 'CCI ' . $pg->cci : null, $pg->titular])->filter()->implode(' · ') }}
@endforeach</div>
            </div>
        @endif
    </div>
    <div class="bottom-right">
        <table class="tot-table">
            <tr><td>Op. gravada</td><td class="val">S/ {{ number_format($cotizacion->subtotal, 2) }}</td></tr>
            <tr><td>IGV (18%)</td><td class="val">S/ {{ number_format($cotizacion->igv, 2) }}</td></tr>
            <tr class="total"><td>Total</td><td class="val">S/ {{ number_format($cotizacion->total, 2) }}</td></tr>
        </table>
    </div>
</div>

<div class="aviso">
    Cotización válida hasta el {{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}. Precios sujetos a disponibilidad de stock.
    Este documento es una propuesta comercial y no es un comprobante de pago.
</div>

</body>
</html>
