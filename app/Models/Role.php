<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /** Nombres de los roles del sistema (los únicos que existen). */
    public const ADMINISTRACION = 'Administración';
    public const CONTADOR       = 'Contador';
    public const LOGISTICA      = 'Logística';
    public const OPERACIONES    = 'Operaciones';
    public const CLIENTE        = 'Cliente';

    /** Roles internos: todos menos Cliente (que solo tiene su propio panel). */
    public const INTERNOS = [self::ADMINISTRACION, self::CONTADOR, self::LOGISTICA, self::OPERACIONES];

    /** Todos los roles, en el orden en que se ofrecen en los formularios. */
    public const TODOS = [self::ADMINISTRACION, self::CONTADOR, self::LOGISTICA, self::OPERACIONES, self::CLIENTE];

    /** Descripción de cada rol (la usa el seeder y la pantalla de usuarios). */
    public const DESCRIPCIONES = [
        self::ADMINISTRACION => 'Acceso total: administración, inventario, facturación, pagos, caja, ventas, usuarios y configuración.',
        self::CONTADOR       => 'Parte administrativa: boletas, facturas, pagos, cuentas por cobrar y por pagar, y todos los reportes (solo lectura en el resto).',
        self::LOGISTICA      => 'Manejo de fábricas y proveedores: compras, pedidos, inventario, traslados, guías de remisión y catálogo.',
        self::OPERACIONES    => 'Programación de tareas internas y externas (instalaciones). Consulta de stock, traslados y guías, y su caja chica.',
        self::CLIENTE        => 'Portal del cliente: sus pedidos y el seguimiento de cada uno (próximamente).',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Get the users for the role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}