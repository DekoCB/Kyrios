<?php

namespace Tests\Feature;

use App\Models\CajaChica;
use App\Models\CajaChicaMovimiento;
use App\Models\Role;
use App\Models\User;
use App\Services\CajaChicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Aprobar toda la caja, repartir un comprobante en varios conceptos y recibos de pago. */
class CajaChicaAprobacionRepartoRecibosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(CajaChicaService::DISCO);
        $this->admin = $this->usuario('Administración');
        $this->tecnico = $this->usuario('Operaciones');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function caja(float $monto = 500): CajaChica
    {
        return app(CajaChicaService::class)->abrir(['tipo' => 'asignada', 'nombre' => 'Caja técnico', 'responsable_id' => $this->tecnico->id, 'monto_inicial' => $monto], $this->admin);
    }

    private function gasto(array $extra = []): array
    {
        return array_merge([
            'fecha' => today()->toDateString(), 'monto' => 30, 'categoria' => 'combustible', 'concepto' => 'Gasolina',
            'emisor' => 'Grifo Sol', 'tipo_comprobante' => 'boleta', 'numero_comprobante' => 'B001-55',
            'adjuntos' => [UploadedFile::fake()->image('foto.jpg', 800, 600)],
        ], $extra);
    }

    public function test_aprobar_toda_la_caja_de_golpe(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-1']))->assertSessionHasNoErrors();
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-2', 'monto' => 20]))->assertSessionHasNoErrors();
        $this->assertSame(2, $caja->fresh()->por_revisar);

        $this->post(route('caja-chica.aprobar-todo', $caja))->assertForbidden();

        $this->actingAs($this->admin);
        $this->post(route('caja-chica.aprobar-todo', $caja))->assertSessionHas('success');
        $this->assertSame(0, $caja->fresh()->por_revisar);
        $this->assertSame(2, CajaChicaMovimiento::where('tipo', 'gasto')->where('estado', 'aprobado')->where('revisado_por', $this->admin->id)->count());

        $this->post(route('caja-chica.aprobar-todo', $caja))->assertSessionHas('error');
    }

    public function test_un_comprobante_se_reparte_en_varios_conceptos_hasta_el_monto_total(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);

        $base = $this->gasto(['monto' => 100, 'concepto' => null]);
        $this->post(route('caja-chica.gasto', $caja), $base + ['conceptos' => [
            ['concepto' => 'Cable', 'monto' => 60, 'categoria' => 'materiales'],
            ['concepto' => 'Cinta', 'monto' => 30],
        ]])->assertSessionHas('error');
        $this->assertSame(0, CajaChicaMovimiento::where('tipo', 'gasto')->count(), 'si no cuadra no se registra nada');

        $this->post(route('caja-chica.gasto', $caja), $base + ['conceptos' => [
            ['concepto' => 'Cable', 'monto' => 60, 'categoria' => 'materiales'],
            ['concepto' => 'Cinta', 'monto' => 40],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $gastos = CajaChicaMovimiento::where('tipo', 'gasto')->orderBy('id')->get();
        $this->assertCount(2, $gastos);
        $this->assertSame($gastos[0]->grupo, $gastos[1]->grupo);
        $this->assertNotNull($gastos[0]->grupo);
        $this->assertSame('materiales', $gastos[0]->categoria);
        $this->assertSame('combustible', $gastos[1]->categoria, 'sin categoría propia hereda la del comprobante');
        $this->assertEquals(100.0, (float) $gastos->sum('monto'));
        $this->assertSame(1, $gastos[0]->adjuntos()->count());
        $this->assertSame(0, $gastos[1]->adjuntos()->count());
        $this->assertEquals(400.0, $caja->fresh()->saldo);

        // La caja muestra los dos conceptos y el aviso del comprobante compartido
        $this->get(route('caja-chica.show', $caja))->assertOk()->assertSee('Cable')->assertSee('Cinta')->assertSee('repartido en varios conceptos');
    }

    public function test_al_eliminar_el_concepto_que_guarda_la_foto_pasa_a_otro_del_grupo(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['monto' => 50, 'concepto' => null, 'conceptos' => [['concepto' => 'A', 'monto' => 20], ['concepto' => 'B', 'monto' => 30]]]));
        [$a, $b] = CajaChicaMovimiento::where('tipo', 'gasto')->orderBy('id')->get()->all();

        $this->delete(route('caja-chica.movimiento.destroy', $a))->assertSessionHas('success');
        $this->assertSame(1, $b->adjuntos()->count());
    }

    public function test_el_repartido_no_puede_superar_el_saldo(): void
    {
        $caja = $this->caja(50);
        $this->actingAs($this->tecnico);
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['monto' => 80, 'concepto' => null, 'conceptos' => [['concepto' => 'A', 'monto' => 40], ['concepto' => 'B', 'monto' => 40]]]))->assertSessionHas('error');
        $this->assertSame(0, CajaChicaMovimiento::where('tipo', 'gasto')->count());
    }

    public function test_recibo_de_pago_con_numero_correlativo_que_no_cambia(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-1']));
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-2', 'concepto' => 'Peaje']));
        [$g1, $g2] = CajaChicaMovimiento::where('tipo', 'gasto')->orderBy('id')->get()->all();

        $r = $this->get(route('caja-chica.recibo', $g2))->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'));
        $this->assertSame('RP-0001', $g2->fresh()->recibo_numero);
        $this->get(route('caja-chica.recibo', $g2))->assertOk();
        $this->assertSame('RP-0001', $g2->fresh()->recibo_numero);

        $this->get(route('caja-chica.recibos', $caja))->assertOk();
        $this->assertSame('RP-0002', $g1->fresh()->recibo_numero);
        $this->assertSame('RP-0001', $g2->fresh()->recibo_numero);

        // Un gasto rechazado no tiene recibo
        $this->actingAs($this->admin);
        $g3 = $caja->movimientos()->create(['tipo' => 'gasto', 'fecha' => today(), 'monto' => 5, 'concepto' => 'x', 'estado' => 'rechazado', 'user_id' => $this->tecnico->id, 'tipo_comprobante' => 'sin_comprobante', 'categoria' => 'otros']);
        $this->get(route('caja-chica.recibo', $g3))->assertRedirect(route('caja-chica.show', $caja))->assertSessionHas('error');
    }

    public function test_otro_usuario_no_obtiene_recibos_de_una_caja_ajena(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->usuario('Logística'));
        $this->get(route('caja-chica.recibos', $caja))->assertForbidden();
    }
}
