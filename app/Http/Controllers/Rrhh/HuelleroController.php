<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Services\HuelleroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Asistencia desde el huellero: importar el archivo del reloj o recibir las marcaciones desde un puente en la red del cliente. */
class HuelleroController extends Controller
{
    private const DISCO = 'local';

    public function __construct(private HuelleroService $huellero)
    {
    }

    public function index(Request $request)
    {
        $empleados = Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->orderBy('nombres')->get();

        return view('rrhh.asistencia.huellero', [
            'conCodigo'  => $empleados->filter(fn ($e) => filled($e->codigo_huellero)),
            'sinCodigo'  => $empleados->filter(fn ($e) => blank($e->codigo_huellero)),
            'analisis'   => null,
            'token'      => null,
            'apiActiva'  => filled(config('kyrios.rrhh.huellero_token')),
            'minutosSalida' => HuelleroService::minutosParaSalida(),
        ]);
    }

    /** Lee el archivo y muestra qué se registraría; no guarda asistencia hasta confirmar. */
    public function previsualizar(Request $request)
    {
        $request->validate(['archivo' => 'required|file|max:20480'], ['archivo.required' => 'Elija el archivo que exportó el reloj.', 'archivo.max' => 'El archivo pesa más de 20 MB.']);

        $archivo = $request->file('archivo');
        if (!in_array(strtolower($archivo->getClientOriginalExtension()), ['dat', 'txt', 'csv', 'log', ''], true)) {
            return back()->with('error', 'El archivo debe ser el que exporta el reloj por USB (.dat, .txt o .csv).');
        }

        $lectura = $this->huellero->leerArchivo((string) file_get_contents($archivo->getRealPath()));
        if (!$lectura['marcaciones']) {
            return back()->with('error', 'No se encontraron marcaciones en el archivo (se esperan líneas con el número de la persona, la fecha y la hora). ' . $lectura['ignoradas'] . ' línea(s) no se entendieron.');
        }

        $token = (string) Str::uuid();
        Storage::disk(self::DISCO)->put("rrhh/huellero/{$token}.json", json_encode(['usuario' => $request->user()->id, 'marcaciones' => $lectura['marcaciones']]));

        $empleados = Empleado::where('estado', '!=', 'cesado')->orderBy('apellidos')->orderBy('nombres')->get();

        return view('rrhh.asistencia.huellero', [
            'conCodigo'  => $empleados->filter(fn ($e) => filled($e->codigo_huellero)),
            'sinCodigo'  => $empleados->filter(fn ($e) => blank($e->codigo_huellero)),
            'analisis'   => $this->huellero->analizar($lectura['marcaciones']) + ['ignoradas' => $lectura['ignoradas'], 'nombre' => $archivo->getClientOriginalName()],
            'token'      => $token,
            'apiActiva'  => filled(config('kyrios.rrhh.huellero_token')),
            'minutosSalida' => HuelleroService::minutosParaSalida(),
        ]);
    }

    public function confirmar(Request $request)
    {
        $datos = $request->validate(['token' => 'required|uuid']);
        $ruta = "rrhh/huellero/{$datos['token']}.json";
        $disco = Storage::disk(self::DISCO);

        $guardado = $disco->exists($ruta) ? json_decode($disco->get($ruta), true) : null;
        if (!$guardado || ($guardado['usuario'] ?? null) !== $request->user()->id) {
            return redirect()->route('rrhh.asistencia.huellero')->with('error', 'La importación venció o ya se hizo: suba el archivo de nuevo.');
        }

        $r = $this->huellero->importar($guardado['marcaciones'], $request->user()->id);
        $disco->delete($ruta);

        return redirect()->route('rrhh.asistencia.huellero')->with('success', $this->resumen($r));
    }

    /** Recibe marcaciones de un puente en la red del cliente. Autenticado con un token propio (no con la sesión). */
    public function api(Request $request): JsonResponse
    {
        $esperado = (string) config('kyrios.rrhh.huellero_token');
        if ($esperado === '') {
            return response()->json(['ok' => false, 'mensaje' => 'La recepción desde el huellero no está activada.'], 404);
        }
        $enviado = $request->bearerToken() ?: (string) $request->header('X-Huellero-Token');
        if (!hash_equals($esperado, $enviado)) {
            return response()->json(['ok' => false, 'mensaje' => 'Token no válido.'], 401);
        }

        $datos = $request->validate([
            'marcaciones'              => 'required|array|min:1|max:5000',
            'marcaciones.*.pin'        => 'required|string|max:20',
            'marcaciones.*.fecha_hora' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $r = $this->huellero->importar($datos['marcaciones']);

        return response()->json(['ok' => true] + $r);
    }

    private function resumen(array $r): string
    {
        $partes = ["{$r['nuevos']} día(s) nuevos", "{$r['actualizados']} actualizados", "{$r['sin_cambios']} sin cambios"];
        if ($r['omitidos']) {
            $partes[] = "{$r['omitidos']} omitidos por tener permiso, vacaciones o licencia";
        }
        if ($r['desconocidos']) {
            $partes[] = count($r['desconocidos']) . ' número(s) del reloj no pertenecen a ningún empleado (' . implode(', ', array_keys($r['desconocidos'])) . ')';
        }

        return 'Asistencia importada del huellero: ' . implode(', ', $partes) . '.';
    }
}
