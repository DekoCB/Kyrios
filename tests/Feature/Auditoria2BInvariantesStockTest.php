<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Catalogo\MotivoMovimiento;
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
use Tests\TestCase;

/**
 * Invariante de stock: tras cada operación, producto = suma de sus almacenes = suma de sus variantes,
 * y cada variante = suma de sus filas por almacén.
 */
class Auditoria2BInvariantesStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;
    private Cliente $cliente;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);

        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Administración', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-INV', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);
        $this->proveedor = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Proveedor SAC', 'estado' => 'activo']);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->b->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
    }

    private function invariante(string $paso): void
    {
        $this->producto->refresh();
        $total = (int) StockAlmacen::where('producto_id', $this->producto->id)->sum('cantidad');
        $this->assertSame($total, (int) $this->producto->stock_actual, "[$paso] producto.stock_actual != suma de stock_almacen");

        $sumaVariantes = 0;
        foreach ([$this->v1, $this->v2] as $v) {
            $v->refresh();
            $filas = (int) StockAlmacen::where('variante_id', $v->id)->sum('cantidad');
            $this->assertSame($filas, (int) $v->stock_actual, "[$paso] variante {$v->id}: stock_actual != suma de sus filas");
            $sumaVariantes += (int) $v->stock_actual;
        }
        $this->assertSame($sumaVariantes, (int) $this->producto->stock_actual, "[$paso] producto != suma de variantes");
        $this->assertSame(0, (int) StockAlmacen::where('cantidad', '<', 0)->count(), "[$paso] hay stock negativo");
        $this->assertSame(0, (int) StockAlmacen::where('producto_id', $this->producto->id)->whereNull('variante_id')->sum('cantidad'), "[$paso] stock sin variante en producto con variantes");
    }

    private function enAlmacen(ProductoVariante $v, Almacen $a): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, $a->id, $v->id);
    }

    public function test_el_stock_cuadra_despues_de_cada_operacion(): void
    {
        $esc = app(EscanerService::class);

        // 1) Escáner: entrada
        $esc->recibir($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 20], ['variante_id' => $this->v2->id, 'cantidad' => 10]], $this->admin->id);
        $this->invariante('entrada escáner');

        // 2) Compra y su anulación
        $compra = app(CompraService::class)->registrarCompra([
            'codigo' => 'C-INV-1', 'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->a->id, 'user_id' => $this->admin->id,
            'numero_factura' => 'F001-1', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_cambio' => 1,
            'tipo_compra' => 'local', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'total_pen' => 0, 'estado' => 'registrado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 5, 'precio_unitario' => 10, 'descuento' => 0]]);
        $this->assertSame(25, $this->enAlmacen($this->v1, $this->a));
        $this->invariante('compra');
        app(CompraService::class)->anularCompra($compra->fresh(), 'prueba');
        $this->assertSame(20, $this->enAlmacen($this->v1, $this->a));
        $this->invariante('anulación de compra');

        // 3) Traslado: crear, confirmar y anular otro
        $guia = app(TrasladoService::class)->crearTraslado([
            'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 8], ['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 3]],
        ]);
        $this->invariante('traslado creado (en tránsito)');
        $movs = MovimientoInventario::where('numero_guia', $guia)->get();
        $this->assertCount(2, $movs);
        app(TrasladoService::class)->confirmarRecepcion($movs[0]->id, $this->admin->id);
        $this->assertSame(8, $this->enAlmacen($this->v1, $this->b));
        $this->invariante('traslado confirmado');

        $guia2 = app(TrasladoService::class)->crearTraslado([
            'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 4]],
        ]);
        $m2 = MovimientoInventario::where('numero_guia', $guia2)->first();
        $this->invariante('segundo traslado creado');
        app(TrasladoService::class)->anularTraslado($m2->id, $this->admin->id, 'prueba');
        $this->assertSame(12, $this->enAlmacen($this->v1, $this->a));
        $this->invariante('traslado anulado');

        // 4) Movimientos manuales: salida, ingreso, ajuste y transferencia directa
        $motivo = MotivoMovimiento::create(['nombre' => 'Prueba', 'codigo' => 'PRUEBA', 'tipo' => 'salida', 'estado' => 'activo']);
        foreach ([
            ['tipo_movimiento' => 'salida', 'cantidad' => 2, 'almacen_id' => $this->a->id],
            ['tipo_movimiento' => 'ingreso', 'cantidad' => 7, 'almacen_id' => $this->a->id],
            ['tipo_movimiento' => 'ajuste', 'stock_nuevo' => 9, 'cantidad' => 9, 'almacen_id' => $this->a->id],
            ['tipo_movimiento' => 'transferencia', 'cantidad' => 3, 'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id],
            ['tipo_movimiento' => 'merma', 'cantidad' => 1, 'almacen_id' => $this->b->id],
        ] as $i => $datos) {
            MovimientoInventario::registrarMovimiento($datos + [
                'producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'user_id' => $this->admin->id,
                'motivo_movimiento_id' => $motivo->id, 'motivo' => 'prueba',
            ]);
            $this->invariante('movimiento manual ' . $datos['tipo_movimiento']);
        }

        // 5) Escáner: salida
        $esc->retirar($this->b->id, [['variante_id' => $this->v1->id, 'cantidad' => 2]], $this->admin->id, $motivo->id, 'obra');
        $this->invariante('salida escáner');

        // 6) Venta y anulación
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->b->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 2, 'precio_unitario' => 50, 'incluye_igv' => false]]);
        $this->invariante('venta');
        app(VentaService::class)->anularVenta($venta->fresh());
        $this->invariante('anulación de venta');

        // 7) Guía de remisión (HTTP) entre almacenes
        $this->post(route('guias-remision.store'), [
            'tipo_destino' => 'almacen', 'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'motivo_traslado' => '04',
            'modalidad' => 'privado', 'fecha_traslado' => now()->toDateString(), 'direccion_partida' => 'AV X 1', 'ubigeo_partida' => '150101',
            'direccion_llegada' => 'AV Y 2', 'ubigeo_llegada' => '150101', 'peso_total' => 3, 'numero_bultos' => 1,
            'conductor_dni' => '41340629', 'conductor_nombre' => 'CESAR LEE', 'conductor_licencia' => 'Q41340629', 'placa_vehiculo' => 'ABC123',
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();
        $this->invariante('guía de remisión creada');
    }

    public function test_dos_descuentos_seguidos_no_dejan_stock_negativo(): void
    {
        $esc = app(EscanerService::class);
        $esc->recibir($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 5]], $this->admin->id);
        $motivo = MotivoMovimiento::create(['nombre' => 'Prueba', 'codigo' => 'PRUEBA', 'tipo' => 'salida', 'estado' => 'activo']);

        $esc->retirar($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 4]], $this->admin->id, $motivo->id);
        try {
            $esc->retirar($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 4]], $this->admin->id, $motivo->id);
            $this->fail('El segundo retiro debía fallar por falta de stock');
        } catch (\Throwable $e) {
            $this->assertStringContainsStringIgnoringCase('stock', $e->getMessage());
        }
        $this->assertSame(1, $this->enAlmacen($this->v1, $this->a));
        $this->invariante('retiros seguidos');
    }
}
