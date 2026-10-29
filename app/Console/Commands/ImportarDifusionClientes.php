<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Carga las listas de difusión de WhatsApp desde la hoja «LISTA DE DIFUSION» del Excel de clientes.
 * Es repetible: empareja por nombre, suma etiquetas sin duplicarlas y no vuelve a crear a quien ya existe.
 */
class ImportarDifusionClientes extends Command
{
    protected $signature = 'clientes:importar-difusion {archivo : Libro .xlsx con la hoja «LISTA DE DIFUSION»} {--dry-run : Solo cuenta, no escribe}';

    protected $description = 'Asigna a los clientes las listas de difusión de WhatsApp desde el Excel comercial';

    /** Encabezado normalizado de la hoja => claves de lista que activa. */
    private const COLUMNAS = [
        'ARQUITECTOS'        => ['arquitecto'],
        'INGENIEROS'         => ['ingeniero'],
        'DIS Y DECORADORES'  => ['disenador', 'decorador'],
        'CLIENTES FINALES'   => ['cliente_final'],
        'PAISAJISTAS'        => ['paisajista'],
        'MAMAS'              => ['mama'],
        'PAPAS'              => ['papa'],
        'MUJER'              => ['mujer'],
        'HOMBRE'             => ['hombre'],
    ];

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        $contactos = $this->leerHoja($archivo);
        if ($contactos === null) {
            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $indice = $this->indiceDeClientes();
        $c = ['filas' => $contactos['filas'], 'sin_nombre' => $contactos['sin_nombre'], 'sin_listas' => $contactos['sin_listas'],
            'contactos' => count($contactos['porNombre']), 'emparejados' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'creados' => 0, 'ambiguos' => 0];
        $ambiguos = [];

        foreach ($contactos['porNombre'] as $clave => $dato) {
            $ids = $indice[$clave] ?? [];

            if (count($ids) > 1) {
                $c['ambiguos']++;
                $ambiguos[] = $dato['nombre'] . ' (' . count($ids) . ' clientes)';
                continue;
            }

            if (count($ids) === 1) {
                $c['emparejados']++;
                $cliente = Cliente::find($ids[0]);
                $nuevas = array_values(array_unique(array_merge((array) $cliente->listas_difusion, $dato['listas'])));
                if ($nuevas === array_values((array) $cliente->listas_difusion)) {
                    $c['sin_cambios']++;
                } else {
                    $c['actualizados']++;
                    if (!$seco) {
                        $cliente->update(['listas_difusion' => $this->ordenar($nuevas)]);
                    }
                }
                continue;
            }

            $c['creados']++;
            if (!$seco) {
                Cliente::create([
                    'nombre'          => $this->nombreLegible($dato['nombre']),
                    'estado'          => 'activo',
                    'listas_difusion' => $this->ordenar($dato['listas']),
                    'origen'          => 'Lista de difusión',
                ]);
            }
        }

        foreach (array_slice($ambiguos, 0, 15) as $linea) {
            $this->warn('Ambiguo, no se asignó: ' . $linea);
        }

        $this->info(($seco ? '[simulación] ' : '')
            . "Filas {$c['filas']} (sin nombre {$c['sin_nombre']}, sin ninguna lista {$c['sin_listas']}); contactos distintos {$c['contactos']}: "
            . "emparejados {$c['emparejados']} (actualizados {$c['actualizados']}, sin cambios {$c['sin_cambios']}), creados {$c['creados']}, ambiguos {$c['ambiguos']}.");

        return self::SUCCESS;
    }

    /** Lee la hoja y agrupa por nombre normalizado, uniendo las listas de las filas repetidas. */
    private function leerHoja(string $archivo): ?array
    {
        try {
            $lector = IOFactory::createReaderForFile($archivo);
            $lector->setReadDataOnly(true);
            $libro = $lector->load($archivo);
        } catch (\Throwable $e) {
            $this->error('No se pudo leer el libro: ' . $e->getMessage());
            return null;
        }

        $hoja = null;
        foreach ($libro->getSheetNames() as $nombre) {
            if ($this->normalizar($nombre) === 'LISTA DE DIFUSION') {
                $hoja = $libro->getSheetByName($nombre);
                break;
            }
        }
        if (!$hoja) {
            $this->error('El libro no tiene la hoja «LISTA DE DIFUSION».');
            return null;
        }

        $filas = $hoja->toArray(null, true, false, false);
        $cabecera = null;
        $columnas = [];
        $cuenta = ['filas' => 0, 'sin_nombre' => 0, 'sin_listas' => 0, 'porNombre' => []];

        foreach ($filas as $fila) {
            if ($cabecera === null) {
                foreach ($fila as $i => $celda) {
                    if ($this->normalizar((string) $celda) === 'NOMBRES') {
                        $cabecera = $i;
                    }
                }
                if ($cabecera !== null) {
                    foreach ($fila as $i => $celda) {
                        $clave = $this->normalizar((string) $celda);
                        if (isset(self::COLUMNAS[$clave])) {
                            $columnas[$i] = self::COLUMNAS[$clave];
                        }
                    }
                }
                continue;
            }

            if (!array_filter($fila, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }
            $cuenta['filas']++;

            $nombre = trim(preg_replace('/\s+/', ' ', (string) ($fila[$cabecera] ?? '')));
            if ($nombre === '') {
                $cuenta['sin_nombre']++;
                continue;
            }

            $listas = [];
            foreach ($columnas as $i => $claves) {
                $marca = trim((string) ($fila[$i] ?? ''));
                if ($marca !== '' && $marca !== '-') {
                    $listas = array_merge($listas, $claves);
                }
            }
            if (!$listas) {
                $cuenta['sin_listas']++;
                continue;
            }

            $clave = $this->normalizar($nombre);
            $previo = $cuenta['porNombre'][$clave]['listas'] ?? [];
            $cuenta['porNombre'][$clave] = [
                'nombre' => $cuenta['porNombre'][$clave]['nombre'] ?? $nombre,
                'listas' => array_values(array_unique(array_merge($previo, $listas))),
            ];
        }

        if ($cabecera === null) {
            $this->error('La hoja no tiene una columna «NOMBRES».');
            return null;
        }

        return $cuenta;
    }

    /** Nombre normalizado => ids de clientes que lo tienen (más de uno = ambiguo). */
    private function indiceDeClientes(): array
    {
        $indice = [];
        Cliente::query()->select('id', 'nombre')->orderBy('id')->chunk(500, function ($lote) use (&$indice) {
            foreach ($lote as $cliente) {
                $indice[$this->normalizar($cliente->nombre)][] = $cliente->id;
            }
        });

        return $indice;
    }

    /** Mayúsculas sin tildes, sin signos y con espacios simples. */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(Str::ascii($texto));
        $texto = preg_replace('/[^A-Z0-9]+/', ' ', $texto);

        return trim($texto);
    }

    private function nombreLegible(string $nombre): string
    {
        return mb_strtoupper($nombre) === $nombre ? mb_convert_case(mb_strtolower($nombre), MB_CASE_TITLE) : $nombre;
    }

    /** Deja las claves en el orden de Cliente::LISTAS_DIFUSION. */
    private function ordenar(array $listas): array
    {
        return array_values(array_filter(array_keys(Cliente::LISTAS_DIFUSION), fn ($k) => in_array($k, $listas, true)));
    }
}
