<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los valores que las vistas pasan a Alpine deben ser texto, no null: el JS llama a .length sobre ellos.
 */
class VistasSinNullEnJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_sin_datos_no_pasa_null_al_formulario(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));
        Empresa::query()->delete();

        $html = $this->get(route('admin.empresa.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\bruc:\s+""/', $html);
        $this->assertDoesNotMatchRegularExpression('/\bruc:\s+null/', $html);
    }

    public function test_alta_de_cliente_no_pasa_null_al_formulario(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));

        $html = $this->get(route('clientes.create'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\b(nombre|direccion|ubigeo|numeroDocumento):\s+null/', $html);
    }
}
