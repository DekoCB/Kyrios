<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Marketing</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; } h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #1e3a8a; color: #1e3a8a; padding-bottom: 2px; }
        .sub { color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; } th { background: #dbeafe; text-align: right; padding: 4px; font-size: 9px; } td { padding: 4px; border-bottom: 1px solid #e5e7eb; text-align: right; }
        th:first-child, td:first-child { text-align: left; } .tot td { font-weight: bold; background: #f3f4f6; }
        .kpi { display: inline-block; width: 23%; margin: 0 1% 6px 0; padding: 6px; background: #f3f4f6; } .kpi b { display: block; font-size: 14px; }
        .pos { color: #15803d; } .neg { color: #b91c1c; }
    </style>
</head>
<body>
@php use App\Support\Marketing; use App\Models\Marketing\Campana; $t = $totales; @endphp
<h1>{{ $empresa?->nombre_display ?? config('app.name') }} — Reporte de Marketing</h1>
<div class="sub">{{ $etiqueta }}{{ $red ? ' · ' . Marketing::nombreRed($red) : '' }} · Generado el {{ now()->format('d/m/Y H:i') }}@if($anterior) · Comparado con {{ $anterior['etiqueta'] }}@endif</div>

@foreach([['Seguidores', Marketing::numero($t['seguidores'])], ['Crecimiento', ($t['crecimiento'] ?? null) === null ? '—' : (($t['crecimiento'] > 0 ? '+' : '') . Marketing::numero($t['crecimiento']))], ['Alcance', Marketing::numero($t['alcance'])], ['Impresiones', Marketing::numero($t['impresiones'])], ['Interacciones', Marketing::numero($t['interacciones'])], ['Reproducciones', Marketing::numero($t['reproducciones'])], ['Clics', Marketing::numero($t['clics'])], ['Engagement', Marketing::porcentaje($t['engagement'])]] as [$e, $v])
    <div class="kpi">{{ $e }}<b>{{ $v }}</b></div>
@endforeach

<h2>Resultados por cuenta</h2>
<table>
    <thead><tr><th>Cuenta</th><th>Seguid.</th><th>Crec.</th><th>Alcance</th><th>Impresiones</th><th>Interacc.</th><th>Reprod.</th><th>Clics</th><th>Engag.</th>@if($anterior)<th>Δ Interacc.</th>@endif</tr></thead>
    <tbody>
    @foreach($filas as $f)
        <tr><td>{{ $f['cuenta']->nombre }} ({{ $f['cuenta']->red_nombre }})</td><td>{{ Marketing::numero($f['seguidores']) }}</td><td>{{ $f['crecimiento'] === null ? '—' : ($f['crecimiento'] > 0 ? '+' : '') . Marketing::numero($f['crecimiento']) }}</td>
            <td>{{ Marketing::numero($f['alcance']) }}</td><td>{{ Marketing::numero($f['impresiones']) }}</td><td>{{ Marketing::numero($f['interacciones']) }}</td><td>{{ Marketing::numero($f['reproducciones']) }}</td><td>{{ Marketing::numero($f['clics']) }}</td><td>{{ Marketing::porcentaje($f['engagement']) }}</td>
            @if($anterior)@php $v = $est->variacion($f['interacciones'], $anterior['filas']->get($f['cuenta']->id)['interacciones'] ?? null); @endphp<td class="{{ ($v ?? 0) >= 0 ? 'pos' : 'neg' }}">{{ $v === null ? '—' : ($v >= 0 ? '+' : '') . $v . ' %' }}</td>@endif</tr>
    @endforeach
    <tr class="tot"><td>Total</td><td>{{ Marketing::numero($t['seguidores']) }}</td><td>{{ ($t['crecimiento'] ?? null) === null ? '—' : Marketing::numero($t['crecimiento']) }}</td><td>{{ Marketing::numero($t['alcance']) }}</td><td>{{ Marketing::numero($t['impresiones']) }}</td><td>{{ Marketing::numero($t['interacciones']) }}</td><td>{{ Marketing::numero($t['reproducciones']) }}</td><td>{{ Marketing::numero($t['clics']) }}</td><td>{{ Marketing::porcentaje($t['engagement']) }}</td>@if($anterior)<td></td>@endif</tr>
    </tbody>
</table>

<h2>Publicaciones con mejores resultados</h2>
<table>
    <thead><tr><th>Publicación</th><th>Fecha</th><th>Alcance</th><th>Interacciones</th><th>Engagement</th></tr></thead>
    <tbody>
    @forelse($mejores as $p)
        <tr><td>{{ $p->titulo }} ({{ $p->red_nombre }})</td><td>{{ $p->publicada_en->format('d/m/Y') }}</td><td>{{ Marketing::numero($p->alcance) }}</td><td>{{ Marketing::numero($p->interacciones) }}</td><td>{{ Marketing::porcentaje($p->engagement) }}</td></tr>
    @empty
        <tr><td colspan="5">Sin resultados cargados en el período.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Campañas</h2>
<table>
    <thead><tr><th>Campaña</th><th>Presupuesto</th><th>Inversión</th><th>Clics</th><th>CPC</th><th>Conv.</th><th>ROAS</th></tr></thead>
    <tbody>
    @forelse($campanas as $c)
        <tr><td>{{ $c->nombre }} ({{ $c->red_nombre }})</td><td>{{ $c->simbolo }} {{ number_format($c->presupuesto, 2) }}</td><td>{{ $c->simbolo }} {{ number_format($c->inversion, 2) }}</td><td>{{ Marketing::numero($c->clics) }}</td>
            <td>{{ $c->cpc === null ? '—' : $c->simbolo . ' ' . number_format($c->cpc, 2) }}</td><td>{{ Marketing::numero($c->conversiones) }}</td><td>{{ $c->roas === null ? '—' : $c->roas . 'x' }}</td></tr>
    @empty
        <tr><td colspan="7">No hubo campañas en el período.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
