<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario administrador principal del sistema.
     * Idempotente: usa updateOrCreate por email.
     *
     * Configurar en .env:
     *   ADMIN_NAME="Administración"
     *   ADMIN_EMAIL="admin@tuempresa.com"
     *   ADMIN_PASSWORD="cambiar_en_produccion"
     */
    public function run(): void
    {
        $adminRole = Role::where('nombre', Role::ADMINISTRACION)->first();

        if (!$adminRole) {
            $this->command->error('❌ Rol Administración no encontrado. Ejecuta RoleSeeder primero.');
            return;
        }

        $esLocal  = app()->environment(['local', 'development', 'testing']);
        $email    = config('kyrios.admin.email') ?: ($esLocal ? 'admin@importaciones.com' : null);
        $nombre   = config('kyrios.admin.nombre') ?: 'Administración';
        $password = config('kyrios.admin.password') ?: ($esLocal ? 'Admin1234!' : null);

        // En producción no existe una clave por defecto: sin credenciales propias no se crea el administrador.
        if (!$email || !$password) {
            $this->command->error('Defina ADMIN_EMAIL y ADMIN_PASSWORD en el .env antes de ejecutar el seeder en este entorno.');
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $nombre,
                'password' => Hash::make($password),
                'role_id'  => $adminRole->id,
                'estado'   => 'activo',
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info("✅ Usuario admin creado: {$email}");
        } else {
            $this->command->info("✅ Usuario admin ya existe, datos actualizados: {$email}");
        }

        if ($esLocal) {
            $this->command->warn("   Contraseña: {$password}");
        }
    }
}
