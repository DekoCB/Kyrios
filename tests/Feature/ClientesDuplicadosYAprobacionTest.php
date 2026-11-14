<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aviso de cliente duplicado al registrar y eliminación de clientes con aprobación. */
class ClientesDuplicadosYAprobacionTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    public function test_avisa_si_ya_existe_un_cliente_con_el_mismo_nombre_o_documento(): void
    {
        $this->como('Administración');
        $c = Cliente::create(['nombre' => 'Arq.  Lucía Mendoza', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'telefono' => '999111222', 'estado' => 'activo']);

        $porNombre = $this->getJson(route('clientes.duplicados', ['nombre' => 'arq. lucía mendoza']))->assertOk()->json('coincidencias');
        $this->assertCount(1, $porNombre);
        $this->assertSame(['nombre'], $porNombre[0]['motivos']);
        $this->assertSame('999111222', $porNombre[0]['datos']['telefono']);

        $porDni = $this->getJson(route('clientes.duplicados', ['documento' => '12345678']))->json('coincidencias');
        $this->assertSame($c->id, $porDni[0]['id']);
        $this->assertContains('DNI', $porDni[0]['motivos']);

        $this->assertSame([], $this->getJson(route('clientes.duplicados', ['nombre' => 'Otra persona', 'documento' => '87654321']))->json('coincidencias'));
    }

    public function test_el_ruc_complementario_tambien_cuenta_como_duplicado(): void
    {
        $this->como('Administración');
        $c = Cliente::create(['nombre' => 'Persona Natural', 'tipo_documento' => 'DNI', 'numero_documento' => '11112222', 'ruc_complementario' => '10111122223', 'estado' => 'activo']);

        $r = $this->getJson(route('clientes.duplicados', ['documento' => '10111122223']))->json('coincidencias');
        $this->assertSame($c->id, $r[0]['id']);
        $this->assertContains('RUC', $r[0]['motivos']);
    }

    public function test_la_pantalla_de_alta_incluye_el_aviso_de_duplicado(): void
    {
        $this->como('Administración');
        $this->get(route('clientes.create'))->assertOk()->assertSee('revisarDuplicados', false)->assertSee('Autocompletar con sus datos');
    }

    public function test_administracion_elimina_directo_y_el_contador_solicita(): void
    {
        $libre = Cliente::create(['nombre' => 'Libre', 'estado' => 'activo']);
        $this->como('Administración');
        $this->delete(route('clientes.destroy', $libre))->assertRedirect(route('clientes.index'));
        $this->assertNull(Cliente::find($libre->id));

        $otro = Cliente::create(['nombre' => 'Otro cliente', 'estado' => 'activo']);
        $this->como('Contador');
        $this->delete(route('clientes.destroy', $otro))->assertRedirect(route('aprobaciones.mias'));
        $this->assertNotNull(Cliente::find($otro->id));
        $a = Aprobacion::deClientes()->firstOrFail();
        $this->assertSame('cliente_eliminacion', $a->accion);
        $this->assertStringContainsString('Otro cliente', $a->titulo);
    }

    public function test_las_bandejas_separan_productos_y_clientes_y_aprobar_elimina_al_cliente(): void
    {
        $cliente = Cliente::create(['nombre' => 'Para borrar', 'estado' => 'activo']);
        $contador = $this->como('Contador');
        $this->delete(route('clientes.destroy', $cliente));
        $a = Aprobacion::deClientes()->firstOrFail();
        Aprobacion::create(['accion' => 'producto_nuevo', 'ruta' => 'productos.store', 'titulo' => 'Producto nuevo: X', 'solicitante_id' => $contador->id]);

        $this->como('Administración');
        $this->get(route('aprobaciones.clientes'))->assertOk()->assertSee('Eliminar cliente: Para borrar')->assertDontSee('Producto nuevo: X');
        $this->get(route('aprobaciones.index'))->assertOk()->assertSee('Producto nuevo: X')->assertDontSee('Eliminar cliente: Para borrar');

        $this->post(route('aprobaciones.aprobar', $a))->assertRedirect(route('aprobaciones.clientes'))->assertSessionHas('success');
        $this->assertNull(Cliente::find($cliente->id));
        $this->assertSame('aprobada', $a->fresh()->estado);
    }
}
