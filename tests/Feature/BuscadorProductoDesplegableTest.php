<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Los buscadores de productos tienen lista desplegable alimentada por «sugerencias». */
class BuscadorProductoDesplegableTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function productos(): void
    {
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        foreach ([['Ring', '0001'], ['Frame', '0002']] as [$nombre, $n]) {
            Producto::create(['codigo' => "KY-LU2501-{$n}", 'nombre' => $nombre, 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        }
    }

    public function test_sin_texto_lista_los_productos_por_orden_alfabetico_y_con_texto_filtra(): void
    {
        $this->productos();
        $this->actingAs($this->usuario('Logística'));

        $todos = $this->getJson(route('inventario.productos.sugerencias'))->assertOk()->json();
        $this->assertSame(['Frame', 'Ring'], array_map(fn ($p) => explode(' (', $p['nombre'])[0], $todos));

        $uno = $this->getJson(route('inventario.productos.sugerencias', ['q' => 'ring']))->assertOk()->json();
        $this->assertCount(1, $uno);
        $this->assertSame('KY-LU2501-0001', $uno[0]['codigo']);
    }

    public function test_el_cliente_no_accede_a_las_sugerencias(): void
    {
        $this->actingAs($this->usuario('Cliente'));
        $this->getJson(route('inventario.productos.sugerencias'))->assertForbidden();
    }

    public function test_las_pantallas_con_buscador_muestran_el_desplegable(): void
    {
        $this->productos();
        $this->actingAs($this->usuario('Administración'));

        $this->get(route('inventario.productos.consulta-tienda'))->assertOk()->assertSee('data-lista-productos', false);
        $this->get(route('inventario.productos.index'))->assertOk()->assertSee('data-lista-productos', false);
    }
}
