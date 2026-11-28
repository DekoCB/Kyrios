<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sitio donde se puede marcar asistencia: oficina, tienda u obra. La marcación se valida contra su radio. */
class LugarTrabajo extends Model
{
    protected $table = 'lugares_trabajo';

    protected $fillable = ['nombre', 'tipo', 'direccion', 'latitud', 'longitud', 'radio_m', 'activo'];

    protected $casts = ['latitud' => 'float', 'longitud' => 'float', 'radio_m' => 'integer', 'activo' => 'boolean'];

    public const TIPOS = ['oficina' => 'Oficina', 'tienda' => 'Tienda', 'obra' => 'Obra o proyecto', 'otro' => 'Otro'];

    /** Distancia en metros entre este lugar y un punto (fórmula de Haversine). */
    public function distanciaA(float $lat, float $lng): int
    {
        return self::distancia($this->latitud, $this->longitud, $lat, $lng);
    }

    public static function distancia(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $a = deg2rad($lat2 - $lat1);
        $b = deg2rad($lng2 - $lng1);
        $h = sin($a / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($b / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($h))));
    }
}
