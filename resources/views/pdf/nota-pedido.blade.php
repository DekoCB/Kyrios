<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
/* sin «*»: un margin:0 universal anula el margen de @page en dompdf */
body, div, p, h1, h2, table, td, th { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 10mm 14mm 8mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1a1a1a; }

/* Estructura del formato comercial «Nota de pedido» (Excel) */
.titulo { background: #17375e; color: #fff; text-align: right; font-size: 18pt; font-weight: bold; padding: 2mm 4mm; }
.subtitulo { text-align: right; font-style: italic; font-size: 7.5pt; color: #666; padding: 1mm 2mm 2mm; }
.seccion { background: #17375e; color: #fff; font-weight: bold; font-size: 8pt; padding: 1.3mm 2mm; margin-top: 2.5mm; }

table.campos { width: 100%; border-collapse: collapse; background: #f2f6fb; }
table.campos td { padding: 0.9mm 2mm; font-size: 7.5pt; vertical-align: bottom; }
table.campos td.e { font-weight: bold; color: #17375e; font-size: 7pt; width: 19%; text-transform: uppercase; }
table.campos td.v { width: 31%; border-bottom: 1px solid #9fb6cf; }
table.campos.cabecera { background: #fff; margin-top: 2mm; }
table.campos.cabecera td.v { background: #f2f6fb; }

table.detalle { width: 100%; border-collapse: collapse; margin-top: 0; }
table.detalle th { background: #4472c4; color: #fff; font-size: 7pt; padding: 1.8mm 2mm; text-transform: uppercase; border-bottom: 2px solid #17375e; }
table.detalle td { font-size: 7.5pt; padding: 0.8mm 2mm; border-bottom: 1px solid #b9c9dc; height: 5.3mm; vertical-align: middle; }
table.detalle tr.par td { background: #f8fafc; }
.c { text-align: center; } .r { text-align: right; }
.indic { margin-top: 2mm; font-size: 7.5pt; color: #333; }
.pie { margin-top: 2mm; font-size: 6.5pt; color: #777; text-align: center; }
</style>
</head>
<body>

@php
    $cli = $nota->cliente;
    $lineas = $nota->detalles->values();
    $filas = max(20, $lineas->count());   // el formato trae 20 renglones; si hay más productos se agregan
    $vendedor = $nota->creador?->name ?? $nota->cotizacion?->vendedor?->name;
@endphp

<div class="titulo">NOTA DE PEDIDO</div>
<div class="subtitulo">FORMATO COMERCIAL / REGISTRO DE PEDIDOS</div>

<table class="campos cabecera">
    <tr>
        <td class="e">N.º de pedido</td><td class="v">{{ $nota->codigo }}</td>
        <td class="e">Fecha de emisión</td><td class="v">{{ $nota->created_at->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="e">Vendedor</td><td class="v">{{ $vendedor }}</td>
        <td class="e">Moneda</td><td class="v">{{ $nota->moneda === 'PEN' ? 'Soles (S/)' : 'Dólares (US$)' }}</td>
    </tr>
</table>

<div class="seccion">DATOS DEL CLIENTE</div>
<table class="campos">
    <tr>
        <td class="e">Razón social / Cliente</td><td class="v">{{ $cli?->nombre }}</td>
        <td class="e">RUC / DNI</td><td class="v">{{ $cli?->numero_documento }}</td>
    </tr>
    <tr>
        <td class="e">Dirección</td><td class="v">{{ $cli?->direccion }}</td>
        <td class="e">Teléfono</td><td class="v">{{ $cli?->telefono }}</td>
    </tr>
    <tr>
        <td class="e">Contacto</td><td class="v">{{ $nota->contacto ?: $cli?->contacto }}</td>
        <td class="e">Correo</td><td class="v">{{ $cli?->email }}</td>
    </tr>
</table>

<div class="seccion">CONDICIONES DEL PEDIDO</div>
<table class="campos">
    <tr>
        <td class="e">Forma de pago</td><td class="v">{{ $nota->forma_pago }}</td>
        <td class="e">Condición</td><td class="v">{{ $nota->condicion }}</td>
    </tr>
    <tr>
        <td class="e">Tipo de comprobante</td><td class="v">{{ $nota->tipo_comprobante }}</td>
        <td class="e">Transporte</td><td class="v">{{ $nota->transporte }}</td>
    </tr>
    <tr>
        <td class="e">Lugar de entrega</td><td class="v">{{ $nota->lugar_entrega }}</td>
        <td class="e">Referencia</td><td class="v">{{ $nota->referencia }}</td>
    </tr>
</table>

<div class="seccion">DETALLE DE PRODUCTOS / SERVICIOS</div>
<table class="detalle">
    <thead>
        <tr>
            <th style="width:11mm">Ítem</th><th style="width:34mm">Código</th><th>Descripción</th><th style="width:16mm">Unidad</th><th style="width:19mm">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        @for($i = 0; $i < $filas; $i++)
            @php($d = $lineas[$i] ?? null)
            <tr class="{{ $i % 2 ? 'par' : '' }}">
                <td class="c">{{ $i + 1 }}</td>
                @if($d)
                    <td>{{ $d->codigo ?: ($d->variante?->codigo_kyrios ?: $d->producto?->codigo) }}</td>
                    <td>{{ $d->producto ? \App\Support\EtiquetaProducto::documento($d->producto, $d->variante) : $d->descripcion }}@if($d->variante && $d->variante->nombre_completo !== 'Variante base') — {{ $d->variante->nombre_completo }}@endif</td>
                    <td class="c">{{ $d->unidad ?: ($d->producto?->unidadMedida?->abreviatura ?: 'UND') }}</td>
                    <td class="c"><strong>{{ $d->cantidad }}</strong></td>
                @else
                    <td></td><td></td><td></td><td></td>
                @endif
            </tr>
        @endfor
    </tbody>
</table>

@if($nota->observaciones)
    <div class="indic"><strong>Indicaciones para almacén:</strong> {{ $nota->observaciones }}</div>
@endif
<div class="pie">{{ $empresa->razon_social }}@if($empresa->ruc) · RUC {{ $empresa->ruc }}@endif · Nota de pedido generada desde la cotización {{ $nota->cotizacion?->codigo }}@if($nota->fecha_entrega) · Entrega prevista: {{ $nota->fecha_entrega->format('d/m/Y') }}@endif</div>
</body>
</html>
