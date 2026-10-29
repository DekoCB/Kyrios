<?php

namespace Tests\Feature;

use App\Models\Catalogo\Marca;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Importador del Excel de Kyrios (productos:importar-kyrios) con un libro de prueba que reproduce
 * la estructura del original: hoja base, matrices de estilo/instalación/proyecto, packing list,
 * observaciones y la hoja «Dañados» (nivel de daño por color de celda).
 */
class ImportarProductosKyriosTest extends TestCase
{
    use RefreshDatabase;

    private string $archivo;
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->archivo = tempnam(sys_get_temp_dir(), 'kyr') . '.xlsx';
        $this->png = tempnam(sys_get_temp_dir(), 'png') . '.png';

        $im = imagecreatetruecolor(8, 8);
        imagepng($im, $this->png);
        imagedestroy($im);

        $this->crearLibro();
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);
        @unlink($this->png);
        parent::tearDown();
    }

    /** Escribe valores por índice de columna (0 = A) en una fila. */
    private function fila($hoja, int $fila, array $valores): void
    {
        foreach ($valores as $idx => $valor) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($idx + 1) . $fila, $valor);
        }
    }

    private function crearLibro(): void
    {
        $libro = new Spreadsheet();

        // ── Base de Productos: cabecera en la fila 4, datos desde la 5 ──
        $base = $libro->getActiveSheet()->setTitle('Base de Productos');
        $this->fila($base, 4, [0 => 'Código', 1 => 'Código KYRIOS', 5 => 'Tipo de producto']);
        $this->fila($base, 5, [0 => 'AAA-111', 1 => 'KLU01-00001', 5 => 'Luminaria', 6 => 'LED', 7 => 'Interior', 8 => 'de pie',
            10 => 'Oxygen', 11 => 'Ring', 12 => 'gris', 14 => 'grande', 15 => 300, 17 => 1800, 23 => 'G9', 24 => 'x3', 28 => 75,
            34 => 20, 39 => 'Si', 41 => 'LEDS-C4 (1)', 42 => 'GROK (G)', 43 => 'ESPAÑA']);
        $this->fila($base, 6, [0 => 'AAA-222', 1 => 'KLU01-00002', 5 => 'Luminaria', 6 => 'LED', 7 => 'Interior', 8 => 'de pie',
            10 => 'Oxygen', 11 => 'Ring', 12 => 'Blanco(a)', 14 => 'mediano(a)', 15 => 200, 41 => 'LEDS-C4 (1)', 42 => 'GROK (G)', 43 => 'ESPAÑA']);
        $this->fila($base, 7, [0 => 'BBB-333', 5 => 'Lámpara', 6 => 'LED', 10 => 'Bulb', 41 => 'PHILIPS (29)', 43 => 'CHINA']);
        $this->fila($base, 8, [0 => 'CCC-444', 8 => 'colgante', 10 => 'Sin datos']); // sin tipo ni marca

        // Imagen incrustada en la columna IMAGEN (E) de la primera fila
        $dibujo = new Drawing();
        $dibujo->setPath($this->png)->setCoordinates('E5')->setWorksheet($base);

        // ── Matrices (cabecera en la fila 4; marcas «x» por fila, alineadas con la base) ──
        foreach ([
            'Estilo'              => [2 => 'CLASICO', 4 => 'MODERNO', 5 => 'CONTENPORANEO'],
            'Tipo de instalación' => [2 => 'COLGANTE', 7 => 'PIE'],
            'Tipo de proyecto'    => [2 => 'RESIDENCIAL', 3 => 'COMERCIAL'],
        ] as $titulo => $columnas) {
            $h = $libro->createSheet()->setTitle($titulo);
            $this->fila($h, 4, [0 => 'Código', 1 => 'Código KYRIOS'] + $columnas);
            foreach ([5 => 'AAA-111', 6 => 'AAA-222', 7 => 'BBB-333', 8 => 'CCC-444'] as $f => $codigo) {
                $this->fila($h, $f, [0 => $codigo]);
            }
        }
        $libro->getSheetByName('Estilo')->setCellValue('E5', 'x');             // AAA-111: moderno
        $libro->getSheetByName('Estilo')->setCellValue('F5', 'x');             // y «contenporaneo» (typo del Excel → contemporaneo)
        $libro->getSheetByName('Tipo de instalación')->setCellValue('H5', 'X'); // AAA-111: pie
        $libro->getSheetByName('Tipo de proyecto')->setCellValue('C5', 'x');    // AAA-111: residencial

        // ── Packing list: precio de fábrica (I), costo (K) y precio de venta con IGV (P) ──
        $pack = $libro->createSheet()->setTitle('Packing list');
        $this->fila($pack, 4, [0 => 'Código']);
        $this->fila($pack, 5, [0 => 'AAA-111', 2 => 0.44, 4 => '12 unidades', 6 => 12, 8 => 46.55, 10 => 60.515, 15 => 129.675]);
        $this->fila($pack, 6, [0 => 'AAA-222', 8 => 29.2, 15 => 0]);   // precio de venta 0: no crea precio
        $this->fila($pack, 7, [0 => 'BBB-333']);
        $this->fila($pack, 8, [0 => 'CCC-444']);

        // ── OBSERVACIONES: estado en texto (fila 2 = cabecera, datos desde la 3) ──
        $obs = $libro->createSheet()->setTitle('OBSERVACIONES');
        $this->fila($obs, 2, [0 => 'ITEM', 2 => 'CODIGO', 4 => 'ESTADO', 5 => 'OBSERVACION']);
        $this->fila($obs, 3, [0 => 1, 2 => 'BBB-333', 4 => 'GRAVE', 5 => 'Base partida']);

        // ── Dañados: el nivel está en el color de la celda del código ──
        $dan = $libro->createSheet()->setTitle('Dañados');
        $this->fila($dan, 1, [0 => 'Codigo', 1 => 'linea', 2 => 'Marca', 3 => 'cantidad', 4 => 'Observaciones']);
        $this->fila($dan, 2, [0 => 'AAA-111', 3 => 5, 4 => 'Golpe leve']);          // amarillo = leve
        $this->fila($dan, 3, [0 => 'ZZZ-999', 1 => 'OUTDOOR (O)', 2 => 'ACCORD (3)', 3 => 2]); // celeste = moderado, no está en la base
        $this->fila($dan, 4, [0 => 'AAA-222']);                                      // sin color: no cuenta
        foreach ([2 => 'FFFFFF00', 3 => 'FF00FFFF'] as $f => $argb) {
            $dan->getStyle("A{$f}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        }

        (new Xlsx($libro))->save($this->archivo);
    }

    private function importar(array $opciones = [])
    {
        return $this->artisan('productos:importar-kyrios', ['archivo' => $this->archivo] + $opciones);
    }

    public function test_el_archivo_inexistente_falla_sin_tocar_nada(): void
    {
        $this->artisan('productos:importar-kyrios', ['archivo' => 'no-existe.xlsx'])->assertFailed();
        $this->assertSame(0, Producto::count());
    }

    public function test_dry_run_procesa_todo_y_no_guarda_nada(): void
    {
        $this->importar(['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, ProductoVariante::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_agrupa_las_filas_del_mismo_modelo_en_un_producto_con_variantes(): void
    {
        $this->importar()->assertSuccessful();

        $oxygen = Producto::where('nombre_fabrica', 'Oxygen')->first();
        $this->assertNotNull($oxygen);
        $this->assertSame('Ring', $oxygen->nombre, 'el nombre es el «Nombre K»');
        $this->assertSame(2, $oxygen->variantes()->count());
        $this->assertSame('LEDS-C4', $oxygen->marca->nombre);
        $this->assertSame('GROK', $oxygen->linea);
        $this->assertSame('de pie', $oxygen->tipoLuminaria->nombre);
    }

    public function test_genera_los_codigos_del_pdf_y_deja_el_del_excel_como_segundo_codigo(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'AAA-111')->first();
        $this->assertSame('KY-LU2501-0001', $v->codigo_kyrios);
        $this->assertSame('KY-LU2501-0001', $v->sku);
        $this->assertSame('KLU01-00001', $v->codigo_excel);

        // Segunda variante del mismo prefijo: correlativo siguiente
        $this->assertSame('KY-LU2501-0002', ProductoVariante::where('codigo_fabricante', 'AAA-222')->value('codigo_kyrios'));
        // Caso 2 (lámpara): luminaria 00 y marca PHILIPS (29)
        $this->assertSame('KY-LA0029-0001', ProductoVariante::where('codigo_fabricante', 'BBB-333')->value('codigo_kyrios'));
        // El producto toma el código de su primera variante
        $this->assertSame('KY-LU2501-0001', Producto::where('nombre_fabrica', 'Oxygen')->value('codigo'));
    }

    public function test_las_filas_sin_tipo_ni_marca_usan_los_valores_por_defecto(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'CCC-444')->first();
        $this->assertSame('KY-ST0000-0001', $v->codigo_kyrios, 'Sin tipo (luminaria 00) + SIN MARCA (00)');
        $this->assertSame('00', Marca::where('nombre', 'SIN MARCA')->value('codigo'));
        $this->assertSame('Sin tipo', $v->producto->tipoProducto->nombre);
    }

    public function test_normaliza_los_atributos_de_la_variante(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'AAA-111')->first();
        $this->assertSame('gris', $v->acabado_1);
        $this->assertSame('grande', $v->tamano);
        $this->assertEquals(300.0, (float) $v->diametro);
        $this->assertEquals(1800.0, (float) $v->alto);
        $this->assertSame('G9', $v->socket);
        $this->assertSame('x3', $v->num_lamparas);
        $this->assertEquals(75.0, (float) $v->potencia_w);
        // Atributos que diferencian la variante, tomados de su propia fila
        $this->assertSame('LED', $v->tipo_lampara);
        $this->assertSame('20', $v->ip);

        $this->assertSame('blanco(a)', ProductoVariante::where('codigo_fabricante', 'AAA-222')->value('acabado_1'), 'acabados en minúsculas');

        $p = Producto::where('nombre_fabrica', 'Oxygen')->first();
        $this->assertSame('20', $p->ip);
        $this->assertTrue($p->regulable);
    }

    public function test_guarda_estilo_instalacion_y_proyecto_con_identificadores_sin_tildes(): void
    {
        $this->importar()->assertSuccessful();

        $p = Producto::where('nombre_fabrica', 'Oxygen')->first();
        $this->assertEqualsCanonicalizing(['moderno', 'contemporaneo'], $p->estilos, 'el typo «CONTENPORANEO» del Excel se corrige');
        $this->assertSame(['pie'], $p->tipos_instalacion);
        $this->assertSame(['residencial'], $p->tipos_proyecto);
    }

    public function test_el_nivel_de_dano_sale_del_color_de_la_celda_y_de_observaciones(): void
    {
        $this->importar()->assertSuccessful();

        $leve = ProductoVariante::where('codigo_fabricante', 'AAA-111')->first();
        $this->assertSame('leve', $leve->estado_dano);
        $this->assertStringContainsString('Golpe leve', $leve->observacion_dano);

        $this->assertSame('ninguno', ProductoVariante::where('codigo_fabricante', 'AAA-222')->value('estado_dano'), 'sin color no hay daño');

        $grave = ProductoVariante::where('codigo_fabricante', 'BBB-333')->first();
        $this->assertSame('grave', $grave->estado_dano, 'la hoja OBSERVACIONES también marca el daño');
        $this->assertStringContainsString('Base partida', $grave->observacion_dano);
    }

    public function test_los_codigos_danados_que_no_estan_en_la_base_se_crean(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'ZZZ-999')->first();
        $this->assertNotNull($v);
        $this->assertSame('moderado', $v->estado_dano);
        $this->assertSame('KY-LU0003-0001', $v->codigo_kyrios, 'Luminaria sin tipo de luminaria (00) + ACCORD (3)');
        $this->assertSame('ZZZ-999', $v->producto->nombre);
        $this->assertSame('OUTDOOR', $v->producto->linea);
        $this->assertSame(5, ProductoVariante::count(), '4 del Excel + 1 de Dañados');
    }

    public function test_importa_precios_y_datos_de_embalaje(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'AAA-111')->first();
        $this->assertEquals(46.55, (float) $v->precio_fabrica);
        $this->assertEquals(0.44, (float) $v->peso);
        $this->assertSame('12 unidades', $v->embalado);
        $this->assertSame(12, $v->cantidad_por_caja);

        $precio = ProductoPrecio::where('variante_id', $v->id)->first();
        $this->assertNotNull($precio);
        // Los importes se guardan con 2 decimales (el Excel trae 129.675 y 60.515)
        $this->assertEqualsWithDelta(129.675, (float) $precio->precio, 0.01);
        $this->assertEqualsWithDelta(60.515, (float) $precio->precio_compra, 0.01);
        $this->assertTrue($precio->incluye_igv);

        $this->assertSame(1, ProductoPrecio::count(), 'precio de venta 0 no crea precio');
    }

    public function test_extrae_la_imagen_incrustada_y_la_deja_como_principal(): void
    {
        $this->importar()->assertSuccessful();

        $v = ProductoVariante::where('codigo_fabricante', 'AAA-111')->first();
        $img = ProductoImagen::where('variante_id', $v->id)->first();

        $this->assertNotNull($img);
        $this->assertSame(1, $img->orden);
        $this->assertTrue($img->es_principal);
        Storage::disk('public')->assertExists($img->ruta);
        $this->assertSame(1, ProductoImagen::count(), 'solo la fila con imagen');
    }

    public function test_sin_imagenes_no_las_extrae(): void
    {
        $this->importar(['--sin-imagenes' => true])->assertSuccessful();

        $this->assertSame(0, ProductoImagen::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_si_algo_falla_no_queda_nada_a_medias(): void
    {
        // Libro sin la hoja «Packing list»: el importador debe abortar y revertir
        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->archivo);
        $libro->removeSheetByIndex($libro->getIndex($libro->getSheetByName('Packing list')));
        (new Xlsx($libro))->save($this->archivo);

        $this->importar()->assertFailed();

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, ProductoVariante::count());
    }
}
