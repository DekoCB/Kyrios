<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Salida rápida con escáner (PDA): escanear → descontar stock del almacén con motivo.
 */
class SalidaEscanerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Almacen $otro;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;
    private MotivoMovimiento $motivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->otro = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Local B', 'tipo' => 'principal', 'estado' => 'activo']);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);

        app(EscanerService::class)->recibir($this->almacen->id, [
            ['variante_id' => $this->v1->id, 'cantidad' => 10], ['variante_id' => $this->v2->id, 'cantidad' => 3],
        ], $this->admin->id);

        $this->motivo = MotivoMovimiento::create(['nombre' => 'Producto dañado', 'codigo' => 'DANADO', 'tipo' => 'salida', 'estado' => 'activo']);
        $this->actingAs($this->admin);
    }

    private function retirar(array $lineas, ?int $almacen = null, ?int $motivo = null, ?string $obs = null)
    {
        return $this->postJson(route('inventario.salida.confirmar'), [
            'almacen_id' => $almacen ?? $this->almacen->id, 'motivo_id' => $motivo ?? $this->motivo->id, 'observaciones' => $obs, 'lineas' => $lineas,
        ]);
    }

    private function stock(ProductoVariante $v, ?Almacen $a = null): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, ($a ?? $this->almacen)->id, $v->id);
    }

    public function test_la_pantalla_de_salida_carga_con_motivos_y_enlace_a_entrada(): void
    {
        $this->get(route('inventario.salida.index'))->assertOk()->assertSee('Salida con escáner')->assertSee('Producto dañado')->assertSee('Almacén de donde sale');
        $this->get(route('inventario.recepcion.index'))->assertOk()->assertSee('Salida');
    }

    public function test_solo_administracion_y_logistica_acceden(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));
        $this->get(route('inventario.salida.index'))->assertOk();

        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Contador'])->id, 'estado' => 'activo']));
        $this->get(route('inventario.salida.index'))->assertForbidden();
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 1]])->assertForbidden();
    }

    public function test_la_busqueda_devuelve_el_stock_del_almacen_elegido(): void
    {
        $r = $this->getJson(route('inventario.recepcion.buscar', ['codigo' => 'KY-LU2501-0001', 'almacen_id' => $this->almacen->id]))->json('resultados.0');
        $this->assertSame(10, $r['stock_almacen']);

        $r = $this->getJson(route('inventario.recepcion.buscar', ['codigo' => 'KY-LU2501-0001', 'almacen_id' => $this->otro->id]))->json('resultados.0');
        $this->assertSame(0, $r['stock_almacen']);
    }

    public function test_la_salida_descuenta_stock_y_deja_movimiento_con_motivo(): void
    {
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 2], ['variante_id' => $this->v1->id, 'cantidad' => 1], ['variante_id' => $this->v2->id, 'cantidad' => 3]], null, null, 'Obra Miraflores')
            ->assertOk()->assertJson(['ok' => true, 'movimientos' => 2]);

        $this->assertSame(7, $this->stock($this->v1));
        $this->assertSame(0, $this->stock($this->v2));
        $this->assertSame(7, $this->v1->fresh()->stock_actual);
        $this->assertEquals(7, $this->producto->fresh()->stock_actual);

        $m = MovimientoInventario::where('tipo_movimiento', 'salida')->where('variante_id', $this->v1->id)->first();
        $this->assertSame(3, (int) $m->cantidad);
        $this->assertSame(10, (int) $m->stock_anterior);
        $this->assertSame(7, (int) $m->stock_nuevo);
        $this->assertSame($this->motivo->id, (int) $m->motivo_movimiento_id);
        $this->assertSame('Obra Miraflores', $m->observaciones);
        $this->assertSame($this->admin->id, (int) $m->user_id);
    }

    public function test_no_deja_sacar_mas_de_lo_que_hay_y_no_aplica_nada_a_medias(): void
    {
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 4], ['variante_id' => $this->v2->id, 'cantidad' => 9]])
            ->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(10, $this->stock($this->v1), 'la primera línea tampoco se descuenta');
        $this->assertSame(3, $this->stock($this->v2));
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'salida')->count());
    }

    public function test_no_toca_el_stock_de_otro_almacen(): void
    {
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 1]], $this->otro->id)->assertStatus(422);
        $this->assertSame(10, $this->stock($this->v1));
    }

    public function test_el_motivo_debe_ser_de_salida(): void
    {
        $ingreso = MotivoMovimiento::create(['nombre' => 'Compra', 'codigo' => 'COMPRA', 'tipo' => 'ingreso', 'estado' => 'activo']);

        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 1]], null, $ingreso->id)->assertStatus(422);
        $this->assertSame(10, $this->stock($this->v1));
    }

    public function test_validaciones_basicas(): void
    {
        $this->postJson(route('inventario.salida.confirmar'), ['lineas' => []])->assertStatus(422)->assertJsonValidationErrors(['almacen_id', 'motivo_id', 'lineas']);
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 0]])->assertStatus(422)->assertJsonValidationErrors('lineas.0.cantidad');
    }

    public function test_entrada_y_salida_dejan_el_stock_cuadrado(): void
    {
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->v1->id, 'cantidad' => 5]], $this->admin->id);
        $this->retirar([['variante_id' => $this->v1->id, 'cantidad' => 12]])->assertOk();

        $this->assertSame(3, $this->stock($this->v1));
        $this->assertSame(3, $this->v1->fresh()->stock_actual);
    }

    public function test_la_ficha_del_producto_muestra_las_fotos_cargadas(): void
    {
        ProductoImagen::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'ruta' => 'productos/1/foto-a.png', 'orden' => 1, 'es_principal' => true]);
        ProductoImagen::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'ruta' => 'productos/1/foto-b.png', 'orden' => 1, 'es_principal' => false]);

        $this->get(route('inventario.productos.show', $this->producto))->assertOk()
            ->assertSee('foto-a.png', false)->assertSee('foto-b.png', false);
    }
}
