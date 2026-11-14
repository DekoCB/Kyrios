<?php

namespace App\Database;

use Illuminate\Database\Connectors\MySqlConnector;
use PDOException;
use Throwable;

/**
 * En el hosting compartido MySQL a veces rechaza la conexión un instante («Too many connections», 1040) o el
 * sistema bloquea el socket («Operation not permitted», 2002). Son fallos pasajeros: se reintenta unas veces
 * antes de rendirse, en lugar de mostrar un error 500 al usuario.
 */
class ReintentaMySqlConnector extends MySqlConnector
{
    /** Códigos de MySQL/PDO que indican saturación o corte momentáneo. */
    public const CODIGOS_PASAJEROS = [1040, 1203, 2002, 2006, 2013];

    private const INTENTOS = 4;
    private const ESPERA_MS = [150, 400, 900];

    public function createConnection($dsn, array $config, array $options)
    {
        for ($intento = 0; ; $intento++) {
            try {
                return parent::createConnection($dsn, $config, $options);
            } catch (Throwable $e) {
                if ($intento >= self::INTENTOS - 1 || !self::esPasajero($e)) {
                    throw $e;
                }
                usleep(self::ESPERA_MS[$intento] * 1000);
            }
        }
    }

    public static function esPasajero(Throwable $e): bool
    {
        for ($previo = $e; $previo; $previo = $previo->getPrevious()) {
            $codigo = $previo instanceof PDOException ? ($previo->errorInfo[1] ?? $previo->getCode()) : $previo->getCode();
            if (in_array((int) $codigo, self::CODIGOS_PASAJEROS, true)) {
                return true;
            }
            if (str_contains($previo->getMessage(), 'Too many connections') || str_contains($previo->getMessage(), 'Operation not permitted')) {
                return true;
            }
        }

        return false;
    }
}
