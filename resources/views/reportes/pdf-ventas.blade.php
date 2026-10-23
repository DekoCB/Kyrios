<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; background: #fff; }

        /* Header */
        .header { background: #44413A; color: #fff; padding: 14px 20px; margin-bottom: 16px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .empresa-nombre { font-size: 16px; font-weight: bold; }
        .reporte-titulo { font-size: 14px; font-weight: bold; text-align: right; }
        .reporte-periodo { font-size: 10px; color: #bfdbfe; text-align: right; margin-top: 2px; }
        .header-sub { font-size: 9px; color: #bfdbfe; margin-top: 4px; }

        /* KPI cards */
        .kpi-grid { display: flex; gap: 10px; margin: 0 20px 14px; }
        .kpi-card { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .kpi-label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        .kpi-value { font-size: 16px; font-weight: bold; color: #111827; margin-top: 2px; }
        .kpi-sub   { font-size: 8px; color: #9ca3af; margin-top: 2px; }
        .kpi-card.blue  { border-left: 3px solid #44413A; }
        .kpi-card.orange { border-left: 3px solid #f97316; }
        .kpi-card.green { border-left: 3px solid #10b981; }
        .kpi-card.purple { border-left: 3px solid #8b5cf6; }

        /* Section title */
        .section-title { font-size: 10px; font-weight: bold; color: #374151; background: #f3f4f6;
            padding: 6px 20px; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; margin-bottom: 0; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #44413A; color: #fff; padding: 6px 8px; font-size: 8px;
            text-transform: uppercase; letter-spacing: 0.04em; text-align: right; }
        thead th:first-child { text-align: left; }
        thead th:nth-child(2) { text-align: left; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 9px; text-align: right; }
        tbody td:first-child { text-align: left; font-weight: 500; }
        tbody td:nth-child(2) { text-align: left; color: #6b7280; }
        tfoot td { background: #44413A; color: #fff; padding: 6px 8px; font-weight: bold; font-size: 9px; text-align: right; }
        tfoot td:first-child { text-align: left; }

        /* Margen badge */
        .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; }
        .badge-green  { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-red    { background: #fee2e2; color: #991b1b; }

        /* Footer */
        .footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 16px; padding: 10px 20px;
            border-top: 1px solid #e5e7eb; }

        .page-content { margin: 0 20px; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="empresa-nombre">{{ $empresa?->nombre_display ?? 'EMPRESA' }}</div>
                @if($empresa?->ruc)
                    <div class="header-sub">RUC: {{ $empresa->ruc }}</div>
                @endif
            </div>
            <div>
                <div class="reporte-titulo">REPORTE DE VENTAS — MÁRGENES DE GANANCIA</div>
                <div class="reporte-periodo">Período: {{ $label }} · {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</div>
                <div class="reporte-periodo">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-label">Ventas Totales</div>
            <div class="kpi-value">S/ {{ number_format($kpis['total_ventas'], 2) }}</div>
            <div class="kpi-sub">{{ $kpis['num_ventas'] }} ventas · {{ $kpis['unidades_vendidas'] }} unidades</div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-label">Costo Total</div>
            <div class="kpi-value">S/ {{ number_format($kpis['total_costo'], 2) }}</div>
            <div class="kpi-sub">Costo promedio de productos</div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-label">Ganancia Bruta</div>
            <div class="kpi-value">S/ {{ number_format($kpis['ganancia_bruta'], 2) }}</div>
            <div class="kpi-sub">Ingresos − Costos</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-label">Margen Promedio</div>
            <div class="kpi-value">{{ number_format($kpis['margen_promedio'], 1) }}%</div>
            <div class="kpi-sub">(Ganancia ÷ Venta) × 100</div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="section-title">Detalle por Producto</div>
    <div class="page-content">
        <table>
            <thead>
                <tr>
                    <th style="text-align:left">Producto</th>
                    <th style="text-align:left">Categoría</th>
                    <th>Cant.</th>
                    <th>P.Venta Prom.</th>
                    <th>Costo Unit.</th>
                    <th>Gan. Unit.</th>
                    <th>Total Vendido</th>
                    <th>Total Ganancia</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tablaProductos as $row)
                    <tr>
                        <td>
                            {{ $row->nombre }}<br>
                            <span style="font-size:8px;color:#9ca3af;font-family:monospace">{{ $row->codigo }}</span>
                        </td>
                        <td>{{ $row->categoria }}</td>
                        <td>{{ number_format($row->cantidad_vendida) }}</td>
                        <td>S/ {{ number_format($row->precio_promedio, 2) }}</td>
                        <td>S/ {{ number_format($row->costo_unitario, 2) }}</td>
                        <td style="{{ $row->ganancia_unitaria >= 0 ? 'color:#065f46' : 'color:#991b1b' }}">
                            S/ {{ number_format($row->ganancia_unitaria, 2) }}
                        </td>
                        <td>S/ {{ number_format($row->total_vendido, 2) }}</td>
                        <td style="{{ $row->total_ganancia >= 0 ? 'color:#065f46;font-weight:bold' : 'color:#991b1b;font-weight:bold' }}">
                            S/ {{ number_format($row->total_ganancia, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:20px;color:#9ca3af">
                            No hay ventas registradas para este período
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($tablaProductos->isNotEmpty())
                @php
                    $totVendido  = $tablaProductos->sum('total_vendido');
                    $totGanancia = $tablaProductos->sum('total_ganancia');
                    $totCantidad = $tablaProductos->sum('cantidad_vendida');
                @endphp
                <tfoot>
                    <tr>
                        <td colspan="2">TOTALES</td>
                        <td>{{ number_format($totCantidad) }}</td>
                        <td></td><td></td><td></td>
                        <td>S/ {{ number_format($totVendido, 2) }}</td>
                        <td>S/ {{ number_format($totGanancia, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="footer">
        Reporte generado automáticamente por el Sistema ERP ·
        {{ $empresa?->nombre_display ?? '' }} ·
        {{ now()->format('d/m/Y H:i:s') }}
    </div>

</body>
</html>
