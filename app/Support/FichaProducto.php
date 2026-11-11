<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoVariante;

/**
 * Datos técnicos de un producto y de una variante como lista «etiqueta => valor» (solo lo que tiene dato).
 * Los comparten el detalle del producto y la ficha técnica en PDF.
 */
class FichaProducto
{
    private static function n($valor, string $unidad = ''): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $texto = is_numeric($valor) ? rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.') : (string) $valor;

        return trim($texto . ' ' . $unidad);
    }

    private static function lista($valor): ?string
    {
        return is_array($valor) && $valor ? implode(', ', $valor) : null;
    }

    private static function limpiar(array $items): array
    {
        return array_filter($items, fn ($v) => filled($v));
    }

    /** Datos generales del producto (comunes a todas sus variantes). */
    public static function producto(Producto $p): array
    {
        return self::limpiar([
            'Tipo de producto'   => $p->tipoProducto?->nombre,
            'Tipo de luminaria'  => $p->tipoLuminaria?->nombre,
            'Línea'              => $p->linea,
            'Procedencia'        => $p->procedencia,
            'Fuente de luz'      => $p->fuente_luz,
            'Uso'                => $p->uso,
            'Aplicación'         => $p->aplicacion_2,
            'Material principal' => $p->material_1,
            'Material secundario' => $p->material_2,
            'Nivel de potencia'  => $p->nivel_potencia,
            'Voltaje'            => $p->voltaje,
            'IP'                 => $p->ip,
            'IK'                 => $p->ik,
            'CRI'                => $p->cri,
            'Ángulo de apertura' => $p->angulo_apertura,
            'Driver'             => $p->driver,
            'Regulable'          => $p->regulable === null ? null : ($p->regulable ? 'Sí' : 'No'),
            'Protocolo de regulación' => $p->protocolo_regulacion,
            'Estilos'            => self::lista($p->estilos),
            'Tipos de instalación' => self::lista($p->tipos_instalacion),
            'Tipos de proyecto'  => self::lista($p->tipos_proyecto),
            'Garantía'           => $p->dias_garantia ? $p->dias_garantia . ' días' . ($p->tipo_garantia ? " ({$p->tipo_garantia})" : '') : null,
            'Código SUNAT'       => $p->codigo_sunat,
            'Ficha técnica del fabricante' => $p->ficha_tecnica,
            'Observaciones'      => $p->observaciones,
        ]);
    }

    /** Datos propios de una variante: color, medidas, potencia, temperatura, embalaje, etc. */
    public static function variante(ProductoVariante $v): array
    {
        return self::limpiar([
            'Color'              => $v->color?->nombre,
            'Acabado'            => collect([$v->acabado_1, $v->acabado_2])->filter()->implode(' / ') ?: null,
            'Tamaño'             => $v->tamano,
            'Capacidad'          => $v->capacidad,
            'Diámetro'           => self::n($v->diametro, 'mm'),
            'Alto'               => self::n($v->alto, 'mm'),
            'Ancho'              => self::n($v->ancho, 'mm'),
            'Profundidad'        => self::n($v->profundidad, 'mm'),
            'Alto suspendido'    => self::n($v->alto_suspendido, 'mm'),
            'Diámetro de agujero' => self::n($v->diametro_agujero, 'mm'),
            'Socket'             => $v->socket ? trim($v->socket . ($v->num_lamparas ? ' ' . $v->num_lamparas : '')) : null,
            'Tipo de lámpara'    => $v->tipo_lampara,
            'Potencia'           => self::n($v->potencia_w, 'W'),
            'Lúmenes'            => self::n($v->lumenes, 'lm'),
            'Temperatura de color' => self::n($v->temperatura_color, 'K'),
            'Tonalidad de luz'   => $v->tonalidad_luz,
            'Ángulo del haz'     => $v->angulo_haz,
            'Regulación'         => $v->protocolo_regulacion,
            'Eficiencia'         => self::n($v->eficiencia_luminica, 'lm/W'),
            'IP'                 => $v->ip,
            'CRI'                => $v->cri,
            'Vida útil'          => $v->vida_util_horas !== null ? number_format($v->vida_util_horas, 0, ',', '.') . ' h' : null,
            'Garantía'           => $v->garantia_meses !== null ? $v->garantia_meses . ' meses' : null,
            'Peso'               => self::n($v->peso, 'kg'),
            'Volumen'            => self::n($v->volumen, 'm³'),
            'Embalaje'           => $v->embalado,
            'Medida de embalaje' => $v->medida_embalaje,
            'Cantidad por caja'  => $v->cantidad_por_caja,
            'Código del fabricante' => $v->codigo_fabricante,
            'Códigos relacionados' => is_array($v->codigos_relacionados) ? self::lista($v->codigos_relacionados) : $v->codigos_relacionados,
            'Otros'              => $v->otros,
        ]);
    }

    /** Foto como data URI para incrustarla en un PDF (null si no existe o pesa demasiado). */
    public static function imagenDataUri(?\App\Models\ProductoImagen $imagen): ?string
    {
        if (!$imagen) {
            return null;
        }
        $ruta = \Illuminate\Support\Facades\Storage::disk('public')->path($imagen->ruta);
        if (!is_file($ruta) || filesize($ruta) > 3_000_000) {
            return null;
        }
        $mime = mime_content_type($ruta) ?: 'image/jpeg';
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null; // dompdf lee con seguridad solo JPG y PNG
        }

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($ruta));
    }
}
