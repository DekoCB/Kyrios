<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    .logo { height: 56px; }
    h1 { font-size: 15px; margin: 0; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: top; }
    .caja { border: 1px solid #bbb; padding: 6px 8px; margin-top: 10px; }
    .caja td { padding: 2px 4px; }
    .lbl { color: #666; width: 22%; }
    .items { margin-top: 12px; }
    .items th { background: #f1f3f5; text-align: left; font-size: 9px; text-transform: uppercase; padding: 5px; border-bottom: 1px solid #bbb; }
    .items td { padding: 5px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
    .r { text-align: right; }
    .c { text-align: center; }
    .tot { width: 42%; margin-left: 58%; margin-top: 8px; }
    .tot td { padding: 3px 5px; }
    .tot .final td { border-top: 1px solid #333; font-size: 12px; font-weight: bold; }
    .firmas { margin-top: 70px; }
    .firmas td { border: 0; text-align: center; width: 50%; padding-top: 6px; }
    .linea { border-top: 1px solid #333; margin: 0 40px; padding-top: 4px; }
    .obs { margin-top: 10px; color: #444; }
</style>
</head>
<body>
@php
    $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $s = $pedido->simbolo_moneda;
    $internacional = $pedido->es_internacional;
@endphp

<table class="cab">
    <tr>
        <td style="width:55%">
            <img class="logo" src="{{ $logoSrc }}" alt="">
            <div style="margin-top:4px"><strong>{{ $empresa?->razon_social ?? config('app.name') }}</strong></div>
            @if($empresa?->ruc)<div>RUC {{ $empresa->ruc }}</div>@endif
            @if($empresa?->direccion)<div>{{ $empresa->direccion }}</div>@endif
        </td>
        <td>
            <h1>ORDEN DE COMPRA {{ $internacional ? 'INTERNACIONAL' : 'NACIONAL' }}</h1>
            <div style="text-align:right; font-size:13px; margin-top:4px"><strong>{{ $pedido->codigo }}</strong></div>
            <div style="text-align:right">Fecha: {{ $pedido->fecha->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<table class="caja">
    <tr><td class="lbl">Proveedor</td><td><strong>{{ $pedido->proveedor?->razon_social }}</strong></td>
        <td class="lbl">{{ $internacional ? 'Tax ID / RUC' : 'RUC' }}</td><td>{{ $pedido->proveedor?->ruc ?: '—' }}</td></tr>
    <tr><td class="lbl">Dirección</td><td>{{ $pedido->proveedor?->direccion ?: '—' }}</td>
        <td class="lbl">Moneda</td><td>{{ $pedido->moneda === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)' }}@if($pedido->tipo_cambio) · T.C. {{ number_format((float) $pedido->tipo_cambio, 3) }}@endif</td></tr>
    <tr><td class="lbl">Destino</td><td>{{ $pedido->almacen?->nombre ?: '—' }}</td>
        <td class="lbl">Entrega esperada</td><td>{{ $pedido->fecha_esperada?->format('d/m/Y') ?: '—' }}</td></tr>
    @if($internacional && $pedido->incoterm)
        <tr><td class="lbl">Incoterm</td><td>{{ $pedido->incoterm }}</td><td class="lbl"></td><td></td></tr>
    @endif
    <tr><td class="lbl">Condición de pago</td><td>{{ $pedido->condicion_pago ?: '—' }}</td>
        <td class="lbl">Lugar de entrega</td><td>{{ $pedido->lugar_entrega ?: '—' }}</td></tr>
</table>

<table class="items">
    <thead>
        <tr><th style="width:4%">#</th><th>Descripción</th><th class="c" style="width:9%">Cant.</th><th class="r" style="width:14%">P. unit. {{ $s }}</th><th class="r" style="width:15%">Importe {{ $s }}</th></tr>
    </thead>
    <tbody>
    @foreach($pedido->detalles as $i => $d)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>
                {{ $d->producto ? \App\Support\EtiquetaProducto::nombre($d->producto) : '—' }}
                <div style="color:#777">{{ $d->variante?->codigo_kyrios ?: $d->producto?->codigo }}@if($d->variante?->nombre_completo) · {{ $d->variante->nombre_completo }}@endif</div>
            </td>
            <td class="c">{{ $d->cantidad }}</td>
            <td class="r">{{ number_format((float) $d->precio_referencial, 2) }}</td>
            <td class="r">{{ number_format($d->cantidad * (float) $d->precio_referencial, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="tot">
    <tr><td>Subtotal</td><td class="r">{{ $s }} {{ number_format((float) $pedido->subtotal, 2) }}</td></tr>
    @unless($internacional)
        <tr><td>IGV (18%)</td><td class="r">{{ $s }} {{ number_format((float) $pedido->igv, 2) }}</td></tr>
    @endunless
    <tr class="final"><td>TOTAL</td><td class="r">{{ $s }} {{ number_format((float) $pedido->total, 2) }}</td></tr>
</table>

@if($pedido->observaciones)
    <div class="obs"><strong>Observaciones:</strong> {{ $pedido->observaciones }}</div>
@endif
@if($internacional)
    <div class="obs">Los impuestos de importación y gastos de aduana se registran al recibir la mercadería.</div>
@endif

<table class="firmas">
    <tr>
        <td><div class="linea">{{ $pedido->usuario?->name ?? 'Responsable' }}<br>Emitido por</div></td>
        <td><div class="linea">Administración<br>Aprobado por</div></td>
    </tr>
</table>
</body>
</html>
