<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una línea que mezcla un producto con la variante de OTRO producto debe rechazarse en todos los flujos de stock,
 * sin mover nada: de lo contrario el stock queda en una variante que no es la del producto.
 */
class Auditoria2BVarianteAjenaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Producto $p1;
    private Producto $p2;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->p1 = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->p2 = Producto::create(['codigo' => 'KY-LU2001-0001', 'nombre' => 'Fust', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->p1->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->p2->id, 'sku' => 'KY-LU2001-0001', 'codigo_kyrios' => 'KY-LU2001-0001', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->a->id, [['variante_id' => $this->v1->id, 'cantidad' => 10], ['variante_id' => $this->v2->id, 'cantidad' => 10]], $this->admin->id);
    }

    private function sinCambios(string $flujo): void
    {
        $this->assertSame(10, StockAlmacen::cantidadEn($this->p1->id, $this->a->id, $this->v1->id), "$flujo: stock de V1");
        $this->assertSame(10, StockAlmacen::cantidadEn($this->p2->id, $this->a->id, $this->v2->id), "$flujo: stock de V2");
        $this->assertSame(0, (int) StockAlmacen::where('producto_id', $this->p1->id)->where('variante_id', $this->v2->id)->sum('cantidad'), "$flujo: stock fantasma (P1, V2)");
        $this->assertSame(0, (int) StockAlmacen::where('almacen_id', $this->b->id)->sum('cantidad'), "$flujo: algo llegó a la tienda");
    }

    public function test_traslado_con_variante_de_otro_producto(): void
    {
        $r = $this->postJson(route('traslados.store'), [
            'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id,
            'productos' => [['producto_id' => $this->p1->id, 'variante_id' => $this->v2->id, 'cantidad' => 3]],
        ]);
        $this->assertLessThan(500, $r->status());
        $this->sinCambios('traslado');
    }

    public function test_guia_con_variante_de_otro_producto(): void
    {
        $this->postJson(route('guias-remision.store'), [
            'tipo_destino' => 'almacen', 'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'motivo_traslado' => '04',
            'modalidad' => 'privado', 'fecha_traslado' => now()->toDateString(), 'direccion_partida' => 'AV X 1', 'ubigeo_partida' => '150101',
            'direccion_llegada' => 'AV Y 2', 'ubigeo_llegada' => '150101', 'peso_total' => 3, 'numero_bultos' => 1,
            'conductor_dni' => '41340629', 'conductor_nombre' => 'CESAR LEE', 'conductor_licencia' => 'Q41340629', 'placa_vehiculo' => 'ABC123',
            'productos' => [['producto_id' => $this->p1->id, 'variante_id' => $this->v2->id, 'cantidad' => 3]],
        ]);
        $this->sinCambios('guía');
    }

    public function test_movimiento_manual_con_variante_de_otro_producto(): void
    {
        $motivo = MotivoMovimiento::create(['nombre' => 'Prueba', 'codigo' => 'PRUEBA', 'tipo' => 'salida', 'estado' => 'activo']);
        $this->postJson(route('inventario.movimientos.store'), [
            'producto_id' => $this->p1->id, 'variante_id' => $this->v2->id, 'almacen_id' => $this->a->id,
            'tipo_movimiento' => 'salida', 'cantidad' => 3, 'motivo_movimiento_id' => $motivo->id,
        ]);
        $this->sinCambios('movimiento manual');
    }

    public function test_compra_con_variante_de_otro_producto(): void
    {
        $prov = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Prov SAC', 'estado' => 'activo']);
        $this->postJson(route('compras.store'), [
            'proveedor_id' => $prov->id, 'almacen_id' => $this->b->id, 'numero_factura' => 'F001-77', 'fecha' => now()->toDateString(),
            'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_operacion' => '01', 'tipo_compra' => 'local',
            'detalles' => [['producto_id' => $this->p1->id, 'variante_id' => $this->v2->id, 'cantidad' => 3, 'precio_unitario' => 10]],
        ]);
        $this->assertSame(0, (int) StockAlmacen::where('almacen_id', $this->b->id)->sum('cantidad'), 'la compra no debe dejar stock con una variante ajena');
        $this->assertSame(0, (int) StockAlmacen::where('producto_id', $this->p1->id)->where('variante_id', $this->v2->id)->sum('cantidad'));
    }

    public function test_pedido_con_variante_de_otro_producto(): void
    {
        $prov = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Prov SAC', 'estado' => 'activo']);
        $this->postJson(route('pedidos.store'), [
            'proveedor_id' => $prov->id, 'almacen_id' => $this->a->id, 'fecha' => now()->toDateString(),
            'detalles' => [['producto_id' => $this->p1->id, 'variante_id' => $this->v2->id, 'cantidad' => 2]],
        ])->assertStatus(422);
        $this->assertSame(0, \App\Models\Pedido::count());
    }
}
