<?php
// app/Http/Controllers/Api/ImeiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Imei;
use App\Models\Producto;
use Illuminate\Http\Request;

class ImeiController extends Controller
{
    /**
     * Verificar disponibilidad de un IMEI
     */
    public function verificarDisponibilidad(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'producto_id' => 'nullable|exists:productos,id'
        ]);

        $codigo = $request->codigo;
        $almacenId = $request->almacen_id ?? auth()->user()->almacen_id;

        // Validar formato (15 dígitos)
        if (!preg_match('/^\d{15}$/', $codigo)) {
            return response()->json([
                'disponible' => false,
                'mensaje' => 'El IMEI debe tener 15 dígitos'
            ]);
        }

        $query = Imei::where('codigo_imei', $codigo);

        // Si se especifica producto, verificar que coincida
        if ($request->producto_id) {
            $query->where('producto_id', $request->producto_id);
        }

        $imei = $query->first();

        if (!$imei) {
            return response()->json([
                'disponible' => false,
                'mensaje' => 'IMEI no encontrado en el sistema'
            ]);
        }

        // Verificar que esté en el almacén correcto y disponible
        $disponible = $imei->almacen_id == $almacenId 
            && $imei->estado_imei === 'en_stock';

        return response()->json([
            'disponible' => $disponible,
            'imei' => $disponible ? [
                'id' => $imei->id,
                'codigo' => $imei->codigo_imei,
                'producto_id' => $imei->producto_id,
                'producto' => $imei->producto->nombre,
                'color' => $imei->color?->nombre
            ] : null,
            'mensaje' => $disponible 
                ? 'IMEI disponible' 
                : 'IMEI no disponible (estado: ' . ($imei->estado_imei ?? 'desconocido') . ')'
        ]);
    }

    /**
     * Obtener IMEIs disponibles para un producto
     */
    public function disponibles(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'cantidad' => 'nullable|integer|min:1'
        ]);

        $productoId = $request->producto_id;
        $almacenId = $request->almacen_id ?? auth()->user()->almacen_id;
        $cantidad = $request->get('cantidad', 10);

        $imeis = Imei::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('estado_imei', 'en_stock')
            ->limit($cantidad)
            ->get(['id', 'codigo_imei', 'serie', 'color_id'])
            ->map(function($imei) {
                return [
                    'id' => $imei->id,
                    'codigo' => $imei->codigo_imei,
                    'serie' => $imei->serie,
                    'color' => $imei->color?->nombre
                ];
            });

        return response()->json([
            'total' => $imeis->count(),
            'imeis' => $imeis
        ]);
    }

    /**
     * Registrar múltiples IMEIs (para importación)
     */
    public function importar(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'compra_id' => 'nullable|exists:compras,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'imeis' => 'required|array|min:1',
            'imeis.*.codigo' => 'required|string|size:15|distinct',
            'imeis.*.serie' => 'nullable|string|max:50'
        ]);

        $producto = Producto::findOrFail($request->producto_id);
        
        if ($producto->tipo_inventario !== 'serie') {
            return response()->json([
                'success' => false,
                'message' => 'El producto no es de tipo serie'
            ], 422);
        }

        $importados = [];
        $duplicados = [];

        \DB::transaction(function () use ($request, $producto, &$importados, &$duplicados) {
            foreach ($request->imeis as $imeiData) {
                // Verificar si ya existe
                $existe = Imei::where('codigo_imei', $imeiData['codigo'])->exists();

                if ($existe) {
                    $duplicados[] = $imeiData['codigo'];
                    continue;
                }

                $imei = Imei::create([
                    'codigo_imei' => $imeiData['codigo'],
                    'serie' => $imeiData['serie'] ?? null,
                    'producto_id' => $producto->id,
                    'almacen_id' => $request->almacen_id,
                    'compra_id' => $request->compra_id,
                    'estado_imei' => 'en_stock',
                    'user_id' => auth()->id()
                ]);

                $importados[] = $imei->codigo_imei;
            }

            if (!empty($importados)) {
                // Recontar desde la fuente real (imeis) en vez de incrementar: mantiene
                // producto.stock_actual exacto (mismo patrón usado en
                // CompraController::storeBulkImeis). Este endpoint no recibe variante_id,
                // así que no hay stock por variante que sincronizar aquí.
                $totalProducto = Imei::where('producto_id', $producto->id)
                    ->where('estado_imei', 'en_stock')
                    ->count();
                Producto::where('id', $producto->id)->update(['stock_actual' => $totalProducto]);
            }
        });

        return response()->json([
            'success' => true,
            'importados' => $importados,
            'duplicados' => $duplicados,
            'total_importados' => count($importados),
            'total_duplicados' => count($duplicados)
        ]);
    }
}