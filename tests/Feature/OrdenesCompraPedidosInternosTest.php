<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Aprobacion;
use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PedidoInterno;
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
 * Orden de compra (nacional / internacional) y nota de pedido interno entre almacenes.
 */
class OrdenesCompraPedidosInternosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private User $operaciones;
    private Almacen $deposito;
    private Almacen $tienda;
    private Producto $producto;
    private ProductoVariante $variante;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->operaciones = $this->usuario('Operaciones');
        $this->deposito = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->tienda = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Showroom', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        $this->proveedor = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Prov SAC', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function stock(int $n): void
    {
        app(EscanerService::class)->recibir($this->deposito->id, [['variante_id' => $this->variante->id, 'cantidad' => $n]], $this->admin->id);
    }

    private function orden(array $extra = [])
    {
        return $this->post(route('pedidos.store'), array_merge([
            'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->deposito->id, 'fecha' => now()->toDateString(),
            'detalles' => [
                ['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 10, 'precio_referencial' => 25.50],
                ['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_referencial' => 100],
            ],
        ], $extra));
    }

    private function pedidoInterno(array $extra = [])
    {
        return $this->post(route('pedidos-internos.store'), array_merge([
            'almacen_origen_id' => $this->deposito->id, 'almacen_destino_id' => $this->tienda->id, 'area' => 'Showroom',
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 4]],
        ], $extra));
    }

    // ── Orden de compra ──────────────────────────────────────────────────────

    public function test_una_orden_nacional_suma_igv_y_guarda_los_totales(): void
    {
        $this->actingAs($this->logistica);

        $this->orden(['tipo_compra' => 'nacional', 'moneda' => 'PEN', 'condicion_pago' => '30 días', 'lugar_entrega' => 'Lima'])->assertSessionHasNoErrors();

        $o = Pedido::firstOrFail();
        $this->assertMatchesRegularExpression('/^OC-\d{5}$/', $o->codigo);
        $this->assertSame('455.00', $o->subtotal);   // 10×25.50 + 2×100
        $this->assertSame('81.90', $o->igv);          // 18 %
        $this->assertSame('536.90', $o->total);
        $this->assertSame('30 días', $o->condicion_pago);
        $this->get(route('pedidos.show', $o))->assertOk()->assertSee('Nacional')->assertSee('IGV (18%)')->assertSee('S/ 536.90');
    }

    public function test_una_orden_internacional_no_lleva_igv_y_admite_incoterm(): void
    {
        $this->actingAs($this->admin);

        $this->orden(['tipo_compra' => 'internacional', 'moneda' => 'USD', 'incoterm' => 'FOB'])->assertSessionHasNoErrors();

        $o = Pedido::firstOrFail();
        $this->assertSame('0.00', $o->igv);
        $this->assertSame('455.00', $o->total);
        $this->assertSame('FOB', $o->incoterm);
        $this->get(route('pedidos.show', $o))->assertOk()->assertSee('Internacional')->assertSee('US$ 455.00')->assertDontSee('IGV (18%)');
    }

    public function test_el_incoterm_solo_se_guarda_en_ordenes_internacionales_y_debe_ser_valido(): void
    {
        $this->actingAs($this->admin);

        $this->orden(['tipo_compra' => 'nacional', 'incoterm' => 'FOB'])->assertSessionHasNoErrors();
        $this->assertNull(Pedido::first()->incoterm);

        $this->orden(['tipo_compra' => 'internacional', 'moneda' => 'USD', 'incoterm' => 'XYZ'])->assertSessionHasErrors('incoterm');
    }

    public function test_una_orden_nacional_en_dolares_exige_tipo_de_cambio(): void
    {
        $this->actingAs($this->admin);

        $this->orden(['tipo_compra' => 'nacional', 'moneda' => 'USD'])->assertSessionHasErrors('tipo_cambio');
        $this->assertSame(0, Pedido::count());

        $this->orden(['tipo_compra' => 'nacional', 'moneda' => 'USD', 'tipo_cambio' => '3.750'])->assertSessionHasNoErrors();
        $this->assertSame('3.750', Pedido::first()->tipo_cambio);
    }

    public function test_sin_indicar_tipo_la_orden_es_nacional_en_soles(): void
    {
        $this->actingAs($this->admin);

        $this->orden()->assertSessionHasNoErrors();

        $this->assertSame('nacional', Pedido::first()->tipo_compra);
        $this->assertSame('PEN', Pedido::first()->moneda);
    }

    public function test_el_listado_filtra_por_tipo(): void
    {
        $this->actingAs($this->admin);
        $this->orden(['tipo_compra' => 'nacional']);
        $this->orden(['tipo_compra' => 'internacional', 'moneda' => 'USD']);

        $this->get(route('pedidos.index', ['tipo_compra' => 'internacional']))->assertOk()
            ->assertViewHas('pedidos', fn ($p) => $p->total() === 1 && $p->first()->tipo_compra === 'internacional');
        $this->get(route('pedidos.index'))->assertOk()->assertSee('Órdenes de compra');
    }

    public function test_la_orden_se_imprime_en_pdf(): void
    {
        $this->actingAs($this->admin);
        $this->orden(['tipo_compra' => 'internacional', 'moneda' => 'USD', 'incoterm' => 'CIF']);

        $r = $this->get(route('pedidos.pdf', Pedido::first()));

        $r->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_recibir_una_orden_internacional_precarga_la_compra_como_importacion(): void
    {
        $this->actingAs($this->admin);
        $this->orden(['tipo_compra' => 'internacional', 'moneda' => 'USD', 'tipo_cambio' => '3.800']);
        $o = Pedido::first();
        $o->update(['estado' => 'aprobado']);

        $html = $this->get(route('compras.create', ['pedido_id' => $o->id]))->assertOk()->getContent();

        $this->assertStringContainsString('"tipo_compra":"importacion"', $html);
        $this->assertStringContainsString('"moneda":"USD"', $html);
        $this->assertStringContainsString('3.800', $html);
    }

    public function test_el_contador_y_el_cliente_no_acceden_a_las_ordenes(): void
    {
        $this->actingAs($this->usuario('Cliente'))->get(route('pedidos.index'))->assertForbidden();
        $this->actingAs($this->usuario('Operaciones'))->get(route('pedidos.index'))->assertForbidden();
    }

    // ── Pedido interno: crear ────────────────────────────────────────────────

    public function test_operaciones_registra_un_pedido_y_avisa_a_administracion_y_logistica(): void
    {
        $this->actingAs($this->operaciones);

        $this->pedidoInterno()->assertSessionHasNoErrors();

        $p = PedidoInterno::firstOrFail();
        $this->assertMatchesRegularExpression('/^PI-\d{5}$/', $p->codigo);
        $this->assertSame('pendiente', $p->estado);
        $this->assertSame($this->operaciones->id, $p->solicitante_id);
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
        $this->assertSame(0, $this->operaciones->unreadNotifications()->count());
        $this->assertSame("/pedidos-internos/{$p->id}", $this->admin->unreadNotifications()->first()->data['url']);
    }

    public function test_las_lineas_repetidas_se_suman(): void
    {
        $this->actingAs($this->operaciones);

        $this->pedidoInterno(['detalles' => [
            ['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 3],
            ['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1, PedidoInterno::first()->detalles()->count());
        $this->assertSame(5, PedidoInterno::first()->detalles()->first()->cantidad);
    }

    public function test_validaciones_del_pedido_interno(): void
    {
        $this->actingAs($this->operaciones);

        $this->pedidoInterno(['almacen_destino_id' => $this->deposito->id])->assertSessionHasErrors('almacen_destino_id');
        $this->pedidoInterno(['fecha_requerida' => now()->subDay()->toDateString()])->assertSessionHasErrors('fecha_requerida');
        $this->pedidoInterno(['detalles' => [['producto_id' => $this->producto->id, 'cantidad' => 1]]])->assertSessionHasErrors('detalles.0.variante_id');
        $this->pedidoInterno(['detalles' => []])->assertSessionHasErrors('detalles');
        $this->assertSame(0, PedidoInterno::count());
    }

    public function test_el_cliente_no_accede_a_los_pedidos_internos(): void
    {
        $this->actingAs($this->usuario('Cliente'))->get(route('pedidos-internos.index'))->assertForbidden();
        $this->actingAs($this->usuario('Contador'))->get(route('pedidos-internos.index'))->assertForbidden();
    }

    // ── Pedido interno: ver ──────────────────────────────────────────────────

    public function test_operaciones_solo_ve_sus_pedidos_y_logistica_ve_todos(): void
    {
        $otro = $this->usuario('Operaciones');
        $this->actingAs($this->operaciones)->pedidoInterno(['area' => 'Mi obra']);
        $this->actingAs($otro)->pedidoInterno(['area' => 'Obra ajena']);
        $ajeno = PedidoInterno::where('area', 'Obra ajena')->first();

        $this->actingAs($this->operaciones)->get(route('pedidos-internos.index'))->assertOk()->assertSee('Mi obra')->assertDontSee('Obra ajena');
        $this->get(route('pedidos-internos.show', $ajeno))->assertForbidden();
        $this->get(route('pedidos-internos.pdf', $ajeno))->assertForbidden();

        $this->actingAs($this->logistica)->get(route('pedidos-internos.index'))->assertOk()->assertSee('Mi obra')->assertSee('Obra ajena');
    }

    public function test_el_detalle_muestra_el_stock_del_almacen_que_atiende_solo_a_quien_atiende(): void
    {
        $this->stock(3);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->logistica)->get(route('pedidos-internos.show', $p))->assertOk()->assertSee('Stock en Depósito')->assertSee('no alcanza el stock');
        $this->actingAs($this->operaciones)->get(route('pedidos-internos.show', $p))->assertOk()->assertDontSee('Stock en Depósito')->assertDontSee('Atender pedido');
    }

    public function test_el_pedido_vencido_se_marca(): void
    {
        $this->actingAs($this->operaciones)->pedidoInterno(['fecha_requerida' => now()->addDay()->toDateString()]);
        PedidoInterno::first()->update(['fecha_requerida' => now()->subDays(2)->toDateString()]);

        $this->get(route('pedidos-internos.index'))->assertOk()->assertSee('(vencido)');
        $this->assertTrue(PedidoInterno::first()->vencido);
    }

    public function test_el_pedido_interno_se_imprime_en_pdf(): void
    {
        $this->actingAs($this->operaciones)->pedidoInterno();

        $r = $this->get(route('pedidos-internos.pdf', PedidoInterno::first()));

        $r->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    // ── Pedido interno: atender ──────────────────────────────────────────────

    public function test_administracion_atiende_el_pedido_y_se_crea_el_traslado(): void
    {
        $this->stock(10);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->admin)->post(route('pedidos-internos.atender', $p))->assertRedirect(route('pedidos-internos.show', $p))->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('atendido', $p->estado);
        $this->assertNotEmpty($p->traslado_guia);
        $this->assertSame($this->admin->id, $p->atendido_por);
        $this->assertSame(6, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
        $this->assertTrue(MovimientoInventario::where('numero_guia', $p->traslado_guia)->exists());
        $this->assertSame('Pedido interno atendido', $this->operaciones->unreadNotifications()->first()->data['titulo']);
    }

    public function test_si_no_hay_stock_el_pedido_sigue_pendiente(): void
    {
        $this->stock(1);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->admin)->post(route('pedidos-internos.atender', $p))->assertSessionHas('error');

        $this->assertSame('pendiente', $p->fresh()->estado);
        $this->assertSame(1, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'transferencia')->count());
    }

    public function test_un_pedido_no_se_atiende_dos_veces(): void
    {
        $this->stock(20);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->admin)->post(route('pedidos-internos.atender', $p));
        $this->post(route('pedidos-internos.atender', $p))->assertSessionHas('error');

        $this->assertSame(16, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id), 'solo salió un traslado de 4');
    }

    public function test_el_traslado_de_logistica_espera_la_aprobacion_del_administrador(): void
    {
        $this->stock(10);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->logistica)->post(route('pedidos-internos.atender', $p))->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame('pendiente', $p->fresh()->estado);
        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
        $a = Aprobacion::firstOrFail();
        $this->assertSame('Atender pedido interno ' . $p->codigo, $a->titulo);
        $this->assertContains('De Depósito a Showroom', $a->detalle);
        $this->assertContains('Ring (KY-LU2501-0001) × 4', $a->detalle);

        $this->actingAs($this->admin)->post(route('aprobaciones.aprobar', $a))->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('atendido', $p->estado);
        $this->assertSame($this->logistica->id, $p->atendido_por, 'queda a nombre de quien lo atendió');
        $this->assertSame(6, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
    }

    public function test_operaciones_no_puede_atender(): void
    {
        $this->actingAs($this->operaciones)->pedidoInterno();

        $this->post(route('pedidos-internos.atender', PedidoInterno::first()))->assertForbidden();
    }

    // ── Pedido interno: cancelar ─────────────────────────────────────────────

    public function test_el_solicitante_cancela_su_pedido_pendiente_pero_no_uno_atendido(): void
    {
        $this->stock(10);
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->post(route('pedidos-internos.cancelar', $p))->assertSessionHas('success');
        $this->assertSame('cancelado', $p->fresh()->estado);

        $this->actingAs($this->admin)->post(route('pedidos-internos.atender', $p))->assertSessionHas('error');
        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id), 'un pedido cancelado no mueve stock');
    }

    public function test_nadie_cancela_el_pedido_de_otro_sin_ser_logistica_o_administracion(): void
    {
        $this->actingAs($this->operaciones)->pedidoInterno();
        $p = PedidoInterno::first();

        $this->actingAs($this->usuario('Operaciones'))->post(route('pedidos-internos.cancelar', $p))->assertForbidden();
        $this->assertSame('pendiente', $p->fresh()->estado);

        $this->actingAs($this->logistica)->post(route('pedidos-internos.cancelar', $p))->assertSessionHas('success');
    }

    public function test_el_menu_ofrece_pedidos_internos_a_los_tres_roles(): void
    {
        foreach ([$this->admin, $this->logistica, $this->operaciones] as $u) {
            $this->actingAs($u)->get(route('pedidos-internos.index'))->assertOk()->assertSee('Pedidos internos');
        }
    }
}
