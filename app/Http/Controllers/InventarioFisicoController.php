<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\ConteoDetalle;
use App\Models\ConteoInventario;
use App\Models\Producto;
use App\Models\StockAlmacen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class InventarioFisicoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = ConteoInventario::with(['almacen', 'usuario'])
            ->withCount('detalles')
            ->withCount(['detalles as contados_count' => fn($q) => $q->whereNotNull('stock_fisico')]);

        if ($user->role->nombre !== 'Administrador' && $user->almacen_id) {
            $query->where('almacen_id', $user->almacen_id);
        }

        $conteos = $query->latest()->paginate(20);
        return view('inventario.conteo.index', compact('conteos'));
    }

    public function create()
    {
        $almacenes = Almacen::where('estado', 'activo')->orderBy('nombre')->get();
        return view('inventario.conteo.create', compact('almacenes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'     => 'required|string|max:100',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        $conteo = ConteoInventario::create([
            'nombre'     => $request->nombre,
            'almacen_id' => $request->almacen_id,
            'user_id'    => auth()->id(),
            'estado'     => 'activo',
        ]);

        $this->poblarDetalles($conteo);

        return redirect()->route('inventario-fisico.show', $conteo)
            ->with('success', 'Conteo creado. ¡Puedes comenzar a ingresar el stock físico!');
    }

    public function show(Request $request, ConteoInventario $conteo)
    {
        $categorias = Categoria::orderBy('nombre')->get();

        $query = $conteo->detalles()
            ->with(['producto.categoria', 'variante'])
            ->join('productos', 'conteo_detalles.producto_id', '=', 'productos.id')
            ->select('conteo_detalles.*');

        if ($request->filled('categoria_id')) {
            $query->where('productos.categoria_id', $request->categoria_id);
        }

        if ($request->filled('buscar')) {
            $buscar = '%' . addcslashes((string) $request->buscar, '%_\\') . '%';
            $query->where(function ($q) use ($buscar) {
                $q->where('productos.nombre', 'like', $buscar)
                  ->orWhere('productos.nombre_fabrica', 'like', $buscar)
                  ->orWhere('productos.nombre_kyrios', 'like', $buscar)
                  ->orWhere('productos.codigo', 'like', $buscar)
                  ->orWhereHas('variante', fn ($v) => $v->where('codigo_kyrios', 'like', $buscar)
                      ->orWhere('codigo_fabricante', 'like', $buscar)->orWhere('codigo_excel', 'like', $buscar));
            });
        }

        if ($request->boolean('solo_faltantes')) {
            $query->whereNotNull('conteo_detalles.stock_fisico')
                  ->whereRaw('conteo_detalles.stock_fisico < conteo_detalles.stock_sistema');
        }

        $detalles = $query->orderBy('productos.nombre')->paginate(50)->withQueryString();

        // KPI stats
        $stats = $conteo->detalles()
            ->selectRaw('
                COUNT(*) as total_lineas,
                COUNT(stock_fisico) as contados,
                SUM(CASE WHEN stock_fisico IS NOT NULL AND stock_fisico < stock_sistema THEN (stock_sistema - stock_fisico) ELSE 0 END) as total_faltante_unidades
            ')
            ->first();

        // Join productos for valor calculations
        $valorStats = $conteo->detalles()
            ->join('productos', 'conteo_detalles.producto_id', '=', 'productos.id')
            ->whereNotNull('conteo_detalles.stock_fisico')
            ->whereRaw('conteo_detalles.stock_fisico < conteo_detalles.stock_sistema')
            ->selectRaw('
                SUM((conteo_detalles.stock_sistema - conteo_detalles.stock_fisico) * COALESCE(productos.costo_promedio, 0)) as valor_compra,
                SUM((conteo_detalles.stock_sistema - conteo_detalles.stock_fisico) * COALESCE(productos.ultimo_costo_compra, 0)) as valor_venta
            ')
            ->first();

        return view('inventario.conteo.show', compact(
            'conteo', 'detalles', 'categorias', 'stats', 'valorStats'
        ));
    }

    public function updateDetalle(Request $request, ConteoInventario $conteo, ConteoDetalle $detalle): JsonResponse
    {
        if ($detalle->conteo_id !== $conteo->id) {
            return response()->json(['ok' => false, 'message' => 'No autorizado'], 403);
        }

        $request->validate([
            'stock_fisico'  => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string|max:255',
        ]);

        $data = ['observaciones' => $request->observaciones];
        if ($request->filled('stock_fisico') || $request->input('stock_fisico') === '0') {
            $data['stock_fisico'] = (int) $request->stock_fisico;
            $data['contado_at']   = now();
        }

        $detalle->update($data);

        $diferencia = $detalle->diferencia;
        $faltante   = $detalle->faltante;

        return response()->json([
            'ok'           => true,
            'diferencia'   => $diferencia,
            'faltante'     => $faltante,
            'valor_faltante' => number_format($detalle->valor_faltante, 2),
            'contado_at'   => $detalle->contado_at?->format('d/m H:i'),
        ]);
    }

    public function reiniciar(ConteoInventario $conteo)
    {
        $conteo->detalles()->update([
            'stock_fisico'  => null,
            'contado_at'    => null,
            'observaciones' => null,
        ]);

        return back()->with('success', 'Conteo reiniciado correctamente.');
    }

    public function exportPdf(ConteoInventario $conteo)
    {
        $conteo->load(['almacen', 'usuario']);
        $detalles = $conteo->detalles()
            ->with(['producto.categoria', 'variante'])
            ->join('productos', 'conteo_detalles.producto_id', '=', 'productos.id')
            ->select('conteo_detalles.*')
            ->orderBy('productos.nombre')
            ->get();

        $pdf = Pdf::loadView('pdf.conteo-inventario', compact('conteo', 'detalles'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('conteo-' . $conteo->id . '-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(ConteoInventario $conteo)
    {
        $conteo->load(['almacen', 'usuario']);
        $detalles = $conteo->detalles()
            ->with(['producto.categoria', 'variante'])
            ->join('productos', 'conteo_detalles.producto_id', '=', 'productos.id')
            ->select('conteo_detalles.*')
            ->orderBy('productos.nombre')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conteo Inventario');

        // ── Título principal ──────────────────────────────────────
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'CONTEO DE INVENTARIO FÍSICO');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Info del conteo ───────────────────────────────────────
        $sheet->setCellValue('A2', 'Conteo:');
        $sheet->setCellValue('B2', $conteo->nombre);
        $sheet->setCellValue('D2', 'Almacén:');
        $sheet->setCellValue('E2', $conteo->almacen?->nombre ?? '—');
        $sheet->setCellValue('G2', 'Generado:');
        $sheet->setCellValue('H2', now()->format('d/m/Y H:i'));
        $sheet->setCellValue('J2', 'Estado:');
        $sheet->setCellValue('K2', strtoupper($conteo->estado));
        foreach (['A2','D2','G2','J2'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $sheet->getRowDimension(3)->setRowHeight(6); // espaciado

        // ── Cabeceras ─────────────────────────────────────────────
        $headers = [
            'A' => '#',
            'B' => 'Código',
            'C' => 'Producto',
            'D' => 'Variante',
            'E' => 'Categoría',
            'F' => 'Stock Mín.',
            'G' => 'Stock Sistema',
            'H' => 'Stock Físico',
            'I' => 'Diferencia',
            'J' => 'P. Costo (S/)',
            'K' => 'Valor Faltante (S/)',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}4", $label);
        }

        $headerRange = 'A4:K4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // ── Datos ────────────────────────────────────────────────
        $row = 5;
        foreach ($detalles as $i => $d) {
            $diferencia  = $d->stock_fisico !== null ? ($d->stock_fisico - $d->stock_sistema) : null;
            $valorFaltante = $d->stock_fisico !== null && $diferencia < 0
                ? abs($diferencia) * ($d->producto->costo_promedio ?? 0)
                : 0;

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", \App\Support\EtiquetaProducto::codigos($d->producto, $d->variante) ?: ($d->producto->codigo ?? ''));
            $sheet->setCellValue("C{$row}", $d->producto ? \App\Support\EtiquetaProducto::nombre($d->producto) : '');
            $sheet->setCellValue("D{$row}", $d->variante?->nombre_completo ?? '—');
            $sheet->setCellValue("E{$row}", $d->producto->categoria?->nombre ?? '—');
            $sheet->setCellValue("F{$row}", $d->producto->stock_minimo ?? 0);
            $sheet->setCellValue("G{$row}", $d->stock_sistema);
            $sheet->setCellValue("H{$row}", $d->stock_fisico ?? '');
            $sheet->setCellValue("I{$row}", $diferencia ?? '');
            $sheet->setCellValue("J{$row}", $d->producto->costo_promedio ?? 0);
            $sheet->setCellValue("K{$row}", $valorFaltante);

            // Zebra stripe
            $bgColor = $i % 2 === 0 ? 'F8FAFC' : 'EFF6FF';
            $sheet->getStyle("A{$row}:K{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bgColor);

            // Resaltar filas con faltante
            if ($diferencia !== null && $diferencia < 0) {
                $sheet->getStyle("I{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
                ]);
                $sheet->getStyle("K{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
                ]);
            }

            // Bordea toda la fila
            $sheet->getStyle("A{$row}:K{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB('E2E8F0');

            // Formato numérico
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            $row++;
        }

        // ── Fila de totales ────────────────────────────────────────
        $lastData = $row - 1;
        $sheet->setCellValue("G{$row}", "=SUM(G5:G{$lastData})");
        $sheet->setCellValue("H{$row}", "=SUM(H5:H{$lastData})");
        $sheet->setCellValue("K{$row}", "=SUM(K5:K{$lastData})");
        $sheet->setCellValue("A{$row}", 'TOTALES');
        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        ]);
        $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("G{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Anchos de columna ─────────────────────────────────────
        $widths = ['A'=>6,'B'=>14,'C'=>34,'D'=>20,'E'=>18,'F'=>10,'G'=>13,'H'=>13,'I'=>11,'J'=>14,'K'=>18];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Alineación numérica
        foreach (['F','G','H','I','J','K'] as $col) {
            $sheet->getStyle("{$col}5:{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("A5:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── Congelar cabecera ─────────────────────────────────────
        $sheet->freezePane('A5');

        // ── Respuesta HTTP ────────────────────────────────────────
        $filename = 'conteo-' . $conteo->id . '-' . now()->format('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function poblarDetalles(ConteoInventario $conteo): void
    {
        $almacenId = $conteo->almacen_id;
        $rows = [];

        // Productos por cantidad: una fila de conteo por cada fila de stock_almacen,
        // es decir, por (producto, variante, almacén). Cada variante se cuenta por separado.
        $stocksCantidad = StockAlmacen::where('almacen_id', $almacenId)
            ->with('producto:id,tipo_inventario')
            ->get();

        foreach ($stocksCantidad as $sa) {
            if (!$sa->producto) {
                continue;
            }

            $rows[] = [
                'conteo_id'     => $conteo->id,
                'producto_id'   => $sa->producto_id,
                'variante_id'   => $sa->variante_id,
                'stock_sistema' => max(0, (int) $sa->cantidad),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // Bulk insert in chunks to avoid memory issues
        foreach (array_chunk($rows, 200) as $chunk) {
            ConteoDetalle::insert($chunk);
        }
    }
}
