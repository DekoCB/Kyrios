<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Montos y textos de caja con valores extremos: deben rechazarse con un mensaje, nunca con un error de base de datos.
 */
class Auditoria2ACajaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo', 'almacen_id' => $this->almacen->id]);
        $this->actingAs($this->admin);
        $this->caja = app(CajaService::class)->abrirCaja($this->admin->id, $this->almacen->id, 100);
    }

    public function test_un_ingreso_o_gasto_gigante_se_rechaza_sin_error_500(): void
    {
        foreach (['caja.ingreso' => ['metodo_pago' => 'efectivo'], 'caja.gasto' => ['categoria_gasto' => 'otros']] as $ruta => $extra) {
            $r = $this->post(route($ruta), ['caja_id' => $this->caja->id, 'monto' => 99999999999, 'concepto' => 'Prueba'] + $extra);
            $this->assertLessThan(500, $r->getStatusCode(), $ruta);
            $r->assertSessionHasErrors('monto');
        }
        $this->assertEquals(100.0, (float) $this->caja->fresh()->monto_final);
    }

    public function test_cerrar_con_un_conteo_gigante_se_rechaza_sin_error_500(): void
    {
        $r = $this->post(route('caja.cerrar'), ['caja_id' => $this->caja->id, 'monto_real_cierre' => 99999999999]);

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors('monto_real_cierre');
        $this->assertSame('abierta', $this->caja->fresh()->estado);
    }

    public function test_abrir_caja_con_monto_gigante_se_rechaza(): void
    {
        $this->caja->update(['estado' => 'cerrada']);

        $r = $this->post(route('caja.store'), ['monto_inicial' => 99999999999, 'almacen_id' => $this->almacen->id]);

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors('monto_inicial');
    }

    public function test_el_cierre_forzado_limita_el_largo_de_las_observaciones(): void
    {
        $r = $this->post(route('admin.cajas.forzar-cierre', $this->caja), ['observaciones' => str_repeat('ñ', 70000)]);

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors('observaciones');
        $this->assertSame('abierta', $this->caja->fresh()->estado);
    }

    public function test_ajustar_la_diferencia_valida_monto_y_motivo(): void
    {
        $this->caja->update(['estado' => 'cerrada', 'monto_real_cierre' => 100, 'diferencia_cierre' => 0]);

        $r = $this->post(route('admin.cajas.ajustar-diferencia', $this->caja), ['monto_ajuste' => 99999999999, 'motivo_ajuste' => str_repeat('ñ', 70000)]);

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors(['monto_ajuste', 'motivo_ajuste']);
    }

    public function test_la_apertura_remota_valida_monto_y_observaciones(): void
    {
        $this->caja->update(['estado' => 'cerrada']);
        $otro = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo', 'almacen_id' => $this->almacen->id]);

        $r = $this->post(route('admin.cajas.apertura-remota.store'), [
            'sucursal_id' => Sucursal::first()->id, 'user_id' => $otro->id, 'monto_inicial' => 99999999999, 'observaciones' => str_repeat('ñ', 70000),
        ]);

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors(['monto_inicial', 'observaciones']);
    }
}
