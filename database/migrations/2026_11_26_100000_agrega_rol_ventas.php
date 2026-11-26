<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Devuelve el rol propio de Ventas (el vendedor): registra cotizaciones y ventas pendientes de cobro, que confirma
 * Administración. No tiene usuarios hasta que se creen desde Usuarios.
 */
return new class extends Migration {
    public function up(): void
    {
        Role::firstOrCreate(['nombre' => Role::VENTAS], ['descripcion' => Role::DESCRIPCIONES[Role::VENTAS]]);
    }

    public function down(): void
    {
        $rol = Role::where('nombre', Role::VENTAS)->first();
        if ($rol && !$rol->users()->exists()) {
            $rol->delete();
        }
    }
};
