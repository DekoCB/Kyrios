<?php

namespace App\Support;

use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocumento;
use Illuminate\Support\Collection;

/**
 * Proforma con el diseño oficial de Kyrios (cabecera, ítems, condiciones, anexo de imágenes y condiciones comerciales).
 * La usan las cotizaciones y las ventas; cada una arma el «documento» con lo suyo.
 */
class ProformaPdf
{
    /**
     * @param array{numero:string,fecha:mixed,cliente:mixed,referencia:string,asesor:?string,formato:string,detalles:Collection,notas:?string} $datos
     *        detalles: líneas con producto, variante, cantidad, precio_unitario (soles, sin IGV) y, si el formato lo usa, piso y ambiente.
     */
    public static function generar(array $datos, bool $enDolares, float $tc): PdfDocumento
    {
        // La proforma oficial lleva los datos de Kyrios; lo que falte en Empresa se completa con ellos.
        $empresa = Empresa::first() ?? new Empresa();
        foreach (['razon_social' => 'KYRIOS LUCES S.A.C', 'ruc' => '20606247746', 'email' => 'administracion@kyrios.com.pe', 'web' => 'www.kyrios.com.pe'] as $campo => $valor) {
            $empresa->{$campo} = $empresa->{$campo} ?: $valor;
        }
        $doc = (object) $datos;

        return Pdf::setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif', 'chroot' => public_path()])
            ->loadView('pdf.proforma', compact('doc', 'empresa', 'enDolares', 'tc'))
            ->setPaper('A4', 'portrait');
    }
}
