<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Cómo se agrupan las líneas de una proforma según su formato:
 *  - simple:   sin títulos.
 *  - ambiente: «SALA - COMEDOR», «COCINA», «BAÑO DE VISITA»…
 *  - piso:     «PRIMER NIVEL - HALL», «SEGUNDO NIVEL - COWORKING» (el ambiente es opcional dentro del piso).
 */
class SeccionesProforma
{
    public const FORMATOS = [
        'simple'   => 'Simple',
        'ambiente' => 'Por ambiente',
        'piso'     => 'Por piso',
    ];

    public const AMBIENTES = ['Sala', 'Comedor', 'Sala - Comedor', 'Cocina', 'Baño', 'Baño de visita', 'Dormitorio', 'Dormitorio principal',
        'Estudio', 'Hall', 'Pasillo', 'Escalera', 'Lavandería', 'Terraza', 'Jardín', 'Cochera', 'Fachada', 'Coworking', 'Gimnasio'];

    public const PISOS = ['Sótano', 'Primer nivel', 'Segundo nivel', 'Tercer nivel', 'Cuarto nivel', 'Quinto nivel', 'Azotea'];

    public static function formatoValido(?string $formato): string
    {
        return array_key_exists((string) $formato, self::FORMATOS) ? $formato : 'simple';
    }

    /** Título de la sección a la que pertenece una línea (null en el formato simple). */
    public static function titulo(string $formato, ?string $piso, ?string $ambiente): ?string
    {
        $piso = trim((string) $piso);
        $ambiente = trim((string) $ambiente);

        return match ($formato) {
            'ambiente' => mb_strtoupper($ambiente !== '' ? $ambiente : 'Sin ambiente'),
            'piso'     => mb_strtoupper(implode(' - ', array_filter([$piso !== '' ? $piso : 'Sin piso', $ambiente]))),
            default    => null,
        };
    }

    /** Deja en cada línea solo lo que el formato usa (por ambiente no lleva piso; simple no lleva ninguno). */
    public static function limpiar(string $formato, ?string $piso, ?string $ambiente): array
    {
        $piso = trim((string) $piso) !== '' ? mb_substr(trim((string) $piso), 0, 60) : null;
        $ambiente = trim((string) $ambiente) !== '' ? mb_substr(trim((string) $ambiente), 0, 60) : null;

        return match ($formato) {
            'ambiente' => ['piso' => null, 'ambiente' => $ambiente],
            'piso'     => ['piso' => $piso, 'ambiente' => $ambiente],
            default    => ['piso' => null, 'ambiente' => null],
        };
    }

    /**
     * Ordena las líneas por sección, conservando el orden en que apareció cada sección y el de las líneas dentro de ella.
     *
     * @param Collection $lineas objetos con propiedades piso y ambiente (pueden faltar)
     */
    public static function ordenar(Collection $lineas, string $formato): Collection
    {
        if ($formato === 'simple') {
            return $lineas->values();
        }

        return $lineas->values()
            ->groupBy(fn ($l) => (string) self::titulo($formato, $l->piso ?? null, $l->ambiente ?? null))
            ->flatten(1)
            ->values();
    }
}
