<?php

namespace App\Support;

/**
 * Catálogos del módulo de Marketing: redes sociales y sus etiquetas.
 */
class Marketing
{
    /** red => [nombre, icono Font Awesome, color de fondo/texto para etiquetas, color de gráficos] */
    public const REDES = [
        'instagram' => ['Instagram', 'fab fa-instagram', 'bg-pink-100 text-pink-800', '#db2777'],
        'facebook'  => ['Facebook', 'fab fa-facebook', 'bg-sky-100 text-sky-800', '#2563eb'],
        'tiktok'    => ['TikTok', 'fab fa-tiktok', 'bg-gray-800 text-white', '#111827'],
        'linkedin'  => ['LinkedIn', 'fab fa-linkedin', 'bg-cyan-100 text-cyan-800', '#0369a1'],
        'youtube'   => ['YouTube', 'fab fa-youtube', 'bg-red-100 text-red-800', '#dc2626'],
        'otra'      => ['Otra', 'fas fa-globe', 'bg-gray-100 text-gray-700', '#6b7280'],
    ];

    public static function redes(): array
    {
        return array_map(fn ($r) => $r[0], self::REDES);
    }

    public static function nombreRed(?string $red): string
    {
        return self::REDES[$red][0] ?? ucfirst((string) $red);
    }

    public static function iconoRed(?string $red): string
    {
        return self::REDES[$red][1] ?? 'fas fa-globe';
    }

    public static function claseRed(?string $red): string
    {
        return self::REDES[$red][2] ?? 'bg-gray-100 text-gray-700';
    }

    public static function colorRed(?string $red): string
    {
        return self::REDES[$red][3] ?? '#6b7280';
    }

    /** Número con separador de miles, o guion si no hay dato. */
    public static function numero($n): string
    {
        return $n === null ? '—' : number_format((float) $n, 0, '.', ',');
    }

    /** Porcentaje con un decimal, o guion. */
    public static function porcentaje($n): string
    {
        return $n === null ? '—' : number_format((float) $n, 1, '.', ',') . ' %';
    }
}
