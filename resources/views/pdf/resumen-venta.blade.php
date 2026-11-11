<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.partials.estilos-simples')
h2.sec { font-size: 9pt; color: #3f3d37; margin: 10px 0 4px; border-bottom: 2px solid #f2c80f; padding-bottom: 2px; }
.tot { width: 40%; margin-left: 60%; border-collapse: collapse; margin-bottom: 6px; }
.tot td { padding: 3px 4px; font-size: 8pt; }
.tot td.r { font-weight: bold; }
.tot tr.total td { border-top: 2px solid #3f3d37; font-size: 10pt; font-weight: bold; padding-top: 5px; }
.estado { display: inline-block; font-size: 7pt; font-weight: bold; padding: 1px 6px; border: 1px solid #999; border-radius: 3px; text-transform: uppercase; }
</style>
</head>
<body>

@php
    $cliente = $venta->cliente;
    $m = fn ($n) => 'S/ ' . number_format((float) $n, 2);
@endphp

@include('pdf.partials.cabecera-simple', ['titulo' => 'PROFORMA Y PRESUPUESTO', 'numero' => 'Venta ' . $venta->codigo])

<div class="caja">
    <div class="caja-titulo">Datos de la venta</div>
    <div class="fila"><div class="e">Fecha:</div><div class="v">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</div></div>
    <div class="fila"><div class="e">Cliente:</div><div class="v">{{ $cliente?->nombre ?? 'Sin cliente' }}@if($cliente?->numero_documento) ({{ $cliente->tipo_documento }} {{ $cliente->numero_documento }})@endif</div></div>
    @if($cliente?->direccion)<div class="fila"><div class="e">Dirección:</div><div class="v">{{ $cliente->direccion }}</div></div>@endif
    <div class="fila"><div class="e">Comprobante:</div><div class="v">{{ strtoupper(str_replace('_', ' ', (string) $venta->tipo_comprobante)) }}@if($venta->numero_documento) {{ $venta->numero_documento }}@endif</div></div>
    <div class="fila"><div class="e">Condición de pago:</div><div class="v">{{ $venta->es_credito ? 'Crédito' : 'Contado' }} · {{ ucfirst((string) $venta->estado_pago) }}</div></div>
    <div class="fila"><div class="e">Vendedor:</div><div class="v">{{ $venta->vendedor?->name ?? '—' }}</div></div>
</div>

<h2 class="sec">Proformas / cotizaciones de esta venta</h2>
@forelse($cotizaciones as $cot)
    <div class="caja">
        <div class="fila">
            <div class="e">{{ $cot->codigo }}</div>
            <div class="v">{{ $cot->fecha->format('d/m/Y') }} · válida hasta {{ $cot->fecha_vencimiento->format('d/m/Y') }} · <span class="estado">{{ $cot->estado }}</span> · Total {{ $m($cot->total) }}</div>
        </div>
        <table class="t" style="margin-top:4px">
            <thead><tr><th class="c" style="width:24px">Ítem</th><th>Descripción</th><th class="c" style="width:34px">Cant.</th><th class="r" style="width:62px">P. unit.</th><th class="r" style="width:66px">Total</th></tr></thead>
            <tbody>
                @foreach($cot->detalles as $i => $d)
                    <tr><td class="c">{{ $i + 1 }}</td><td>{{ $d->nombre_mostrar }}</td><td class="c">{{ $d->cantidad }}</td><td class="r">{{ $m($d->precio_con_igv) }}</td><td class="r">{{ $m($d->subtotal_con_igv) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p style="font-size:7.5pt;color:#777;margin-bottom:6px">Esta venta no proviene de una cotización.</p>
@endforelse

<h2 class="sec">Presupuesto (detalle de la venta)</h2>
<table class="t">
    <thead><tr><th class="c" style="width:24px">Ítem</th><th>Descripción</th><th class="c" style="width:34px">Cant.</th><th class="r" style="width:62px">P. unit.</th><th class="r" style="width:66px">Total</th></tr></thead>
    <tbody>
        @foreach($venta->detalles as $i => $d)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $d->producto ? \App\Support\EtiquetaProducto::documento($d->producto, $d->variante) : 'Producto' }}@if($d->variante && $d->variante->nombre_completo !== 'Variante base') — {{ $d->variante->nombre_completo }}@endif</td>
                <td class="c">{{ $d->cantidad }}</td>
                <td class="r">{{ $m($d->precio_con_igv) }}</td>
                <td class="r">{{ $m($d->subtotal_con_igv) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<table class="tot">
    <tr><td>Subtotal</td><td class="r">{{ $m($venta->subtotal) }}</td></tr>
    <tr><td>IGV (18%)</td><td class="r">{{ $m($venta->igv) }}</td></tr>
    <tr class="total"><td>TOTAL</td><td class="r">{{ $m($venta->total) }}</td></tr>
</table>

<div class="nota">Documento informativo generado por el sistema; no reemplaza al comprobante de pago. Este formato será reemplazado por el oficial.</div>

</body>
</html>
