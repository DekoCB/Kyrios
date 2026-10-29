<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Role;
use App\Models\Almacen;
use App\Models\Proveedor;
use App\Models\Cliente;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\DetalleCompra;
use App\Models\Compra;
use App\Models\Venta;
use App\Services\CompraService;
use App\Services\VentaService;

class CompraVentaFlowTest extends TestCase
{
    // No usamos DatabaseTransactions porque los servicios hacen DB::transaction()
    // interno que desincroniza el nivel de transacción.
    // En su lugar limpiamos tablas relevantes en tearDown().

    private Role      $roleAdmin;
    private User      $admin;
    private Almacen   $almacen;
    private Proveedor $proveedor;
    private Cliente   $cliente;
    private Categoria $categoria;
    private \App\Models\Caja $caja;

    // IDs creados en cada test para limpiar en tearDown
    private array $createdCompras  = [];
    private array $createdVentas   = [];
    private array $createdProductos = [];
    private array $createdUsers    = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Roles: firstOrCreate para no fallar si ya existen
        $this->roleAdmin = Role::firstOrCreate(['nombre' => 'Administración']);
        Role::firstOrCreate(['nombre' => 'Logística']);
        Role::firstOrCreate(['nombre' => 'Administración']);

        // Datos únicos por test usando uniqid
        $uid = uniqid();

        $this->admin = User::create([
            'name'     => 'Admin Test',
            'email'    => "admin_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $this->roleAdmin->id,
        ]);
        $this->createdUsers[] = $this->admin->id;

        $this->almacen = Almacen::create([
            'nombre' => "Almacén Test {$uid}",
            'codigo' => "ALM-{$uid}",
            'tipo'   => 'principal',
            'estado' => 'activo',
        ]);

        $this->proveedor = Proveedor::create([
            'ruc'          => substr('20' . $uid, 0, 11),
            'razon_social' => "Proveedor {$uid}",
            'estado'       => 'activo',
        ]);

        $this->cliente = Cliente::create([
            'tipo_documento'   => 'DNI',
            'numero_documento' => (string)rand(10000000, 99999999),
            'nombre'           => "Cliente {$uid}",
            'estado'           => 'activo',
        ]);

        $this->categoria = Categoria::firstOrCreate(
            ['codigo' => 'LUM-TEST'],
            ['nombre' => 'Luminarias Test', 'estado' => 'activo']
        );

        // Las ventas al contado exigen caja abierta del usuario (regla de VentaService)
        $this->caja = \App\Models\Caja::create([
            'user_id'        => $this->admin->id,
            'almacen_id'     => $this->almacen->id,
            'fecha'          => now()->toDateString(),
            'fecha_apertura' => now(),
            'monto_inicial'  => 0,
            'estado'         => 'abierta',
        ]);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        // Limpiar de adentro hacia afuera (respetar FK)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->createdVentas)) {
            \App\Models\DetalleVenta::whereIn('venta_id', $this->createdVentas)->delete();
            Venta::whereIn('id', $this->createdVentas)->delete();
        }
        if (!empty($this->createdCompras)) {
            DetalleCompra::whereIn('compra_id', $this->createdCompras)->delete();
            Compra::whereIn('id', $this->createdCompras)->delete();
        }
        // Limpiar stock del almacén de test siempre (cubre stock creado manualmente)
        StockAlmacen::where('almacen_id', $this->almacen->id)->delete();
        if (!empty($this->createdProductos)) {
            Producto::whereIn('id', $this->createdProductos)->delete();
        }

        // Limpiar movimientos del almacén (los crea CompraService al registrar entradas)
        \App\Models\MovimientoInventario::where('almacen_id', $this->almacen->id)
            ->orWhere('almacen_destino_id', $this->almacen->id)
            ->delete();

        \App\Models\MovimientoCaja::where('caja_id', $this->caja->id)->delete();
        $this->caja->delete();

        // Limpiar almacen, proveedor, cliente, user del test
        \App\Models\CuentaPorPagar::where('proveedor_id', $this->proveedor->id)->delete();
        $this->almacen->delete();
        $this->proveedor->delete();
        $this->cliente->delete();
        User::whereIn('id', $this->createdUsers)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Reset arrays
        $this->createdCompras   = [];
        $this->createdVentas    = [];
        $this->createdProductos = [];
        $this->createdUsers     = [];

        parent::tearDown();
    }

    // ══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════
    private function crearProductoNormal(): Producto
    {
        $uid = uniqid();
        $p = Producto::create([
            'codigo'          => "PROD-{$uid}",
            'nombre'          => "Cable USB {$uid}",
            'categoria_id'    => $this->categoria->id,
            'tipo_inventario' => 'cantidad',
            'stock_actual'    => 0,
            'stock_minimo'    => 1,
            'stock_maximo'    => 100,
            'estado'          => 'activo',
        ]);
        $this->createdProductos[] = $p->id;
        return $p;
    }

    private function datosCompraBase(string $tipo = 'local'): array
    {
        return [
            'codigo'          => 'C-' . uniqid(),
            'proveedor_id'    => $this->proveedor->id,
            'almacen_id'      => $this->almacen->id,
            'user_id'         => $this->admin->id,
            'numero_factura'  => 'F001-' . uniqid(),
            'fecha'           => now()->toDateString(),
            'forma_pago'      => 'contado',
            'tipo_moneda'     => 'PEN',
            'tipo_cambio'     => 1.0,
            'tipo_compra'     => $tipo,
            'subtotal'        => 0,
            'igv'             => 0,
            'total'           => 0,
            'total_pen'       => 0,
            'estado'          => 'registrado',
        ];
    }

    private function registrarCompra(array $datosCompra, array $detalles): Compra
    {
        $compra = app(CompraService::class)->registrarCompra($datosCompra, $detalles);
        $this->createdCompras[] = $compra->id;
        return $compra;
    }

    private function crearVenta(array $datosVenta, array $detalles): Venta
    {
        $venta = app(VentaService::class)->crearVenta($datosVenta, $detalles);
        $this->createdVentas[] = $venta->id;
        return $venta;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 1: Compra local — producto normal (por cantidad)
    // ══════════════════════════════════════════════════════════════════════
    public function test_compra_local_producto_normal_incrementa_stock(): void
    {
        $producto = $this->crearProductoNormal();

        $datosCompra = $this->datosCompraBase();
        $detalles = [[
            'producto_id'     => $producto->id,
            'cantidad'        => 10,
            'precio_unitario' => 15.00,
            'descuento'       => 0,
        ]];

        $compra = $this->registrarCompra($datosCompra, $detalles);

        // La compra existe en BD
        $this->assertDatabaseHas('compras', ['id' => $compra->id]);

        // El detalle se creó correctamente
        $this->assertDatabaseHas('detalle_compras', [
            'compra_id'       => $compra->id,
            'producto_id'     => $producto->id,
            'cantidad'        => 10,
            'precio_unitario' => 15.00,
        ]);

        // Stock del almacén aumentó
        $stock = StockAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertNotNull($stock, 'Debe existir un registro de StockAlmacen');
        $this->assertEquals(10, $stock->cantidad);

        // Stock general del producto actualizado
        $producto->refresh();
        $this->assertEquals(10, $producto->stock_actual);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 3: Compra importación — prorrateo de gastos
    // ══════════════════════════════════════════════════════════════════════
    public function test_compra_importacion_calcula_prorrateo_correcto(): void
    {
        $u = uniqid();
        $prod1 = Producto::create([
            'codigo' => "IMP1-{$u}", 'nombre' => "Luminaria A {$u}",
            'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_actual' => 0,
            'estado' => 'activo',
        ]);
        $this->createdProductos[] = $prod1->id;
        $prod2 = Producto::create([
            'codigo' => "IMP2-{$u}", 'nombre' => "Luminaria B {$u}",
            'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_actual' => 0,
            'estado' => 'activo',
        ]);
        $this->createdProductos[] = $prod2->id;

        // Compra en USD con gastos de importación
        $datosCompra = array_merge($this->datosCompraBase('importacion'), [
            'tipo_moneda'          => 'USD',
            'tipo_cambio'          => 3.80,
            'flete_usd'            => 200.00,   // 200 USD → S/ 760
            'seguro_usd'           => 50.00,    // 50 USD  → S/ 190
            'otros_usd'            => 0.00,
            'impuestos_usd'        => 100.00,   // 100 USD → S/ 380
            'transporte_local_pen' => 100.00,   // S/ 100
            'impuestos_pen'        => 0.00,
            'percepcion_pen'       => 0.00,
        ]);
        // Total gastos en PEN = (200+50+100) * 3.80 + 100 = 350*3.80+100 = 1330+100 = 1430


        $detalles = [
            [   // 2 unidades a 300 USD c/u → subtotal 600 USD
                'producto_id'     => $prod1->id,
                'cantidad'        => 2,
                'precio_unitario' => 300.00,
                'descuento'       => 0,
            ],
            [   // 1 Luminaria B a 200 USD → subtotal 200 USD
                'producto_id'     => $prod2->id,
                'cantidad'        => 1,
                'precio_unitario' => 200.00,
                'descuento'       => 0,
            ],
        ];

        $compra = $this->registrarCompra($datosCompra, $detalles);

        $detallesGuardados = DetalleCompra::where('compra_id', $compra->id)
            ->orderBy('precio_unitario', 'desc')
            ->get();

        $this->assertCount(2, $detallesGuardados);

        /*
         * Cálculo esperado:
         *   total_subtotal_pen = (600 + 200) * 3.80 = 3040
         *   total_gastos_pen   = 1430
         *
         *   Línea 1 (unidades, subtotal 600 USD → 2280 PEN):
         *     proporción = 2280/3040 = 0.75
         *     gasto_asignado = 0.75 * 1430 = 1072.50
         *     prorrateo/ud  = 1072.50 / 2 = 536.25
         *     precio_pen/ud = 300 * 3.80 = 1140
         *     costo_final   = 1140 + 536.25 = 1676.25
         *
         *   Línea 2 (Luminaria B, subtotal 200 USD → 760 PEN):
         *     proporción = 760/3040 = 0.25
         *     gasto_asignado = 0.25 * 1430 = 357.50
         *     prorrateo/ud  = 357.50 / 1 = 357.50
         *     precio_pen/ud = 200 * 3.80 = 760
         *     costo_final   = 760 + 357.50 = 1117.50
         */
        $detalle1 = $detallesGuardados->where('producto_id', $prod1->id)->first();
        $detalle2 = $detallesGuardados->where('producto_id', $prod2->id)->first();

        $this->assertNotNull($detalle1);
        $this->assertNotNull($detalle2);

        // Verificar prorrateo guardado (tolerancia de 0.02 por redondeos)
        $this->assertEqualsWithDelta(536.25, (float)$detalle1->costo_prorateado_pen,    0.02);
        $this->assertEqualsWithDelta(1676.25, (float)$detalle1->costo_unitario_final_pen, 0.02);

        $this->assertEqualsWithDelta(357.50, (float)$detalle2->costo_prorateado_pen,    0.02);
        $this->assertEqualsWithDelta(1117.50, (float)$detalle2->costo_unitario_final_pen, 0.02);

        // El costo del producto se actualizó con el valor prorateado
        $prod1->refresh();
        $this->assertEqualsWithDelta(1676.25, (float)$prod1->ultimo_costo_compra, 0.02);

        $prod2->refresh();
        $this->assertEqualsWithDelta(1117.50, (float)$prod2->ultimo_costo_compra, 0.02);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 4: Venta de producto normal — descuenta stock
    // ══════════════════════════════════════════════════════════════════════
    public function test_venta_producto_normal_descuenta_stock(): void
    {
        $producto = $this->crearProductoNormal();

        // Primero hacer una compra para tener stock
        $this->registrarCompra($this->datosCompraBase(), [[
            'producto_id'     => $producto->id,
            'cantidad'        => 20,
            'precio_unitario' => 15.00,
            'descuento'       => 0,
        ]]);

        $producto->refresh();
        $this->assertEquals(20, $producto->stock_actual);

        // Ahora vender 5 unidades
        $datosVenta = [
            'user_id'       => $this->admin->id,
            'cliente_id'    => $this->cliente->id,
            'almacen_id'    => $this->almacen->id,
            'fecha'         => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'      => 0,
            'igv'           => 0,
            'total'         => 0,
            'estado_pago'   => 'pagado',
            'condicion_pago'=> 'contado',
            'metodo_pago'   => 'efectivo',
        ];
        $detallesVenta = [[
            'producto_id'     => $producto->id,
            'variante_id'     => null,
            'cantidad'        => 5,
            'precio_unitario' => 25.00,
            'incluye_igv'     => false,
        ]];

        $venta = $this->crearVenta($datosVenta, $detallesVenta);

        $this->assertDatabaseHas('ventas', ['id' => $venta->id]);

        // Stock decrementó
        $producto->refresh();
        $this->assertEquals(15, $producto->stock_actual);

        // Stock en almacén decrementó
        $stock = StockAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();
        $this->assertEquals(15, $stock->cantidad);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 7: No se puede vender sin stock suficiente
    // ══════════════════════════════════════════════════════════════════════
    public function test_venta_falla_si_no_hay_stock_suficiente(): void
    {
        $producto = $this->crearProductoNormal();

        // Stock = 0, sin compra previa
        // Crear registro mínimo de stock = 2 unidades
        StockAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id'  => $this->almacen->id,
            'cantidad'    => 2,
        ]);
        $producto->update(['stock_actual' => 2]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/stock insuficiente/i');

        app(VentaService::class)->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pagado',
            'condicion_pago'   => 'contado',
            'metodo_pago'      => 'efectivo',
        ], [[
            'producto_id'     => $producto->id,
            'variante_id'     => null,
            'cantidad'        => 10,  // pide 10, solo hay 2
            'precio_unitario' => 25.00,
            'incluye_igv'     => false,
        ]]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 8: Recalcular prorrateo de compra existente via controller
    // ══════════════════════════════════════════════════════════════════════
    public function test_recalcular_prorrateo_actualiza_costo_unitario(): void
    {
        $producto = $this->crearProductoNormal();

        // Compra sin gastos iniciales
        $datosCompra = array_merge($this->datosCompraBase('importacion'), [
            'tipo_moneda' => 'USD',
            'tipo_cambio' => 3.80,
            'flete_usd'   => 0,
        ]);
        $compra = $this->registrarCompra($datosCompra, [[
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 200.00,
            'descuento'       => 0,
        ]]);
        $detalle = DetalleCompra::where('compra_id', $compra->id)->first();

        // Costo inicial sin gastos: precio_pen = 200 * 3.80 = 760, prorrateo = 0
        $this->assertEqualsWithDelta(0.0,  (float)$detalle->costo_prorateado_pen,     0.01);
        $this->assertEqualsWithDelta(760.0,(float)$detalle->costo_unitario_final_pen, 0.01);

        // Ahora se registran gastos de flete (simulando edición de la compra)
        $compra->update(['flete_usd' => 100.00]);  // 100 USD → S/ 380

        // Recalcular via ruta del controller
        $response = $this->post(route('compras.recalcular-prorrateo', $compra));
        $response->assertRedirect(route('compras.show', $compra));
        $response->assertSessionHas('success');

        // Costo debe haber aumentado: 760 + 380 = 1140
        $detalle->refresh();
        $this->assertEqualsWithDelta(380.0,  (float)$detalle->costo_prorateado_pen,     0.01);
        $this->assertEqualsWithDelta(1140.0, (float)$detalle->costo_unitario_final_pen, 0.01);
    }
}
