<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConteoInventario;
use App\Models\DetalleVenta;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CompraService;
use App\Services\EscanerService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Devoluciones, conteo físico y pantallas que muestran el stock por variante y almacén.
 */
class InventarioPorVarianteFlujosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);

        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Local B', 'tipo' => 'tienda', 'estado' => 'activo']);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);

        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'tamano' => 'grande', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002',
            'acabado_1' => 'blanco(a)', 'estado_dano' => 'leve', 'estado' => 'activo']);

        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->a->id, 'fecha' => now()->toDateString(),
            'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);

        // Stock de partida: A tiene 10 de V1 y 4 de V2; B tiene 6 de V1
        $esc = app(EscanerService::class);
        $esc->recibir($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 10], ['variante_id' => $this->v2->id, 'cantidad' => 4]], $this->admin->id);
        $esc->recibir($this->b->id, [['variante_id' => $this->v1->id, 'cantidad' => 6]], $this->admin->id);
    }

    private function stock(ProductoVariante $v, Almacen $a): int
    {
        return StockAlmacen::cantidadEn($v->producto_id, $a->id, $v->id);
    }

    private function vender(ProductoVariante $v, int $cantidad)
    {
        return app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->a->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
            'estado_pago' => 'pendiente', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $v->producto_id, 'variante_id' => $v->id, 'cantidad' => $cantidad, 'precio_unitario' => 30, 'incluye_igv' => false]]);
    }

    // ── Devoluciones ──────────────────────────────────────────────────────

    public function test_una_devolucion_vuelve_a_la_variante_vendida_y_se_puede_anular(): void
    {
        $venta = $this->vender($this->v1, 3);
        $this->assertSame(7, $this->stock($this->v1, $this->a));
        $detalle = DetalleVenta::where('venta_id', $venta->id)->first();

        $this->post(route('devoluciones.store'), [
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->a->id, 'cantidades' => [$detalle->id => 2], 'observaciones' => 'prueba',
        ])->assertSessionHas('success');

        $this->assertSame(9, $this->stock($this->v1, $this->a), 'devuelve 2 a la variante');
        $this->assertSame(4, $this->stock($this->v2, $this->a), 'la otra variante no cambia');
        $this->assertSame(9, (int) $this->v1->fresh()->stock_actual - 6, 'el stock global de la variante sube en 2 (A=9 + B=6 = 15)');

        $mov = MovimientoInventario::where('tipo_movimiento', 'devolucion')->first();
        $this->assertSame($this->v1->id, $mov->variante_id);

        // Anular la devolución quita lo devuelto de la misma variante
        $this->post(route('devoluciones.anular', $mov))->assertSessionHasNoErrors();
        $this->assertSame(7, $this->stock($this->v1, $this->a));
    }

    public function test_no_se_puede_devolver_mas_de_lo_vendido(): void
    {
        $venta = $this->vender($this->v1, 3);
        $detalle = DetalleVenta::where('venta_id', $venta->id)->first();

        $this->post(route('devoluciones.store'), [
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->a->id, 'cantidades' => [$detalle->id => 99],
        ]);

        $this->assertSame(10, $this->stock($this->v1, $this->a), 'se devuelve como máximo lo vendido (3)');
    }

    // ── Conteo físico ─────────────────────────────────────────────────────

    public function test_el_conteo_fisico_lista_una_fila_por_variante_con_su_stock_en_ese_almacen(): void
    {
        $this->post(route('inventario-fisico.store'), ['nombre' => 'Conteo A', 'almacen_id' => $this->a->id])->assertRedirect();

        $conteo = ConteoInventario::first();
        $detalles = $conteo->detalles()->get()->keyBy('variante_id');

        $this->assertCount(2, $detalles);
        $this->assertSame(10, (int) $detalles[$this->v1->id]->stock_sistema);
        $this->assertSame(4, (int) $detalles[$this->v2->id]->stock_sistema);

        $this->post(route('inventario-fisico.store'), ['nombre' => 'Conteo B', 'almacen_id' => $this->b->id]);
        $this->assertSame(6, (int) ConteoInventario::where('nombre', 'Conteo B')->first()->detalles()->where('variante_id', $this->v1->id)->value('stock_sistema'));

        $this->get(route('inventario-fisico.show', $conteo))->assertOk();
    }

    // ── Pantallas que leen el stock ───────────────────────────────────────

    public function test_el_detalle_del_almacen_muestra_cada_variante_con_su_cantidad(): void
    {
        $this->get(route('inventario.almacenes.show', $this->a))
            ->assertOk()->assertSee('KY-LU2501-0001')->assertSee('KY-LU2501-0002');

        $items = $this->get(route('inventario.almacenes.show', $this->b))->viewData('stockDetalle');
        $this->assertSame(6, $items->first()['stock']);
        $this->assertCount(1, $items->first()['variantes']);
    }

    public function test_la_busqueda_de_traslados_da_el_stock_de_la_variante_en_el_origen(): void
    {
        $r = $this->getJson(route('traslados.buscar-productos', ['q' => 'Ring', 'almacen_id' => $this->a->id]))->assertOk()->json();
        $variantes = collect($r[0]['variantes'])->pluck('stock', 'sku');
        $this->assertSame(10, $variantes['KY-LU2501-0001']);
        $this->assertSame(4, $variantes['KY-LU2501-0002']);
        $this->assertSame(14, $r[0]['stock_origen']);

        $r = $this->getJson(route('traslados.buscar-productos', ['q' => 'Ring', 'almacen_id' => $this->b->id]))->json();
        $this->assertSame(['KY-LU2501-0001' => 6, 'KY-LU2501-0002' => 0], collect($r[0]['variantes'])->pluck('stock', 'sku')->all());
    }

    public function test_la_pantalla_de_ventas_recibe_el_stock_de_cada_variante_por_almacen(): void
    {
        $productos = $this->get(route('ventas.create'))->assertOk()->viewData('productos');
        $variantes = collect($productos->firstWhere('id', $this->producto->id)['variantes'])->keyBy('sku');

        $this->assertEquals([$this->a->id => 10, $this->b->id => 6], $variantes['KY-LU2501-0001']['stock_por_almacen']);
        $this->assertEquals([$this->a->id => 4], $variantes['KY-LU2501-0002']['stock_por_almacen']);
    }

    public function test_el_stock_total_por_almacen_suma_todas_las_variantes(): void
    {
        $productos = $this->get(route('ventas.create'))->viewData('productos');
        $this->assertEquals([$this->a->id => 14, $this->b->id => 6], $productos->firstWhere('id', $this->producto->id)['stock_por_almacen']);
    }

    public function test_las_pantallas_de_inventario_cargan_con_stock_en_varios_almacenes(): void
    {
        foreach ([
            route('inventario.productos.index'),
            route('inventario.productos.index', ['almacen_id' => $this->a->id]),
            route('inventario.productos.show', $this->producto),
            route('inventario.productos.variantes', $this->producto),
            route('inventario.movimientos.create'),
            route('inventario.movimientos.index'),
            route('inventario.almacenes.index'),
            route('traslados.stock'),
            route('traslados.create'),
            route('inventario.reportes.stock-valorizado'),
            route('inventario.reportes.stock-valorizado', ['sucursal_id' => 1]),
            route('inventario.reportes.kardex'),
            route('inventario-fisico.create'),
            route('inventario.recepcion.index'),
            route('compras.create'),
            route('pedidos.create'),
            route('admin.dashboard'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_el_reporte_valorizado_por_almacen_usa_el_stock_de_cada_variante(): void
    {
        // El reporte filtra por sucursal; cada sucursal tiene su almacén
        $sucursalB = \App\Models\Sucursal::create(['codigo' => 'SUC-B', 'nombre' => 'Sucursal B', 'tipo' => 'tienda', 'almacen_id' => $this->b->id, 'estado' => 'activo']);

        $todas = collect($this->get(route('inventario.reportes.stock-valorizado'))->assertOk()->viewData('productos'))->firstWhere('id', $this->producto->id);
        $this->assertSame(20, (int) $todas['stock'], 'sin filtro: 10 + 4 en A y 6 en B');

        $enB = collect($this->get(route('inventario.reportes.stock-valorizado', ['sucursal_id' => $sucursalB->id]))->assertOk()->viewData('productos'))->firstWhere('id', $this->producto->id);
        $this->assertSame(6, (int) $enB['stock'], 'en B solo hay 6 de V1');
        $this->assertCount(1, $enB['variantes'], 'V2 no tiene stock en B y no aparece');
    }

    // ── Compras y pedidos: etiqueta de variante ───────────────────────────

    public function test_los_selectores_de_compras_muestran_el_codigo_y_los_atributos_de_la_variante(): void
    {
        $r = $this->getJson(route('compras.buscar-productos', ['q' => 'Ring']))->assertOk()->json();
        $variantes = collect($r)->flatMap(fn ($p) => $p['variantes'] ?? []);

        $this->assertTrue($variantes->contains(fn ($v) => ($v['etiqueta'] ?? '') === 'KY-LU2501-0001 · negro(a) / grande'), 'etiqueta con código y atributos');
        $this->assertTrue($variantes->every(fn ($v) => !empty($v['etiqueta'])));
    }
}
