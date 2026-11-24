<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ajustes sueltos del sistema (clave → valor), por ejemplo el margen mínimo general. */
class Ajuste extends Model
{
    protected $table = 'ajustes';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['clave', 'valor'];

    public static function obtener(string $clave): ?string
    {
        return static::query()->whereKey($clave)->value('valor');
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::query()->updateOrInsert(['clave' => $clave], ['valor' => $valor, 'updated_at' => now(), 'created_at' => now()]);
    }
}
