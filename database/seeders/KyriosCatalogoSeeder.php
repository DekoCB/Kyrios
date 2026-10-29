<?php

namespace Database\Seeders;

use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use Illuminate\Database\Seeder;

/**
 * Catálogos de la codificación KYRIOS (PDF "Codificacion KYRIOS - PRODUCTOS"). Idempotente.
 */
class KyriosCatalogoSeeder extends Seeder
{
    public const TIPOS_PRODUCTO = [
        // Caso 1: el código incluye el tipo de luminaria
        ['LU', 'Luminaria',           'con_luminaria'],
        ['LE', 'Luces de emergencia', 'con_luminaria'],
        ['SO', 'Solares',             'con_luminaria'],
        ['RE', 'Recargables',         'con_luminaria'],
        // Caso 2: tipo de luminaria = "00"
        ['LA', 'Lámpara',                      'sin_luminaria'],
        ['CL', 'Cinta LED',                    'sin_luminaria'],
        ['SM', 'Sistema modular',              'sin_luminaria'],
        ['AC', 'Accesorio',                    'sin_luminaria'],
        ['EA', 'Fuente (driver, transformador)', 'sin_luminaria'],
        ['PE', 'Perfiles',                     'sin_luminaria'],
        ['PA', 'Pantalla',                     'sin_luminaria'],
        ['VE', 'Ventilador',                   'sin_luminaria'],
        ['CA', 'Cabezal',                      'sin_luminaria'],
        ['PO', 'Poste',                        'sin_luminaria'],
        // Productos cuyo tipo no se conoce (el código de 2 letras no choca con ningún otro)
        ['ST', 'Sin tipo',                     'sin_luminaria'],
        // PROVISIONAL: aparece en el Excel pero no en el PDF. Confirmar código.
        ['SP', 'Spot',                         'con_luminaria'],
    ];

    /** Los códigos repetidos son intencionales (variantes), ver PDF. */
    public const TIPOS_LUMINARIA = [
        ['25', 'de pie'],
        ['10', 'de mesa'],
        ['10', 'de escritorio'],
        ['05', 'braquete'],
        ['20', 'colgante'],
        ['15', 'techo (plafón / adosado)'],
        ['16', 'proyector'],
        ['71', 'riel'],
        ['ET', 'empotrado de techo'],
        ['17', 'estaca'],
        ['EM', 'empotrado de pared'],
        ['EP', 'empotrado de piso'],
        ['17', 'baliza'],
        ['18', 'reflector'],
        // PROVISIONALES: están en el Excel (Aplicación 1) pero no en el PDF. Confirmar códigos.
        ['30', 'de lectura'],
        ['31', 'sumergible'],
        ['32', 'bañador'],
    ];

    public const MARCAS = [
        '01' => 'LEDS-C4', '02' => 'FARO BARCELONA', '03' => 'ACCORD', '04' => 'AVER METAIS', '05' => 'STILO CLEAN',
        '06' => 'SWAROVSKI', '07' => 'NSL', '08' => 'LZ LED', '09' => 'ECOSCAN', '10' => 'ACEVEL',
        '11' => 'KETAI', '12' => 'MEEK', '13' => 'ARENA LEDS', '14' => 'CADYSUN', '15' => 'BRANDON',
        '16' => 'FLUA', '17' => 'HALCON', '18' => 'DILUCE', '19' => 'NORMALUX', '20' => 'VITA',
        '21' => 'IDEAL LUX', '22' => 'SAGELUX', '23' => 'CANDOLA', '24' => 'CONTEMPORANEA', '25' => 'TRANSGLOBO',
        '26' => 'FV GROUP', '27' => 'OSRAM', '28' => 'CELISZEN', '29' => 'PHILIPS', '30' => 'DURALAMP',
    ];

    public function run(): void
    {
        foreach (self::TIPOS_PRODUCTO as [$codigo, $nombre, $caso]) {
            TipoProducto::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'caso_codigo' => $caso]);
        }

        foreach (self::TIPOS_LUMINARIA as [$codigo, $nombre]) {
            TipoLuminaria::updateOrCreate(['nombre' => $nombre], ['codigo' => $codigo]);
        }

        foreach (self::MARCAS as $codigo => $nombre) {
            Marca::updateOrCreate(['nombre' => $nombre], ['codigo' => $codigo]);
        }
    }
}
