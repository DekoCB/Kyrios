<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use App\Services\CompraService;
use App\Services\CostoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostosYMargenesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-X', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function comprar(array $lineas, array $cabecera = [], ?Proveedor $prov = null)
    {
        $prov ??= Proveedor::firstOrCreate(['ruc' => '20555666771'], ['razon_social' => 'Proveedor SAC', 'estado' => 'activo']);

        return app(CompraService::class)->registrarCompra($cabecera + [
            'codigo' => 'C-' . uniqid(), 'proveedor_id' => $prov->id, 'almacen_id' => $this->almacen->id, 'user_id' => $this->admin->id,
            'numero_factura' => 'F001-' . uniqid(), 'fecha' => now()->toDateString(), 'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_cambio' => 1,
            'tipo_compra' => 'local', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'total_pen' => 0, 'estado' => 'registrado',
        ], $lineas);
    }

    private function linea(ProductoVariante $v, int $cantidad, float $precio): array
    {
        return ['producto_id' => $this->producto->id, 'variante_id' => $v->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'descuento' => 0];
    }

    private function precio(float $monto, bool $conIgv = false): ProductoPrecio
    {
        return ProductoPrecio::create(['producto_id' => $this->producto->id, 'tipo_precio' => 'venta_regular', 'precio' => $monto,
            'moneda' => 'PEN', 'incluye_igv' => $conIgv, 'activo' => true, 'prioridad' => 1]);
    }

    public function test_el_costo_es_el_promedio_ponderado_por_cantidad(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10)]);
        $this->comprar([$this->linea($this->v1, 30, 20)]);

        // (10×10 + 30×20) / 40 = 17.50; el último costo es el de la compra más reciente
        $this->assertEquals(17.50, $this->v1->fresh()->costo_promedio);
        $this->assertEquals(20.00, $this->v1->fresh()->ultimo_costo_compra);
        $this->assertEquals(17.50, $this->producto->fresh()->costo_promedio);
        $this->assertEquals(20.00, $this->producto->fresh()->ultimo_costo_compra);
    }

    public function test_el_costo_del_producto_pondera_todas_sus_variantes_y_el_de_la_variante_solo_las_suyas(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10), $this->linea($this->v2, 10, 30)]);

        $this->assertEquals(10.00, $this->v1->fresh()->costo_promedio);
        $this->assertEquals(30.00, $this->v2->fresh()->costo_promedio);
        $this->assertEquals(20.00, $this->producto->fresh()->costo_promedio);
    }

    public function test_una_compra_en_dolares_se_valora_en_soles_con_su_tipo_de_cambio(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10)], ['tipo_moneda' => 'USD', 'tipo_cambio' => 3.8]);

        $this->assertEquals(38.00, $this->v1->fresh()->costo_promedio);
    }

    public function test_los_gastos_de_importacion_forman_parte_del_costo(): void
    {
        // 10 u × US$10 = US$100; flete US$20 → +US$2 por unidad → US$12 × 3.8 = S/ 45.60
        $this->comprar([$this->linea($this->v1, 10, 10)], ['tipo_compra' => 'importacion', 'tipo_moneda' => 'USD', 'tipo_cambio' => 3.8, 'flete_usd' => 20]);

        $this->assertEquals(45.60, $this->v1->fresh()->costo_promedio);
    }

    public function test_anular_una_compra_la_saca_del_promedio(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10)]);
        $segunda = $this->comprar([$this->linea($this->v1, 10, 30)]);
        $this->assertEquals(20.00, $this->v1->fresh()->costo_promedio);

        app(CompraService::class)->anularCompra($segunda->fresh('detalles'), 'error de digitación');

        $this->assertEquals(10.00, $this->v1->fresh()->costo_promedio);
        $this->assertEquals(10.00, $this->producto->fresh()->costo_promedio);
    }

    public function test_el_comando_corrige_costos_guardados_con_el_calculo_anterior(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10)]);
        $this->comprar([$this->linea($this->v1, 30, 20)]);
        $this->producto->update(['costo_promedio' => 99]);
        $this->v1->update(['costo_promedio' => 99]);

        $this->artisan('costos:recalcular')->assertSuccessful();

        $this->assertEquals(17.50, $this->producto->fresh()->costo_promedio);
        $this->assertEquals(17.50, $this->v1->fresh()->costo_promedio);
    }

    public function test_el_historial_acumula_el_promedio_y_marca_las_alzas(): void
    {
        $this->comprar([$this->linea($this->v1, 10, 10)]);
        $this->comprar([$this->linea($this->v1, 10, 15)]);

        $h = app(CostoService::class)->historial($this->producto->id);

        $this->assertCount(2, $h);
        $this->assertEquals(15.0, $h[0]->costo);          // más reciente primero
        $this->assertEquals(50.0, $h[0]->variacion);      // +50 % frente a 10
        $this->assertEquals(12.5, $h[0]->promedio);
        $this->assertNull($h[1]->variacion);
    }

    public function test_el_comparador_ordena_los_proveedores_del_mas_barato_al_mas_caro(): void
    {
        $caro = Proveedor::create(['ruc' => '20111111111', 'razon_social' => 'Caro SAC', 'estado' => 'activo']);
        $barato = Proveedor::create(['ruc' => '20222222222', 'razon_social' => 'Barato SAC', 'estado' => 'activo']);
        $this->comprar([$this->linea($this->v1, 5, 20)], [], $caro);
        $this->comprar([$this->linea($this->v1, 5, 12)], [], $barato);

        $p = app(CostoService::class)->proveedores($this->producto->id);

        $this->assertSame(['Barato SAC', 'Caro SAC'], $p->pluck('proveedor')->all());
    }

    public function test_margen_sobre_costo_y_precio_sugerido(): void
    {
        $this->assertSame(25.0, CostoService::margen(125.0, 100.0));
        $this->assertNull(CostoService::margen(125.0, 0.0));
        $this->assertNull(CostoService::margen(null, 100.0));
        $this->assertSame(['sin_igv' => 130.0, 'con_igv' => 153.4], CostoService::precioSugerido(100.0, 30.0));
        $this->assertSame('bajo', CostoService::estado(100.0, 120.0, 30.0));
        $this->assertSame('ok', CostoService::estado(100.0, 130.0, 30.0));
        $this->assertSame('sin_minimo', CostoService::estado(100.0, 130.0, null));
        $this->assertSame('sin_precio', CostoService::estado(100.0, null, 30.0));
        $this->assertSame('sin_costo', CostoService::estado(null, 130.0, 30.0));
    }

    public function test_el_precio_con_igv_se_lleva_a_sin_igv_para_medir_el_margen(): void
    {
        $this->precio(118.0, true);

        $this->assertEquals(100.0, app(CostoService::class)->precioSinIgv($this->producto->id));
    }

    public function test_avisa_cuando_el_margen_queda_bajo_el_minimo_y_lo_retira_al_corregir_el_precio(): void
    {
        $this->producto->update(['margen_minimo' => 30]);
        $this->precio(110.0);                                  // sin IGV
        $this->comprar([$this->linea($this->v1, 10, 100)]);    // costo 100 → margen 10 %

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
        $aviso = $this->admin->unreadNotifications->first()->data;
        $this->assertSame('Margen por debajo del mínimo', $aviso['titulo']);
        $this->assertStringContainsString('margen 10%', $aviso['mensaje']);
        $this->assertStringContainsString('S/ 130.00', $aviso['mensaje']);

        ProductoPrecio::where('producto_id', $this->producto->id)->update(['precio' => 140]);
        $this->artisan('alertas:margenes')->assertSuccessful();

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame(0, $this->logistica->unreadNotifications()->count());
    }

    public function test_sin_margen_general_ni_propio_el_producto_no_genera_avisos(): void
    {
        CostoService::guardarMinimoGeneral(null);
        $this->precio(101.0);
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        $this->artisan('alertas:margenes')->assertSuccessful();

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_administracion_y_logistica_ven_el_tablero_y_la_ficha_de_costos(): void
    {
        $this->precio(150.0);
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        foreach ([$this->admin, $this->logistica] as $u) {
            $this->actingAs($u)->get(route('precios.costos.index'))->assertOk()->assertSee('Costos y márgenes')->assertSee('Ring');
            $this->actingAs($u)->get(route('precios.costos.show', $this->producto))->assertOk()
                ->assertSee('Comparador de proveedores')->assertSee('Historial de compras')->assertSee('S/ 100.00');
        }
    }

    public function test_los_demas_roles_no_ven_costos_ni_margenes(): void
    {
        foreach (['Marketing', 'Contador', 'Operaciones'] as $rol) {
            $u = $this->usuario($rol);
            $this->actingAs($u)->get(route('precios.costos.index'))->assertForbidden();
            $this->actingAs($u)->get(route('precios.costos.show', $this->producto))->assertForbidden();
            $this->actingAs($u)->post(route('precios.costos.margen-minimo', $this->producto), ['margen_minimo' => 20])->assertForbidden();
        }
        $this->assertNull($this->producto->fresh()->margen_minimo);
    }

    public function test_el_tablero_filtra_por_estado(): void
    {
        $this->producto->update(['margen_minimo' => 30]);
        $this->precio(110.0);
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        $this->get(route('precios.costos.index', ['estado' => 'bajo']))->assertOk()->assertSee('Ring');
        $this->get(route('precios.costos.index', ['estado' => 'ok']))->assertOk()->assertDontSee('KY-LU2501-0001');
    }

    public function test_se_guarda_y_se_quita_el_margen_minimo_con_validacion(): void
    {
        $this->actingAs($this->logistica)->post(route('precios.costos.margen-minimo', $this->producto), ['margen_minimo' => '27.5'])->assertSessionHasNoErrors();
        $this->assertEquals(27.5, $this->producto->fresh()->margen_minimo);

        $this->post(route('precios.costos.margen-minimo', $this->producto), ['margen_minimo' => '-5'])->assertSessionHasErrors('margen_minimo');
        $this->post(route('precios.costos.margen-minimo', $this->producto), ['margen_minimo' => 'abc'])->assertSessionHasErrors('margen_minimo');
        $this->assertEquals(27.5, $this->producto->fresh()->margen_minimo);

        $this->post(route('precios.costos.margen-minimo', $this->producto), ['margen_minimo' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->producto->fresh()->margen_minimo);
    }

    public function test_el_menu_de_administracion_y_logistica_enlaza_costos_y_margenes(): void
    {
        foreach ([$this->admin, $this->logistica] as $u) {
            $this->actingAs($u)->get(route('precios.costos.index'))->assertSee(route('precios.costos.index'), false);
        }
    }

    public function test_el_margen_general_arranca_en_30_y_rige_para_los_productos_sin_minimo_propio(): void
    {
        $this->assertSame(30.0, CostoService::minimoGeneral());
        $this->precio(120.0);                                  // margen 20 % < 30 % general
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertStringContainsString('mínimo 30%', $this->admin->unreadNotifications->first()->data['mensaje']);
    }

    public function test_el_minimo_propio_manda_sobre_el_general(): void
    {
        $this->producto->update(['margen_minimo' => 10]);      // con 20 % de margen cumple su propio mínimo
        $this->precio(120.0);
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame('ok', CostoService::estado(100.0, 120.0, CostoService::minimoDe($this->producto->fresh())));
    }

    public function test_se_cambia_el_margen_general_y_se_valida(): void
    {
        $this->actingAs($this->logistica)->post(route('precios.costos.margen-general'), ['margen_general' => '25.5'])->assertSessionHasNoErrors();
        $this->assertSame(25.5, CostoService::minimoGeneral());

        $this->post(route('precios.costos.margen-general'), ['margen_general' => '-1'])->assertSessionHasErrors('margen_general');
        $this->post(route('precios.costos.margen-general'), ['margen_general' => 'x'])->assertSessionHasErrors('margen_general');
        $this->assertSame(25.5, CostoService::minimoGeneral());

        $this->post(route('precios.costos.margen-general'), ['margen_general' => ''])->assertSessionHasNoErrors();
        $this->assertNull(CostoService::minimoGeneral());
    }

    public function test_aplicar_a_todos_quita_los_minimos_propios(): void
    {
        $this->producto->update(['margen_minimo' => 5]);

        $this->post(route('precios.costos.margen-general'), ['margen_general' => '40', 'aplicar_a_todos' => '1'])->assertSessionHas('success');
        $this->assertNull($this->producto->fresh()->margen_minimo);
        $this->assertSame(40.0, CostoService::minimoGeneral());

        // Sin la casilla, los mínimos propios se respetan
        Producto::whereKey($this->producto->id)->update(['margen_minimo' => 5]);
        $this->post(route('precios.costos.margen-general'), ['margen_general' => '35']);
        $this->assertEquals(5, $this->producto->fresh()->margen_minimo);
    }

    public function test_solo_administracion_y_logistica_cambian_el_margen_general(): void
    {
        $this->actingAs($this->usuario('Marketing'))->post(route('precios.costos.margen-general'), ['margen_general' => '1'])->assertForbidden();
        $this->assertSame(30.0, CostoService::minimoGeneral());
    }

    public function test_si_son_muchos_los_productos_bajo_el_minimo_se_envia_un_solo_aviso_resumen(): void
    {
        $cat = Categoria::first();
        for ($i = 0; $i < CostoService::MAX_AVISOS_INDIVIDUALES + 2; $i++) {
            $p = Producto::create(['codigo' => "KY-LU2501-1{$i}", 'nombre' => "Aro {$i}", 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad',
                'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo', 'costo_promedio' => 100]);
            ProductoPrecio::create(['producto_id' => $p->id, 'tipo_precio' => 'venta_regular', 'precio' => 110, 'moneda' => 'PEN', 'incluye_igv' => false, 'activo' => true, 'prioridad' => 1]);
        }

        $r = app(CostoService::class)->revisarMargenes();

        $this->assertSame(CostoService::MAX_AVISOS_INDIVIDUALES + 2, $r['bajo_minimo']);
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame('Productos por debajo del margen mínimo', $this->admin->unreadNotifications->first()->data['titulo']);
        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
    }

    public function test_el_tablero_muestra_el_margen_general_y_los_productos_que_lo_heredan(): void
    {
        $this->precio(150.0);
        $this->comprar([$this->linea($this->v1, 10, 100)]);

        $this->get(route('precios.costos.index'))->assertOk()->assertSee('Margen mínimo general')->assertSee('30 (general)');
    }

    private function precioDeVariante(ProductoVariante $v, float $monto): ProductoPrecio
    {
        return ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $v->id, 'tipo_precio' => 'venta_regular', 'precio' => $monto,
            'moneda' => 'PEN', 'incluye_igv' => false, 'activo' => true, 'prioridad' => 1]);
    }

    public function test_con_precios_por_variante_el_producto_se_evalua_por_su_peor_margen(): void
    {
        $this->precioDeVariante($this->v1, 200.0);   // costo 100 → margen 100 %
        $this->precioDeVariante($this->v2, 110.0);   // costo 100 → margen 10 %  (peor)
        $this->comprar([$this->linea($this->v1, 5, 100), $this->linea($this->v2, 5, 100)]);

        $costos = app(CostoService::class);
        $e = $costos->evaluar($this->producto->fresh(), $costos->precios([$this->producto->id]), $costos->variantesDe([$this->producto->id]), 30.0);

        $this->assertSame('bajo', $e->estado);
        $this->assertSame(10.0, $e->margen);
        $this->assertSame(110.0, $e->precio);
        $this->assertSame('KY-LU2501-0002', $e->variante);
        $this->assertSame(2, $e->con_precio);
    }

    public function test_un_producto_con_precios_solo_por_variante_no_sale_como_sin_precio(): void
    {
        $this->precioDeVariante($this->v1, 200.0);
        $this->comprar([$this->linea($this->v1, 5, 100)]);

        $this->get(route('precios.costos.index', ['estado' => 'ok']))->assertOk()->assertSee('KY-LU2501-0001');
        $this->get(route('precios.costos.index', ['estado' => 'sin_precio']))->assertOk()->assertDontSee('KY-LU2501-0001');
    }

    public function test_el_aviso_con_precios_por_variante_nombra_la_variante_de_peor_margen(): void
    {
        $this->precioDeVariante($this->v1, 200.0);
        $this->precioDeVariante($this->v2, 110.0);
        $this->comprar([$this->linea($this->v1, 5, 100), $this->linea($this->v2, 5, 100)]);

        $mensaje = $this->admin->unreadNotifications->first()->data['mensaje'];
        $this->assertStringContainsString('KY-LU2501-0002', $mensaje);
        $this->assertStringContainsString('margen 10%', $mensaje);
    }

    public function test_una_variante_sin_compras_propias_usa_el_costo_del_producto(): void
    {
        $this->precioDeVariante($this->v2, 110.0);
        $this->comprar([$this->linea($this->v1, 5, 100)]);   // solo v1 se compró; v2 hereda el costo del producto (100)

        $costos = app(CostoService::class);
        $e = $costos->evaluar($this->producto->fresh(), $costos->precios([$this->producto->id]), $costos->variantesDe([$this->producto->id]), 30.0);

        $this->assertSame(10.0, $e->margen);
    }
}
