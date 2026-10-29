<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\ProductoVariante;
use App\Services\EscanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recepción rápida con lector de códigos (PDA): escanear → ubicar la variante → sumar cantidad al almacén.
 */
class RecepcionEscanerController extends Controller
{
    public function __construct(private EscanerService $escaner)
    {
        $this->middleware('role:Administración,Logística');
    }

    public function index()
    {
        $almacenes = Almacen::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'tipo']);

        return view('inventario.recepcion.index', ['almacenes' => $almacenes, 'modo' => 'ingreso', 'motivos' => collect()]);
    }

    /** Salida rápida: mismo flujo de escaneo, pero descuenta stock del almacén elegido. */
    public function salida()
    {
        $almacenes = Almacen::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'tipo']);
        $motivos = \App\Models\Catalogo\MotivoMovimiento::activos()->where('tipo', 'salida')->orderBy('id')->get(['id', 'nombre']);

        return view('inventario.recepcion.index', ['almacenes' => $almacenes, 'modo' => 'salida', 'motivos' => $motivos]);
    }

    /** POST almacen_id + motivo_id + lineas[{variante_id, cantidad}] → salidas de inventario */
    public function retirar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'almacen_id'            => 'required|exists:almacenes,id',
            'motivo_id'             => 'required|exists:motivos_movimiento,id',
            'observaciones'         => 'nullable|string|max:255',
            'lineas'                => 'required|array|min:1|max:300',
            'lineas.*.variante_id'  => 'required|exists:producto_variantes,id',
            'lineas.*.cantidad'     => 'required|integer|min:1|max:100000',
        ]);

        try {
            $n = $this->escaner->retirar((int) $datos['almacen_id'], $datos['lineas'], (int) auth()->id(), (int) $datos['motivo_id'], $datos['observaciones'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'movimientos' => $n]);
    }

    /** GET ?codigo= → variantes que coinciden con el código leído */
    public function buscar(Request $request): JsonResponse
    {
        $codigo = $this->escaner->normalizar((string) $request->query('codigo', ''));
        if ($codigo === '') {
            return response()->json(['codigo' => '', 'resultados' => []]);
        }

        return response()->json([
            'codigo'     => $codigo,
            'resultados' => $this->escaner->buscar($codigo)->map(fn ($v) => $this->conStock($this->escaner->formatear($v), $request))->values(),
        ]);
    }

    /** GET ?q= → búsqueda manual (nombre o código) para asociar un código desconocido a una variante */
    public function variantes(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $variantes = ProductoVariante::activas()
            ->where(function ($w) use ($q) {
                $w->where('codigo_kyrios', 'like', "%{$q}%")->orWhere('codigo_fabricante', 'like', "%{$q}%")
                    ->orWhere('codigo_excel', 'like', "%{$q}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$q}%")->orWhere('nombre_fabrica', 'like', "%{$q}%")
                        ->orWhere('nombre_kyrios', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"));
            })
            ->with(['producto:id,codigo,nombre,nombre_fabrica,nombre_kyrios,marca_id', 'producto.marca:id,nombre', 'imagenPrincipal'])
            ->limit(15)->get();

        return response()->json($variantes->map(fn ($v) => $this->conStock($this->escaner->formatear($v), $request))->values());
    }

    /** POST codigo + variante_id → guarda el código de barras en la variante */
    public function asociar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo'      => 'required|string|max:50',
            'variante_id' => 'required|exists:producto_variantes,id',
        ]);

        try {
            $this->escaner->asociar(ProductoVariante::findOrFail($datos['variante_id']), $datos['codigo']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** POST almacen_id + lineas[{variante_id, cantidad}] → ingresos de inventario */
    public function confirmar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'almacen_id'            => 'required|exists:almacenes,id',
            'documento_referencia'  => 'nullable|string|max:100',
            'lineas'                => 'required|array|min:1|max:300',
            'lineas.*.variante_id'  => 'required|exists:producto_variantes,id',
            'lineas.*.cantidad'     => 'required|integer|min:1|max:100000',
        ]);

        try {
            $n = $this->escaner->recibir((int) $datos['almacen_id'], $datos['lineas'], (int) auth()->id(), $datos['documento_referencia'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'movimientos' => $n]);
    }

    /** Con ?almacen_id= agrega el stock de la variante en ese almacén (para la salida rápida). */
    private function conStock(array $v, Request $request): array
    {
        if ($request->filled('almacen_id')) {
            $v['stock_almacen'] = \App\Models\StockAlmacen::cantidadEn((int) $v['producto_id'], (int) $request->query('almacen_id'), (int) $v['id']);
        }

        return $v;
    }
}
