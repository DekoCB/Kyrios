<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoriaController extends Controller
{
    /**
     * Constructor - Definir permisos por rol
     */
    public function __construct()
    {
        // Solo Administración y Logística pueden crear/editar
        $this->middleware('role:Administración,Logística')->except(['index', 'show']);
        
        // Solo Admin puede eliminar
        $this->middleware('role:Administración')->only(['destroy']);
    }

    /**
     * Mostrar listado de categorías
     */
    public function index()
    {
        $categorias = Categoria::withCount('productos')
            ->orderBy('nombre')
            ->get();
        
        // Verificar si el usuario puede crear categorías
        $canCreate = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canEdit = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canDelete = auth()->user()->role->nombre === 'Administración';
        
        return view('inventario.categorias.index', compact('categorias', 'canCreate', 'canEdit', 'canDelete'));
    }

    /**
     * Mostrar formulario de crear categoría
     */
    public function create()
    {
        return view('inventario.categorias.create');
    }

    /**
     * Guardar nueva categoría
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre',
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio',
            'nombre.unique' => 'Ya existe una categoría con este nombre',
            'imagen.image' => 'El archivo debe ser una imagen',
            'imagen.mimes' => 'La imagen debe ser JPG, JPEG, PNG o WEBP',
            'imagen.max' => 'La imagen no debe superar los 2MB',
        ]);

        // Generar código automático
        $validated['codigo'] = Categoria::generarCodigo();

        // Subir imagen si existe
        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('categorias', 'public');
        }

        Categoria::create($validated);

        return redirect()
            ->route('inventario.categorias.index')
            ->with('success', 'Categoría creada exitosamente');
    }

    /**
     * Mostrar una categoría específica
     */
    public function show(Categoria $categoria)
    {
        $categoria->load(['productos' => function($query) {
            $query->activos()->orderBy('nombre');
        }]);
        
        return view('inventario.categorias.show', compact('categoria'));
    }

    /**
     * Mostrar formulario de editar categoría
     */
    public function edit(Categoria $categoria)
    {
        return view('inventario.categorias.edit', compact('categoria'));
    }

    /**
     * Actualizar categoría
     */
    public function update(Request $request, Categoria $categoria)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id,
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio',
            'nombre.unique' => 'Ya existe una categoría con este nombre',
            'imagen.image' => 'El archivo debe ser una imagen',
            'imagen.mimes' => 'La imagen debe ser JPG, JPEG, PNG o WEBP',
            'imagen.max' => 'La imagen no debe superar los 2MB',
        ]);

        // Subir nueva imagen si existe
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior si existe
            if ($categoria->imagen) {
                Storage::disk('public')->delete($categoria->imagen);
            }
            $validated['imagen'] = $request->file('imagen')->store('categorias', 'public');
        }

        $categoria->update($validated);

        return redirect()
            ->route('inventario.categorias.index')
            ->with('success', 'Categoría actualizada exitosamente');
    }

    /**
     * Eliminar categoría
     */
    public function destroy(Categoria $categoria)
    {
        try {
            $imagen = $categoria->imagen;

            $categoria->delete();

            // La imagen se borra solo si la categoría se pudo eliminar
            if ($imagen) {
                Storage::disk('public')->delete($imagen);
            }
            
            return redirect()
                ->route('inventario.categorias.index')
                ->with('success', 'Categoría eliminada exitosamente');
                
        } catch (\Exception $e) {
            return redirect()
                ->route('inventario.categorias.index')
                ->with('error', 'No se puede eliminar la categoría porque tiene productos asociados');
        }
    }

    /**
     * Cambiar estado de categoría (activar/inactivar)
     */
    public function toggleEstado(Categoria $categoria)
    {
        $categoria->update([
            'estado' => $categoria->estado === 'activo' ? 'inactivo' : 'activo'
        ]);

        return redirect()
            ->route('inventario.categorias.index')
            ->with('success', 'Estado de categoría actualizado');
    }
}