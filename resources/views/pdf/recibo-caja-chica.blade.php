<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
body, div, p, h1, table, td, th, img { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 18mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; }
.recibo { page-break-after: always; }
.recibo:last-child { page-break-after: auto; }
.cab { width: 100%; border-collapse: collapse; margin-bottom: 8mm; }
.cab td { vertical-align: top; }
.logo { height: 16mm; }
.empresa { font-size: 11pt; font-weight: bold; color: #3f3d37; }
.empresa-dir { font-size: 8pt; color: #555; line-height: 1.4; }
.titulo { background: #f2c80f; color: #3f3d37; font-weight: bold; font-size: 12pt; text-align: center; padding: 6px 10px; letter-spacing: 1px; }
.numero { border: 1.5px solid #f2c80f; background: #faf9f5; font-weight: bold; font-size: 12pt; text-align: center; padding: 6px 10px; }
.monto { margin: 6mm 0 4mm; border: 1.5px solid #4a4a45; padding: 4mm 6mm; font-size: 14pt; font-weight: bold; text-align: right; }
.datos { width: 100%; border-collapse: collapse; }
.datos td { padding: 3mm 2mm; border-bottom: 1px solid #e0ddd2; vertical-align: top; font-size: 9.5pt; }
.datos td.e { width: 38mm; color: #555; font-weight: bold; }
.letras { margin: 4mm 0; font-size: 9pt; font-style: italic; }
.firmas { width: 100%; border-collapse: collapse; margin-top: 30mm; }
.firmas td { width: 50%; text-align: center; font-size: 8.5pt; padding: 0 8mm; }
.firmas .linea { border-top: 1px solid #000; padding-top: 2mm; }
.nota { margin-top: 12mm; font-size: 7.5pt; color: #666; border-top: 1px solid #d9d6cc; padding-top: 3mm; line-height: 1.5; }
</style>
</head>
<body>
@php
    $logo = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $m = fn ($n) => 'S/ ' . number_format((float) $n, 2);
@endphp

@foreach($gastos as $g)
    @php
        $beneficiario = $g->emisor ?: ($g->registrador?->name ?? '—');
        $comprobante = $g->tipo_comprobante === 'sin_comprobante' ? 'Sin comprobante' : trim($g->comprobante_nombre . ' ' . $g->numero_comprobante);
    @endphp
    <div class="recibo">
        <table class="cab">
            <tr>
                <td style="width:60%">
                    @if($logo)<img src="{{ $logo }}" class="logo" alt=""><br>@endif
                    <div class="empresa">{{ $empresa->razon_social }}</div>
                    <div class="empresa-dir">@if($empresa->ruc)R.U.C.: {{ $empresa->ruc }}<br>@endif{{ $empresa->direccion }}</div>
                </td>
                <td style="width:40%">
                    <div class="titulo">RECIBO DE PAGO</div>
                    <div class="numero">{{ $g->recibo_numero }}</div>
                </td>
            </tr>
        </table>

        <div class="monto">{{ $m($g->monto) }}</div>
        <div class="letras">Son: {{ montoEnLetras((float) $g->monto) }}</div>

        <table class="datos">
            <tr><td class="e">Pagado a:</td><td>{{ $beneficiario }}</td></tr>
            <tr><td class="e">Por concepto de:</td><td>{{ $g->concepto }} <span style="color:#777">({{ $g->categoria_nombre }})</span></td></tr>
            <tr><td class="e">Fecha del gasto:</td><td>{{ $g->fecha->format('d/m/Y') }}</td></tr>
            <tr><td class="e">Comprobante de origen:</td><td>{{ $comprobante }}</td></tr>
            <tr><td class="e">Caja chica:</td><td>{{ $caja->codigo }} · {{ $caja->nombre }}</td></tr>
            <tr><td class="e">Registrado por:</td><td>{{ $g->registrador?->name ?? '—' }}</td></tr>
        </table>

        <table class="firmas">
            <tr>
                <td><div class="linea">Entregué conforme<br>{{ $caja->responsable?->name ?? 'Responsable de caja chica' }}</div></td>
                <td><div class="linea">Recibí conforme<br>Nombre y DNI: ______________________</div></td>
            </tr>
        </table>

        <div class="nota">Recibo emitido el {{ $g->recibo_emitido_en?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }} a partir del gasto registrado en la caja chica {{ $caja->codigo }}. Sirve como sustento interno del pago.</div>
    </div>
@endforeach
</body>
</html>
