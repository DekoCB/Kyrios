<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Role;

/**
 * Reportado: un usuario con rol Tienda podía entrar a Traslados Pendientes
 * pero le salía 403 al usar los enlaces de esa misma página (Historial,
 * Stock por Almacén, Nuevo Traslado) porque esas rutas solo dejaban pasar a
 * Administrador/Almacenero. Se decidió darle a Tienda el mismo acceso que
 * Almacenero para crear/ver traslados y su guía de remisión asociada (el
 * flujo de "Nuevo Traslado" termina ahí), sin extender a acciones más
 * sensibles (anular, envío/consulta SUNAT), que siguen restringidas.
 */
class TrasladosTiendaAccessTest extends TestCase
{
    private array $createdUsers = [];

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        User::whereIn('id', $this->createdUsers)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->createdUsers = [];
        parent::tearDown();
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['nombre' => $roleName]);
        $uid  = uniqid();
        $user = User::create([
            'name'     => "{$roleName} Test {$uid}",
            'email'    => strtolower($roleName) . "_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
        $this->createdUsers[] = $user->id;
        return $user;
    }

    public function test_tienda_puede_ver_historial_stock_y_crear_traslado(): void
    {
        $tienda = $this->makeUser('Tienda');

        $this->actingAs($tienda)->get(route('traslados.index'))->assertOk();
        $this->actingAs($tienda)->get(route('traslados.stock'))->assertOk();
        $this->actingAs($tienda)->get(route('traslados.create'))->assertOk();
        $this->actingAs($tienda)->get(route('traslados.pendientes'))->assertOk();
    }

    public function test_tienda_puede_crear_guia_de_remision_para_el_traslado(): void
    {
        $tienda = $this->makeUser('Tienda');

        $this->actingAs($tienda)->get(route('guias-remision.create'))->assertOk();
    }

    public function test_tienda_no_tiene_permiso_de_rol_para_anular_ni_para_acciones_sunat(): void
    {
        // Verificado a nivel de definición de ruta (no HTTP): con un ID
        // inexistente, el route-model-binding de Laravel devuelve 404 antes
        // de que corra el middleware de rol, así que una prueba HTTP no
        // distinguiría "sin permiso" de "no existe". Esto confirma
        // directamente qué roles acepta cada ruta.
        $rolesPermitidos = fn (string $routeName) => collect(\Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName)->middleware())
            ->first(fn ($m) => str_starts_with($m, 'role:'));

        $this->assertStringNotContainsString('Tienda', $rolesPermitidos('traslados.anular'));
        $this->assertStringNotContainsString('Tienda', $rolesPermitidos('guias-remision.enviar-sunat'));
        $this->assertStringNotContainsString('Tienda', $rolesPermitidos('guias-remision.update-estado'));
        $this->assertStringNotContainsString('Tienda', $rolesPermitidos('guias-remision.consultar-sunat'));
    }

    public function test_almacenero_conserva_acceso_completo(): void
    {
        $almacenero = $this->makeUser('Almacenero');

        $this->actingAs($almacenero)->get(route('traslados.index'))->assertOk();
        $this->actingAs($almacenero)->get(route('traslados.create'))->assertOk();
        $this->actingAs($almacenero)->get(route('guias-remision.create'))->assertOk();
    }
}
