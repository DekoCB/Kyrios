<?php

namespace Tests\Feature;

use App\Models\Catalogo\UnidadMedida;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pantallas que listan todo el catálogo no hacen una consulta por producto (antes: ~1.000 con 1.018 productos).
 */
class Auditoria2BRendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantallas_con_catalogo_grande_hacen_pocas_consultas(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $unidad = UnidadMedida::first() ?? UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'estado' => 'activo']);

        for ($i = 1; $i <= 100; $i++) {
            $p = Producto::create(['codigo' => 'KY-LU25' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-0001', 'nombre' => "Producto $i", 'categoria_id' => $cat->id,
                'unidad_medida_id' => $unidad->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo', 'stock_actual' => 2, 'ultimo_costo_compra' => 10]);
            foreach ([1, 2] as $n) {
                ProductoVariante::create(['producto_id' => $p->id, 'sku' => "SKU-$i-$n", 'codigo_kyrios' => "KY-$i-$n", 'acabado_1' => 'negro(a)', 'stock_actual' => 1, 'estado' => 'activo']);
            }
        }

        foreach (['inventario.reportes.abc', 'compras.create', 'pedidos.create', 'inventario.movimientos.create'] as $ruta) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route($ruta))->assertOk();
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThan(60, $consultas, "$ruta hizo $consultas consultas con 100 productos");
        }
    }
}
