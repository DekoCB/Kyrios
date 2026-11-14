<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CronogramaEvento;
use App\Models\Herramienta;
use App\Models\HerramientaSalida;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\AlertaService;
use App\Services\CotizacionService;
use App\Services\EscanerService;
use App\Services\HomologacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Fotos imprimibles, ficha técnica, herramientas de instalación, cronograma ligado a ventas,
 * PDF de cotización y venta, y plantilla de homologación.
 */
class FotosHerramientasHomologacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private User $operaciones;
    private Almacen $almacen;
    private Producto $producto;
    private ProductoVariante $variante;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->operaciones = $this->usuario('Operaciones');

        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Downlight Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo',
            'voltaje' => '220V', 'material_1' => 'Aluminio', 'ip' => '44']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'potencia_w' => 12, 'temperatura_color' => 3000, 'tamano' => 'grande', 'diametro' => 120, 'acabado_1' => 'blanco(a)', 'estado' => 'activo']);
        ProductoPrecio::create(['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'tipo_precio' => 'venta_regular',
            'precio' => 118, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Pérez', 'direccion' => 'Av. Lima 123', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function cotizacion()
    {
        return app(CotizacionService::class)->crear([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7,
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]]);
    }

    private function venta(): Venta
    {
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->variante->id, 'cantidad' => 10]], $this->admin->id);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $this->almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $c = $this->cotizacion();
        $this->actingAs($this->admin)->post(route('cotizaciones.convertir', $c), ['tipo_comprobante' => 'nota_venta', 'condicion' => 'contado', 'metodo_pago' => 'efectivo'])->assertSessionHasNoErrors();

        return Venta::firstOrFail();
    }

    // ── 1 y 2. Fotos y detalle del producto ───────────────────────────────

    public function test_detalle_del_producto_muestra_las_especificaciones_y_el_acceso_a_fotos(): void
    {
        $r = $this->actingAs($this->admin)->get(route('inventario.productos.show', $this->producto))->assertOk();
        $r->assertSee('Especificaciones técnicas')->assertSee('Aluminio')->assertSee('220V')
            ->assertSee('Ver todas las características')->assertSee('3000 K')->assertSee('12 W')->assertSee('Editar / Imprimir Fotos');
    }

    public function test_pagina_de_fotos_lista_las_imagenes_y_permite_imprimir(): void
    {
        ProductoImagen::create(['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'ruta' => 'productos/1/a.jpg', 'orden' => 1, 'es_principal' => true]);

        $this->actingAs($this->logistica)->get(route('inventario.productos.fotos', $this->producto))->assertOk()
            ->assertSee('Imprimir fotos')->assertSee('productos/1/a.jpg', false)->assertSee('Copias por foto');
    }

    public function test_operaciones_no_edita_fotos(): void
    {
        $this->actingAs($this->operaciones)->get(route('inventario.productos.fotos', $this->producto))->assertForbidden();
    }

    // ── 3. Herramientas ───────────────────────────────────────────────────

    private function herramienta(int $n = 5): Herramienta
    {
        $this->actingAs($this->logistica)->post(route('herramientas.store'), ['nombre' => 'Taladro', 'cantidad_total' => $n])->assertSessionHasNoErrors();

        return Herramienta::firstOrFail();
    }

    public function test_alta_de_herramienta_con_codigo_correlativo(): void
    {
        $h = $this->herramienta();
        $this->assertSame('HE-0001', $h->codigo);
        $this->assertSame(5, $h->disponible);
    }

    public function test_salida_descuenta_disponibles_y_no_permite_pasarse(): void
    {
        $h = $this->herramienta(3);
        $this->actingAs($this->logistica)->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $h->fresh()->disponible);

        $this->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 2])
            ->assertSessionHas('error');
        $this->assertSame(1, HerramientaSalida::count());
    }

    public function test_retorno_con_danadas_y_perdidas_ajusta_el_inventario(): void
    {
        $h = $this->herramienta(5);
        $this->actingAs($this->logistica)->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 4]);
        $s = HerramientaSalida::firstOrFail();

        $this->post(route('herramientas.salidas.retorno', $s), ['cantidad_buena' => 2, 'cantidad_danada' => 1, 'cantidad_perdida' => 1])->assertSessionHas('success');

        $h->refresh();
        $this->assertSame(4, $h->cantidad_total, 'la perdida sale del inventario');
        $this->assertSame(1, $h->cantidad_danada);
        $this->assertSame(3, $h->disponible);
        $this->assertSame('devuelta', $s->fresh()->estado);

        $this->post(route('herramientas.reparar', $h), ['cantidad' => 1]);
        $this->assertSame(4, $h->fresh()->disponible);
    }

    public function test_retorno_exige_que_las_cantidades_sumen_y_no_se_repite(): void
    {
        $h = $this->herramienta(5);
        $this->actingAs($this->logistica)->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 3]);
        $s = HerramientaSalida::firstOrFail();

        $this->post(route('herramientas.salidas.retorno', $s), ['cantidad_buena' => 1, 'cantidad_danada' => 0, 'cantidad_perdida' => 0])->assertSessionHas('error');
        $this->assertSame('en_uso', $s->fresh()->estado);

        $this->post(route('herramientas.salidas.retorno', $s), ['cantidad_buena' => 3, 'cantidad_danada' => 0, 'cantidad_perdida' => 0])->assertSessionHas('success');
        $this->post(route('herramientas.salidas.retorno', $s), ['cantidad_buena' => 3, 'cantidad_danada' => 0, 'cantidad_perdida' => 0])->assertSessionHas('error');
    }

    public function test_operaciones_solo_ve_lo_suyo_y_no_registra(): void
    {
        $h = $this->herramienta(5);
        $otro = $this->usuario('Operaciones');
        $this->actingAs($this->logistica)->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 1]);
        $this->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $otro->id, 'cantidad' => 1]);

        $this->actingAs($this->operaciones)->get(route('herramientas.salidas'))->assertOk()->assertSee($this->operaciones->name)->assertDontSee($otro->name);
        $this->get(route('herramientas.salidas.create'))->assertForbidden();
        $this->post(route('herramientas.salidas.store'), ['herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 1])->assertForbidden();
        $this->post(route('herramientas.salidas.retorno', HerramientaSalida::first()), ['cantidad_buena' => 1, 'cantidad_danada' => 0, 'cantidad_perdida' => 0])->assertForbidden();
    }

    public function test_cliente_no_entra_a_herramientas(): void
    {
        $this->actingAs($this->usuario('Cliente'))->get(route('herramientas.index'))->assertForbidden();
    }

    public function test_aviso_de_herramienta_por_devolver(): void
    {
        $h = $this->herramienta(2);
        $this->actingAs($this->logistica)->post(route('herramientas.salidas.store'), [
            'herramienta_id' => $h->id, 'tecnico_id' => $this->operaciones->id, 'cantidad' => 1, 'fecha_devolucion_esperada' => today()->toDateString(),
        ]);

        $r = app(AlertaService::class)->revisarFechas();

        $this->assertGreaterThan(0, $r['avisos']);
        $this->assertSame(1, $this->operaciones->unreadNotifications()->where('data->clave', 'like', 'herr:%')->count());
    }

    // ── 4. Cronograma y ventas ────────────────────────────────────────────

    public function test_ventas_sin_instalacion_aparecen_para_programar(): void
    {
        $venta = $this->venta();

        $this->actingAs($this->logistica)->get(route('cronograma.index'))->assertOk()->assertSee('Ventas por programar')->assertSee($venta->codigo);
        $this->actingAs($this->operaciones)->get(route('cronograma.index'))->assertOk()->assertDontSee('Ventas por programar');
    }

    public function test_programar_desde_la_venta_precarga_y_la_saca_de_la_lista(): void
    {
        $venta = $this->venta();

        $this->actingAs($this->logistica)->get(route('cronograma.create', ['venta_id' => $venta->id]))->assertOk()
            ->assertSee('Instalación ' . $venta->codigo, false)->assertSee('Av. Lima 123');

        $this->post(route('cronograma.store'), ['tipo' => 'instalacion', 'titulo' => 'Instalación X', 'fecha' => today()->addDays(10)->toDateString(), 'venta_id' => $venta->id, 'cliente_id' => $this->cliente->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($venta->id, CronogramaEvento::firstOrFail()->venta_id);
        $this->get(route('cronograma.index', ['mes' => today()->addDays(10)->format('Y-m')]))->assertDontSee('data-ventas-por-programar', false);
    }

    public function test_instalacion_cancelada_devuelve_la_venta_a_la_lista(): void
    {
        $venta = $this->venta();
        $this->actingAs($this->logistica)->post(route('cronograma.store'), ['tipo' => 'instalacion', 'titulo' => 'Instalación cancelable', 'fecha' => today()->addDay()->toDateString(), 'venta_id' => $venta->id]);
        $this->post(route('cronograma.cancelar', CronogramaEvento::first()));

        $this->get(route('cronograma.index'))->assertSee('data-ventas-por-programar', false);
    }

    public function test_vista_calendario_y_lista(): void
    {
        $this->actingAs($this->logistica)->post(route('cronograma.store'), ['tipo' => 'entrega', 'titulo' => 'Entrega calendario', 'fecha' => today()->toDateString()]);

        $this->get(route('cronograma.index', ['vista' => 'calendario']))->assertOk()->assertSee('data-calendario', false)->assertSee('Entrega calendario');
        $this->get(route('cronograma.index', ['vista' => 'lista']))->assertOk()->assertDontSee('data-calendario', false)->assertSee('Entrega calendario');
        $this->get(route('cronograma.index'))->assertOk()->assertDontSee('data-calendario', false);
    }

    // ── 5 y 6. PDF de cotización y venta ──────────────────────────────────

    public function test_ficha_tecnica_de_la_cotizacion_se_descarga_en_pdf(): void
    {
        $c = $this->cotizacion();

        $r = $this->actingAs($this->admin)->get(route('cotizaciones.ficha-tecnica', $c))->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->actingAs($this->operaciones)->get(route('cotizaciones.ficha-tecnica', $c))->assertForbidden();
    }

    public function test_pdf_de_proforma_y_presupuesto_de_la_venta(): void
    {
        $venta = $this->venta();

        $r = $this->actingAs($this->admin)->get(route('ventas.resumen-pdf', $venta))->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->actingAs($this->operaciones)->get(route('ventas.resumen-pdf', $venta))->assertForbidden();
    }

    public function test_la_proforma_sale_en_dolares_con_el_tipo_de_cambio_del_dia_de_la_venta(): void
    {
        \App\Models\TipoCambio::create(['fecha' => today()->subDays(10), 'compra' => 3.6, 'venta' => 4.0]);
        \App\Models\TipoCambio::create(['fecha' => today()->addDays(5), 'compra' => 3.9, 'venta' => 9.0]); // posterior: no cuenta
        $venta = $this->venta();
        $venta->update(['fecha' => today()]);

        $usd = $this->actingAs($this->admin)->get(route('ventas.resumen-pdf', $venta))->assertOk();
        $this->assertStringContainsString('-usd.pdf', $usd->headers->get('Content-Disposition'));
        $pen = $this->get(route('ventas.resumen-pdf', [$venta, 'moneda' => 'PEN']))->assertOk();
        $this->assertStringContainsString('-pen.pdf', $pen->headers->get('Content-Disposition'));

        $this->assertSame(4.0, \App\Models\TipoCambio::ventaDelDia(today()));
        $this->assertSame(9.0, \App\Models\TipoCambio::ventaDelDia(today()->addDays(6)));
    }

    public function test_la_proforma_lleva_numero_oficial_correlativo_que_no_cambia(): void
    {
        $venta = $this->venta();
        $this->actingAs($this->admin)->get(route('ventas.resumen-pdf', $venta))->assertOk();
        $anio = now()->format('y');
        $this->assertSame("IL-00001/{$anio}", $venta->fresh()->proforma_numero);

        $this->get(route('ventas.resumen-pdf', [$venta, 'moneda' => 'PEN']))->assertOk();
        $this->assertSame("IL-00001/{$anio}", $venta->fresh()->proforma_numero, 'el número no cambia al volver a emitirla');

        $otra = $venta->replicate(['codigo', 'proforma_numero', 'proforma_correlativo', 'serie_comprobante_id', 'correlativo']);
        $otra->save();
        $this->get(route('ventas.resumen-pdf', $otra))->assertOk();
        $this->assertSame("IL-00002/{$anio}", $otra->fresh()->proforma_numero);

        config(['kyrios.proforma.inicio' => 63080]);
        $tercera = $venta->replicate(['codigo', 'proforma_numero', 'proforma_correlativo', 'serie_comprobante_id', 'correlativo']);
        $tercera->save();
        $this->get(route('ventas.resumen-pdf', $tercera))->assertOk();
        $this->assertSame("IL-63080/{$anio}", $tercera->fresh()->proforma_numero, 'se puede continuar la numeración que ya usaban');
    }

    // ── 7. Homologación ───────────────────────────────────────────────────

    private function excel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        foreach (array_values(HomologacionService::COLUMNAS) as $i => $t) {
            $hoja->setCellValue([$i + 1, 1], $t);
        }
        foreach ($filas as $r => $fila) {
            foreach (array_values($fila) as $c => $valor) {
                $hoja->setCellValue([$c + 1, $r + 2], $valor);
            }
        }
        $ruta = tempnam(sys_get_temp_dir(), 'h') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'plantilla.xlsx', null, null, true);
    }

    public function test_descarga_de_la_plantilla_en_pdf_y_excel(): void
    {
        $this->actingAs($this->admin)->get(route('cotizaciones.homologacion'))->assertOk()->assertSee('Plantilla de homologación');
        $pdf = $this->get(route('cotizaciones.homologacion.plantilla-pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get(route('cotizaciones.homologacion.plantilla-excel'))->assertOk()->assertDownload('plantilla-homologacion.xlsx');
    }

    public function test_la_homologacion_encuentra_la_mejor_coincidencia(): void
    {
        $filas = [['Downlight empotrable', '', '12', '3000', '20', 'blanco', '', '5', '']];

        $this->actingAs($this->admin)->post(route('cotizaciones.homologacion.analizar'), [
            'archivo' => $this->excel($filas), 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id,
        ])->assertRedirect(route('cotizaciones.homologacion.resultado'));

        $this->get(route('cotizaciones.homologacion.resultado'))->assertOk()
            ->assertSee('Downlight Ring')->assertSee('KY-LU2501-0001')->assertSee('118.00', false);
    }

    public function test_genera_la_cotizacion_con_lo_elegido(): void
    {
        $this->actingAs($this->admin)->post(route('cotizaciones.homologacion.generar'), [
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7,
            'lineas' => [
                ['elegido' => $this->producto->id . ':' . $this->variante->id, 'cantidad' => 3, 'precio' => 118],
                ['elegido' => '', 'cantidad' => 1, 'precio' => 5],
            ],
        ])->assertSessionHasNoErrors();

        $c = \App\Models\Cotizacion::firstOrFail();
        $this->assertSame(1, $c->detalles()->count());
        $this->assertSame(3, $c->detalles->first()->cantidad);
        $this->assertEquals(354.00, (float) $c->total);
    }

    public function test_no_genera_sin_nada_elegido_ni_sin_precio(): void
    {
        $base = ['cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7];

        $this->actingAs($this->admin)->post(route('cotizaciones.homologacion.generar'), $base + ['lineas' => [['elegido' => '', 'cantidad' => 1, 'precio' => 1]]])->assertSessionHas('error');
        $this->post(route('cotizaciones.homologacion.generar'), $base + ['lineas' => [['elegido' => $this->producto->id . ':' . $this->variante->id, 'cantidad' => 1, 'precio' => '']]])->assertSessionHas('error');
        $this->assertSame(0, \App\Models\Cotizacion::count());
    }

    public function test_archivo_que_no_es_la_plantilla_da_error_claro(): void
    {
        $libro = new Spreadsheet();
        $libro->getActiveSheet()->setCellValue('A1', 'otra cosa');
        $ruta = tempnam(sys_get_temp_dir(), 'h') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->admin)->post(route('cotizaciones.homologacion.analizar'), [
            'archivo' => new UploadedFile($ruta, 'x.xlsx', null, null, true), 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id,
        ])->assertSessionHas('error');
    }

    public function test_homologacion_es_solo_de_administracion(): void
    {
        foreach (['Logística', 'Contador', 'Operaciones'] as $rol) {
            $this->actingAs($this->usuario($rol))->get(route('cotizaciones.homologacion'))->assertForbidden();
        }
    }
}
