<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Support\EtiquetaProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada variante conserva su propio nombre K y de fábrica (Cuadro -> Calma, Cálido, Centro).
 */
class NombresPorVarianteTest extends TestCase
{
    use RefreshDatabase;

    private function producto(): Producto
    {
        $cat = Categoria::firstOrCreate(['codigo' => 'CAT-NV'], ['nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU0001-0001', 'nombre' => 'Calma', 'nombre_fabrica' => 'Cuadro', 'nombre_kyrios' => 'Calma',
            'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);
        foreach ([['KY-LU0001-0001', 'Calma'], ['KY-LU0001-0002', 'Cálido'], ['KY-LU0001-0003', 'Centro']] as $i => [$cod, $k]) {
            ProductoVariante::create(['producto_id' => $p->id, 'sku' => $cod, 'codigo_kyrios' => $cod, 'codigo_fabricante' => "F-{$i}",
                'nombre_fabrica' => 'Cuadro', 'nombre_kyrios' => $k, 'estado' => 'activo']);
        }

        return $p->load('variantes');
    }

    public function test_el_helper_lista_los_otros_nombres_k_sin_repetir(): void
    {
        $this->assertSame(['Cálido', 'Centro'], EtiquetaProducto::otrosNombresK($this->producto()));
    }

    public function test_el_nombre_de_una_variante_muestra_ambos_nombres(): void
    {
        $v = $this->producto()->variantes->get(1);

        $this->assertSame('Cálido (fábrica: Cuadro)', EtiquetaProducto::nombreVariante($v));
        $this->assertSame('', EtiquetaProducto::nombreVariante(new ProductoVariante()));
    }

    public function test_el_listado_y_la_ficha_muestran_los_nombres_de_las_variantes(): void
    {
        $p = $this->producto();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));

        $this->get(route('inventario.productos.index'))->assertOk()->assertSee('Calma')->assertSee('Cálido · Centro');
        $this->get(route('inventario.productos.show', $p))->assertOk()->assertSee('Cálido (fábrica: Cuadro)');
    }

    public function test_la_busqueda_encuentra_por_el_nombre_k_de_una_variante(): void
    {
        $this->producto();
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));

        $this->get(route('inventario.productos.index', ['buscar' => 'Centro']))->assertOk()->assertSee('Cálido · Centro');
    }

    public function test_el_comando_completa_las_variantes_desde_el_excel_sin_pisar(): void
    {
        $p = $this->producto();
        $v = $p->variantes->first();
        $v->update(['nombre_kyrios' => null, 'nombre_fabrica' => null, 'codigo_fabricante' => '55-9423-Z5-T2']);

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Base de Productos');
        $hoja->fromArray(['Código', 'Código KYRIOS', '', '', '', 'Tipo', '', '', '', '', 'Nombre Fabrica', 'Nombre K'], null, 'A4');
        $hoja->fromArray(['55-9423-Z5-T2', 'KLU01-00001', '', '', '', 'Luminaria', '', '', '', '', 'Xena', 'xina'], null, 'A5');
        $ruta = tempnam(sys_get_temp_dir(), 'nv') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);

        $this->artisan('productos:nombres-por-variante', ['archivo' => $ruta])->assertSuccessful();
        $this->artisan('productos:nombres-por-variante', ['archivo' => $ruta])->assertSuccessful();

        $v->refresh();
        $this->assertSame('Xena', $v->nombre_fabrica);
        $this->assertSame('xina', $v->nombre_kyrios);
        $this->assertSame('Cálido', $p->variantes->get(1)->fresh()->nombre_kyrios, 'no pisa nombres ya escritos');
        unlink($ruta);
    }

    public function test_los_codigos_de_danados_que_no_estan_en_la_base_pasan_a_sin_tipo_sin_cambiar_codigos(): void
    {
        $this->seed(\Database\Seeders\KyriosCatalogoSeeder::class);
        $lum = \App\Models\Catalogo\TipoProducto::where('codigo', 'LU')->firstOrFail();
        $cat = Categoria::firstOrCreate(['codigo' => 'CAT-NV'], ['nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU0000-0099', 'nombre' => 'D-1', 'categoria_id' => $cat->id, 'tipo_producto_id' => $lum->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU0000-0099', 'codigo_kyrios' => 'KY-LU0000-0099', 'codigo_fabricante' => 'D-1', 'estado' => 'activo']);

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $base = $libro->getActiveSheet();
        $base->setTitle('Base de Productos');
        $base->fromArray(['Código', 'Código KYRIOS', '', '', '', 'Tipo de producto'], null, 'A4');
        $base->fromArray(['OTRO-1', 'KLU01-00001', '', '', '', 'Luminaria'], null, 'A5');
        $danados = $libro->createSheet();
        $danados->setTitle('Dañados');
        $danados->fromArray(['Codigo'], null, 'A1');
        $danados->fromArray([['D-1'], ['OTRO-1']], null, 'A2');
        $ruta = tempnam(sys_get_temp_dir(), 'dn') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);

        $this->artisan('productos:marcar-sin-tipo', ['archivo' => $ruta])->assertSuccessful();

        $p->refresh();
        $this->assertSame('Sin tipo', $p->tipoProducto->nombre);
        $this->assertSame('KY-LU0000-0099', $p->codigo, 'el código no cambia');
        unlink($ruta);
    }
}
