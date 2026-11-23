<?php

namespace App\Http\Controllers;

use App\Models\CatalogoLibro;
use App\Services\LibroCatalogoService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Enlace público de un catálogo: se abre sin iniciar sesión mientras el enlace esté activo y no haya vencido. */
class CatalogoPublicoController extends Controller
{
    public function show(Request $request, string $token, LibroCatalogoService $servicio)
    {
        $catalogo = CatalogoLibro::where('token', $token)->first();
        if (!$catalogo || !$catalogo->enlaceDisponible()) {
            return response()->view('catalogos.no-disponible', [], 404);
        }

        // Una persona que recarga la página no cuenta dos veces dentro de 10 minutos
        $visitante = substr(sha1($request->ip() . '|' . $request->userAgent()), 0, 40);
        if (!$catalogo->vistas()->where('visitante', $visitante)->where('created_at', '>', now()->subMinutes(10))->exists()) {
            $catalogo->vistas()->create(['visitante' => $visitante]);
        }

        return view('catalogos.publico', [
            'catalogo' => $catalogo,
            'libro' => $servicio->construir($catalogo),
            'modo' => $catalogo->precio_enlace,
            'qr' => (string) QrCode::format('svg')->size(300)->margin(0)->errorCorrection('M')->generate($catalogo->url_publica),
        ]);
    }
}
