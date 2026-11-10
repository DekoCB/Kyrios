<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoEtapa extends Model
{
    protected $table = 'pedido_etapas';

    protected $fillable = ['pedido_id', 'etapa_id', 'nombre', 'orden', 'fecha_prevista', 'fecha_real', 'notas', 'completada_por'];

    protected $casts = ['fecha_prevista' => 'date', 'fecha_real' => 'date'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function completadaPor()
    {
        return $this->belongsTo(User::class, 'completada_por');
    }

    public function getCompletadaAttribute(): bool
    {
        return $this->fecha_real !== null;
    }

    /** Días que faltan para la fecha prevista (negativo si ya pasó); null si no tiene fecha o ya se cumplió. */
    public function getDiasRestantesAttribute(): ?int
    {
        if ($this->completada || !$this->fecha_prevista) {
            return null;
        }

        return (int) today()->diffInDays($this->fecha_prevista, false);
    }
}
