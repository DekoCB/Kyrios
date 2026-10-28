<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\CuentaPorPagar;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CompraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagos de cuentas por pagar: ni por cuota ni por pago manual puede superar el saldo pendiente.
 */
class Auditoria2BCuentasPorPagarTest extends TestCase
{
    use RefreshDatabase;

    private CuentaPorPagar $cuenta;
    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->actingAs($admin);
        $almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->sucursal = Sucursal::create(['nombre' => 'Principal', 'codigo' => 'S001', 'tipo' => 'tienda', 'estado' => 'activo', 'almacen_id' => $almacen->id]);
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $v = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        $prov = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Proveedor SAC', 'estado' => 'activo']);

        app(CompraService::class)->registrarCompra([
            'codigo' => 'C-1', 'proveedor_id' => $prov->id, 'almacen_id' => $almacen->id, 'user_id' => $admin->id, 'numero_factura' => 'F001-1',
            'fecha' => now()->toDateString(), 'forma_pago' => 'credito', 'condicion_pago' => 30, 'tipo_moneda' => 'PEN', 'tipo_cambio' => 1,
            'tipo_compra' => 'local', 'subtotal' => 100, 'igv' => 18, 'total' => 118, 'total_pen' => 118, 'estado' => 'registrado',
        ], [['producto_id' => $p->id, 'variante_id' => $v->id, 'cantidad' => 10, 'precio_unitario' => 10, 'descuento' => 0]]);

        $this->cuenta = CuentaPorPagar::firstOrFail();
    }

    private function pago(float $monto): array
    {
        return ['monto' => $monto, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => 'transferencia', 'sucursal_id' => $this->sucursal->id];
    }

    public function test_una_cuota_no_puede_pagar_mas_que_el_saldo(): void
    {
        $this->postJson(route('cuentas-por-pagar.generar-cuotas', $this->cuenta), ['num_cuotas' => 2])->assertOk();
        $cuota = Cuota::where('cuenta_por_pagar_id', $this->cuenta->id)->first();
        $saldo = (float) $this->cuenta->fresh()->saldo_pendiente;

        $this->postJson(route('cuentas-por-pagar.cuotas.pagar', $cuota), $this->pago($saldo + 500))->assertStatus(422);

        $this->assertSame(0.0, (float) $this->cuenta->fresh()->monto_pagado, 'no se registra nada');
        $this->assertSame('pendiente', $cuota->fresh()->estado);
        $this->assertSame(0, \App\Models\Pago::count());
    }

    public function test_pagar_una_cuota_normal_actualiza_el_saldo(): void
    {
        $this->postJson(route('cuentas-por-pagar.generar-cuotas', $this->cuenta), ['num_cuotas' => 2])->assertOk();
        $cuota = Cuota::where('cuenta_por_pagar_id', $this->cuenta->id)->orderBy('numero_cuota')->first();

        $this->postJson(route('cuentas-por-pagar.cuotas.pagar', $cuota), $this->pago((float) $cuota->monto))->assertOk()->assertJsonPath('success', true);

        $this->assertEqualsWithDelta((float) $cuota->monto, (float) $this->cuenta->fresh()->monto_pagado, 0.001);
        $this->assertSame('parcial', $this->cuenta->fresh()->estado);
    }

    public function test_el_pago_manual_tampoco_supera_el_saldo(): void
    {
        $saldo = (float) $this->cuenta->saldo_pendiente;
        $this->postJson(route('cuentas-por-pagar.registrar-pago', $this->cuenta), $this->pago($saldo + 1))->assertStatus(422);
        $this->assertSame(0, \App\Models\Pago::count());
    }
}
