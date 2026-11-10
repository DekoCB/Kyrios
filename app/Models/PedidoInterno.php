<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoInterno extends Model
{
    protected $table = 'pedidos_internos';

    protected $fillable = [
        'codigo', 'solicitante_id', 'almacen_origen_id', 'almacen_destino_id', 'area', 'fecha', 'fecha_requerida',
        'estado', 'observaciones', 'traslado_guia', 'atendido_por', 'atendido_at',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'fecha_requerida' => 'date',
        'atendido_at'     => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::created(function (self $p) {
            if (empty($p->codigo) || str_starts_with($p->codigo, 'TMP-')) {
                $p->forceFill(['codigo' => 'PI-' . str_pad($p->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
        static::creating(function (self $p) {
            // Provisorio y único hasta conocer el id: el código definitivo se arma con él
            $p->codigo = $p->codigo ?: 'TMP-' . bin2hex(random_bytes(6));
        });
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function atendidoPor()
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function origen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    public function detalles()
    {
        return $this->hasMany(PedidoInternoDetalle::class);
    }

    public function scopePendientes($q)
    {
        return $q->where('estado', 'pendiente');
    }

    public function getVencidoAttribute(): bool
    {
        return $this->estado === 'pendiente' && $this->fecha_requerida && $this->fecha_requerida->isPast() && !$this->fecha_requerida->isToday();
    }
}
