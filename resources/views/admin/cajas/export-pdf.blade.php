<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 15mm 12mm; size: A4 landscape; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 14px; font-weight: bold; margin-bottom: 3px; }
        .subtitle { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background-color: #44413A;
            color: white;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        thead th.num { text-align: right; }
        tbody tr:nth-child(even) { background-color: #f9fafb; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 8px; }
        tbody td.num { text-align: right; }
        tfoot td { padding: 5px 6px; font-weight: bold; font-size: 8px; border-top: 2px solid #44413A; }
        tfoot td.num { text-align: right; }
        .badge-open   { color: #16a34a; font-weight: bold; }
        .badge-closed { color: #6b7280; }
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }
    </style>
</head>
<body>
    <h1>Reporte de Cajas</h1>
    <p class="subtitle">Generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Cajero</th>
                <th>Sucursal</th>
                <th>Fecha</th>
                <th>Apertura</th>
                <th>Cierre</th>
                <th class="num">M. Inicial</th>
                <th class="num">Ventas</th>
                <th class="num">Ingresos</th>
                <th class="num">Egresos</th>
                <th class="num">M. Final</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cajas as $c)
                <tr>
                    <td>{{ $c->id }}</td>
                    <td>{{ $c->usuario?->name ?? '—' }}</td>
                    <td>{{ $c->sucursal?->nombre ?? '—' }}</td>
                    <td>{{ $c->fecha }}</td>
                    <td>{{ $c->fecha_apertura?->format('H:i') ?? '—' }}</td>
                    <td>{{ $c->fecha_cierre?->format('H:i') ?? '—' }}</td>
                    <td class="num">{{ number_format($c->monto_inicial, 2) }}</td>
                    <td class="num">{{ number_format($c->total_ventas, 2) }}</td>
                    <td class="num">{{ number_format($c->total_ingresos, 2) }}</td>
                    <td class="num">{{ number_format($c->total_egresos, 2) }}</td>
                    <td class="num">{{ number_format($c->monto_final, 2) }}</td>
                    <td class="num {{ ($c->diferencia_cierre ?? 0) < 0 ? 'neg' : (($c->diferencia_cierre ?? 0) > 0 ? 'pos' : '') }}">
                        {{ $c->diferencia_cierre !== null ? number_format($c->diferencia_cierre, 2) : '—' }}
                    </td>
                    <td class="{{ $c->estado === 'abierta' ? 'badge-open' : 'badge-closed' }}">
                        {{ ucfirst($c->estado) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="13" style="text-align:center;padding:10px;color:#9ca3af;">Sin registros</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total ({{ $cajas->count() }} registros)</td>
                <td class="num">{{ number_format($cajas->sum('monto_inicial'), 2) }}</td>
                <td class="num">{{ number_format($cajas->sum('total_ventas'), 2) }}</td>
                <td class="num">{{ number_format($cajas->sum('total_ingresos'), 2) }}</td>
                <td class="num">{{ number_format($cajas->sum('total_egresos'), 2) }}</td>
                <td class="num">{{ number_format($cajas->sum('monto_final'), 2) }}</td>
                <td class="num {{ $cajas->sum('diferencia_cierre') < 0 ? 'neg' : '' }}">
                    {{ number_format($cajas->sum('diferencia_cierre'), 2) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
