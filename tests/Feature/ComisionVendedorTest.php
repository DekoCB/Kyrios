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
use App\Models\Compra;
use App\Models\Venta;
use App\Models\ComisionRegla;
use App\Models\ComisionDetalleVenta;
use App\Services\CompraService;
use App\Services\VentaService;
use App\Services\ComisionService;

class ComisionVendedorTest extends TestCase
{
    // Igual que CompraVentaFlowTest: sin DatabaseTransactions porque los
    // servicios usan DB::transaction() interno; limpiamos en tearDown().

    private Role      $roleAdmin;
    private Role      $roleVendedor;
    private User      $admin;
    private User      $vendedor;
    private Almacen   $almacen;
    private Proveedor $proveedor;
    private Cliente   $cliente;
    private Categoria $categoria;

    private array $createdCompras   = [];
    private array $createdVentas    = [];
    private array $createdProductos = [];
    private array $createdUsers     = [];
    private array $createdReglas    = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin    = Role::firstOrCreate(['nombre' => 'Administrador']);
        $this->roleVendedor = Role::firstOrCreate(['nombre' => 'Vendedor']);
        Role::firstOrCreate(['nombre' => 'Almacenero']);
        Role::firstOrCreate(['nombre' => 'Tienda']);
        Role::firstOrCreate(['nombre' => 'Cajero']);

        $uid = uniqid();

        $this->admin = User::create([
            'name'     => 'Admin Test',
            'email'    => "admin_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $this->roleAdmin->id,
        ]);
        $this->createdUsers[] = $this->admin->id;

        $this->vendedor = User::create([
            'name'     => 'Vendedor Test',
            'email'    => "vendedor_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $this->roleVendedor->id,
        ]);
        $this->createdUsers[] = $this->vendedor->id;

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
            'numero_documento' => (string) rand(10000000, 99999999),
            'nombre'           => "Cliente {$uid}",
            'estado'           => 'activo',
        ]);

        $this->categoria = Categoria::firstOrCreate(
            ['codigo' => 'CEL-TEST'],
            ['nombre' => 'Celulares Test', 'estado' => 'activo']
        );

        // VentaService exige caja abierta para ventas al contado.
        \App\Models\Caja::create([
            'user_id'        => $this->vendedor->id,
            'almacen_id'     => $this->almacen->id,
            'fecha'          => now()->toDateString(),
            'fecha_apertura' => now(),
            'monto_inicial'  => 0,
            'estado'         => 'abierta',
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->createdVentas)) {
            ComisionDetalleVenta::whereHas(
                'detalleVenta',
                fn($q) => $q->whereIn('venta_id', $this->createdVentas)
            )->delete();
            \App\Models\DetalleVenta::whereIn('venta_id', $this->createdVentas)->delete();
            Venta::whereIn('id', $this->createdVentas)->delete();
        }
        if (!empty($this->createdReglas)) {
            ComisionRegla::whereIn('id', $this->createdReglas)->delete();
        }
        if (!empty($this->createdCompras)) {
            \App\Models\DetalleCompra::whereIn('compra_id', $this->createdCompras)->delete();
            Compra::whereIn('id', $this->createdCompras)->delete();
        }
        StockAlmacen::where('almacen_id', $this->almacen->id)->delete();
        if (!empty($this->createdProductos)) {
            Producto::whereIn('id', $this->createdProductos)->delete();
        }
        \App\Models\MovimientoInventario::where('almacen_id', $this->almacen->id)
            ->orWhere('almacen_destino_id', $this->almacen->id)
            ->delete();

        \App\Models\CuentaPorPagar::where('proveedor_id', $this->proveedor->id)->delete();
        \App\Models\Caja::where('almacen_id', $this->almacen->id)->delete();
        $this->almacen->delete();
        $this->proveedor->delete();
        $this->cliente->delete();
        User::whereIn('id', $this->createdUsers)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->createdCompras   = [];
        $this->createdVentas    = [];
        $this->createdProductos = [];
        $this->createdUsers     = [];
        $this->createdReglas    = [];

        parent::tearDown();
    }

    // ══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════
    private function crearProducto(float $costo = 100.00): Producto
    {
        $uid = uniqid();
        $p = Producto::create([
            'codigo'          => "PROD-{$uid}",
            'nombre'          => "Producto Comisión {$uid}",
            'categoria_id'    => $this->categoria->id,
            'tipo_inventario' => 'cantidad',
            'stock_actual'    => 0,
            'stock_minimo'    => 1,
            'stock_maximo'    => 100,
            'costo_promedio'  => $costo,
            'estado'          => 'activo',
        ]);
        $this->createdProductos[] = $p->id;
        return $p;
    }

    private function darStock(Producto $producto, int $cantidad, float $precioCompra): Compra
    {
        $datosCompra = [
            'codigo'          => 'C-' . uniqid(),
            'proveedor_id'    => $this->proveedor->id,
            'almacen_id'      => $this->almacen->id,
            'user_id'         => $this->admin->id,
            'numero_factura'  => 'F001-' . uniqid(),
            'fecha'           => now()->toDateString(),
            'forma_pago'      => 'contado',
            'tipo_moneda'     => 'PEN',
            'tipo_cambio'     => 1.0,
            'tipo_compra'     => 'local',
            'subtotal'        => 0,
            'igv'             => 0,
            'total'           => 0,
            'total_pen'       => 0,
            'estado'          => 'registrado',
        ];
        $compra = app(CompraService::class)->registrarCompra($datosCompra, [[
            'producto_id'     => $producto->id,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precioCompra,
            'descuento'       => 0,
        ]]);
        $this->createdCompras[] = $compra->id;
        return $compra;
    }

    private function crearVentaComoVendedor(Producto $producto, int $cantidad, float $precioVenta, bool $pagadoInmediato = true): Venta
    {
        $this->actingAs($this->vendedor);

        $venta = app(VentaService::class)->crearVenta([
            'user_id'          => $this->vendedor->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => $pagadoInmediato ? 'pagado' : 'pendiente',
            'condicion_pago'   => 'contado',
            'metodo_pago'      => 'efectivo',
        ], [[
            'producto_id'     => $producto->id,
            'variante_id'     => null,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precioVenta,
            'incluye_igv'     => false,
        ]]);

        $this->createdVentas[] = $venta->id;
        return $venta;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 1: Regla "usuario" (vendedor específico) — porcentaje sobre venta
    // ══════════════════════════════════════════════════════════════════════
    public function test_regla_por_usuario_genera_comision_al_pagar_venta(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        $regla = ComisionRegla::create([
            'nombre'          => 'Comisión 5% vendedor test',
            'tipo_aplicacion' => 'usuario',
            'user_id'         => $this->vendedor->id,
            'tipo_calculo'    => 'porcentaje',
            'valor'           => 5,
            'activo'          => true,
        ]);
        $this->createdReglas[] = $regla->id;

        $venta = $this->crearVentaComoVendedor($producto, 2, 100.00); // subtotal_con_igv = 236.00

        $detalle = $venta->detalles()->first();
        $this->assertNotNull($detalle, 'La venta debe tener un detalle');

        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();

        $this->assertNotNull(
            $comision,
            'BUG CONFIRMADO: no se generó ninguna comisión para el vendedor pese a existir una regla activa tipo "usuario" asignada a él.'
        );
        $this->assertEquals($this->vendedor->id, $comision->user_id);
        $this->assertEquals($regla->id, $comision->regla_id);
        $this->assertEquals('pendiente', $comision->estado);
        // 5% de subtotal_con_igv (236.00) = 11.80
        $this->assertEqualsWithDelta(11.80, (float) $comision->monto_comision, 0.01);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 2: Prioridad de reglas — producto+vendedor debe ganarle a "usuario"
    // ══════════════════════════════════════════════════════════════════════
    public function test_regla_producto_usuario_tiene_prioridad_sobre_regla_usuario(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        // Regla genérica por vendedor: 5%
        $reglaUsuario = ComisionRegla::create([
            'nombre' => 'Genérica vendedor', 'tipo_aplicacion' => 'usuario',
            'user_id' => $this->vendedor->id, 'tipo_calculo' => 'porcentaje', 'valor' => 5, 'activo' => true,
        ]);
        $this->createdReglas[] = $reglaUsuario->id;

        // Regla específica producto+vendedor: 20% (debe ganar)
        $reglaEspecifica = ComisionRegla::create([
            'nombre' => 'Producto+Vendedor', 'tipo_aplicacion' => 'producto_usuario',
            'user_id' => $this->vendedor->id, 'producto_id' => $producto->id,
            'tipo_calculo' => 'porcentaje', 'valor' => 20, 'activo' => true,
        ]);
        $this->createdReglas[] = $reglaEspecifica->id;

        $venta = $this->crearVentaComoVendedor($producto, 1, 100.00); // subtotal_con_igv = 118.00
        $detalle = $venta->detalles()->first();
        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();

        $this->assertNotNull($comision);
        $this->assertEquals(
            $reglaEspecifica->id,
            $comision->regla_id,
            'BUG: se aplicó la regla genérica de usuario en vez de la más específica producto+vendedor.'
        );
        // 20% de 118.00 = 23.60
        $this->assertEqualsWithDelta(23.60, (float) $comision->monto_comision, 0.01);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 2B: Prioridad de reglas — categoría+vendedor debe ganarle a "categoria"
    // ══════════════════════════════════════════════════════════════════════
    public function test_regla_categoria_usuario_tiene_prioridad_sobre_regla_categoria(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        // Regla genérica por categoría: 5%
        $reglaCategoria = ComisionRegla::create([
            'nombre' => 'Genérica categoría', 'tipo_aplicacion' => 'categoria',
            'categoria_id' => $this->categoria->id, 'tipo_calculo' => 'porcentaje', 'valor' => 5, 'activo' => true,
        ]);
        $this->createdReglas[] = $reglaCategoria->id;

        // Regla específica categoría+vendedor: 15% (debe ganar)
        $reglaEspecifica = ComisionRegla::create([
            'nombre' => 'Categoría+Vendedor', 'tipo_aplicacion' => 'categoria_usuario',
            'user_id' => $this->vendedor->id, 'categoria_id' => $this->categoria->id,
            'tipo_calculo' => 'porcentaje', 'valor' => 15, 'activo' => true,
        ]);
        $this->createdReglas[] = $reglaEspecifica->id;

        $venta = $this->crearVentaComoVendedor($producto, 1, 100.00); // subtotal_con_igv = 118.00
        $detalle = $venta->detalles()->first();
        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();

        $this->assertNotNull($comision);
        $this->assertEquals(
            $reglaEspecifica->id,
            $comision->regla_id,
            'BUG: se aplicó la regla genérica de categoría en vez de la más específica categoría+vendedor.'
        );
        // 15% de 118.00 = 17.70
        $this->assertEqualsWithDelta(17.70, (float) $comision->monto_comision, 0.01);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 3: Venta pendiente → se paga después → comisión se genera recién
    //         al confirmar el pago (no antes)
    // ══════════════════════════════════════════════════════════════════════
    public function test_venta_pendiente_no_genera_comision_hasta_confirmar_pago(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        $regla = ComisionRegla::create([
            'nombre' => 'Comisión vendedor', 'tipo_aplicacion' => 'usuario',
            'user_id' => $this->vendedor->id, 'tipo_calculo' => 'monto_fijo', 'valor' => 3.00, 'activo' => true,
        ]);
        $this->createdReglas[] = $regla->id;

        $venta = $this->crearVentaComoVendedor($producto, 1, 100.00, pagadoInmediato: false);
        $detalle = $venta->detalles()->first();

        $this->assertNull(
            ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first(),
            'No debería existir comisión mientras la venta sigue "pendiente".'
        );

        app(VentaService::class)->confirmarPago($venta->id, 'efectivo', $this->admin->id);

        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();
        $this->assertNotNull(
            $comision,
            'BUG CONFIRMADO: al confirmar el pago de una venta pendiente no se generó la comisión del vendedor.'
        );
        $this->assertEqualsWithDelta(3.00, (float) $comision->monto_comision, 0.01);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 4: Regla creada DESPUÉS de la venta pagada — recalcularHistorico
    //         debe generar la comisión retroactiva
    // ══════════════════════════════════════════════════════════════════════
    public function test_recalcular_historico_genera_comision_de_regla_creada_despues(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        // Venta pagada SIN ninguna regla activa todavía
        $venta = $this->crearVentaComoVendedor($producto, 1, 100.00);
        $detalle = $venta->detalles()->first();

        $this->assertNull(ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first());

        // Ahora se crea la regla (después de la venta)
        $regla = ComisionRegla::create([
            'nombre' => 'Regla tardía', 'tipo_aplicacion' => 'usuario',
            'user_id' => $this->vendedor->id, 'tipo_calculo' => 'porcentaje', 'valor' => 10, 'activo' => true,
        ]);
        $this->createdReglas[] = $regla->id;

        $creadas = app(ComisionService::class)->recalcularHistorico();

        $this->assertGreaterThanOrEqual(1, $creadas);
        $comision = ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first();
        $this->assertNotNull(
            $comision,
            'BUG CONFIRMADO: recalcularHistorico() no generó la comisión retroactiva para la venta ya pagada.'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST 5: Regla inactiva NO debe generar comisión
    // ══════════════════════════════════════════════════════════════════════
    public function test_regla_inactiva_no_genera_comision(): void
    {
        $producto = $this->crearProducto();
        $this->darStock($producto, 10, 50.00);

        $regla = ComisionRegla::create([
            'nombre' => 'Regla desactivada', 'tipo_aplicacion' => 'usuario',
            'user_id' => $this->vendedor->id, 'tipo_calculo' => 'porcentaje', 'valor' => 5, 'activo' => false,
        ]);
        $this->createdReglas[] = $regla->id;

        $venta = $this->crearVentaComoVendedor($producto, 1, 100.00);
        $detalle = $venta->detalles()->first();

        $this->assertNull(
            ComisionDetalleVenta::where('detalle_venta_id', $detalle->id)->first(),
            'No debería generarse comisión con una regla "activo=false".'
        );
    }
}
