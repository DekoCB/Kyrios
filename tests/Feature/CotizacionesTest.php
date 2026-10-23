<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CotizacionService;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo de cotizaciones: alta, estados, validez, conversión a nota de venta / boleta / factura y PDF.
 */
class CotizacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $v1;
    private ProductoVariante $v2;
    private Cliente $dni;
    private Cliente $ruc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administrador');
        $this->actingAs($this->admin);

        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);

        $categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'tamano' => 'grande', 'estado' => 'activo']);
        $this->v2 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0002', 'codigo_kyrios' => 'KY-LU2501-0002',
            'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->almacen->id, [
            ['variante_id' => $this->v1->id, 'cantidad' => 10], ['variante_id' => $this->v2->id, 'cantidad' => 4],
        ], $this->admin->id);

        $this->dni = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Pérez', 'estado' => 'activo']);
        $this->ruc = Cliente::create(['tipo_documento' => 'RUC', 'numero_documento' => '20123456789', 'nombre' => 'ACME S.A.C.', 'estado' => 'activo']);

        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(),
            'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function lineas(array $extra = []): array
    {
        return [array_merge(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true], $extra)];
    }

    private function crear(?Cliente $cliente = null, array $lineas = null, ?User $user = null): Cotizacion
    {
        return app(CotizacionService::class)->crear([
            'user_id' => ($user ?? $this->admin)->id, 'cliente_id' => ($cliente ?? $this->dni)->id, 'almacen_id' => $this->almacen->id,
            'condiciones' => 'Pago contado', 'validez_dias' => 7,
        ], $lineas ?? $this->lineas());
    }

    // ── Alta y cálculo ────────────────────────────────────────────────────

    public function test_se_crea_con_numero_de_serie_y_totales_con_igv_incluido(): void
    {
        $c = $this->crear();

        $this->assertSame('CT01-00000001', $c->codigo);
        $this->assertSame('pendiente', $c->estado);
        $this->assertEquals(200.00, (float) $c->subtotal);
        $this->assertEquals(36.00, (float) $c->igv);
        $this->assertEquals(236.00, (float) $c->total);
        $this->assertSame(today()->addDays(7)->toDateString(), $c->fecha_vencimiento->toDateString());

        $this->assertSame('CT01-00000002', $this->crear()->codigo, 'el correlativo avanza');
        $this->assertDatabaseHas('series_comprobantes', ['tipo_comprobante' => 'CT', 'serie' => 'CT01', 'correlativo_actual' => 3]);
    }

    public function test_el_detalle_guarda_codigo_y_descripcion_tal_como_se_cotizo(): void
    {
        $c = $this->crear();
        $d = $c->detalles->first();

        $this->assertSame('KY-LU2501-0001', $d->codigo);
        $this->assertSame('Ring — negro(a) / grande', $d->descripcion);
        $this->assertEquals(100.0, (float) $d->precio_unitario);
        $this->assertEquals(118.0, (float) $d->precio_con_igv);

        $this->producto->update(['nombre' => 'Otro nombre']);
        $this->assertSame('Ring — negro(a) / grande', $c->fresh()->detalles->first()->descripcion, 'cambiar el producto no altera lo cotizado');
    }

    public function test_un_precio_sin_igv_se_calcula_bien(): void
    {
        $c = $this->crear(null, $this->lineas(['precio_unitario' => 100, 'incluye_igv' => false, 'cantidad' => 1]));

        $this->assertEquals(100.00, (float) $c->subtotal);
        $this->assertEquals(118.00, (float) $c->total);
    }

    public function test_por_http_se_guarda_y_redirige_a_la_ficha(): void
    {
        $this->post(route('cotizaciones.store'), [
            'cliente_id' => $this->dni->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 15, 'condiciones' => 'Entrega en 3 días',
            'detalles' => $this->lineas(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $c = Cotizacion::first();
        $this->assertSame(15, $c->validez_dias);
        $this->assertSame($this->admin->id, $c->user_id);
        $this->get(route('cotizaciones.show', $c))->assertOk()->assertSee('CT01-00000001')->assertSee('Ring — negro(a) / grande');
    }

    public function test_validaciones_del_formulario(): void
    {
        $this->post(route('cotizaciones.store'), ['almacen_id' => $this->almacen->id, 'validez_dias' => 7])
            ->assertSessionHasErrors(['cliente_id', 'detalles']);

        $this->post(route('cotizaciones.store'), [
            'cliente_id' => $this->dni->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7,
            'detalles' => $this->lineas(['cantidad' => 0, 'precio_unitario' => 0]),
        ])->assertSessionHasErrors(['detalles.0.cantidad', 'detalles.0.precio_unitario']);

        $this->assertSame(0, Cotizacion::count());
    }

    public function test_un_producto_con_variantes_exige_elegir_la_variante(): void
    {
        $this->post(route('cotizaciones.store'), [
            'cliente_id' => $this->dni->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7,
            'detalles' => [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => 50]],
        ])->assertSessionHas('error');

        $this->assertSame(0, Cotizacion::count());
    }

    public function test_un_producto_por_serie_no_se_cotiza_aqui(): void
    {
        $serie = Producto::create(['codigo' => 'CEL-1', 'nombre' => 'Celular', 'categoria_id' => $this->producto->categoria_id,
            'tipo_inventario' => 'serie', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);

        $this->expectException(\DomainException::class);
        $this->crear(null, [['producto_id' => $serie->id, 'cantidad' => 1, 'precio_unitario' => 500]]);
    }

    public function test_una_variante_de_otro_producto_se_rechaza(): void
    {
        $otro = Producto::create(['codigo' => 'KY-X', 'nombre' => 'Otro', 'categoria_id' => $this->producto->categoria_id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);

        $this->expectException(\DomainException::class);
        $this->crear(null, [['producto_id' => $otro->id, 'variante_id' => $this->v1->id, 'cantidad' => 1, 'precio_unitario' => 50]]);
    }

    // ── Edición ───────────────────────────────────────────────────────────

    public function test_se_edita_una_pendiente_y_se_recalculan_los_totales_sin_cambiar_el_numero(): void
    {
        $c = $this->crear();

        $this->put(route('cotizaciones.update', $c), [
            'cliente_id' => $this->ruc->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 30, 'condiciones' => 'Nuevo texto',
            'detalles' => $this->lineas(['cantidad' => 5, 'variante_id' => $this->v2->id, 'precio_unitario' => 59]),
        ])->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame('CT01-00000001', $c->codigo);
        $this->assertSame($this->ruc->id, $c->cliente_id);
        $this->assertEquals(295.00, (float) $c->total);
        $this->assertSame(1, $c->detalles()->count(), 'las líneas anteriores se reemplazan');
        $this->assertSame('KY-LU2501-0002', $c->detalles->first()->codigo);
        $this->assertSame(30, $c->validez_dias);
    }

    public function test_no_se_edita_una_cotizacion_aceptada(): void
    {
        $c = $this->crear();
        app(CotizacionService::class)->cambiarEstado($c, 'aceptada');

        $this->get(route('cotizaciones.edit', $c))->assertRedirect(route('cotizaciones.show', $c));
        $this->put(route('cotizaciones.update', $c), [
            'cliente_id' => $this->dni->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7, 'detalles' => $this->lineas(['cantidad' => 9]),
        ])->assertSessionHas('error');

        $this->assertSame(2, $c->detalles()->first()->cantidad);
    }

    // ── Estados y validez ─────────────────────────────────────────────────

    public function test_aceptar_rechazar_reabrir_y_anular(): void
    {
        $c = $this->crear();

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'aceptada'])->assertSessionHasNoErrors();
        $this->assertSame('aceptada', $c->fresh()->estado);

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'rechazada'])->assertSessionHas('error'); // sin motivo
        $this->assertSame('aceptada', $c->fresh()->estado);

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'rechazada', 'motivo' => 'Precio alto'])->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $c->fresh()->estado);
        $this->assertSame('Precio alto', $c->fresh()->motivo_estado);

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'pendiente']);
        $this->assertSame('pendiente', $c->fresh()->estado);

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'anulada', 'motivo' => 'Error de carga']);
        $this->assertSame('anulada', $c->fresh()->estado);

        $this->post(route('cotizaciones.estado', $c), ['estado' => 'pendiente'])->assertSessionHas('error');
        $this->assertSame('anulada', $c->fresh()->estado, 'una anulada no vuelve');
    }

    public function test_una_cotizacion_vencida_no_se_convierte_hasta_renovarla(): void
    {
        $c = $this->crear();
        $c->update(['fecha' => today()->subDays(10), 'fecha_vencimiento' => today()->subDays(3)]);
        $c->refresh();

        $this->assertTrue($c->esta_vencida);
        $this->assertSame('vencida', $c->estado_visible);
        $this->assertFalse($c->puede_convertirse);

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])
            ->assertSessionHas('error');
        $this->assertSame(0, Venta::count());

        $this->post(route('cotizaciones.renovar', $c), ['validez_dias' => 10])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame(today()->addDays(10)->toDateString(), $c->fecha_vencimiento->toDateString());
        $this->assertTrue($c->puede_convertirse);
    }

    public function test_duplicar_crea_otra_pendiente_con_las_mismas_lineas(): void
    {
        $c = $this->crear();
        app(CotizacionService::class)->cambiarEstado($c, 'rechazada', 'no');

        $this->post(route('cotizaciones.duplicar', $c))->assertRedirect();

        $copia = Cotizacion::where('id', '!=', $c->id)->first();
        $this->assertSame('CT01-00000002', $copia->codigo);
        $this->assertSame('pendiente', $copia->estado);
        $this->assertEquals((float) $c->total, (float) $copia->total);
        $this->assertSame($c->detalles->pluck('codigo')->all(), $copia->detalles->pluck('codigo')->all());
    }

    // ── Conversión ────────────────────────────────────────────────────────

    public function test_convertir_en_nota_de_venta_descuenta_stock_y_no_va_a_sunat(): void
    {
        $c = $this->crear();

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])
            ->assertSessionHasNoErrors();

        $c->refresh();
        $venta = $c->venta;
        $this->assertSame('convertida', $c->estado);
        $this->assertSame('nota_venta', $c->tipo_comprobante_convertido);
        $this->assertNotNull($c->convertida_en);

        $this->assertSame('nota_venta', $venta->tipo_comprobante);
        $this->assertSame('no_aplica', $venta->estado_sunat);
        $this->assertSame('NV01-00000001', $venta->numero_documento);
        $this->assertSame('pagado', $venta->estado_pago);
        $this->assertEquals(236.00, (float) $venta->total);
        $this->assertSame('Cotización CT01-00000001', $venta->observaciones);
        $this->assertSame(8, StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v1->id), 'se descontaron las 2 unidades');
    }

    public function test_convertir_en_boleta_y_en_factura(): void
    {
        $boleta = $this->crear($this->dni);
        $this->post(route('cotizaciones.convertir', $boleta), ['tipo_comprobante' => 'boleta', 'condicion' => 'contado', 'metodo_pago' => 'yape'])
            ->assertSessionHasNoErrors();
        $this->assertSame('boleta', $boleta->fresh()->venta->tipo_comprobante);
        $this->assertSame('pendiente_envio', $boleta->fresh()->venta->estado_sunat, 'la boleta sí va a SUNAT');

        $factura = $this->crear($this->ruc, $this->lineas(['cantidad' => 1]));
        $this->post(route('cotizaciones.convertir', $factura), ['tipo_comprobante' => 'factura', 'condicion' => 'contado', 'metodo_pago' => 'transferencia'])
            ->assertSessionHasNoErrors();
        $this->assertSame('factura', $factura->fresh()->venta->tipo_comprobante);
    }

    public function test_la_factura_exige_un_cliente_con_ruc(): void
    {
        $c = $this->crear($this->dni);

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'factura', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $c->fresh()->estado);
        $this->assertSame(0, Venta::count());
    }

    public function test_no_se_convierte_dos_veces(): void
    {
        $c = $this->crear();
        $datos = ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'];

        $this->post(route('cotizaciones.convertir', $c), $datos)->assertSessionHasNoErrors();
        $this->post(route('cotizaciones.convertir', $c), $datos)->assertSessionHas('error');

        $this->assertSame(1, Venta::count());
        $this->assertSame(8, StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v1->id), 'el stock se descuenta una sola vez');
    }

    public function test_sin_stock_suficiente_no_convierte_y_no_deja_nada_a_medias(): void
    {
        $c = $this->crear(null, $this->lineas(['cantidad' => 50]));

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $c->fresh()->estado);
        $this->assertNull($c->fresh()->venta_id);
        $this->assertSame(0, Venta::count());
        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->almacen->id, $this->v1->id));
    }

    public function test_cobrar_ahora_exige_el_metodo_de_pago(): void
    {
        $c = $this->crear();

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado'])->assertSessionHas('error');
        $this->assertSame('pendiente', $c->fresh()->estado);
    }

    public function test_dejar_pendiente_de_cobro_no_pide_metodo_y_deja_la_venta_pendiente(): void
    {
        $c = $this->crear();

        $this->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'pendiente_cobro'])->assertSessionHasNoErrors();

        $this->assertSame('pendiente', $c->fresh()->venta->estado_pago);
    }

    public function test_un_vendedor_convierte_y_su_venta_queda_pendiente_de_cobro(): void
    {
        $vendedor = $this->usuario('Vendedor');
        $c = $this->crear($this->dni, null, $vendedor);

        $this->actingAs($vendedor)
            ->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])
            ->assertSessionHasNoErrors();

        $venta = $c->fresh()->venta;
        $this->assertSame('pendiente', $venta->estado_pago, 'el vendedor no cobra: lo hace el cajero');
        $this->assertSame($vendedor->id, $venta->user_id);
    }

    // ── PDF, búsquedas y permisos ─────────────────────────────────────────

    public function test_el_pdf_se_genera(): void
    {
        Empresa::create(['ruc' => '20123456789', 'razon_social' => 'KYRIOS S.A.C.', 'direccion' => 'Av. Ejemplo 123']);
        $c = $this->crear();

        $r = $this->get(route('cotizaciones.pdf', $c))->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_la_busqueda_de_productos_trae_variantes_precio_y_stock(): void
    {
        ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'tipo_precio' => 'venta_regular',
            'precio' => 100, 'incluye_igv' => false, 'activo' => true, 'prioridad' => 1]);
        ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => null, 'tipo_precio' => 'venta_regular',
            'precio' => 90, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);

        $r = $this->getJson(route('cotizaciones.productos', ['q' => 'Ring', 'almacen_id' => $this->almacen->id]))->assertOk()->json();
        $porCodigo = collect($r)->keyBy('codigo');

        $this->assertEquals(118.0, $porCodigo['KY-LU2501-0001']['precio'], 'precio sin IGV → se muestra con IGV');
        $this->assertEquals(90.0, $porCodigo['KY-LU2501-0002']['precio'], 'sin precio propio usa el del producto');
        $this->assertSame(10, $porCodigo['KY-LU2501-0001']['stock']);
        $this->assertSame(4, $porCodigo['KY-LU2501-0002']['stock']);

        $this->getJson(route('cotizaciones.productos', ['q' => 'KY-LU2501-0002']))->assertJsonCount(1);
        $this->getJson(route('cotizaciones.productos', ['q' => 'R']))->assertExactJson([]);
    }

    public function test_la_busqueda_no_ofrece_productos_por_serie(): void
    {
        Producto::create(['codigo' => 'CEL-9', 'nombre' => 'Ring Celular', 'categoria_id' => $this->producto->categoria_id,
            'tipo_inventario' => 'serie', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);

        $codigos = collect($this->getJson(route('cotizaciones.productos', ['q' => 'Ring']))->json())->pluck('codigo');
        $this->assertFalse($codigos->contains('CEL-9'));
    }

    public function test_la_busqueda_de_clientes(): void
    {
        $this->getJson(route('cotizaciones.clientes', ['q' => 'acme']))->assertJsonCount(1)->assertJsonPath('0.id', $this->ruc->id);
        $this->getJson(route('cotizaciones.clientes', ['q' => 'juan']))->assertJsonCount(1)->assertJsonPath('0.id', $this->dni->id);
        $this->getJson(route('cotizaciones.clientes', ['q' => 'x']))->assertExactJson([]);
    }

    public function test_listado_y_filtros(): void
    {
        $a = $this->crear($this->dni);
        $b = $this->crear($this->ruc);
        app(CotizacionService::class)->cambiarEstado($b, 'rechazada', 'no');
        $a->update(['fecha_vencimiento' => today()->subDay()]);

        $total = fn (array $f = []) => $this->get(route('cotizaciones.index', $f))->assertOk()->viewData('cotizaciones')->total();

        $this->assertSame(2, $total());
        $this->assertSame(1, $total(['estado' => 'vencida']));
        $this->assertSame(1, $total(['estado' => 'rechazada']));
        $this->assertSame(0, $total(['estado' => 'pendiente']), 'la pendiente vencida cuenta como vencida');
        $this->assertSame(1, $total(['q' => 'ACME']));
        $this->assertSame(1, $total(['q' => 'CT01-00000001']));
        $this->assertSame(1, $total(['cliente_id' => $this->ruc->id]));
    }

    public function test_un_vendedor_solo_ve_y_abre_sus_cotizaciones(): void
    {
        $vendedor = $this->usuario('Vendedor');
        $mia = $this->crear($this->dni, null, $vendedor);
        $ajena = $this->crear($this->ruc);

        $this->actingAs($vendedor);
        $this->assertSame(1, $this->get(route('cotizaciones.index'))->viewData('cotizaciones')->total());
        $this->get(route('cotizaciones.show', $mia))->assertOk();
        $this->get(route('cotizaciones.show', $ajena))->assertForbidden();
        $this->post(route('cotizaciones.estado', $ajena), ['estado' => 'aceptada'])->assertForbidden();
        $this->post(route('cotizaciones.convertir', $ajena), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'pendiente_cobro'])->assertForbidden();
    }

    public function test_un_almacenero_no_tiene_acceso_al_modulo(): void
    {
        $this->actingAs($this->usuario('Almacenero'));

        $this->get(route('cotizaciones.index'))->assertForbidden();
        $this->get(route('cotizaciones.create'))->assertForbidden();
    }

    public function test_las_pantallas_cargan(): void
    {
        $c = $this->crear();

        foreach ([route('cotizaciones.index'), route('cotizaciones.create'), route('cotizaciones.create', ['cliente_id' => $this->ruc->id]),
                  route('cotizaciones.show', $c), route('cotizaciones.edit', $c)] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
