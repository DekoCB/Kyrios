<?php

namespace Tests\Feature;

use App\Console\Commands\ImportarListaPrecios;
use App\Models\Catalogo\Marca;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use Database\Seeders\KyriosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** precios:importar-lista — aplica la «Lista de precios KYRIOS» (JSON) a precios, costos y productos nuevos. */
class ImportarListaPreciosTest extends TestCase
{
    use RefreshDatabase;

    private string $archivo;
    private Producto $producto;
    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KyriosCatalogoSeeder::class);
        $this->archivo = tempnam(sys_get_temp_dir(), 'lista') . '.json';

        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2502-0001', 'nombre' => 'Aurora', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2502-0001', 'codigo_kyrios' => 'KY-LU2502-0001',
            'codigo_fabricante' => '62115', 'codigo_excel' => 'KLU02-00013', 'estado' => 'activo']);
        ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'tipo_precio' => 'venta_regular',
            'precio' => 100, 'precio_compra' => 50, 'moneda' => 'USD', 'incluye_igv' => true, 'prioridad' => 1, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);
        parent::tearDown();
    }

    private function fila(array $extra = []): array
    {
        return array_merge(['hoja' => 'FARO', 'marca' => 'FARO BARCELONA', 'klu' => 'KLU02-00013', 'cod_fab' => 62115, 'desc' => 'AUREA APLIQUE BLANCO',
            'color' => 'BLANCO', 'material' => 'METAL', 'c_fab' => 20.0, 'c_alm' => 26.0, 'pv_usd' => 100.0, 'of_usd' => 70.8, 'oes' => 250.0], $extra);
    }

    private function correr(array $filas, bool $aplicar = true)
    {
        file_put_contents($this->archivo, json_encode($filas));

        return $this->artisan('precios:importar-lista', ['archivo' => $this->archivo] + ($aplicar ? ['--aplicar' => true] : []));
    }

    public function test_por_defecto_solo_simula_y_no_guarda(): void
    {
        $this->correr([$this->fila()], false)->assertSuccessful();

        $this->assertSame(1, ProductoPrecio::count());
        $this->assertNull($this->variante->fresh()->precio_fabrica);
    }

    public function test_actualiza_el_precio_regular_en_dolares_con_igv_con_su_costo_y_deja_el_anterior_inactivo(): void
    {
        $this->correr([$this->fila()])->assertSuccessful();

        $vigente = ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_regular')->where('activo', true)->sole();
        // 100 sin IGV en la lista → 118 con IGV guardado, como todos los precios regulares del sistema
        $this->assertEquals(118.00, (float) $vigente->precio);
        $this->assertSame('USD', $vigente->moneda);
        $this->assertTrue((bool) $vigente->incluye_igv);
        $this->assertEquals(26.00, (float) $vigente->precio_compra);
        $this->assertEquals(353.85, (float) $vigente->margen);
        $this->assertSame(1, ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_regular')->where('activo', false)->count());
        $this->assertEquals(100.0, $vigente->precio_con_igv / 1.18);
    }

    public function test_guarda_oferta_en_dolares_con_igv_y_oechsle_en_soles_sin_igv(): void
    {
        $this->correr([$this->fila()])->assertSuccessful();

        $oferta = ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_oferta')->sole();
        $this->assertEquals(70.80, (float) $oferta->precio);
        $this->assertTrue((bool) $oferta->incluye_igv);
        $oechsle = ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_oechsle')->sole();
        $this->assertEquals(250.00, (float) $oechsle->precio);
        $this->assertSame('PEN', $oechsle->moneda);
        $this->assertFalse((bool) $oechsle->incluye_igv);
    }

    public function test_el_costo_de_fabrica_solo_se_escribe_si_la_variante_no_lo_tiene(): void
    {
        $this->correr([$this->fila()])->assertSuccessful();
        $this->assertEquals(20.00, (float) $this->variante->fresh()->precio_fabrica);

        $this->correr([$this->fila(['c_fab' => 99.0])])->assertSuccessful();
        $this->assertEquals(20.00, (float) $this->variante->fresh()->precio_fabrica);
    }

    public function test_no_toca_el_costo_promedio_de_las_compras(): void
    {
        $this->producto->update(['costo_promedio' => 31.5]);
        $this->variante->update(['costo_promedio' => 31.5]);

        $this->correr([$this->fila()])->assertSuccessful();

        $this->assertEquals(31.5, (float) $this->producto->fresh()->costo_promedio);
        $this->assertEquals(31.5, (float) $this->variante->fresh()->costo_promedio);
    }

    public function test_repetir_la_importacion_no_duplica_precios(): void
    {
        $this->correr([$this->fila()])->assertSuccessful();
        $total = ProductoPrecio::count();

        $this->correr([$this->fila()])->assertSuccessful();

        $this->assertSame($total, ProductoPrecio::count());
    }

    public function test_el_codigo_de_fabrica_manda_sobre_el_klu_repetido(): void
    {
        $otro = Producto::create(['codigo' => 'KY-LU2502-0002', 'nombre' => 'Tarim', 'categoria_id' => $this->producto->categoria_id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $vOtro = ProductoVariante::create(['producto_id' => $otro->id, 'sku' => 'KY-LU2502-0002', 'codigo_kyrios' => 'KY-LU2502-0002',
            'codigo_fabricante' => '70449', 'codigo_excel' => 'KLU02-00013', 'estado' => 'activo']);

        $this->correr([$this->fila()])->assertSuccessful();

        $this->assertSame(0, ProductoPrecio::where('variante_id', $vOtro->id)->count());
        $this->assertSame(1, ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_regular')->where('activo', true)->count());
    }

    public function test_si_la_fabrica_no_existe_usa_el_klu(): void
    {
        $this->correr([$this->fila(['cod_fab' => 'NUEVO-1'])])->assertSuccessful();

        $this->assertSame(0, Producto::where('id', '!=', $this->producto->id)->count());
        $this->assertEquals(118.0, (float) ProductoPrecio::where('variante_id', $this->variante->id)->where('tipo_precio', 'venta_regular')->where('activo', true)->value('precio'));
    }

    public function test_las_filas_sin_precio_de_venta_no_se_tocan_ni_se_crean(): void
    {
        $this->correr([
            $this->fila(['pv_usd' => null, 'c_alm' => null]),
            $this->fila(['cod_fab' => 'X-SIN', 'klu' => 'KLU99-00001', 'pv_usd' => null]),
        ])->assertSuccessful();

        $this->assertSame(1, ProductoPrecio::count());
        $this->assertSame(1, Producto::count());
    }

    public function test_crea_el_producto_que_no_existe_con_codigo_ky_marca_nueva_y_tipo_segun_la_descripcion(): void
    {
        $this->correr([$this->fila(['hoja' => 'ILUMITEC', 'marca' => 'ILUMITEC', 'klu' => 'KLU34-00003', 'cod_fab' => '8948BRO-T',
            'desc' => 'COLGANTE LED 3 LUCES', 'color' => 'DORADO', 'material' => 'METAL', 'alto' => 430.0])])->assertSuccessful();

        $marca = Marca::where('nombre', 'ILUMITEC')->sole();
        $variante = ProductoVariante::where('codigo_fabricante', '8948BRO-T')->sole();
        $producto = $variante->producto;
        $this->assertSame('KLU34-00003', $variante->codigo_excel);
        $this->assertStringStartsWith('KY-LU20' . $marca->codigo, $variante->codigo_kyrios);
        $this->assertSame($variante->codigo_kyrios, $producto->codigo);
        $this->assertSame('COLGANTE LED 3 LUCES', $producto->nombre_fabrica);
        $this->assertSame('dorado', $variante->acabado_1);
        $this->assertEquals(430.0, (float) $variante->alto);
        $this->assertSame('colgante', $producto->tipoLuminaria?->nombre);
        $this->assertSame(1, ProductoPrecio::where('variante_id', $variante->id)->where('tipo_precio', 'venta_regular')->where('activo', true)->count());
    }

    public function test_un_producto_nuevo_sin_descripcion_queda_como_sin_tipo(): void
    {
        $this->correr([$this->fila(['hoja' => 'LEDS C4', 'marca' => 'LEDS-C4', 'klu' => 'KLU01-00168', 'cod_fab' => '00-0052-ah-ah', 'desc' => null,
            'color' => null, 'material' => null])])->assertSuccessful();

        $variante = ProductoVariante::where('codigo_fabricante', '00-0052-ah-ah')->sole();
        $this->assertStringStartsWith('KY-ST00', $variante->codigo_kyrios);
    }

    public function test_una_fabrica_repetida_en_la_lista_se_omite(): void
    {
        $this->correr([$this->fila(), $this->fila(['pv_usd' => 55.0])])->assertSuccessful();

        $this->assertSame(1, ProductoPrecio::count());
    }

    public function test_si_la_fabrica_coincide_con_productos_distintos_se_omite(): void
    {
        $otro = Producto::create(['codigo' => 'KY-LU2502-0003', 'nombre' => 'Otro', 'categoria_id' => $this->producto->categoria_id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $otro->id, 'sku' => 'KY-LU2502-0003', 'codigo_kyrios' => 'KY-LU2502-0003', 'codigo_fabricante' => '62115', 'estado' => 'activo']);

        $this->correr([$this->fila()])->assertSuccessful();

        $this->assertSame(1, ProductoPrecio::count());
    }

    public function test_revertir_devuelve_todo_como_estaba(): void
    {
        $this->correr([$this->fila(), $this->fila(['hoja' => 'ILUMITEC', 'marca' => 'ILUMITEC', 'klu' => 'KLU34-00003', 'cod_fab' => '8948BRO-T', 'desc' => 'LAMP.MESA'])])->assertSuccessful();
        $respaldo = collect(glob(storage_path('app/importacion_lista_precios_*.json')))->sort()->last();
        $this->assertNotEmpty($respaldo);

        $this->artisan('precios:importar-lista', ['--revertir' => $respaldo])->assertSuccessful();
        @unlink($respaldo);

        $this->assertSame(1, Producto::count());
        $this->assertNull(Marca::where('nombre', 'ILUMITEC')->first());
        $this->assertNull($this->variante->fresh()->precio_fabrica);
        $vigente = ProductoPrecio::where('variante_id', $this->variante->id)->where('activo', true)->sole();
        $this->assertEquals(100.0, (float) $vigente->precio);
        $this->assertTrue((bool) $vigente->incluye_igv);
    }

    public function test_la_migracion_pasa_a_con_igv_los_precios_importados_sin_igv_y_se_puede_deshacer(): void
    {
        $migracion = require database_path('migrations/2026_11_25_120000_precios_de_lista_con_igv.php');
        $sinIgv = ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'tipo_precio' => 'venta_regular',
            'precio' => 100, 'precio_compra' => 26, 'margen' => 284.62, 'moneda' => 'USD', 'incluye_igv' => false, 'prioridad' => 1, 'activo' => false,
            'observaciones' => ImportarListaPrecios::NOTA]);
        $ajeno = ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'tipo_precio' => 'venta_regular',
            'precio' => 50, 'precio_compra' => 20, 'moneda' => 'USD', 'incluye_igv' => false, 'prioridad' => 1, 'activo' => false, 'observaciones' => 'Carga manual']);

        $migracion->up();

        $this->assertEquals(118.0, (float) $sinIgv->fresh()->precio);
        $this->assertTrue((bool) $sinIgv->fresh()->incluye_igv);
        $this->assertEquals(353.85, (float) $sinIgv->fresh()->margen);
        $this->assertEquals(50.0, (float) $ajeno->fresh()->precio, 'solo toca los precios de la lista');
        $this->assertFalse((bool) $ajeno->fresh()->incluye_igv);

        $migracion->down();

        $this->assertEquals(100.0, (float) $sinIgv->fresh()->precio);
        $this->assertFalse((bool) $sinIgv->fresh()->incluye_igv);
    }
}
