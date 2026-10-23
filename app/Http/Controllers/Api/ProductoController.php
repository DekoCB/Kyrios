<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoCodigoBarras;
use App\Models\StockAlmacen;
use App\Services\PrecioRotativoService;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    protected $precioService;

    public function __construct(PrecioRotativoService $precioService)
    {
        $this->precioService = $precioService;
    }

    /**
     * Buscar producto por código de barras
     */
    public function buscarPorCodigo(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'almacen_id' => 'nullable|exists:almacenes,id'
        ]);

        $codigo = $request->codigo;
        $almacenId = $request->almacen_id ?? auth()->user()->almacen_id;

        // Buscar en códigos de barras
        $codigoBarras = ProductoCodigoBarras::where('codigo', $codigo)
            ->with('producto')
            ->first();

        if ($codigoBarras) {
            $producto = $codigoBarras->producto;
        } else {
            // Buscar por código interno
            $producto = Producto::where('codigo_interno', $codigo)
                ->orWhere('sku', $codigo)
                ->orWhere('codigo_barras', $codigo)
                ->first();
        }

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        // Obtener stock del almacén
        $stock = 0;
        if ($almacenId) {
            $stock = StockAlmacen::totalEn($producto->id, $almacenId);
        }

        // Obtener precio vigente
        $precioInfo = $this->precioService->obtenerPrecioVigente($producto);

        return response()->json([
            'success' => true,
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo_interno' => $producto->codigo_interno,
                'precio_venta' => $precioInfo['precio'],
                'incluye_igv' => $precioInfo['incluye_igv'],
                'stock_actual' => $stock,
                'tipo_inventario' => $producto->tipo_inventario,
                'imagen' => $producto->imagen ? asset('storage/' . $producto->imagen) : null
            ]
        ]);
    }
}