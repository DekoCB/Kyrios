<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\NotaPedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nota de pedido: de una cotización aceptada a la preparación en almacén. */
class NotaPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private Cotizacion $cotizacion;
    private Almacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p1 = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $p2 = Producto::create(['codigo' => 'KY-LU2501-0002', 'nombre' => 'Frame', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        StockAlmacen::obtenerOCrear($p1->id, $this->almacen->id)->update(['cantidad' => 5]);

        $cliente = Cliente::create(['nombre' => 'Cliente Uno', 'estado' => 'activo']);
        $this->cotizacion = Cotizacion::create([
            'codigo' => 'COT-0001', 'user_id' => $this->admin->id, 'cliente_id' => $cliente->id, 'almacen_id' => $this->almacen->id,
            'fecha' => today(), 'fecha_vencimiento' => today()->addDays(7), 'validez_dias' => 7, 'subtotal' => 100, 'igv' => 18, 'total' => 118, 'estado' => 'aceptada',
        ]);
        $this->cotizacion->detalles()->create(['producto_id' => $p1->id, 'descripcion' => 'Ring', 'cantidad' => 3, 'precio_unitario' => 10, 'precio_con_igv' => 11.8, 'subtotal' => 30, 'subtotal_con_igv' => 35.4]);
        $this->cotizacion->detalles()->create(['producto_id' => $p2->id, 'descripcion' => 'Frame', 'cantidad' => 2, 'precio_unitario' => 10, 'precio_con_igv' => 11.8, 'subtotal' => 20, 'subtotal_con_igv' => 23.6]);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    public function test_una_cotizacion_aceptada_genera_la_nota_y_avisa_a_almacen(): void
    {
        $this->actingAs($this->admin)
            ->post(route('cotizaciones.nota-pedido', $this->cotizacion), ['fecha_entrega' => today()->addDays(3)->toDateString(), 'observaciones' => 'Embalar con cuidado'])
            ->assertSessionHasNoErrors();

        $nota = NotaPedido::firstOrFail();
        $this->assertSame('NP-00001', $nota->codigo);
        $this->assertSame('pendiente', $nota->estado);
        $this->assertSame($this->almacen->id, $nota->almacen_id);
        $this->assertSame(2, $nota->detalles()->count());
        $this->assertEquals(5, $nota->detalles()->sum('cantidad'));
        $this->assertSame(1, $this->logistica->unreadNotifications()->count(), 'almacén recibe el aviso');

        // No se duplica mientras haya una activa
        $this->post(route('cotizaciones.nota-pedido', $this->cotizacion))->assertSessionHas('error');
        $this->assertSame(1, NotaPedido::count());
    }

    public function test_solo_una_cotizacion_aceptada_genera_nota(): void
    {
        $this->cotizacion->update(['estado' => 'pendiente']);
        $this->actingAs($this->admin)->post(route('cotizaciones.nota-pedido', $this->cotizacion))->assertSessionHas('error');
        $this->assertSame(0, NotaPedido::count());
    }

    public function test_almacen_prepara_las_lineas_y_la_nota_queda_lista_sola(): void
    {
        $this->actingAs($this->admin)->post(route('cotizaciones.nota-pedido', $this->cotizacion));
        $nota = NotaPedido::firstOrFail();
        [$l1, $l2] = $nota->detalles->all();

        $this->actingAs($this->logistica);
        $this->get(route('notas-pedido.show', $nota))->assertOk()->assertSee('Ring')->assertSee('Frame');
        $this->get(route('notas-pedido.pdf', $nota))->assertOk();

        $this->post(route('notas-pedido.preparar', $nota), ['preparado' => [$l1->id => 3, $l2->id => 0]])->assertSessionHas('success');
        $this->assertSame('en_preparacion', $nota->fresh()->estado);

        $this->post(route('notas-pedido.preparar', $nota), ['preparado' => [$l1->id => 3, $l2->id => 9]]);
        $this->assertSame(2, $l2->fresh()->preparado, 'no se puede preparar más de lo pedido');
        $this->assertSame('lista', $nota->fresh()->estado);
        $this->assertSame(1, $this->admin->unreadNotifications()->where('data->clave', "nota-pedido-lista:{$nota->id}")->count(), 'quien la generó recibe el aviso');

        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'entregada'])->assertSessionHas('success');
        $this->assertSame('entregada', $nota->fresh()->estado);
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'anulada'])->assertSessionHas('error');
    }

    public function test_roles_y_permisos(): void
    {
        $this->actingAs($this->admin)->post(route('cotizaciones.nota-pedido', $this->cotizacion));
        $nota = NotaPedido::firstOrFail();

        $this->actingAs($this->usuario('Contador'));
        $this->get(route('notas-pedido.index'))->assertOk();
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'en_preparacion'])->assertForbidden();

        $this->actingAs($this->usuario('Operaciones'));
        $this->get(route('notas-pedido.index'))->assertForbidden();
        $this->actingAs($this->usuario('Cliente'));
        $this->get(route('notas-pedido.show', $nota))->assertForbidden();

        // La cotización muestra el acceso a su nota
        $this->actingAs($this->admin)->get(route('cotizaciones.show', $this->cotizacion))->assertOk()->assertSee('NP-00001');
    }
}
