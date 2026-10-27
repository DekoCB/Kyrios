<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class MasterPasswordController extends Controller
{
    /**
     * Contraseña maestra del sistema
     * IMPORTANTE: En producción, esto debería estar en el .env
     */
    private function getMasterPassword(): string
    {
        return (string) config('auth.master_password');
    }
    public function show(Request $request): View
    {
        $redirect = $request->query('redirect', 'register');
        
        return view('auth.master-password', compact('redirect'));
    }

    /**
     * Verificar la contraseña maestra
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'master_password' => ['required', 'string'],
            'redirect' => ['required', 'string'],
        ], [
            'master_password.required' => 'La contraseña maestra es obligatoria.',
        ]);

        // Sin contraseña maestra configurada, nadie puede pasar (no hay valor por defecto).
        $maestra = $this->getMasterPassword();
        if ($maestra === '' || !hash_equals($maestra, (string) $request->master_password)) {
            return back()->withErrors([
                'master_password' => 'La contraseña maestra es incorrecta.',
            ])->withInput();
        }

        // Guardar en sesión que la contraseña maestra fue verificada
        session(['master_password_verified' => true]);

        // Solo a rutas internas del sistema: nunca a una dirección externa.
        $destino = preg_match('/^[A-Za-z0-9\/_\-]+$/', $request->redirect) ? '/' . ltrim($request->redirect, '/') : '/register';

        return redirect($destino);
    }
}