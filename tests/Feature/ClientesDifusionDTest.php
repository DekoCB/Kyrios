<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Listas de difusión de WhatsApp: etiquetas múltiples por cliente, filtro, exportación e importación del Excel.
 */
class ClientesDifusionDTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    private function cliente(string $nombre, array $extra = []): Cliente
    {
        return Cliente::create(array_merge(['nombre' => $nombre, 'estado' => 'activo'], $extra));
    }

    // ── Modelo y filtro ────────────────────────────────────────────────────

    public function test_las_listas_se_guardan_como_arreglo_y_el_filtro_no_confunde_papa_con_mama(): void
    {
        $this->cliente('Ana', ['listas_difusion' => ['arquitecto', 'mama', 'mujer']]);
        $this->cliente('Luis', ['listas_difusion' => ['papa', 'hombre']]);
        $this->cliente('Sin lista');

        $this->assertSame(['arquitecto', 'mama', 'mujer'], Cliente::where('nombre', 'Ana')->first()->listas_difusion);
        $this->assertSame(['Ana'], Cliente::enListas(['mama'])->pluck('nombre')->all());
        $this->assertSame(['Luis'], Cliente::enListas(['papa'])->pluck('nombre')->all());
        $this->assertEqualsCanonicalizing(['Ana', 'Luis'], Cliente::enListas(['mama', 'papa'])->pluck('nombre')->all());
        $this->assertSame(3, Cliente::count(), 'una lista vacía o inválida no filtra nada');
        $this->assertSame(3, Cliente::enListas(['inventada'])->count());
    }

    public function test_etiquetas_legibles_en_el_orden_del_catalogo(): void
    {
        $c = $this->cliente('Ana', ['listas_difusion' => ['mujer', 'arquitecto', 'mama']]);

        $this->assertSame(['Arquitecto/a', 'Mamá', 'Mujer'], $c->etiquetas_difusion);
        $this->assertSame([], $this->cliente('Otro')->etiquetas_difusion);
    }

    public function test_celular_valido_acepta_formatos_comunes_y_rechaza_el_resto(): void
    {
        $valido = fn (?string $t) => $this->cliente('X' . uniqid(), ['telefono' => $t])->celularWhatsapp();

        $this->assertSame('999111222', $valido('999 111 222'));
        $this->assertSame('987654321', $valido('+51 987-654-321'));
        $this->assertSame('987654321', $valido('51987654321'));
        $this->assertSame('998328449', $valido('6830567 / 998328449'));
        $this->assertNull($valido('012345678'));
        $this->assertNull($valido('6830567'));
        $this->assertNull($valido('9876'));
        $this->assertNull($valido(null));
    }

    // ── Formularios ────────────────────────────────────────────────────────

    public function test_alta_y_edicion_guardan_las_listas_y_se_pueden_quitar_todas(): void
    {
        $this->como('Administración');

        $this->post(route('clientes.store'), ['nombre' => 'Ana', 'estado' => 'activo', 'listas_difusion' => ['', 'arquitecto', 'mama', 'mama']])
            ->assertSessionHasNoErrors();
        $c = Cliente::first();
        $this->assertSame(['arquitecto', 'mama'], $c->listas_difusion);

        $this->get(route('clientes.show', $c))->assertOk()->assertSee('Arquitecto/a')->assertSee('Mamá');
        $this->get(route('clientes.edit', $c))->assertOk()->assertSee('Listas de difusión (WhatsApp)');

        // Se desmarcan todas: el formulario envía solo el campo vacío
        $this->put(route('clientes.update', $c), ['nombre' => 'Ana', 'estado' => 'activo', 'listas_difusion' => ['']])->assertSessionHasNoErrors();
        $this->assertNull($c->fresh()->listas_difusion);
    }

    public function test_una_lista_inventada_se_rechaza(): void
    {
        $this->como('Administración');

        $this->post(route('clientes.store'), ['nombre' => 'Ana', 'estado' => 'activo', 'listas_difusion' => ['vip']])
            ->assertSessionHasErrors('listas_difusion.0');
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_alta_rapida_por_json_sin_listas_sigue_funcionando_y_no_borra_las_existentes(): void
    {
        $this->como('Administración');

        $this->postJson(route('clientes.store'), ['nombre' => 'Rápido', 'estado' => 'activo'])->assertOk()->assertJsonStructure(['id', 'nombre']);
        $this->assertNull(Cliente::first()->listas_difusion);

        $c = $this->cliente('Con lista', ['listas_difusion' => ['mujer']]);
        $this->putJson(route('clientes.update', $c), ['nombre' => 'Con lista', 'estado' => 'activo', 'telefono' => '999111222'])->assertOk();
        $this->assertSame(['mujer'], $c->fresh()->listas_difusion);
    }

    // ── Listado y exportación ──────────────────────────────────────────────

    public function test_el_listado_filtra_por_lista_y_muestra_los_contadores(): void
    {
        $this->como('Administración');
        $this->cliente('Ana Arquitecta', ['listas_difusion' => ['arquitecto', 'mujer']]);
        $this->cliente('Beto Hombre', ['listas_difusion' => ['hombre']]);
        $this->cliente('Carla Nada');

        $this->get(route('clientes.index', ['lista' => 'arquitecto']))->assertOk()
            ->assertSee('Ana Arquitecta')->assertDontSee('Beto Hombre')->assertDontSee('Carla Nada');
        $this->get(route('clientes.index'))->assertOk()
            ->assertSee('Exportar lista de difusión')->assertSee('Arquitecto/a')->assertSee('Clientes finales');

        // El filtro se conserva en la exportación general
        $csv = $this->get(route('clientes.exportar', ['lista' => 'hombre']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Beto Hombre', $csv);
        $this->assertStringNotContainsString('Ana Arquitecta', $csv);
        $this->assertStringContainsString('Listas de difusión', $csv);
    }

    public function test_el_resumen_cuenta_incluidos_sin_celular_y_repetidos(): void
    {
        $this->como('Administración');
        $this->cliente('Con celular', ['telefono' => '999 111 222', 'listas_difusion' => ['mama']]);
        $this->cliente('Repetida', ['telefono' => '+51 999111222', 'listas_difusion' => ['mama']]);
        $this->cliente('Fijo solo', ['telefono' => '6830567', 'listas_difusion' => ['mama']]);
        $this->cliente('Sin teléfono', ['listas_difusion' => ['mama']]);
        $this->cliente('Otra lista', ['telefono' => '988777666', 'listas_difusion' => ['papa']]);

        $this->getJson(route('clientes.difusion.resumen', ['listas' => ['mama']]))->assertOk()
            ->assertJson(['incluidos' => 1, 'sin_celular' => 2, 'repetidos' => 1]);
        $this->getJson(route('clientes.difusion.resumen', ['listas' => ['mama', 'papa']]))
            ->assertJson(['incluidos' => 2, 'sin_celular' => 2]);
        $this->getJson(route('clientes.difusion.resumen'))->assertJson(['incluidos' => 0, 'sin_celular' => 0]);
    }

    public function test_la_exportacion_para_whatsapp_trae_solo_celulares_validos_en_formato_internacional(): void
    {
        $this->como('Administración');
        $this->cliente('Ana López', ['telefono' => '999 111 222', 'listas_difusion' => ['arquitecto', 'mujer']]);
        $this->cliente('Sin celular', ['telefono' => '6830567', 'listas_difusion' => ['arquitecto']]);

        $r = $this->get(route('clientes.difusion.exportar', ['listas' => ['arquitecto']]))->assertOk();
        $csv = $r->streamedContent();

        $this->assertStringContainsString('Nombre;Celular;Listas', $csv);
        $this->assertStringContainsString('"Ana López";+51999111222;"Arquitecto/a, Mujer"', $csv);
        $this->assertStringNotContainsString('Sin celular', $csv);
        $this->assertSame('1', $r->headers->get('X-Contactos-Incluidos'));
        $this->assertSame('1', $r->headers->get('X-Contactos-Sin-Celular'));
    }

    public function test_exportar_sin_elegir_lista_avisa_y_los_roles_sin_acceso_no_entran(): void
    {
        $this->como('Administración');
        $this->get(route('clientes.difusion.exportar'))->assertRedirect(route('clientes.index'))->assertSessionHas('error');

        $this->como('Logística');
        $this->get(route('clientes.difusion.exportar', ['listas' => ['mama']]))->assertForbidden();
        $this->getJson(route('clientes.difusion.resumen', ['listas' => ['mama']]))->assertForbidden();
    }

    // ── Importador del Excel ───────────────────────────────────────────────

    /** Libro con la hoja «LISTA DE DIFUSION» como la del Excel real (encabezados con tildes y puntos). */
    private function libro(array $filas): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('LISTA DE DIFUSION');
        $hoja->fromArray(['NOMBRES', 'ARQUITECTOS', 'INGENIEROS', 'DIS. Y DECORADORES', 'CLIENTES FINALES', 'PAISAJISTAS', 'MAMÁS', 'PAPÁS', 'MUJER', 'HOMBRE', 'Atención'], null, 'A1');
        $hoja->fromArray($filas, null, 'A2');
        $ruta = tempnam(sys_get_temp_dir(), 'dif') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $ruta;
    }

    /** Corre el comando y devuelve su salida; falla si no termina bien. */
    private function importar(string $ruta, bool $seco = false): string
    {
        $codigo = Artisan::call('clientes:importar-difusion', ['archivo' => $ruta] + ($seco ? ['--dry-run' => true] : []));
        $salida = Artisan::output();
        $this->assertSame(0, $codigo, $salida);

        return $salida;
    }

    private function filas(): array
    {
        //                 nombre            arq  ing  dis  cf   pai  mama papa mujer hombre atención
        return [
            ['ANA PÉREZ',       'X', null, null, null, null, 'X', null, 'X', null, 'x'],   // existe como «Ana Perez»
            ['Luis Gómez',      null, null, null, 'X', null, null, null, null, 'X', 'x'],  // ambiguo: dos clientes iguales
            ['MARIA NUEVA',     null, null, 'X', null, null, 'X', null, 'X', null, 'x'],   // se crea
            ['maria nueva',     null, 'X', null, null, null, null, null, null, null, 'x'],   // repetida: se suman sus listas
            ['',                'X', null, null, null, null, null, null, null, null, 'x'],   // sin nombre
            ['SIN MARCAS',      null, null, null, null, null, null, null, null, null, 'x'], // sin ninguna lista
        ];
    }

    public function test_el_importador_empareja_crea_y_reporta_ambiguos(): void
    {
        $ana = $this->cliente('Ana Perez', ['listas_difusion' => ['hombre']]);
        $this->cliente('Luis Gomez');
        $this->cliente('LUIS GÓMEZ');

        $salida = $this->importar($this->libro($this->filas()));
        $this->assertStringContainsString('emparejados 1', $salida);
        $this->assertStringContainsString('creados 1', $salida);
        $this->assertStringContainsString('ambiguos 1', $salida);

        // Existente: se suman las listas sin perder la que tenía
        $this->assertEqualsCanonicalizing(['arquitecto', 'mama', 'mujer', 'hombre'], $ana->fresh()->listas_difusion);

        // Nuevo: prospecto sin documento, nombre legible y listas de las dos filas, «DIS. Y DECORADORES» = dos listas
        $nuevo = Cliente::where('nombre', 'Maria Nueva')->first();
        $this->assertNotNull($nuevo);
        $this->assertTrue($nuevo->es_prospecto);
        $this->assertEqualsCanonicalizing(['disenador', 'decorador', 'mama', 'mujer', 'ingeniero'], $nuevo->listas_difusion);

        // Ambiguo: ninguno de los dos recibe etiquetas
        $this->assertSame(0, Cliente::whereIn('nombre', ['Luis Gomez', 'LUIS GÓMEZ'])->whereNotNull('listas_difusion')->count());
        $this->assertSame(0, Cliente::where('nombre', 'like', 'SIN MARCAS')->count());
    }

    public function test_el_importador_es_repetible_y_el_dry_run_no_escribe(): void
    {
        $ruta = $this->libro($this->filas());

        $this->assertStringContainsString('[simulación]', $this->importar($ruta, true));
        $this->assertSame(0, Cliente::count(), 'la simulación no crea nada');

        $this->assertStringContainsString('creados 3', $this->importar($ruta));
        $total = Cliente::count();

        $segunda = $this->importar($ruta);
        $this->assertStringContainsString('creados 0', $segunda);
        $this->assertStringContainsString('actualizados 0', $segunda);
        $this->assertSame($total, Cliente::count(), 'repetir la carga no duplica clientes');
    }

    public function test_el_importador_avisa_si_falta_el_archivo_o_la_hoja(): void
    {
        $this->artisan('clientes:importar-difusion', ['archivo' => '/no/existe.xlsx'])->assertFailed();

        $libro = new Spreadsheet();
        $libro->getActiveSheet()->setTitle('OTRA');
        $ruta = tempnam(sys_get_temp_dir(), 'dif') . '.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->artisan('clientes:importar-difusion', ['archivo' => $ruta])->expectsOutputToContain('LISTA DE DIFUSION')->assertFailed();
    }
}
