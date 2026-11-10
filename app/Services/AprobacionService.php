<?php

namespace App\Services;

use App\Models\Aprobacion;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AvisoSistema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aprobaciones del administrador.
 *
 * Una solicitud guarda la petición tal como la envió el usuario (ruta, ids y datos) y no toca el inventario.
 * Al aprobarla se ejecuta la misma acción real, a nombre del solicitante, de modo que valida y registra
 * exactamente igual que si la hubiera hecho él. Si la ejecución falla, la solicitud sigue pendiente con el motivo.
 */
class AprobacionService
{
    /** Datos del formulario que no forman parte de lo que se aprueba. */
    private const IGNORAR = ['_token', '_method'];

    /** Tabla y columna con la que se muestra cada referencia a otro registro. */
    private const REFERENCIAS = [
        'almacen_id' => ['almacenes', 'nombre'], 'almacen_destino_id' => ['almacenes', 'nombre'],
        'proveedor_id' => ['proveedores', 'razon_social'], 'categoria_id' => ['categorias', 'nombre'],
        'marca_id' => ['marcas', 'nombre'], 'modelo_id' => ['modelos', 'nombre'], 'color_id' => ['colores', 'nombre'],
        'producto_id' => ['productos', 'nombre'], 'variante_id' => ['producto_variantes', 'codigo_kyrios'],
        'motivo_movimiento_id' => ['motivos_movimiento', 'nombre'],
    ];

    public function __construct(private AlertaService $alertas)
    {
    }

    // ── Solicitar ────────────────────────────────────────────────────────────

    public function solicitar(Request $request, string $accion): Response
    {
        $ruta = $request->route();
        $parametros = collect($ruta->parameters())
            ->map(fn ($v) => $v instanceof Model ? $v->getKey() : $v)
            ->all();
        $datos = Arr::except($request->input(), self::IGNORAR);

        [$titulo, $detalle] = $this->describir($accion, $ruta->getName(), $parametros, $datos);

        $aprobacion = Aprobacion::create([
            'accion'         => $accion,
            'ruta'           => $ruta->getName(),
            'parametros'     => $parametros,
            'datos'          => $datos,
            'archivos'       => $this->guardarArchivos($request),
            'titulo'         => Str::limit($titulo, 200, '…'),
            'resumen'        => $detalle[0] ?? null,
            'detalle'        => $detalle,
            'solicitante_id' => $request->user()->id,
        ]);

        $this->alertas->avisar([Role::ADMINISTRACION], new AvisoSistema(
            "aprobacion:{$aprobacion->id}",
            'Aprobación pendiente',
            "{$request->user()->name} solicita: {$aprobacion->titulo}",
            route('aprobaciones.index', [], false),
            'alerta'
        ));

        $mensaje = 'Quedó pendiente de aprobación del administrador: todavía no se aplicó ningún cambio. Puedes seguirla en «Mis solicitudes».';

        if ($request->expectsJson() || $request->isJson()) {
            return new JsonResponse(['ok' => true, 'success' => false, 'pendiente' => true, 'mensaje' => $mensaje, 'message' => $mensaje], 202);
        }

        return redirect()->route('aprobaciones.mias')->with('success', $mensaje);
    }

    // ── Resolver ─────────────────────────────────────────────────────────────

    /** @throws RuntimeException con el motivo si la acción no pudo aplicarse */
    public function aprobar(Aprobacion $aprobacion, User $por): void
    {
        try {
            DB::transaction(function () use ($aprobacion, $por) {
                $actual = Aprobacion::whereKey($aprobacion->id)->lockForUpdate()->first();
                if (!$actual || $actual->estado !== 'pendiente') {
                    throw new RuntimeException('Esta solicitud ya fue resuelta.');
                }

                $this->ejecutar($actual);

                $actual->update(['estado' => 'aprobada', 'resuelta_por' => $por->id, 'resuelta_at' => now(), 'ultimo_error' => null]);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'Esta solicitud ya fue resuelta.') {
                $aprobacion->forceFill(['ultimo_error' => Str::limit($e->getMessage(), 500)])->save();
            }
            throw $e;
        }

        $this->limpiarArchivos($aprobacion);
        $this->avisarResultado($aprobacion->fresh(), 'aprobada');
    }

    public function rechazar(Aprobacion $aprobacion, User $por, string $motivo): void
    {
        $filas = Aprobacion::whereKey($aprobacion->id)->where('estado', 'pendiente')->update([
            'estado' => 'rechazada', 'resuelta_por' => $por->id, 'resuelta_at' => now(), 'motivo_rechazo' => $motivo, 'updated_at' => now(),
        ]);
        if ($filas === 0) {
            throw new RuntimeException('Esta solicitud ya fue resuelta.');
        }

        $this->limpiarArchivos($aprobacion);
        $this->avisarResultado($aprobacion->fresh(), 'rechazada');
    }

    private function avisarResultado(Aprobacion $a, string $resultado): void
    {
        $aprobada = $resultado === 'aprobada';
        $a->solicitante?->notify(new AvisoSistema(
            "aprobacion-resuelta:{$a->id}",
            $aprobada ? 'Solicitud aprobada' : 'Solicitud rechazada',
            $aprobada ? "Se aprobó y aplicó: {$a->titulo}" : "Se rechazó: {$a->titulo}. Motivo: {$a->motivo_rechazo}",
            route('aprobaciones.mias', [], false),
            $aprobada ? 'info' : 'alerta'
        ));
    }

    // ── Ejecución ────────────────────────────────────────────────────────────

    /** Ejecuta la acción guardada como si la hiciera el solicitante. */
    private function ejecutar(Aprobacion $a): void
    {
        $base = Route::getRoutes()->getByName($a->ruta);
        if (!$base) {
            throw new RuntimeException("La acción «{$a->ruta}» ya no existe en el sistema.");
        }

        $ruta = clone $base;
        $solicitante = $a->solicitante ?: throw new RuntimeException('El usuario que hizo la solicitud ya no existe.');
        $metodo = collect($ruta->methods())->first(fn ($m) => !in_array($m, ['GET', 'HEAD'], true)) ?? 'POST';

        $peticion = Request::create(route($a->ruta, $a->parametros ?? [], false), $metodo, $a->datos ?? [], [], $this->reconstruirArchivos($a));
        $peticion->setRouteResolver(fn () => $ruta);
        $peticion->setUserResolver(fn () => $solicitante);
        $ruta->bind($peticion);

        $peticionActual = app('request');
        $usuarioActual = Auth::user();
        $router = app('router');

        // Lo que la acción deje en la sesión (éxito o error) no debe mezclarse con la pantalla del administrador
        $claves = ['error', 'success', 'info', 'warning', '_old_input'];
        session()->forget($claves);

        app()->instance('request', $peticion);
        Auth::setUser($solicitante);

        try {
            $router->substituteBindings($ruta);
            $router->substituteImplicitBindings($ruta);
            $respuesta = $ruta->run();
            $error = $this->errorDe($respuesta);
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->implode(' ');
        } catch (\Throwable $e) {
            $error = $e->getMessage() ?: class_basename($e);
        } finally {
            app()->instance('request', $peticionActual);
            if ($usuarioActual) {
                Auth::setUser($usuarioActual);
            }
            session()->forget($claves);
        }

        if (!empty($error)) {
            throw new RuntimeException($error);
        }
    }

    /** Mensaje de error si la respuesta de la acción indica que no se hizo. */
    private function errorDe(mixed $respuesta): ?string
    {
        if ($respuesta instanceof JsonResponse) {
            $d = $respuesta->getData(true);
            if ($respuesta->getStatusCode() >= 400 || ($d['ok'] ?? true) === false || ($d['success'] ?? true) === false) {
                return $d['mensaje'] ?? $d['message'] ?? 'La acción no pudo completarse.';
            }

            return null;
        }

        if ($respuesta instanceof RedirectResponse) {
            $err = session('error');
            if ($err) {
                return is_string($err) ? $err : 'La acción no pudo completarse.';
            }
        }

        return null;
    }

    // ── Archivos ─────────────────────────────────────────────────────────────

    private function guardarArchivos(Request $request): ?array
    {
        $guardados = [];
        $carpeta = 'aprobaciones/' . Str::uuid();

        foreach (Arr::dot($request->allFiles()) as $campo => $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                $guardados[] = [
                    'campo'  => $campo,
                    'ruta'   => Storage::disk('local')->putFile($carpeta, $archivo),
                    'nombre' => $archivo->getClientOriginalName(),
                    'mime'   => $archivo->getClientMimeType(),
                ];
            }
        }

        return $guardados ?: null;
    }

    private function reconstruirArchivos(Aprobacion $a): array
    {
        $archivos = [];
        foreach ($a->archivos ?? [] as $f) {
            if (Storage::disk('local')->exists($f['ruta'])) {
                Arr::set($archivos, $f['campo'], new UploadedFile(Storage::disk('local')->path($f['ruta']), $f['nombre'], $f['mime'], null, true));
            }
        }

        return $archivos;
    }

    private function limpiarArchivos(Aprobacion $a): void
    {
        foreach ($a->archivos ?? [] as $f) {
            Storage::disk('local')->delete($f['ruta']);
        }
    }

    // ── Descripción legible para quien aprueba ───────────────────────────────

    /** @return array{0:string,1:string[]} título y líneas de detalle */
    private function describir(string $accion, string $ruta, array $params, array $in): array
    {
        $nombreProducto = fn ($id) => $id ? (DB::table('productos')->where('id', $id)->value('nombre') ?: "Producto {$id}") : '';
        $lineas = fn () => $this->lineasDe($in);

        switch ($accion) {
            case 'producto_nuevo':
                return ['Producto nuevo: ' . ($in['nombre'] ?? 'sin nombre'), $this->camposLegibles($in)];

            case 'producto_edicion':
                $p = \App\Models\Producto::find($params['producto'] ?? null);

                return ['Editar producto: ' . ($p?->nombre ?? 'sin nombre'), $this->diferencias($p, $in)];

            case 'producto_eliminacion':
                $p = \App\Models\Producto::find($params['producto'] ?? null);

                return ['Eliminar producto: ' . ($p?->nombre ?? 'sin nombre'), [$p ? "Código {$p->codigo}" : 'El producto ya no existe']];

            case 'variante_nueva':
                return ['Nueva variante de ' . $nombreProducto($params['producto'] ?? null), $this->camposLegibles($in)];

            case 'variante_edicion':
                $v = \App\Models\ProductoVariante::find($params['variante'] ?? null);

                return ['Editar variante ' . ($v?->codigo_kyrios ?? ''), $this->diferencias($v, $in)];

            case 'variante_eliminacion':
                $v = \App\Models\ProductoVariante::find($params['variante'] ?? null);

                return ['Eliminar variante ' . ($v?->codigo_kyrios ?? ''), [$v ? 'De: ' . $nombreProducto($v->producto_id) : 'La variante ya no existe']];

            case 'ingreso_escaner':
                return ['Ingreso con escáner en ' . $this->referencia('almacen_id', $in['almacen_id'] ?? null), array_merge(
                    array_filter([!empty($in['documento_referencia']) ? 'Documento: ' . $in['documento_referencia'] : null]),
                    $lineas()
                )];

            case 'ingreso_movimiento':
                return [ucfirst((string) ($in['tipo_movimiento'] ?? 'movimiento')) . ' de inventario: ' . $nombreProducto($in['producto_id'] ?? null), $this->camposLegibles($in)];

            case 'compra':
                return ['Compra ' . ($in['numero_factura'] ?? '') . ' · ' . $this->referencia('proveedor_id', $in['proveedor_id'] ?? null), array_merge(
                    $this->camposLegibles(Arr::except($in, ['detalles', 'proveedor_id', 'numero_factura'])),
                    $lineas()
                )];

            case 'pedido_interno_atencion':
                $pi = \App\Models\PedidoInterno::with(['origen', 'destino', 'detalles'])->find($params['pedidoInterno'] ?? null);

                return ['Atender pedido interno ' . ($pi?->codigo ?? ''), $pi ? array_merge(
                    ["De {$pi->origen?->nombre} a {$pi->destino?->nombre}"],
                    $this->lineasDe(['lineas' => $pi->detalles->map(fn ($d) => ['producto_id' => $d->producto_id, 'variante_id' => $d->variante_id, 'cantidad' => $d->cantidad])->all()])
                ) : ['El pedido ya no existe']];

            case 'traslado':
                return ['Traslado ' . $this->referencia('almacen_id', $in['almacen_id'] ?? null) . ' → ' . $this->referencia('almacen_destino_id', $in['almacen_destino_id'] ?? null), array_merge(
                    array_filter([!empty($in['observaciones']) ? 'Observaciones: ' . $in['observaciones'] : null]),
                    $lineas()
                )];
        }

        return [ucfirst(str_replace('_', ' ', $accion)), $this->camposLegibles($in)];
    }

    /** Líneas de productos (compras, traslados, escáner) como «Producto × cantidad». */
    private function lineasDe(array $in): array
    {
        $salida = [];
        foreach (['detalles', 'productos', 'lineas'] as $clave) {
            foreach ((array) ($in[$clave] ?? []) as $l) {
                if (!is_array($l)) {
                    continue;
                }
                // El escáner solo envía la variante: el producto se busca a partir de ella
                $productoId = $l['producto_id'] ?? (!empty($l['variante_id']) ? DB::table('producto_variantes')->where('id', $l['variante_id'])->value('producto_id') : null);
                $nombre = $productoId ? $this->referencia('producto_id', $productoId) : '';
                $variante = !empty($l['variante_id']) ? ' (' . $this->referencia('variante_id', $l['variante_id']) . ')' : '';
                $precio = isset($l['precio_unitario']) ? ' a ' . $l['precio_unitario'] : '';
                $salida[] = trim(($nombre ?: 'Variante ' . ($l['variante_id'] ?? '?')) . $variante) . ' × ' . ($l['cantidad'] ?? '?') . $precio;
            }
        }

        return $salida;
    }

    private function referencia(string $campo, mixed $id): string
    {
        if (!$id) {
            return '';
        }
        [$tabla, $col] = self::REFERENCIAS[$campo] ?? [null, null];
        if (!$tabla) {
            return (string) $id;
        }
        try {
            return (string) (DB::table($tabla)->where('id', $id)->value($col) ?: $id);
        } catch (\Throwable) {
            return (string) $id;
        }
    }

    /** Campos simples con valor, con nombres legibles. */
    private function camposLegibles(array $in): array
    {
        $salida = [];
        foreach ($in as $campo => $valor) {
            if (is_array($valor) || $valor === null || $valor === '') {
                continue;
            }
            $texto = isset(self::REFERENCIAS[$campo]) ? $this->referencia($campo, $valor) : (string) $valor;
            $salida[] = $this->etiqueta($campo) . ': ' . $texto;
        }

        return $salida;
    }

    /** Qué cambia en un registro existente: «campo: antes → después». */
    private function diferencias(?Model $modelo, array $in): array
    {
        if (!$modelo) {
            return ['El registro ya no existe'];
        }

        $salida = [];
        foreach ($in as $campo => $nuevo) {
            if (is_array($nuevo) || !array_key_exists($campo, $modelo->getAttributes())) {
                continue;
            }
            $antes = $modelo->getAttribute($campo);
            $iguales = (string) ($antes ?? '') === (string) ($nuevo ?? '')
                || (is_numeric($antes) && is_numeric($nuevo) && (float) $antes === (float) $nuevo);
            if (!$iguales) {
                $salida[] = $this->etiqueta($campo) . ': ' . ($antes === null || $antes === '' ? '(vacío)' : $antes) . ' → ' . ($nuevo === null || $nuevo === '' ? '(vacío)' : $nuevo);
            }
        }

        return $salida ?: ['Sin cambios en los campos principales'];
    }

    private function etiqueta(string $campo): string
    {
        return ucfirst(str_replace(['_id', '_'], ['', ' '], $campo));
    }
}
