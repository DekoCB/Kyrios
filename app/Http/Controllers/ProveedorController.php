<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Services\SunatService;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::withCount('compras', 'pedidos')
            ->orderBy('razon_social')
            ->get();

        $canCreate = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canEdit = in_array(auth()->user()->role->nombre, ['Administración', 'Logística']);
        $canDelete = auth()->user()->role->nombre === 'Administración';

        return view('proveedores.index', compact('proveedores', 'canCreate', 'canEdit', 'canDelete'));
    }

    public function create()
    {
        return view('proveedores.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruc' => 'required|string|size:11|unique:proveedores,ruc',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto_nombre' => 'nullable|string|max:255',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'ruc.required' => 'El RUC es obligatorio',
            'ruc.size' => 'El RUC debe tener 11 dígitos',
            'ruc.unique' => 'Este RUC ya está registrado',
            'razon_social.required' => 'La razón social es obligatoria',
        ]);

        Proveedor::create($validated);

        return redirect()
            ->route('proveedores.index')
            ->with('success', 'Proveedor registrado exitosamente');
    }

    public function show(Proveedor $proveedor)
    {
        $proveedor->load(['compras' => function ($q) {
            $q->latest()->take(10);
        }, 'pedidos' => function ($q) {
            $q->latest()->take(10);
        }]);

        return view('proveedores.show', compact('proveedor'));
    }

    public function edit(Proveedor $proveedor)
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $validated = $request->validate([
            'ruc' => 'required|string|size:11|unique:proveedores,ruc,' . $proveedor->id,
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto_nombre' => 'nullable|string|max:255',
            'estado' => 'required|in:activo,inactivo',
        ]);

        $proveedor->update($validated);

        return redirect()
            ->route('proveedores.index')
            ->with('success', 'Proveedor actualizado exitosamente');
    }

    public function destroy(Proveedor $proveedor)
    {
        try {
            $proveedor->delete();
            return redirect()
                ->route('proveedores.index')
                ->with('success', 'Proveedor eliminado exitosamente');
        } catch (\Exception $e) {
            return redirect()
                ->route('proveedores.index')
                ->with('error', 'No se puede eliminar el proveedor porque tiene compras o pedidos asociados');
        }
    }

    public function consultarSunat(Request $request)
    {
        $ruc = $request->input('ruc');
        // Sin RUC (o con algo que no es texto) se responde con el mensaje de validación del servicio, no con un error 500
        $result = app(SunatService::class)->consultarRuc(is_scalar($ruc) ? trim((string) $ruc) : '');
        return response()->json($result);
    }
}
