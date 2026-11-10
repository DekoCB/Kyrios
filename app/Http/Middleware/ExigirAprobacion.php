<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\AprobacionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las acciones que cambian el inventario las aprueba Administración.
 * Si quien las envía es de otro rol, no se ejecutan: queda una solicitud pendiente.
 *
 * Uso: ->middleware('aprobacion:accion')
 *      ->middleware('aprobacion:accion,campo,valor1,valor2')  solo si el campo trae alguno de esos valores
 */
class ExigirAprobacion
{
    public function handle(Request $request, Closure $next, string $accion, ?string $campo = null, string ...$valores): Response
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->role?->nombre === Role::ADMINISTRACION) {
            return $next($request);
        }

        if ($campo !== null && !in_array((string) $request->input($campo), $valores, true)) {
            return $next($request);
        }

        return app(AprobacionService::class)->solicitar($request, $accion);
    }
}
