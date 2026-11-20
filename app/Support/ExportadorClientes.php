<?php

namespace App\Support;

use App\Models\Cliente;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Exporta la base de clientes con la misma estructura del Excel comercial («Base de datos - Consolidado»):
 * mismas columnas, en el mismo orden y con las mismas franjas (REGISTRO, DATOS DE IDENTIFICACIÓN, DATOS DE SEGUIMIENTO,
 * DATOS PROFESIONALES, HISTORIAL DE CLIENTE y los bloques de seguimiento y proyecto). Al final van los datos propios del sistema.
 * Una fila por cliente; si tiene varios seguimientos o proyectos, las filas siguientes repiten solo el ID y el nombre.
 */
class ExportadorClientes
{
    private const LAVANDA = 'D9D2E9';
    private const AMARILLO = 'FFFF00';
    private const NARANJA = 'FF9900';
    private const GRIS = 'E7E6E6';

    /** Separa un nombre completo en nombres / primer apellido / segundo apellido (el sistema guarda el nombre en un solo campo). */
    public static function partirNombre(?string $completo): array
    {
        $t = array_values(array_filter(preg_split('/\s+/', trim((string) $completo))));
        // «de», «del», «la»… pertenecen al apellido que les sigue
        $unidos = [];
        foreach ($t as $x) {
            if ($unidos && in_array(mb_strtolower(end($unidos)), ['de', 'del', 'la', 'las', 'los', 'y'], true)) {
                $unidos[count($unidos) - 1] .= ' ' . $x;
            } else {
                $unidos[] = $x;
            }
        }
        $n = count($unidos);

        return match (true) {
            $n === 0 => ['', '', ''],
            $n === 1 => [$unidos[0], '', ''],
            $n === 2 => [$unidos[0], $unidos[1], ''],
            default  => [implode(' ', array_slice($unidos, 0, $n - 2)), $unidos[$n - 2], $unidos[$n - 1]],
        };
    }

    /** @return array<int, array{0:string,1:string,2:?string}> [franja, encabezado, color del encabezado] en el orden del Excel */
    public static function columnas(): array
    {
        $y = self::AMARILLO;
        $o = self::NARANJA;
        $reg = 'REGISTRO';
        $ide = 'DATOS DE IDENTIFICACIÓN';
        $seg = 'DATOS DE SEGUIMIENTO';
        $pro = 'DATOS PROFESIONALES';
        $his = 'HISTORIAL DE CLIENTE';
        $cs = 'SEGUIMIENTO COMERCIAL';
        $pj = 'PROYECTO';
        $sis = 'DATOS DEL SISTEMA';

        return [
            [$reg, 'ID CLIENTE', $y], [$reg, 'FECHA REGISTRO', $y], [$reg, 'REGISTRADO POR', $y],
            [$ide, 'TIPO', $y], [$ide, 'NOMBRES', $y], [$ide, 'APELLIDO 1', $y], [$ide, 'APELLIDO 2', $y], [$ide, 'DNI', $y],
            [$seg, 'CORREO PERSONAL', $y], [$seg, 'CELULAR', $y], [$seg, 'USUARIO', $y], [$seg, 'FECHA DE CUMPLEAÑOS', $y], [$seg, 'EDAD', $y],
            [$seg, 'TIPO 2', $y], [$seg, 'DIRECC. RESID.', $y], [$seg, 'REDES SOCIALES PERSONALES', $y], [$seg, 'DATOS ADICIONALES', $y],
            [$pro, 'ESPECIALIDAD', $y], [$pro, 'CAP/CIP', $y], [$pro, 'RUC', $y], [$pro, 'EMPRESA', $y], [$pro, 'TIPO DE EMPRESA', $y],
            [$pro, 'OCUPACIÓN / CARGO', $y], [$pro, 'TELEF. EMPRESA', $y], [$pro, 'ANEXO', $y], [$pro, 'DIRECCIÓN EMPRESA', $y],
            [$pro, 'CORREO EMPRESA', $y], [$pro, 'REDES SOCIALES EMPRESA', $y],
            [$his, 'CLASIFICACIÓN', $y], [$his, 'ALIADOS', $y], [$his, 'COMO INGRESÓ', $y], [$his, 'PREFERENCIA DE CONTACTO', $y], [$his, 'PREFERENCIAS PRODUCT.', $y],
            [$his, 'ÚLTIMA ATENCIÓN POR', $o], [$his, 'FECHA', $o],
            [$cs, 'ÚLTIMO ESTADO', $y], [$cs, 'SITUACIÓN', $y], [$cs, 'QUÉ BUSCA', $y], [$cs, 'TIPO', $y], [$cs, 'NIVEL', $y],
            [$cs, 'MONTO PRESUPUESTADO', $y], [$cs, 'MONTO COMPRADO', $y], [$cs, 'OBSERVACIONES', $y], [$cs, 'RESUMEN DE VISITA', $y],
            [$cs, 'PROBABILIDAD DE VENTA (%)', $y], [$cs, 'MEDIO DE CONTACTO / REFERIDO POR', $y],
            [$pj, 'ID DEL PROYECTO', $y], [$pj, 'PERSONA A CARGO DEL PROYECTO', $y], [$pj, 'PRIORIDAD', $y], [$pj, 'NOMBRE DE PROYECTO', $y],
            [$pj, 'FECHA DE RECEPCIÓN DE PROYECTO', $y], [$pj, 'FECHA APROX. DE ENTREGA', $y], [$pj, 'FECHA REAL DE ENTREGA DE PROYECTO', $y],
            [$pj, 'MONTO PRESUPUESTADO', $y], [$pj, 'MONTO VENDIDO', $y], [$pj, 'RESULTADO', $y], [$pj, 'SEGUIMIENTO / ACCIONES Y/U OBSERVACIONES', $y],
            [$sis, 'TIPO DE DOCUMENTO', $y], [$sis, 'N° DE DOCUMENTO', $y], [$sis, 'LISTAS DE DIFUSIÓN', $y], [$sis, 'COMISIÓN (%)', $y],
            [$sis, 'DISTRITO', $y], [$sis, 'PROVINCIA', $y], [$sis, 'DEPARTAMENTO', $y], [$sis, 'ESTADO', $y], [$sis, 'VERIFICACIÓN SUNAT/RENIEC', $y],
            [$sis, 'N° DE VENTAS', $y], [$sis, 'TOTAL COMPRADO (S/)', $y], [$sis, 'NOTAS', $y],
        ];
    }

    /** Valores de un cliente en el orden de columnas(): seguimiento y proyecto son los de la fila que se está escribiendo. */
    public static function fila(Cliente $c, $seguimiento = null, $proyecto = null): array
    {
        $esEmpresa = $c->tipo_documento === 'RUC';
        // El cliente-empresa lleva el nombre de la empresa y a la persona en «contacto»
        [$nom, $ap1, $ap2] = self::partirNombre($c->contacto ?: ($esEmpresa ? '' : $c->nombre));
        $edad = ($c->fecha_cumpleanos && $c->fecha_cumpleanos->year >= 1900) ? $c->fecha_cumpleanos->age : null;
        $empresa = $esEmpresa ? $c->nombre : $c->empresa;
        $correoEmpresa = $c->email_empresa ?: ($esEmpresa ? $c->email : null);
        $correoPersonal = $esEmpresa && !$c->email_empresa ? null : $c->email;
        $fecha = fn ($d) => $d?->format('d/m/Y');

        return [
            $c->codigo, $fecha($c->created_at), $c->asesor,
            $c->segmento, $nom, $ap1, $ap2, $c->dni,
            $correoPersonal, $c->telefono, null, $c->fecha_cumpleanos?->format('d/m/Y'), $edad,
            null, $esEmpresa ? null : $c->direccion, $c->redes_sociales, null,
            $c->especialidad, null, $c->ruc, $empresa, $c->tipo_empresa,
            $c->cargo, $c->telefono_fijo, $c->anexo, $esEmpresa || $c->empresa ? $c->direccion : null,
            $correoEmpresa, null,
            $c->clasificacion, null, $c->como_ingreso, $c->preferencia_contacto, $c->preferencias,
            $c->ultima_atencion_por, $fecha($c->ultima_atencion_en),
            $seguimiento?->ultimo_estado, $seguimiento?->situacion, $seguimiento?->busca, $seguimiento?->tipo, $seguimiento?->nivel,
            self::monto($seguimiento?->monto_presupuestado, $seguimiento?->moneda), self::monto($seguimiento?->monto_comprado, $seguimiento?->moneda),
            $seguimiento?->observaciones, $seguimiento?->resumen_visita,
            $seguimiento?->probabilidad, $seguimiento?->medio_contacto,
            $proyecto?->codigo, $proyecto?->persona_a_cargo, $proyecto?->prioridad, $proyecto?->nombre,
            $fecha($proyecto?->fecha_recepcion), $fecha($proyecto?->fecha_entrega_aprox), $fecha($proyecto?->fecha_entrega_real),
            self::monto($proyecto?->monto_presupuestado, $proyecto?->moneda), self::monto($proyecto?->monto_vendido, $proyecto?->moneda),
            $proyecto?->resultado, $proyecto?->seguimiento,
            $c->tipo_documento, $c->numero_documento, implode(', ', $c->etiquetas_difusion), $c->comision !== null ? (float) $c->comision : null,
            $c->distrito, $c->provincia, $c->departamento, $c->estado, $c->verificacion,
            $c->ventas_count ?? null, isset($c->total_comprado) ? round((float) $c->total_comprado, 2) : null, $c->notas,
        ];
    }

    /** Monto con su moneda: [valor, 'USD'|'PEN'] para dar formato a la celda. */
    private static function monto($valor, ?string $moneda): ?array
    {
        return $valor === null ? null : [(float) $valor, $moneda === 'PEN' ? 'PEN' : 'USD'];
    }

    public static function libro(Collection $clientes): Spreadsheet
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet()->setTitle('BDFINAL');
        $cols = self::columnas();
        $n = count($cols);

        // Franjas (fila 1, combinadas) y encabezados (fila 2)
        $inicio = 1;
        for ($i = 1; $i <= $n; $i++) {
            $franja = $cols[$i - 1][0];
            $siguiente = $cols[$i][0] ?? null;
            if ($franja !== $siguiente) {
                $a = Coordinate::stringFromColumnIndex($inicio) . '1';
                $b = Coordinate::stringFromColumnIndex($i) . '1';
                $hoja->setCellValue($a, $franja);
                if ($a !== $b) {
                    $hoja->mergeCells("$a:$b");
                }
                $hoja->getStyle("$a:$b")->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LAVANDA]]]);
                $inicio = $i + 1;
            }
        }
        foreach ($cols as $i => [, $titulo, $color]) {
            $celda = Coordinate::stringFromColumnIndex($i + 1) . '2';
            $hoja->setCellValue($celda, $titulo);
            $hoja->getStyle($celda)->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color ?: self::AMARILLO]],
                'borders' => ['allBorders' => ['borderStyle' => 'thin']]]);
        }

        $r = 3;
        foreach ($clientes as $c) {
            $seg = $c->relationLoaded('seguimientos') ? $c->seguimientos->values() : collect();
            $pro = $c->relationLoaded('proyectos') ? $c->proyectos->values() : collect();
            $filas = max(1, $seg->count(), $pro->count());
            for ($k = 0; $k < $filas; $k++) {
                $valores = self::fila($c, $seg[$k] ?? null, $pro[$k] ?? null);
                if ($k > 0) {
                    // las filas adicionales solo repiten quién es el cliente
                    $solo = array_fill(0, count($valores), null);
                    foreach ([0, 4, 5, 6] as $idx) {
                        $solo[$idx] = $valores[$idx];
                    }
                    foreach (range(35, 56) as $idx) {
                        $solo[$idx] = $valores[$idx];
                    }
                    $valores = $solo;
                }
                foreach ($valores as $i => $v) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $celda = Coordinate::stringFromColumnIndex($i + 1) . $r;
                    if (is_array($v)) {
                        $hoja->setCellValue($celda, $v[0]);
                        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode($v[1] === 'PEN' ? '"S/" #,##0.00' : '"$" #,##0.00');
                    } elseif (is_string($v)) {
                        // texto explícito: DNI, RUC y celulares no deben convertirse en números ni perder ceros
                        $hoja->setCellValueExplicit($celda, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $hoja->setCellValue($celda, $v);
                    }
                }
                $r++;
            }
        }

        $hoja->freezePane('F3');
        $hoja->getRowDimension(2)->setRowHeight(32);
        if ($r > 3) {
            $hoja->setAutoFilter('A2:' . Coordinate::stringFromColumnIndex($n) . ($r - 1));
        }
        foreach ($cols as $i => [, $titulo]) {
            $hoja->getColumnDimensionByColumn($i + 1)->setWidth(match (true) {
                in_array($titulo, ['ID CLIENTE', 'EDAD', 'NIVEL', 'ANEXO', 'FECHA', 'PRIORIDAD'], true) => 11,
                str_contains($titulo, 'OBSERVACIONES'), str_contains($titulo, 'NOTAS'), str_contains($titulo, 'ÚLTIMO ESTADO'), str_contains($titulo, 'DIREC') => 42,
                in_array($titulo, ['NOMBRES', 'APELLIDO 1', 'APELLIDO 2', 'EMPRESA', 'NOMBRE DE PROYECTO', 'CORREO PERSONAL', 'CORREO EMPRESA', 'QUÉ BUSCA'], true) => 28,
                default => 18,
            });
        }

        return $libro;
    }
}
