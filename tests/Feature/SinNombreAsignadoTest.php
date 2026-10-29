<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CotizacionService;
use App\Support\EtiquetaProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un producto cuyo «nombre» es en realidad un código se muestra como «Sin nombre asignado» en pantallas;
 * los documentos (comprobantes, cotizaciones, XML) nunca lo dicen y caen al código.
 */
class SinNombreAsignadoTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;
    private Categoria $cat;

    protected function setUp(): void
    {
        parent::setUp();

        // Rol administrativo vigente: el sistema está cambiando de «Administrador» a «Administración»
        $rol = Role::whereIn('nombre', ['Administración', 'Administración'])->orderByDesc('nombre')->first() ?? Role::firstOrCreate(['nombre' => 'Administración']);
        $this->actingAs(User::factory()->create(['role_id' => $rol->id, 'estado' => 'activo']));
        $this->almacen = Almacen::create(['codigo' => 'ALM-T', 'nombre' => 'Tienda Central', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->cat = Categoria::create(['codigo' => 'CAT-T', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        EtiquetaProducto::olvidarCodigos();
    }

    private function producto(string $codigo, string $nombre, ?string $k = null, ?string $f = null, string $codigoFab = '00-0078-N3-F9'): Producto
    {
        $p = Producto::create(['codigo' => $codigo, 'nombre' => $nombre, 'nombre_kyrios' => $k, 'nombre_fabrica' => $f, 'categoria_id' => $this->cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => $codigo, 'codigo_kyrios' => $codigo, 'codigo_fabricante' => $codigoFab,
            'codigo_excel' => 'KLU01-00099', 'acabado_1' => 'gris', 'estado' => 'activo']);
        EtiquetaProducto::olvidarCodigos();

        return $p->fresh();
    }

    // ── Regla del helper ────────────────────────────────────────────────

    public function test_con_nombre_k_se_muestra_el_nombre_k(): void
    {
        $p = $this->producto('KY-LU2501-0001', '00-0078-N3-F9', 'Ring', 'Oxygen');

        $this->assertTrue(EtiquetaProducto::tieneNombre($p));
        $this->assertSame('Ring (fábrica: Oxygen)', EtiquetaProducto::nombre($p));
        $this->assertSame('Ring', EtiquetaProducto::nombrePrincipal($p));
    }

    public function test_solo_con_nombre_de_fabrica_se_muestra_ese(): void
    {
        $p = $this->producto('KY-LU2501-0002', '00-0078-N3-F9', null, 'Oxygen');

        $this->assertTrue(EtiquetaProducto::tieneNombre($p));
        $this->assertSame('Oxygen', EtiquetaProducto::nombre($p));
    }

    public function test_un_nombre_escrito_a_mano_se_respeta(): void
    {
        $p = $this->producto('KY-LU2501-0003', 'Lámpara de mesa Ring 30');

        $this->assertTrue(EtiquetaProducto::tieneNombre($p));
        $this->assertSame('Lámpara de mesa Ring 30', EtiquetaProducto::nombre($p));
    }

    public function test_un_nombre_igual_al_codigo_de_fabrica_es_sin_nombre(): void
    {
        $p = $this->producto('KY-LU2501-0004', '00-0078-N3-F9');

        $this->assertFalse(EtiquetaProducto::tieneNombre($p));
        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombre($p));
        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombrePrincipal($p));
    }

    public function test_un_nombre_igual_al_codigo_kyrios_o_al_del_excel_es_sin_nombre(): void
    {
        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombre($this->producto('KY-LU2501-0005', 'KY-LU2501-0005')));
        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombre($this->producto('KY-LU2501-0006', 'klu01-00099')), 'sin importar mayúsculas');
    }

    public function test_un_nombre_vacio_es_sin_nombre(): void
    {
        $p = $this->producto('KY-LU2501-0007', '');

        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombre($p));
    }

    public function test_funciona_aunque_las_variantes_no_esten_cargadas_ni_haya_modelo(): void
    {
        $this->producto('KY-LU2501-0008', '00-0078-N3-F9');

        $arreglo = ['codigo' => 'KY-LU2501-0008', 'nombre' => '00-0078-N3-F9', 'nombre_kyrios' => null, 'nombre_fabrica' => null];

        $this->assertSame('Sin nombre asignado', EtiquetaProducto::nombre($arreglo));
    }

    public function test_el_documento_nunca_dice_sin_nombre_y_cae_al_codigo(): void
    {
        $p = $this->producto('KY-LU2501-0009', '00-0078-N3-F9');

        $this->assertSame('00-0078-N3-F9', EtiquetaProducto::nombreParaDocumento($p), 'código de fábrica');
        $this->assertSame('00-0078-N3-F9', EtiquetaProducto::documento($p));
        $this->assertSame('00-0078-N3-F9', EtiquetaProducto::rotulo($p), 'los mensajes de error identifican por código');

        $con = $this->producto('KY-LU2501-0010', 'x', 'Ring', 'Oxygen', '11-1111');
        $this->assertSame('Ring (fábrica: Oxygen)', EtiquetaProducto::documento($con));
    }

    // ── Pantallas ───────────────────────────────────────────────────────

    public function test_el_listado_y_la_ficha_de_productos_muestran_sin_nombre_asignado_en_vez_del_codigo(): void
    {
        $p = $this->producto('KY-LU2501-0011', '00-0078-N3-F9');

        $lista = $this->get(route('inventario.productos.index'))->assertOk()->assertSee('Sin nombre asignado');
        $this->assertStringNotContainsString('text-gray-900">00-0078-N3-F9</p>', $lista->getContent(), 'el código ya no aparece como nombre');

        $this->get(route('inventario.productos.show', $p))->assertOk()->assertSee('Sin nombre asignado');
        $this->get(route('inventario.productos.variantes', $p))->assertOk()->assertSee('Sin nombre asignado');
    }

    public function test_el_formulario_de_edicion_conserva_el_valor_real_y_explica_la_regla(): void
    {
        $p = $this->producto('KY-LU2501-0012', '00-0078-N3-F9');

        $this->get(route('inventario.productos.edit', $p))->assertOk()
            ->assertSee('value="00-0078-N3-F9"', false)
            ->assertSee('Sin nombre asignado');
    }

    public function test_un_producto_con_nombre_no_muestra_el_texto(): void
    {
        $this->producto('KY-LU2501-0013', 'x', 'Ring', 'Oxygen');

        $this->get(route('inventario.productos.index'))->assertOk()->assertDontSee('Sin nombre asignado');
    }

    public function test_el_punto_de_venta_muestra_sin_nombre_asignado_en_el_json(): void
    {
        $p = $this->producto('KY-LU2501-0014', '00-0078-N3-F9');
        StockAlmacen::fila($p->id, $this->almacen->id, $p->variantes()->first()->id)->update(['cantidad' => 5]);

        $html = $this->get(route('ventas.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Sin nombre asignado', html_entity_decode($html));
    }

    public function test_la_busqueda_por_codigo_sigue_funcionando_sin_nombre(): void
    {
        $this->producto('KY-LU2501-0015', '00-0078-N3-F9');

        $this->assertCount(1, $this->getJson(route('compras.buscar-productos', ['q' => '00-0078']))->assertOk()->json());
        $this->assertCount(1, $this->getJson(route('compras.buscar-productos', ['q' => 'KY-LU2501-0015']))->json());
    }

    public function test_los_selectores_de_compras_muestran_sin_nombre_asignado(): void
    {
        $this->producto('KY-LU2501-0016', '00-0078-N3-F9');

        $item = $this->getJson(route('compras.buscar-productos', ['q' => 'KY-LU2501-0016']))->json('0');

        $this->assertSame('Sin nombre asignado', $item['nombre']);
        $this->assertStringContainsString('KY-LU2501-0016', $item['codigos'] ?? '');
    }

    // ── Documentos ──────────────────────────────────────────────────────

    public function test_la_cotizacion_y_su_pdf_usan_el_codigo_y_no_el_texto(): void
    {
        $p = $this->producto('KY-LU2501-0017', '00-0078-N3-F9');
        $cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'CESAR LEE', 'estado' => 'activo']);
        $cot = app(CotizacionService::class)->crear(
            ['user_id' => auth()->id(), 'cliente_id' => $cliente->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'validez_dias' => 7],
            [['producto_id' => $p->id, 'variante_id' => $p->variantes()->first()->id, 'cantidad' => 1, 'precio_unitario' => 118.0, 'incluye_igv' => true]]
        );

        $detalle = $cot->fresh()->detalles()->with(['producto', 'variante'])->first();
        $this->assertStringNotContainsString('Sin nombre asignado', $detalle->descripcion);
        $this->assertStringNotContainsString('Sin nombre asignado', $detalle->nombre_mostrar);
        $this->assertStringContainsString('00-0078-N3-F9', $detalle->nombre_mostrar);

        $this->get(route('cotizaciones.show', $cot))->assertOk()->assertDontSee('Sin nombre asignado');
        $this->get(route('cotizaciones.pdf', $cot))->assertOk();
    }

    public function test_el_comprobante_con_producto_sin_nombre_cae_al_codigo(): void
    {
        $p = $this->producto('KY-LU2501-0018', '00-0078-N3-F9');
        StockAlmacen::fila($p->id, $this->almacen->id, $p->variantes()->first()->id)->update(['cantidad' => 5]);
        $p->variantes()->first()->update(['stock_actual' => 5]);
        $p->update(['stock_actual' => 5]);
        Caja::create(['user_id' => auth()->id(), 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString()] + ['fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'CESAR LEE', 'estado' => 'activo']);

        $venta = app(\App\Services\VentaService::class)->crearVenta([
            'user_id' => auth()->id(), 'cliente_id' => $cliente->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'nota_venta', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'estado_pago' => 'pagado',
            'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $p->id, 'variante_id' => $p->variantes()->first()->id, 'cantidad' => 1, 'precio_unitario' => 118.0, 'incluye_igv' => true]]);

        $detalle = $venta->detalles()->with(['producto.variantes'])->first();
        $this->assertSame('00-0078-N3-F9', EtiquetaProducto::nombreParaDocumento($detalle->producto), 'descripción para el XML de SUNAT');

        $venta->load('vendedor', 'cliente', 'almacen', 'sucursal', 'serieComprobante', 'detalles.producto.unidadMedida', 'detalles.variante.color');
        $html = view('pdf.factura-a4', [
            'venta' => $venta, 'empresa' => new \App\Models\Empresa(['razon_social' => 'KYRIOS', 'ruc' => '20000000001']),
            'sucursal' => null, 'pagos' => collect(),
        ])->render();
        $this->assertStringNotContainsString('Sin nombre asignado', $html);
        $this->assertStringContainsString('00-0078-N3-F9', $html);
    }
}
