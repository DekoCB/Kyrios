<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    .logo { height: 56px; }
    h1 { font-size: 15px; margin: 6px 0 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #f1f3f5; text-align: left; font-size: 9px; text-transform: uppercase; padding: 5px; border-bottom: 1px solid #bbb; }
    td { padding: 5px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
    .r { text-align: right; }
    .rech { text-decoration: line-through; color: #888; }
    .resumen td { border: 0; padding: 2px 5px; }
    .firmas { margin-top: 60px; width: 100%; }
    .firmas td { border: 0; text-align: center; width: 50%; padding-top: 6px; }
    .linea { border-top: 1px solid #333; margin: 0 40px; padding-top: 4px; }
</style>
</head>
<body>
@php
    $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $ing = $movimientos->where('tipo', 'ingreso')->sum('monto');
    $gas = $movimientos->where('tipo', 'gasto')->where('estado', '!=', 'rechazado')->sum('monto');
@endphp
<img class="logo" src="{{ $logoSrc }}" alt="">
<h1>Rendición de caja chica {{ $caja->codigo }}</h1>
<div>{{ $caja->nombre }} · {{ $caja->tipo === 'general' ? 'Caja general' : 'Responsable: ' . ($caja->responsable?->name ?? '—') }}</div>
<div>Emitido el {{ now()->format('d/m/Y H:i') }} · Estado: {{ $caja->esta_activa ? 'Activa' : 'Cerrada' }}</div>

<table class="resumen" style="width:auto; margin-top:8px;">
    <tr><td>Dinero entregado:</td><td class="r"><strong>S/ {{ number_format($ing, 2) }}</strong></td></tr>
    <tr><td>Gastos (sin rechazados):</td><td class="r"><strong>S/ {{ number_format($gas, 2) }}</strong></td></tr>
    <tr><td>Saldo:</td><td class="r"><strong>S/ {{ number_format($ing - $gas, 2) }}</strong></td></tr>
</table>

<table>
    <thead><tr><th>Fecha</th><th>Detalle</th><th>Comprobante</th><th>Estado</th><th class="r">Monto S/</th></tr></thead>
    <tbody>
    @foreach($movimientos as $m)
        <tr class="{{ $m->estado === 'rechazado' ? 'rech' : '' }}">
            <td>{{ $m->fecha->format('d/m/Y') }}</td>
            <td>{{ $m->concepto }}@if($m->tipo === 'gasto') <br><span style="color:#777">{{ $m->categoria_nombre }}@if($m->emisor) · {{ $m->emisor }}@endif</span>@endif</td>
            <td>@if($m->tipo === 'gasto'){{ $m->comprobante_nombre }} {{ $m->numero_comprobante }}<br><span style="color:#777">{{ $m->adjuntos->count() }} archivo(s)</span>@else — @endif</td>
            <td>{{ $m->tipo === 'ingreso' ? 'Ingreso' : ucfirst($m->estado) }}</td>
            <td class="r">{{ $m->tipo === 'ingreso' ? '+' : '-' }}{{ number_format($m->monto, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="firmas">
    <tr>
        <td><div class="linea">{{ $caja->responsable?->name ?? 'Responsable' }}<br>Responsable de la caja</div></td>
        <td><div class="linea">Administración<br>Revisado por</div></td>
    </tr>
</table>
</body>
</html>
