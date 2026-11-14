<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'almacen_id',
        'telefono',
        'dni',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }
    public function ventasExternasPendientes()
    {
    return $this->hasMany(Venta::class, 'tienda_destino_id');
    }
    /**
     * Check if user has a specific role.
     *
     * @param string $roleName
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->nombre === $roleName;
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->nombre, $roles);
    }
     // Scope para filtrar usuarios por almacén
    public function scopeEnAlmacen($query, $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }
    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'activo');
    }

    /** Ficha de personal vinculada a este usuario (para marcar asistencia). */
    public function empleado(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Empleado::class);
    }
}
