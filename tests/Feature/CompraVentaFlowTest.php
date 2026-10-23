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
use App\Models\Imei;
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
    private array $createdImeis    = [];
    private array $createdCompras  = [];
    private array $createdVentas   = [];
    private array $createdProductos = [];
    private array $createdUsers    = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Roles: firstOrCreate para no fallar si ya existen
        $this->roleAdmin = Role::firstOrCreate(['nombre' => 'Administrador']);
        Role::firstOrCreate(['nombre' => 'Almacenero']);
        Role::firstOrCreate(['nombre' => 'Tienda']);

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
            ['codigo' => 'CEL-TEST'],
            ['nombre' => 'Celulares Test', 'estado' => 'activo']
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

        if (!empty($this->createdImeis)) {
            \App\Models\Imei::whereIn('id', $this->createdImeis)->forceDelete();
        }
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
        $this->createdImeis     = [];
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

    private function crearProductoSerie(): Producto
    {
        $uid = uniqid();
        $p = Producto::create([
            'codigo'          => "CEL-{$uid}",
            'nombre'          => "Samsung Galaxy {$uid}",
            'categoria_id'    => $this->categoria->id,
            'tipo_inventario' => 'serie',
            'stock_actual'    => 0,
            'stock_minimo'    => 1,
            'stock_maximo'    => 50,
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
        // Rastrear IMEIs creados
        $imeiIds = Imei::where('compra_id', $compra->id)->pluck('id')->toArray();
        $this->createdImeis = array_merge($this->createdImeis, $imeiIds);
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
    // TEST 2: Compra local — producto serie (IMEI)
    // ══════════════════════════════════════════════════════════════════════
    public function test_compra_local_producto_serie_registra_imeis(): void
    {
        $producto = $this->crearProductoSerie();

        $imeis = [
            ['codigo_imei' => (string)rand(100000000000000, 999999999999999), 'serie' => 'SN001'],
            ['codigo_imei' => (string)rand(100000000000000, 999999999999999), 'serie' => 'SN002'],
        ];

        $datosCompra = $this->datosCompraBase();
        $detalles = [[
            'producto_id'     => $producto->id,
            'cantidad'        => 2,
            'precio_unitario' => 350.00,
            'descuento'       => 0,
            'imeis'           => $imeis,
        ]];

        $compra = $this->registrarCompra($datosCompra, $detalles);

        // Se crearon los 2 IMEIs en BD
        $this->assertEquals(2, Imei::where('compra_id', $compra->id)->count());

        // Cada IMEI tiene estado en_stock y está vinculado al almacén
        foreach ($imeis as $imeiData) {
            $this->assertDatabaseHas('imeis', [
                'codigo_imei' => $imeiData['codigo_imei'],
                'producto_id' => $producto->id,
                'almacen_id'  => $this->almacen->id,
                'estado_imei' => 'en_stock',
                'compra_id'   => $compra->id,
            ]);
        }

        // Stock del producto = número de IMEIs en_stock
        $producto->refresh();
        $this->assertEquals(2, $producto->stock_actual);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 3: Compra importación — prorrateo de gastos
    // ══════════════════════════════════════════════════════════════════════
    public function test_compra_importacion_calcula_prorrateo_correcto(): void
    {
        $u = uniqid();
        $prod1 = Producto::create([
            'codigo' => "IMP1-{$u}", 'nombre' => "iPhone {$u}",
            'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'serie', 'stock_actual' => 0,
            'estado' => 'activo',
        ]);
        $this->createdProductos[] = $prod1->id;
        $prod2 = Producto::create([
            'codigo' => "IMP2-{$u}", 'nombre' => "Samsung {$u}",
            'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'serie', 'stock_actual' => 0,
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

        $imei1 = (string)rand(100000000000000, 999999999999999);
        $imei2 = (string)rand(100000000000000, 999999999999999);
        $imei3 = (string)rand(100000000000000, 999999999999999);

        $detalles = [
            [   // 2 iPhones a 300 USD c/u → subtotal 600 USD
                'producto_id'     => $prod1->id,
                'cantidad'        => 2,
                'precio_unitario' => 300.00,
                'descuento'       => 0,
                'imeis'           => [
                    ['codigo_imei' => $imei1],
                    ['codigo_imei' => $imei2],
                ],
            ],
            [   // 1 Samsung a 200 USD → subtotal 200 USD
                'producto_id'     => $prod2->id,
                'cantidad'        => 1,
                'precio_unitario' => 200.00,
                'descuento'       => 0,
                'imeis'           => [
                    ['codigo_imei' => $imei3],
                ],
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
         *   Línea 1 (iPhones, subtotal 600 USD → 2280 PEN):
         *     proporción = 2280/3040 = 0.75
         *     gasto_asignado = 0.75 * 1430 = 1072.50
         *     prorrateo/ud  = 1072.50 / 2 = 536.25
         *     precio_pen/ud = 300 * 3.80 = 1140
         *     costo_final   = 1140 + 536.25 = 1676.25
         *
         *   Línea 2 (Samsung, subtotal 200 USD → 760 PEN):
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

        // Los IMEIs tienen proveedor accesible
        $imei = Imei::where('codigo_imei', $imei1)->with('compra.proveedor')->first();
        $this->assertNotNull($imei->compra->proveedor);
        $this->assertEquals($this->proveedor->razon_social, $imei->compra->proveedor->razon_social);
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
    // TEST 5: Venta de producto serie (IMEI) — cambia estado del IMEI
    // ══════════════════════════════════════════════════════════════════════
    public function test_venta_producto_serie_marca_imei_vendido(): void
    {
        $producto = $this->crearProductoSerie();

        // Comprar 1 celular con IMEI
        $imeiCodigo = (string)rand(100000000000000, 999999999999999);
        $this->registrarCompra($this->datosCompraBase(), [[
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 450.00,
            'descuento'       => 0,
            'imeis'           => [['codigo_imei' => $imeiCodigo, 'serie' => 'SN-CEL-001']],
        ]]);

        $imei = Imei::where('codigo_imei', $imeiCodigo)->first();
        $this->assertNotNull($imei);
        $this->assertEquals('en_stock', $imei->estado_imei);

        $producto->refresh();
        $this->assertEquals(1, $producto->stock_actual);

        // Vender el celular pasando el IMEI
        $datosVenta = [
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0,
            'igv'              => 0,
            'total'            => 0,
            'estado_pago'      => 'pagado',
            'condicion_pago'   => 'contado',
            'metodo_pago'      => 'efectivo',
        ];
        $detallesVenta = [[
            'producto_id'     => $producto->id,
            'variante_id'     => null,
            'cantidad'        => 1,
            'precio_unitario' => 650.00,
            'incluye_igv'     => false,
            'imeis'           => [['codigo_imei' => $imeiCodigo]],
        ]];

        $venta = $this->crearVenta($datosVenta, $detallesVenta);

        // IMEI ahora está vendido
        $imei->refresh();
        $this->assertEquals('vendido', $imei->estado_imei);
        $this->assertEquals($venta->id, $imei->venta_id);
        $this->assertNotNull($imei->fecha_venta);

        // Stock del producto bajó a 0
        $producto->refresh();
        $this->assertEquals(0, $producto->stock_actual);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 6: Flujo completo — compra importación → venta con IMEI
    //         Verifica trazabilidad: venta → IMEI → compra → proveedor
    // ══════════════════════════════════════════════════════════════════════
    public function test_flujo_completo_importacion_venta_trazabilidad(): void
    {
        $producto = $this->crearProductoSerie();

        // 1. Compra de importación en USD
        $datosCompra = array_merge($this->datosCompraBase('importacion'), [
            'tipo_moneda'          => 'USD',
            'tipo_cambio'          => 3.80,
            'flete_usd'            => 100.00,
            'seguro_usd'           => 20.00,
            'transporte_local_pen' => 50.00,
        ]);

        $imeiCodigo = (string)rand(100000000000000, 999999999999999);
        $this->registrarCompra($datosCompra, [[
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 500.00,   // USD
            'descuento'       => 0,
            'imeis'           => [['codigo_imei' => $imeiCodigo]],
        ]]);

        // 2. Verificar prorrateo calculado
        $detalle = DetalleCompra::where('producto_id', $producto->id)->first();
        $this->assertNotNull($detalle);
        $this->assertGreaterThan(0, (float)$detalle->costo_prorateado_pen);
        $this->assertGreaterThan(0, (float)$detalle->costo_unitario_final_pen);

        // Costo esperado: gastos = (100+20)*3.80 + 50 = 456+50 = 506
        //                precio_pen = 500*3.80 = 1900
        //                costo_final = 1900 + 506 = 2406.00
        $this->assertEqualsWithDelta(2406.00, (float)$detalle->costo_unitario_final_pen, 0.05);

        // 3. Vender el celular
        $venta = $this->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'factura',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pagado',
            'condicion_pago'   => 'contado',
            'metodo_pago'      => 'transferencia',
        ], [[
            'producto_id'     => $producto->id,
            'variante_id'     => null,
            'cantidad'        => 1,
            'precio_unitario' => 3200.00,
            'incluye_igv'     => false,
            'imeis'           => [['codigo_imei' => $imeiCodigo]],
        ]]);

        // 4. Trazabilidad: venta → IMEI → compra → proveedor
        $imei = Imei::where('codigo_imei', $imeiCodigo)
            ->with('compra.proveedor')
            ->first();

        $this->assertEquals('vendido', $imei->estado_imei);
        $this->assertEquals($venta->id, $imei->venta_id);
        $this->assertNotNull($imei->compra);
        $this->assertEquals($this->proveedor->razon_social, $imei->compra->proveedor->razon_social);
        $this->assertEquals($this->proveedor->ruc, $imei->compra->proveedor->ruc);

        // 5. Stock en 0 después de la venta
        $producto->refresh();
        $this->assertEquals(0, $producto->stock_actual);
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
        $producto = $this->crearProductoSerie();

        // Compra sin gastos iniciales
        $datosCompra = array_merge($this->datosCompraBase('importacion'), [
            'tipo_moneda' => 'USD',
            'tipo_cambio' => 3.80,
            'flete_usd'   => 0,
        ]);
        $imeiCodigo = (string)rand(100000000000000, 999999999999999);
        $compra = $this->registrarCompra($datosCompra, [[
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 200.00,
            'descuento'       => 0,
            'imeis'           => [['codigo_imei' => $imeiCodigo]],
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
