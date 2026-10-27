<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Almacen;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['role', 'almacen.sucursal'])
            ->orderBy('name')
            ->paginate(15);

        $roles = Role::orderBy('nombre')->get();
        $sucursales = Sucursal::where('estado', 'activo')
            ->with(['almacenes' => fn($q) => $q->where('estado', 'activo')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return view('users.index', compact('users', 'roles', 'sucursales'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::orderBy('nombre')->get();
        $sucursales = Sucursal::where('estado', 'activo')
            ->with(['almacenes' => fn($q) => $q->where('estado', 'activo')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return view('users.create', compact('roles', 'sucursales'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'dni' => 'nullable|string|digits:8|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'estado' => 'required|in:activo,inactivo',
            'telefono' => 'nullable|string|max:15',
            'direccion' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $validated['password'] = Hash::make($validated['password']);
            
            User::create($validated);

            DB::commit();

            return redirect()
                ->route('users.index')
                ->with('success', 'Usuario creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al crear el usuario: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load('role', 'almacen');
        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load('almacen.sucursal');
        $roles = Role::orderBy('nombre')->get();
        $sucursales = Sucursal::where('estado', 'activo')
            ->with(['almacenes' => fn($q) => $q->where('estado', 'activo')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return view('users.edit', compact('user', 'roles', 'sucursales'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'dni' => 'nullable|string|digits:8|unique:users,dni,' . $user->id,
            'role_id' => 'required|exists:roles,id',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'estado' => 'required|in:activo,inactivo',
            'telefono' => 'nullable|string|max:15',
            'direccion' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Un administrador no puede quitarse a sí mismo el acceso (se quedaría fuera del sistema).
        if ($user->id === auth()->id()) {
            $rolAdmin = Role::where('nombre', 'Administrador')->value('id');
            if ($validated['estado'] !== 'activo' || (int) $validated['role_id'] !== (int) $rolAdmin) {
                return back()->withInput()->with('error', 'No puedes desactivarte ni cambiar tu propio rol.');
            }
        }

        try {
            DB::beginTransaction();

            if (!empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $user->update($validated);

            DB::commit();

            return redirect()
                ->route('users.index')
                ->with('success', 'Usuario actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar el usuario: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Evitar que se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }

        try {
            DB::beginTransaction();
            
            $user->delete();
            
            DB::commit();

            return redirect()
                ->route('users.index')
                ->with('success', 'Usuario eliminado exitosamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Tiene cajas, ventas u otros registros a su nombre: se conserva el historial.
            return back()->with('error', 'No se puede eliminar: el usuario tiene registros a su nombre. Márquelo como inactivo desde «Editar».');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo eliminar el usuario.');
        }
    }
}