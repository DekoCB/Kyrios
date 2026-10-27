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

        $almacenId = $request->almacen_id ?? auth()->user()->almacen_id;

        // Misma búsqueda que usa el escáner: código Kyrios, del fabricante, del Excel o de barras
        $variante = app(\App\Services\EscanerService::class)->buscar($request->codigo)->first();

        if (!$variante) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $producto = $variante->producto;

        // Stock de la variante en el almacén
        $stock = 0;
        if ($almacenId) {
            $stock = StockAlmacen::cantidadEn($producto->id, $almacenId, $variante->id);
        }

        // Obtener precio vigente
        $precioInfo = $this->precioService->obtenerPrecioVigente($producto);

        return response()->json([
            'success' => true,
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'variante_id' => $variante->id,
                'codigo_interno' => $variante->codigo_kyrios ?? $variante->sku,
                'precio_venta' => $precioInfo['precio'],
                'incluye_igv' => $precioInfo['incluye_igv'],
                'stock_actual' => $stock,
                'tipo_inventario' => $producto->tipo_inventario,
                'imagen' => $producto->imagen ? asset('storage/' . $producto->imagen) : null
            ]
        ]);
    }
}