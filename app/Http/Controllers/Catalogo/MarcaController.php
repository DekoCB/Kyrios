<?php
// app/Http/Controllers/Catalogo/MarcaController.php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Models\Catalogo\Marca;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarcaController extends Controller
{
    public function index(Request $request)
    {
        $query = Marca::withCount('modelos')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $query->where('nombre', 'like', '%' . $request->buscar . '%');
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $marcas = $query->paginate(15)->withQueryString();
        return view('catalogo.marcas.index', compact('marcas'));
    }

    public function create()
    {
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        return view('catalogo.marcas.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:marcas',
            'descripcion' => 'nullable|string',
            'sitio_web'   => 'nullable|url|max:255',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado'      => 'required|in:activo,inactivo',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('marcas', 'public');
        }

        Marca::create($validated);

        return redirect()
            ->route('catalogo.marcas.index')
            ->with('success', 'Marca creada exitosamente');
    }

    public function edit(Marca $marca)
    {
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        $marca->load('categorias');
        return view('catalogo.marcas.edit', compact('marca', 'categorias'));
    }

    public function update(Request $request, Marca $marca)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:marcas,nombre,' . $marca->id,
            'descripcion' => 'nullable|string',
            'sitio_web'   => 'nullable|url|max:255',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado'      => 'required|in:activo,inactivo',
        ]);

        if ($request->hasFile('logo')) {
            if ($marca->logo) {
                Storage::disk('public')->delete($marca->logo);
            }
            $validated['logo'] = $request->file('logo')->store('marcas', 'public');
        }

        $marca->update($validated);

        return redirect()
            ->route('catalogo.marcas.index')
            ->with('success', 'Marca actualizada exitosamente');
    }

    public function destroy(Marca $marca)
    {
        if ($marca->modelos()->exists()) {
            return back()->with('error', 'No se puede eliminar porque tiene modelos asociados');
        }

        if ($marca->logo) {
            Storage::disk('public')->delete($marca->logo);
        }

        $marca->delete();

        return redirect()
            ->route('catalogo.marcas.index')
            ->with('success', 'Marca eliminada exitosamente');
    }

    /**
     * Creación rápida de marca desde el formulario de productos (AJAX).
     */
    public function storeRapida(Request $request)
        {
            try {
                $request->validate([
                    'nombre' => 'required|string|max:100|unique:marcas',
                    'categoria_id' => 'required|exists:categorias,id'
                ]);
                
                $marca = Marca::create([
                    'nombre' => $request->nombre,
                    'estado' => 'activo'
                ]);
                
                // Asociar con la categoría
                $marca->categorias()->attach($request->categoria_id);
                
                return response()->json([
                    'success' => true,
                    'id' => $marca->id,
                    'nombre' => $marca->nombre
                ]);
                
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Un dato inválido es un 422 con sus mensajes, no un error del servidor
                throw $e;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

    /**
     * API: devuelve las marcas activas que pertenecen a una categoría.
     * Usado por el selector encadenado Categoría → Marca → Modelo en productos.
     */
    public function getMarcasPorCategoria($categoriaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);

        $marcas = $categoria->marcas()
            ->where('marcas.estado', 'activo')
            ->orderBy('marcas.nombre')
            ->get(['marcas.id', 'marcas.nombre']);

        // Si la categoría no tiene marcas vinculadas, devolver todas las activas
        if ($marcas->isEmpty()) {
            $marcas = Marca::where('estado', 'activo')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        return response()->json($marcas);
    }
}
