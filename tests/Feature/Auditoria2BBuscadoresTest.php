<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buscadores de destinatario y consulta de RUC: nunca responden 500.
 */
class Auditoria2BBuscadoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'Cesar Lee Polick', 'estado' => 'activo']);
        Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Iluminacion Import SAC', 'estado' => 'activo']);
    }

    public function test_traslados_busca_destinatarios_cliente_y_proveedor(): void
    {
        $this->getJson(route('traslados.buscar-destinatario', ['tipo' => 'cliente', 'buscar' => 'Cesar']))
            ->assertOk()->assertJsonPath('0.nombre', 'Cesar Lee Polick')->assertJsonPath('0.documento', '41340629');
        $this->getJson(route('traslados.buscar-destinatario', ['tipo' => 'cliente', 'buscar' => '4134']))->assertOk()->assertJsonCount(1);
        $this->getJson(route('traslados.buscar-destinatario', ['tipo' => 'proveedor', 'buscar' => 'Iluminacion']))
            ->assertOk()->assertJsonPath('0.nombre', 'Iluminacion Import SAC');
    }

    public function test_guias_busca_destinatarios_cliente_y_proveedor(): void
    {
        $this->getJson(route('guias-remision.buscar-destinatario', ['tipo' => 'cliente', 'buscar' => 'Cesar']))->assertOk()->assertJsonCount(1);
        $this->getJson(route('guias-remision.buscar-destinatario', ['tipo' => 'proveedor', 'buscar' => '2055']))->assertOk()->assertJsonCount(1);
    }

    public function test_consultar_ruc_de_proveedor_sin_ruc_no_da_500(): void
    {
        $this->postJson(route('proveedores.consultar-sunat'), [])->assertOk()->assertJsonPath('success', false);
        $this->postJson(route('proveedores.consultar-sunat'), ['ruc' => ['x']])->assertOk()->assertJsonPath('success', false);
        $this->postJson(route('proveedores.consultar-sunat'), ['ruc' => '123'])->assertOk()->assertJsonPath('success', false);
    }
}
