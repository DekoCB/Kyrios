<?php

namespace Tests\Feature;

use App\Models\CajaChica;
use App\Models\CajaChicaMovimiento;
use App\Models\Role;
use App\Models\User;
use App\Services\CajaChicaService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caja chica: el saldo nunca debe quedar en negativo por eliminar un ingreso o reactivar un gasto, y no se cierra con gastos sin resolver.
 */
class Auditoria2ACajaChicaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private CajaChica $caja;
    private CajaChicaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->servicio = app(CajaChicaService::class);
        $this->caja = $this->servicio->abrir(['tipo' => 'general', 'nombre' => 'Caja chica', 'monto_inicial' => 100], $this->admin);
    }

    private function gasto(float $monto, ?User $quien = null): CajaChicaMovimiento
    {
        return $this->servicio->registrarGasto($this->caja->fresh(), [
            'fecha' => now()->toDateString(), 'monto' => $monto, 'categoria' => 'transporte', 'concepto' => 'Pasajes', 'tipo_comprobante' => 'sin_comprobante',
        ], [], $quien ?? $this->admin);
    }

    public function test_no_se_elimina_un_ingreso_que_dejaria_el_saldo_en_negativo(): void
    {
        $this->gasto(80);
        $ingreso = $this->caja->movimientos()->where('tipo', 'ingreso')->first();

        try {
            $this->servicio->eliminarMovimiento($ingreso);
            $this->fail('Debió rechazar la eliminación del ingreso');
        } catch (DomainException $e) {
            $this->assertStringContainsString('saldo', $e->getMessage());
        }

        $this->assertEquals(20.0, $this->caja->fresh()->saldo);
    }

    public function test_si_alcanza_el_saldo_el_ingreso_si_se_puede_eliminar(): void
    {
        $this->servicio->ingreso($this->caja->fresh(), 50, $this->admin, 'Reposición');
        $this->gasto(30);
        $reposicion = $this->caja->movimientos()->where('tipo', 'ingreso')->where('concepto', 'Reposición')->first();

        $this->servicio->eliminarMovimiento($reposicion);

        $this->assertEquals(70.0, $this->caja->fresh()->saldo);
    }

    public function test_un_gasto_rechazado_no_se_reactiva_si_ya_no_hay_saldo(): void
    {
        $primero = $this->gasto(80);
        $this->servicio->revisar($primero, 'rechazar', 'Sin sustento', $this->admin);
        $this->gasto(90); // usa el dinero que liberó el rechazo

        try {
            $this->servicio->revisar($primero->fresh(), 'aprobar', null, $this->admin);
            $this->fail('Debió rechazar la aprobación sin saldo');
        } catch (DomainException $e) {
            $this->assertStringContainsString('saldo', $e->getMessage());
        }

        $this->assertSame('rechazado', $primero->fresh()->estado);
        $this->assertEquals(10.0, $this->caja->fresh()->saldo);
    }

    public function test_no_se_cierra_la_caja_con_gastos_observados(): void
    {
        $gasto = $this->gasto(20);
        $this->servicio->revisar($gasto, 'observar', 'Falta la boleta', $this->admin);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('pendientes de revisión');
        $this->servicio->cerrar($this->caja->fresh());
    }

    public function test_se_cierra_cuando_todo_esta_aprobado_o_rechazado(): void
    {
        $uno = $this->gasto(20);
        $dos = $this->gasto(10);
        $this->servicio->revisar($uno, 'aprobar', null, $this->admin);
        $this->servicio->revisar($dos, 'rechazar', 'No corresponde', $this->admin);

        $this->servicio->cerrar($this->caja->fresh());

        $this->assertSame('cerrada', $this->caja->fresh()->estado);
    }
}
