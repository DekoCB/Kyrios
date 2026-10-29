<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_perfil_carga_con_sus_datos(): void
    {
        $user = User::factory()->create(['role_id' => \App\Models\Role::firstOrCreate(['nombre' => 'Administración'])->id]);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->email)->assertSee('Cambiar contraseña');
    }

    public function test_se_cambia_la_contrasena_con_la_actual_correcta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'mala', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'password', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'password', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NuevaClave123', $user->fresh()->password));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
