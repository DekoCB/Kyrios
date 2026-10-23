<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conteo de Inventario</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        .header { background: #44413A; color: #fff; padding: 10px 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 13px; font-weight: bold; }
        .header p { font-size: 8px; opacity: .8; margin-top: 2px; }
        .meta { font-size: 8px; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead th { background: #44413A; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; text-transform: uppercase; }
        thead th.right { text-align: right; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 8px; }
        tbody td.right { text-align: right; }
        .badge-red { background: #dc2626; color: #fff; padding: 1px 5px; border-radius: 10px; font-size: 7px; font-weight: bold; }
        .badge-green { background: #d1fae5; color: #065f46; padding: 1px 5px; border-radius: 10px; font-size: 7px; }
        tfoot td { background: #f3f4f6; font-weight: bold; padding: 5px 6px; border-top: 2px solid #9ca3af; }
        tfoot td.right { text-align: right; }
        .kpis { display: flex; gap: 8px; margin-bottom: 8px; }
        .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px 8px; text-align: center; }
        .kpi-val { font-size: 12px; font-weight: bold; }
        .kpi-lbl { font-size: 7px; color: #6b7280; margin-top: 1px; }
        .blue { color: #44413A; }
        .red { color: #dc2626; }
        .amber { color: #d97706; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Conteo Físico de Inventario</h1>
            <p>{{ $conteo->nombre }} · Almacén: {{ $conteo->almacen->nombre }}</p>
        </div>
        <div class="meta">
            <p>Creado por: {{ $conteo->usuario->name }}</p>
            <p>Fecha: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @php
        $totalFaltante = $detalles->sum(fn($d) => $d->faltante);
        $valorCompra   = $detalles->sum(fn($d) => $d->valor_faltante);
        $contados      = $detalles->where('stock_fisico', '!=', null)->count();
    @endphp

    <div class="kpis">
        <div class="kpi"><div class="kpi-val blue">{{ $contados }}</div><div class="kpi-lbl">Contados</div></div>
        <div class="kpi"><div class="kpi-val red">{{ number_format($totalFaltante) }}</div><div class="kpi-lbl">Unidades faltantes</div></div>
        <div class="kpi"><div class="kpi-val amber">S/ {{ number_format($valorCompra, 2) }}</div><div class="kpi-lbl">Valor a precio compra</div></div>
        <div class="kpi"><div class="kpi-val">{{ $detalles->count() }}</div><div class="kpi-lbl">Total líneas</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th class="right">Mín.</th>
                <th class="right">S. Sistema</th>
                <th class="right">S. Físico</th>
                <th class="right">Diferencia</th>
                <th class="right">P. Compra</th>
                <th class="right">Val. Faltante</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $d)
                <tr>
                    <td>{{ $d->producto->codigo }}</td>
                    <td>
                        {{ $d->producto->nombre }}
                        @if($d->variante) <small>({{ $d->variante->nombre_completo }})</small> @endif
                    </td>
                    <td>{{ $d->producto->categoria?->nombre ?? '—' }}</td>
                    <td class="right">{{ $d->producto->stock_minimo ?? '—' }}</td>
                    <td class="right">{{ $d->stock_sistema }}</td>
                    <td class="right">{{ $d->stock_fisico ?? '—' }}</td>
                    <td class="right">
                        @if($d->diferencia !== null)
                            @if($d->diferencia < 0)
                                <span class="badge-red">▼ {{ abs($d->diferencia) }}</span>
                            @elseif($d->diferencia > 0)
                                <span class="badge-green">+{{ $d->diferencia }}</span>
                            @else
                                0
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="right">S/ {{ number_format($d->producto->costo_promedio ?? 0, 2) }}</td>
                    <td class="right">
                        @if($d->faltante > 0)
                            S/ {{ number_format($d->valor_faltante, 2) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="right">TOTAL FALTANTE:</td>
                <td class="right red">{{ number_format($totalFaltante) }} und</td>
                <td></td>
                <td class="right red">S/ {{ number_format($valorCompra, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
