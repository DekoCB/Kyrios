<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Services\CotizacionService;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eliminar un producto que otras tablas referencian (cotización, guía de remisión) lo desactiva con aviso: nunca da error 500.
 */
class Auditoria2BEliminarProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Producto $p;
    private ProductoVariante $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Administración', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->v = ProductoVariante::create(['producto_id' => $this->p->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
    }

    private function eliminar(): void
    {
        $this->delete(route('inventario.productos.destroy', $this->p))->assertRedirect()->assertSessionHas('info');
        $this->assertSame('inactivo', $this->p->fresh()->estado);
        $this->assertNotNull(Producto::find($this->p->id), 'el producto sigue existiendo');
    }

    public function test_producto_en_una_cotizacion(): void
    {
        $cli = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Cliente', 'estado' => 'activo']);
        app(CotizacionService::class)->crear([
            'user_id' => $this->admin->id, 'cliente_id' => $cli->id, 'almacen_id' => $this->a->id, 'condiciones' => 'x', 'validez_dias' => 7,
        ], [['producto_id' => $this->p->id, 'variante_id' => $this->v->id, 'cantidad' => 1, 'precio_unitario' => 50]]);

        $this->eliminar();
    }

    public function test_producto_en_una_guia_de_remision_sin_movimientos_propios(): void
    {
        app(EscanerService::class)->recibir($this->a->id, [['variante_id' => $this->v->id, 'cantidad' => 5]], $this->admin->id);
        $this->post(route('guias-remision.store'), [
            'tipo_destino' => 'almacen', 'almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'motivo_traslado' => '04',
            'modalidad' => 'privado', 'fecha_traslado' => now()->toDateString(), 'direccion_partida' => 'AV X 1', 'ubigeo_partida' => '150101',
            'direccion_llegada' => 'AV Y 2', 'ubigeo_llegada' => '150101', 'peso_total' => 3, 'numero_bultos' => 1,
            'conductor_dni' => '41340629', 'conductor_nombre' => 'CESAR LEE', 'conductor_licencia' => 'Q41340629', 'placa_vehiculo' => 'ABC123',
            'productos' => [['producto_id' => $this->p->id, 'variante_id' => $this->v->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();

        $this->eliminar();
    }

    public function test_producto_sin_ninguna_referencia_se_elimina_de_verdad(): void
    {
        $this->delete(route('inventario.productos.destroy', $this->p))->assertRedirect()->assertSessionHas('success');
        $this->assertNull(Producto::find($this->p->id));
    }
}
