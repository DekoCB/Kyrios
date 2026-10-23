<?php

namespace App\Console\Commands;

use App\Models\ProductoCodigoBarras;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Carga masiva de imágenes desde una carpeta (máximo 6 por variante).
 *
 * Cada imagen se asocia a una variante por su código — Kyrios (KY-LU2501-0001), del fabricante
 * (25-0573-N3-B8), del Excel anterior (KLU01-00003) o de barras de la caja — y puede indicar el orden
 * con un sufijo. Repetir la importación no duplica las fotos que ya están cargadas (se comparan por contenido):
 *
 *   KY-LU2501-0001.jpg        → imagen 1
 *   KY-LU2501-0001_2.jpg      → imagen 2        (también "-2" o " (2)")
 *   25-0573-N3-B8/foto-a.jpg  → carpeta con el código; el orden sale del nombre del archivo
 */
class ImportarImagenesProductos extends Command
{
    protected $signature = 'productos:importar-imagenes
                            {carpeta : Carpeta con las imágenes (se recorre completa)}
                            {--reemplazar : Elimina las imágenes actuales de las variantes que reciban imágenes nuevas}
                            {--dry-run : Solo muestra qué se asociaría}';

    protected $description = 'Asocia imágenes (hasta 6 por variante) a las variantes por código';

    public function handle(): int
    {
        $carpeta = $this->argument('carpeta');
        if (!is_dir($carpeta)) {
            $this->error("No existe la carpeta: {$carpeta}");
            return self::FAILURE;
        }

        $indice = $this->indiceCodigos();
        $grupos = [];          // variante_id => [[orden|null, ruta archivo], ...]
        $sinCodigo = [];
        $ambiguos = [];

        $finder = (new Finder())->files()->in($carpeta)->name('/\.(jpe?g|png|webp)$/i')->sortByName();
        foreach ($finder as $archivo) {
            $base = pathinfo($archivo->getFilename(), PATHINFO_FILENAME);
            $carpetaPadre = basename($archivo->getPath());

            [$varianteIds, $orden] = $this->resolver($base, $indice);
            if (!$varianteIds && $carpetaPadre !== basename($carpeta)) {
                [$varianteIds] = $this->resolver($carpetaPadre, $indice);
                $orden = $this->ordenDeNombre($base);
            }

            if (!$varianteIds) {
                $sinCodigo[] = $archivo->getRelativePathname();
                continue;
            }
            if (count($varianteIds) > 1) {
                $ambiguos[] = $archivo->getRelativePathname() . ' → ' . count($varianteIds) . ' variantes';
                continue;
            }
            $grupos[$varianteIds[0]][] = [$orden, $archivo->getRealPath()];
        }

        $nuevas = $descartadas = $repetidas = 0;
        foreach ($grupos as $varianteId => $archivos) {
            $variante = ProductoVariante::find($varianteId);
            if ($this->option('reemplazar') && !$this->option('dry-run')) {
                foreach ($variante->imagenes as $img) {
                    Storage::disk('public')->delete($img->ruta);
                    $img->delete();
                }
            }

            // Con orden explícito primero (1..6), luego los demás por nombre
            usort($archivos, fn ($a, $b) => [$a[0] ?? 99, $a[1]] <=> [$b[0] ?? 99, $b[1]]);
            $ocupados = $this->option('reemplazar') ? [] : $variante->imagenes()->pluck('orden')->all();

            // Contenido de las fotos que la variante ya tiene: una foto igual no se vuelve a cargar
            $existentes = [];
            if (!$this->option('reemplazar')) {
                foreach ($variante->imagenes as $img) {
                    if (Storage::disk('public')->exists($img->ruta)) {
                        $existentes[md5(Storage::disk('public')->get($img->ruta))] = true;
                    }
                }
            }

            foreach ($archivos as [$ordenPedido, $ruta]) {
                $hash = md5_file($ruta);
                if (isset($existentes[$hash])) {
                    $repetidas++;
                    continue;
                }
                $existentes[$hash] = true;

                $orden = ($ordenPedido && !in_array($ordenPedido, $ocupados, true))
                    ? $ordenPedido
                    : collect(range(1, ProductoImagen::MAX_POR_VARIANTE))->first(fn ($n) => !in_array($n, $ocupados, true));

                if (!$orden) {
                    $descartadas++;
                    continue;
                }
                $ocupados[] = $orden;
                $nuevas++;

                if ($this->option('dry-run')) {
                    continue;
                }
                $destino = "productos/{$variante->id}/{$orden}." . strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
                Storage::disk('public')->put($destino, file_get_contents($ruta));
                ProductoImagen::updateOrCreate(
                    ['variante_id' => $variante->id, 'orden' => $orden],
                    ['producto_id' => $variante->producto_id, 'ruta' => $destino, 'es_principal' => false]
                );
            }

            if (!$this->option('dry-run') && !$variante->imagenes()->where('es_principal', true)->exists()) {
                $variante->imagenes()->orderBy('orden')->first()?->update(['es_principal' => true]);
            }
        }

        $this->table(['Concepto', 'Cantidad'], [
            ['Variantes con imágenes', count($grupos)],
            ['Imágenes ' . ($this->option('dry-run') ? 'por cargar' : 'cargadas'), $nuevas],
            ['Ya cargadas (misma foto)', $repetidas],
            ['Descartadas (la variante ya tiene 6)', $descartadas],
            ['Sin código reconocible', count($sinCodigo)],
            ['Código ambiguo (varias variantes)', count($ambiguos)],
        ]);

        foreach (['Sin código reconocible' => $sinCodigo, 'Ambiguos' => $ambiguos] as $titulo => $lista) {
            if ($lista) {
                $this->warn($titulo . ' (primeros 15):');
                collect($lista)->take(15)->each(fn ($l) => $this->line("  - {$l}"));
            }
        }

        return self::SUCCESS;
    }

    /** clave normalizada del código → [variante_id, ...] */
    private function indiceCodigos(): array
    {
        $indice = [];
        ProductoVariante::query()->select('id', 'codigo_kyrios', 'codigo_fabricante', 'codigo_excel')
            ->orderBy('id')->chunk(500, function ($filas) use (&$indice) {
                foreach ($filas as $v) {
                    foreach ([$v->codigo_kyrios, $v->codigo_fabricante, $v->codigo_excel] as $c) {
                        if ($c) {
                            $indice[$this->clave($c)][$v->id] = $v->id;
                        }
                    }
                }
            });

        // Códigos de barras asociados a una variante
        ProductoCodigoBarras::whereNotNull('variante_id')->get(['variante_id', 'codigo_barras'])
            ->each(function ($c) use (&$indice) {
                $indice[$this->clave($c->codigo_barras)][$c->variante_id] = $c->variante_id;
            });

        return array_map('array_values', $indice);
    }

    /** @return array{0: int[], 1: ?int} variantes y orden pedido en el nombre */
    private function resolver(string $nombre, array $indice): array
    {
        if (isset($indice[$this->clave($nombre)])) {
            return [$indice[$this->clave($nombre)], null];
        }
        // Sufijo de orden: _2, -2, " (2)", " 2"
        if (preg_match('/^(.*?)(?:\s*\((\d)\)|[_\-\s](\d))$/', $nombre, $m)) {
            $orden = (int) ($m[2] !== '' ? $m[2] : $m[3]);
            if ($orden >= 1 && $orden <= ProductoImagen::MAX_POR_VARIANTE && isset($indice[$this->clave($m[1])])) {
                return [$indice[$this->clave($m[1])], $orden];
            }
        }

        return [[], null];
    }

    private function ordenDeNombre(string $base): ?int
    {
        return preg_match('/(?:^|[_\-\s(])([1-6])\)?$/', $base, $m) ? (int) $m[1] : null;
    }

    private function clave(string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/', '', Str::ascii(trim($s))));
    }
}
