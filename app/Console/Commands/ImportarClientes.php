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

        $cuenta = ['creados' => 0, 'ya_existian' => 0, 'prospectos' => 0, 'con_documento' => 0];
        $seco = (bool) $this->option('dry-run');

        foreach ($datos['clientes'] as $fila) {
            $fila = $this->limpiar($fila);
            $existente = $this->buscarExistente($fila);

            if ($existente) {
                $cuenta['ya_existian']++;
                continue;
            }

            $cuenta[$fila['numero_documento'] ? 'con_documento' : 'prospectos']++;
            $cuenta['creados']++;

            if (!$seco) {
                $creado = $fila['created_at'] ?? null;
                unset($fila['created_at']);
                $cliente = Cliente::create($fila + ['estado' => 'activo']);
                if ($creado) {
                    $cliente->forceFill(['created_at' => $creado])->saveQuietly();
                }
            }
        }

        $this->info(($seco ? '[simulación] ' : '') . "Creados {$cuenta['creados']} (con documento {$cuenta['con_documento']}, prospectos {$cuenta['prospectos']}); ya existían {$cuenta['ya_existian']}.");

        return self::SUCCESS;
    }

    private function limpiar(array $fila): array
    {
        foreach (self::LIMITES as $campo => $max) {
            if (isset($fila[$campo]) && $fila[$campo] !== null) {
                $fila[$campo] = mb_substr(trim((string) $fila[$campo]), 0, $max) ?: null;
            }
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
