<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea (o actualiza) un usuario de prueba por cada rol, para probar el sistema en un entorno real.
 * No forma parte de DatabaseSeeder: se ejecuta a mano y la clave se pasa al ejecutarlo, no queda en el repositorio.
 *
 *   CLAVE_USUARIOS='UnaClave123' php artisan db:seed --class=UsuariosPorRolSeeder --force
 */
class UsuariosPorRolSeeder extends Seeder
{
    private const USUARIOS = [
        ['name' => 'Contador',   'email' => 'contador@gmail.com',   'role' => Role::CONTADOR],
        ['name' => 'Logística',  'email' => 'logistica@gmail.com',  'role' => Role::LOGISTICA],
        ['name' => 'Operaciones', 'email' => 'operaciones@gmail.com', 'role' => Role::OPERACIONES],
        ['name' => 'Cliente',    'email' => 'cliente@gmail.com',    'role' => Role::CLIENTE],
    ];

    public function run(): void
    {
        $clave = getenv('CLAVE_USUARIOS');
        if (!$clave || strlen($clave) < 8) {
            $this->command->error('Pase la clave al ejecutar: CLAVE_USUARIOS=\'UnaClave123\' php artisan db:seed --class=UsuariosPorRolSeeder --force (mínimo 8 caracteres).');

            return;
        }

        $roles = Role::pluck('id', 'nombre');
        $filas = [];
        foreach (self::USUARIOS as $u) {
            $roleId = $roles->get($u['role']);
            if (!$roleId) {
                $this->command->warn("Rol «{$u['role']}» no encontrado (ejecute RoleSeeder primero); se omite {$u['email']}.");
                continue;
            }
            User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make($clave), 'role_id' => $roleId, 'estado' => 'activo']
            );
            $filas[] = [$u['role'], $u['email'], $clave];
        }

        $this->command->table(['Rol', 'Correo', 'Clave'], $filas);
    }
}
