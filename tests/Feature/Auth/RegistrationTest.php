<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrar usuarios y cambiar contraseñas desde fuera exige la contraseña maestra
 * (middleware VerifyMasterPassword).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function claveMaestra(): string
    {
        // Sin valor por defecto en el sistema: cada test fija su propia clave maestra.
        config(['auth.master_password' => 'Clave-Maestra-Prueba']);

        return 'Clave-Maestra-Prueba';
    }

    public function test_el_registro_pide_la_clave_maestra_primero(): void
    {
        $this->get('/register')->assertRedirect(route('master-password.show', ['redirect' => 'register']));
    }

    public function test_una_clave_maestra_incorrecta_se_rechaza(): void
    {
        $this->post('/master-password', ['master_password' => 'incorrecta', 'redirect' => 'register'])
            ->assertSessionHasErrors('master_password');

        $this->get('/register')->assertRedirect();
    }

    public function test_con_la_clave_maestra_se_muestra_el_registro(): void
    {
        $this->post('/master-password', ['master_password' => $this->claveMaestra(), 'redirect' => 'register'])
            ->assertRedirect('register');

        $this->get('/register')->assertOk();
    }

    public function test_se_puede_registrar_un_usuario_con_rol(): void
    {
        $rol = Role::firstOrCreate(['nombre' => 'Logística']);

        $this->withSession(['master_password_verified' => true])
            ->post('/register', [
                'name'                  => 'Usuario Nuevo',
                'email'                 => 'nuevo@kyrios.test',
                'role_id'               => $rol->id,
                'password'              => 'ClaveSegura#2026',
                'password_confirmation' => 'ClaveSegura#2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nuevo@kyrios.test', 'role_id' => $rol->id, 'estado' => 'activo']);
    }

    public function test_el_registro_exige_un_rol_valido(): void
    {
        $this->withSession(['master_password_verified' => true])
            ->post('/register', [
                'name'                  => 'Sin Rol',
                'email'                 => 'sinrol@kyrios.test',
                'role_id'               => 9999,
                'password'              => 'ClaveSegura#2026',
                'password_confirmation' => 'ClaveSegura#2026',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'sinrol@kyrios.test']);
    }
}
