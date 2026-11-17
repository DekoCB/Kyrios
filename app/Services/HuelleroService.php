<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Asistencia desde el huellero (reloj biométrico ZKTeco, p. ej. LX14).
 *
 * El reloj guarda cada marcación con el número de la persona («PIN») y la fecha y hora. De ahí salen dos caminos:
 *  - archivo exportado por USB (ATTLOG / «…_attlog.dat» / .txt / .csv), que se sube y se revisa antes de importar;
 *  - un puente en la red local del cliente que envía las marcaciones al sistema (API con token).
 * Ambos terminan en importar(): de las marcaciones de cada día, la primera es la entrada y la última la salida.
 */
class HuelleroService
{
    public function __construct(private AsistenciaService $asistencia)
    {
    }

    /** Minutos mínimos entre la primera y la última marcación para que la última cuente como salida (evita tomar un doble toque como salida). */
    public static function minutosParaSalida(): int
    {
        return (int) config('kyrios.rrhh.huellero_minutos_salida', 60);
    }

    /**
     * Lee el texto de un archivo exportado por el reloj. No depende de un formato exacto: en cada línea toma como PIN
     * el primer número y busca la fecha y la hora (aaaa-mm-dd hh:mm[:ss] o dd/mm/aaaa hh:mm[:ss]), separados por tabulación,
     * espacios, comas o punto y coma.
     *
     * @return array{marcaciones: array<int, array{pin: string, fecha_hora: string}>, ignoradas: int}
     */
    public function leerArchivo(string $contenido): array
    {
        // Algunos relojes exportan en UTF-16 o con BOM
        if (str_starts_with($contenido, "\xFF\xFE") || str_starts_with($contenido, "\xFE\xFF")) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-16');
        }
        $contenido = ltrim($contenido, "\xEF\xBB\xBF");

        $marcaciones = [];
        $ignoradas = 0;
        foreach (preg_split('/\R/', $contenido) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            $fechaHora = $this->fechaHoraDeLinea($linea, $posicion);
            if (!$fechaHora) {
                $ignoradas++;   // encabezados u otras líneas sin fecha
                continue;
            }
            $pin = $this->pinDeLinea(substr($linea, 0, $posicion) ?: $linea);
            if ($pin === null) {
                $ignoradas++;
                continue;
            }
            $marcaciones[] = ['pin' => $pin, 'fecha_hora' => $fechaHora->format('Y-m-d H:i:s')];
        }

        return ['marcaciones' => $marcaciones, 'ignoradas' => $ignoradas];
    }

    private function fechaHoraDeLinea(string $linea, ?int &$posicion = null): ?Carbon
    {
        $posicion = null;
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})[\sT]+(\d{1,2}):(\d{2})(?::(\d{2}))?/', $linea, $m, PREG_OFFSET_CAPTURE)) {
            [$anio, $mes, $dia] = [(int) $m[1][0], (int) $m[2][0], (int) $m[3][0]];
            $hora = [(int) $m[4][0], (int) $m[5][0], (int) ($m[6][0] ?? 0)];
        } elseif (preg_match('/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})[\sT]+(\d{1,2}):(\d{2})(?::(\d{2}))?/', $linea, $m, PREG_OFFSET_CAPTURE)) {
            [$dia, $mes, $anio] = [(int) $m[1][0], (int) $m[2][0], (int) $m[3][0]];
            $hora = [(int) $m[4][0], (int) $m[5][0], (int) ($m[6][0] ?? 0)];
        } else {
            return null;
        }
        if (!checkdate($mes, $dia, $anio) || $hora[0] > 23 || $hora[1] > 59 || $hora[2] > 59) {
            return null;
        }
        $posicion = $m[0][1];

        return Carbon::create($anio, $mes, $dia, $hora[0], $hora[1], $hora[2]);
    }

    /** El PIN es el primer número que aparece antes de la fecha. */
    private function pinDeLinea(string $texto): ?string
    {
        foreach (preg_split('/[\s,;|]+/', trim($texto)) as $pedazo) {
            $pedazo = trim($pedazo, "\"'");
            if ($pedazo !== '' && ctype_digit($pedazo)) {
                return $pedazo;
            }
        }

        return null;
    }

    /** Un PIN de «0035» y uno de «35» son la misma persona. */
    public static function normalizarPin(string $pin): string
    {
        $pin = trim($pin);

        return ctype_digit($pin) ? (ltrim($pin, '0') ?: '0') : $pin;
    }

    /**
     * Qué se registraría con estas marcaciones, sin guardar nada.
     *
     * @param array<int, array{pin: string, fecha_hora: string}> $marcaciones
     * @return array{dias: Collection, desconocidos: array<string,int>, total: int, repetidas: int}
     */
    public function analizar(array $marcaciones): array
    {
        $empleados = Empleado::whereNotNull('codigo_huellero')->get()->keyBy(fn ($e) => self::normalizarPin($e->codigo_huellero));
        $porDia = [];
        $desconocidos = [];
        $repetidas = 0;
        $vistas = [];

        foreach ($marcaciones as $m) {
            $pin = self::normalizarPin((string) $m['pin']);
            $momento = Carbon::parse($m['fecha_hora']);
            $clave = $pin . '|' . $momento->format('Y-m-d H:i');
            if (isset($vistas[$clave])) {   // el mismo toque exportado dos veces
                $repetidas++;
                continue;
            }
            $vistas[$clave] = true;

            if (!$empleados->has($pin)) {
                $desconocidos[$m['pin']] = ($desconocidos[$m['pin']] ?? 0) + 1;
                continue;
            }
            $porDia[$empleados[$pin]->id][$momento->toDateString()][] = $momento;
        }

        $dias = collect();
        foreach ($porDia as $empleadoId => $fechas) {
            foreach ($fechas as $fecha => $momentos) {
                $momentos = collect($momentos)->sortBy(fn (Carbon $c) => $c->timestamp)->values();
                $primera = $momentos->first();
                $ultima = $momentos->last();
                $conSalida = $momentos->count() > 1 && $primera->diffInMinutes($ultima) >= self::minutosParaSalida();

                $dias->push((object) [
                    'empleado' => $empleados->first(fn ($e) => $e->id === $empleadoId),
                    'fecha' => $fecha,
                    'entrada' => $primera->format('H:i:s'),
                    'salida' => $conSalida ? $ultima->format('H:i:s') : null,
                    'marcaciones' => $momentos->count(),
                ]);
            }
        }

        return [
            'dias' => $dias->sortBy([['fecha', 'asc'], fn ($a, $b) => strcmp($a->empleado->apellidos, $b->empleado->apellidos)])->values(),
            'desconocidos' => $desconocidos,
            'total' => count($marcaciones),
            'repetidas' => $repetidas,
        ];
    }

    /**
     * Guarda la asistencia de los días analizados. Es repetible: volver a importar el mismo archivo no cambia nada.
     * No pisa los días con permiso, vacaciones, descanso médico o licencia aprobados.
     *
     * @return array{nuevos:int, actualizados:int, sin_cambios:int, omitidos:int, desconocidos:array<string,int>, total:int}
     */
    public function importar(array $marcaciones, ?int $registradoPor = null): array
    {
        $analisis = $this->analizar($marcaciones);
        $res = ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 0, 'desconocidos' => $analisis['desconocidos'], 'total' => $analisis['total']];

        foreach ($analisis['dias'] as $d) {
            $actual = Asistencia::where('empleado_id', $d->empleado->id)->whereDate('fecha', $d->fecha)->first();
            if ($actual && in_array($actual->estado, ['permiso', 'vacaciones', 'descanso_medico', 'licencia'], true)) {
                $res['omitidos']++;
                continue;
            }

            // La entrada es la más temprana conocida y la salida la más tardía (una corrección manual no se pierde si es válida)
            $entrada = $actual?->hora_entrada ? min($actual->hora_entrada, $d->entrada) : $d->entrada;
            $salida = collect([$actual?->hora_salida, $d->salida])->filter()->max();
            $tarde = $this->asistencia->minutosTarde($d->empleado, $entrada);
            $datos = [
                'hora_entrada' => $entrada, 'hora_salida' => $salida, 'estado' => $tarde > 0 ? 'tardanza' : 'puntual', 'minutos_tarde' => $tarde,
            ];

            if (!$actual) {
                Asistencia::create($datos + ['empleado_id' => $d->empleado->id, 'fecha' => $d->fecha, 'origen' => 'huellero', 'registrado_por' => $registradoPor]);
                $res['nuevos']++;
            } elseif ($actual->hora_entrada === $entrada && $actual->hora_salida === $salida && $actual->estado === $datos['estado']) {
                $res['sin_cambios']++;
            } else {
                $actual->update($datos + ['origen' => 'huellero']);
                $res['actualizados']++;
            }
        }

        return $res;
    }
}
