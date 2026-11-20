<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Opciones de piso y de ambiente que se sugieren al armar una cotización. */
class OpcionUbicacion extends Model
{
    protected $table = 'opciones_ubicacion';

    protected $fillable = ['tipo', 'nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public const TIPOS = ['piso' => 'Pisos', 'ambiente' => 'Ambientes'];

    /** Nombres activos de un tipo, en su orden; si la tabla aún no existe o está vacía, las de fábrica. */
    public static function nombres(string $tipo): array
    {
        try {
            $n = static::where('tipo', $tipo)->where('activo', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre')->all();
        } catch (\Throwable $e) {
            $n = [];
        }

        return $n ?: ($tipo === 'piso' ? \App\Support\SeccionesProforma::PISOS : \App\Support\SeccionesProforma::AMBIENTES);
    }
}
