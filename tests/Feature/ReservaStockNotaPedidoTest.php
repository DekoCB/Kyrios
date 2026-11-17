<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\NotaPedido;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CotizacionService;
use App\Services\EscanerService;
use App\Services\NotaPedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La nota de pedido APARTA stock (no lo descuenta). La venta descuenta una sola vez y libera la reserva,
 * así que no hay descuento doble.
 */
class ReservaStockNotaPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $v1;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->v1->id, 'cantidad' => 10]], $this->admin->id);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Pérez', 'estado' => 'activo']);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
    }

    private function cotizacion(int $cantidad): Cotizacion
    {
        return app(CotizacionService::class)->crear(
            ['user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id],
            [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => $cantidad, 'precio_unitario' => 118, 'incluye_igv' => true]]
        );
    }

    /** Cotización aceptada con su nota de pedido (aparta el stock). */
    private function conNota(int $cantidad): array
    {
        $c = $this->cotizacion($cantidad);
        app(CotizacionService::class)->cambiarEstado($c, 'aceptada');
        $nota = app(NotaPedidoService::class)->crearDesdeCotizacion($c, $this->admin);

        return [$c, $nota];
    }

    private function convertir(Cotizacion $c)
    {
        return $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo']);
    }

    private function fisico(): int
    {
        return StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v1->id);
    }

    private function disponible(): int
    {
        return StockAlmacen::disponibleEn($this->producto->id, $this->almacen->id, $this->v1->id);
    }

    public function test_la_nota_aparta_el_stock_sin_descontarlo(): void
    {
        $this->conNota(3);

        $this->assertSame(10, $this->fisico(), 'el stock físico no se toca');
        $this->assertSame(3, StockAlmacen::reservadoEn($this->producto->id, $this->almacen->id, $this->v1->id));
        $this->assertSame(7, $this->disponible());
        $this->assertSame(7, StockAlmacen::disponibleTotalEn($this->producto->id, $this->almacen->id));
    }

    public function test_lo_apartado_no_se_puede_vender_a_otro(): void
    {
        $this->conNota(3);

        $otra = $this->cotizacion(8);
        $this->convertir($otra)->assertSessionHas('error');
        $this->assertStringContainsString('apartado', session('error'));
        $this->assertSame(10, $this->fisico());

        $cabe = $this->cotizacion(7);
        $this->convertir($cabe)->assertSessionHasNoErrors();
        $this->assertSame(3, $this->fisico());
    }

    public function test_convertir_la_cotizacion_de_la_nota_descuenta_una_sola_vez(): void
    {
        [$c, $nota] = $this->conNota(3);

        $this->convertir($c)->assertSessionHasNoErrors();

        $this->assertSame(7, $this->fisico(), 'descuenta 3 una sola vez, no 6');
        $this->assertSame(0, StockAlmacen::reservadoEn($this->producto->id, $this->almacen->id, $this->v1->id), 'la reserva se libera sola');
        $this->assertSame(7, $this->disponible());

        $nota = $nota->fresh();
        $this->assertSame('pendiente', $nota->estado, 'la nota sigue abierta para preparar y entregar');
        $this->assertFalse($nota->reserva_stock);
        $this->get(route('notas-pedido.show', $nota))->assertOk()->assertSee('Stock ya descontado');
    }

    public function test_con_la_nota_abierta_se_ve_que_aparta_stock(): void
    {
        [, $nota] = $this->conNota(3);

        $this->get(route('notas-pedido.show', $nota))->assertOk()->assertSee('Stock apartado')->assertSee('no descuenta');
    }

    public function test_anular_la_nota_libera_lo_apartado(): void
    {
        [, $nota] = $this->conNota(3);
        $this->assertSame(7, $this->disponible());

        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'anulada'])->assertSessionHas('success');

        $this->assertSame(10, $this->disponible());
    }

    public function test_no_se_marca_entregada_sin_haber_convertido_la_cotizacion(): void
    {
        [$c, $nota] = $this->conNota(3);
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'en_preparacion']);
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'lista']);
        $this->assertSame('lista', $nota->fresh()->estado);

        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'entregada'])->assertSessionHas('error');
        $this->assertSame('lista', $nota->fresh()->estado);
        $this->assertSame(7, $this->disponible(), 'sigue apartado');

        $this->convertir($c)->assertSessionHasNoErrors();
        $this->post(route('notas-pedido.estado', $nota), ['estado' => 'entregada'])->assertSessionHas('success');
        $this->assertSame('entregada', $nota->fresh()->estado);
        $this->assertSame(7, $this->fisico(), 'entregar no vuelve a descontar');
    }

    public function test_el_buscador_de_la_cotizacion_muestra_lo_disponible(): void
    {
        $this->conNota(3);

        $r = $this->getJson(route('cotizaciones.productos', ['q' => 'KY-LU2501', 'almacen_id' => $this->almacen->id]))->assertOk()->json();
        $this->assertSame(7, $r[0]['stock']);

        $otra = $this->cotizacion(5);
        $this->get(route('cotizaciones.show', $otra))->assertOk();
    }
}
