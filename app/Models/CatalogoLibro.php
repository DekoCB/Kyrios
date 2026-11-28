<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Catálogo comercial de una temporada: un libro de páginas con productos, que se hojea, imprime y comparte. */
class CatalogoLibro extends Model
{
    protected $table = 'catalogos_libro';

    protected $guarded = [];

    protected $casts = ['vigencia' => 'date', 'enlace_activo' => 'boolean', 'enlace_expira_at' => 'datetime', 'anio' => 'integer', 'diseno' => 'array'];

    /** Catálogo técnico (fichas con características y precios) o brochure (solo imágenes y código, más vistoso). */
    public const TIPOS = ['tecnico' => 'Catálogo técnico', 'brochure' => 'Brochure'];

    /** Tipografías: pila para pantalla y para el PDF (dompdf solo trae fuentes del sistema). `titulo_*` solo si difiere del texto. */
    public const FUENTES = [
        'moderna' => ['etiqueta' => 'Moderna (sans-serif)', 'web' => "'Archivo', system-ui, 'Segoe UI', sans-serif", 'pdf' => 'Helvetica, Arial, sans-serif'],
        'clasica' => ['etiqueta' => 'Clásica (serif)', 'web' => "Georgia, 'Times New Roman', serif", 'pdf' => "'Times New Roman', Times, serif"],
        'elegante' => ['etiqueta' => 'Elegante (títulos serif, texto sans)', 'web' => "'Archivo', system-ui, 'Segoe UI', sans-serif", 'pdf' => 'Helvetica, Arial, sans-serif',
            'titulo_web' => "Georgia, 'Times New Roman', serif", 'titulo_pdf' => "'Times New Roman', Times, serif"],
        'condensada' => ['etiqueta' => 'Condensada', 'web' => "'Arial Narrow', 'Roboto Condensed', 'Archivo Narrow', 'Archivo', sans-serif", 'pdf' => 'Helvetica, Arial, sans-serif'],
        'tecnica' => ['etiqueta' => 'Técnica (monoespaciada)', 'web' => "ui-monospace, 'SF Mono', Consolas, monospace", 'pdf' => 'Courier, monospace'],
    ];

    /** Características técnicas que pueden aparecer en cada producto del catálogo técnico. */
    public const CARACTERISTICAS = ['Acabado', 'Material', 'Potencia', 'Temperatura de color', 'IP', 'Alto', 'Diámetro', 'Largo'];

    public const COLORES = ['papel' => 'Fondo de las páginas', 'tinta' => 'Texto', 'suave' => 'Texto secundario', 'portada' => 'Portada y contraportada', 'acento' => 'Color de acento'];

    public const PORTADAS = ['oscura' => 'Color de portada', 'clara' => 'Color de papel', 'acento' => 'Color de acento', 'foto' => 'Con foto de fondo'];

    /** Diseño de partida de cada tipo; lo guardado en `diseno` lo reemplaza campo por campo. */
    public static function disenoBase(string $tipo): array
    {
        $brochure = $tipo === 'brochure';

        return [
            'fuente' => 'moderna',
            'colores' => ['papel' => $brochure ? '#ffffff' : '#fbfaf5', 'tinta' => $brochure ? '#14130f' : '#22221e', 'suave' => $brochure ? '#5f5d54' : '#6a695f',
                'portada' => $brochure ? '#14130f' : '#3f3d37', 'acento' => '#f2c80f'],
            'portada' => ['titulo' => null, 'subtitulo' => null, 'lema' => 'Luces & entorno', 'estilo' => $brochure ? 'acento' : 'oscura', 'imagen' => null, 'logo' => true],
            'caracteristicas' => self::CARACTERISTICAS,
        ];
    }

    public function esBrochure(): bool
    {
        return $this->tipo === 'brochure';
    }

    /** El diseño completo: el de partida del tipo con lo que se haya personalizado. */
    public function disenoFinal(): array
    {
        $base = self::disenoBase($this->tipo ?: 'tecnico');
        $g = (array) $this->diseno;
        $d = $base;
        $d['fuente'] = isset(self::FUENTES[$g['fuente'] ?? '']) ? $g['fuente'] : $base['fuente'];
        foreach (array_keys(self::COLORES) as $k) {
            if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($g['colores'][$k] ?? ''))) {
                $d['colores'][$k] = strtolower($g['colores'][$k]);
            }
        }
        $gp = (array) ($g['portada'] ?? []);
        foreach (['titulo', 'subtitulo', 'lema', 'imagen'] as $k) {
            if (array_key_exists($k, $gp)) {
                $d['portada'][$k] = filled($gp[$k]) ? $gp[$k] : null;
            }
        }
        if (isset(self::PORTADAS[$gp['estilo'] ?? ''])) {
            $d['portada']['estilo'] = $gp['estilo'];
        }
        if (array_key_exists('logo', $gp)) {
            $d['portada']['logo'] = (bool) $gp['logo'];
        }
        if (isset($g['caracteristicas']) && is_array($g['caracteristicas'])) {
            $d['caracteristicas'] = array_values(array_intersect(self::CARACTERISTICAS, $g['caracteristicas']));
        }
        // Colores derivados: un tinte suave del acento sobre el papel y el texto legible sobre el acento y sobre la portada
        $d['colores']['tinte'] = self::mezclar($d['colores']['acento'], $d['colores']['papel'], 0.16);
        $d['colores']['tinte_fuerte'] = self::mezclar($d['colores']['acento'], $d['colores']['papel'], 0.4);
        $d['colores']['sobre_acento'] = self::textoSobre($d['colores']['acento']);
        $d['colores']['sobre_portada'] = self::textoSobre($d['colores']['portada']);
        $f = self::FUENTES[$d['fuente']];
        $d['css'] = ['fuente' => $f['web'], 'titulo' => $f['titulo_web'] ?? $f['web']];
        $d['pdf'] = ['fuente' => $f['pdf'], 'titulo' => $f['titulo_pdf'] ?? $f['pdf']];

        return $d;
    }

    /** Mezcla dos colores #rrggbb: `$peso` de $a y el resto de $b. */
    public static function mezclar(string $a, string $b, float $peso): string
    {
        $x = sscanf($a, '#%02x%02x%02x');
        $y = sscanf($b, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($x[$i] * $peso + $y[$i] * (1 - $peso)), [0, 1, 2]));
    }

    /** Negro o blanco, el que mejor se lee sobre un color de fondo. */
    public static function textoSobre(string $fondo): string
    {
        [$r, $g, $b] = sscanf($fondo, '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#14130f' : '#ffffff';
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? self::TIPOS['tecnico'];
    }

    public const TEMPORADAS = ['Primavera–Verano', 'Otoño–Invierno', 'Colección especial'];
    public const ESTADOS = ['borrador', 'publicado', 'archivado'];
    public const PRECIOS = ['none', 'pen', 'usd'];
    /** Vencimientos que se ofrecen para el enlace público (días; null = no vence). */
    public const VENCIMIENTOS = ['7' => '7 días', '30' => '30 días', '90' => '90 días', '' => 'Sin vencimiento'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->token = $c->token ?: self::nuevoToken();
        });
    }

    public static function nuevoToken(): string
    {
        do {
            $t = Str::lower(Str::random(8));
        } while (self::where('token', $t)->exists());

        return $t;
    }

    public function paginas()
    {
        return $this->hasMany(CatalogoLibroPagina::class, 'catalogo_id')->orderBy('orden');
    }

    public function vistas()
    {
        return $this->hasMany(CatalogoLibroVista::class, 'catalogo_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function getUrlPublicaAttribute(): string
    {
        return route('catalogos.publico', $this->token);
    }

    /** El enlace público abre solo si está activo y no venció. */
    public function enlaceDisponible(): bool
    {
        return $this->enlace_activo && ($this->enlace_expira_at === null || $this->enlace_expira_at->isFuture());
    }

    public function etiquetaTemporada(): string
    {
        return $this->temporada . ' ' . $this->anio;
    }
}
