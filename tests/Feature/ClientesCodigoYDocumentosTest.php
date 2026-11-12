<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ID de cliente correlativo (KYR001…) y DNI + RUC juntos en el listado. */
class ClientesCodigoYDocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
    }

    public function test_cada_cliente_nuevo_recibe_el_siguiente_id_correlativo(): void
    {
        $a = Cliente::create(['nombre' => 'Uno', 'estado' => 'activo']);
        $b = Cliente::create(['nombre' => 'Dos', 'estado' => 'activo']);
        $this->post(route('clientes.store'), ['nombre' => 'Tres', 'estado' => 'activo']);

        $this->assertSame('KYR001', $a->fresh()->codigo);
        $this->assertSame('KYR002', $b->fresh()->codigo);
        $this->assertSame('KYR003', Cliente::where('nombre', 'Tres')->value('codigo'));
    }

    public function test_el_id_no_cambia_al_editar_y_pasa_de_999(): void
    {
        $c = Cliente::create(['nombre' => 'Uno', 'estado' => 'activo']);
        $c->forceFill(['codigo' => 'KYR999'])->save();
        $this->put(route('clientes.update', $c), ['nombre' => 'Uno editado', 'estado' => 'activo'])->assertSessionHasNoErrors();
        $this->assertSame('KYR999', $c->fresh()->codigo);
        $this->assertSame('KYR1000', Cliente::create(['nombre' => 'Dos', 'estado' => 'activo'])->codigo);
    }

    public function test_el_listado_muestra_id_ruc_y_dni_juntos_y_se_busca_por_id(): void
    {
        $this->post(route('clientes.store'), [
            'nombre' => 'Estudio Rocha', 'estado' => 'activo', 'tipo_documento' => 'RUC', 'numero_documento' => '20547052120', 'dni_complementario' => '41234567',
        ])->assertSessionHasNoErrors();
        Cliente::create(['nombre' => 'Otro Cliente', 'estado' => 'activo']);

        $this->get(route('clientes.index'))->assertOk()->assertSee('KYR001')->assertSee('20547052120')->assertSee('41234567');
        $this->get(route('clientes.index', ['q' => 'KYR001']))->assertSee('Estudio Rocha')->assertDontSee('Otro Cliente');
        $this->get(route('clientes.index', ['q' => '41234567']))->assertSee('Estudio Rocha');
    }

    public function test_el_dni_y_ruc_adicionales_se_validan_y_no_repiten_el_principal(): void
    {
        $this->post(route('clientes.store'), ['nombre' => 'X', 'estado' => 'activo', 'dni_complementario' => '123'])->assertSessionHasErrors('dni_complementario');
        $this->post(route('clientes.store'), ['nombre' => 'Y', 'estado' => 'activo', 'ruc_complementario' => '30123456789'])->assertSessionHasErrors('ruc_complementario');

        $this->post(route('clientes.store'), [
            'nombre' => 'Persona', 'estado' => 'activo', 'tipo_documento' => 'DNI', 'numero_documento' => '41234567',
            'dni_complementario' => '99999999', 'ruc_complementario' => '10412345678',
        ])->assertSessionHasNoErrors();
        $c = Cliente::where('nombre', 'Persona')->first();
        $this->assertNull($c->dni_complementario);
        $this->assertSame('41234567', $c->dni);
        $this->assertSame('10412345678', $c->ruc);
    }

    public function test_exportacion_y_ficha_incluyen_el_id(): void
    {
        $c = Cliente::create(['nombre' => 'Ana', 'estado' => 'activo', 'tipo_documento' => 'DNI', 'numero_documento' => '41234567', 'ruc_complementario' => '10412345678']);
        $csv = $this->get(route('clientes.exportar'))->streamedContent();
        $this->assertStringContainsString('ID cliente', $csv);
        $this->assertStringContainsString('KYR001', $csv);
        $this->get(route('clientes.show', $c))->assertOk()->assertSee('KYR001')->assertSee('10412345678');
        $this->get(route('clientes.edit', $c))->assertOk()->assertSee('KYR001');
    }

    public function test_el_listado_se_ordena_por_id_y_se_puede_invertir(): void
    {
        foreach (['Primero', 'Segundo', 'Tercero'] as $n) {
            Cliente::create(['nombre' => $n, 'estado' => 'activo']);
        }
        // Por defecto, del más reciente al más antiguo
        $this->get(route('clientes.index'))->assertOk()->assertSeeInOrder(['Tercero', 'Segundo', 'Primero']);
        $this->get(route('clientes.index', ['orden' => 'antiguo']))->assertSeeInOrder(['Primero', 'Segundo', 'Tercero']);
        // El orden numérico: KYR10 va después de KYR9, no de KYR1
        for ($i = 4; $i <= 10; $i++) {
            Cliente::create(['nombre' => "Cliente $i", 'estado' => 'activo']);
        }
        $this->get(route('clientes.index', ['orden' => 'antiguo']))->assertSeeInOrder(['KYR009', 'KYR010']);
    }
}
