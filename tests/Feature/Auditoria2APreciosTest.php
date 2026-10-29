<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gestión de precios por variante (en Kyrios las variantes se distinguen por acabado y tamaño, no por capacidad).
 */
class Auditoria2APreciosTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;
    private ProductoVariante $negro;
    private ProductoVariante $blanco;

    protected function setUp(): void
    {
        parent::setUp();
        Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->negro = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->blanco = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-2', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['precio_compra' => 50, 'precio_venta' => 118, 'margen' => 100, 'incluye_igv' => 1], $extra);
    }

    public function test_el_precio_de_una_variante_no_se_aplica_a_las_demas(): void
    {
        $this->post(route('precios.store', $this->producto), $this->datos(['variante_id' => $this->negro->id]))->assertSessionHasNoErrors();

        $this->assertTrue(ProductoPrecio::where('producto_id', $this->producto->id)->where('variante_id', $this->negro->id)->where('activo', true)->exists());
        $this->assertFalse(ProductoPrecio::where('producto_id', $this->producto->id)->where('variante_id', $this->blanco->id)->exists(),
            'la variante blanca no debe heredar el precio de la negra');
    }

    public function test_una_variante_de_otro_producto_no_se_acepta(): void
    {
        $otro = Producto::create(['codigo' => 'KY-LU2501-0099', 'nombre' => 'Otro', 'categoria_id' => $this->producto->categoria_id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $ajena = ProductoVariante::create(['producto_id' => $otro->id, 'sku' => 'KY-9', 'codigo_kyrios' => 'KY-LU2501-0099', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);

        $this->post(route('precios.store', $this->producto), $this->datos(['variante_id' => $ajena->id]))->assertSessionHasErrors('variante_id');

        $this->assertSame(0, ProductoPrecio::count());
    }

    public function test_precios_gigantes_se_rechazan_sin_error_500(): void
    {
        $r = $this->post(route('precios.store', $this->producto), $this->datos(['precio_compra' => 99999999999, 'precio_venta' => 99999999999, 'precio_mayorista' => 99999999999]));

        $this->assertLessThan(500, $r->getStatusCode());
        $r->assertSessionHasErrors(['precio_compra', 'precio_venta', 'precio_mayorista']);
    }

    public function test_el_precio_guardado_respeta_que_incluye_igv_como_muestra_el_formulario(): void
    {
        $this->post(route('precios.store', $this->producto), $this->datos(['variante_id' => $this->negro->id, 'incluye_igv' => 1]))->assertSessionHasNoErrors();

        $precio = ProductoPrecio::where('variante_id', $this->negro->id)->where('tipo_precio', 'venta_regular')->first();
        $this->assertTrue((bool) $precio->incluye_igv, 'el POS no debe sumar otro 18 % a un precio que ya lo incluye');

        $this->put(route('precios.update', [$this->producto, $precio->id]), $this->datos(['incluye_igv' => 1, 'precio_venta' => 130]))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $precio->fresh()->incluye_igv);
    }

    public function test_no_se_edita_ni_restaura_el_precio_de_otro_producto(): void
    {
        $otro = Producto::create(['codigo' => 'KY-LU2501-0099', 'nombre' => 'Otro', 'categoria_id' => $this->producto->categoria_id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $ajeno = ProductoPrecio::create(['producto_id' => $otro->id, 'tipo_precio' => 'venta_regular', 'precio' => 50, 'precio_compra' => 20, 'margen' => 150, 'incluye_igv' => true, 'activo' => true]);

        $this->put(route('precios.update', [$this->producto, $ajeno->id]), $this->datos(['precio_venta' => 1]))->assertNotFound();
        $this->post(route('precios.bulk-action', $this->producto), ['action' => 'update_price', 'price_ids' => [$ajeno->id], 'value' => 1]);

        $this->assertEquals(50.0, (float) $ajeno->fresh()->precio);
    }

    public function test_actualizar_en_bloque_sin_valor_se_rechaza(): void
    {
        $this->post(route('precios.store', $this->producto), $this->datos())->assertSessionHasNoErrors()->assertSessionMissing('error');
        $precio = ProductoPrecio::first();

        $r = $this->postJson(route('precios.bulk-action', $this->producto), ['action' => 'update_price', 'price_ids' => [$precio->id]]);

        $r->assertStatus(422);
        $this->assertEquals(118.0, (float) $precio->fresh()->precio);
    }

    public function test_se_guarda_el_precio_base_de_un_producto_sin_elegir_variante(): void
    {
        $this->post(route('precios.store', $this->producto), $this->datos())->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertTrue(ProductoPrecio::where('producto_id', $this->producto->id)->whereNull('variante_id')->where('activo', true)->exists());
    }
}
