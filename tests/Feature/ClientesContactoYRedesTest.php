<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Clientes: fecha de registro en la tabla, documento opcional, WhatsApp, varias redes con ícono y edad. */
class ClientesContactoYRedesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Ana Torres', 'estado' => 'activo'], $extra);
    }

    public function test_se_puede_registrar_un_cliente_sin_dni_ni_ruc(): void
    {
        $this->post(route('clientes.store'), $this->datos())->assertSessionHasNoErrors();

        $cliente = Cliente::where('nombre', 'Ana Torres')->sole();
        $this->assertNull($cliente->numero_documento);
        $this->assertNull($cliente->tipo_documento);
    }

    public function test_el_formulario_de_alta_ya_no_preselecciona_dni_ni_lo_exige(): void
    {
        $html = $this->get(route('clientes.create'))->assertOk()->getContent();

        $this->assertStringContainsString("tipoDocumento:   \"\"", str_replace("tipoDocumento:   ''", "tipoDocumento:   \"\"", $html));
        $this->assertStringNotContainsString("tipoDocumento:   \"DNI\"", $html);
        $this->assertStringContainsString('(opcional)', $html);
    }

    public function test_el_alta_rapida_sin_documento_desde_json_funciona(): void
    {
        $this->postJson(route('clientes.store'), $this->datos(['tipo_documento' => null, 'numero_documento' => null, 'telefono' => '999888777']))
            ->assertSuccessful()->assertJsonPath('nombre', 'Ana Torres');
    }

    public function test_guarda_whatsapp_edad_y_varias_redes_sociales(): void
    {
        $this->post(route('clientes.store'), $this->datos([
            'telefono' => '987654321', 'whatsapp' => '+51 987 654 321', 'edad' => 34,
            'redes' => [
                ['red' => 'instagram', 'usuario' => '@ana.arquitecta'],
                ['red' => 'linkedin', 'usuario' => 'ana-torres'],
                ['red' => 'tiktok', 'usuario' => ''],                      // sin usuario: se descarta
                ['red' => 'instagram', 'usuario' => '@ANA.arquitecta'],    // repetida: se descarta
                ['red' => 'web', 'usuario' => 'https://anatorres.pe'],
            ],
        ]))->assertSessionHasNoErrors();

        $c = Cliente::where('nombre', 'Ana Torres')->sole();
        $this->assertSame('+51 987 654 321', $c->whatsapp);
        $this->assertSame(34, $c->edad);
        $this->assertCount(3, $c->redes);
        $this->assertSame(['instagram', 'linkedin', 'web'], array_column($c->redes_lista, 'red'));
        $this->assertSame('https://instagram.com/ana.arquitecta', $c->redes_lista[0]['enlace']);
        $this->assertSame('fab fa-linkedin', $c->redes_lista[1]['icono']);
        $this->assertSame('https://anatorres.pe', $c->redes_lista[2]['enlace']);
    }

    public function test_la_edad_se_calcula_con_la_fecha_de_nacimiento_si_no_se_escribe(): void
    {
        $this->post(route('clientes.store'), $this->datos(['fecha_cumpleanos' => now()->subYears(30)->subDays(2)->toDateString()]))->assertSessionHasNoErrors();

        $this->assertSame(30, Cliente::where('nombre', 'Ana Torres')->value('edad'));
    }

    public function test_valida_whatsapp_edad_y_red_desconocida(): void
    {
        $this->post(route('clientes.store'), $this->datos(['whatsapp' => 'abc', 'edad' => 200, 'redes' => [['red' => 'myspace', 'usuario' => 'x']]]))
            ->assertSessionHasErrors(['whatsapp', 'edad', 'redes.0.red']);
    }

    public function test_el_whatsapp_de_nueve_digitos_se_enlaza_como_numero_peruano(): void
    {
        $this->assertSame('https://wa.me/51987654321', (new Cliente(['whatsapp' => '987 654 321']))->whatsapp_enlace);
        $this->assertSame('https://wa.me/573001234567', (new Cliente(['whatsapp' => '+57 300 1234567']))->whatsapp_enlace);
        $this->assertNull((new Cliente())->whatsapp_enlace);
    }

    public function test_editar_conserva_y_actualiza_las_redes(): void
    {
        $c = Cliente::create($this->datos(['redes' => [['red' => 'facebook', 'usuario' => 'ana.t']]]));

        $this->get(route('clientes.edit', $c))->assertOk()->assertSee('ana.t');
        $this->put(route('clientes.update', $c), $this->datos(['redes' => [['red' => 'youtube', 'usuario' => 'anatorres']]]))->assertSessionHasNoErrors();

        $this->assertSame([['red' => 'youtube', 'usuario' => 'anatorres']], $c->fresh()->redes);
    }

    public function test_la_tabla_muestra_cuando_se_registro_el_cliente_whatsapp_e_iconos_de_redes(): void
    {
        $c = Cliente::create($this->datos(['whatsapp' => '987654321', 'redes' => [['red' => 'instagram', 'usuario' => '@ana'], ['red' => 'tiktok', 'usuario' => 'ana']]]));

        $html = $this->get(route('clientes.index'))->assertOk()->assertSee('Registrado')->getContent();

        $this->assertStringContainsString($c->created_at->format('d/m/Y'), $html);
        $this->assertStringContainsString('fab fa-instagram', $html);
        $this->assertStringContainsString('fab fa-tiktok', $html);
        $this->assertStringContainsString('https://wa.me/51987654321', $html);
    }

    public function test_la_ficha_muestra_edad_whatsapp_redes_y_fecha_de_registro(): void
    {
        $c = Cliente::create($this->datos(['edad' => 41, 'whatsapp' => '987654321', 'redes' => [['red' => 'linkedin', 'usuario' => 'ana-torres']]]));

        $this->get(route('clientes.show', $c))->assertOk()->assertSee('41 años')->assertSee('fab fa-linkedin', false)->assertSee('Registrado');
    }

    public function test_las_pantallas_de_venta_y_cotizacion_no_exigen_documento_al_crear_un_cliente(): void
    {
        $venta = $this->get(route('ventas.create'))->assertOk()->getContent();
        $this->assertStringContainsString(':disabled="!nuevoCliente.nombre || guardandoCliente"', $venta);
        $this->assertStringNotContainsString('!nuevoCliente.numero_documento', $venta);

        $cot = $this->get(route('cotizaciones.create'))->assertOk()->getContent();
        $this->assertStringContainsString('Documento (opcional)', $cot);
    }
}
