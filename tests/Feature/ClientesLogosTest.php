<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo de clientes, menú por rol y logos oficiales.
 */
class ClientesLogosTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Pérez',
            'telefono' => '999111222', 'email' => 'juan@correo.com', 'contacto' => 'Ana', 'notas' => 'Paga a 15 días', 'estado' => 'activo',
        ], $extra);
    }

    public function test_alta_con_todos_los_datos_y_ficha(): void
    {
        $this->como('Administración');

        $this->post(route('clientes.store'), $this->datos())->assertSessionHasNoErrors();

        $c = Cliente::first();
        $this->assertSame('Paga a 15 días', $c->notas);
        $this->get(route('clientes.show', $c))->assertOk()->assertSee('Juan Pérez')->assertSee('Paga a 15 días');
    }

    public function test_el_formato_del_documento_depende_del_tipo(): void
    {
        $this->como('Administración');

        $this->post(route('clientes.store'), $this->datos(['numero_documento' => '123']))->assertSessionHasErrors('numero_documento');
        $this->post(route('clientes.store'), $this->datos(['tipo_documento' => 'RUC', 'numero_documento' => '12345678901']))->assertSessionHasErrors('numero_documento');
        $this->post(route('clientes.store'), $this->datos(['tipo_documento' => 'RUC', 'numero_documento' => '20123456789']))->assertSessionHasNoErrors();
        $this->post(route('clientes.store'), $this->datos(['tipo_documento' => 'CE', 'numero_documento' => 'AB1234']))->assertSessionHasNoErrors();
    }

    public function test_no_se_repite_el_documento(): void
    {
        $this->como('Administración');
        $this->post(route('clientes.store'), $this->datos());

        $this->post(route('clientes.store'), $this->datos(['nombre' => 'Otro']))->assertSessionHasErrors('numero_documento');
        $this->assertSame(1, Cliente::count());
    }

    public function test_edicion_y_busqueda(): void
    {
        $this->como('Administración');
        $c = Cliente::create($this->datos());
        Cliente::create($this->datos(['numero_documento' => '87654321', 'nombre' => 'María López', 'email' => null, 'telefono' => null, 'contacto' => null]));

        $this->put(route('clientes.update', $c), $this->datos(['nombre' => 'Juan P. Actualizado']))->assertSessionHasNoErrors();
        $this->assertSame('Juan P. Actualizado', $c->fresh()->nombre);

        $total = fn (array $f) => $this->get(route('clientes.index', $f))->assertOk()->viewData('clientes')->total();
        $this->assertSame(1, $total(['q' => 'María']));
        $this->assertSame(1, $total(['q' => '999111222']));
        $this->assertSame(1, $total(['q' => 'Ana']), 'busca también por contacto');
        $this->assertSame(2, $total([]));
    }

    public function test_no_se_elimina_un_cliente_con_historial(): void
    {
        $this->como('Administración');
        $libre = Cliente::create($this->datos());
        $this->delete(route('clientes.destroy', $libre))->assertRedirect(route('clientes.index'));
        $this->assertNull(Cliente::find($libre->id));
    }

    public function test_exportar_csv(): void
    {
        $this->como('Administración');
        Cliente::create($this->datos());

        $r = $this->get(route('clientes.exportar'))->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $csv = $r->streamedContent();
        $this->assertStringContainsString('Juan Pérez', $csv);
        $this->assertStringContainsString('12345678', $csv);
    }

    public function test_roles_con_acceso_a_clientes(): void
    {
        foreach (['Administración', 'Administración', 'Administración'] as $rol) {
            $this->como($rol);
            $this->get(route('clientes.index'))->assertOk();
        }
        $this->como('Logística');
        $this->get(route('clientes.index'))->assertForbidden();
    }

    public function test_solo_administracion_elimina(): void
    {
        $c = Cliente::create($this->datos());
        $this->como('Contador');
        $this->delete(route('clientes.destroy', $c))->assertForbidden();
        $this->assertNotNull(Cliente::find($c->id));
    }

    public function test_el_menu_de_cada_rol_compila_y_muestra_cotizaciones_y_clientes(): void
    {
        foreach (['Administración', 'Administración', 'Administración', 'Administración'] as $rol) {
            $this->como($rol);
            $this->get(route('clientes.index'))->assertOk()->assertSee('Cotizaciones')->assertSee('Clientes');
        }
        foreach (['Logística', 'Cliente'] as $rol) {
            $this->como($rol);
            $this->assertLessThan(500, $this->get(route('profile.edit'))->getStatusCode());
        }
    }

    // ── Logos ─────────────────────────────────────────────────────────────

    public function test_los_logos_oficiales_existen_en_public_img(): void
    {
        $this->assertFileExists(public_path(Empresa::LOGO_FONDO_CLARO));
        $this->assertFileExists(public_path(Empresa::LOGO_FONDO_OSCURO));
    }

    public function test_sin_logo_cargado_se_usan_los_oficiales(): void
    {
        $empresa = Empresa::create(['ruc' => '20123456789', 'razon_social' => 'KYRIOS S.A.C.']);

        $this->assertStringContainsString(Empresa::LOGO_FONDO_OSCURO, $empresa->logo_url);
        $this->assertStringContainsString(Empresa::LOGO_FONDO_CLARO, $empresa->logo_pdf_url);
        $this->assertStringStartsWith('data:image/png;base64,', Empresa::logoDocumentoDataUri($empresa));
        $this->assertStringContainsString(Empresa::LOGO_FONDO_CLARO, Empresa::logoUrlPara('claro'));
        $this->assertStringContainsString(Empresa::LOGO_FONDO_OSCURO, Empresa::logoUrlPara('oscuro'));
    }

    public function test_el_logo_aparece_en_la_pantalla_de_acceso(): void
    {
        $this->get('/login')->assertOk()->assertSee('logo-kyrios');
    }
}
