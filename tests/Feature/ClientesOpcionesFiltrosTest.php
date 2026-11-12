<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OpcionCliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Filtros de clientes con listas de opciones que el administrador puede editar. */
class ClientesOpcionesFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
    }

    private function alta(array $extra = [])
    {
        return $this->post(route('clientes.store'), array_merge(['nombre' => 'Cliente Prueba', 'estado' => 'activo'], $extra));
    }

    public function test_las_listas_del_excel_vienen_cargadas(): void
    {
        $this->assertSame(['GOLD', 'VIP', 'FRECUENTE', 'OCASIONAL', 'NUEVO', 'RECUPERADO'], OpcionCliente::nombres('clasificacion'));
        $this->assertContains('Publicidad digital', OpcionCliente::nombres('como_ingreso'));
        $this->assertContains('Llamada Telf.', OpcionCliente::nombres('preferencia_contacto'));
        $this->assertCount(10, OpcionCliente::nombres('especialidad'));
        $this->assertContains('Del Estado', OpcionCliente::nombres('tipo_empresa'));
    }

    public function test_se_guarda_un_cliente_con_las_opciones_y_se_valida_la_lista(): void
    {
        $this->alta(['clasificacion' => 'GOLD', 'como_ingreso' => 'Referido', 'preferencia_contacto' => 'Whatsapp', 'especialidad' => 'Hoteles', 'tipo_empresa' => 'Estudio'])
            ->assertSessionHasNoErrors();
        $c = Cliente::first();
        $this->assertSame(['GOLD', 'Referido', 'Whatsapp', 'Hoteles', 'Estudio'], [$c->clasificacion, $c->como_ingreso, $c->preferencia_contacto, $c->especialidad, $c->tipo_empresa]);

        $this->alta(['nombre' => 'Otro', 'clasificacion' => 'PLATINO'])->assertSessionHasErrors('clasificacion');
    }

    public function test_el_listado_filtra_por_cada_lista_y_por_sin_dato(): void
    {
        Cliente::create(['nombre' => 'Ana Oro', 'estado' => 'activo', 'clasificacion' => 'GOLD', 'tipo_empresa' => 'Constructora']);
        Cliente::create(['nombre' => 'Beto Nuevo', 'estado' => 'activo', 'clasificacion' => 'NUEVO']);
        Cliente::create(['nombre' => 'Carla Sin', 'estado' => 'activo']);

        $this->get(route('clientes.index', ['clasificacion' => 'GOLD']))->assertSee('Ana Oro')->assertDontSee('Beto Nuevo')->assertDontSee('Carla Sin');
        $this->get(route('clientes.index', ['tipo_empresa' => 'Constructora']))->assertSee('Ana Oro')->assertDontSee('Beto Nuevo');
        $this->get(route('clientes.index', ['clasificacion' => '__sin']))->assertSee('Carla Sin')->assertDontSee('Ana Oro');
    }

    public function test_el_admin_agrega_renombra_y_desactiva_opciones(): void
    {
        $this->post(route('clientes.opciones.store'), ['grupo' => 'clasificacion', 'nombre' => 'PLATINO'])->assertSessionHasNoErrors();
        $this->assertContains('PLATINO', OpcionCliente::nombres('clasificacion'));
        $this->post(route('clientes.opciones.store'), ['grupo' => 'clasificacion', 'nombre' => 'PLATINO'])->assertSessionHasErrors('nombre');

        // Renombrar actualiza a los clientes que la tenían
        $cliente = Cliente::create(['nombre' => 'Ana', 'estado' => 'activo', 'clasificacion' => 'GOLD']);
        $oro = OpcionCliente::where(['grupo' => 'clasificacion', 'nombre' => 'GOLD'])->first();
        $this->put(route('clientes.opciones.update', $oro), ['nombre' => 'ORO'])->assertSessionHasNoErrors();
        $this->assertSame('ORO', $cliente->fresh()->clasificacion);

        // Desactivada ya no se ofrece, pero el cliente que la tiene conserva el dato y puede editarse
        $this->put(route('clientes.opciones.update', $oro), ['nombre' => 'ORO', 'activo' => 0]);
        $this->assertNotContains('ORO', OpcionCliente::nombres('clasificacion'));
        $this->put(route('clientes.update', $cliente), ['nombre' => 'Ana', 'estado' => 'activo', 'clasificacion' => 'ORO'])->assertSessionHasNoErrors();
        $this->get(route('clientes.edit', $cliente))->assertOk()->assertSee('ORO');
    }

    public function test_una_opcion_en_uso_no_se_elimina_pero_una_libre_si(): void
    {
        Cliente::create(['nombre' => 'Ana', 'estado' => 'activo', 'clasificacion' => 'VIP']);
        $vip = OpcionCliente::where(['grupo' => 'clasificacion', 'nombre' => 'VIP'])->first();
        $this->delete(route('clientes.opciones.destroy', $vip))->assertSessionHas('error');
        $this->assertDatabaseHas('opciones_cliente', ['id' => $vip->id]);

        $libre = OpcionCliente::where(['grupo' => 'clasificacion', 'nombre' => 'RECUPERADO'])->first();
        $this->delete(route('clientes.opciones.destroy', $libre))->assertSessionHas('success');
        $this->assertDatabaseMissing('opciones_cliente', ['id' => $libre->id]);
    }

    public function test_la_pantalla_de_opciones_es_solo_del_administrador(): void
    {
        $this->get(route('clientes.opciones.index'))->assertOk()->assertSee('Clasificación')->assertSee('Tipo de empresa');

        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Contador'])->id, 'estado' => 'activo']));
        $this->get(route('clientes.opciones.index'))->assertForbidden();
    }

    public function test_la_exportacion_incluye_las_columnas_nuevas(): void
    {
        Cliente::create(['nombre' => 'Ana', 'estado' => 'activo', 'clasificacion' => 'GOLD', 'como_ingreso' => 'Evento']);
        $csv = $this->get(route('clientes.exportar', ['clasificacion' => 'GOLD']))->streamedContent();
        $this->assertStringContainsString('Clasificación', $csv);
        $this->assertStringContainsString('GOLD', $csv);
        $this->assertStringContainsString('Evento', $csv);
    }
}
