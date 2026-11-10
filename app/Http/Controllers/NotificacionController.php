<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        return view('notificaciones.index', [
            'notificaciones' => $usuario->notifications()->latest()->paginate(20),
            'sinLeer'        => $usuario->unreadNotifications()->count(),
        ]);
    }

    /** Marca el aviso como leído y lleva a la pantalla que lo originó. */
    public function abrir(Request $request, string $id)
    {
        $aviso = $request->user()->notifications()->findOrFail($id);
        $aviso->markAsRead();

        $url = $aviso->data['url'] ?? null;
        // Solo rutas internas: el aviso nunca debe sacar al usuario del sistema
        $interna = $url && str_starts_with($url, '/') && !str_starts_with($url, '//');

        return redirect($interna ? $url : route('notificaciones.index'));
    }

    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notificaciones.index')->with('success', 'Todos los avisos quedaron como leídos.');
    }
}
