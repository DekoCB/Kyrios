<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

/**
 * Seeder exclusivo para desarrollo/testing.
 * Crea usuarios de prueba para cada rol del sistema.
 * NUNCA se ejecuta en producción.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('⚠️  DevelopmentSeeder omitido en producción.');
            return;
        }

        $roles = Role::pluck('id', 'nombre');

        if ($roles->isEmpty()) {
            $this->command->error('❌ No hay roles. Ejecuta RoleSeeder primero.');
            return;
        }

        $usuarios = [
            ['name' => 'Administración Demo', 'email' => 'admin@demo.test',      'password' => 'password', 'role' => Role::ADMINISTRACION],
            ['name' => 'Contador Demo',       'email' => 'contador@demo.test',   'password' => 'password', 'role' => Role::CONTADOR],
            ['name' => 'Logística Demo',      'email' => 'logistica@demo.test',  'password' => 'password', 'role' => Role::LOGISTICA],
            ['name' => 'Operaciones Demo',    'email' => 'operaciones@demo.test', 'password' => 'password', 'role' => Role::OPERACIONES],
            ['name' => 'Cliente Demo',        'email' => 'cliente@demo.test',    'password' => 'password', 'role' => Role::CLIENTE],
        ];

        foreach ($usuarios as $datos) {
            $roleId = $roles->get($datos['role']);

            if (!$roleId) {
                $this->command->warn("⚠️  Rol '{$datos['role']}' no encontrado, omitiendo {$datos['email']}");
                continue;
            }

            User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name'     => $datos['name'],
                    'password' => Hash::make($datos['password']),
                    'role_id'  => $roleId,
                    'estado'   => 'activo',
                ]
            );
        }

        $this->command->info('✅ Usuarios de prueba creados (contraseña: "password"):');
        foreach ($usuarios as $u) {
            $this->command->line("   [{$u['role']}] {$u['email']}");
        }
    }
}
