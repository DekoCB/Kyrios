<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use Illuminate\Console\Command;

/**
 * Importa la base de contactos (JSON normalizado a partir del Excel comercial).
 * Con documento → cliente; sin documento → prospecto. Es repetible: no duplica.
 */
class ImportarClientes extends Command
{
    protected $signature = 'clientes:importar {archivo : JSON con la clave "clientes"} {--dry-run : Solo cuenta, no escribe}';

    protected $description = 'Importa clientes y prospectos desde el JSON de la base comercial';

    private const LIMITES = [
        'nombre' => 255, 'contacto' => 120, 'cargo' => 120, 'empresa' => 190, 'telefono' => 20, 'telefono_fijo' => 30,
        'anexo' => 15, 'email' => 255, 'email_empresa' => 255, 'direccion' => 255, 'distrito' => 100,
        'especialidad' => 150, 'asesor' => 80, 'origen' => 150, 'redes_sociales' => 1000, 'preferencias' => 1000,
    ];

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        $datos = json_decode(file_get_contents($archivo), true);
        if (!is_array($datos['clientes'] ?? null)) {
            $this->error('El JSON no trae la clave "clientes".');
            return self::FAILURE;
        }

        $cuenta = ['creados' => 0, 'ya_existian' => 0, 'prospectos' => 0, 'con_documento' => 0, 'con_error' => 0];
        $errores = [];
        $seco = (bool) $this->option('dry-run');

        foreach ($datos['clientes'] as $posicion => $fila) {
            $fila = $this->limpiar(is_array($fila) ? $fila : []);

            if (empty($fila['nombre'])) {
                $cuenta['con_error']++;
                $errores[] = 'Fila ' . ($posicion + 1) . ': sin nombre';
                continue;
            }

            $existente = $this->buscarExistente($fila);

            if ($existente) {
                $cuenta['ya_existian']++;
                continue;
            }

            if (!$seco) {
                try {
                    $creado = $fila['created_at'] ?? null;
                    unset($fila['created_at']);
                    $cliente = Cliente::create($fila + ['estado' => 'activo']);
                    if ($creado) {
                        $cliente->forceFill(['created_at' => $creado])->saveQuietly();
                    }
                } catch (\Throwable $e) {
                    // Un dato malo (fecha imposible, número fuera de rango) no detiene el resto de la carga
                    $cuenta['con_error']++;
                    $errores[] = 'Fila ' . ($posicion + 1) . " ({$fila['nombre']}): " . mb_substr(strtok($e->getMessage(), "\n"), 0, 120);
                    continue;
                }
            }

            $cuenta[$fila['numero_documento'] ? 'con_documento' : 'prospectos']++;
            $cuenta['creados']++;
        }

        foreach (array_slice($errores, 0, 20) as $linea) {
            $this->warn($linea);
        }

        $this->info(($seco ? '[simulación] ' : '') . "Creados {$cuenta['creados']} (con documento {$cuenta['con_documento']}, prospectos {$cuenta['prospectos']}); ya existían {$cuenta['ya_existian']}; {$cuenta['con_error']} con error.");

        return self::SUCCESS;
    }

    private function limpiar(array $fila): array
    {
        foreach (self::LIMITES as $campo => $max) {
            if (isset($fila[$campo]) && $fila[$campo] !== null) {
                $fila[$campo] = mb_substr(trim((string) $fila[$campo]), 0, $max) ?: null;
            }
        }
        // Una fecha imposible (30 de febrero) se descarta: Carbon la corrige en silencio a otra fecha
        if (!empty($fila['fecha_cumpleanos'])) {
            $d = \DateTime::createFromFormat('!Y-m-d', (string) $fila['fecha_cumpleanos']);
            if (!$d || $d->format('Y-m-d') !== $fila['fecha_cumpleanos']) {
                $fila['fecha_cumpleanos'] = null;
            }
        }
        // Documento sin espacios ni guiones (viene así de copiar desde Excel o un PDF)
        if (!empty($fila['numero_documento'])) {
            $fila['numero_documento'] = preg_replace('/[\s\-]+/', '', $fila['numero_documento']);
        }
        if (empty($fila['numero_documento'])) {
            $fila['numero_documento'] = $fila['tipo_documento'] = null;
        }

        return array_intersect_key($fila, array_flip(array_merge(
            (new Cliente)->getFillable(), ['created_at']
        )));
    }

    private function buscarExistente(array $fila): ?Cliente
    {
        if ($fila['numero_documento']) {
            return Cliente::where('numero_documento', $fila['numero_documento'])->first();
        }

        return Cliente::whereNull('numero_documento')->where('nombre', $fila['nombre'])->first();
    }
}
