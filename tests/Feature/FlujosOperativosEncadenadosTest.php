<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleVenta;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CompraService;
use App\Services\CotizacionService;
use App\Services\EscanerService;
use App\Services\NotaPedidoService;
use App\Services\TrasladoService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recorridos de uso diario encadenados (ingreso → traslado → venta → devolución → salida → anulaciones)
 * comprobando después de cada paso que el stock cuadra y que nada queda a medias cuando una operación falla.
 */
class FlujosOperativosEncadenadosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $deposito;
    private Almacen $tienda;
    private Almacen $tienda2;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;
    private Cliente $cliente;
    private Proveedor $proveedor;
    private MotivoMovimiento $motivoSalida;
    private MotivoMovimiento $motivoIngreso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);

        $this->deposito = Almacen::create(['codigo' => 'ALM-D', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->tienda   = Almacen::create(['codigo' => 'ALM-T', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->tienda2  = Almacen::create(['codigo' => 'ALM-T2', 'nombre' => 'Tienda 2', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-T', 'nombre' => 'Sucursal Tienda', 'tipo' => 'tienda', 'almacen_id' => $this->tienda->id, 'estado' => 'activo']);

        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);

        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);
        $this->proveedor = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Proveedor SAC', 'estado' => 'activo']);
        $this->motivoSalida = MotivoMovimiento::create(['nombre' => 'Producto dañado', 'codigo' => 'DANADO', 'tipo' => 'salida', 'estado' => 'activo']);
        $this->motivoIngreso = MotivoMovimiento::create(['nombre' => 'Ajuste de inventario', 'codigo' => 'AJUSTE', 'tipo' => 'ingreso', 'estado' => 'activo']);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->tienda->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
    }

    // ── Ayudas ────────────────────────────────────────────────────────────

    private function en(ProductoVariante $v, Almacen $a): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, $a->id, $v->id);
    }

    /** producto = suma de almacenes = suma de variantes; variante = suma de sus filas; nada negativo. */
    private function cuadra(string $paso): void
    {
        $this->producto->refresh();
        $this->assertSame((int) StockAlmacen::where('producto_id', $this->producto->id)->sum('cantidad'), (int) $this->producto->stock_actual, "[$paso] producto != suma de almacenes");
        $suma = 0;
        foreach ([$this->v1, $this->v2] as $v) {
            $v->refresh();
            $this->assertSame((int) StockAlmacen::where('variante_id', $v->id)->sum('cantidad'), (int) $v->stock_actual, "[$paso] variante {$v->codigo_kyrios} != suma de sus filas");
            $suma += (int) $v->stock_actual;
        }
        $this->assertSame($suma, (int) $this->producto->stock_actual, "[$paso] producto != suma de variantes");
        $this->assertSame(0, StockAlmacen::where('cantidad', '<', 0)->count(), "[$paso] hay stock negativo");
    }

    private function ingreso(Almacen $a, ProductoVariante $v, int $n): void
    {
        $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => $a->id, 'documento_referencia' => 'GR-X', 'lineas' => [['variante_id' => $v->id, 'cantidad' => $n]]])->assertOk();
    }

    private function trasladar(Almacen $de, Almacen $a, array $lineas): string
    {
        return app(TrasladoService::class)->crearTraslado([
            'almacen_id' => $de->id, 'almacen_destino_id' => $a->id, 'user_id' => $this->admin->id,
            'productos' => array_map(fn ($l) => ['producto_id' => $this->producto->id, 'variante_id' => $l[0]->id, 'cantidad' => $l[1]], $lineas),
        ]);
    }

    private function mov(string $guia): MovimientoInventario
    {
        return MovimientoInventario::where('numero_guia', $guia)->orderBy('id')->firstOrFail();
    }

    private function vender(Almacen $a, ProductoVariante $v, int $n, string $comprobante = 'nota_venta')
    {
        return app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $a->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => $comprobante, 'subtotal' => 0, 'igv' => 0, 'total' => 0,
            'estado_pago' => 'pendiente', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $v->id, 'cantidad' => $n, 'precio_unitario' => 50, 'incluye_igv' => false]]);
    }

    private function salida(Almacen $a, ProductoVariante $v, int $n)
    {
        return $this->postJson(route('inventario.salida.confirmar'), [
            'almacen_id' => $a->id, 'motivo_id' => $this->motivoSalida->id, 'lineas' => [['variante_id' => $v->id, 'cantidad' => $n]],
        ]);
    }

    // ── Traslados ─────────────────────────────────────────────────────────

    public function test_traslado_completo_el_stock_sale_al_crear_y_entra_al_confirmar(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);

        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);
        $this->assertSame(6, $this->en($this->v1, $this->deposito), 'sale del origen al crear');
        $this->assertSame(0, $this->en($this->v1, $this->tienda), 'todavía no llega al destino');
        $this->cuadra('traslado en tránsito');

        $this->post(route('traslados.confirmar', $this->mov($guia)))->assertSessionHas('success');
        $this->assertSame(6, $this->en($this->v1, $this->deposito));
        $this->assertSame(4, $this->en($this->v1, $this->tienda));
        $this->assertSame('confirmado', $this->mov($guia)->estado);
        $this->assertNotNull($this->mov($guia)->usuario_confirma_id);
        $this->cuadra('traslado confirmado');
    }

    public function test_confirmar_dos_veces_no_acredita_dos_veces(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);
        $mov = $this->mov($guia);

        $this->post(route('traslados.confirmar', $mov))->assertSessionHas('success');
        $this->post(route('traslados.confirmar', $mov))->assertSessionHas('error');

        $this->assertSame(4, $this->en($this->v1, $this->tienda), 'se acredita una sola vez');
        $this->cuadra('doble confirmación');
    }

    public function test_un_traslado_con_una_linea_sin_stock_no_mueve_ninguna(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->ingreso($this->deposito, $this->v2, 1);

        try {
            $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 5], [$this->v2, 3]]);
            $this->fail('debía rechazarse por falta de stock de la segunda línea');
        } catch (\Exception $e) {
            $this->assertStringContainsString('stock insuficiente', $e->getMessage());
        }

        $this->assertSame(10, $this->en($this->v1, $this->deposito), 'la primera línea no se descuenta');
        $this->assertSame(1, $this->en($this->v2, $this->deposito));
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'transferencia')->count());
        $this->cuadra('traslado rechazado');
    }

    public function test_traslado_por_http_valida_origen_destino_y_cantidad(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);

        $base = ['almacen_id' => $this->deposito->id, 'almacen_destino_id' => $this->tienda->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 2]]];

        $this->post(route('traslados.store'), array_merge($base, ['almacen_destino_id' => $this->deposito->id]))->assertSessionHasErrors('almacen_destino_id');
        $this->post(route('traslados.store'), array_merge($base, ['productos' => []]))->assertSessionHasErrors('productos');
        $this->post(route('traslados.store'), array_merge($base, ['productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 0]]]))->assertSessionHasErrors('productos.0.cantidad');
        $this->post(route('traslados.store'), array_merge($base, ['productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => -3]]]))->assertSessionHasErrors('productos.0.cantidad');
        $this->post(route('traslados.store'), array_merge($base, ['productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 99]]]))->assertSessionHas('error');

        $this->assertSame(10, $this->en($this->v1, $this->deposito));
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'transferencia')->count());

        $this->post(route('traslados.store'), $base)->assertSessionHasNoErrors();
        $this->assertSame(8, $this->en($this->v1, $this->deposito));
    }

    public function test_misma_variante_repetida_en_el_traslado_se_rechaza(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->expectExceptionMessage('duplicados');
        $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 2], [$this->v1, 2]]);
    }

    public function test_anular_un_traslado_pendiente_devuelve_todo_y_ya_no_se_puede_confirmar(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->ingreso($this->deposito, $this->v2, 5);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4], [$this->v2, 5]]);
        $this->assertSame(0, $this->en($this->v2, $this->deposito));

        $this->post(route('traslados.anular', $this->mov($guia)), ['motivo' => 'error de digitación'])->assertSessionHas('success');
        $this->assertSame(10, $this->en($this->v1, $this->deposito));
        $this->assertSame(5, $this->en($this->v2, $this->deposito));
        $this->assertSame(2, MovimientoInventario::where('numero_guia', $guia)->where('estado', 'anulado')->count());
        $this->cuadra('traslado anulado');

        $this->post(route('traslados.confirmar', $this->mov($guia)))->assertSessionHas('error');
        $this->post(route('traslados.anular', $this->mov($guia)))->assertSessionHas('error');
        $this->assertSame(0, $this->en($this->v1, $this->tienda));
        $this->assertSame(10, $this->en($this->v1, $this->deposito), 'anular dos veces no devuelve dos veces');
    }

    public function test_un_traslado_confirmado_no_se_puede_anular(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);
        $this->post(route('traslados.confirmar', $this->mov($guia)));

        $this->post(route('traslados.anular', $this->mov($guia)))->assertSessionHas('error');
        $this->assertSame(4, $this->en($this->v1, $this->tienda));
        $this->assertSame(6, $this->en($this->v1, $this->deposito));
    }

    public function test_eliminar_un_traslado_confirmado_devuelve_el_stock_al_origen(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);
        $this->post(route('traslados.confirmar', $this->mov($guia)));

        $this->delete(route('traslados.eliminar', $this->mov($guia)), ['motivo' => 'registrado por error'])->assertSessionHas('success');

        $this->assertSame(10, $this->en($this->v1, $this->deposito));
        $this->assertSame(0, $this->en($this->v1, $this->tienda));
        $this->cuadra('traslado confirmado eliminado');
        $this->assertSame(0, MovimientoInventario::where('numero_guia', $guia)->noEliminados()->count());
    }

    public function test_no_se_elimina_un_traslado_cuyo_stock_ya_se_vendio_en_destino(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);
        $this->post(route('traslados.confirmar', $this->mov($guia)));
        $this->vender($this->tienda, $this->v1, 3);

        $this->delete(route('traslados.eliminar', $this->mov($guia)))->assertSessionHas('error');

        $this->assertSame(1, $this->en($this->v1, $this->tienda), 'no se toca nada');
        $this->assertSame(6, $this->en($this->v1, $this->deposito));
        $this->assertSame('confirmado', $this->mov($guia)->estado);
        $this->cuadra('eliminación rechazada');
    }

    public function test_cadena_deposito_tienda_tienda2_y_venta_en_el_ultimo(): void
    {
        $this->ingreso($this->deposito, $this->v1, 20);
        $g1 = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 12]]);
        $this->post(route('traslados.confirmar', $this->mov($g1)));
        $g2 = $this->trasladar($this->tienda, $this->tienda2, [[$this->v1, 5]]);
        $this->cuadra('segundo traslado en tránsito');
        $this->post(route('traslados.confirmar', $this->mov($g2)));

        $this->assertSame(8, $this->en($this->v1, $this->deposito));
        $this->assertSame(7, $this->en($this->v1, $this->tienda));
        $this->assertSame(5, $this->en($this->v1, $this->tienda2));
        $this->assertSame(20, (int) $this->v1->fresh()->stock_actual);

        // Lo que ya salió de la tienda no se puede volver a mover desde ella
        $this->expectExceptionMessage('stock insuficiente');
        $this->trasladar($this->tienda, $this->tienda2, [[$this->v1, 8]]);
    }

    public function test_el_traslado_pendiente_no_cuenta_como_stock_vendible_en_destino(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 4]]);

        $this->expectExceptionMessage('Stock insuficiente');
        $this->vender($this->tienda, $this->v1, 1);
    }

    // ── Ingresos y salidas ────────────────────────────────────────────────

    public function test_ingreso_y_salida_dejan_kardex_continuo(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->ingreso($this->deposito, $this->v1, 5);
        $this->salida($this->deposito, $this->v1, 3)->assertOk();
        $this->vender($this->deposito, $this->v1, 2);

        $movs = MovimientoInventario::where('variante_id', $this->v1->id)->where('almacen_id', $this->deposito->id)->orderBy('id')->get();
        $this->assertCount(4, $movs);
        $esperado = 0;
        foreach ($movs as $m) {
            $this->assertSame($esperado, (int) $m->stock_anterior, "kardex roto en el movimiento {$m->id} ({$m->tipo_movimiento})");
            $esperado = (int) $m->stock_nuevo;
        }
        $this->assertSame($esperado, $this->en($this->v1, $this->deposito), 'el último stock_nuevo es el stock real');
        $this->assertSame(10, $this->en($this->v1, $this->deposito));
        $this->cuadra('kardex');
    }

    public function test_salida_de_mas_de_lo_que_hay_no_toca_nada_ni_siquiera_otras_lineas(): void
    {
        $this->ingreso($this->deposito, $this->v1, 5);
        $this->ingreso($this->deposito, $this->v2, 1);

        $this->postJson(route('inventario.salida.confirmar'), [
            'almacen_id' => $this->deposito->id, 'motivo_id' => $this->motivoSalida->id,
            'lineas' => [['variante_id' => $this->v1->id, 'cantidad' => 2], ['variante_id' => $this->v2->id, 'cantidad' => 9]],
        ])->assertStatus(422);

        $this->assertSame(5, $this->en($this->v1, $this->deposito));
        $this->assertSame(1, $this->en($this->v2, $this->deposito));
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'salida')->count());
        $this->cuadra('salida rechazada');
    }

    public function test_salida_o_ingreso_con_cantidades_absurdas_se_rechazan(): void
    {
        $this->ingreso($this->deposito, $this->v1, 5);
        foreach ([0, -1] as $n) {
            $this->postJson(route('inventario.salida.confirmar'), ['almacen_id' => $this->deposito->id, 'motivo_id' => $this->motivoSalida->id, 'lineas' => [['variante_id' => $this->v1->id, 'cantidad' => $n]]])->assertStatus(422);
            $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => $this->deposito->id, 'lineas' => [['variante_id' => $this->v1->id, 'cantidad' => $n]]])->assertStatus(422);
        }
        $this->assertSame(5, $this->en($this->v1, $this->deposito));
        $this->cuadra('cantidades inválidas');
    }

    public function test_ingreso_a_un_almacen_o_variante_inexistente_se_rechaza(): void
    {
        $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => 9999, 'lineas' => [['variante_id' => $this->v1->id, 'cantidad' => 1]]])->assertStatus(422);
        $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => $this->deposito->id, 'lineas' => [['variante_id' => 9999, 'cantidad' => 1]]])->assertStatus(422);
        $this->assertSame(0, StockAlmacen::count());
    }

    // ── Compras ───────────────────────────────────────────────────────────

    private function comprar(Almacen $a, ProductoVariante $v, int $n, string $factura = 'F001-1')
    {
        return app(CompraService::class)->registrarCompra([
            'codigo' => 'C-' . $factura, 'proveedor_id' => $this->proveedor->id, 'almacen_id' => $a->id, 'user_id' => $this->admin->id,
            'numero_factura' => $factura, 'fecha' => now()->toDateString(), 'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_cambio' => 1,
            'tipo_compra' => 'local', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'total_pen' => 0, 'estado' => 'registrado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $v->id, 'cantidad' => $n, 'precio_unitario' => 10, 'descuento' => 0]]);
    }

    public function test_compra_ingresa_stock_y_su_anulacion_lo_retira(): void
    {
        $compra = $this->comprar($this->deposito, $this->v1, 8);
        $this->assertSame(8, $this->en($this->v1, $this->deposito));
        $this->cuadra('compra');

        app(CompraService::class)->anularCompra($compra->fresh(), 'prueba');
        $this->assertSame(0, $this->en($this->v1, $this->deposito));
        $this->cuadra('compra anulada');

        $this->expectExceptionMessage('ya está anulada');
        app(CompraService::class)->anularCompra($compra->fresh());
    }

    public function test_no_se_anula_una_compra_cuyo_stock_ya_se_trasladó_o_vendió(): void
    {
        $compra = $this->comprar($this->deposito, $this->v1, 8);
        $guia = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 5]]);
        $this->post(route('traslados.confirmar', $this->mov($guia)));

        try {
            app(CompraService::class)->anularCompra($compra->fresh(), 'prueba');
            $this->fail('no debía dejar anular');
        } catch (\Exception $e) {
            $this->assertStringContainsString('No se puede anular', $e->getMessage());
        }

        $this->assertSame(3, $this->en($this->v1, $this->deposito));
        $this->assertNotSame('anulado', $compra->fresh()->estado);
        $this->cuadra('anulación de compra rechazada');
    }

    public function test_dos_compras_de_la_misma_variante_en_distintos_almacenes_no_se_mezclan(): void
    {
        $this->comprar($this->deposito, $this->v1, 4, 'F001-1');
        $this->comprar($this->tienda, $this->v1, 6, 'F001-2');
        $this->assertSame(4, $this->en($this->v1, $this->deposito));
        $this->assertSame(6, $this->en($this->v1, $this->tienda));
        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual);
        $this->cuadra('compras en dos almacenes');
    }

    // ── Ventas ────────────────────────────────────────────────────────────

    public function test_venta_descuenta_su_almacen_y_anularla_repone_exactamente_lo_vendido(): void
    {
        $this->ingreso($this->tienda, $this->v1, 10);
        $this->ingreso($this->deposito, $this->v1, 10);

        $venta = $this->vender($this->tienda, $this->v1, 4);
        $this->assertSame(6, $this->en($this->v1, $this->tienda));
        $this->assertSame(10, $this->en($this->v1, $this->deposito), 'el otro almacén no se toca');
        $this->cuadra('venta');

        app(VentaService::class)->anularVenta($venta->fresh());
        $this->assertSame(10, $this->en($this->v1, $this->tienda));
        $this->assertSame('anulado', $venta->fresh()->estado_pago);
        $this->cuadra('venta anulada');
    }

    public function test_venta_de_dos_variantes_falla_completa_si_una_no_alcanza(): void
    {
        $this->ingreso($this->tienda, $this->v1, 10);
        $this->ingreso($this->tienda, $this->v2, 1);

        try {
            app(VentaService::class)->crearVenta([
                'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->tienda->id, 'fecha' => now()->toDateString(),
                'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
                'estado_pago' => 'pendiente', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
            ], [
                ['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 3, 'precio_unitario' => 50, 'incluye_igv' => false],
                ['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 2, 'precio_unitario' => 50, 'incluye_igv' => false],
            ]);
            $this->fail('debía fallar por stock');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }

        $this->assertSame(10, $this->en($this->v1, $this->tienda));
        $this->assertSame(1, $this->en($this->v2, $this->tienda));
        $this->assertSame(0, \App\Models\Venta::count(), 'no queda la venta a medias');
        $this->cuadra('venta fallida');
    }

    public function test_venta_devolucion_parcial_y_anulacion_de_la_devolucion(): void
    {
        $this->ingreso($this->tienda, $this->v1, 10);
        $venta = $this->vender($this->tienda, $this->v1, 5);
        $venta->update(['estado_pago' => 'pagado']);
        $detalle = DetalleVenta::where('venta_id', $venta->id)->first();

        $this->post(route('devoluciones.store'), ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->tienda->id, 'cantidades' => [$detalle->id => 2]])->assertSessionHas('success');
        $this->assertSame(7, $this->en($this->v1, $this->tienda));
        $this->cuadra('devolución');

        // Segunda devolución: solo quedan 3 por devolver
        $this->post(route('devoluciones.store'), ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->tienda->id, 'cantidades' => [$detalle->id => 9]]);
        $this->assertLessThanOrEqual(10, $this->en($this->v1, $this->tienda), 'nunca se devuelve más de lo vendido');
        $this->cuadra('segunda devolución');
    }

    public function test_la_variante_sin_stock_en_ese_almacen_no_se_vende_aunque_haya_en_otro(): void
    {
        $this->ingreso($this->deposito, $this->v1, 10);
        $this->expectExceptionMessage('Stock insuficiente');
        $this->vender($this->tienda, $this->v1, 1);
    }

    public function test_dos_ventas_seguidas_que_juntas_superan_el_stock_la_segunda_falla(): void
    {
        $this->ingreso($this->tienda, $this->v1, 5);
        $this->vender($this->tienda, $this->v1, 3);

        try {
            $this->vender($this->tienda, $this->v1, 3);
            $this->fail('la segunda venta no debía pasar');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }
        $this->assertSame(2, $this->en($this->v1, $this->tienda));
        $this->cuadra('segunda venta rechazada');
    }

    // ── Reserva de nota de pedido frente a otros movimientos de stock ─────

    private function conNotaApartando(int $cantidad): void
    {
        $c = app(CotizacionService::class)->crear(
            ['user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->tienda->id],
            [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => $cantidad, 'precio_unitario' => 118, 'incluye_igv' => true]]
        );
        app(CotizacionService::class)->cambiarEstado($c, 'aceptada');
        app(NotaPedidoService::class)->crearDesdeCotizacion($c, $this->admin);
    }

    public function test_un_traslado_no_puede_llevarse_lo_apartado_por_una_nota_de_pedido(): void
    {
        $this->ingreso($this->tienda, $this->v1, 10);
        $this->conNotaApartando(8);

        try {
            $this->trasladar($this->tienda, $this->tienda2, [[$this->v1, 5]]);
            $this->fail('lo apartado no se puede trasladar');
        } catch (\Exception $e) {
            $this->assertStringContainsString('apartada', $e->getMessage());
        }
        $this->assertSame(10, $this->en($this->v1, $this->tienda));

        // Lo que sí está libre (2) se puede trasladar
        $this->trasladar($this->tienda, $this->tienda2, [[$this->v1, 2]]);
        $this->assertSame(8, $this->en($this->v1, $this->tienda));
    }

    public function test_una_salida_no_puede_sacar_lo_apartado_por_una_nota_de_pedido(): void
    {
        $this->ingreso($this->tienda, $this->v1, 10);
        $this->conNotaApartando(8);

        $this->salida($this->tienda, $this->v1, 5)->assertStatus(422);
        $this->assertSame(10, $this->en($this->v1, $this->tienda));

        $this->salida($this->tienda, $this->v1, 2)->assertOk();
        $this->assertSame(8, $this->en($this->v1, $this->tienda));
        $this->cuadra('salida con reserva');
    }

    // ── Movimiento manual (ingreso / ajuste desde la pantalla de movimientos) ──

    public function test_movimiento_manual_de_ingreso_suma_y_deja_registro(): void
    {
        $r = $this->post(route('inventario.movimientos.store'), [
            'producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'almacen_id' => $this->deposito->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 7, 'motivo_movimiento_id' => $this->motivoIngreso->id,
        ]);
        $r->assertSessionHasNoErrors();

        $this->assertSame(7, $this->en($this->v1, $this->deposito));
        $this->assertSame(1, MovimientoInventario::where('tipo_movimiento', 'ingreso')->count());
        $this->cuadra('movimiento manual');
    }

    public function test_movimiento_manual_de_salida_no_deja_stock_negativo(): void
    {
        $this->ingreso($this->deposito, $this->v1, 3);
        $this->post(route('inventario.movimientos.store'), [
            'producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'almacen_id' => $this->deposito->id,
            'tipo_movimiento' => 'salida', 'cantidad' => 10, 'motivo_movimiento_id' => $this->motivoSalida->id,
        ]);
        $this->assertSame(3, $this->en($this->v1, $this->deposito));
        $this->cuadra('salida manual excesiva');
    }

    // ── Recorrido de un día completo ──────────────────────────────────────

    public function test_dia_completo_el_stock_cuadra_en_cada_paso(): void
    {
        $this->comprar($this->deposito, $this->v1, 30, 'F001-10');
        $this->cuadra('1 compra');
        $this->ingreso($this->deposito, $this->v2, 12);
        $this->cuadra('2 ingreso escáner');

        $g = $this->trasladar($this->deposito, $this->tienda, [[$this->v1, 10], [$this->v2, 6]]);
        $this->cuadra('3 traslado en tránsito');
        $this->post(route('traslados.confirmar', $this->mov($g)));
        $this->cuadra('4 traslado confirmado');

        $v = $this->vender($this->tienda, $this->v1, 4);
        $this->vender($this->tienda, $this->v2, 6);
        $this->cuadra('5 ventas');
        $this->assertSame(0, $this->en($this->v2, $this->tienda));

        app(VentaService::class)->anularVenta($v->fresh());
        $this->cuadra('6 venta anulada');

        $this->salida($this->deposito, $this->v1, 5)->assertOk();
        $this->cuadra('7 salida por daño');

        $g2 = $this->trasladar($this->tienda, $this->tienda2, [[$this->v1, 3]]);
        $this->post(route('traslados.anular', $this->mov($g2)));
        $this->cuadra('8 traslado anulado');

        $this->assertSame(15, $this->en($this->v1, $this->deposito));
        $this->assertSame(10, $this->en($this->v1, $this->tienda));
        $this->assertSame(6, $this->en($this->v2, $this->deposito));
        $this->assertSame(0, $this->en($this->v2, $this->tienda));
        $this->assertSame(31, (int) $this->producto->fresh()->stock_actual);
    }
}
