<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CompraService;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quitar una línea de una compra revierte su stock: no puede dejar el almacén en negativo
 * cuando parte de esa mercadería ya salió.
 */
class Auditoria2BCompraEliminarDetalleTest extends TestCase
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
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-X', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002', 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
    }

    private function comprar(array $lineas)
    {
        $prov = Proveedor::firstOrCreate(['ruc' => '20555666771'], ['razon_social' => 'Proveedor SAC', 'estado' => 'activo']);

        return app(CompraService::class)->registrarCompra([
            'codigo' => 'C-' . uniqid(), 'proveedor_id' => $prov->id, 'almacen_id' => $this->almacen->id, 'user_id' => $this->admin->id,
            'numero_factura' => 'F001-' . uniqid(), 'fecha' => now()->toDateString(), 'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_cambio' => 1,
            'tipo_compra' => 'local', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'total_pen' => 0, 'estado' => 'registrado',
        ], $lineas);
    }

    private function sacar(ProductoVariante $v, int $cantidad): void
    {
        $motivo = MotivoMovimiento::firstOrCreate(['codigo' => 'PRUEBA'], ['nombre' => 'Prueba', 'tipo' => 'salida', 'estado' => 'activo']);
        app(EscanerService::class)->retirar($this->almacen->id, [['variante_id' => $v->id, 'cantidad' => $cantidad]], $this->admin->id, $motivo->id);
    }

    private function enAlmacen(ProductoVariante $v): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $v->id);
    }

    public function test_no_se_quita_una_linea_si_su_mercaderia_ya_salio(): void
    {
        $compra = $this->comprar([
            ['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 10, 'precio_unitario' => 10, 'descuento' => 0],
            ['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 5, 'precio_unitario' => 10, 'descuento' => 0],
        ]);
        $this->sacar($this->v1, 8);
        $this->assertSame(2, $this->enAlmacen($this->v1));

        $detalle = DetalleCompra::where('compra_id', $compra->id)->where('variante_id', $this->v1->id)->first();

        $mensaje = null;
        try {
            app(CompraService::class)->eliminarDetalle($compra->fresh(), $detalle);
        } catch (\Exception $e) {
            $mensaje = $e->getMessage();
        }
        $this->assertNotNull($mensaje, 'Debía rechazar quitar una línea cuya mercadería ya salió');
        $this->assertStringContainsString('ya salió', $mensaje);

        $this->assertSame(2, $this->enAlmacen($this->v1), 'el stock no cambia');
        $this->assertSame(2, (int) $this->v1->fresh()->stock_actual);
        $this->assertNotNull(DetalleCompra::find($detalle->id), 'la línea sigue en la compra');
        $this->assertSame(0, (int) StockAlmacen::where('cantidad', '<', 0)->count());
    }

    public function test_quitar_una_linea_sin_salidas_revierte_su_stock(): void
    {
        $compra = $this->comprar([
            ['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 10, 'precio_unitario' => 10, 'descuento' => 0],
            ['producto_id' => $this->producto->id, 'variante_id' => $this->v2->id, 'cantidad' => 5, 'precio_unitario' => 10, 'descuento' => 0],
        ]);
        $detalle = DetalleCompra::where('compra_id', $compra->id)->where('variante_id', $this->v2->id)->first();

        app(CompraService::class)->eliminarDetalle($compra->fresh(), $detalle);

        $this->assertSame(0, $this->enAlmacen($this->v2));
        $this->assertSame(10, $this->enAlmacen($this->v1));
        $this->assertSame(10, (int) $this->producto->fresh()->stock_actual);
    }
}
