<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronogramaEvento extends Model
{
    protected $table = 'cronograma_eventos';

    protected $fillable = ['tipo', 'titulo', 'cliente_id', 'pedido_id', 'direccion', 'fecha', 'hora', 'responsable_id', 'estado', 'notas', 'creado_por'];

    protected $casts = ['fecha' => 'date'];

    public const TIPOS = ['instalacion' => 'Instalación', 'entrega' => 'Entrega'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopeProgramados($q)
    {
        return $q->where('estado', 'programado');
    }

    public function getDiasRestantesAttribute(): ?int
    {
        return $this->estado === 'programado' ? (int) today()->diffInDays($this->fecha, false) : null;
    }

    public function getHoraCortaAttribute(): ?string
    {
        return $this->hora ? substr($this->hora, 0, 5) : null;
    }
}
