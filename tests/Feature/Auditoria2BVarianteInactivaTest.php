<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\EscanerService;
use App\Services\VarianteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El stock del producto es la suma de stock_almacen aunque alguna de sus variantes esté desactivada
 * (las unidades siguen existiendo físicamente).
 */
class Auditoria2BVarianteInactivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_desactivar_una_variante_con_stock_no_desfasa_el_total_del_producto(): void
    {
        $admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->actingAs($admin);
        $a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $v1 = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        $v2 = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($a->id, [['variante_id' => $v1->id, 'cantidad' => 10], ['variante_id' => $v2->id, 'cantidad' => 4]], $admin->id);

        app(VarianteService::class)->desactivarVariante($v2->fresh());

        $this->assertSame(14, (int) $p->fresh()->stock_actual, 'las 4 unidades de la variante desactivada siguen en el almacén');

        // Seguir moviendo la variante activa no cambia ese criterio
        $motivo = MotivoMovimiento::create(['nombre' => 'Prueba', 'codigo' => 'PRUEBA', 'tipo' => 'salida', 'estado' => 'activo']);
        app(EscanerService::class)->retirar($a->id, [['variante_id' => $v1->id, 'cantidad' => 3]], $admin->id, $motivo->id);
        $v1->fresh()->decrementarStock(0);

        $this->assertSame((int) StockAlmacen::where('producto_id', $p->id)->sum('cantidad'), (int) $p->fresh()->stock_actual);
        $this->assertSame(11, (int) $p->fresh()->stock_actual);
    }
}
