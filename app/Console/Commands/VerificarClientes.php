<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\SunatService;
use Illuminate\Console\Command;

/**
 * Contrasta los clientes que tienen documento con SUNAT (RUC) y RENIEC (DNI).
 * Es reanudable (solo toma los pendientes), espera entre consultas y nunca marca
 * un fallo temporal (límite de la API gratuita, caída) como «no encontrado».
 */
class VerificarClientes extends Command
{
    protected $signature = 'clientes:verificar {--limite=0 : Máximo de consultas (0 = todas)} {--pausa=1.5 : Segundos entre consultas} {--espera=60 : Segundos de espera cuando la API limita el ritmo} {--reintentar : Vuelve a revisar los ya verificados}';

    protected $description = 'Verifica DNI y RUC de los clientes con RENIEC y SUNAT';

    public function handle(SunatService $sunat): int
    {
        $limite = (int) $this->option('limite');
        $pausa = (float) $this->option('pausa');

        $pendientes = Cliente::whereNotNull('numero_documento')
            ->whereIn('tipo_documento', ['DNI', 'RUC'])
            ->when(!$this->option('reintentar'), fn ($q) => $q->whereNull('verificacion'))
            ->orderBy('id');

        $total = (clone $pendientes)->count();
        $this->info("Pendientes de verificar: {$total}");

        $hechos = $fallos = 0;
        $contadores = ['ok' => 0, 'discrepancia' => 0, 'no_encontrado' => 0];

        foreach ($pendientes->lazyById(100) as $cliente) {
            if ($limite > 0 && $hechos >= $limite) {
                break;
            }

            $res = $this->consultar($sunat, $cliente);
            while (!($res['success'] ?? false) && !$this->noExiste($res) && ++$fallos < 6) {
                $this->warn("  {$cliente->numero_documento}: {$res['message']} — espero {$this->option('espera')} s");
                sleep((int) $this->option('espera'));
                $res = $this->consultar($sunat, $cliente);
            }
            $hechos++;

            if ($res['success'] ?? false) {
                $contadores[$this->aplicar($cliente, $res['data'])]++;
            } elseif ($this->noExiste($res)) {
                $this->marcarNoEncontrado($cliente);
                $contadores['no_encontrado']++;
            } else {
                $this->error('La API sigue limitando el ritmo. Reintente más tarde con el mismo comando: continúa donde quedó.');
                break;
            }

            $fallos = 0;
            usleep((int) ($pausa * 1_000_000));
        }

        $this->info("Consultados {$hechos}: ok {$contadores['ok']}, nombre distinto {$contadores['discrepancia']}, no encontrados {$contadores['no_encontrado']}.");

        return self::SUCCESS;
    }

    private function consultar(SunatService $sunat, Cliente $cliente): array
    {
        return $cliente->tipo_documento === 'RUC'
            ? $sunat->consultarRuc($cliente->numero_documento)
            : $sunat->consultarDni($cliente->numero_documento);
    }

    private function noExiste(array $res): bool
    {
        return str_contains(mb_strtolower($res['message'] ?? ''), 'no encontrado');
    }

    /** Guarda lo oficial y devuelve 'ok' o 'discrepancia' según coincida el nombre del archivo. */
    private function aplicar(Cliente $cliente, array $dato): string
    {
        $oficial = trim($dato['razon_social'] ?? $dato['nombre'] ?? '');
        $coincide = self::nombresParecidos($cliente->nombre, $oficial)
            || ($cliente->tipo_documento === 'RUC' && self::nombresParecidos((string) $cliente->empresa, $oficial));

        $cambios = ['verificado_at' => now()];

        if ($coincide) {
            // Persona o empresa confirmada: se adopta el nombre oficial.
            $cambios['nombre'] = mb_substr($oficial, 0, 255);
            $cambios['verificacion'] = 'ok';
        } else {
            $cambios['verificacion'] = 'discrepancia';
            $cambios['notas'] = trim(($cliente->notas ? $cliente->notas . "\n" : '') . "Nombre oficial ({$this->fuente($cliente)}): {$oficial}");
        }

        if ($cliente->tipo_documento === 'RUC') {
            foreach (['direccion' => 'direccion_fiscal', 'distrito' => 'distrito', 'provincia' => 'provincia', 'departamento' => 'departamento', 'ubigeo' => 'ubigeo'] as $campo => $origen) {
                if (!empty($dato[$origen]) && $dato[$origen] !== '-' && ($campo === 'ubigeo' || empty($cliente->{$campo}) || $cliente->{$campo} === '-')) {
                    $cambios[$campo] = $dato[$origen];
                }
            }
            if (!empty($dato['estado']) && strtoupper($dato['estado']) !== 'ACTIVO') {
                $cambios['notas'] = trim(($cambios['notas'] ?? $cliente->notas ?? '') . "\nSUNAT: estado {$dato['estado']}" . (!empty($dato['condicion']) ? ", condición {$dato['condicion']}" : ''));
            }
        }

        $cliente->forceFill($cambios)->save();

        return $cambios['verificacion'];
    }

    private function marcarNoEncontrado(Cliente $cliente): void
    {
        // Un DNI al que se le puso el 0 inicial y no existe en RENIEC era un dato mal digitado: vuelve a prospecto.
        if (str_contains((string) $cliente->notas, 'se completó con un 0 inicial')) {
            $cliente->forceFill([
                'tipo_documento' => null,
                'numero_documento' => null,
                'verificacion' => null,
                'notas' => trim($cliente->notas . "
DNI {$cliente->numero_documento} no existe en RENIEC; se quitó."),
            ])->save();

            return;
        }

        $cliente->forceFill([
            'verificacion' => 'no_encontrado',
            'verificado_at' => now(),
            'notas' => trim(($cliente->notas ? $cliente->notas . "\n" : '') . "No figura en {$this->fuente($cliente)}: revisar el número."),
        ])->save();
    }

    private function fuente(Cliente $cliente): string
    {
        return $cliente->tipo_documento === 'RUC' ? 'SUNAT' : 'RENIEC';
    }

    /** ¿El nombre del archivo y el oficial son la misma persona/empresa? (≥ mitad de palabras en común) */
    public static function nombresParecidos(string $archivo, string $oficial): bool
    {
        $a = self::palabras($archivo);
        $b = self::palabras($oficial);
        if (!$a || !$b) {
            return false;
        }
        $comunes = count(array_intersect($a, $b));

        return $comunes >= max(1, (int) ceil(min(count($a), count($b)) / 2));
    }

    private static function palabras(string $texto): array
    {
        $texto = strtolower(\Illuminate\Support\Str::ascii($texto));
        $texto = preg_replace('/[^a-z0-9 ]/', ' ', $texto);
        $vacias = ['sac', 'sa', 'srl', 'eirl', 'de', 'del', 'la', 'el', 'y', 'e', 'los', 'las', 'arq', 'ing', 'sra', 'sr', 'peru'];

        return array_values(array_diff(array_filter(explode(' ', $texto), fn ($p) => strlen($p) > 1), $vacias));
    }
}
