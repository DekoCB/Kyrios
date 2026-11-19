<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marketing\Campana;
use App\Models\Marketing\Competidor;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Interaccion;
use App\Models\Marketing\Medio;
use App\Models\Marketing\Metrica;
use App\Models\Marketing\Publicacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketingEstadisticas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Módulo de Marketing: acceso por rol, cuentas y métricas, publicaciones con aprobación, biblioteca, bandeja, competencia, campañas y reportes. */
class MarketingModuloTest extends TestCase
{
    use RefreshDatabase;

    private User $mkt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mkt = $this->usuario(Role::MARKETING);
        $this->actingAs($this->mkt);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function cuenta(array $extra = []): Cuenta
    {
        return Cuenta::create($extra + ['red' => 'instagram', 'nombre' => 'kyrios_lighting', 'usuario' => '@kyrios_lighting', 'estado' => 'conectada']);
    }

    private function publicacion(array $extra = []): Publicacion
    {
        return Publicacion::create($extra + ['titulo' => 'Lanzamiento Ring', 'red' => 'instagram', 'tipo' => 'publicacion', 'estado' => 'pendiente', 'creado_por' => $this->mkt->id]);
    }

    // ── Acceso por rol ────────────────────────────────────────────────────

    public function test_el_rol_marketing_existe_y_es_uno_de_los_roles_del_sistema(): void
    {
        $this->assertContains('Marketing', Role::TODOS);
        $this->assertContains('Marketing', Role::INTERNOS);
        $this->assertArrayHasKey('Marketing', Role::DESCRIPCIONES);
    }

    public function test_marketing_entra_a_su_panel_y_al_iniciar_sesion_va_ahi(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('marketing.dashboard'));
        $this->get(route('marketing.dashboard'))->assertOk()->assertSee('Panel de Marketing')->assertSee('Redes conectadas');
    }

    public function test_marketing_y_administracion_acceden_todos_los_demas_roles_no(): void
    {
        $rutas = [route('marketing.dashboard'), route('marketing.cuentas.index'), route('marketing.publicaciones.index'), route('marketing.publicaciones.calendario'),
            route('marketing.medios.index'), route('marketing.interacciones.index'), route('marketing.estadisticas.index'), route('marketing.competencia.index'),
            route('marketing.campanas.index'), route('marketing.reportes.index')];

        foreach ($rutas as $r) {
            $this->actingAs($this->mkt)->get($r)->assertOk();
            $this->actingAs($this->usuario(Role::ADMINISTRACION))->get($r)->assertOk();
            foreach ([Role::CONTADOR, Role::LOGISTICA, Role::OPERACIONES, Role::CLIENTE] as $rol) {
                $this->actingAs($this->usuario($rol))->get($r)->assertForbidden();
            }
        }
    }

    public function test_el_menu_de_marketing_muestra_su_modulo_y_no_el_de_otros(): void
    {
        $this->get(route('marketing.dashboard'))->assertSee('Bandeja de entrada')->assertSee('Biblioteca multimedia')->assertSee('Campañas publicitarias')
            ->assertDontSee('Cuentas por cobrar')->assertDontSee('Facturación')->assertDontSee('Caja chica');
    }

    public function test_marketing_solo_ve_clientes_y_catalogo(): void
    {
        $c = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Pérez', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 10, 'estado' => 'activo']);

        $this->get(route('clientes.index'))->assertOk()->assertSee('Juan Pérez')->assertDontSee('Nuevo cliente')->assertDontSee('Exportar');
        $this->get(route('clientes.show', $c))->assertOk()->assertDontSee('Editar')->assertDontSee('Eliminar');
        $this->get(route('inventario.productos.index'))->assertOk();
        $this->get(route('inventario.productos.show', $p))->assertOk();

        // Escribir o sacar datos personales: no
        $this->get(route('clientes.create'))->assertForbidden();
        $this->get(route('clientes.edit', $c))->assertForbidden();
        $this->post(route('clientes.store'), ['nombre' => 'X'])->assertForbidden();
        $this->delete(route('clientes.destroy', $c))->assertForbidden();
        $this->get(route('clientes.exportar'))->assertForbidden();
        $this->get(route('clientes.difusion.exportar'))->assertForbidden();
        $this->get(route('inventario.productos.create'))->assertForbidden();
        $this->get(route('inventario.productos.edit', $p))->assertForbidden();
        $this->delete(route('inventario.productos.destroy', $p))->assertForbidden();

        // Ventas, caja, compras, precios, movimientos: no
        foreach ([route('ventas.index'), route('compras.index'), route('precios.index'), route('inventario.movimientos.index'), route('facturacion.index'), route('cuentas-por-cobrar.index')] as $r) {
            $this->get($r)->assertForbidden();
        }
        $this->assertSame(1, Cliente::count());
    }

    public function test_el_contador_sigue_exportando_y_eliminando_clientes_como_antes(): void
    {
        $this->actingAs($this->usuario(Role::CONTADOR));
        $this->get(route('clientes.index'))->assertOk()->assertSee('Exportar');
        $this->get(route('clientes.exportar'))->assertOk();
    }

    public function test_marketing_tiene_su_asistencia_y_tareas_del_dia(): void
    {
        $this->get(route('rrhh.mi-asistencia'))->assertOk();
        $this->get(route('tareas.index'))->assertOk();
    }

    // ── Cuentas y métricas ────────────────────────────────────────────────

    public function test_se_agrega_una_cuenta_con_su_estado_y_responsable(): void
    {
        $this->post(route('marketing.cuentas.store'), ['red' => 'tiktok', 'nombre' => 'Kyrios TikTok', 'estado' => 'por_reconectar', 'responsable_id' => $this->mkt->id, 'url' => 'https://tiktok.com/@kyrios'])
            ->assertRedirect();

        $c = Cuenta::firstOrFail();
        $this->assertSame('tiktok', $c->red);
        $this->assertSame('Por reconectar', $c->estado_nombre);
        $this->assertSame('Metricool', $c->origen);
        $this->assertTrue($c->activa);
        $this->get(route('marketing.cuentas.index'))->assertOk()->assertSee('Kyrios TikTok')->assertSee('Por reconectar');
        $this->get(route('marketing.cuentas.show', $c))->assertOk();
    }

    public function test_la_cuenta_valida_la_red_el_estado_y_el_enlace(): void
    {
        $this->post(route('marketing.cuentas.store'), ['red' => 'myspace', 'nombre' => '', 'estado' => 'rara', 'url' => 'no-es-url'])
            ->assertSessionHasErrors(['red', 'nombre', 'estado', 'url']);
        $this->assertSame(0, Cuenta::count());
    }

    public function test_cargar_metricas_de_un_dia_y_corregirlas(): void
    {
        $c = $this->cuenta();
        $this->post(route('marketing.cuentas.metricas.store', $c), ['fecha' => '2026-11-10', 'seguidores' => 1000, 'alcance' => 500])->assertSessionHas('success');
        $this->post(route('marketing.cuentas.metricas.store', $c), ['fecha' => '2026-11-10', 'seguidores' => 1010, 'interacciones' => 40])->assertSessionHas('success');

        $this->assertSame(1, Metrica::count(), 'misma fecha: se actualiza, no se duplica');
        $m = Metrica::first();
        $this->assertSame(1010, $m->seguidores);
        $this->assertSame(40, $m->interacciones);
        $this->assertSame(500, $m->alcance, 'lo que no se vuelve a mandar queda como estaba');
    }

    public function test_no_se_guardan_metricas_vacias_futuras_ni_negativas(): void
    {
        $c = $this->cuenta();
        $this->post(route('marketing.cuentas.metricas.store', $c), ['fecha' => '2026-11-10'])->assertSessionHas('error');
        $this->post(route('marketing.cuentas.metricas.store', $c), ['fecha' => now()->addDays(3)->toDateString(), 'seguidores' => 5])->assertSessionHasErrors('fecha');
        $this->post(route('marketing.cuentas.metricas.store', $c), ['fecha' => '2026-11-10', 'seguidores' => -4])->assertSessionHasErrors('seguidores');
        $this->assertSame(0, Metrica::count());
    }

    public function test_importar_un_csv_de_metricas_en_espanol_con_punto_y_coma(): void
    {
        $c = $this->cuenta();
        $csv = "Fecha;Seguidores;Alcance;Impresiones;Interacciones;Reproducciones;Clics\n18/11/2026;1.050;2.300;4.100;120;80;15\n19/11/2026;1.058;1.900;3.000;90;;9\nfecha-mala;1;1;1;1;1;1\n";
        $archivo = UploadedFile::fake()->createWithContent('metricas.csv', $csv);

        $this->post(route('marketing.cuentas.importar', $c), ['archivo' => $archivo])->assertSessionHas('success');

        $this->assertSame(2, Metrica::count());
        $m = Metrica::whereDate('fecha', '2026-11-18')->first();
        $this->assertSame(1050, $m->seguidores, '«1.050» se lee como mil cincuenta');
        $this->assertSame(2300, $m->alcance);
        $this->assertSame(80, $m->reproducciones);
        $this->assertNull(Metrica::whereDate('fecha', '2026-11-19')->first()->reproducciones);
    }

    public function test_importar_en_ingles_actualiza_sin_duplicar_y_avisa_si_no_reconoce_columnas(): void
    {
        $c = $this->cuenta();
        $ok = "date,followers,reach,impressions,engagement,video views,clicks\n2026-11-18,1000,10,20,5,2,1\n";
        $this->post(route('marketing.cuentas.importar', $c), ['archivo' => UploadedFile::fake()->createWithContent('a.csv', $ok)])->assertSessionHas('success');
        $ok2 = "date,followers\n2026-11-18,1100\n";
        $this->post(route('marketing.cuentas.importar', $c), ['archivo' => UploadedFile::fake()->createWithContent('b.csv', $ok2)])->assertSessionHas('success');

        $this->assertSame(1, Metrica::count());
        $this->assertSame(1100, Metrica::first()->seguidores);
        $this->assertSame(10, Metrica::first()->alcance, 'conserva lo que el segundo archivo no trae');

        $this->post(route('marketing.cuentas.importar', $c), ['archivo' => UploadedFile::fake()->createWithContent('c.csv', "uno,dos\n1,2\n")])->assertSessionHas('error');
        $this->post(route('marketing.cuentas.importar', $c), ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('archivo');
    }

    public function test_eliminar_una_cuenta_borra_sus_metricas(): void
    {
        $c = $this->cuenta();
        Metrica::create(['cuenta_id' => $c->id, 'fecha' => '2026-11-10', 'seguidores' => 5]);
        $this->delete(route('marketing.cuentas.destroy', $c))->assertRedirect(route('marketing.cuentas.index'));
        $this->assertSame(0, Metrica::count());
    }

    // ── Estadísticas ──────────────────────────────────────────────────────

    private function datosEstadisticas(): Cuenta
    {
        $c = $this->cuenta();
        foreach ([['2026-11-03', 1000, 100, 200, 10], ['2026-11-10', 1030, 300, 600, 40], ['2026-11-17', 1050, 100, 200, 10]] as [$f, $s, $a, $i, $in]) {
            Metrica::create(['cuenta_id' => $c->id, 'fecha' => $f, 'seguidores' => $s, 'alcance' => $a, 'impresiones' => $i, 'interacciones' => $in]);
        }

        return $c;
    }

    public function test_el_servicio_suma_acumulables_toma_el_ultimo_seguidor_y_calcula_crecimiento_y_engagement(): void
    {
        $this->datosEstadisticas();
        $filas = app(MarketingEstadisticas::class)->porCuenta(\Carbon\Carbon::parse('2026-11-01'), \Carbon\Carbon::parse('2026-11-30')->endOfDay());
        $f = $filas->first();

        $this->assertSame(500, $f['alcance']);
        $this->assertSame(1000, $f['impresiones']);
        $this->assertSame(60, $f['interacciones']);
        $this->assertSame(1050, $f['seguidores'], 'seguidores: el último valor, no la suma');
        $this->assertSame(50, $f['crecimiento']);
        $this->assertSame(12.0, $f['engagement'], '60 interacciones / 500 de alcance');
        $this->assertNull($f['reproducciones'], 'sin dato no es cero');
    }

    public function test_un_periodo_sin_datos_no_inventa_numeros(): void
    {
        $this->datosEstadisticas();
        $f = app(MarketingEstadisticas::class)->porCuenta(\Carbon\Carbon::parse('2026-01-01'), \Carbon\Carbon::parse('2026-01-31')->endOfDay())->first();
        $this->assertFalse($f['tiene_datos']);
        $this->assertNull($f['alcance']);
        $this->assertNull($f['seguidores']);
        $this->assertNull($f['engagement']);
    }

    public function test_la_pantalla_de_estadisticas_con_comparacion_y_filtros(): void
    {
        $c = $this->datosEstadisticas();
        Metrica::create(['cuenta_id' => $c->id, 'fecha' => '2026-10-10', 'seguidores' => 950, 'alcance' => 250, 'interacciones' => 20]);

        $this->get(route('marketing.estadisticas.index', ['periodo' => 'mes', 'fecha' => '2026-11-15', 'comparar' => 1]))
            ->assertOk()->assertSee('Noviembre 2026')->assertSee('vs. período anterior')->assertSee('kyrios_lighting')->assertSee('grafico-seguidores-0');
        $this->get(route('marketing.estadisticas.index', ['periodo' => 'rango', 'desde' => '2026-11-01', 'hasta' => '2026-11-12', 'red' => 'instagram', 'cuenta' => $c->id]))->assertOk();
        $this->get(route('marketing.estadisticas.index', ['periodo' => 'semana', 'fecha' => '2026-11-10']))->assertOk()->assertSee('Semana del');
        $this->get(route('marketing.estadisticas.index', ['periodo' => 'rango', 'desde' => 'basura', 'hasta' => '???']))->assertOk();
        $this->get(route('marketing.estadisticas.index', ['periodo' => 'hack', 'fecha' => 'no-fecha']))->assertOk();
    }

    public function test_las_mejores_publicaciones_salen_ordenadas_por_interacciones(): void
    {
        $this->publicacion(['titulo' => 'A', 'estado' => 'publicada', 'publicada_en' => '2026-11-05 10:00', 'interacciones' => 10, 'alcance' => 100]);
        $this->publicacion(['titulo' => 'B', 'estado' => 'publicada', 'publicada_en' => '2026-11-06 10:00', 'interacciones' => 90, 'alcance' => 300]);
        $this->publicacion(['titulo' => 'C', 'estado' => 'publicada', 'publicada_en' => '2026-11-07 10:00']);
        $this->publicacion(['titulo' => 'D', 'estado' => 'publicada', 'publicada_en' => '2026-10-07 10:00', 'interacciones' => 999]);

        $m = app(MarketingEstadisticas::class)->mejoresPublicaciones(\Carbon\Carbon::parse('2026-11-01'), \Carbon\Carbon::parse('2026-11-30')->endOfDay());
        $this->assertSame(['B', 'A'], $m->pluck('titulo')->all(), 'sin resultados no entra; fuera del período tampoco');
        $this->assertSame(30.0, $m->first()->engagement);
    }

    // ── Publicaciones: creación, aprobación, programación ─────────────────

    private function datosPublicacion(array $extra = []): array
    {
        return $extra + ['titulo' => 'Ring negro', 'texto' => 'Nueva luminaria', 'red' => 'instagram', 'tipo' => 'carrusel', 'programada_para' => '2026-12-05 10:30'];
    }

    public function test_se_crea_una_publicacion_pendiente_con_medios_y_queda_a_nombre_del_creador(): void
    {
        $m = Medio::create(['titulo' => 'Foto', 'tipo' => 'imagen', 'ruta' => 'marketing/medios/x.jpg', 'tamano' => 10]);
        $this->post(route('marketing.publicaciones.store'), $this->datosPublicacion(['medios' => [$m->id]]))->assertRedirect();

        $p = Publicacion::firstOrFail();
        $this->assertSame('pendiente', $p->estado);
        $this->assertSame($this->mkt->id, $p->creado_por);
        $this->assertSame([$m->id], $p->medios()->pluck('mkt_medios.id')->all());
        $this->get(route('marketing.publicaciones.edit', $p))->assertOk()->assertSee('Aprobar')->assertSee('Ring negro');
    }

    public function test_la_publicacion_valida_sus_datos(): void
    {
        $this->post(route('marketing.publicaciones.store'), ['titulo' => '', 'red' => 'x', 'tipo' => 'y', 'enlace' => 'no', 'medios' => [999]])
            ->assertSessionHasErrors(['titulo', 'red', 'tipo', 'enlace', 'medios.0']);
        $this->assertSame(0, Publicacion::count());
    }

    public function test_recorrido_pendiente_aprobada_programada_publicada(): void
    {
        $p = $this->publicacion(['programada_para' => '2026-12-05 10:30']);

        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'aprobada'])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('aprobada', $p->estado);
        $this->assertSame($this->mkt->id, $p->aprobado_por, 'aprueba el mismo rol de Marketing');
        $this->assertNotNull($p->aprobada_en);

        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'programada', 'programada_para' => '2026-12-06 09:00'])->assertSessionHas('success');
        $this->assertSame('2026-12-06 09:00', $p->fresh()->programada_para->format('Y-m-d H:i'));

        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'publicada', 'enlace' => 'https://instagram.com/p/abc'])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('publicada', $p->estado);
        $this->assertNotNull($p->publicada_en);
        $this->assertSame('https://instagram.com/p/abc', $p->enlace);
    }

    public function test_no_se_saltan_pasos_ni_se_retrocede_desde_publicada(): void
    {
        $p = $this->publicacion();
        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'publicada'])->assertSessionHas('error');
        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'programada'])->assertSessionHas('error');
        $this->assertSame('pendiente', $p->fresh()->estado);

        $p->update(['estado' => 'publicada', 'publicada_en' => now()]);
        foreach (['pendiente', 'aprobada', 'programada', 'rechazada'] as $e) {
            $this->post(route('marketing.publicaciones.estado', $p), ['estado' => $e, 'motivo' => 'x', 'programada_para' => '2026-12-01 10:00'])->assertSessionHas('error');
        }
        $this->assertSame('publicada', $p->fresh()->estado);
    }

    public function test_programar_exige_fecha_y_rechazar_exige_motivo(): void
    {
        $p = $this->publicacion(['estado' => 'aprobada']);
        $this->post(route('marketing.publicaciones.estado', $p), ['estado' => 'programada'])->assertSessionHas('error');
        $this->assertSame('aprobada', $p->fresh()->estado);

        $q = $this->publicacion();
        $this->post(route('marketing.publicaciones.estado', $q), ['estado' => 'rechazada'])->assertSessionHas('error');
        $this->post(route('marketing.publicaciones.estado', $q), ['estado' => 'rechazada', 'motivo' => 'Falta el logo'])->assertSessionHas('success');
        $this->assertSame('Falta el logo', $q->fresh()->motivo_rechazo);
        $this->get(route('marketing.publicaciones.edit', $q))->assertSee('Falta el logo');

        $this->post(route('marketing.publicaciones.estado', $q), ['estado' => 'pendiente'])->assertSessionHas('success');
        $this->assertSame('pendiente', $q->fresh()->estado);
    }

    public function test_cambiar_el_contenido_de_una_aprobada_pide_aprobar_otra_vez_pero_reprogramar_no(): void
    {
        $p = $this->publicacion(['estado' => 'programada', 'programada_para' => '2026-12-05 10:00', 'aprobado_por' => $this->mkt->id, 'aprobada_en' => now()]);

        $this->put(route('marketing.publicaciones.update', $p), $this->datosPublicacion(['titulo' => 'Lanzamiento Ring', 'texto' => (string) $p->texto, 'tipo' => 'publicacion', 'programada_para' => '2026-12-09 18:00']))->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('programada', $p->estado, 'solo se movió la fecha');
        $this->assertSame('2026-12-09 18:00', $p->programada_para->format('Y-m-d H:i'));

        $this->put(route('marketing.publicaciones.update', $p), $this->datosPublicacion(['titulo' => 'Otro título', 'tipo' => 'publicacion', 'texto' => '', 'programada_para' => '2026-12-09 18:00']));
        $p->refresh();
        $this->assertSame('pendiente', $p->estado, 'cambió lo que se publica: vuelve a aprobación');
        $this->assertNull($p->aprobado_por);
    }

    public function test_una_publicada_solo_admite_enlace_y_resultados(): void
    {
        $p = $this->publicacion(['estado' => 'publicada', 'publicada_en' => '2026-11-05 10:00', 'titulo' => 'Original']);
        $this->put(route('marketing.publicaciones.update', $p), $this->datosPublicacion(['titulo' => 'Cambiado', 'alcance' => 800, 'interacciones' => 64, 'enlace' => 'https://x.com/p']))->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('Original', $p->titulo, 'el contenido publicado no se reescribe');
        $this->assertSame(800, $p->alcance);
        $this->assertSame(8.0, $p->engagement);

        $this->delete(route('marketing.publicaciones.destroy', $p))->assertSessionHas('error');
        $this->assertSame(1, Publicacion::count());
    }

    public function test_copiar_una_publicacion_a_otra_red_y_eliminar_una_pendiente(): void
    {
        $m = Medio::create(['titulo' => 'Foto', 'tipo' => 'imagen', 'ruta' => 'marketing/medios/x.jpg', 'tamano' => 10]);
        $p = $this->publicacion(['estado' => 'publicada', 'publicada_en' => now(), 'alcance' => 50]);
        $p->medios()->sync([$m->id]);

        $this->post(route('marketing.publicaciones.duplicar', $p), ['red' => 'facebook'])->assertRedirect();
        $copia = Publicacion::where('red', 'facebook')->firstOrFail();
        $this->assertSame('pendiente', $copia->estado);
        $this->assertNull($copia->alcance);
        $this->assertNull($copia->publicada_en);
        $this->assertSame([$m->id], $copia->medios()->pluck('mkt_medios.id')->all());

        $this->delete(route('marketing.publicaciones.destroy', $copia))->assertRedirect(route('marketing.publicaciones.index'));
        $this->assertSame(1, Publicacion::count());
    }

    public function test_el_listado_filtra_por_estado_y_red_y_cuenta_por_estado(): void
    {
        $this->publicacion(['titulo' => 'Uno', 'estado' => 'pendiente']);
        $this->publicacion(['titulo' => 'Dos', 'estado' => 'aprobada', 'red' => 'facebook']);
        $r = $this->get(route('marketing.publicaciones.index', ['estado' => 'aprobada']))->assertOk();
        $r->assertSee('Dos')->assertDontSee('Uno');
        $this->get(route('marketing.publicaciones.index', ['red' => 'instagram', 'q' => 'Un']))->assertOk()->assertSee('Uno')->assertDontSee('Dos');
    }

    // ── Calendario ────────────────────────────────────────────────────────

    public function test_el_calendario_muestra_las_publicaciones_en_vista_mensual_semanal_y_diaria(): void
    {
        $this->publicacion(['titulo' => 'Navidad ring', 'estado' => 'programada', 'programada_para' => '2026-12-05 10:30']);
        $this->publicacion(['titulo' => 'Ya salió', 'estado' => 'publicada', 'programada_para' => '2026-12-01 09:00', 'publicada_en' => '2026-12-06 11:00']);

        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'mes', 'fecha' => '2026-12-10']))->assertOk()->assertSee('Diciembre 2026')->assertSee('Navidad ring')->assertSee('Ya salió');
        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'semana', 'fecha' => '2026-12-05']))->assertOk()->assertSee('Navidad ring');
        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'dia', 'fecha' => '2026-12-05']))->assertOk()->assertSee('Navidad ring')->assertSee('10:30');
        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'dia', 'fecha' => '2026-12-06']))->assertOk()->assertSee('Ya salió')->assertDontSee('Navidad ring');
        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'mes', 'fecha' => '2026-12-10', 'estado' => 'publicada']))->assertOk()->assertSee('Ya salió')->assertDontSee('Navidad ring');
        $this->get(route('marketing.publicaciones.calendario', ['vista' => 'raro', 'fecha' => 'basura']))->assertOk();
    }

    // ── Biblioteca multimedia ─────────────────────────────────────────────

    public function test_subir_varios_archivos_a_la_biblioteca_y_eliminar_uno(): void
    {
        Storage::fake('public');
        $this->post(route('marketing.medios.store'), ['archivos' => [UploadedFile::fake()->image('ring.jpg', 600, 600), UploadedFile::fake()->create('promo.mp4', 2000, 'video/mp4')], 'etiquetas' => 'navidad'])
            ->assertSessionHas('success');

        $this->assertSame(2, Medio::count());
        $img = Medio::where('tipo', 'imagen')->first();
        $this->assertSame('ring', $img->titulo);
        $this->assertSame('video', Medio::where('nombre_original', 'promo.mp4')->value('tipo'));
        Storage::disk('public')->assertExists($img->ruta);
        $this->get(route('marketing.medios.index', ['q' => 'navidad']))->assertOk()->assertSee('ring');

        $this->delete(route('marketing.medios.destroy', $img))->assertSessionHas('success');
        Storage::disk('public')->assertMissing($img->ruta);
        $this->assertSame(1, Medio::count());
    }

    public function test_la_biblioteca_rechaza_formatos_peligrosos_y_archivos_enormes(): void
    {
        Storage::fake('public');
        foreach (['malo.php', 'malo.exe', 'malo.html', 'malo.svg'] as $nombre) {
            $this->post(route('marketing.medios.store'), ['archivos' => [UploadedFile::fake()->create($nombre, 5)]])->assertSessionHasErrors('archivos.0');
        }
        $this->post(route('marketing.medios.store'), ['archivos' => [UploadedFile::fake()->create('enorme.mp4', 60000, 'video/mp4')]])->assertSessionHasErrors('archivos.0');
        $this->post(route('marketing.medios.store'), [])->assertSessionHasErrors('archivos');
        $this->assertSame(0, Medio::count());
    }

    public function test_borrar_un_medio_lo_quita_de_las_publicaciones_que_lo_usan(): void
    {
        Storage::fake('public');
        $m = Medio::create(['titulo' => 'Foto', 'tipo' => 'imagen', 'ruta' => 'marketing/medios/x.jpg', 'tamano' => 10]);
        $p = $this->publicacion();
        $p->medios()->sync([$m->id]);
        $this->delete(route('marketing.medios.destroy', $m));
        $this->assertSame(0, $p->medios()->count());
        $this->assertSame(1, Publicacion::count());
    }

    // ── Bandeja de interacciones ──────────────────────────────────────────

    private function interaccion(array $extra = []): Interaccion
    {
        return Interaccion::create($extra + ['red' => 'instagram', 'tipo' => 'comentario', 'autor' => '@maria', 'texto' => '¿Tienen el Ring en blanco?', 'recibida_en' => now()->subHours(3), 'estado' => 'pendiente']);
    }

    public function test_registrar_una_interaccion_asignarla_y_responderla(): void
    {
        $this->post(route('marketing.interacciones.store'), ['red' => 'facebook', 'tipo' => 'mensaje', 'autor' => 'María López', 'texto' => 'Precio del Ring?', 'asignado_a' => $this->mkt->id])->assertRedirect();
        $i = Interaccion::firstOrFail();
        $this->assertSame('pendiente', $i->estado);
        $this->assertSame($this->mkt->id, $i->asignado_a);

        $otro = $this->usuario(Role::MARKETING);
        $this->post(route('marketing.interacciones.asignar', $i), ['asignado_a' => $otro->id])->assertSessionHas('success');
        $this->assertSame($otro->id, $i->fresh()->asignado_a);

        $this->post(route('marketing.interacciones.responder', $i), ['respuesta' => ''])->assertSessionHasErrors('respuesta');
        $this->post(route('marketing.interacciones.responder', $i), ['respuesta' => 'Cuesta S/ 120, te escribimos por privado'])->assertSessionHas('success');
        $i->refresh();
        $this->assertSame('atendida', $i->estado);
        $this->assertSame($this->mkt->id, $i->respondida_por, 'queda registrado quién respondió');
        $this->assertNotNull($i->respondida_en);

        $this->post(route('marketing.interacciones.reabrir', $i))->assertSessionHas('success');
        $this->assertSame('pendiente', $i->fresh()->estado);
    }

    public function test_la_bandeja_filtra_pendientes_mias_y_por_persona_y_muestra_el_historial(): void
    {
        $a = $this->interaccion(['autor' => '@maria', 'asignado_a' => $this->mkt->id]);
        $this->interaccion(['autor' => '@maria', 'texto' => 'Gracias!', 'estado' => 'atendida', 'recibida_en' => now()->subDays(2)]);
        $this->interaccion(['autor' => '@pedro', 'texto' => 'Envían a provincia?', 'red' => 'tiktok']);

        $this->get(route('marketing.interacciones.index', ['estado' => 'pendiente', 'asignado' => 'yo']))->assertOk()->assertSee('Tienen el Ring')->assertDontSee('provincia');
        $this->get(route('marketing.interacciones.index', ['red' => 'tiktok']))->assertOk()->assertSee('provincia')->assertDontSee('Tienen el Ring');
        $this->get(route('marketing.interacciones.index', ['q' => 'pedro']))->assertOk()->assertSee('provincia');
        $this->get(route('marketing.interacciones.show', $a))->assertOk()->assertSee('Historial con @maria')->assertSee('Gracias!');
        $this->get(route('marketing.interacciones.index'))->assertSee('Bandeja de entrada');
    }

    public function test_la_interaccion_valida_y_no_acepta_fechas_futuras(): void
    {
        $this->post(route('marketing.interacciones.store'), ['red' => 'x', 'tipo' => 'y', 'autor' => '', 'texto' => ''])->assertSessionHasErrors(['red', 'tipo', 'autor', 'texto']);
        $this->post(route('marketing.interacciones.store'), ['red' => 'instagram', 'tipo' => 'comentario', 'autor' => 'a', 'texto' => 'b', 'recibida_en' => now()->addDay()->toDateTimeString()])->assertSessionHasErrors('recibida_en');
        $this->assertSame(0, Interaccion::count());
    }

    // ── Competencia ───────────────────────────────────────────────────────

    public function test_registrar_competidor_cargar_sus_datos_y_ver_el_crecimiento(): void
    {
        $this->post(route('marketing.competencia.store'), ['red' => 'instagram', 'nombre' => 'Luminarias Sol', 'usuario' => '@luminarias_sol'])->assertRedirect();
        $c = Competidor::firstOrFail();

        $this->post(route('marketing.competencia.metricas.store', $c), ['fecha' => '2026-11-03', 'seguidores' => 5000, 'publicaciones' => 6, 'interacciones' => 300])->assertSessionHas('success');
        $this->post(route('marketing.competencia.metricas.store', $c), ['fecha' => '2026-11-10', 'seguidores' => 5250, 'publicaciones' => 9, 'destacada' => 'Reel de instalación', 'enlace_destacada' => 'https://instagram.com/reel/1'])->assertSessionHas('success');

        $r = $this->get(route('marketing.competencia.index'))->assertOk();
        $r->assertSee('Luminarias Sol')->assertSee('+250')->assertSee('5 %');
        $this->get(route('marketing.competencia.show', $c))->assertOk()->assertSee('Reel de instalación');

        $this->post(route('marketing.competencia.metricas.store', $c), ['fecha' => '2026-11-10'])->assertSessionHas('error');
        $this->assertSame(2, $c->metricas()->count());
    }

    public function test_la_competencia_compara_con_nuestras_cuentas(): void
    {
        $mia = $this->cuenta();
        Metrica::create(['cuenta_id' => $mia->id, 'fecha' => '2026-11-10', 'seguidores' => 1050]);
        $this->publicacion(['estado' => 'publicada', 'publicada_en' => now()->subDays(3)]);

        $this->get(route('marketing.competencia.index'))->assertOk()->assertSee('Nosotros, por red')->assertSee('1,050');
    }

    public function test_eliminar_un_competidor_borra_su_historial(): void
    {
        $c = Competidor::create(['red' => 'tiktok', 'nombre' => 'X']);
        $c->metricas()->create(['fecha' => '2026-11-10', 'seguidores' => 10]);
        $this->delete(route('marketing.competencia.destroy', $c))->assertRedirect();
        $this->assertSame(0, \App\Models\Marketing\CompetidorMetrica::count());
    }

    // ── Campañas ──────────────────────────────────────────────────────────

    private function datosCampana(array $extra = []): array
    {
        return $extra + ['nombre' => 'Campaña Navidad', 'red' => 'instagram', 'estado' => 'activa', 'moneda' => 'PEN', 'presupuesto' => 500, 'inversion' => 200,
            'impresiones' => 20000, 'alcance' => 12000, 'clics' => 400, 'conversiones' => 8, 'ingresos' => 1000];
    }

    public function test_la_campana_calcula_cpc_ctr_roas_y_avance(): void
    {
        $this->post(route('marketing.campanas.store'), $this->datosCampana())->assertRedirect();
        $c = Campana::firstOrFail();

        $this->assertSame(0.5, $c->cpc, 'S/ 200 / 400 clics');
        $this->assertSame(2.0, $c->ctr, '400 / 20000');
        $this->assertSame(5.0, $c->roas, '1000 / 200');
        $this->assertSame(25.0, $c->costo_por_conversion);
        $this->assertSame(40.0, $c->avance_presupuesto);
        $this->get(route('marketing.campanas.edit', $c))->assertOk()->assertSee('5x')->assertSee('0.50');
    }

    public function test_sin_clics_ni_ingresos_los_indicadores_quedan_vacios_y_no_dividen_por_cero(): void
    {
        $this->post(route('marketing.campanas.store'), $this->datosCampana(['clics' => null, 'impresiones' => null, 'ingresos' => null, 'conversiones' => null, 'inversion' => 0, 'presupuesto' => 0]))->assertRedirect();
        $c = Campana::firstOrFail();
        $this->assertNull($c->cpc);
        $this->assertNull($c->ctr);
        $this->assertNull($c->roas);
        $this->assertNull($c->costo_por_conversion);
        $this->assertNull($c->avance_presupuesto);
        $this->get(route('marketing.campanas.index'))->assertOk();
        $this->get(route('marketing.campanas.edit', $c))->assertOk();
    }

    public function test_la_campana_valida_montos_fechas_y_moneda(): void
    {
        $this->post(route('marketing.campanas.store'), $this->datosCampana(['presupuesto' => -5, 'moneda' => 'EUR', 'fecha_inicio' => '2026-12-10', 'fecha_fin' => '2026-12-01', 'clics' => -1, 'estado' => 'x']))
            ->assertSessionHasErrors(['presupuesto', 'moneda', 'fecha_fin', 'clics', 'estado']);
        $this->assertSame(0, Campana::count());
    }

    public function test_los_totales_de_campanas_no_mezclan_soles_con_dolares(): void
    {
        $this->post(route('marketing.campanas.store'), $this->datosCampana(['nombre' => 'Soles']));
        $this->post(route('marketing.campanas.store'), $this->datosCampana(['nombre' => 'Dólares', 'moneda' => 'USD', 'presupuesto' => 100, 'inversion' => 10]));
        $r = $this->get(route('marketing.campanas.index'))->assertOk();
        $porMoneda = $r->viewData('porMoneda');
        $this->assertSame(200.0, (float) $porMoneda['PEN']['inversion']);
        $this->assertSame(10.0, (float) $porMoneda['USD']['inversion']);
    }

    public function test_eliminar_una_campana_conserva_sus_publicaciones(): void
    {
        $c = Campana::create($this->datosCampana() + ['creado_por' => $this->mkt->id]);
        $p = $this->publicacion(['campana_id' => $c->id]);
        $this->delete(route('marketing.campanas.destroy', $c))->assertRedirect();
        $this->assertNull($p->fresh()->campana_id);
        $this->assertSame(1, Publicacion::count());
    }

    // ── Reportes ──────────────────────────────────────────────────────────

    public function test_el_reporte_en_pantalla_pdf_y_excel(): void
    {
        $this->datosEstadisticas();
        Campana::create($this->datosCampana() + ['fecha_inicio' => '2026-11-01']);
        $q = ['periodo' => 'mes', 'fecha' => '2026-11-15', 'comparar' => 1];

        $this->get(route('marketing.reportes.index', $q))->assertOk()->assertSee('Noviembre 2026')->assertSee('Campaña Navidad')->assertSee('PDF')->assertSee('Excel');

        $pdf = $this->get(route('marketing.reportes.pdf', $q))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get(route('marketing.reportes.excel', $q))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));
        $this->assertStringContainsString('reporte-marketing-20261101-20261130.xlsx', $xlsx->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $xlsx->streamedContent(), 'un .xlsx es un zip');
    }

    public function test_el_excel_trae_los_numeros_del_periodo(): void
    {
        $this->datosEstadisticas();
        $r = $this->get(route('marketing.reportes.excel', ['periodo' => 'rango', 'desde' => '2026-11-01', 'hasta' => '2026-11-30']));
        $tmp = tempnam(sys_get_temp_dir(), 'rep') . '.xlsx';
        file_put_contents($tmp, $r->streamedContent());
        $hoja = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getSheetByName('Redes');
        @unlink($tmp);

        $this->assertSame('kyrios_lighting', $hoja->getCell('B4')->getValue());
        $this->assertEquals(1050, $hoja->getCell('C4')->getValue());
        $this->assertEquals(500, $hoja->getCell('E4')->getValue());
        $this->assertEquals(60, $hoja->getCell('G4')->getValue());
        $this->assertSame('TOTAL', $hoja->getCell('A5')->getValue());
    }

    public function test_los_reportes_aceptan_periodos_raros_sin_error(): void
    {
        foreach ([['periodo' => 'semana'], ['periodo' => 'rango', 'desde' => '2026-12-31', 'hasta' => '2026-01-01'], ['periodo' => 'x', 'fecha' => '###'], ['red' => 'tiktok', 'comparar' => 1]] as $q) {
            $this->get(route('marketing.reportes.index', $q))->assertOk();
            $this->get(route('marketing.reportes.pdf', $q))->assertOk();
            $this->get(route('marketing.reportes.excel', $q))->assertOk();
        }
    }

    // ── Panel ─────────────────────────────────────────────────────────────

    public function test_el_panel_cuenta_pendientes_por_aprobar_y_proximas(): void
    {
        $this->interaccion(['asignado_a' => $this->mkt->id, 'recibida_en' => now()->subDays(2)]);
        $this->publicacion(['estado' => 'pendiente']);
        $this->publicacion(['titulo' => 'Próxima grande', 'estado' => 'programada', 'programada_para' => now()->addDays(2)]);
        Campana::create($this->datosCampana());

        $r = $this->get(route('marketing.dashboard'))->assertOk();
        $this->assertSame(1, $r->viewData('interaccionesPend'));
        $this->assertSame(1, $r->viewData('misInteracciones'));
        $this->assertSame(1, $r->viewData('antiguas'));
        $this->assertSame(1, $r->viewData('porAprobar'));
        $this->assertSame(1, $r->viewData('campanasActivas')->count());
        $r->assertSee('Próxima grande')->assertSee('Campaña Navidad');
    }

    // ── Almacenero (almacén) no ve el módulo, Administración sí lo tiene en su menú ──

    public function test_administracion_ve_marketing_en_su_menu(): void
    {
        $this->actingAs($this->usuario(Role::ADMINISTRACION));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Marketing y medios')->assertSee('Bandeja de entrada');
    }

    public function test_otros_roles_no_ven_marketing_en_su_menu(): void
    {
        foreach ([Role::CONTADOR => 'contador.dashboard', Role::LOGISTICA => 'logistica.dashboard', Role::OPERACIONES => 'operaciones.dashboard'] as $rol => $ruta) {
            $this->actingAs($this->usuario($rol))->get(route($ruta))->assertOk()->assertDontSee('Marketing y medios');
        }
    }

    public function test_la_pantalla_de_usuarios_ofrece_el_rol_marketing(): void
    {
        $this->actingAs($this->usuario(Role::ADMINISTRACION));
        $this->get(route('users.index'))->assertOk()->assertSee('Marketing');
    }
}
