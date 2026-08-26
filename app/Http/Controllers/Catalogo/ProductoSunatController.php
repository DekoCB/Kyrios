<?php
// app/Http/Controllers/Catalogo/ProductoSunatController.php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Catalogo\ProductoSunat;
use Illuminate\Http\Request;

class ProductoSunatController extends Controller
{
    /**
     * Búsqueda AJAX en el catálogo SUNAT (Código de Producto / UNSPSC).
     */
    public function buscar(Request $request)
    {
        $termino = trim((string) $request->get('q', ''));

        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        $resultados = ProductoSunat::buscar($termino)
            ->limit(20)
            ->get(['id', 'codigo', 'producto', 'clase', 'familia', 'segmento']);

        return response()->json($resultados);
    }
}
