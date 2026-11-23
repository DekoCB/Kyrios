<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Comentarios libres sobre el proveedor: se guardan al crear, se editan y se ven en su ficha. */
class ProveedorObservacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['ruc' => '20555666771', 'razon_social' => 'Iluminación Import SAC', 'estado' => 'activo'];
    }

    public function test_los_formularios_piden_observaciones(): void
    {
        $this->get(route('proveedores.create'))->assertOk()->assertSee('Observaciones');
        $p = Proveedor::create($this->datos());
        $this->get(route('proveedores.edit', $p))->assertOk()->assertSee('Observaciones');
    }

    public function test_se_guardan_al_crear_y_se_ven_en_la_ficha(): void
    {
        $this->post(route('proveedores.store'), $this->datos(['observaciones' => "Entrega en 15 días.\nPide pago adelantado."]))->assertSessionHasNoErrors();

        $p = Proveedor::first();
        $this->assertSame("Entrega en 15 días.\nPide pago adelantado.", $p->observaciones);
        $this->get(route('proveedores.show', $p))->assertOk()->assertSee('Entrega en 15 días.')->assertSee('Pide pago adelantado.');
    }

    public function test_se_editan_y_son_opcionales(): void
    {
        $p = Proveedor::create($this->datos());
        $this->assertNull($p->observaciones);
        $this->get(route('proveedores.show', $p))->assertOk()->assertSee('Observaciones');

        $this->put(route('proveedores.update', $p), $this->datos(['observaciones' => 'Mal servicio en el último pedido.']))->assertSessionHasNoErrors();
        $this->assertSame('Mal servicio en el último pedido.', $p->fresh()->observaciones);

        $this->put(route('proveedores.update', $p), $this->datos(['observaciones' => '']));
        $this->assertNull($p->fresh()->observaciones);
    }

    public function test_el_texto_no_puede_pasar_de_2000_caracteres(): void
    {
        $this->post(route('proveedores.store'), $this->datos(['observaciones' => str_repeat('a', 2001)]))->assertSessionHasErrors('observaciones');
        $this->assertSame(0, Proveedor::count());
    }
}
