<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Opción de una lista editable que usa el cliente (clasificación, cómo ingresó, etc.). */
class OpcionCliente extends Model
{
    protected $table = 'opciones_cliente';

    protected $fillable = ['grupo', 'nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** Cada grupo coincide con la columna de la tabla clientes donde se guarda la opción elegida. */
    public const GRUPOS = [
        'clasificacion'        => 'Clasificación',
        'como_ingreso'         => 'Cómo ingresó',
        'preferencia_contacto' => 'Preferencia de contacto',
        'especialidad'         => 'Especialidad',
        'tipo_empresa'         => 'Tipo de empresa',
    ];

    /** Nombres activos de un grupo, en su orden. */
    public static function nombres(string $grupo): array
    {
        return static::where('grupo', $grupo)->where('activo', true)->orderBy('orden')->orderBy('id')->pluck('nombre')->all();
    }

    /** Lo mismo para el formulario del cliente: suma el valor que ya tenía si ya no está en la lista. */
    public static function paraSelect(string $grupo, ?string $actual = null): array
    {
        $nombres = static::nombres($grupo);

        return $actual && !in_array($actual, $nombres, true) ? array_merge($nombres, [$actual]) : $nombres;
    }

    /** Cuántos clientes usan esta opción. */
    public function enUso(): int
    {
        return Cliente::where($this->grupo, $this->nombre)->count();
    }
}
