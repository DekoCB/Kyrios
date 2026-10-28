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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El POS no debe hacer una consulta por producto al abrirse.
 */
class Auditoria2AVentasRendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_con_muchos_productos_hace_pocas_consultas(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));
        Almacen::create(['codigo' => 'ALM-T', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-P', 'nombre' => 'Luminaria', 'estado' => 'activo']);

        for ($i = 1; $i <= 100; $i++) {
            $p = Producto::create(['codigo' => sprintf('KY-LU25%02d-%04d', $i % 90, $i), 'nombre' => "Modelo {$i}", 'categoria_id' => $cat->id,
                'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 10, 'estado' => 'activo']);
            ProductoVariante::create(['producto_id' => $p->id, 'sku' => sprintf('KY-LU25%02d-%04d', $i % 90, $i), 'codigo_kyrios' => sprintf('KY-LU25%02d-%04d', $i % 90, $i), 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
            // La mitad sin precio cargado: es el caso que antes disparaba una consulta por producto
            if ($i % 2 === 0) {
                ProductoPrecio::create(['producto_id' => $p->id, 'tipo_precio' => 'venta_regular', 'precio' => 100 + $i, 'incluye_igv' => true, 'activo' => true]);
            }
        }

        DB::enableQueryLog();
        $this->get(route('ventas.create'))->assertOk();
        $consultas = count(DB::getQueryLog());

        $this->assertLessThan(60, $consultas, "El POS hizo {$consultas} consultas");
    }

    public function test_el_precio_de_respaldo_sigue_siendo_el_de_mayor_prioridad_vigente(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));
        $a = Almacen::create(['codigo' => 'ALM-T', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-P', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 10, 'estado' => 'activo']);
        // Solo hay precios por almacén (no entran en la carga normal) y uno vencido
        ProductoPrecio::create(['producto_id' => $p->id, 'almacen_id' => $a->id, 'tipo_precio' => 'venta_regular', 'precio' => 80, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);
        ProductoPrecio::create(['producto_id' => $p->id, 'almacen_id' => $a->id, 'tipo_precio' => 'venta_regular', 'precio' => 95, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 5]);
        ProductoPrecio::create(['producto_id' => $p->id, 'almacen_id' => $a->id, 'tipo_precio' => 'venta_regular', 'precio' => 999, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 9, 'fecha_fin' => now()->subDay()]);

        $this->assertSame(95.0, $p->precio_venta, 'referencia: el accesor del modelo');
        $html = $this->get(route('ventas.create'))->assertOk()->getContent();
        $this->assertStringContainsString('"precio_venta":95', str_replace(' ', '', $html));
    }
}
