<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoProducto;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\KyriosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Sin tipo», atributos de variante y nombres/códigos visibles en el módulo de productos.
 */
class SinTipoVariantesVTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KyriosCatalogoSeeder::class);
        $this->categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->unidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'categoria' => 'unidad', 'permite_decimales' => false]);
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Producto ' . uniqid(), 'categoria_id' => $this->categoria->id,
            'marca_id' => Marca::where('nombre', 'LEDS-C4')->value('id'), 'unidad_medida_id' => $this->unidad->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo',
            'variante_inicial' => ['acabado_1' => 'negro(a)'],
        ], $extra);
    }

    private function crear(array $datos): Producto
    {
        $this->post(route('inventario.productos.store'), $datos)->assertSessionHasNoErrors();

        return Producto::where('nombre', $datos['nombre'])->firstOrFail();
    }

    // ── Sin tipo ──────────────────────────────────────────────────────────

    public function test_existe_el_tipo_sin_tipo_con_un_codigo_que_no_choca(): void
    {
        $st = TipoProducto::where('nombre', 'Sin tipo')->first();

        $this->assertNotNull($st);
        $this->assertSame('ST', $st->codigo);
        $this->assertFalse($st->usaTipoLuminaria());
        $this->assertSame(1, TipoProducto::where('codigo', 'ST')->count());
    }

    public function test_un_producto_creado_sin_elegir_tipo_queda_como_sin_tipo_con_codigo_valido(): void
    {
        $p = $this->crear($this->datos());

        $this->assertSame('Sin tipo', $p->tipoProducto->nombre);
        $this->assertMatchesRegularExpression('/^KY-ST00\d{2}-0001$/', $p->codigo);
        $this->assertSame($p->codigo, $p->variantes()->first()->codigo_kyrios);
    }

    public function test_el_formulario_de_alta_ofrece_sin_tipo_ya_seleccionado(): void
    {
        $id = TipoProducto::where('codigo', 'ST')->value('id');

        $this->get(route('inventario.productos.create'))->assertOk()
            ->assertSee('ST — Sin tipo', false)
            ->assertSee("tipo: '{$id}'", false);
    }

    public function test_el_listado_muestra_sin_tipo_y_lo_filtra(): void
    {
        $st = TipoProducto::where('codigo', 'ST')->value('id');
        $p = $this->crear($this->datos(['nombre' => 'Modelo sin clasificar']));
        $this->crear($this->datos(['nombre' => 'Modelo lampara', 'tipo_producto_id' => TipoProducto::where('codigo', 'LA')->value('id')]));

        $r = $this->get(route('inventario.productos.index', ['tipo_producto_id' => $st]))->assertOk();
        $r->assertSee('Modelo sin clasificar')->assertDontSee('Modelo lampara')->assertSee('Sin tipo');
        $this->assertSame($st, $p->tipo_producto_id);
    }

    public function test_el_comando_marca_sin_tipo_no_toca_los_codigos_y_es_idempotente(): void
    {
        $lu = TipoProducto::where('codigo', 'LU')->value('id');
        $p = Producto::create(['codigo' => 'KY-LU2001-0001', 'nombre' => 'X', 'categoria_id' => $this->categoria->id, 'tipo_producto_id' => $lu,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2001-0001', 'codigo_kyrios' => 'KY-LU2001-0001', 'codigo_fabricante' => 'ZZ-SIN-TIPO', 'estado' => 'activo']);

        $xlsx = $this->excelDePrueba([['ZZ-SIN-TIPO', null, null, null, null, null], ['ZZ-CON-TIPO', null, null, null, null, 'Luminaria']]);

        $this->artisan('productos:marcar-sin-tipo', ['archivo' => $xlsx, '--dry-run' => true])->assertSuccessful();
        $this->assertSame($lu, $p->fresh()->tipo_producto_id, 'dry-run no cambia nada');

        $this->artisan('productos:marcar-sin-tipo', ['archivo' => $xlsx])->assertSuccessful();
        $this->artisan('productos:marcar-sin-tipo', ['archivo' => $xlsx])->assertSuccessful();

        $p->refresh();
        $this->assertSame('Sin tipo', $p->tipoProducto->nombre);
        $this->assertSame('KY-LU2001-0001', $p->codigo, 'el código Kyrios no se toca');
        $this->assertSame('KY-LU2001-0001', $p->variantes()->first()->codigo_kyrios);
        @unlink($xlsx);
    }

    // ── Atributos de variante ─────────────────────────────────────────────

    private function atributos(): array
    {
        return [
            'acabado_1' => 'blanco(a)', 'tonalidad_luz' => 'cálida', 'tipo_lampara' => 'LED', 'angulo_haz' => '36°',
            'protocolo_regulacion' => 'DALI', 'eficiencia_luminica' => '95.5', 'garantia_meses' => 24, 'vida_util_horas' => 50000,
            'ip' => '44', 'cri' => '90', 'otros' => 'Incluye driver',
        ];
    }

    public function test_una_variante_guarda_y_muestra_los_atributos_que_la_diferencian(): void
    {
        $p = $this->crear($this->datos());
        $this->post(route('inventario.productos.variantes.store', $p), $this->atributos())->assertSessionHasNoErrors();

        $v = $p->variantes()->where('acabado_1', 'blanco(a)')->firstOrFail();
        $this->assertSame('LED', $v->tipo_lampara);
        $this->assertSame('DALI', $v->protocolo_regulacion);
        $this->assertSame(24, (int) $v->garantia_meses);
        $this->assertSame(50000, (int) $v->vida_util_horas);
        $this->assertEquals(95.5, (float) $v->eficiencia_luminica);

        $this->get(route('inventario.productos.show', $p))->assertOk()
            ->assertSee('DALI')->assertSee('24 meses')->assertSee('50.000 h')->assertSee('95.5 lm/W');
        $this->get(route('inventario.productos.variantes', $p))->assertOk()->assertSee('DALI')->assertSee('Incluye driver');
    }

    public function test_dos_variantes_que_solo_difieren_en_esos_atributos_no_son_duplicadas(): void
    {
        $p = $this->crear($this->datos());
        $this->post(route('inventario.productos.variantes.store', $p), ['acabado_1' => 'negro(a)', 'ip' => '20'])->assertSessionHasNoErrors();
        $this->post(route('inventario.productos.variantes.store', $p), ['acabado_1' => 'negro(a)', 'ip' => '65'])->assertSessionHasNoErrors();

        $this->assertSame(3, $p->variantes()->count());
        $this->assertSame(3, $p->variantes()->distinct()->count('codigo_kyrios'), 'cada variante conserva su propio código');
    }

    public function test_los_atributos_de_variante_se_validan(): void
    {
        $p = $this->crear($this->datos());

        $this->post(route('inventario.productos.variantes.store', $p), ['acabado_1' => 'rojo', 'garantia_meses' => -1, 'vida_util_horas' => 'x', 'eficiencia_luminica' => -5])
            ->assertSessionHasErrors(['garantia_meses', 'vida_util_horas', 'eficiencia_luminica']);
    }

    public function test_el_filtro_de_fuente_de_luz_considera_producto_y_variantes(): void
    {
        $a = $this->crear($this->datos(['nombre' => 'Modelo A', 'fuente_luz' => 'halógena']));
        $b = $this->crear($this->datos(['nombre' => 'Modelo B']));
        $this->post(route('inventario.productos.variantes.store', $b), ['acabado_1' => 'rojo', 'tipo_lampara' => 'LED'])->assertSessionHasNoErrors();

        $this->get(route('inventario.productos.index', ['fuente_luz' => 'halógena']))->assertOk()->assertSee('Modelo A')->assertDontSee('Modelo B');
        $this->get(route('inventario.productos.index', ['fuente_luz' => 'LED']))->assertOk()->assertSee('Modelo B')->assertDontSee('Modelo A');
        $this->get(route('inventario.productos.index', ['ip' => '65']))->assertOk()->assertDontSee('Modelo A');
    }

    // ── Nombres y códigos juntos ──────────────────────────────────────────

    public function test_el_listado_y_la_ficha_muestran_ambos_nombres_y_ambos_codigos(): void
    {
        $p = $this->crear($this->datos(['nombre' => 'Multi', 'nombre_kyrios' => 'Multi', 'nombre_fabrica' => 'Multidir',
            'variante_inicial' => ['acabado_1' => 'negro(a)', 'codigo_fabricante' => '25-0573-N3-B8']]));

        foreach ([route('inventario.productos.index'), route('inventario.productos.show', $p), route('inventario.productos.variantes', $p)] as $url) {
            $this->get($url)->assertOk()->assertSee('Multidir')->assertSee('25-0573-N3-B8')->assertSee($p->codigo);
        }
    }

    public function test_los_comandos_de_nombres_completan_solo_los_vacios(): void
    {
        $lu = TipoProducto::where('codigo', 'LU')->value('id');
        $mk = function (string $codigoFab, string $nombre, ?string $fab, ?string $k) use ($lu) {
            $p = Producto::create(['codigo' => 'KY-LU0000-' . random_int(1000, 9999), 'nombre' => $nombre, 'nombre_fabrica' => $fab, 'nombre_kyrios' => $k,
                'categoria_id' => $this->categoria->id, 'tipo_producto_id' => $lu, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1, 'estado' => 'activo']);
            ProductoVariante::create(['producto_id' => $p->id, 'sku' => $p->codigo, 'codigo_kyrios' => $p->codigo, 'codigo_fabricante' => $codigoFab, 'estado' => 'activo']);

            return $p;
        };
        $vacio = $mk('FAB-1', 'FAB-1', null, null);          // nombre es un código y faltan ambos
        $escrito = $mk('FAB-2', 'Mi nombre', 'Escrito', 'Yo'); // no se pisa

        $xlsx = $this->excelDePrueba([
            ['FAB-1', null, null, null, null, 'Luminaria', null, null, null, null, 'Multidir', 'Multi'],
            ['FAB-2', null, null, null, null, 'Luminaria', null, null, null, null, 'Otro fábrica', 'Otro K'],
        ]);

        $this->artisan('productos:corregir-nombres', ['archivo' => $xlsx, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($vacio->fresh()->nombre_kyrios);

        $this->artisan('productos:corregir-nombres', ['archivo' => $xlsx])->assertSuccessful();
        $this->artisan('productos:corregir-nombres', ['archivo' => $xlsx])->assertSuccessful();

        $vacio->refresh();
        $this->assertSame('Multi', $vacio->nombre_kyrios);
        $this->assertSame('Multidir', $vacio->nombre_fabrica);
        $this->assertSame('Multi', $vacio->nombre, 'el nombre que era un código pasa al nombre K');
        $escrito->refresh();
        $this->assertSame(['Escrito', 'Yo', 'Mi nombre'], [$escrito->nombre_fabrica, $escrito->nombre_kyrios, $escrito->nombre]);
        @unlink($xlsx);
    }

    /** Crea un .xlsx mínimo con la hoja «Base de Productos» (cabecera en la fila 4, datos desde la 5). */
    private function excelDePrueba(array $filas): string
    {
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Base de Productos');
        $hoja->fromArray(['Código', 'Código KYRIOS', 'STOCK', 'Observaciones', 'IMAGEN', 'Tipo de producto'], null, 'A4');
        $hoja->fromArray($filas, null, 'A5');
        $ruta = tempnam(sys_get_temp_dir(), 'prd') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);

        return $ruta;
    }
}
