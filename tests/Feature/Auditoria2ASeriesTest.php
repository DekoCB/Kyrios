<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\SerieComprobante;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\EscanerService;
use App\Services\SucursalService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Series de comprobantes: formato exigido por SUNAT, y numeración que no se puede repetir.
 */
class Auditoria2ASeriesTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->sucursal = Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $almacen->id, 'estado' => 'activo']);
        $almacen->update(['sucursal_id' => $this->sucursal->id]);
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo', 'almacen_id' => $almacen->id]);
        $this->actingAs($this->admin);
    }

    private function nuevaSerie(string $tipo, string $serie)
    {
        return $this->post(route('admin.sucursales.series.store', $this->sucursal), [
            'tipo_comprobante' => $tipo, 'tipo_nombre' => 'Prueba', 'serie' => $serie, 'correlativo_actual' => 1, 'formato_impresion' => 'A4',
        ]);
    }

    public function test_todas_las_series_estandar_cumplen_el_formato_de_sunat(): void
    {
        app(SucursalService::class)->generarSeriesEstandar($this->sucursal, 1);
        app(SucursalService::class)->generarSeriesEstandar($this->sucursal, 12);

        foreach (SerieComprobante::where('sucursal_id', $this->sucursal->id)->get() as $s) {
            $this->assertMatchesRegularExpression(SerieComprobante::patronSerie($s->tipo_comprobante), $s->serie, "{$s->tipo_nombre}: {$s->serie}");
        }
        $this->assertTrue(SerieComprobante::where('tipo_comprobante', '09')->where('serie', 'T001')->exists(), 'la guía usa T001, no T0101');
        $this->assertTrue(SerieComprobante::where('tipo_comprobante', '09')->where('serie', 'T012')->exists());
    }

    public function test_se_rechazan_series_con_formato_invalido_para_sunat(): void
    {
        $this->nuevaSerie('01', 'B001')->assertSessionHasErrors('serie');     // factura con letra de boleta
        $this->nuevaSerie('03', 'F001')->assertSessionHasErrors('serie');     // boleta con letra de factura
        $this->nuevaSerie('09', 'T0101')->assertSessionHasErrors('serie');    // guía de 5 caracteres
        $this->nuevaSerie('01', 'FA')->assertSessionHasErrors('serie');       // muy corta
        $this->assertSame(0, SerieComprobante::count());
    }

    public function test_se_aceptan_series_validas_y_se_guardan_en_mayuscula(): void
    {
        $this->nuevaSerie('01', 'f002')->assertSessionHasNoErrors();
        $this->nuevaSerie('09', 'T002')->assertSessionHasNoErrors();
        $this->nuevaSerie('NV', 'NV09')->assertSessionHasNoErrors();

        $this->assertTrue(SerieComprobante::where('serie', 'F002')->exists());
        $this->assertTrue(SerieComprobante::where('serie', 'T002')->exists());
    }

    public function test_el_formulario_de_facturacion_aplica_el_mismo_formato(): void
    {
        $this->post(route('facturacion.series.store'), [
            'sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '09', 'tipo_nombre' => 'Guía', 'serie' => 'T0101', 'correlativo_actual' => 1, 'formato_impresion' => 'A4',
        ])->assertSessionHasErrors('serie');
        $this->assertSame(0, SerieComprobante::count());
    }

    private function ventaConSerie(): array
    {
        $serie = SerieComprobante::create(['sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '03', 'tipo_nombre' => 'Boleta', 'serie' => 'B001', 'correlativo_actual' => 1, 'formato_impresion' => 'ticket', 'activo' => true]);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $prod = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $var = ProductoVariante::create(['producto_id' => $prod->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->sucursal->almacen_id, [['variante_id' => $var->id, 'cantidad' => 20]], $this->admin->id);
        app(CajaService::class)->abrirCaja($this->admin->id, $this->sucursal->almacen_id, 0);
        $cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan', 'estado' => 'activo']);
        for ($i = 0; $i < 3; $i++) {
            app(VentaService::class)->crearVenta([
                'user_id' => $this->admin->id, 'cliente_id' => $cliente->id, 'almacen_id' => $this->sucursal->almacen_id, 'sucursal_id' => $this->sucursal->id, 'fecha' => now()->toDateString(),
                'tipo_comprobante' => 'boleta', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
            ], [['producto_id' => $prod->id, 'variante_id' => $var->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true]], ['metodo_pago' => 'efectivo']);
        }

        return [$serie->fresh()];
    }

    public function test_el_siguiente_numero_no_puede_repetir_uno_ya_emitido(): void
    {
        [$serie] = $this->ventaConSerie();
        $this->assertSame(4, (int) $serie->correlativo_actual);

        $this->put(route('admin.sucursales.series.update', [$this->sucursal, $serie]), ['serie' => 'B001', 'correlativo_actual' => 2, 'formato_impresion' => 'ticket'])
            ->assertSessionHasErrors('correlativo_actual');
        $this->assertSame(4, (int) $serie->fresh()->correlativo_actual);

        $this->put(route('admin.sucursales.series.update', [$this->sucursal, $serie]), ['serie' => 'B001', 'correlativo_actual' => 10, 'formato_impresion' => 'ticket'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, (int) $serie->fresh()->correlativo_actual);
    }

    public function test_el_formulario_de_facturacion_tampoco_deja_bajar_el_numero(): void
    {
        [$serie] = $this->ventaConSerie();

        $this->put(route('facturacion.series.update', $serie), ['tipo_nombre' => 'Boleta', 'correlativo_actual' => 1, 'formato_impresion' => 'ticket'])
            ->assertSessionHasErrors('correlativo_actual');
        $this->assertSame(4, (int) $serie->fresh()->correlativo_actual);
    }

    public function test_una_serie_con_comprobantes_emitidos_no_cambia_de_codigo(): void
    {
        [$serie] = $this->ventaConSerie();

        $this->put(route('admin.sucursales.series.update', [$this->sucursal, $serie]), ['serie' => 'B002', 'correlativo_actual' => 4, 'formato_impresion' => 'ticket'])
            ->assertSessionHasErrors('serie');
        $this->assertSame('B001', $serie->fresh()->serie);
    }

    public function test_una_serie_sin_emisiones_si_puede_renombrarse(): void
    {
        $serie = SerieComprobante::create(['sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '03', 'tipo_nombre' => 'Boleta', 'serie' => 'B001', 'correlativo_actual' => 1, 'formato_impresion' => 'ticket', 'activo' => true]);

        $this->put(route('admin.sucursales.series.update', [$this->sucursal, $serie]), ['serie' => 'b002', 'correlativo_actual' => 1, 'formato_impresion' => 'ticket'])
            ->assertSessionHasNoErrors();
        $this->assertSame('B002', $serie->fresh()->serie);
    }

    public function test_la_migracion_renombra_las_series_de_guia_de_cinco_caracteres(): void
    {
        $a = SerieComprobante::create(['sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '09', 'tipo_nombre' => 'Guía', 'serie' => 'T0101', 'correlativo_actual' => 7, 'formato_impresion' => 'A4', 'activo' => true]);
        $b = SerieComprobante::create(['sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '09', 'tipo_nombre' => 'Guía', 'serie' => 'T0201', 'correlativo_actual' => 1, 'formato_impresion' => 'A4', 'activo' => false]);
        SerieComprobante::create(['sucursal_id' => $this->sucursal->id, 'tipo_comprobante' => '09', 'tipo_nombre' => 'Guía', 'serie' => 'T002', 'correlativo_actual' => 1, 'formato_impresion' => 'A4', 'activo' => true]);

        (require database_path('migrations/2026_10_28_100000_corrige_series_de_guias_de_remision.php'))->up();

        $this->assertSame('T001', $a->fresh()->serie);
        $this->assertSame(7, (int) $a->fresh()->correlativo_actual, 'el correlativo se conserva');
        $this->assertSame('T0201', $b->fresh()->serie, 'si T002 ya existe no se pisa');
    }
}
