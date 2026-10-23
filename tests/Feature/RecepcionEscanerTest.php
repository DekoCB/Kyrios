<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoCodigoBarras;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recepción con escáner (lector del PDA): buscar por código, asociar códigos de barras y registrar ingresos.
 */
class RecepcionEscanerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);

        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'codigo_fabricante' => '25-0573-N3-B8', 'codigo_excel' => 'KLU01-00003', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002',
            'codigo_fabricante' => '25-0573-N3-M1', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);

        $this->actingAs($this->admin);
    }

    private function buscar(string $codigo)
    {
        return $this->getJson(route('inventario.recepcion.buscar', ['codigo' => $codigo]));
    }

    // ── Acceso ────────────────────────────────────────────────────────────

    public function test_la_pantalla_carga_para_el_almacenero_y_el_administrador(): void
    {
        $this->get(route('inventario.recepcion.index'))->assertOk()->assertSee('Recepción con escáner');

        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Almacenero'])->id, 'estado' => 'activo']))
            ->get(route('inventario.recepcion.index'))->assertOk();
    }

    public function test_un_vendedor_no_tiene_acceso(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Vendedor'])->id, 'estado' => 'activo']));

        $this->get(route('inventario.recepcion.index'))->assertForbidden();
        $this->getJson(route('inventario.recepcion.buscar', ['codigo' => 'KY-LU2501-0001']))->assertForbidden();
        $this->postJson(route('inventario.recepcion.confirmar'), [])->assertForbidden();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        auth()->logout();

        $this->get(route('inventario.recepcion.index'))->assertRedirect(route('login'));
    }

    // ── Búsqueda ──────────────────────────────────────────────────────────

    public function test_busca_por_codigo_kyrios_del_fabricante_y_del_excel(): void
    {
        foreach (['KY-LU2501-0001', '25-0573-N3-B8', 'KLU01-00003', 'ky-lu2501-0001'] as $codigo) {
            $this->buscar($codigo)->assertOk()->assertJsonCount(1, 'resultados')
                ->assertJsonPath('resultados.0.id', $this->v1->id);
        }
    }

    public function test_ignora_los_saltos_de_linea_que_agrega_el_lector(): void
    {
        $this->buscar("  KY-LU2501-0002\r\n")->assertJsonPath('resultados.0.id', $this->v2->id)->assertJsonPath('codigo', 'KY-LU2501-0002');
    }

    public function test_un_codigo_desconocido_no_devuelve_resultados(): void
    {
        $this->buscar('7501234567890')->assertOk()->assertJsonCount(0, 'resultados');
        $this->buscar('')->assertOk()->assertJsonCount(0, 'resultados');
    }

    public function test_un_codigo_de_barras_de_producto_devuelve_todas_sus_variantes(): void
    {
        ProductoCodigoBarras::create(['producto_id' => $this->producto->id, 'codigo_barras' => '7790000000001', 'es_principal' => true]);

        $this->buscar('7790000000001')->assertJsonCount(2, 'resultados');
    }

    public function test_las_variantes_inactivas_no_aparecen(): void
    {
        $this->v2->update(['estado' => 'inactivo']);

        $this->buscar('KY-LU2501-0002')->assertJsonCount(0, 'resultados');
    }

    public function test_la_busqueda_manual_necesita_al_menos_dos_letras(): void
    {
        $this->getJson(route('inventario.recepcion.variantes', ['q' => 'r']))->assertOk()->assertExactJson([]);
        $this->getJson(route('inventario.recepcion.variantes', ['q' => 'Ring']))->assertOk()->assertJsonCount(2);
    }

    // ── Asociar un código de barras ───────────────────────────────────────

    public function test_se_asocia_un_codigo_de_barras_y_despues_lo_encuentra(): void
    {
        $this->postJson(route('inventario.recepcion.asociar'), ['codigo' => '7501234567890', 'variante_id' => $this->v2->id])
            ->assertOk()->assertJson(['ok' => true]);

        $this->buscar('7501234567890')->assertJsonCount(1, 'resultados')->assertJsonPath('resultados.0.id', $this->v2->id);
        $this->assertDatabaseHas('productos_codigos_barras', ['codigo_barras' => '7501234567890', 'variante_id' => $this->v2->id, 'es_principal' => true]);
    }

    public function test_no_se_asocia_un_codigo_que_ya_esta_en_uso(): void
    {
        $this->postJson(route('inventario.recepcion.asociar'), ['codigo' => '7501234567890', 'variante_id' => $this->v1->id])->assertOk();

        $this->postJson(route('inventario.recepcion.asociar'), ['codigo' => '7501234567890', 'variante_id' => $this->v2->id])
            ->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_asociar_valida_los_datos(): void
    {
        $this->postJson(route('inventario.recepcion.asociar'), ['codigo' => '', 'variante_id' => 999])->assertStatus(422)->assertJsonValidationErrors(['codigo', 'variante_id']);
    }

    // ── Confirmar el ingreso ──────────────────────────────────────────────

    public function test_confirmar_suma_el_stock_de_cada_variante_y_junta_lecturas_repetidas(): void
    {
        $this->postJson(route('inventario.recepcion.confirmar'), [
            'almacen_id' => $this->almacen->id, 'documento_referencia' => 'GR-001',
            'lineas' => [
                ['variante_id' => $this->v1->id, 'cantidad' => 3],
                ['variante_id' => $this->v1->id, 'cantidad' => 2],
                ['variante_id' => $this->v2->id, 'cantidad' => 4],
            ],
        ])->assertOk()->assertJson(['ok' => true, 'movimientos' => 2]);

        $this->assertSame(5, StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v1->id));
        $this->assertSame(4, StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v2->id));
        $this->assertSame(9, (int) $this->producto->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_confirmar_valida_el_almacen_las_lineas_y_las_cantidades(): void
    {
        $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => 999, 'lineas' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['almacen_id', 'lineas']);

        $this->postJson(route('inventario.recepcion.confirmar'), ['almacen_id' => $this->almacen->id, 'lineas' => [['variante_id' => $this->v1->id, 'cantidad' => 0]]])
            ->assertStatus(422)->assertJsonValidationErrors('lineas.0.cantidad');

        $this->assertDatabaseCount('stock_almacen', 0);
    }

}
