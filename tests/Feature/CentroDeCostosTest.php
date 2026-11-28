<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CentroCostosService;
use App\Services\EscanerService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Centro de costos: costo real de las importaciones por origen y utilidad de las ventas con su estadística. */
class CentroDeCostosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $ring;
    private Producto $fust;
    private ProductoVariante $vRing;
    private ProductoVariante $vFust;
    private Proveedor $china;
    private Proveedor $espana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $sucursal = Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $this->almacen->update(['sucursal_id' => $sucursal->id]);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        foreach (['ring' => 'Ring', 'fust' => 'Fust'] as $k => $nombre) {
            $p = Producto::create(['codigo' => 'KY-' . $k, 'nombre' => $nombre, 'nombre_kyrios' => $nombre, 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad',
                'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
            $v = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-' . $k, 'codigo_kyrios' => 'KY-LU2501-' . ($k === 'ring' ? '0001' : '0002'), 'estado' => 'activo']);
            $this->{$k} = $p;
            $this->{'v' . ucfirst($k)} = $v;
            app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $v->id, 'cantidad' => 100]], $this->admin->id);
        }
        $this->china = Proveedor::create(['ruc' => '20000000001', 'razon_social' => 'Shenzhen Lighting Co', 'pais' => 'China', 'ciudad_origen' => 'Shenzhen', 'estado' => 'activo']);
        $this->espana = Proveedor::create(['ruc' => '20000000002', 'razon_social' => 'Faro Barcelona SA', 'pais' => 'España', 'ciudad_origen' => 'Barcelona', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    /** Importación: 10 unidades de Ring a US$ 50 (500 de fábrica), T.C. 3.5; gastos desglosados; costo final por unidad ya prorrateado. */
    private function importacion(Proveedor $prov, string $fecha, array $extra = [], float $costoFinalUnit = 242.0): Compra
    {
        $c = Compra::create($extra + [
            'codigo' => 'IMP-' . random_int(1000, 9999), 'numero_factura' => 'F-' . random_int(1000, 9999), 'proveedor_id' => $prov->id, 'almacen_id' => $this->almacen->id, 'user_id' => $this->admin->id,
            'fecha' => $fecha, 'tipo_moneda' => 'USD', 'tipo_cambio' => 3.5, 'tipo_compra' => 'importacion', 'subtotal' => 500, 'igv' => 0, 'total' => 500, 'total_pen' => 1750,
            'flete_usd' => 100, 'seguro_usd' => 10, 'otros_usd' => 0, 'impuestos_usd' => 40, 'impuestos_pen' => 0, 'transporte_local_pen' => 200, 'percepcion_pen' => 50,
            'estado' => 'registrado', 'forma_pago' => 'contado',
        ]);
        DetalleCompra::create(['compra_id' => $c->id, 'producto_id' => $this->ring->id, 'variante_id' => $this->vRing->id, 'cantidad' => 10, 'precio_unitario' => 50, 'subtotal' => 500,
            'costo_unitario_final_pen' => $costoFinalUnit]);

        return $c;
    }

    private function venta(float $precio, int $cantidad, string $fecha, array $extra = [], ?Producto $p = null, ?ProductoVariante $v = null): Venta
    {
        $vendedor = $extra['vendedor'] ?? $this->admin;
        unset($extra['vendedor']);
        $venta = app(VentaService::class)->crearVenta($extra + [
            'user_id' => $vendedor->id, 'cliente_id' => Cliente::firstOrCreate(['numero_documento' => '12345678'], ['tipo_documento' => 'DNI', 'nombre' => 'Juan Perez', 'estado' => 'activo'])->id,
            'almacen_id' => $this->almacen->id, 'sucursal_id' => $this->almacen->sucursal_id, 'fecha' => $fecha, 'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
            'estado_pago' => 'pendiente',   // sin cobro no hace falta abrir caja: la utilidad cuenta igual
        ], [['producto_id' => ($p ?? $this->ring)->id, 'variante_id' => ($v ?? $this->vRing)->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'incluye_igv' => false]]);

        return $venta;
    }

    // ── Importaciones ──

    public function test_el_costo_real_de_una_importacion_con_sus_gastos_y_su_factor(): void
    {
        $this->importacion($this->china, '2026-11-05');

        $d = app(CentroCostosService::class)->importaciones('2026-11-01', '2026-11-30');
        $fila = $d['filas']->first();

        $this->assertEquals(1750.0, $fila['fob_pen'], '500 USD × 3.5');
        $this->assertEquals(350.0, $fila['partes']['flete']);
        $this->assertEquals(35.0, $fila['partes']['seguro']);
        $this->assertEquals(140.0, $fila['partes']['impuestos']);
        $this->assertEquals(200.0, $fila['partes']['transporte']);
        $this->assertEquals(50.0, $fila['partes']['percepcion']);
        $this->assertEquals(2420.0, $fila['costo_total'], '10 unidades × 242 ya prorrateadas');
        $this->assertEquals(670.0, $fila['gastos']);
        $this->assertEquals(242.0, $fila['costo_unitario']);
        $this->assertEquals(1.383, $fila['factor']);
        $this->assertSame('China', $fila['pais']);
        $this->assertSame('Shenzhen', $fila['ciudad']);
    }

    public function test_las_importaciones_se_agrupan_por_pais_proveedor_y_mes(): void
    {
        $this->importacion($this->china, '2026-10-10');
        $this->importacion($this->china, '2026-11-05');
        $this->importacion($this->espana, '2026-11-20', [], 300.0);

        $d = app(CentroCostosService::class)->importaciones('2026-10-01', '2026-11-30');

        $this->assertSame(['China', 'España'], $d['por_pais']->pluck('pais')->all(), 'ordenado por costo total');
        $this->assertSame(2, $d['por_pais']['China']['compras']);
        $this->assertEquals(4840.0, $d['por_pais']['China']['costo_total']);
        $this->assertCount(2, $d['por_proveedor']);
        $this->assertSame(['2026-10', '2026-11'], $d['por_mes']->keys()->all());
        $this->assertSame(3, $d['totales']['compras']);
        $this->assertSame(30, $d['totales']['unidades']);
        $this->assertEquals(5250.0, $d['totales']['fob_pen']);
    }

    public function test_los_filtros_del_periodo_proveedor_y_pais_y_se_excluyen_las_anuladas_y_las_locales(): void
    {
        $this->importacion($this->china, '2026-11-05');
        $this->importacion($this->espana, '2026-11-06');
        $this->importacion($this->china, '2026-11-07', ['estado' => 'anulado']);
        $this->importacion($this->china, '2026-11-08', ['tipo_compra' => 'local']);
        $this->importacion($this->china, '2026-09-01');
        $s = app(CentroCostosService::class);

        $this->assertSame(2, $s->importaciones('2026-11-01', '2026-11-30')['totales']['compras']);
        $this->assertSame(1, $s->importaciones('2026-11-01', '2026-11-30', ['pais' => 'España'])['totales']['compras']);
        $this->assertSame(1, $s->importaciones('2026-11-01', '2026-11-30', ['proveedor_id' => $this->china->id])['totales']['compras']);
        $this->assertSame(0, $s->importaciones('2026-12-01', '2026-12-31')['totales']['compras']);
    }

    public function test_sin_costo_final_se_estima_con_el_precio_de_fabrica_por_el_tipo_de_cambio(): void
    {
        $c = $this->importacion($this->china, '2026-11-05');
        DetalleCompra::where('compra_id', $c->id)->update(['costo_unitario_final_pen' => 0]);

        $fila = app(CentroCostosService::class)->importaciones('2026-11-01', '2026-11-30')['filas']->first();

        $this->assertEquals(1750.0, $fila['costo_total'], '10 × 50 × 3.5');
        $this->assertEquals(1.0, $fila['factor']);
    }

    public function test_los_productos_con_mayor_sobrecosto_comparan_la_fabrica_con_el_almacen(): void
    {
        $this->importacion($this->china, '2026-11-05');

        $p = app(CentroCostosService::class)->importaciones('2026-11-01', '2026-11-30')['productos']->first();

        $this->assertSame('Ring', $p->nombre);
        $this->assertEquals(175.0, $p->fob_unitario);
        $this->assertEquals(242.0, $p->costo_unitario);
        $this->assertEquals(67.0, $p->sobrecosto);
        $this->assertEquals(38.3, $p->porcentaje);
    }

    public function test_la_pantalla_de_importaciones_y_su_csv(): void
    {
        $this->importacion($this->china, '2026-11-05');

        $this->get(route('centro-costos.importaciones', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->assertSee('Shenzhen Lighting Co')->assertSee('China')
            ->assertSee('Gastos de importación')->assertSee('¿En qué se va el costo?')->assertSee('×1.383', false)->assertSee('Productos con mayor sobrecosto');
        $csv = $this->get(route('centro-costos.importaciones.exportar', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Shenzhen Lighting Co', $csv);
        $this->assertStringContainsString('2420', $csv);
        $this->get(route('centro-costos.importaciones', ['desde' => '2030-01-01', 'hasta' => '2030-01-31']))->assertOk()->assertSee('No hay importaciones registradas');
    }

    public function test_el_proveedor_guarda_su_pais_y_ciudad_de_origen(): void
    {
        $this->put(route('proveedores.update', $this->china), ['ruc' => '20000000001', 'razon_social' => 'Shenzhen Lighting Co', 'estado' => 'activo', 'pais' => 'China', 'ciudad_origen' => 'Guangzhou'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Guangzhou', $this->china->fresh()->ciudad_origen);
        $this->get(route('proveedores.edit', $this->china))->assertOk()->assertSee('País de origen')->assertSee('Guangzhou');
        $this->get(route('proveedores.show', $this->china))->assertOk()->assertSee('Guangzhou, China');
    }

    // ── Ventas y utilidad ──

    public function test_la_venta_guarda_el_costo_del_momento_y_no_cambia_si_el_costo_sube_despues(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $venta = $this->venta(200, 3, '2026-11-10');
        $this->assertEquals(100.0, (float) $venta->detalles->first()->costo_unitario);

        $this->vRing->update(['costo_promedio' => 150]);

        $t = app(CentroCostosService::class)->ventas('2026-11-01', '2026-11-30')['totales'];
        $this->assertEquals(600.0, $t['ingreso']);
        $this->assertEquals(300.0, $t['costo'], 'sigue con el costo de cuando se vendió');
        $this->assertEquals(300.0, $t['utilidad']);
        $this->assertEquals(50.0, $t['margen']);
        $this->assertEquals(100.0, $t['recargo']);
    }

    public function test_el_costo_del_producto_se_usa_si_la_variante_no_tiene_compras_propias(): void
    {
        $this->ring->update(['costo_promedio' => 80]);

        $venta = $this->venta(200, 1, '2026-11-10');

        $this->assertEquals(80.0, (float) $venta->detalles->first()->costo_unitario);
    }

    public function test_las_ventas_antiguas_sin_costo_guardado_usan_el_costo_actual_y_se_avisa(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $venta = $this->venta(200, 2, '2026-11-10');
        $venta->detalles()->update(['costo_unitario' => null]);
        $this->vRing->update(['costo_promedio' => 120]);

        $t = app(CentroCostosService::class)->ventas('2026-11-01', '2026-11-30')['totales'];

        $this->assertEquals(240.0, $t['costo']);
        $this->assertSame(1, $t['estimadas']);
        $this->assertSame(0, $t['sin_costo']);
    }

    public function test_las_lineas_sin_costo_se_cuentan_aparte_y_bajan_la_cobertura(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $this->venta(200, 1, '2026-11-10');
        $this->venta(300, 1, '2026-11-11', [], $this->fust, $this->vFust);   // sin costo

        $t = app(CentroCostosService::class)->ventas('2026-11-01', '2026-11-30')['totales'];

        $this->assertSame(2, $t['lineas']);
        $this->assertSame(1, $t['sin_costo']);
        $this->assertEquals(50.0, $t['cobertura']);
        $this->assertEquals(500.0, $t['ingreso']);
        $this->assertEquals(100.0, $t['costo']);
    }

    public function test_la_utilidad_se_reparte_por_mes_producto_vendedor_tienda_y_cliente(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $this->vFust->update(['costo_promedio' => 50]);
        $vendedor = $this->usuario('Ventas');
        $this->venta(200, 2, '2026-10-10');                                               // Ring: ingreso 400, costo 200
        $this->venta(100, 4, '2026-11-10', ['vendedor' => $vendedor], $this->fust, $this->vFust);   // Fust: ingreso 400, costo 200
        $this->venta(300, 1, '2026-11-12');                                               // Ring: ingreso 300, costo 100

        $d = app(CentroCostosService::class)->ventas('2026-10-01', '2026-11-30');

        $this->assertSame(['2026-10', '2026-11'], $d['por_mes']->pluck('clave')->all());
        $this->assertEquals(400.0, $d['por_mes'][0]['ingreso']);
        $this->assertEquals(700.0, $d['por_mes'][1]['ingreso']);
        $this->assertSame('Ring', $d['por_producto'][0]['etiqueta'], 'el de mayor utilidad primero (Ring: 400 contra Fust: 200)');
        $this->assertEquals(400.0, $d['por_producto'][0]['utilidad']);
        $this->assertCount(2, $d['por_vendedor']);
        $this->assertEquals(200.0, $d['por_vendedor']->firstWhere('etiqueta', $vendedor->name)['utilidad']);
        $this->assertSame('Local A', $d['por_tienda'][0]['etiqueta']);
        $this->assertSame('Juan Perez', $d['por_cliente'][0]['etiqueta']);
        $this->assertSame('Luminaria', $d['por_categoria'][0]['etiqueta']);
        $this->assertEquals(1100.0, $d['totales']['ingreso']);
        $this->assertEquals(500.0, $d['totales']['costo']);
        $this->assertEquals(600.0, $d['totales']['utilidad']);
        $this->assertSame(3, $d['totales']['ventas']);
    }

    public function test_los_filtros_de_ventas_y_se_excluyen_anuladas_y_notas_de_credito(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $vendedor = $this->usuario('Ventas');
        $this->venta(200, 1, '2026-11-10');
        $this->venta(200, 1, '2026-11-11', ['vendedor' => $vendedor]);
        $anulada = $this->venta(200, 1, '2026-11-12');
        $anulada->update(['estado_pago' => 'anulado']);
        $nc = $this->venta(200, 1, '2026-11-13');
        $nc->update(['tipo_comprobante' => 'nc_boleta']);
        $s = app(CentroCostosService::class);

        $this->assertSame(2, $s->ventas('2026-11-01', '2026-11-30')['totales']['ventas']);
        $this->assertSame(1, $s->ventas('2026-11-01', '2026-11-30', ['vendedor_id' => $vendedor->id])['totales']['ventas']);
        $this->assertSame(0, $s->ventas('2026-11-01', '2026-11-30', ['categoria_id' => 99999])['totales']['ventas']);
        $this->assertSame(0, $s->ventas('2026-12-01', '2026-12-31')['totales']['ventas']);
    }

    public function test_se_listan_las_lineas_vendidas_por_debajo_del_costo(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $this->venta(60, 2, '2026-11-10');    // pérdida de 80
        $this->venta(150, 1, '2026-11-11');

        $d = app(CentroCostosService::class)->ventas('2026-11-01', '2026-11-30');

        $this->assertCount(1, $d['perdidas']);
        $this->assertEquals(120.0, (float) $d['perdidas'][0]->ingreso);
        $this->assertEquals(200.0, (float) $d['perdidas'][0]->costo);
    }

    public function test_el_resumen_contable_compara_el_igv_de_ventas_y_compras(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $this->venta(100, 10, '2026-11-10');    // base 1000 → IGV 180
        Compra::create(['codigo' => 'C-1', 'numero_factura' => 'F-1', 'proveedor_id' => $this->china->id, 'almacen_id' => $this->almacen->id, 'user_id' => $this->admin->id, 'fecha' => '2026-11-05',
            'tipo_moneda' => 'PEN', 'tipo_cambio' => 1, 'tipo_compra' => 'local', 'subtotal' => 500, 'igv' => 90, 'total' => 590, 'total_pen' => 590, 'estado' => 'registrado', 'forma_pago' => 'contado']);

        $c = app(CentroCostosService::class)->ventas('2026-11-01', '2026-11-30')['contable'];

        $this->assertEquals(180.0, $c['igv_ventas']);
        $this->assertEquals(90.0, $c['igv_compras']);
        $this->assertEquals(90.0, $c['saldo_igv']);
        $this->assertEquals(590.0, $c['compras']);
    }

    public function test_la_pantalla_de_ventas_y_su_csv(): void
    {
        $this->vRing->update(['costo_promedio' => 100]);
        $this->venta(200, 2, '2026-11-10');
        $this->venta(60, 1, '2026-11-11');

        $this->get(route('centro-costos.ventas', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->assertSee('Utilidad bruta')->assertSee('Resumen contable del período')
            ->assertSee('Líneas vendidas por debajo de su costo')->assertSee('Por categoría')->assertSee('Por vendedor')->assertSee('Ring')->assertSee('S/ 460.00', false);
        $csv = $this->get(route('centro-costos.ventas.exportar', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->streamedContent();
        $this->assertStringContainsString('POR PRODUCTO', $csv);
        $this->assertStringContainsString('Ring', $csv);
        $this->get(route('centro-costos.ventas', ['desde' => '2030-01-01', 'hasta' => '2030-01-31']))->assertOk()->assertSee('No hay ventas en este período');
    }

    // ── Permisos y periodo ──

    public function test_el_contador_ve_importaciones_y_ventas_la_logistica_solo_importaciones(): void
    {
        $this->actingAs($this->usuario('Contador'));
        $this->get(route('centro-costos.importaciones'))->assertOk();
        $this->get(route('centro-costos.ventas'))->assertOk();

        $this->actingAs($this->usuario('Logística'));
        $this->get(route('centro-costos.importaciones'))->assertOk()->assertDontSee('Ventas y utilidad');
        $this->get(route('centro-costos.ventas'))->assertForbidden();
        $this->get(route('centro-costos.ventas.exportar'))->assertForbidden();
    }

    public function test_otros_roles_no_entran_y_la_raiz_lleva_a_importaciones(): void
    {
        $this->get(route('centro-costos.index'))->assertRedirect(route('centro-costos.importaciones'));
        foreach (['Marketing', 'Operaciones', 'Ventas', 'Cliente'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('centro-costos.importaciones'))->assertForbidden();
            $this->get(route('centro-costos.ventas'))->assertForbidden();
        }
    }

    public function test_el_periodo_se_normaliza(): void
    {
        $this->assertSame(['2026-11-01', today()->toDateString()], CentroCostosService::periodo('2026-11-01', null));
        $this->assertSame(['2026-10-01', '2026-11-30'], CentroCostosService::periodo('2026-11-30', '2026-10-01'), 'si vienen al revés se acomodan');
        $this->assertSame([today()->startOfMonth()->toDateString(), today()->toDateString()], CentroCostosService::periodo('no es fecha', 'tampoco'));
    }
}
