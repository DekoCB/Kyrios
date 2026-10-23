<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El cambio de contraseña olvidada es directo (sin correo) y exige la contraseña maestra.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_clave_maestra_se_redirige_a_pedirla(): void
    {
        $this->get('/forgot-password')->assertRedirect();
    }

    public function test_con_clave_maestra_se_muestra_el_formulario(): void
    {
        $this->withSession(['master_password_verified' => true])->get('/forgot-password')->assertOk();
    }

    public function test_la_contrasena_se_cambia_y_se_olvida_la_verificacion_maestra(): void
    {
        $user = User::factory()->create();

        $this->withSession(['master_password_verified' => true])
            ->post('/forgot-password', [
                'email'                 => $user->email,
                'password'              => 'NuevaClave#2026',
                'password_confirmation' => 'NuevaClave#2026',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('master_password_verified');

        $this->assertTrue(Hash::check('NuevaClave#2026', $user->fresh()->password));
    }

    public function test_un_correo_inexistente_se_rechaza(): void
    {
        $this->withSession(['master_password_verified' => true])
            ->post('/forgot-password', [
                'email'                 => 'nadie@kyrios.test',
                'password'              => 'NuevaClave#2026',
                'password_confirmation' => 'NuevaClave#2026',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_las_contrasenas_deben_coincidir(): void
    {
        $user = User::factory()->create();

        $this->withSession(['master_password_verified' => true])
            ->post('/forgot-password', [
                'email'                 => $user->email,
                'password'              => 'NuevaClave#2026',
                'password_confirmation' => 'otra-distinta',
            ])
            ->assertSessionHasErrors('password');

        $this->assertFalse(Hash::check('NuevaClave#2026', $user->fresh()->password));
    }
}
