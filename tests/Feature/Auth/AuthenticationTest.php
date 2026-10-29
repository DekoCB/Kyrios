<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(?string $rol, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => $rol ? Role::firstOrCreate(['nombre' => $rol])->id : null,
            'estado'  => 'activo',
        ], $extra));
    }

    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_cada_rol_entra_a_su_dashboard(): void
    {
        $destinos = [
            'Administración' => 'admin.dashboard',
            'Contador'       => 'contador.dashboard',
            'Logística'      => 'logistica.dashboard',
            'Operaciones'    => 'operaciones.dashboard',
            'Cliente'        => 'cliente.dashboard',
        ];

        foreach ($destinos as $rol => $ruta) {
            $user = $this->usuario($rol);

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($ruta));
            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_una_clave_incorrecta_no_inicia_sesion(): void
    {
        $user = $this->usuario('Administración');

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_puede_entrar(): void
    {
        $user = $this->usuario('Administración', ['estado' => 'inactivo']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_sin_rol_no_puede_entrar_y_no_da_error(): void
    {
        $user = $this->usuario(null);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_usuario_puede_cerrar_sesion(): void
    {
        $user = $this->usuario('Administración');

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
