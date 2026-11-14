<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Aprobacion;
use App\Models\Categoria;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Movimientos de inventario con varios productos a la vez y lista desplegable. */
class MovimientosVariosProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private MotivoMovimiento $ingreso;
    private MotivoMovimiento $salida;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->ingreso = MotivoMovimiento::create(['nombre' => 'Compra', 'codigo' => 'COMPRA', 'tipo' => 'ingreso', 'estado' => 'activo']);
        $this->salida = MotivoMovimiento::create(['nombre' => 'Consumo', 'codigo' => 'CONSUMO', 'tipo' => 'salida', 'estado' => 'activo']);

        foreach (['v1' => ['Ring', '0001'], 'v2' => ['Frame', '0002']] as $prop => [$nombre, $n]) {
            $p = Producto::create(['codigo' => "KY-LU2501-{$n}", 'nombre' => $nombre, 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
            $this->{$prop} = ProductoVariante::create(['producto_id' => $p->id, 'sku' => "KY-{$n}", 'codigo_kyrios' => "KY-LU2501-{$n}", 'estado' => 'activo']);
        }
        $this->actingAs($this->admin);
    }

    private function stock(ProductoVariante $v): int
    {
        return StockAlmacen::cantidadEn($v->producto_id, $this->almacen->id, $v->id);
    }

    private function datos(array $lineas, string $tipo = 'ingreso'): array
    {
        return ['almacen_id' => $this->almacen->id, 'tipo_movimiento' => $tipo, 'motivo_movimiento_id' => ($tipo === 'ingreso' ? $this->ingreso : $this->salida)->id, 'lineas' => $lineas];
    }

    public function test_un_ingreso_con_varios_productos_los_registra_todos(): void
    {
        $this->post(route('inventario.movimientos.store'), $this->datos([
            ['producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'cantidad' => 5],
            ['producto_id' => $this->v2->producto_id, 'variante_id' => $this->v2->id, 'cantidad' => 3],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(5, $this->stock($this->v1));
        $this->assertSame(3, $this->stock($this->v2));
    }

    public function test_si_una_linea_falla_no_se_registra_ninguna(): void
    {
        $this->post(route('inventario.movimientos.store'), $this->datos([['producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'cantidad' => 4]]));
        $this->assertSame(4, $this->stock($this->v1));

        // La segunda línea pide más de lo que hay: la salida completa se revierte
        $r = $this->post(route('inventario.movimientos.store'), $this->datos([
            ['producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'cantidad' => 2],
            ['producto_id' => $this->v2->producto_id, 'variante_id' => $this->v2->id, 'cantidad' => 9],
        ], 'salida'));

        $r->assertSessionHas('error');
        $this->assertSame(4, $this->stock($this->v1), 'la primera línea no debe haberse descontado');
        $this->assertSame(0, $this->stock($this->v2));
    }

    public function test_valida_cada_linea(): void
    {
        $this->post(route('inventario.movimientos.store'), $this->datos([['producto_id' => $this->v1->producto_id, 'cantidad' => 0]]))->assertSessionHasErrors('lineas.0.cantidad');
        $this->post(route('inventario.movimientos.store'), $this->datos([['cantidad' => 2]]))->assertSessionHasErrors('lineas.0.producto_id');
        $this->post(route('inventario.movimientos.store'), $this->datos([]))->assertSessionHasErrors();
    }

    public function test_el_formato_de_un_solo_producto_sigue_funcionando(): void
    {
        $this->post(route('inventario.movimientos.store'), [
            'producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'almacen_id' => $this->almacen->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 7, 'motivo_movimiento_id' => $this->ingreso->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(7, $this->stock($this->v1));
    }

    public function test_un_usuario_de_logistica_deja_una_solicitud_con_todos_los_productos(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));
        $this->post(route('inventario.movimientos.store'), $this->datos([
            ['producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'cantidad' => 5],
            ['producto_id' => $this->v2->producto_id, 'variante_id' => $this->v2->id, 'cantidad' => 3],
        ]));
        $a = Aprobacion::firstOrFail();
        $this->assertSame(0, $this->stock($this->v1), 'nada se aplica hasta aprobar');
        $this->assertStringContainsString('2 productos', $a->titulo);

        $this->actingAs($this->admin)->post(route('aprobaciones.aprobar', $a))->assertSessionHas('success');
        $this->assertSame(5, $this->stock($this->v1));
        $this->assertSame(3, $this->stock($this->v2));
    }

    public function test_la_pantalla_ofrece_lista_desplegable_y_agregar_productos(): void
    {
        $this->get(route('inventario.movimientos.create'))->assertOk()
            ->assertSee('data-lista-productos', false)->assertSee('Agregar otro producto')->assertSee('lineas[', false);
    }
}
