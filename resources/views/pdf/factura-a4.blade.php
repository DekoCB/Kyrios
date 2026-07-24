<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 18mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1a1a1a; background: #fff; margin: 18mm 16mm; }

/* ─── HEADER ─── */
.header { display: table; width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.header-left  { display: table-cell; width: 62%; vertical-align: top; padding-right: 12px; }
.header-right { display: table-cell; width: 38%; vertical-align: top; }

.logo { height: 54px; margin-bottom: 4px; }
.empresa-nombre { font-size: 13pt; font-weight: bold; color: #1e3a5f; margin-bottom: 2px; }
.empresa-sub { font-size: 7.5pt; color: #555; margin-bottom: 6px; }
.empresa-dir { font-size: 7.5pt; color: #333; line-height: 1.4; }

.doc-ruc { background: #1e3a5f; color: #fff; font-weight: bold; font-size: 11pt;
           text-align: center; padding: 6px 10px; border-radius: 3px 3px 0 0; }
.doc-tipo { background: #2563eb; color: #fff; font-weight: bold; font-size: 8.5pt;
            text-align: center; padding: 5px 10px; }
.doc-numero { background: #f8fafc; border: 1.5px solid #2563eb; font-size: 10pt;
              font-weight: bold; text-align: center; padding: 6px 10px;
              border-radius: 0 0 3px 3px; color: #1e3a5f; letter-spacing: 0.5px; }

/* ─── FECHA/INFO ROW ─── */
.info-row { display: table; width: 100%; border: 1px solid #c0d0e0; margin-bottom: 8px; border-radius: 3px; }
.info-cell { display: table-cell; border-right: 1px solid #c0d0e0; padding: 5px 8px; }
.info-cell:last-child { border-right: none; }
.info-label { font-size: 6.5pt; color: #555; text-transform: uppercase; font-weight: bold; }
.info-value { font-size: 8pt; font-weight: bold; color: #1a1a1a; margin-top: 1px; }

/* ─── CLIENTE ─── */
.cliente-box { border: 1px solid #c0d0e0; border-radius: 3px; padding: 7px 10px; margin-bottom: 8px; }
.cliente-grid { display: table; width: 100%; }
.cliente-row { display: table-row; }
.cliente-label { display: table-cell; font-size: 7.5pt; color: #555; font-weight: bold;
                 width: 110px; padding: 1.5px 0; }
.cliente-val   { display: table-cell; font-size: 7.5pt; color: #1a1a1a; padding: 1.5px 0; }

/* ─── ITEMS TABLE ─── */
table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 7.5pt; }
table.items thead tr { background: #1e3a5f; color: #fff; }
table.items thead th { padding: 5px 6px; text-align: left; font-weight: bold; font-size: 7pt; letter-spacing: 0.3px; }
table.items thead th.center { text-align: center; }
table.items thead th.right  { text-align: right; }
table.items tbody tr:nth-child(even) { background: #f4f7fb; }
table.items tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
table.items tbody td.center { text-align: center; }
table.items tbody td.right  { text-align: right; }
table.items tfoot td { padding: 3px 6px; font-size: 7.5pt; }

/* ─── TOTALES + SON ─── */
.bottom-section { display: table; width: 100%; margin-top: 4px; }
.bottom-left  { display: table-cell; width: 55%; vertical-align: top; padding-right: 12px; }
.bottom-right { display: table-cell; width: 45%; vertical-align: top; }

.son-box { border: 1px solid #c0d0e0; border-radius: 3px; padding: 5px 8px; margin-bottom: 8px; }
.son-label { font-size: 6.5pt; font-weight: bold; color: #555; text-transform: uppercase; }
.son-texto { font-size: 7.5pt; font-weight: bold; color: #1a1a1a; margin-top: 2px; }

.pago-info { border: 1px solid #c0d0e0; border-radius: 3px; padding: 6px 8px; }
.pago-titulo { font-size: 7pt; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 4px; }
.pago-linea { font-size: 7pt; color: #333; margin-bottom: 1.5px; }

/* ─── MÉTODOS DE PAGO ─── */
.pagos-sec { margin-top: 6px; }
.pagos-sec-titulo { font-size: 5.5pt; font-weight: bold; color: #777; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 4px; }
.pago-metodo-row { margin-bottom: 5px; }
.logo-pill { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; color: #fff; letter-spacing: 0.5px; }
.pago-metodo-dato { font-size: 6pt; color: #333; line-height: 1.55; margin-top: 2px; }
.pago-metodo-qr { width: 52px; height: 52px; float: right; margin-left: 4px; margin-bottom: 2px; }
.card-badge { display: inline-block; font-size: 5.5pt; font-weight: bold; padding: 1px 4px; border-radius: 2px; margin-right: 2px; color: #fff; vertical-align: middle; }

.totales-row { display: table; width: 100%; margin-bottom: 2px; }
.totales-label { display: table-cell; font-size: 8pt; color: #444; padding: 2px 0; }
.totales-val   { display: table-cell; font-size: 8pt; color: #1a1a1a; font-weight: bold;
                 text-align: right; padding: 2px 0; width: 90px; }
.totales-total .totales-label { font-size: 9.5pt; font-weight: bold; color: #1e3a5f; }
.totales-total .totales-val   { font-size: 9.5pt; font-weight: bold; color: #1e3a5f; }
.totales-divider { border-top: 1.5px solid #1e3a5f; margin: 3px 0; }

/* ─── FOOTER LEGAL ─── */
.footer { border-top: 1px solid #c0d0e0; margin-top: 10px; padding-top: 8px; display: table; width: 100%; }
.footer-text { display: table-cell; vertical-align: middle; width: 75%; font-size: 6.5pt; color: #555; line-height: 1.5; }
.footer-qr   { display: table-cell; vertical-align: middle; width: 25%; text-align: center; }
.qr-img { width: 72px; height: 72px; }
.badge-vendedor { font-size: 6.5pt; color: #777; margin-top: 4px; }
</style>
</head>
<body>

{{-- ─── CABECERA ─── --}}
<div class="header">
    <div class="header-left">
        @php
            $logoFile = $empresa->logo_pdf_path ?: $empresa->logo_path;
            $logoPath = $logoFile ? storage_path('app/public/' . $logoFile) : null;
            $logoSrc  = null;
            if ($logoPath && file_exists($logoPath)) {
                $ext     = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime    = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : "image/$ext";
                $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        @endphp
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
        @if($empresa->nombre_comercial)
            <div class="empresa-sub">{{ $empresa->nombre_comercial }}</div>
        @endif
        <div class="empresa-dir">
            {{ $empresa->direccion }}<br>
            {{ implode(', ', array_filter([$empresa->distrito, $empresa->provincia, $empresa->departamento])) }}<br>
            @if($empresa->telefono) Telf: {{ $empresa->telefono }}<br>@endif
            @if($empresa->email) Email: {{ $empresa->email }}@endif
        </div>
    </div>
    <div class="header-right">
        <div class="doc-ruc">R.U.C.: {{ $empresa->ruc }}</div>
        <div class="doc-tipo">
            @php
                $tiposNombre = [
                    'factura'   => 'Factura de Venta Electrónica',
                    'boleta'    => 'Boleta de Venta Electrónica',
                    'cotizacion'=> 'Nota de Entrega / Cotización',
                ];
            @endphp
            {{ $tiposNombre[$venta->tipo_comprobante] ?? ucfirst($venta->tipo_comprobante) }}
        </div>
        <div class="doc-numero">
            Nro. {{ $venta->numero_documento ?? $venta->codigo }}
        </div>
    </div>
</div>

{{-- ─── FILA INFO ─── --}}
<div class="info-row">
    <div class="info-cell" style="width:25%">
        <div class="info-label">Fecha de Emisión</div>
        <div class="info-value">{{ $venta->fecha->format('d-m-Y') }} {{ $venta->created_at->format('H:i A') }}</div>
    </div>
    <div class="info-cell" style="width:25%">
        <div class="info-label">Cond. Pago</div>
        <div class="info-value">
            @php
                $metodosLabel = ['efectivo'=>'Efectivo','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia','mixto'=>'Mixto'];
            @endphp
            {{ $metodosLabel[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago ?? '—') }}
        </div>
    </div>
    <div class="info-cell" style="width:25%">
        <div class="info-label">Moneda</div>
        <div class="info-value">Soles (PEN)</div>
    </div>
    <div class="info-cell" style="width:25%">
        <div class="info-label">Guía de Remisión N°</div>
        <div class="info-value">{{ $venta->guiaRemision?->numero_guia ?? '—' }}</div>
    </div>
</div>

{{-- ─── DATOS DEL CLIENTE ─── --}}
<div class="cliente-box">
    <div class="cliente-grid">
        <div class="cliente-row">
            <div class="cliente-label">{{ $venta->tipo_comprobante === 'factura' ? 'R.U.C.' : 'DNI/Doc.' }}:</div>
            <div class="cliente-val">{{ $venta->cliente?->numero_documento ?? '—' }}</div>
            <div class="cliente-label" style="padding-left:20px">Orden de Compra:</div>
            <div class="cliente-val">—</div>
        </div>
        <div class="cliente-row">
            <div class="cliente-label">Razón Social:</div>
            <div class="cliente-val">{{ $venta->cliente?->nombre ?? 'CLIENTE GENERAL' }}</div>
            <div class="cliente-label" style="padding-left:20px">Placa N°:</div>
            <div class="cliente-val">{{ $venta->guiaRemision?->placa_vehiculo ?? '—' }}</div>
        </div>
        <div class="cliente-row">
            <div class="cliente-label">Dirección:</div>
            <div class="cliente-val">{{ $venta->cliente?->direccion ?? '—' }}</div>
        </div>
    </div>
</div>

{{-- ─── TABLA DE ITEMS ─── --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:28px">ITEM</th>
            <th style="width:70px">CÓDIGO</th>
            <th>DESCRIPCIÓN</th>
            <th class="center" style="width:60px">UNID.</th>
            <th class="center" style="width:50px">CANT.</th>
            <th class="right"  style="width:65px">P.UNIT.</th>
            <th class="right"  style="width:65px">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @php $igvFactor = $venta->subtotal > 0 ? ($venta->total / $venta->subtotal) : 1.18; @endphp
        @foreach($venta->detalles as $i => $d)
        @php
            $precioFinal = $d->precio_unitario * $igvFactor;
            $totalFinal  = $d->subtotal * $igvFactor;
            $imeisLinea  = $venta->imeis->filter(fn($imei) =>
                $imei->producto_id == $d->producto_id &&
                ($d->variante_id ? $imei->variante_id == $d->variante_id : true)
            );
            if ($imeisLinea->isEmpty() && $d->imei) {
                $imeisLinea = collect([$d->imei]);
            }
        @endphp
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $d->producto?->codigo ?? '—' }}</td>
            <td>
                {{ $d->producto?->nombre }}
                @if($d->variante)
                    <br><span style="color:#555;font-size:7pt">{{ $d->variante->nombre_completo }} · {{ $d->variante->sku }}</span>
                @endif
                @foreach($imeisLinea as $imei)
                    <br><span style="color:#555;font-size:7pt">Serie: {{ $imei->codigo_imei }}</span>
                @endforeach
            </td>
            <td class="center">{{ $d->producto?->unidadMedida?->abreviatura ?? 'UND' }}</td>
            <td class="center">{{ $d->cantidad }}</td>
            <td class="right">S/ {{ number_format($precioFinal, 2) }}</td>
            <td class="right">S/ {{ number_format($totalFinal, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ─── SECCIÓN INFERIOR ─── --}}
<div class="bottom-section">
    <div class="bottom-left">
        {{-- Son --}}
        <div class="son-box">
            <div class="son-label">Son:</div>
            <div class="son-texto">{{ montoEnLetras($venta->total) }}</div>
        </div>

        {{-- Métodos de pago configurados --}}
        @if($pagos->count() > 0)
        <div class="pagos-sec">
            <div class="pagos-sec-titulo">Formas de pago aceptadas</div>
            @foreach($pagos as $tipo => $pg)
            @php
                [$pillColor, $pillLabel] = match($tipo) {
                    'yape'          => ['#7c3aed', 'YAPE'],
                    'plin'          => ['#16a34a', 'PLIN'],
                    'transferencia' => ['#1d4ed8', 'TRANSFERENCIA'],
                    'pos'           => ['#b91c1c', 'POS / TARJETA'],
                    default         => ['#374151', strtoupper($tipo)],
                };
                $qrFilePath = $pg->qr_imagen_path ? storage_path('app/public/' . $pg->qr_imagen_path) : null;
                $qrDataUri  = null;
                if ($qrFilePath && file_exists($qrFilePath)) {
                    $ext       = strtolower(pathinfo($qrFilePath, PATHINFO_EXTENSION));
                    $mime      = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : "image/$ext";
                    $qrDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($qrFilePath));
                }
            @endphp
            <div class="pago-metodo-row">
                @if($qrDataUri)
                    <img src="{{ $qrDataUri }}" class="pago-metodo-qr" alt="QR">
                @endif
                <span class="logo-pill" style="background:{{ $pillColor }}">{{ $pillLabel }}</span>
                @if($tipo === 'pos')
                    <span class="card-badge" style="background:#1a1f71">VISA</span>
                    <span class="card-badge" style="background:#eb001b">MC</span>
                    <span class="card-badge" style="background:#ff5f00">&#9679;</span>
                @endif
                <div class="pago-metodo-dato">
                    @if($pg->titular) {{ $pg->titular }}<br>@endif
                    @if($pg->numero)  {{ $pg->numero }}<br>@endif
                    @if($pg->banco)   {{ strtoupper($pg->banco) }}<br>@endif
                    @if($pg->cci)     CCI: {{ $pg->cci }}@endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bottom-right">
        {{-- Totales --}}
        <div class="totales-row">
            <div class="totales-label">Gravado</div>
            <div class="totales-val">S/ {{ number_format($venta->subtotal, 2) }}</div>
        </div>
        <div class="totales-row">
            <div class="totales-label">IGV (18%)</div>
            <div class="totales-val">S/ {{ number_format($venta->igv, 2) }}</div>
        </div>
        <div class="totales-row">
            <div class="totales-label">Descuento Total</div>
            <div class="totales-val">S/ 0.00</div>
        </div>
        <div class="totales-divider"></div>
        <div class="totales-row totales-total">
            <div class="totales-label">Total</div>
            <div class="totales-val">S/ {{ number_format($venta->total, 2) }}</div>
        </div>

        @if($venta->observaciones)
            <div style="margin-top:8px;font-size:7pt;color:#555;border-top:1px solid #ddd;padding-top:6px;">
                <strong>Observaciones:</strong> {{ $venta->observaciones }}
            </div>
        @endif
    </div>
</div>

{{-- ─── FOOTER LEGAL ─── --}}
<div class="footer">
    <div class="footer-text">
        Autorizado mediante la resolución N° 0640050002737/Sunat<br>
        Representación impresa de la {{ $tiposNombre[$venta->tipo_comprobante] ?? 'Comprobante' }}<br>
        @if($venta->vendedor)
            <span class="badge-vendedor">Atendido por: {{ $venta->vendedor->name }} (cod: {{ $venta->vendedor->id }})</span><br>
        @endif
        @if($venta->sucursal)
            <span class="badge-vendedor">Sucursal: {{ $venta->sucursal->nombre }}</span><br>
        @endif
        @if($empresa->web)
            Para consultar el comprobante visita <strong>{{ $empresa->web }}</strong>
        @endif
    </div>
    <div class="footer-qr">
        @php
            $qrData = route('ventas.show', $venta->id);
            $qrSvg  = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(90)->generate($qrData));
        @endphp
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" class="qr-img" alt="QR">
        <div style="font-size:5.5pt;color:#777;margin-top:2px">Consulta aquí</div>
    </div>
</div>

</body>
</html>
