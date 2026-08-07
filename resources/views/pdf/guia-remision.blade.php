<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Guía de Remisión - {{ $venta->codigo }}</title>
<style>
@page { size: A4; margin: 14mm 14mm 14mm 14mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',sans-serif; font-size:7.5pt; color:#000; background:#fff; margin: 14mm 14mm 14mm 14mm; }

/* ── layout helpers ── */
.tbl   { display:table; width:100%; border-collapse:collapse; }
.tr    { display:table-row; }
.td    { display:table-cell; vertical-align:top; }

/* ── header ── */
.hdr-empresa { width:58%; padding-right:8px; vertical-align:middle; }
.hdr-doc     { width:42%; vertical-align:top; }

.empresa-logo  { height:36px; margin-bottom:3px; display:block; }
.empresa-name  { font-size:11pt; font-weight:bold; color:#1e3a5f; line-height:1.2; }
.empresa-sub   { font-size:7pt; color:#444; margin-top:1px; line-height:1.5; }

.doc-ruc-box   { background:#1e3a5f; color:#fff; font-weight:bold; font-size:9pt;
                 text-align:center; padding:4px 6px; border-radius:2px 2px 0 0; }
.doc-tipo-box  { border:1.5px solid #1e3a5f; border-top:none; text-align:center;
                 padding:3px 6px; font-weight:bold; font-size:7.5pt; color:#1e3a5f;
                 text-transform:uppercase; line-height:1.4; }
.doc-num-box   { border:1.5px solid #1e3a5f; border-top:none; text-align:center;
                 font-size:13pt; font-weight:bold; color:#1e3a5f; padding:4px 6px;
                 font-family:monospace; border-radius:0 0 2px 2px; letter-spacing:1px; }

/* ── dates row ── */
.dates-row { border:1px solid #cbd5e1; margin:5px 0 4px; padding:3px 6px; }
.date-cell { width:33%; vertical-align:middle; }
.date-label{ font-size:6.5pt; font-weight:bold; color:#555; text-transform:uppercase; }
.date-val  { font-size:8pt; font-weight:bold; color:#000; border-bottom:1px solid #cbd5e1;
             min-width:100px; display:inline-block; padding-bottom:1px; }

/* ── section boxes ── */
.box  { border:1px solid #1e3a5f; border-collapse:collapse; }
.box-title { background:#1e3a5f; color:#fff; font-size:6.5pt; font-weight:bold;
             text-transform:uppercase; letter-spacing:0.5px; padding:2px 5px; }
.box-body  { padding:3px 5px; }
.fl  { font-size:6pt; font-weight:bold; color:#777; text-transform:uppercase; margin-top:2px; }
.fv  { font-size:7.5pt; color:#000; border-bottom:1px solid #ddd; min-height:11px;
       padding-bottom:1px; margin-bottom:1px; }
.fv.bold { font-weight:bold; }

/* ── items table ── */
table.items { width:100%; border-collapse:collapse; font-size:7pt; }
table.items thead tr { background:#1e3a5f; }
table.items thead th { color:#fff; font-weight:bold; padding:3px 4px; text-align:left;
                        font-size:6.5pt; border-right:1px solid #3a5f9f; }
table.items thead th:last-child { border-right:none; }
table.items tbody tr:nth-child(even) { background:#f4f7fb; }
table.items tbody td { padding:2px 4px; border-bottom:1px solid #e2e8f0;
                        border-right:1px solid #e2e8f0; vertical-align:top; }
table.items tbody td:last-child { border-right:none; }
table.items tfoot td { background:#e8edf5; font-weight:bold; padding:2px 4px;
                        border-top:1.5px solid #1e3a5f; font-size:7pt; }

/* ── motivos checkboxes ── */
.motivo-item { font-size:6.5pt; margin-bottom:2px; line-height:1.4; }
.chk { display:inline-block; width:8px; height:8px; border:1px solid #333;
       margin-right:2px; vertical-align:middle; text-align:center; font-size:7pt;
       line-height:8px; }
.chk.marked { font-weight:bold; }

/* ── footer ── */
.sign-box { border:1px solid #cbd5e1; height:35px; margin-top:3px; }
.sign-lbl { font-size:6pt; color:#555; text-align:center; margin-top:1px; }
</style>
</head>
<body>

@php
    /* ── Logo ── */
    $logoFile = $empresa?->logo_pdf_path ?: $empresa?->logo_path;
    $logoPath = $logoFile ? storage_path('app/public/'.$logoFile) : null;
    $logoSrc  = null;
    if ($logoPath && file_exists($logoPath)) {
        $ext     = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime    = in_array($ext,['jpg','jpeg']) ? 'image/jpeg' : "image/$ext";
        $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
    }

    /* ── Motivos SUNAT ── */
    $motivosSunat = [
        'VENTA'                    => 'Venta',
        'VENTA_CONFIRMACION'       => 'Venta sujeta a confirmación del comprador',
        'COMPRA'                   => 'Compra',
        'CONSIGNACION'             => 'Consignación',
        'DEVOLUCION'               => 'Devolución',
        'TRASLADO_ENTRE_ALMACENES' => 'Traslado entre establecimientos de la misma empresa',
        'BIENES_TRANSFORMADOS'     => 'Traslado de bienes transformados',
        'RECOJO_BIENES'            => 'Recojo de bienes transformados',
        'EMISOR_ITINERANTE'        => 'Traslado por emisor itinerante de comprobante de pago',
        'IMPORTACION'              => 'Importación',
        'EXPORTACION'              => 'Exportación',
        'OTRO'                     => 'Otro',
    ];
    $motivoActual = $guia->motivo_traslado ?? '';
@endphp

{{-- ═══════════════════════════════════════════
     ENCABEZADO
════════════════════════════════════════════ --}}
<div class="tbl" style="margin-bottom:4px">
    <div class="tr">
        <div class="td hdr-empresa">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" class="empresa-logo" alt="Logo">
            @endif
            <div class="empresa-name">{{ $empresa?->razon_social ?? config('app.name') }}</div>
            <div class="empresa-sub">
                {{ $empresa?->direccion ?? '' }}
                @if($empresa?->distrito) · {{ implode(' - ', array_filter([$empresa->distrito,$empresa->provincia,$empresa->departamento])) }}@endif
                @if($empresa?->telefono) &nbsp;·&nbsp; Tel: {{ $empresa->telefono }}@endif
                @if($empresa?->email) &nbsp;·&nbsp; {{ $empresa->email }}@endif
            </div>
        </div>
        <div class="td hdr-doc">
            <div class="doc-ruc-box">R.U.C.: {{ $empresa?->ruc ?? '—' }}</div>
            <div class="doc-tipo-box">Guía de Remisión<br>Remitente</div>
            <div class="doc-num-box">{{ $venta->guia_remision ?? $venta->codigo }}</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     FECHAS
════════════════════════════════════════════ --}}
<div class="tbl dates-row">
    <div class="tr">
        <div class="td date-cell">
            <div class="date-label">Fecha de Emisión</div>
            <span class="date-val">{{ now()->format('d/m/Y') }}</span>
        </div>
        <div class="td date-cell">
            <div class="date-label">Fecha de Inicio de Traslado</div>
            <span class="date-val">{{ $guia->fecha_traslado?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="td date-cell">
            <div class="date-label">Comprobante de Pago</div>
            <span class="date-val" style="font-size:7pt">
                {{ strtoupper($venta->tipo_comprobante) }} · {{ $venta->numero_documento ?? $venta->codigo }}
            </span>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     DOMICILIO PARTIDA | LLEGADA
════════════════════════════════════════════ --}}
<div class="tbl" style="margin-bottom:4px">
    <div class="tr">
        <div class="td" style="width:50%; padding-right:3px">
            <div class="box">
                <div class="box-title">Domicilio de Partida (Origen)</div>
                <div class="box-body">
                    <div class="fl">Dirección</div>
                    <div class="fv bold">{{ $guia->direccion_partida ?? ($empresa?->direccion ?? '—') }}</div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:34%">
                                <div class="fl">Distrito</div>
                                <div class="fv">{{ $empresa?->distrito ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Provincia</div>
                                <div class="fv">{{ $empresa?->provincia ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Dpto.</div>
                                <div class="fv">{{ $empresa?->departamento ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    @if($guia->ubigeo_partida)
                    <div class="fl" style="margin-top:1px">Ubigeo</div>
                    <div class="fv">{{ $guia->ubigeo_partida }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="td" style="width:50%; padding-left:3px">
            <div class="box">
                <div class="box-title">Domicilio de Llegada (Destino)</div>
                <div class="box-body">
                    <div class="fl">Dirección</div>
                    <div class="fv bold">{{ $guia->direccion_llegada ?? $venta->cliente?->direccion ?? '—' }}</div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:34%">
                                <div class="fl">Distrito</div>
                                <div class="fv">{{ $venta->cliente?->distrito ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Provincia</div>
                                <div class="fv">{{ $venta->cliente?->provincia ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Dpto.</div>
                                <div class="fv">{{ $venta->cliente?->departamento ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    @if($guia->ubigeo_llegada)
                    <div class="fl" style="margin-top:1px">Ubigeo</div>
                    <div class="fv">{{ $guia->ubigeo_llegada }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     DESTINATARIO | DATOS TRASLADO
════════════════════════════════════════════ --}}
<div class="tbl" style="margin-bottom:4px">
    <div class="tr">
        <div class="td" style="width:58%; padding-right:3px">
            <div class="box">
                <div class="box-title">Destinatario</div>
                <div class="box-body">
                    <div class="fl">Señores</div>
                    <div class="fv bold">{{ $venta->cliente?->nombre ?? 'CONSUMIDOR FINAL' }}</div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:28%">
                                <div class="fl">Tipo Doc.</div>
                                <div class="fv">{{ strtoupper($venta->cliente?->tipo_documento ?? 'DNI') }}</div>
                            </div>
                            <div class="td">
                                <div class="fl">Nro. Documento</div>
                                <div class="fv">{{ $venta->cliente?->numero_documento ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="fl" style="margin-top:1px">Dirección</div>
                    <div class="fv">{{ $venta->cliente?->direccion ?? '—' }}</div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:34%">
                                <div class="fl">Distrito</div>
                                <div class="fv">{{ $venta->cliente?->distrito ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Provincia</div>
                                <div class="fv">{{ $venta->cliente?->provincia ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:33%">
                                <div class="fl">Dpto.</div>
                                <div class="fv">{{ $venta->cliente?->departamento ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:50%">
                                <div class="fl">Teléfono / Celular</div>
                                <div class="fv">{{ $venta->cliente?->telefono ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:50%">
                                <div class="fl">Email</div>
                                <div class="fv">{{ $venta->cliente?->email ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="td" style="width:42%; padding-left:3px">
            <div class="box">
                <div class="box-title">Datos del Traslado</div>
                <div class="box-body">
                    <div class="tbl">
                        <div class="tr">
                            <div class="td" style="width:50%">
                                <div class="fl">Modalidad</div>
                                <div class="fv">{{ $guia->modalidad_label }}</div>
                            </div>
                            <div class="td" style="width:50%">
                                <div class="fl">Peso Bruto</div>
                                <div class="fv">{{ $guia->peso_total ? number_format($guia->peso_total,2).' kg' : '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:50%">
                                <div class="fl">Nro. Bultos</div>
                                <div class="fv">{{ $guia->bultos ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:50%">
                                <div class="fl">Placa vehículo</div>
                                <div class="fv">{{ $guia->placa_vehiculo ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    @if($guia->transportista_nombre)
                    <div class="fl" style="margin-top:2px">Transportista</div>
                    <div class="fv bold">{{ $guia->transportista_nombre }}</div>
                    @if($guia->transportista_doc)
                    <div class="fv" style="font-size:6.5pt;color:#555">{{ $guia->transportista_tipo_doc ?? 'RUC' }}: {{ $guia->transportista_doc }}</div>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     TABLA DE BIENES
════════════════════════════════════════════ --}}
<div class="box" style="margin-bottom:4px">
    <div class="box-title">Detalle de Bienes a Trasladar</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:30px; text-align:center">CANT.</th>
                <th style="width:48px; text-align:center">UNIDAD</th>
                <th style="width:60px">CÓDIGO</th>
                <th>DESCRIPCIÓN</th>
                <th style="width:55px; text-align:center">SERIE / IMEI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $i => $detalle)
            @php
                $imeisLinea = $venta->imeis->filter(fn($imei) =>
                    $imei->producto_id == $detalle->producto_id &&
                    ($detalle->variante_id ? $imei->variante_id == $detalle->variante_id : true)
                );
                if ($imeisLinea->isEmpty() && $detalle->imei) {
                    $imeisLinea = collect([$detalle->imei]);
                }
            @endphp
            <tr>
                <td style="text-align:center; font-weight:bold">{{ $detalle->cantidad }}</td>
                <td style="text-align:center">{{ $detalle->producto?->unidadMedida?->abreviatura ?? 'UND' }}</td>
                <td style="font-family:monospace; font-size:6.5pt">{{ $detalle->producto?->codigo ?? '—' }}</td>
                <td>
                    <span style="font-weight:bold">{{ $detalle->producto?->nombre }}</span>
                    @if($detalle->variante)
                        <span style="color:#4f46e5; font-size:6.5pt"> · {{ $detalle->variante->nombre_completo }}</span>
                    @endif
                </td>
                <td style="text-align:center; font-size:6.5pt; font-family:monospace; color:#555">
                    @foreach($imeisLinea as $imei)
                        {{ $imei->codigo_imei }}<br>
                    @endforeach
                    @if($imeisLinea->isEmpty()) — @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align:center">{{ $venta->detalles->sum('cantidad') }}</td>
                <td colspan="3" style="text-align:right; font-size:6.5pt; color:#555">Total unidades trasladadas</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- ═══════════════════════════════════════════
     TRANSPORTISTA | MOTIVO DEL TRASLADO
════════════════════════════════════════════ --}}
<div class="tbl" style="margin-bottom:5px">
    <div class="tr">
        {{-- Transportista / Vehículo --}}
        <div class="td" style="width:45%; padding-right:3px">
            <div class="box">
                <div class="box-title">{{ $guia->modalidad === 'publico' ? 'Transportista' : 'Unidad de Transporte / Conductor' }}</div>
                <div class="box-body">
                @if($guia->modalidad === 'privado')
                    <div class="fl">Vehículo marca y placa</div>
                    <div class="fv">{{ $guia->placa_vehiculo ?? '—' }}</div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:50%">
                                <div class="fl">Cert. de Inscripción</div>
                                <div class="fv">—</div>
                            </div>
                            <div class="td" style="width:50%">
                                <div class="fl">Licencia de Conducir</div>
                                <div class="fv">{{ $guia->conductor_licencia ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:55%">
                                <div class="fl">Nombre</div>
                                <div class="fv bold">{{ $guia->conductor_nombre ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:45%">
                                <div class="fl">DNI</div>
                                <div class="fv">{{ $guia->conductor_dni ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="tbl" style="margin-top:2px">
                        <div class="tr">
                            <div class="td" style="width:55%">
                                <div class="fl">Razón Social / Nombre</div>
                                <div class="fv bold">{{ $guia->transportista_nombre ?? '—' }}</div>
                            </div>
                            <div class="td" style="width:45%">
                                <div class="fl">{{ $guia->transportista_tipo_doc ?? 'RUC' }}</div>
                                <div class="fv">{{ $guia->transportista_doc ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                @endif
                </div>
            </div>
        </div>
        {{-- Motivo del traslado (checkboxes) --}}
        <div class="td" style="width:55%; padding-left:3px">
            <div class="box">
                <div class="box-title">Motivo del Traslado</div>
                <div class="box-body">
                    <div class="tbl">
                        <div class="tr">
                            <div class="td" style="width:50%; padding-right:4px">
                                @foreach(['VENTA'=>'Venta','VENTA_CONFIRMACION'=>'Venta sujeta a confirmación del comprador','COMPRA'=>'Compra','CONSIGNACION'=>'Consignación','DEVOLUCION'=>'Devolución','TRASLADO_ENTRE_ALMACENES'=>'Traslado entre establecimientos de la misma empresa'] as $k => $lbl)
                                <div class="motivo-item">
                                    <span class="chk {{ $motivoActual === $k ? 'marked' : '' }}">{{ $motivoActual === $k ? 'X' : ' ' }}</span>
                                    {{ $lbl }}
                                </div>
                                @endforeach
                            </div>
                            <div class="td" style="width:50%">
                                @foreach(['BIENES_TRANSFORMADOS'=>'Traslado de bienes transformados','RECOJO_BIENES'=>'Recojo de bienes transformados','EMISOR_ITINERANTE'=>'Traslado por emisor itinerante de comprobante de pago','IMPORTACION'=>'Importación','EXPORTACION'=>'Exportación','OTRO'=>'Otro no incluido en los puntos anteriores'] as $k => $lbl)
                                <div class="motivo-item">
                                    <span class="chk {{ $motivoActual === $k ? 'marked' : '' }}">{{ $motivoActual === $k ? 'X' : ' ' }}</span>
                                    {{ $lbl }}
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     FIRMAS
════════════════════════════════════════════ --}}
<div class="tbl">
    <div class="tr">
        <div class="td" style="width:33%; padding-right:4px; text-align:center">
            <div class="sign-box"></div>
            <div class="sign-lbl">Firma y Sello del Remitente<br>{{ $empresa?->razon_social ?? '' }}</div>
        </div>
        <div class="td" style="width:34%; padding:0 4px; text-align:center">
            <div class="sign-box"></div>
            <div class="sign-lbl">Conformidad de Recepción<br>Fecha: ___/___/______</div>
        </div>
        <div class="td" style="width:33%; padding-left:4px; text-align:center">
            <div class="sign-box"></div>
            <div class="sign-lbl">Firma Transportista / Chofer<br>DNI: _____________________</div>
        </div>
    </div>
</div>

<div style="text-align:center; font-size:6pt; color:#94a3b8; margin-top:5px; border-top:1px solid #e2e8f0; padding-top:3px">
    Generado el {{ now()->format('d/m/Y H:i') }} · {{ config('app.name') }}
</div>

</body>
</html>
