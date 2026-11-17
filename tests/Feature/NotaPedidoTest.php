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

        // Entregar exige que la venta ya haya descontado el stock (la nota solo lo aparta)
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'entregada'])->assertSessionHas('error');
        $this->cotizacion->update(['estado' => 'convertida']);
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

    public function test_la_nota_guarda_los_datos_del_formato_comercial_y_el_pdf_no_lleva_precios(): void
    {
        $this->cotizacion->cliente->update(['direccion' => 'Av. Arequipa 123', 'contacto' => 'Ing. Carlos', 'telefono' => '999111222', 'email' => 'carlos@correo.com', 'numero_documento' => '20123456789']);
        $this->cotizacion->update(['referencia' => 'VIVIENDA - Prueba']);

        $this->actingAs($this->admin)->post(route('cotizaciones.nota-pedido', $this->cotizacion), [
            'moneda' => 'PEN', 'forma_pago' => 'Transferencia', 'condicion' => 'Contado', 'tipo_comprobante' => 'Factura', 'transporte' => 'Recoge en tienda',
        ])->assertSessionHasNoErrors();

        $nota = NotaPedido::firstOrFail();
        $this->assertSame('PEN', $nota->moneda);
        $this->assertSame('Transferencia', $nota->forma_pago);
        $this->assertSame('Factura', $nota->tipo_comprobante);
        $this->assertSame('Av. Arequipa 123', $nota->lugar_entrega, 'sin lugar de entrega usa la dirección del cliente');
        $this->assertSame('VIVIENDA - Prueba', $nota->referencia, 'sin referencia usa la de la cotización');
        $this->assertSame('Ing. Carlos', $nota->contacto);

        $detalle = $nota->detalles()->first();
        $this->assertSame('KY-LU2501-0001', $detalle->codigo);
        $this->assertSame('UND', $detalle->unidad);

        $r = $this->get(route('notas-pedido.pdf', $nota))->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->get(route('notas-pedido.show', $nota))->assertOk()->assertSee('Transferencia')->assertSee('Recoge en tienda');

        // El formato no lleva precio ni importe
        $html = view('pdf.nota-pedido', ['nota' => $nota->load(['cliente', 'almacen', 'cotizacion.vendedor', 'creador', 'detalles.producto.unidadMedida', 'detalles.variante']), 'empresa' => new \App\Models\Empresa(['razon_social' => 'KYRIOS'])])->render();
        $this->assertStringContainsString('FORMATO COMERCIAL / REGISTRO DE PEDIDOS', $html);
        $this->assertStringContainsString('DETALLE DE PRODUCTOS / SERVICIOS', $html);
        $this->assertStringNotContainsStringIgnoringCase('P. UNITARIO', $html);
        $this->assertStringNotContainsStringIgnoringCase('IMPORTE', $html);
        $this->assertStringNotContainsString('precio', mb_strtolower($html));
    }
}
