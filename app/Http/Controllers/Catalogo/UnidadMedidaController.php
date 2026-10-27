<?php
// app/Http/Controllers/Catalogo/UnidadMedidaController.php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Categoria;
use Illuminate\Http\Request;

class UnidadMedidaController extends Controller
{
    public function index(Request $request)
    {
        $query = UnidadMedida::orderBy('nombre');

        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('abreviatura', 'like', '%' . $request->buscar . '%');
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $unidades = $query->paginate(15)->withQueryString();
        return view('catalogo.unidades.index', compact('unidades'));
    }

    public function create()
    {
        // Verificar si el modelo Categoria existe
        if (!class_exists('App\Models\Categoria')) {
            $categorias = collect([]);
        } else {
            try {
                $categorias = Categoria::where('estado', 'activo')
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']);
            } catch (\Exception $e) {
                \Log::error('Error en create de unidades: ' . $e->getMessage());
                $categorias = collect([]);
            }
        }
        
        return view('catalogo.unidades.create', compact('categorias'));
    }
    public function storeRapida(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:unidades_medida',
            'abreviatura' => 'required|string|max:10|unique:unidades_medida',
            'tipo' => 'required|in:unidad,masa,volumen,longitud,empaque'
        ]);

        // El formulario rápido usa «masa» y «empaque»; la columna `categoria` admite peso y otros.
        $categoria = ['masa' => 'peso', 'empaque' => 'otros'][$request->tipo] ?? $request->tipo;

        $unidad = UnidadMedida::create([
            'nombre' => $request->nombre,
            'abreviatura' => $request->abreviatura,
            'categoria' => $categoria,
            'estado' => 'activo'
        ]);
        
        return response()->json([
            'success' => true,
            'id' => $unidad->id,
            'nombre' => $unidad->nombre,
            'abreviatura' => $unidad->abreviatura
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:unidades_medida',
            'abreviatura' => 'required|string|max:20|unique:unidades_medida',
            'categoria' => 'required|in:unidad,peso,volumen,longitud,otros',
            'categoria_inventario_id' => 'nullable|exists:categorias,id',
            'descripcion' => 'nullable|string',
            'permite_decimales' => 'boolean',
            'estado' => 'required|in:activo,inactivo'
        ]);

        UnidadMedida::create($validated);

        return redirect()
            ->route('catalogo.unidades.index')
            ->with('success', 'Unidad de medida creada exitosamente');
    }

    public function edit(UnidadMedida $unidade)
    {
        $categorias = Categoria::where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
            
        return view('catalogo.unidades.edit', compact('unidade', 'categorias'));
    }

    public function update(Request $request, UnidadMedida $unidade)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:unidades_medida,nombre,' . $unidade->id,
            'abreviatura' => 'required|string|max:20|unique:unidades_medida,abreviatura,' . $unidade->id,
            'categoria' => 'required|in:unidad,peso,volumen,longitud,otros',
            'categoria_inventario_id' => 'nullable|exists:categorias,id',
            'descripcion' => 'nullable|string',
            'permite_decimales' => 'boolean',
            'estado' => 'required|in:activo,inactivo'
        ]);

        $unidade->update($validated);

        return redirect()
            ->route('catalogo.unidades.index')
            ->with('success', 'Unidad de medida actualizada exitosamente');
    }

    public function destroy(UnidadMedida $unidade)
    {
        $unidade->delete();

        return redirect()
            ->route('catalogo.unidades.index')
            ->with('success', 'Unidad de medida eliminada exitosamente');
    }
}