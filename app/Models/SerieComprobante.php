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
        ['tipo_comprobante' => '09', 'tipo_nombre' => 'Guía de Remisión Remitente',     'serie_template' => 'T{N}01', 'formato_impresion' => 'A4'],
        ['tipo_comprobante' => 'NE', 'tipo_nombre' => 'Nota de Entrega/Cotización',     'serie_template' => 'CO{N}', 'formato_impresion' => 'A4'],
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
}
