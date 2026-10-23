<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Verificar que el usuario esté activo
        if (auth()->user()->estado !== 'activo') {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ]);
        }

        // Un usuario sin rol no puede entrar: no hay dashboard al que enviarlo
        $role = auth()->user()->role?->nombre;

        if (!$role) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Tu usuario no tiene un rol asignado. Contacta al administrador.',
            ]);
        }

        return match($role) {
            'Administrador' => redirect()->route('admin.dashboard'),
            'Vendedor'      => redirect()->route('vendedor.dashboard'),
            'Almacenero'    => redirect()->route('almacenero.dashboard'),
            'Tienda'        => redirect()->route('tienda.dashboard'),
            'Proveedor'     => redirect()->route('proveedor.dashboard'),
            'Técnico'       => redirect()->route('caja-chica.index'),
            default         => redirect()->route('dashboard'),
        };
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}