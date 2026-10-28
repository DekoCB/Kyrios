<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\EscanerService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segunda auditoría de ventas y caja: dinero, stock y validaciones del servidor.
 */
class Auditoria2AVentasDineroTest extends TestCase
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
        $this->almacen->update(['sucursal_id' => $this->sucursal->id]);
        $this->admin = $this->usuario('Administrador');
        $this->actingAs($this->admin);

        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->variante->id, 'cantidad' => 50]], $this->admin->id);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Perez', 'estado' => 'activo']);
        app(CajaService::class)->abrirCaja($this->admin->id, $this->almacen->id, 100);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo', 'almacen_id' => $this->almacen->id]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'tipo_comprobante' => 'nota_venta', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 10, 'precio_unitario' => 118, 'incluye_igv' => true]],
        ], $extra);
    }

    private function stock(): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->variante->id);
    }

    private function caja(): float
    {
        return (float) Caja::first()->fresh()->monto_final;
    }

    /** Venta de 10 unidades a S/ 118 (total S/ 1.180) pagada en efectivo. */
    private function venta(array $datosExtra = [], ?array $pago = ['metodo_pago' => 'efectivo']): Venta
    {
        return app(VentaService::class)->crearVenta(array_merge([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], $datosExtra), [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 10, 'precio_unitario' => 118, 'incluye_igv' => true]], $pago);
    }

    private function devolver(Venta $venta, int $unidades)
    {
        $det = DetalleVenta::where('venta_id', $venta->id)->first();

        return $this->post(route('devoluciones.store'), ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'cantidades' => [$det->id => $unidades]]);
    }

    // ── devolución parcial + anulación / eliminación ──────────────────────────

    public function test_anular_una_venta_parcialmente_devuelta_no_repone_dos_veces_el_stock_ni_el_dinero(): void
    {
        $venta = $this->venta();
        $this->assertSame(40, $this->stock());
        $this->assertEquals(1280.0, $this->caja());

        $this->devolver($venta, 4);
        $this->assertSame(44, $this->stock());
        $this->assertEquals(808.0, $this->caja());

        $this->post(route('ventas.anular', $venta->fresh()));

        $this->assertSame(50, $this->stock(), 'quedan reponer solo las 6 unidades que no se habían devuelto');
        $this->assertEquals(100.0, $this->caja(), 'la caja vuelve a su monto inicial: se reintegran S/ 708, no S/ 1.180');
        $this->assertSame('anulado', $venta->fresh()->estado_pago);
    }

    public function test_eliminar_una_venta_parcialmente_devuelta_deja_stock_y_caja_como_al_inicio(): void
    {
        $venta = $this->venta();
        $this->devolver($venta, 4);

        $this->delete(route('ventas.destroy', $venta->fresh()));

        $this->assertSame(50, $this->stock());
        $this->assertEquals(100.0, $this->caja());
    }

    public function test_una_venta_totalmente_devuelta_ya_no_mueve_nada_al_anularla(): void
    {
        $venta = $this->venta();
        $this->devolver($venta, 10);
        $this->assertSame(50, $this->stock());
        $this->assertEquals(100.0, $this->caja());

        $this->post(route('ventas.anular', $venta->fresh()));

        $this->assertSame(50, $this->stock());
        $this->assertEquals(100.0, $this->caja());
    }

    // ── doble envío / solicitudes simultáneas ────────────────────────────────

    public function test_anular_dos_veces_con_la_venta_cargada_antes_de_la_primera_anulacion_no_repite_los_reversos(): void
    {
        $venta = $this->venta();
        $a = Venta::find($venta->id);
        $b = Venta::find($venta->id); // segunda solicitud: cargó la venta cuando aún estaba pagada

        app(VentaService::class)->anularVenta($a);

        $this->expectExceptionMessage('ya está anulada');
        try {
            app(VentaService::class)->anularVenta($b);
        } finally {
            $this->assertSame(50, $this->stock(), 'el stock solo se repuso una vez');
            $this->assertEquals(100.0, $this->caja(), 'el egreso solo se registró una vez');
        }
    }

    public function test_cobrar_dos_veces_a_la_vez_una_cuenta_no_deja_pasarse_del_saldo(): void
    {
        $credito = app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]],
            null, ['numero_cuotas' => 2, 'dias_entre_cuotas' => 30]);
        $c1 = CuentaPorCobrar::find($credito->cuentaPorCobrar->id);
        $c2 = CuentaPorCobrar::find($credito->cuentaPorCobrar->id); // saldo visto antes del primer cobro

        app(VentaService::class)->registrarPagoCredito($c1, ['monto' => 200, 'metodo_pago' => 'efectivo']);

        $this->expectExceptionMessage('excede el saldo');
        try {
            app(VentaService::class)->registrarPagoCredito($c2, ['monto' => 200, 'metodo_pago' => 'efectivo']);
        } finally {
            $this->assertEquals(200.0, (float) $c1->fresh()->monto_pagado);
        }
    }

    // ── pagos ────────────────────────────────────────────────────────────────

    public function test_un_pago_mixto_que_no_cubre_el_total_se_rechaza_y_no_deja_la_venta_a_medias(): void
    {
        $stock = $this->stock();
        $caja = $this->caja();
        $ventas = Venta::count();

        $this->post(route('ventas.store'), $this->datos(['metodo_pago' => 'mixto', 'pagos_detalle' => [
            ['metodo' => 'efectivo', 'monto' => 10], ['metodo' => 'yape', 'monto' => 10],
        ]]))->assertSessionHas('error');

        $this->assertSame($ventas, Venta::count());
        $this->assertSame($stock, $this->stock());
        $this->assertEquals($caja, $this->caja());
    }

    public function test_el_vuelto_de_un_pago_mixto_no_entra_a_la_caja(): void
    {
        $caja = $this->caja();

        // Total S/ 1.180: Yape 500 y 700 en efectivo (20 de vuelto)
        $this->post(route('ventas.store'), $this->datos(['metodo_pago' => 'mixto', 'pagos_detalle' => [
            ['metodo' => 'yape', 'monto' => 500], ['metodo' => 'efectivo', 'monto' => 700],
        ]]))->assertSessionHasNoErrors();

        $venta = Venta::latest('id')->first();
        $this->assertEquals($caja + 1180, $this->caja());
        $this->assertEquals([500.0, 680.0], array_map(fn ($p) => (float) $p['monto'], $venta->pagos_detalle));
    }

    public function test_el_vuelto_no_puede_salir_de_un_pago_que_no_es_en_efectivo(): void
    {
        $this->post(route('ventas.store'), $this->datos(['metodo_pago' => 'mixto', 'pagos_detalle' => [
            ['metodo' => 'yape', 'monto' => 600], ['metodo' => 'transferencia', 'monto' => 700],
        ]]))->assertSessionHas('error');

        $this->assertSame(0, Venta::count());
    }

    public function test_al_confirmar_un_cobro_los_pagos_tambien_deben_cubrir_el_total(): void
    {
        $venta = $this->venta(['estado_pago' => 'pendiente'], null);
        $this->assertSame('pendiente', $venta->estado_pago);

        $this->expectExceptionMessage('no cubren el total');
        app(VentaService::class)->confirmarPago($venta->id, 'mixto', $this->admin->id, ['pagos_detalle' => [
            ['metodo' => 'efectivo', 'monto' => 100], ['metodo' => 'yape', 'monto' => 100],
        ]]);
    }

    // ── cuotas ───────────────────────────────────────────────────────────────

    private function ventaACredito(): Venta
    {
        return app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->sucursal->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]],
            null, ['numero_cuotas' => 2, 'dias_entre_cuotas' => 30]);
    }

    private function pagarCuota(Venta $venta, $cuota, float $monto)
    {
        return $this->post(route('ventas.credito.pago', $venta), [
            'monto' => $monto, 'metodo_pago' => 'efectivo', 'fecha_pago' => now()->toDateString(), 'cuota_cobro_id' => $cuota->id,
        ]);
    }

    public function test_un_abono_parcial_no_marca_la_cuota_como_pagada_pero_dos_abonos_que_la_cubren_si(): void
    {
        $venta = $this->ventaACredito();
        $cuota = $venta->cuentaPorCobrar->cuotas()->orderBy('numero_cuota')->first();
        $this->assertEquals(118.0, (float) $cuota->monto);

        $this->pagarCuota($venta, $cuota, 10);
        $this->assertSame('pendiente', $cuota->fresh()->estado);

        $this->pagarCuota($venta, $cuota, 108);
        $this->assertSame('pagado', $cuota->fresh()->estado);
    }

    public function test_pagar_una_cuota_vencida_la_marca_como_pagada(): void
    {
        $venta = $this->ventaACredito();
        $cuota = $venta->cuentaPorCobrar->cuotas()->orderBy('numero_cuota')->first();
        $cuota->update(['estado' => 'vencido']);

        $this->pagarCuota($venta, $cuota, 118);

        $this->assertSame('pagado', $cuota->fresh()->estado);
    }

    public function test_al_cubrir_el_total_de_la_cuenta_todas_las_cuotas_quedan_pagadas(): void
    {
        $venta = $this->ventaACredito();
        // Pago general (sin elegir cuota) por el total de la deuda
        $this->post(route('ventas.credito.pago', $venta), ['monto' => 236, 'metodo_pago' => 'efectivo', 'fecha_pago' => now()->toDateString()]);

        $this->assertSame('pagado', $venta->cuentaPorCobrar->fresh()->estado);
        $this->assertSame(0, $venta->cuentaPorCobrar->cuotas()->where('estado', '!=', 'pagado')->count());
    }

    // ── validaciones de la solicitud ─────────────────────────────────────────

    public function test_no_se_vende_desde_un_almacen_que_no_es_tienda_aunque_tenga_stock(): void
    {
        $deposito = Almacen::create(['codigo' => 'ALM-D', 'nombre' => 'Depósito', 'tipo' => 'deposito', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($deposito->id, [['variante_id' => $this->variante->id, 'cantidad' => 30]], $this->admin->id);

        $this->post(route('ventas.store'), $this->datos(['almacen_id' => $deposito->id]))->assertSessionHasErrors('almacen_id');

        $this->assertSame(0, Venta::count());
        $this->assertSame(30, StockAlmacen::cantidadEn($this->producto->id, $deposito->id, $this->variante->id));
    }

    public function test_una_tienda_inactiva_no_vende(): void
    {
        $this->almacen->update(['estado' => 'inactivo']);

        $this->post(route('ventas.store'), $this->datos())->assertSessionHasErrors('almacen_id');
    }

    public function test_un_usuario_con_tienda_asignada_no_vende_desde_otra(): void
    {
        $otra = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Local B', 'tipo' => 'tienda', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($otra->id, [['variante_id' => $this->variante->id, 'cantidad' => 30]], $this->admin->id);
        $this->actingAs($this->usuario('Tienda'));

        $this->post(route('ventas.store'), $this->datos(['almacen_id' => $otra->id]))->assertSessionHasErrors('almacen_id');
        $this->assertSame(0, Venta::count());
    }

    public function test_no_se_vende_a_un_cliente_inactivo(): void
    {
        $this->cliente->update(['estado' => 'inactivo']);

        $this->post(route('ventas.store'), $this->datos())->assertSessionHasErrors('cliente_id');
        $this->assertSame(0, Venta::count());
    }

    public function test_cantidades_y_precios_extremos_se_rechazan_sin_error_500(): void
    {
        foreach ([['cantidad' => 999999999999, 'precio_unitario' => 10], ['cantidad' => 1, 'precio_unitario' => 99999999999999], ['cantidad' => 2.5, 'precio_unitario' => 10],
                  ['cantidad' => -1, 'precio_unitario' => 10], ['cantidad' => 0, 'precio_unitario' => 10], ['cantidad' => 1, 'precio_unitario' => -5], ['cantidad' => 'abc', 'precio_unitario' => 10]] as $linea) {
            $r = $this->post(route('ventas.store'), $this->datos(['detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id] + $linea]]));
            $this->assertLessThan(500, $r->getStatusCode(), json_encode($linea));
            $r->assertSessionHasErrors();
        }
        $this->assertSame(0, Venta::count());
        $this->assertSame(40 + 10, $this->stock());
    }

    public function test_una_cotizacion_de_un_deposito_no_se_convierte_en_venta(): void
    {
        $deposito = Almacen::create(['codigo' => 'ALM-D', 'nombre' => 'Depósito', 'tipo' => 'deposito', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($deposito->id, [['variante_id' => $this->variante->id, 'cantidad' => 30]], $this->admin->id);
        $this->post(route('cotizaciones.store'), [
            'cliente_id' => $this->cliente->id, 'almacen_id' => $deposito->id, 'validez_dias' => 7,
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 1, 'precio_unitario' => 100, 'incluye_igv' => 1]],
        ])->assertSessionHasNoErrors();
        $cotizacion = \App\Models\Cotizacion::firstOrFail();

        $this->post(route('cotizaciones.convertir', $cotizacion), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])->assertSessionHas('error');

        $this->assertSame(0, Venta::count());
        $this->assertSame('pendiente', $cotizacion->fresh()->estado);
        $this->assertSame(30, StockAlmacen::cantidadEn($this->producto->id, $deposito->id, $this->variante->id));
    }
}
