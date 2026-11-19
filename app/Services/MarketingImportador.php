<?php

namespace App\Services;

use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Metrica;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa métricas diarias de una cuenta desde un CSV o Excel (por ejemplo, lo que se descarga de Metricool).
 * La primera fila trae los encabezados; se reconocen en español e inglés. Si ya hay datos de esa fecha, se actualizan.
 */
class MarketingImportador
{
    /** Nombre normalizado del encabezado => columna de mkt_metricas. */
    private const ENCABEZADOS = [
        'fecha' => 'fecha', 'date' => 'fecha', 'dia' => 'fecha', 'day' => 'fecha',
        'seguidores' => 'seguidores', 'followers' => 'seguidores', 'seguidoresnetos' => 'seguidores', 'comunidad' => 'seguidores',
        'alcance' => 'alcance', 'reach' => 'alcance',
        'impresiones' => 'impresiones', 'impressions' => 'impresiones', 'visualizaciones' => 'impresiones', 'views' => 'impresiones',
        'interacciones' => 'interacciones', 'interactions' => 'interacciones', 'engagement' => 'interacciones', 'interaccionestotales' => 'interacciones',
        'reproducciones' => 'reproducciones', 'videoviews' => 'reproducciones', 'reproduccionesdevideo' => 'reproducciones', 'plays' => 'reproducciones',
        'clics' => 'clics', 'clicks' => 'clics', 'clic' => 'clics',
        'publicaciones' => 'publicaciones', 'posts' => 'publicaciones', 'contenidototal' => 'publicaciones',
    ];

    /** @return array{creadas: int, actualizadas: int, omitidas: int, errores: array<int, string>} */
    public function metricas(Cuenta $cuenta, UploadedFile $archivo): array
    {
        $filas = $this->leer($archivo);
        $res = ['creadas' => 0, 'actualizadas' => 0, 'omitidas' => 0, 'errores' => []];

        if (count($filas) < 2) {
            $res['errores'][] = 'El archivo no tiene filas de datos (la primera fila debe traer los encabezados).';

            return $res;
        }

        $mapa = [];
        foreach ($filas[0] as $i => $titulo) {
            $clave = self::ENCABEZADOS[$this->normalizar((string) $titulo)] ?? null;
            if ($clave && !in_array($clave, $mapa, true)) {
                $mapa[$i] = $clave;
            }
        }
        if (!in_array('fecha', $mapa, true) || count($mapa) < 2) {
            $res['errores'][] = 'No encontré la columna «fecha» y al menos una métrica (seguidores, alcance, impresiones, interacciones, reproducciones, clics o publicaciones).';

            return $res;
        }

        foreach (array_slice($filas, 1) as $n => $fila) {
            $linea = $n + 2;
            $dato = [];
            foreach ($mapa as $i => $col) {
                $dato[$col] = $fila[$i] ?? null;
            }
            $fecha = $this->fecha($dato['fecha'] ?? null);
            if (!$fecha) {
                if (array_filter($fila, fn ($v) => trim((string) $v) !== '')) {
                    $res['omitidas']++;
                    $res['errores'][] = "Fila {$linea}: la fecha no es válida.";
                }
                continue;
            }

            $valores = [];
            foreach (Metrica::ACUMULABLES as $col) {
                $valores[$col] = $this->entero($dato[$col] ?? null);
            }
            $valores['seguidores'] = $this->entero($dato['seguidores'] ?? null);
            if (count(array_filter($valores, fn ($v) => $v !== null)) === 0) {
                $res['omitidas']++;
                continue;
            }

            $m = Metrica::firstOrNew(['cuenta_id' => $cuenta->id, 'fecha' => $fecha]);
            $existia = $m->exists;
            // Solo se pisa lo que el archivo trae; lo demás se conserva
            $m->fill(array_filter($valores, fn ($v) => $v !== null))->save();
            $existia ? $res['actualizadas']++ : $res['creadas']++;
        }

        return $res;
    }

    /** @return array<int, array<int, mixed>> */
    private function leer(UploadedFile $archivo): array
    {
        $ext = strtolower($archivo->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            $texto = file_get_contents($archivo->getRealPath());
            $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
            if (!mb_check_encoding($texto, 'UTF-8')) {
                $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
            }
            $primera = strtok($texto, "\n") ?: '';
            $sep = collect([';' => substr_count($primera, ';'), ',' => substr_count($primera, ','), "\t" => substr_count($primera, "\t")])->sortDesc()->keys()->first();
            $filas = [];
            foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
                if (trim($linea) !== '') {
                    $filas[] = str_getcsv($linea, $sep);
                }
            }

            return $filas;
        }

        return IOFactory::load($archivo->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
    }

    private function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]/', '', $t);
    }

    private function fecha($v): ?string
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000) { // fecha serial de Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        $v = trim((string) $v);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $formato) {
            $d = \DateTime::createFromFormat('!' . $formato, $v);
            if ($d && $d->format($formato) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private function entero($v): ?int
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        $limpio = preg_replace('/[^\d.,-]/', '', (string) $v);
        // «1,234» o «1.234» como miles; «12,5» o «12.5» como decimal que se redondea
        if (preg_match('/^-?\d{1,3}([.,]\d{3})+$/', $limpio)) {
            $limpio = str_replace([',', '.'], '', $limpio);
        } else {
            $limpio = str_replace(',', '.', $limpio);
        }

        return is_numeric($limpio) && (float) $limpio >= 0 ? (int) round((float) $limpio) : null;
    }
}
