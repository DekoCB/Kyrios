<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Role;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Venta;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Services\VentaService;
use App\Services\CajaService;

/**
 * Reportado por un cliente vía WhatsApp: al eliminar ventas para volver a
 * hacerlas, "las cajas" (sesiones de caja) se quedaban con los montos
 * viejos. Causa: registrarEnCaja() buscaba "la caja abierta ahora mismo"
 * en vez de la caja donde se registró originalmente el ingreso de la
 * venta, y CajaService::registrarMovimiento() rechaza escribir en una caja
 * ya cerrada — así que la corrección fallaba en silencio.
 *
 * Regla de negocio confirmada con el cliente: "eliminar" debe quedar sin
 * ningún rastro (revierte/borra el ingreso original); "anular" conserva la
 * venta visible y deja un egreso nuevo (rastro visible de la corrección).
 */
class VentaCajaReversalTest extends TestCase
{
    private Role    $roleAdmin;
    private User    $admin;
    private Almacen $almacen;
    private Cliente $cliente;
    private Categoria $categoria;
    private Producto $producto;

    private array $createdVentas = [];
    private array $createdCajas  = [];
    private array $createdUsers  = [];
    private array $createdProductos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::firstOrCreate(['nombre' => 'Administrador']);

        $uid = uniqid();

        $this->admin = User::create([
            'name'     => 'Admin Caja Test',
            'email'    => "admin_caja_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $this->roleAdmin->id,
        ]);
        $this->createdUsers[] = $this->admin->id;

        $this->almacen = Almacen::create([
            'nombre' => "Almacén Caja Test {$uid}",
            'codigo' => "ALMC-{$uid}",
            'tipo'   => 'principal',
            'estado' => 'activo',
        ]);
        $this->admin->update(['almacen_id' => $this->almacen->id]);

        $this->cliente = Cliente::create([
            'tipo_documento'   => 'DNI',
            'numero_documento' => (string) rand(10000000, 99999999),
            'nombre'           => "Cliente Caja {$uid}",
            'estado'           => 'activo',
        ]);

        $this->categoria = Categoria::firstOrCreate(
            ['codigo' => 'CAJA-TEST'],
            ['nombre' => 'Caja Test', 'estado' => 'activo']
        );

        $this->producto = Producto::create([
            'codigo'          => "PC-{$uid}",
            'nombre'          => "Producto Caja {$uid}",
            'categoria_id'    => $this->categoria->id,
            'tipo_inventario' => 'cantidad',
            'stock_actual'    => 100,
            'stock_minimo'    => 1,
            'stock_maximo'    => 200,
            'estado'          => 'activo',
        ]);
        $this->createdProductos[] = $this->producto->id;
        StockAlmacen::create([
            'producto_id' => $this->producto->id,
            'almacen_id'  => $this->almacen->id,
            'cantidad'    => 100,
        ]);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->createdVentas)) {
            MovimientoCaja::whereIn('venta_id', $this->createdVentas)->delete();
            \App\Models\DetalleVenta::whereIn('venta_id', $this->createdVentas)->delete();
            Venta::whereIn('id', $this->createdVentas)->delete();
        }
        if (!empty($this->createdCajas)) {
            MovimientoCaja::whereIn('caja_id', $this->createdCajas)->delete();
            Caja::whereIn('id', $this->createdCajas)->delete();
        }
        StockAlmacen::where('almacen_id', $this->almacen->id)->delete();
        if (!empty($this->createdProductos)) {
            Producto::whereIn('id', $this->createdProductos)->delete();
        }
        \App\Models\MovimientoInventario::where('almacen_id', $this->almacen->id)->delete();
        $this->almacen->delete();
        $this->cliente->delete();
        User::whereIn('id', $this->createdUsers)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    private function abrirCaja(float $montoInicial = 100.0): Caja
    {
        $caja = app(CajaService::class)->abrirCaja($this->admin->id, $this->almacen->id, $montoInicial);
        $this->createdCajas[] = $caja->id;
        return $caja;
    }

    private function crearVentaPagada(string $metodoPago, float $precio = 50.0, int $cantidad = 1): Venta
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pagado',
            'condicion_pago'   => 'contado',
            'metodo_pago'      => $metodoPago,
        ], [[
            'producto_id'     => $this->producto->id,
            'variante_id'     => null,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio,
            'incluye_igv'     => true,
        ]], ['metodo_pago' => $metodoPago]);
        $this->createdVentas[] = $venta->id;
        return $venta;
    }

    public function test_eliminar_venta_efectivo_revierte_ingreso_en_caja_cerrada_y_ajusta_diferencia(): void
    {
        $caja  = $this->abrirCaja(100.0);
        $venta = $this->crearVentaPagada('efectivo', 50.0);

        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'ingreso',
        ]);

        app(CajaService::class)->cerrarCaja($caja->id, 150.0); // 100 inicial + 50 venta, arqueo exacto
        $caja->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $caja->diferencia_cierre, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $caja->monto_final, 0.01);

        $cajaActualizada = app(VentaService::class)->eliminarVenta($venta);

        $this->assertTrue($cajaActualizada);
        $this->assertDatabaseMissing('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'ingreso',
        ]);

        $caja->refresh();
        // Se fue el ingreso de 50: final baja a 100, y como ya no se esperaban
        // esos 50 en efectivo, la diferencia sube a +50 (sobrante respecto a
        // lo que ahora "debía" haber).
        $this->assertEqualsWithDelta(100.0, (float) $caja->monto_final, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $caja->diferencia_cierre, 0.01);
    }

    public function test_eliminar_venta_transferencia_no_afecta_diferencia_de_efectivo(): void
    {
        $caja  = $this->abrirCaja(100.0);
        $venta = $this->crearVentaPagada('transferencia', 80.0);

        app(CajaService::class)->cerrarCaja($caja->id, 100.0); // solo el efectivo inicial, sin tocar la venta por transferencia
        $caja->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $caja->diferencia_cierre, 0.01);

        app(VentaService::class)->eliminarVenta($venta);

        $caja->refresh();
        // El monto_final total sí baja (era ingreso, aunque no en efectivo),
        // pero diferencia_cierre (solo efectivo) no debe moverse.
        $this->assertEqualsWithDelta(100.0, (float) $caja->monto_final, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $caja->diferencia_cierre, 0.01);
    }

    public function test_anular_venta_en_caja_cerrada_agrega_egreso_visible_y_conserva_ingreso_original(): void
    {
        $caja  = $this->abrirCaja(100.0);
        $venta = $this->crearVentaPagada('efectivo', 30.0);

        app(CajaService::class)->cerrarCaja($caja->id, 130.0);
        $caja->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $caja->diferencia_cierre, 0.01);

        $cajaActualizada = app(VentaService::class)->anularVenta($venta);

        $this->assertTrue($cajaActualizada);
        // A diferencia de eliminar: el ingreso original SIGUE existiendo.
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'ingreso',
        ]);
        // Y aparece un egreso nuevo — el rastro visible de la corrección.
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'egreso',
        ]);

        $caja->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $caja->monto_final, 0.01);
        $this->assertEqualsWithDelta(30.0, (float) $caja->diferencia_cierre, 0.01);
    }

    public function test_eliminar_venta_en_caja_abierta_decrementa_monto_final_sin_movimiento_nuevo(): void
    {
        $caja  = $this->abrirCaja(100.0);
        $venta = $this->crearVentaPagada('efectivo', 40.0);

        $caja->refresh();
        $this->assertEqualsWithDelta(140.0, (float) $caja->monto_final, 0.01);

        app(VentaService::class)->eliminarVenta($venta);

        $caja->refresh();
        $this->assertEquals('abierta', $caja->estado);
        $this->assertEqualsWithDelta(100.0, (float) $caja->monto_final, 0.01);
        $this->assertEquals(0, MovimientoCaja::where('venta_id', $venta->id)->count());
    }

    /**
     * DevolucionController::store() tenía el mismo bug que registrarEnCaja():
     * buscaba "la caja abierta ahora" en vez de la caja original de la venta,
     * así que una devolución sobre una venta de una caja ya cerrada no
     * afectaba esa caja para nada (quedaba "caja_pendiente" en silencio).
     */
    public function test_devolucion_parcial_en_caja_cerrada_registra_egreso_en_caja_original(): void
    {
        $caja  = $this->abrirCaja(100.0);
        // 2 unidades a S/ 30 c/u (con IGV) = S/ 60 de ingreso
        $venta = $this->crearVentaPagada('efectivo', 30.0, 2);

        app(CajaService::class)->cerrarCaja($caja->id, 160.0); // 100 + 60, arqueo exacto
        $caja->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $caja->diferencia_cierre, 0.01);

        $detalle = $venta->detalles->first();
        $this->assertNotNull($detalle);

        $response = $this->post(route('devoluciones.store'), [
            'cliente_id'   => $this->cliente->id,
            'almacen_id'   => $this->almacen->id,
            'cantidades'   => [$detalle->id => 1], // devuelve solo 1 de las 2 unidades
            'observaciones'=> 'Test devolución caja cerrada',
        ]);

        $response->assertRedirect(route('devoluciones.index'));
        $response->assertSessionHas('success');
        $this->assertStringNotContainsString(
            'no se encontró ninguna caja',
            session('success'),
            'No debería quedar pendiente: la venta sí tiene una caja asociada (aunque cerrada).'
        );

        // Egreso de S/ 30 (1 unidad devuelta) en la caja ORIGINAL, aunque ya
        // esté cerrada — y el ingreso original de S/ 60 sigue intacto.
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'egreso', 'monto' => 30.00,
        ]);
        $this->assertDatabaseHas('movimientos_caja', [
            'caja_id' => $caja->id, 'venta_id' => $venta->id, 'tipo' => 'ingreso', 'monto' => 60.00,
        ]);

        $caja->refresh();
        $this->assertEqualsWithDelta(130.0, (float) $caja->monto_final, 0.01);   // 160 - 30
        $this->assertEqualsWithDelta(30.0, (float) $caja->diferencia_cierre, 0.01);
    }
}
