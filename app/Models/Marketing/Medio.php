<?php

namespace App\Models\Marketing;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Archivo de la biblioteca multimedia: imagen, video o diseño. */
class Medio extends Model
{
    protected $table = 'mkt_medios';

    public const TIPOS = ['imagen' => 'Imagen', 'video' => 'Video', 'diseno' => 'Diseño / documento'];
    public const DISCO = 'public';

    protected $fillable = ['titulo', 'tipo', 'ruta', 'nombre_original', 'mime', 'tamano', 'etiquetas', 'producto_id', 'user_id'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(self::DISCO)->url($this->ruta);
    }

    public function getTamanoLegibleAttribute(): string
    {
        $b = (int) $this->tamano;

        return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }

    /** Tipo de la biblioteca según el tipo MIME del archivo subido. */
    public static function tipoPorMime(?string $mime): string
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => 'imagen',
            str_starts_with((string) $mime, 'video/') => 'video',
            default                                   => 'diseno',
        };
    }
}
