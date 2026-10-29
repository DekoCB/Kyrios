<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Roles del sistema (solo estos cinco). Idempotente: se puede ejecutar varias veces sin duplicar.
     */
    public function run(): void
    {
        foreach (Role::TODOS as $nombre) {
            Role::firstOrCreate(['nombre' => $nombre], ['descripcion' => Role::DESCRIPCIONES[$nombre]]);
        }

        $this->command?->info('Roles verificados/creados correctamente.');
    }
}
