<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\ConteoInventario;
use App\Models\GuiaRemision;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reportes CSV, Excel y PDF del inventario: se generan tanto sin datos como con datos.
 */
class Auditoria2BExportacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
    }

    private function descargar(string $url, string $tipo)
    {
        $r = $this->get($url);
        $r->assertOk();
        $contenido = $r->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $r->streamedContent() : $r->getContent();
        $this->assertStringContainsString($tipo, (string) $r->headers->get('content-type'), $url);

        return $contenido;
    }

    public function test_reportes_csv_sin_datos(): void
    {
        foreach (['stock-valorizado', 'valorizacion-prorateada', 'abc', 'kardex'] as $reporte) {
            $this->get(route('inventario.reportes.' . ($reporte === 'abc' ? 'abc' : $reporte), ['export' => 'csv']))->assertOk();
        }
    }

    public function test_reportes_csv_con_datos_y_conteo_en_excel_y_pdf(): void
    {
        $a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Administración', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring «Ñandú» / 30cm', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $v = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        $p->update(['ultimo_costo_compra' => 10]); // el ABC solo lista productos con valor de inventario
        app(EscanerService::class)->recibir($a->id, [['variante_id' => $v->id, 'cantidad' => 6]], $this->admin->id);

        foreach (['stock-valorizado', 'valorizacion-prorateada', 'abc'] as $reporte) {
            $csv = $this->descargar(route('inventario.reportes.' . $reporte, ['export' => 'csv']), 'csv');
            $this->assertStringContainsString('Ring', $csv, $reporte);
        }
        $csv = $this->descargar(route('inventario.reportes.kardex', ['export' => 'csv', 'producto_id' => $p->id, 'desde' => now()->subDay()->toDateString(), 'hasta' => now()->toDateString()]), 'csv');
        $this->assertStringContainsString('Recepci', $csv);

        $this->post(route('inventario-fisico.store'), ['nombre' => 'Conteo prueba', 'almacen_id' => $a->id])->assertRedirect();
        $conteo = ConteoInventario::first();
        $this->assertNotNull($conteo);
        $this->assertStringStartsWith('%PDF', $this->descargar(route('inventario-fisico.pdf', $conteo), 'pdf'));
        $r = $this->get(route('inventario-fisico.excel', $conteo));
        $r->assertOk();
    }
}
