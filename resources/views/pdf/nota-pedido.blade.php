<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.partials.estilos-simples')
.casilla { display: inline-block; width: 11px; height: 11px; border: 1.3px solid #333; }
</style>
</head>
<body>
@include('pdf.partials.cabecera-simple', ['titulo' => 'NOTA DE PEDIDO', 'numero' => $nota->codigo])

<div class="caja">
    <div class="fila"><div class="e">Cliente:</div><div class="v">{{ $nota->cliente?->nombre ?? '—' }}</div></div>
    <div class="fila"><div class="e">Cotización:</div><div class="v">{{ $nota->cotizacion?->codigo }}</div></div>
    <div class="fila"><div class="e">Almacén:</div><div class="v">{{ $nota->almacen?->nombre ?? '—' }}</div></div>
    <div class="fila"><div class="e">Entrega prevista:</div><div class="v">{{ $nota->fecha_entrega?->format('d/m/Y') ?? 'Por coordinar' }}</div></div>
    <div class="fila"><div class="e">Generada por:</div><div class="v">{{ $nota->creador?->name ?? '—' }} · {{ $nota->created_at->format('d/m/Y H:i') }}</div></div>
    @if($nota->observaciones)<div class="fila"><div class="e">Indicaciones:</div><div class="v">{{ $nota->observaciones }}</div></div>@endif
</div>

<table class="t">
    <thead><tr><th class="c" style="width:22px">N.º</th><th>Producto</th><th>Código</th><th class="c" style="width:44px">Cant.</th><th class="c" style="width:44px">Listo</th></tr></thead>
    <tbody>
        @foreach($nota->detalles as $i => $d)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $d->producto ? \App\Support\EtiquetaProducto::documento($d->producto, $d->variante) : $d->descripcion }}@if($d->variante && $d->variante->nombre_completo !== 'Variante base') — {{ $d->variante->nombre_completo }}@endif</td>
                <td>{{ $d->variante?->codigo_kyrios }}</td>
                <td class="c"><strong>{{ $d->cantidad }}</strong></td>
                <td class="c"><span class="casilla"></span></td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="nota">Preparar y separar estos productos en almacén. Marque cada línea al dejarla lista y registre la preparación en el sistema para avisar a ventas.</div>
</body>
</html>
