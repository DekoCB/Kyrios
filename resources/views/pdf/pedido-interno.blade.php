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
    .caja { border: 1px solid #bbb; padding: 6px 8px; margin-top: 10px; }
    .caja td { padding: 2px 4px; }
    .lbl { color: #666; width: 22%; }
    .items { margin-top: 12px; }
    .items th { background: #f1f3f5; text-align: left; font-size: 9px; text-transform: uppercase; padding: 5px; border-bottom: 1px solid #bbb; }
    .items td { padding: 5px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
    .c { text-align: center; }
    .firmas { margin-top: 70px; }
    .firmas td { text-align: center; width: 33%; padding-top: 6px; }
    .linea { border-top: 1px solid #333; margin: 0 24px; padding-top: 4px; }
</style>
</head>
<body>
@php $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null); @endphp

<table>
    <tr>
        <td style="width:55%; vertical-align:top">
            <img class="logo" src="{{ $logoSrc }}" alt="">
            <div style="margin-top:4px"><strong>{{ $empresa?->razon_social ?? config('app.name') }}</strong></div>
            @if($empresa?->ruc)<div>RUC {{ $empresa->ruc }}</div>@endif
        </td>
        <td style="vertical-align:top">
            <h1>NOTA DE PEDIDO INTERNO</h1>
            <div style="text-align:right; font-size:13px; margin-top:4px"><strong>{{ $pedido->codigo }}</strong></div>
            <div style="text-align:right">Fecha: {{ $pedido->fecha->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<table class="caja">
    <tr><td class="lbl">Lo pide</td><td><strong>{{ $pedido->destino?->nombre }}</strong></td><td class="lbl">Lo atiende</td><td><strong>{{ $pedido->origen?->nombre }}</strong></td></tr>
    <tr><td class="lbl">Solicitante</td><td>{{ $pedido->solicitante?->name }}</td><td class="lbl">Área / proyecto</td><td>{{ $pedido->area ?: '—' }}</td></tr>
    <tr><td class="lbl">Requerido para</td><td>{{ $pedido->fecha_requerida?->format('d/m/Y') ?? '—' }}</td><td class="lbl">Estado</td><td>{{ ucfirst($pedido->estado) }}@if($pedido->traslado_guia) · traslado {{ $pedido->traslado_guia }}@endif</td></tr>
</table>

<table class="items">
    <thead><tr><th style="width:5%">#</th><th>Descripción</th><th style="width:20%">Código</th><th class="c" style="width:10%">Cant.</th></tr></thead>
    <tbody>
    @foreach($pedido->detalles as $i => $d)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $d->producto ? \App\Support\EtiquetaProducto::nombre($d->producto) : '—' }}@if($d->variante?->nombre_completo) <div style="color:#777">{{ $d->variante->nombre_completo }}</div>@endif</td>
            <td>{{ $d->variante?->codigo_kyrios ?? $d->producto?->codigo }}</td>
            <td class="c">{{ $d->cantidad }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if($pedido->observaciones)
    <p style="margin-top:10px"><strong>Observaciones:</strong> {{ $pedido->observaciones }}</p>
@endif

<table class="firmas">
    <tr>
        <td><div class="linea">{{ $pedido->solicitante?->name }}<br>Solicita</div></td>
        <td><div class="linea">Almacén<br>Entrega</div></td>
        <td><div class="linea">&nbsp;<br>Recibe conforme</div></td>
    </tr>
</table>
</body>
</html>
