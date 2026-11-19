<?php

namespace App\Models\Marketing;

use App\Models\User;
use App\Support\Marketing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cuenta oficial de la empresa en una red social. */
class Cuenta extends Model
{
    protected $table = 'mkt_cuentas';

    public const ESTADOS = ['conectada' => 'Conectada', 'por_reconectar' => 'Por reconectar', 'desconectada' => 'Desconectada'];

    protected $fillable = ['red', 'nombre', 'usuario', 'url', 'estado', 'origen', 'responsable_id', 'notas', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function metricas(): HasMany
    {
        return $this->hasMany(Metrica::class, 'cuenta_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function scopeActivas($q)
    {
        return $q->where('activa', true);
    }

    public function getRedNombreAttribute(): string
    {
        return Marketing::nombreRed($this->red);
    }

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Último dato de seguidores cargado. */
    public function getSeguidoresActualesAttribute(): ?int
    {
        return $this->metricas()->whereNotNull('seguidores')->orderByDesc('fecha')->value('seguidores');
    }
}
