<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pantallas fuera del módulo de productos muestran el nombre K y el de fábrica, y ambos códigos;
 * los buscadores encuentran por cualquiera de ellos.
 */
class NombresCodigosNPantallasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-T', 'nombre' => 'Tienda Central', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->producto = $this->crearProducto('KY-LU2501-0001', 'Ring', 'Oxygen', '25-0573-N3-B8');
        $this->variante = $this->producto->variantes()->first();
        StockAlmacen::fila($this->producto->id, $this->almacen->id, $this->variante->id)->update(['cantidad' => 10]);
        $this->variante->update(['stock_actual' => 10]);
        $this->producto->update(['stock_actual' => 10]);
    }

    private function crearProducto(string $codigo, string $nombreK, string $nombreFab, string $codigoFab): Producto
    {
        $cat = Categoria::firstOrCreate(['codigo' => 'CAT-T'], ['nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => $codigo, 'nombre' => $nombreK, 'nombre_kyrios' => $nombreK, 'nombre_fabrica' => $nombreFab,
            'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => $codigo, 'codigo_kyrios' => $codigo, 'codigo_fabricante' => $codigoFab,
            'codigo_excel' => 'KLU01-00003', 'acabado_1' => 'gris', 'estado' => 'activo']);

        return $p;
    }

    public function test_el_punto_de_venta_trae_ambos_nombres_ambos_codigos_y_texto_de_busqueda(): void
    {
        $html = $this->get(route('ventas.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Ring (fábrica: Oxygen)', json_decode('"' . 'Ring (fábrica: Oxygen)' . '"') ? 'Ring (fábrica: Oxygen)' : '');
        $this->assertStringContainsString('fab: 25-0573-N3-B8', html_entity_decode($html));
        $this->assertStringContainsString('texto_busqueda', $html);
        $this->assertMatchesRegularExpression('/25-0573-n3-b8/', $html, 'el texto de búsqueda incluye el código de fábrica en minúsculas');
    }

    public function test_cotizaciones_buscan_y_muestran_ambos_nombres_y_codigos(): void
    {
        foreach (['Oxygen', 'Ring', '25-0573', 'KY-LU2501'] as $q) {
            $r = $this->getJson(route('cotizaciones.productos', ['q' => $q, 'almacen_id' => $this->almacen->id]))->assertOk();
            $this->assertCount(1, $r->json(), "no encontró «{$q}»");
        }
        $item = $this->getJson(route('cotizaciones.productos', ['q' => 'Oxygen']))->json('0');
        $this->assertSame('Ring (fábrica: Oxygen)', $item['nombre']);
        $this->assertSame('KY-LU2501-0001 · fab: 25-0573-N3-B8', $item['codigo']);
    }

    public function test_la_cotizacion_guardada_y_su_pdf_muestran_ambos_codigos(): void
    {
        $cliente = \App\Models\Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '41340629', 'nombre' => 'CESAR LEE', 'estado' => 'activo']);
        $cot = app(CotizacionService::class)->crear(
            ['user_id' => $this->admin->id, 'cliente_id' => $cliente->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'validez_dias' => 7],
            [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118.0, 'incluye_igv' => true]]
        );

        $this->get(route('cotizaciones.show', $cot))->assertOk()->assertSee('Ring (fábrica: Oxygen)', false)->assertSee('fab: 25-0573-N3-B8', false);
        $this->get(route('cotizaciones.pdf', $cot))->assertOk();
        $this->assertStringContainsString('Ring (fábrica: Oxygen)', Cotizacion::find($cot->id)->detalles->first()->descripcion);
    }

    public function test_compras_buscan_por_nombre_de_fabrica_y_por_codigo_de_fabricante(): void
    {
        foreach (['Oxygen', 'Ring', '25-0573-N3', 'KLU01-00003', 'KY-LU2501'] as $q) {
            $this->assertCount(1, $this->getJson(route('compras.buscar-productos', ['q' => $q]))->assertOk()->json(), "no encontró «{$q}»");
        }
        $item = $this->getJson(route('compras.buscar-productos', ['q' => 'Oxygen']))->json('0');
        $this->assertSame('Ring (fábrica: Oxygen)', $item['nombre']);
        $this->assertStringContainsString('fab: 25-0573-N3-B8', $item['variantes'][0]['etiqueta']);
    }

    public function test_compras_pedidos_y_movimientos_muestran_los_nombres_en_sus_catalogos(): void
    {
        foreach (['compras.create', 'pedidos.create', 'inventario.movimientos.create'] as $ruta) {
            $html = $this->get(route($ruta))->assertOk()->getContent();
            // El catálogo viaja como JSON dentro de la página (tildes como \uXXXX): se busca la parte sin tilde
            $this->assertStringContainsString('Oxygen)', $html, "{$ruta}: falta el nombre de fábrica");
            $this->assertStringContainsString('25-0573-N3-B8', $html, "{$ruta}: falta el código de fábrica");
        }
    }

    public function test_los_traslados_buscan_por_nombre_de_fabrica_y_codigo_de_fabricante(): void
    {
        foreach (['Oxygen', '25-0573-N3-B8', 'KY-LU2501-0001'] as $q) {
            $this->assertCount(1, $this->getJson(route('traslados.buscar-productos', ['q' => $q, 'almacen_id' => $this->almacen->id]))->assertOk()->json(), "no encontró «{$q}»");
        }
        $item = $this->getJson(route('traslados.buscar-productos', ['q' => 'Oxygen']))->json('0');
        $this->assertSame('Ring (fábrica: Oxygen)', $item['nombre']);
        $this->assertStringContainsString('fab: 25-0573-N3-B8', $item['variantes'][0]['sku']);

        $this->get(route('traslados.stock', ['buscar' => 'Oxygen']))->assertOk()->assertSee('Ring (fábrica: Oxygen)', false);
    }

    public function test_el_escaner_devuelve_el_nombre_completo_y_ambos_codigos(): void
    {
        $r = $this->getJson(route('inventario.recepcion.buscar', ['codigo' => '25-0573-N3-B8', 'almacen_id' => $this->almacen->id]))->assertOk()->json('resultados.0');
        $this->assertSame('Ring (fábrica: Oxygen)', $r['nombre']);
        $this->assertSame('KY-LU2501-0001 · fab: 25-0573-N3-B8', $r['codigos']);

        $lista = $this->getJson(route('inventario.recepcion.variantes', ['q' => 'Oxygen']))->assertOk()->json();
        $this->assertCount(1, $lista);
        $this->assertSame('Ring (fábrica: Oxygen)', $lista[0]['nombre']);
    }

    public function test_los_movimientos_muestran_los_dos_codigos(): void
    {
        \App\Models\MovimientoInventario::registrarMovimiento([
            'producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'almacen_id' => $this->almacen->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 3, 'motivo' => 'prueba', 'user_id' => $this->admin->id,
        ]);

        $this->get(route('inventario.movimientos.index'))->assertOk()->assertSee('Ring (fábrica: Oxygen)', false)->assertSee('fab: 25-0573-N3-B8', false);
    }

    public function test_el_kardex_y_la_ficha_de_almacen_muestran_los_nombres(): void
    {
        $this->get(route('inventario.reportes.kardex'))->assertOk()->assertSee('Ring (fábrica: Oxygen)', false);
        $this->get(route('inventario.almacenes.show', $this->almacen))->assertOk();
    }

    public function test_las_pantallas_de_lectura_no_hacen_una_consulta_por_producto(): void
    {
        for ($i = 2; $i <= 40; $i++) {
            $p = $this->crearProducto(sprintf('KY-LU2501-%04d', $i), "Modelo {$i}", "Fab {$i}", "F-{$i}");
            StockAlmacen::fila($p->id, $this->almacen->id, $p->variantes()->first()->id)->update(['cantidad' => 5]);
        }

        foreach (['compras.create', 'pedidos.create', 'inventario.movimientos.create', 'ventas.create', 'traslados.stock'] as $ruta) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route($ruta))->assertOk();
            $this->assertLessThan(70, count(DB::getQueryLog()), "{$ruta} hace demasiadas consultas");
        }
    }
}
