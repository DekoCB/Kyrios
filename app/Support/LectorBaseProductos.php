<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Lee la hoja «Base de Productos» del Excel de Kyrios (cabecera en la fila 4, datos desde la 5).
 * Devuelve las filas como [fila de Excel => [índice de columna (0 = A) => valor]].
 */
class LectorBaseProductos
{
    public const COL_CODIGO = 0;
    public const COL_CODIGO_KYRIOS_EXCEL = 1;
    public const COL_TIPO_PRODUCTO = 5;
    public const COL_NOMBRE_FABRICA = 10;
    public const COL_NOMBRE_K = 11;

    public static function filas(string $archivo, int $maxCol = 45): array
    {
        $filtro = new class($maxCol) implements IReadFilter {
            public function __construct(private int $maxCol) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= 5 && Coordinate::columnIndexFromString($columnAddress) <= $this->maxCol;
            }
        };

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly('Base de Productos');
        $reader->setReadFilter($filtro);
        $ws = $reader->load($archivo)->getActiveSheet();

        $filas = [];
        foreach ($ws->getRowIterator(5) as $row) {
            $r = [];
            foreach ($row->getCellIterator('A', Coordinate::stringFromColumnIndex($maxCol)) as $cell) {
                $valor = $cell->getValue();
                if (is_string($valor) && str_starts_with($valor, '=')) {
                    $valor = $cell->getOldCalculatedValue();
                }
                $r[Coordinate::columnIndexFromString($cell->getColumn()) - 1] = $valor;
            }
            if (array_filter($r, fn ($v) => $v !== null && $v !== '')) {
                $filas[$row->getRowIndex()] = $r;
            }
        }
        $ws->getParent()->disconnectWorksheets();

        return $filas;
    }

    /** Texto limpio de una celda; «-», «--» y vacío cuentan como sin dato. */
    public static function texto($v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return null;
        }
        if (is_float($v) && floor($v) == $v) {
            $v = (int) $v;
        }
        $v = is_string($v) ? trim($v) : $v;

        return ($v === null || $v === '' || $v === '-' || $v === '--') ? null : (string) $v;
    }
}
