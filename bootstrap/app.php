<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\VerifyMasterPassword;
use App\Http\Middleware\CheckSistemaBloqueado;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->web(append: [
            CheckSistemaBloqueado::class,
        ]);
        $middleware->alias([
            'role' => CheckRole::class,
            'master' => VerifyMasterPassword::class,
            'aprobacion' => \App\Http\Middleware\ExigirAprobacion::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.');
        });
        // Si la base de datos está saturada un instante, una página que se reintenta sola en lugar de un error 500
        $exceptions->render(function (\Illuminate\Database\QueryException|\PDOException $e, \Illuminate\Http\Request $request) {
            if (!\App\Database\ReintentaMySqlConnector::esPasajero($e)) {
                return null;
            }
            \Illuminate\Support\Facades\Log::warning('Base de datos saturada: ' . $e->getMessage());

            return $request->expectsJson()
                ? response()->json(['message' => 'El sistema está muy ocupado. Intenta de nuevo en unos segundos.'], 503, ['Retry-After' => 3])
                : response()->view('errors.base-ocupada', [], 503, ['Retry-After' => 3]);
        });
    })->create();