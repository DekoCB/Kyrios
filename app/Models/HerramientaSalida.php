<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HerramientaSalida extends Model
{
    protected $table = 'herramienta_salidas';

    protected $fillable = [
        'herramienta_id', 'tecnico_id', 'cantidad', 'cronograma_evento_id', 'destino', 'fecha_salida', 'fecha_devolucion_esperada', 'notas',
        'registrado_por', 'estado', 'fecha_retorno', 'cantidad_buena', 'cantidad_danada', 'cantidad_perdida', 'notas_retorno', 'recibido_por',
    ];

    protected $casts = ['fecha_salida' => 'datetime', 'fecha_retorno' => 'datetime', 'fecha_devolucion_esperada' => 'date'];

    public function herramienta()
    {
        return $this->belongsTo(Herramienta::class);
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function receptor()
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function evento()
    {
        return $this->belongsTo(CronogramaEvento::class, 'cronograma_evento_id');
    }

    public function getAtrasadaAttribute(): bool
    {
        return $this->estado === 'en_uso' && $this->fecha_devolucion_esperada && $this->fecha_devolucion_esperada->lt(today());
    }
}
