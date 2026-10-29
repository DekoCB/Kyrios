<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CompraService;
use App\Services\EscanerService;
use App\Services\TrasladoService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * El stock se guarda por producto + variante + almacén (una fila en stock_almacen por combinación).
 */
class StockPorVarianteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);

        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Local B', 'tipo' => 'tienda', 'estado' => 'activo']);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create([
            'codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo',
        ]);
        $this->v1 = $this->variante('KY-LU2501-0001', 'negro(a)');
        $this->v2 = $this->variante('KY-LU2501-0002', 'blanco(a)');

        // Las ventas al contado exigen caja abierta
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->a->id, 'fecha' => now()->toDateString(),
            'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
    }

    private function variante(string $codigo, string $acabado): ProductoVariante
    {
        return ProductoVariante::create([
            'producto_id' => $this->producto->id, 'sku' => $codigo, 'codigo_kyrios' => $codigo,
            'acabado_1' => $acabado, 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => 'activo',
        ]);
    }

    private function stock(ProductoVariante $v, Almacen $a): int
    {
        return StockAlmacen::cantidadEn($v->producto_id, $a->id, $v->id);
    }

    private function comprar(Almacen $almacen, array $cantidades): void
    {
        $proveedor = Proveedor::first() ?? Proveedor::create(['ruc' => '20100000001', 'razon_social' => 'Cliente', 'estado' => 'activo']);

        $detalles = [];
        foreach ($cantidades as $varianteId => $cantidad) {
            $detalles[] = ['producto_id' => $this->producto->id, 'variante_id' => $varianteId, 'cantidad' => $cantidad,
                'precio_unitario' => 10, 'subtotal' => 10 * $cantidad];
        }

        app(CompraService::class)->registrarCompra([
            'codigo' => 'C-' . uniqid(), 'proveedor_id' => $proveedor->id, 'user_id' => $this->admin->id, 'almacen_id' => $almacen->id,
            'numero_factura' => 'F001-' . uniqid(), 'fecha' => now()->toDateString(), 'forma_pago' => 'contado', 'condicion_pago' => 0,
            'tipo_moneda' => 'PEN', 'tipo_cambio' => 1, 'incluye_igv' => false, 'subtotal' => 100, 'igv' => 18, 'total' => 118,
            'total_pen' => 118, 'tipo_operacion' => '01', 'tipo_compra' => 'local', 'estado' => 'registrado',
        ], $detalles);
    }

    private function datosVenta(Almacen $almacen): array
    {
        $cliente = Cliente::first() ?? Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);

        return ['user_id' => $this->admin->id, 'cliente_id' => $cliente->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
            'estado_pago' => 'pendiente', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo'];
    }

    private function lineaVenta(ProductoVariante $v, int $cantidad): array
    {
        return [['producto_id' => $v->producto_id, 'variante_id' => $v->id, 'cantidad' => $cantidad, 'precio_unitario' => 30, 'incluye_igv' => false]];
    }

    // ── Compra ────────────────────────────────────────────────────────────

    public function test_la_compra_ingresa_el_stock_de_cada_variante_en_su_almacen(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);

        $this->assertSame(10, $this->stock($this->v1, $this->a));
        $this->assertSame(4, $this->stock($this->v2, $this->a));
        $this->assertSame(0, $this->stock($this->v1, $this->b));
        $this->assertSame(14, StockAlmacen::totalEn($this->producto->id, $this->a->id));
        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual);
        $this->assertSame(14, (int) $this->producto->fresh()->stock_actual);
    }

    // ── Traslados ─────────────────────────────────────────────────────────

    public function test_un_traslado_mueve_solo_el_stock_de_esa_variante(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);
        $service = app(TrasladoService::class);

        $guia = $service->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 3]]]);

        $this->assertSame(7, $this->stock($this->v1, $this->a), 'se reserva en origen');
        $this->assertSame(4, $this->stock($this->v2, $this->a), 'la otra variante no se toca');

        $service->confirmarRecepcion(MovimientoInventario::where('numero_guia', $guia)->first()->id, $this->admin->id);

        $this->assertSame(3, $this->stock($this->v1, $this->b));
        $this->assertSame(0, $this->stock($this->v2, $this->b));
        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual, 'el stock global no cambia al trasladar');
    }

    public function test_no_se_puede_trasladar_mas_de_lo_que_hay_de_esa_variante(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);

        $this->expectExceptionMessage('stock insuficiente');
        app(TrasladoService::class)->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 5]]]);
    }

    public function test_un_traslado_de_un_producto_con_variantes_exige_elegir_variante(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10]);

        $this->expectExceptionMessage('variante');
        app(TrasladoService::class)->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'cantidad' => 1]]]);
    }

    public function test_anular_un_traslado_pendiente_devuelve_el_stock_a_la_variante(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10]);
        $service = app(TrasladoService::class);

        $guia = $service->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 2]]]);
        $this->assertSame(8, $this->stock($this->v1, $this->a));

        $service->anularTraslado(MovimientoInventario::where('numero_guia', $guia)->first()->id, $this->admin->id, 'prueba');

        $this->assertSame(10, $this->stock($this->v1, $this->a));
    }

    // ── Movimientos manuales ──────────────────────────────────────────────

    public function test_un_movimiento_manual_usa_la_variante_indicada(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);

        MovimientoInventario::registrarMovimiento(['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id,
            'almacen_id' => $this->a->id, 'tipo_movimiento' => 'salida', 'cantidad' => 1, 'motivo' => 'prueba', 'user_id' => $this->admin->id]);

        $this->assertSame(3, $this->stock($this->v2, $this->a));
        $this->assertSame(3, (int) $this->v2->fresh()->stock_actual);
        $this->assertSame(10, $this->stock($this->v1, $this->a));
    }

    public function test_un_movimiento_manual_sin_variante_se_rechaza(): void
    {
        $this->expectExceptionMessage('variante');
        MovimientoInventario::registrarMovimiento(['producto_id' => $this->producto->id, 'almacen_id' => $this->a->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 1, 'motivo' => 'prueba', 'user_id' => $this->admin->id]);
    }

    // ── Ventas ────────────────────────────────────────────────────────────

    public function test_la_venta_descuenta_la_variante_vendida_en_el_almacen_de_la_venta(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);

        $venta = app(VentaService::class)->crearVenta($this->datosVenta($this->a), $this->lineaVenta($this->v1, 2));

        $this->assertSame(8, $this->stock($this->v1, $this->a));
        $this->assertSame(4, $this->stock($this->v2, $this->a));
        $this->assertTrue(MovimientoInventario::where('variante_id', $this->v1->id)->where('motivo', 'Venta')->exists());

        app(VentaService::class)->anularVenta($venta->fresh());
        $this->assertSame(10, $this->stock($this->v1, $this->a), 'anular la venta devuelve el stock a la variante');
    }

    public function test_no_se_vende_mas_de_lo_que_hay_de_la_variante_en_ese_almacen(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10, $this->v2->id => 4]);

        $this->expectExceptionMessage('Stock insuficiente');
        app(VentaService::class)->crearVenta($this->datosVenta($this->a), $this->lineaVenta($this->v2, 5));
    }

    public function test_el_stock_de_otro_almacen_no_sirve_para_vender(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10]);

        $this->expectExceptionMessage('Stock insuficiente');
        app(VentaService::class)->crearVenta($this->datosVenta($this->b), $this->lineaVenta($this->v1, 1));
    }

    // ── Escáner ───────────────────────────────────────────────────────────

    public function test_la_recepcion_con_escaner_suma_a_la_variante_y_junta_las_lecturas(): void
    {
        $n = app(EscanerService::class)->recibir($this->b->id, [
            ['variante_id' => $this->v2->id, 'cantidad' => 3],
            ['variante_id' => $this->v2->id, 'cantidad' => 2],
        ], $this->admin->id, 'GR-001');

        $this->assertSame(1, $n);
        $this->assertSame(5, $this->stock($this->v2, $this->b));
        $this->assertSame(5, (int) $this->v2->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['variante_id' => $this->v2->id, 'almacen_id' => $this->b->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 5, 'documento_referencia' => 'GR-001']);
    }

    // ── Reconciliación e integridad ───────────────────────────────────────

    public function test_reconciliar_corrige_el_stock_global_de_la_variante(): void
    {
        $this->comprar($this->a, [$this->v1->id => 10]);
        $this->v1->update(['stock_actual' => 999]);

        $this->artisan('inventario:reconciliar', ['--producto' => $this->producto->id])->assertSuccessful();

        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual);
    }

    public function test_no_puede_haber_dos_filas_para_el_mismo_producto_variante_y_almacen(): void
    {
        StockAlmacen::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'almacen_id' => $this->a->id, 'cantidad' => 1]);

        $this->expectException(QueryException::class);
        StockAlmacen::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'almacen_id' => $this->a->id, 'cantidad' => 1]);
    }

    public function test_tampoco_se_duplica_la_fila_de_un_producto_sin_variante(): void
    {
        StockAlmacen::create(['producto_id' => $this->producto->id, 'variante_id' => null, 'almacen_id' => $this->a->id, 'cantidad' => 1]);

        $this->expectException(QueryException::class);
        StockAlmacen::create(['producto_id' => $this->producto->id, 'variante_id' => null, 'almacen_id' => $this->a->id, 'cantidad' => 1]);
    }

    public function test_variantes_distintas_pueden_convivir_en_el_mismo_almacen(): void
    {
        StockAlmacen::fila($this->producto->id, $this->a->id, $this->v1->id)->increment('cantidad', 2);
        StockAlmacen::fila($this->producto->id, $this->a->id, $this->v2->id)->increment('cantidad', 3);
        StockAlmacen::fila($this->producto->id, $this->a->id, $this->v1->id)->increment('cantidad', 1);

        $this->assertSame(2, StockAlmacen::where('almacen_id', $this->a->id)->count());
        $this->assertSame(3, $this->stock($this->v1, $this->a));
        $this->assertSame(6, StockAlmacen::totalEn($this->producto->id, $this->a->id));
    }
}
