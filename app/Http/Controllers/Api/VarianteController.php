<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Services\VarianteService;
use App\Models\Catalogo\Color;
use Illuminate\Http\Request;

class VarianteController extends Controller
{
    public function __construct(protected VarianteService $varianteService) {}

    /**
     * GET /api/variantes/producto/{producto}
     * Retorna todas las variantes activas de un producto base.
     */
    public function porProducto(Producto $producto): \Illuminate\Http\JsonResponse
    {
        $variantes = $producto->variantesActivas()
            ->with('color')
            ->orderBy('capacidad')
            ->orderBy('color_id')
            ->get()
            ->map(fn($v) => $this->varianteService->formatearVariante($v));

        return response()->json([
            'success'   => true,
            'producto'  => [
                'id'              => $producto->id,
                'nombre'          => \App\Support\EtiquetaProducto::nombre($producto),
                'tipo_inventario' => $producto->tipo_inventario,
                'codigo'          => $producto->codigo,
            ],
            'variantes' => $variantes,
            'total'     => $variantes->count(),
        ]);
    }

    /**
     * GET /api/variantes/{variante}
     * Detalle de una sola variante.
     */
    public function show(ProductoVariante $variante): \Illuminate\Http\JsonResponse
    {
        $variante->load('color', 'producto.marca', 'producto.modelo');

        return response()->json([
            'success'  => true,
            'variante' => $this->varianteService->formatearVariante($variante),
            'producto' => [
                'id'              => $variante->producto->id,
                'nombre'          => \App\Support\EtiquetaProducto::nombre($variante->producto),
                'tipo_inventario' => $variante->producto->tipo_inventario,
                'marca'           => $variante->producto->marca?->nombre,
                'modelo'          => $variante->producto->modelo?->nombre,
            ],
        ]);
    }

    /**
     * POST /api/variantes
     * Crear nueva variante para un producto base.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'producto_id'  => 'required|exists:productos,id',
            'color_id'     => 'nullable|exists:colores,id',
            'capacidad'    => 'nullable|string|max:50',
            'sobreprecio'  => 'nullable|numeric|min:0',
            'stock_inicial'=> 'nullable|integer|min:0',
        ]);

        $producto = Producto::findOrFail($validated['producto_id']);

        $variante = $this->varianteService->obtenerOCrearVariante(
            $producto,
            $validated['color_id'] ?? null,
            $validated['capacidad'] ?? null,
            (float)($validated['sobreprecio'] ?? 0)
        );

        if (!empty($validated['stock_inicial']) && $validated['stock_inicial'] > 0) {
            $variante->incrementarStock($validated['stock_inicial']);
        }

        $variante->load('color');

        return response()->json([
            'success'  => true,
            'message'  => 'Variante creada correctamente',
            'variante' => $this->varianteService->formatearVariante($variante),
        ], 201);
    }

    /**
     * PUT /api/variantes/{variante}
     * Actualizar datos de una variante.
     */
    public function update(Request $request, ProductoVariante $variante): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'color_id'    => 'nullable|exists:colores,id',
            'capacidad'   => 'nullable|string|max:50',
            'sobreprecio' => 'nullable|numeric|min:0',
            'stock_minimo'=> 'nullable|integer|min:0',
            'estado'      => 'nullable|in:activo,inactivo',
        ]);

        $variante->update(array_filter($validated, fn($v) => $v !== null));
        $variante->load('color');

        return response()->json([
            'success'  => true,
            'variante' => $this->varianteService->formatearVariante($variante),
        ]);
    }

    /**
     * DELETE /api/variantes/{variante}
     * Desactivar variante.
     */
    public function destroy(ProductoVariante $variante): \Illuminate\Http\JsonResponse
    {
        $this->varianteService->desactivarVariante($variante);

        return response()->json([
            'success' => true,
            'message' => 'Variante desactivada correctamente',
        ]);
    }

    /**
     * GET /api/variantes/stock/{variante}?almacen_id=X
     * Stock de la variante, opcionalmente por almacén.
     */
    public function stock(ProductoVariante $variante, Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'     => true,
            'stock_total' => $variante->stock_actual,
        ]);
    }

}
