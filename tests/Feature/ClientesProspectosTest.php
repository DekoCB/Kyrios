<?php

namespace Tests\Feature;

use App\Console\Commands\VerificarClientes;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prospectos (sin documento), datos comerciales, importación y verificación SUNAT/RENIEC.
 */
class ClientesProspectosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));
    }

    private function alta(array $extra = [])
    {
        return $this->post(route('clientes.store'), array_merge(['nombre' => 'Sandra Gonzalez', 'estado' => 'activo'], $extra));
    }

    public function test_se_registra_un_prospecto_sin_documento(): void
    {
        $this->alta(['telefono' => '994198198', 'segmento' => 'DIS'])->assertSessionHasNoErrors();

        $c = Cliente::first();
        $this->assertNull($c->numero_documento);
        $this->assertTrue($c->es_prospecto);
        $this->get(route('clientes.show', $c))->assertOk()->assertSee('Prospecto');
    }

    public function test_varios_prospectos_conviven_pero_el_documento_sigue_siendo_unico(): void
    {
        $this->alta()->assertSessionHasNoErrors();
        $this->alta(['nombre' => 'Otra Persona'])->assertSessionHasNoErrors();
        $this->assertSame(2, Cliente::prospectos()->count());

        $this->alta(['nombre' => 'Con DNI', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678'])->assertSessionHasNoErrors();
        $this->alta(['nombre' => 'Repetido', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678'])->assertSessionHasErrors('numero_documento');
    }

    public function test_tipo_y_numero_van_juntos(): void
    {
        $this->alta(['tipo_documento' => 'DNI'])->assertSessionHasErrors('numero_documento');
        $this->alta(['numero_documento' => '12345678'])->assertSessionHasErrors('tipo_documento');
    }

    public function test_un_prospecto_se_completa_al_editar(): void
    {
        $this->alta();
        $c = Cliente::first();

        $this->put(route('clientes.update', $c), ['nombre' => 'Sandra Gonzalez', 'estado' => 'activo', 'tipo_documento' => 'DNI', 'numero_documento' => '87654321'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($c->fresh()->es_prospecto);
        $this->assertSame(0, Cliente::prospectos()->count());
    }

    public function test_datos_comerciales_se_guardan_y_se_muestran(): void
    {
        $this->alta([
            'segmento' => 'ARQ', 'especialidad' => 'Residencial', 'cargo' => 'Gerente', 'empresa' => 'Estudio Rocha',
            'fecha_cumpleanos' => '1988-08-21', 'comision' => '10', 'asesor' => 'Wendy', 'origen' => 'Showroom',
            'telefono_fijo' => '6830567', 'anexo' => '12', 'email_empresa' => 'info@rocha.pe', 'preferencias' => 'Negro mate',
        ])->assertSessionHasNoErrors();

        $c = Cliente::first();
        $this->assertSame('21/08', $c->fecha_cumpleanos->format('d/m'));
        $this->assertSame('10.00', $c->comision);

        $this->get(route('clientes.show', $c))->assertOk()
            ->assertSee('Arquitecto')->assertSee('Estudio Rocha')->assertSee('Negro mate')->assertSee('21/08')->assertSee('Wendy');
        $this->get(route('clientes.edit', $c))->assertOk()->assertSee('Negro mate');
    }

    public function test_comision_fuera_de_rango_se_rechaza(): void
    {
        $this->alta(['comision' => '150'])->assertSessionHasErrors('comision');
    }

    public function test_listado_filtra_prospectos_y_segmento_y_cuenta(): void
    {
        $this->alta(['nombre' => 'Prospecto Uno', 'segmento' => 'ARQ']);
        $this->alta(['nombre' => 'Cliente Dos', 'tipo_documento' => 'DNI', 'numero_documento' => '11112222', 'segmento' => 'PN']);

        $this->get(route('clientes.index', ['tipo_documento' => 'SIN']))->assertOk()->assertSee('Prospecto Uno')->assertDontSee('Cliente Dos');
        $this->get(route('clientes.index', ['segmento' => 'PN']))->assertOk()->assertSee('Cliente Dos')->assertDontSee('Prospecto Uno');
        $this->get(route('clientes.index'))->assertOk()->assertSee('Prospectos (sin documento)');
    }

    public function test_exportar_incluye_prospectos(): void
    {
        $this->alta(['nombre' => 'Prospecto Uno']);

        $csv = $this->get(route('clientes.exportar'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Prospecto Uno', $csv);
        $this->assertStringContainsString('SIN DOC.', $csv);
    }

    public function test_comando_importar_crea_clientes_y_prospectos_sin_duplicar(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cli');
        file_put_contents($ruta, json_encode(['clientes' => [
            ['tipo_documento' => 'RUC', 'numero_documento' => '20511879621', 'nombre' => 'GDK S.A.C', 'segmento' => 'ARQ', 'notas' => null],
            ['tipo_documento' => null, 'numero_documento' => null, 'nombre' => 'Carla Rocha', 'telefono' => '994082466', 'comision' => 10, 'created_at' => '2014-06-17 00:00:00'],
        ]]));

        $this->artisan('clientes:importar', ['archivo' => $ruta])->assertSuccessful();
        $this->artisan('clientes:importar', ['archivo' => $ruta])->assertSuccessful();

        $this->assertSame(2, Cliente::count());
        $this->assertSame(1, Cliente::prospectos()->count());
        $this->assertSame('2014', Cliente::where('nombre', 'Carla Rocha')->first()->created_at->format('Y'));
        unlink($ruta);
    }

    public function test_verificar_adopta_el_nombre_oficial_y_marca_discrepancias(): void
    {
        Http::fake([
            'api.apis.net.pe/v1/dni*' => Http::sequence()
                ->push(['numeroDocumento' => '41340629', 'nombre' => 'LEE POLICK CESAR ALEJANDRO'])
                ->push(['numeroDocumento' => '11112222', 'nombre' => 'MARIA TORRES VEGA']),
            'api.apis.net.pe/v1/ruc*' => Http::response(['message' => 'not found'], 404),
        ]);
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'Cesar Lee', 'estado' => 'activo']);
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '11112222', 'nombre' => 'Pedro Quispe', 'estado' => 'activo']);
        Cliente::create(['tipo_documento' => 'RUC', 'numero_documento' => '20123456786', 'nombre' => 'Empresa X', 'estado' => 'activo']);

        $this->artisan('clientes:verificar', ['--pausa' => 0, '--espera' => 0])->assertSuccessful();

        $ok = Cliente::where('numero_documento', '41340629')->first();
        $this->assertSame('ok', $ok->verificacion);
        $this->assertSame('LEE POLICK CESAR ALEJANDRO', $ok->nombre);

        $dif = Cliente::where('numero_documento', '11112222')->first();
        $this->assertSame('discrepancia', $dif->verificacion);
        $this->assertSame('Pedro Quispe', $dif->nombre, 'no se pisa el nombre cuando no coincide');
        $this->assertStringContainsString('MARIA TORRES VEGA', $dif->notas);

        $this->assertSame('no_encontrado', Cliente::where('numero_documento', '20123456786')->first()->verificacion);
    }

    public function test_un_limite_de_la_api_no_marca_como_no_encontrado(): void
    {
        Http::fake(['api.apis.net.pe/*' => Http::response('', 429)]);
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'Cesar Lee', 'estado' => 'activo']);

        $this->artisan('clientes:verificar', ['--pausa' => 0, '--espera' => 0])->assertSuccessful();

        $this->assertNull(Cliente::first()->verificacion, 'queda pendiente para otra corrida');
    }

    public function test_dni_de_siete_digitos_inexistente_vuelve_a_prospecto(): void
    {
        Http::fake(['api.apis.net.pe/v1/dni*' => Http::response(['message' => 'not found'], 404)]);
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombre' => 'Ana', 'estado' => 'activo',
            'notas' => 'DNI de 7 dígitos en el archivo; se completó con un 0 inicial (verificar)']);

        $this->artisan('clientes:verificar', ['--pausa' => 0, '--espera' => 0])->assertSuccessful();

        $this->assertTrue(Cliente::first()->es_prospecto);
    }

    public function test_comparacion_de_nombres(): void
    {
        $this->assertTrue(VerificarClientes::nombresParecidos('GDK S.A.C', 'GDK S.A.C.'));
        $this->assertTrue(VerificarClientes::nombresParecidos('Cesar Lee', 'LEE POLICK CESAR ALEJANDRO'));
        $this->assertFalse(VerificarClientes::nombresParecidos('Pedro Quispe', 'MARIA TORRES VEGA'));
    }
}
