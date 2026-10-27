<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\EscanerService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la auditoría de ventas y caja: cierre automático y forzado, ajuste de diferencia,
 * cuentas por cobrar y panel del cajero.
 */
class VentasCajaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $variante;
    private Cliente $cliente;
    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->sucursal = Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $this->admin = $this->usuario('Administrador');
        $this->actingAs($this->admin);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->variante->id, 'cantidad' => 50]], $this->admin->id);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Perez', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo', 'almacen_id' => $this->almacen->id,
        ]);
    }

    /** Caja de ayer con S/ 100 iniciales y un cobro por Yape de S/ 50 (no entra al cajón). */
    private function cajaConYape(bool $deAyer = false): Caja
    {
        $servicio = app(CajaService::class);
        $caja = $servicio->abrirCaja($this->admin->id, $this->almacen->id, 100);
        $servicio->registrarMovimiento($caja->id, 'ingreso', 50, 'Cobro por Yape', null, null, null, 'yape');
        if ($deAyer) {
            $caja->update(['fecha' => now()->subDay()->toDateString()]);
        }

        return $caja->fresh();
    }

    private function ventaACredito(): \App\Models\Venta
    {
        return app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]],
            null, ['numero_cuotas' => 2, 'dias_entre_cuotas' => 30]);
    }

    public function test_el_cierre_automatico_usa_el_mismo_arqueo_que_el_manual(): void
    {
        $caja = $this->cajaConYape(true);

        $this->artisan('caja:cerrar-automatico')->assertSuccessful();

        $caja->refresh();
        $this->assertSame('cerrada', $caja->estado);
        $this->assertEquals(100.0, (float) $caja->monto_real_cierre, 'el Yape no está en el cajón');
        $this->assertEquals(0.0, (float) $caja->diferencia_cierre);
    }

    public function test_el_cierre_forzado_no_cuenta_como_efectivo_lo_cobrado_por_yape(): void
    {
        $caja = $this->cajaConYape();
        $this->assertEquals(150.0, (float) $caja->monto_final);

        $this->post(route('admin.cajas.forzar-cierre', $caja), ['observaciones' => 'Cierre por olvido del cajero'])->assertRedirect();

        $caja->refresh();
        $this->assertSame('cerrada', $caja->estado);
        $this->assertEquals(100.0, (float) $caja->monto_real_cierre);
        $this->assertEquals(0.0, (float) $caja->diferencia_cierre);
        $this->assertStringContainsString('FORZADO', $caja->observaciones_cierre);
    }

    public function test_ajustar_la_diferencia_de_una_caja_cerrada_mueve_el_saldo_y_la_diferencia_en_el_sentido_correcto(): void
    {
        $caja = $this->cajaConYape();
        app(CajaService::class)->cerrarCaja($caja->id, 100.0);
        $caja->refresh();
        $final = (float) $caja->monto_final;

        $this->post(route('admin.cajas.ajustar-diferencia', $caja), ['monto_ajuste' => 30, 'motivo_ajuste' => 'Ingreso olvidado'])->assertSessionHasNoErrors();

        $caja->refresh();
        $this->assertEquals($final + 30, (float) $caja->monto_final, 'el ajuste mueve el saldo de la caja');
        $this->assertEquals(-30.0, (float) $caja->diferencia_cierre, 'un ingreso sube lo esperado: el conteo real queda 30 por debajo');
        $this->assertEquals(130.0, app(CajaService::class)->getArqueo($caja->fresh('movimientos'))['saldo_esperado']);
    }

    public function test_ajustar_una_caja_abierta_con_un_egreso_baja_el_saldo(): void
    {
        $caja = $this->cajaConYape();

        $this->post(route('admin.cajas.ajustar-diferencia', $caja), ['monto_ajuste' => -20, 'motivo_ajuste' => 'Gasto sin registrar'])->assertSessionHasNoErrors();

        $this->assertEquals(130.0, (float) $caja->fresh()->monto_final);
        $this->assertEquals(80.0, app(CajaService::class)->getArqueo($caja->fresh('movimientos'))['saldo_esperado']);
    }

    public function test_el_detalle_de_una_cuenta_por_cobrar_lleva_a_la_ficha_del_credito(): void
    {
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $venta = $this->ventaACredito();
        $cuenta = CuentaPorCobrar::firstOrFail();

        $this->get(route('cuentas-por-cobrar.show', $cuenta))->assertRedirect(route('ventas.credito.show', $venta->id));
        $this->get(route('ventas.credito.show', $venta->id))->assertOk();
    }

    public function test_la_lista_de_cuentas_ya_muestra_como_vencidas_las_que_vencieron(): void
    {
        $venta = $this->ventaACredito();
        $cuenta = CuentaPorCobrar::firstOrFail();
        $cuenta->update(['fecha_vencimiento_final' => now()->subDays(3)->toDateString()]);
        $this->assertSame('vigente', $cuenta->fresh()->estado);

        $this->get(route('cuentas-por-cobrar.index', ['estado' => 'vencido']))->assertOk()->assertSee($venta->codigo);
        $this->assertSame('vencido', $cuenta->fresh()->estado);
    }

    public function test_el_panel_del_cajero_con_una_caja_atrasada_enlaza_a_una_ruta_valida(): void
    {
        $cajero = $this->usuario('Cajero');
        $caja = app(CajaService::class)->abrirCaja($cajero->id, $this->almacen->id, 20);
        $caja->update(['fecha' => now()->subDays(2)->toDateString()]);

        $this->actingAs($cajero)->get(route('cajero.dashboard'))->assertOk()
            ->assertSee('sin cerrar')->assertSee(route('caja.actual'), false);
    }

    public function test_no_se_confirma_un_cobro_sin_caja_abierta(): void
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta', 'estado_pago' => 'pendiente', 'condicion_pago' => 'contado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true]]);

        $this->post(route('ventas.confirmar-pago', $venta), ['metodo_pago' => 'efectivo'])->assertSessionHas('error');
        $this->assertSame('pendiente', $venta->fresh()->estado_pago, 'la venta no queda pagada sin ingreso en caja');

        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $this->post(route('ventas.confirmar-pago', $venta), ['metodo_pago' => 'efectivo'])->assertSessionHasNoErrors();
        $this->assertSame('pagado', $venta->fresh()->estado_pago);
        $this->assertDatabaseHas('movimientos_caja', ['venta_id' => $venta->id, 'tipo' => 'ingreso']);
    }

    public function test_un_pago_de_credito_no_puede_marcar_la_cuota_de_otra_cuenta(): void
    {
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $uno = $this->ventaACredito();
        $dos = $this->ventaACredito();
        $cuotaAjena = CuentaPorCobrar::where('venta_id', $dos->id)->firstOrFail()->cuotas()->firstOrFail();

        $this->post(route('ventas.credito.pago', $uno), [
            'monto' => 50, 'metodo_pago' => 'efectivo', 'fecha_pago' => now()->toDateString(), 'cuota_cobro_id' => $cuotaAjena->id,
        ])->assertSessionHas('error');

        $this->assertSame('pendiente', $cuotaAjena->fresh()->estado);
        $this->assertEquals(0.0, (float) CuentaPorCobrar::where('venta_id', $uno->id)->first()->monto_pagado);

        $propia = CuentaPorCobrar::where('venta_id', $uno->id)->firstOrFail()->cuotas()->firstOrFail();
        $this->post(route('ventas.credito.pago', $uno), [
            'monto' => 50, 'metodo_pago' => 'efectivo', 'fecha_pago' => now()->toDateString(), 'cuota_cobro_id' => $propia->id,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame('pagado', $propia->fresh()->estado);
    }

    public function test_un_cajero_no_mueve_la_caja_de_otro_usuario(): void
    {
        $duenio = $this->usuario('Cajero');
        $ajeno = $this->usuario('Cajero');
        $caja = app(CajaService::class)->abrirCaja($duenio->id, $this->almacen->id, 100);
        $ingreso = ['caja_id' => $caja->id, 'monto' => 10, 'concepto' => 'Prueba', 'metodo_pago' => 'efectivo'];
        $gasto = ['caja_id' => $caja->id, 'monto' => 5, 'concepto' => 'Prueba', 'categoria_gasto' => 'otros'];

        $this->actingAs($ajeno)->post(route('caja.ingreso'), $ingreso)->assertForbidden();
        $this->actingAs($ajeno)->post(route('caja.gasto'), $gasto)->assertForbidden();
        $this->assertEquals(100.0, (float) $caja->fresh()->monto_final);

        $this->actingAs($duenio)->post(route('caja.ingreso'), $ingreso)->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->admin)->post(route('caja.gasto'), $gasto)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(105.0, (float) $caja->fresh()->monto_final);
    }

    public function test_un_cliente_sin_documento_va_a_sunat_como_sin_documento_y_no_como_dni(): void
    {
        $prospecto = Cliente::create(['nombre' => 'Sandra Gonzalez', 'estado' => 'activo']);
        $venta = new \App\Models\Venta(['cliente_id' => $prospecto->id]);
        $venta->setRelation('cliente', $prospecto);
        $buildClient = new \ReflectionMethod(\App\Services\SunatComprobanteService::class, 'buildClient');
        $buildClient->setAccessible(true);

        $cliente = $buildClient->invoke(app(\App\Services\SunatComprobanteService::class), $venta);
        $this->assertSame('0', $cliente->getTipoDoc());
        $this->assertSame('-', $cliente->getNumDoc());

        $venta->setRelation('cliente', $this->cliente);
        $cliente = $buildClient->invoke(app(\App\Services\SunatComprobanteService::class), $venta);
        $this->assertSame('1', $cliente->getTipoDoc());
        $this->assertSame('12345678', $cliente->getNumDoc());
    }

    public function test_la_ficha_de_una_venta_a_un_prospecto_no_muestra_null(): void
    {
        $prospecto = Cliente::create(['nombre' => 'Sandra Gonzalez', 'estado' => 'activo']);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $prospecto->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta', 'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true]], ['metodo_pago' => 'efectivo']);

        $html = $this->get(route('ventas.show', $venta))->assertOk()->getContent();
        $this->assertStringContainsString('Sin documento', $html);
        $this->assertStringNotContainsString("tipo_documento:   'null'", $html);
        $this->get(route('ventas.pdf', ['venta' => $venta->id, 'formato' => 'a4']))->assertOk();
    }

    private function ventaPagada(?Cliente $cliente = null): \App\Models\Venta
    {
        return app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => ($cliente ?? $this->cliente)->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta', 'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]], ['metodo_pago' => 'efectivo']);
    }

    public function test_no_se_devuelve_una_venta_anulada_ni_de_otro_cliente_y_el_stock_no_se_duplica(): void
    {
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $otro = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '87654321', 'nombre' => 'Otra Persona', 'estado' => 'activo']);
        $venta = $this->ventaPagada();
        $detalle = $venta->detalles()->firstOrFail();
        $stock = fn () => \App\Models\StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->variante->id);
        $this->assertSame(48, $stock());

        // Línea de una venta de otro cliente
        $this->post(route('devoluciones.store'), ['cliente_id' => $otro->id, 'almacen_id' => $this->almacen->id, 'cantidades' => [$detalle->id => 1]])
            ->assertSessionHas('error');
        $this->assertSame(48, $stock());

        // Venta anulada: el stock ya se repuso al anular
        app(VentaService::class)->anularVenta($venta->fresh());
        $this->assertSame(50, $stock());
        $this->post(route('devoluciones.store'), ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'cantidades' => [$detalle->id => 1]])
            ->assertSessionHas('error');
        $this->assertSame(50, $stock(), 'devolver una venta anulada no debe sumar stock otra vez');
    }

    public function test_una_devolucion_valida_sigue_funcionando(): void
    {
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $detalle = $this->ventaPagada()->detalles()->firstOrFail();

        $this->post(route('devoluciones.store'), ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'cantidades' => [$detalle->id => 1]])
            ->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSame(49, \App\Models\StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->variante->id));
    }

    public function test_se_puede_ver_una_venta_pendiente_sin_cliente(): void
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => null, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta', 'estado_pago' => 'pendiente', 'condicion_pago' => 'contado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true]]);

        $this->get(route('ventas.show', $venta))->assertOk()->assertSee($venta->codigo);
        $this->get(route('ventas.pdf', ['venta' => $venta->id, 'formato' => 'ticket']))->assertOk();
        $this->get(route('ventas.index'))->assertOk()->assertSee('Sin cliente');
    }

    public function test_un_vendedor_solo_abre_sus_propias_ventas(): void
    {
        $uno = $this->usuario('Vendedor');
        $otro = $this->usuario('Vendedor');
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $uno->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(), 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'tipo_comprobante' => 'nota_venta', 'estado_pago' => 'pendiente', 'condicion_pago' => 'contado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true]]);

        $this->actingAs($otro)->get(route('ventas.show', $venta))->assertForbidden();
        $this->actingAs($otro)->get(route('ventas.pdf', ['venta' => $venta->id, 'formato' => 'a4']))->assertForbidden();
        $this->actingAs($otro)->get(route('ventas.guia-pdf', $venta))->assertForbidden();

        $this->actingAs($uno)->get(route('ventas.show', $venta))->assertOk();
        $this->actingAs($this->admin)->get(route('ventas.show', $venta))->assertOk();
    }
}
