<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerieComprobante extends Model
{
    protected $table = 'series_comprobantes';

    protected $fillable = [
        'sucursal_id', 'tipo_comprobante', 'tipo_nombre',
        'serie', 'correlativo_actual', 'formato_impresion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'correlativo_actual' => 'integer',
    ];

    // ── Constantes ─────────────────────────────────────────────

    public const TIPOS = [
        '01' => ['nombre' => 'Factura Electrónica',            'prefijo' => 'F'],
        '03' => ['nombre' => 'Boleta de Venta Electrónica',    'prefijo' => 'B'],
        '07' => ['nombre' => 'Nota de Crédito',                'prefijo' => 'FC'],
        '08' => ['nombre' => 'Nota de Débito',                 'prefijo' => 'FD'],
        '09' => ['nombre' => 'Guía de Remisión Remitente',     'prefijo' => 'T'],
        'NE' => ['nombre' => 'Nota de Entrega / Cotización',   'prefijo' => 'CO'],
        // Documentos internos (sin SUNAT): su serie se crea sola, ver NumeracionService
        'NV' => ['nombre' => 'Nota de Venta',                  'prefijo' => 'NV'],
        'CT' => ['nombre' => 'Cotización',                     'prefijo' => 'CT'],
    ];

    /**
     * Tipos estándar que toda sucursal debe tener.
     */
    public const TIPOS_ESTANDAR = [
        ['tipo_comprobante' => '01', 'tipo_nombre' => 'Factura Electrónica',            'serie_template' => 'FA{N}', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => '03', 'tipo_nombre' => 'Boleta de Venta Electrónica',    'serie_template' => 'BA{N}', 'formato_impresion' => 'ticket'],
        // SUNAT exige que la Nota de Crédito/Débito de una Factura y la de una Boleta usen
        // series con prefijo distinto (F.. vs B..) — no se puede compartir una sola serie "07"
        // para ambas, aunque el código SUNAT del documento sea el mismo. Ver generarNotaCredito().
        ['tipo_comprobante' => '07', 'tipo_nombre' => 'Nota de Crédito (Factura)',      'serie_template' => 'FC{N}', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => '07', 'tipo_nombre' => 'Nota de Crédito (Boleta)',       'serie_template' => 'BC{N}', 'formato_impresion' => 'ticket'],
        ['tipo_comprobante' => '08', 'tipo_nombre' => 'Nota de Débito (Factura)',       'serie_template' => 'FD{N}', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => '08', 'tipo_nombre' => 'Nota de Débito (Boleta)',        'serie_template' => 'BD{N}', 'formato_impresion' => 'ticket'],
        ['tipo_comprobante' => '09', 'tipo_nombre' => 'Guía de Remisión Remitente',     'serie_template' => 'T0{N}', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => 'NV', 'tipo_nombre' => 'Nota de Venta',                  'serie_template' => 'NV{N}', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => 'CT', 'tipo_nombre' => 'Cotización',                     'serie_template' => 'CT{N}', 'formato_impresion' => 'A4'],
    ];

    // ── Relaciones ─────────────────────────────────────────────

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'serie_comprobante_id');
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Obtener y avanzar el correlativo.
     */
    public function siguienteCorrelativo(): int
    {
        $correlativo = $this->correlativo_actual;
        $this->increment('correlativo_actual');
        return $correlativo;
    }

    /**
     * Número de documento completo: FA01-00000001
     */
    public function numeroDocumento(int $correlativo): string
    {
        return $this->serie . '-' . str_pad($correlativo, 8, '0', STR_PAD_LEFT);
    }

    /**
     * SUNAT exige series de 4 caracteres que empiecen por la letra del documento: F factura, B boleta,
     * F o B en notas de crédito y débito (según el documento que afectan) y T en la guía de remisión remitente.
     * Los documentos internos (nota de venta, cotización) no pasan por SUNAT y solo piden 2 a 5 letras o números.
     */
    public static function patronSerie(string $tipoComprobante): string
    {
        return match ($tipoComprobante) {
            '01'       => '/^F[A-Z0-9]{3}$/',
            '03'       => '/^B[A-Z0-9]{3}$/',
            '07', '08' => '/^[FB][A-Z0-9]{3}$/',
            '09'       => '/^T[A-Z0-9]{3}$/',
            default    => '/^[A-Z0-9]{2,5}$/',
        };
    }

    /** Regla de validación para el campo «serie» según el tipo de comprobante. */
    public static function reglaFormatoSerie(string $tipoComprobante): \Closure
    {
        return function (string $atributo, $valor, \Closure $fallar) use ($tipoComprobante) {
            if (!preg_match(self::patronSerie($tipoComprobante), strtoupper((string) $valor))) {
                $fallar(match ($tipoComprobante) {
                    '01'       => 'La serie de una factura son 4 caracteres y empieza con F (por ejemplo F001).',
                    '03'       => 'La serie de una boleta son 4 caracteres y empieza con B (por ejemplo B001).',
                    '07', '08' => 'La serie de una nota de crédito o débito son 4 caracteres y empieza con F o B (por ejemplo FC01).',
                    '09'       => 'La serie de una guía de remisión son 4 caracteres y empieza con T (por ejemplo T001).',
                    default    => 'La serie solo admite letras y números (de 2 a 5 caracteres).',
                });
            }
        };
    }

    /** Último correlativo emitido con esta serie (null si todavía no se emitió nada). */
    public function ultimoCorrelativoEmitido(): ?int
    {
        $max = Venta::where('serie_comprobante_id', $this->id)->max('correlativo');

        return $max === null ? null : (int) $max;
    }

    /** Regla: el siguiente número no puede ser menor o igual a uno ya emitido (repetiría un comprobante). */
    public function reglaCorrelativoSiguiente(): \Closure
    {
        return function (string $atributo, $valor, \Closure $fallar) {
            $ultimo = $this->ultimoCorrelativoEmitido();
            if ($ultimo !== null && (int) $valor <= $ultimo) {
                $fallar("Ya se emitió hasta el número {$ultimo} con esta serie: el siguiente debe ser " . ($ultimo + 1) . ' o mayor.');
            }
        };
    }
}
