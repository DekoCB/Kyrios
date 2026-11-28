<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\AsistenciaMarcacion;
use App\Models\Empleado;
use App\Models\EvaluacionEmpleado;
use App\Models\LugarTrabajo;
use App\Models\Role;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Services\PlanillaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** RRHH: ajuste al sueldo por tardanzas, faltas y horas extra; selfie y ubicación al marcar; notas de 0 a 20. */
class RrhhPlanillaNotasYUbicacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = $this->usuario('Administración');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function empleado(array $extra = []): Empleado
    {
        return Empleado::create($extra + [
            'nombres' => 'Valeria', 'apellidos' => 'Mendoza', 'dni' => '12345678', 'cargo' => 'Asesora', 'tipo_contrato' => 'indefinido', 'fecha_ingreso' => '2025-01-10',
            'hora_entrada' => '08:00:00', 'hora_salida' => '18:00:00', 'estado' => 'activo', 'sueldo_basico' => 3000,
        ]);
    }

    private function dia(Empleado $e, string $fecha, string $estado, array $extra = []): Asistencia
    {
        return Asistencia::create($extra + ['empleado_id' => $e->id, 'fecha' => $fecha, 'estado' => $estado, 'origen' => 'manual']);
    }

    // ── Cálculo del ajuste ──

    public function test_el_horario_de_partida_es_de_8_a_18(): void
    {
        $this->actingAs($this->admin)->get(route('rrhh.empleados.create'))->assertOk()->assertSee('value="08:00"', false)->assertSee('value="18:00"', false)
            ->assertSee('Salario, horario y descuentos')->assertSee('Horas extra (después de la hora de salida)');
    }

    public function test_descuento_proporcional_por_tardanza_descuento_por_falta_y_bono_por_hora_extra(): void
    {
        $e = $this->empleado();                          // 3000: día 100, hora 12.50, minuto 0.2083
        $this->dia($e, '2026-11-02', 'tardanza', ['minutos_tarde' => 30]);
        $this->dia($e, '2026-11-03', 'tardanza', ['minutos_tarde' => 30]);
        $this->dia($e, '2026-11-04', 'falta');
        $this->dia($e, '2026-11-05', 'puntual', ['minutos_extra' => 120]);

        $a = app(PlanillaService::class)->ajuste($e, 2026, 11);

        $this->assertEquals(100.0, $a['valor_dia']);
        $this->assertEquals(12.5, $a['valor_hora']);
        $this->assertSame(2, $a['tardanzas']);
        $this->assertSame(60, $a['min_tarde']);
        $this->assertEquals(12.5, $a['desc_tardanzas'], 'proporcional: 60 min = 1 hora = 12.50');
        $this->assertEquals(100.0, $a['desc_faltas']);
        $this->assertEquals(2.0, $a['horas_extra']);
        $this->assertEquals(31.25, $a['bono_extra'], '2 h × 12.50 × 1.25');
        $this->assertEquals(-81.25, $a['neto']);
        $this->assertEquals(2918.75, $a['sueldo_estimado']);
    }

    public function test_los_otros_modos_de_descuento_y_bonificacion(): void
    {
        $e = $this->empleado(['tardanza_modo' => 'por_minuto', 'tardanza_valor' => 0.5, 'falta_modo' => 'monto', 'falta_valor' => 80, 'extra_modo' => 'por_hora', 'extra_valor' => 20]);
        $this->dia($e, '2026-11-02', 'tardanza', ['minutos_tarde' => 40]);
        $this->dia($e, '2026-11-03', 'falta');
        $this->dia($e, '2026-11-04', 'falta');
        $this->dia($e, '2026-11-05', 'puntual', ['minutos_extra' => 90]);

        $a = app(PlanillaService::class)->ajuste($e, 2026, 11);
        $this->assertEquals(20.0, $a['desc_tardanzas']);
        $this->assertEquals(160.0, $a['desc_faltas']);
        $this->assertEquals(30.0, $a['bono_extra']);

        $e->update(['tardanza_modo' => 'por_tardanza', 'tardanza_valor' => 15, 'falta_modo' => 'ninguno', 'extra_modo' => 'ninguno']);
        $a = app(PlanillaService::class)->ajuste($e->fresh(), 2026, 11);
        $this->assertEquals(15.0, $a['desc_tardanzas']);
        $this->assertEquals(0.0, $a['desc_faltas']);
        $this->assertEquals(0.0, $a['bono_extra']);
    }

    public function test_sin_sueldo_los_importes_salen_en_cero_y_otro_mes_no_cuenta(): void
    {
        $e = $this->empleado(['sueldo_basico' => null]);
        $this->dia($e, '2026-11-04', 'falta');
        $this->dia($e, '2026-10-04', 'falta');

        $a = app(PlanillaService::class)->ajuste($e, 2026, 11);

        $this->assertSame(1, $a['faltas']);
        $this->assertEquals(0.0, $a['desc_faltas']);
    }

    public function test_las_horas_extra_solo_cuentan_desde_el_minimo_de_cada_trabajador(): void
    {
        $s = app(AsistenciaService::class);
        $e = $this->empleado();

        $this->assertSame(70, $s->minutosExtra($e, '19:10:00'));
        $this->assertSame(0, $s->minutosExtra($e, '18:20:00'), 'menos de 30 minutos no se bonifica');
        $this->assertSame(0, $s->minutosExtra($e, '17:30:00'));
        $this->assertSame(0, $s->minutosExtra($e, null));
        $this->assertSame(20, $s->minutosExtra($this->empleado(['dni' => '999', 'extra_minimo_min' => 10]), '18:20:00'));
    }

    public function test_la_tolerancia_propia_reemplaza_a_la_general(): void
    {
        $s = app(AsistenciaService::class);
        $this->assertSame(0, $s->minutosTarde($this->empleado(), '08:09:00'));
        $this->assertSame(25, $s->minutosTarde($this->empleado(['dni' => '2', 'tolerancia_min' => 0]), '08:25:00'));
        $this->assertSame(0, $s->minutosTarde($this->empleado(['dni' => '3', 'tolerancia_min' => 30]), '08:25:00'));
    }

    public function test_marcar_la_salida_y_cargar_a_mano_guardan_los_minutos_extra(): void
    {
        $user = $this->usuario('Operaciones');
        $e = $this->empleado(['user_id' => $user->id]);
        config(['kyrios.rrhh.exigir_foto_ubicacion' => false]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-11-16 07:58:00');
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHas('success');
        Carbon::setTestNow('2026-11-16 19:15:00');
        $this->post(route('rrhh.marcar'), ['accion' => 'salida'])->assertSessionHas('success');
        $this->assertSame(75, Asistencia::firstOrFail()->minutos_extra);

        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->actingAs($this->admin)->post(route('rrhh.asistencia.registrar', $e), ['fecha' => '2026-11-17', 'estado' => 'puntual', 'hora_entrada' => '08:00', 'hora_salida' => '20:00'])->assertSessionHasNoErrors();
        $this->assertSame(120, Asistencia::whereDate('fecha', '2026-11-17')->firstOrFail()->minutos_extra);
    }

    // ── Vistas por día, por mes y por trabajador ──

    public function test_el_reporte_mensual_muestra_el_ajuste_y_el_calendario_y_el_csv_lo_exporta(): void
    {
        $e = $this->empleado();
        $this->dia($e, '2026-11-02', 'tardanza', ['minutos_tarde' => 60]);
        $this->dia($e, '2026-11-03', 'falta');
        $this->actingAs($this->admin);

        $this->get(route('rrhh.asistencia.reporte', ['mes' => '2026-11']))->assertOk()->assertSee('Calendario del mes')->assertSee('Sueldo estimado')->assertSee('S/ 2,887.50', false)->assertSee('Valeria Mendoza');

        $csv = $this->get(route('rrhh.asistencia.exportar', ['mes' => '2026-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Descuento por tardanzas', $csv);
        $this->assertStringContainsString('2887.5', $csv);
    }

    public function test_la_vista_por_trabajador_muestra_cada_dia_y_el_ajuste(): void
    {
        $e = $this->empleado();
        $this->dia($e, '2026-11-02', 'puntual', ['hora_entrada' => '08:00:00', 'hora_salida' => '20:00:00', 'minutos_extra' => 120]);
        $this->actingAs($this->admin);

        $this->get(route('rrhh.asistencia.trabajador'))->assertOk()->assertSee('Elija un trabajador');
        $this->get(route('rrhh.asistencia.trabajador', ['empleado' => $e->id, 'mes' => '2026-11']))->assertOk()
            ->assertSee('Valeria Mendoza')->assertSee('2 h 0 min')->assertSee('Horas extra')->assertSee('S/ 31.25', false);
    }

    public function test_solo_administracion_ve_las_vistas_de_rrhh_nuevas(): void
    {
        $e = $this->empleado();
        $this->actingAs($this->usuario('Logística'));

        foreach ([route('rrhh.asistencia.trabajador'), route('rrhh.lugares.index'), route('rrhh.evaluaciones.index'), route('rrhh.evaluaciones.show', $e)] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    // ── Selfie y ubicación ──

    private function marcarConFoto(array $extra = [])
    {
        return $this->post(route('rrhh.marcar'), $extra + ['accion' => 'entrada', 'foto' => UploadedFile::fake()->image('selfie.jpg', 300, 400), 'latitud' => -12.0464, 'longitud' => -77.0428, 'precision' => 12]);
    }

    public function test_marcar_exige_la_selfie_y_la_ubicacion(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHasErrors(['foto', 'latitud', 'longitud']);
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada', 'foto' => UploadedFile::fake()->image('s.jpg')])->assertSessionHasErrors(['latitud', 'longitud']);
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada', 'foto' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'latitud' => 1, 'longitud' => 1])->assertSessionHasErrors('foto');
        $this->assertSame(0, Asistencia::count());
    }

    public function test_dentro_del_radio_de_un_lugar_la_marcacion_queda_validada(): void
    {
        $user = $this->usuario('Marketing');
        $e = $this->empleado(['user_id' => $user->id]);
        $oficina = LugarTrabajo::create(['nombre' => 'Oficina central', 'tipo' => 'oficina', 'latitud' => -12.0464, 'longitud' => -77.0428, 'radio_m' => 150]);
        $this->actingAs($user);

        $this->marcarConFoto(['latitud' => -12.04655, 'longitud' => -77.04285])->assertSessionHas('success');

        $m = AsistenciaMarcacion::firstOrFail();
        $this->assertSame('valida', $m->ubicacion);
        $this->assertSame($oficina->id, $m->lugar_trabajo_id);
        $this->assertLessThan(150, $m->distancia_m);
        $this->assertSame('entrada', $m->tipo);
        $this->assertSame($e->id, $m->empleado_id);
        Storage::disk('local')->assertExists($m->foto);
        $this->assertStringContainsString('Oficina central', session('success'));
    }

    public function test_lejos_de_todo_lugar_la_marcacion_queda_fuera_y_pendiente_de_revision(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        LugarTrabajo::create(['nombre' => 'Oficina', 'tipo' => 'oficina', 'latitud' => -12.0464, 'longitud' => -77.0428, 'radio_m' => 150]);
        LugarTrabajo::create(['nombre' => 'Cerrada', 'tipo' => 'obra', 'latitud' => -12.1000, 'longitud' => -77.1000, 'radio_m' => 5000, 'activo' => false]);
        $this->actingAs($user);

        $this->marcarConFoto(['latitud' => -12.1000, 'longitud' => -77.1000])->assertSessionHas('success');

        $m = AsistenciaMarcacion::firstOrFail();
        $this->assertSame('fuera', $m->ubicacion, 'un lugar inactivo no valida');
        $this->assertNull($m->lugar_trabajo_id);
        $this->assertGreaterThan(5000, $m->distancia_m);
        $this->assertStringContainsString('Administración revisará', session('success'));
    }

    public function test_una_obra_registrada_valida_a_quien_va_directo_al_lugar(): void
    {
        $user = $this->usuario('Operaciones');
        $this->empleado(['user_id' => $user->id]);
        LugarTrabajo::create(['nombre' => 'Obra Miraflores', 'tipo' => 'obra', 'latitud' => -12.1219, 'longitud' => -77.0297, 'radio_m' => 200]);
        $this->actingAs($user);

        $this->marcarConFoto(['latitud' => -12.1220, 'longitud' => -77.0298])->assertSessionHas('success');

        $this->assertSame('valida', AsistenciaMarcacion::firstOrFail()->ubicacion);
        $this->assertSame('Obra Miraflores', AsistenciaMarcacion::firstOrFail()->lugar->nombre);
    }

    public function test_marcar_la_salida_guarda_su_propia_selfie_y_ubicacion(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        LugarTrabajo::create(['nombre' => 'Oficina', 'tipo' => 'oficina', 'latitud' => -12.0464, 'longitud' => -77.0428, 'radio_m' => 150]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-11-16 08:00:00');
        $this->marcarConFoto();
        Carbon::setTestNow('2026-11-16 18:05:00');

        $this->marcarConFoto(['accion' => 'salida'])->assertSessionHas('success');

        $this->assertSame(['entrada', 'salida'], AsistenciaMarcacion::orderBy('id')->pluck('tipo')->all());
    }

    public function test_si_la_marcacion_falla_no_queda_la_selfie_guardada(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->marcarConFoto(['accion' => 'salida'])->assertSessionHas('error');   // sin entrada previa

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, AsistenciaMarcacion::count());
    }

    public function test_la_selfie_la_ven_administracion_y_su_dueno_nadie_mas(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->marcarConFoto();
        $m = AsistenciaMarcacion::firstOrFail();

        $this->get(route('rrhh.marcaciones.foto', $m))->assertOk();
        $this->actingAs($this->admin)->get(route('rrhh.marcaciones.foto', $m))->assertOk();
        $this->actingAs($this->usuario('Operaciones'))->get(route('rrhh.marcaciones.foto', $m))->assertForbidden();
    }

    public function test_administracion_aprueba_o_rechaza_una_marcacion_fuera_de_lugar(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->marcarConFoto();                                       // sin lugares registrados: queda «fuera»
        $m = AsistenciaMarcacion::firstOrFail();
        $this->assertSame('fuera', $m->ubicacion);

        $this->post(route('rrhh.marcaciones.revisar', $m), ['revision' => 'aprobada'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('rrhh.marcaciones.revisar', $m), ['revision' => 'aprobada'])->assertSessionHas('success');
        $this->assertSame('aprobada', $m->fresh()->revision);
        $this->assertSame('puntual', Asistencia::firstOrFail()->estado === 'falta' ? 'falta' : 'puntual');

        $this->post(route('rrhh.marcaciones.revisar', $m), ['revision' => 'rechazada'])->assertSessionHas('success');
        $a = Asistencia::firstOrFail();
        $this->assertSame('falta', $a->estado);
        $this->assertNull($a->hora_entrada);
        $this->post(route('rrhh.marcaciones.revisar', $m), ['revision' => 'quizas'])->assertSessionHasErrors('revision');
    }

    public function test_el_control_del_dia_muestra_ubicacion_selfie_y_botones_de_revision(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-11-16 08:00:00');
        $this->marcarConFoto();
        $this->actingAs($this->admin);

        $this->get(route('rrhh.asistencia.control', ['fecha' => '2026-11-16']))->assertOk()->assertSee('Ubicación y selfie')->assertSee('Fuera')->assertSee('Aprobar')->assertSee('Rechazar')->assertSee('mapa');
    }

    public function test_la_pantalla_de_marcar_pide_camara_y_ubicacion(): void
    {
        $user = $this->usuario('Marketing');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('rrhh.mi-asistencia'))->assertOk()->assertSee('getUserMedia', false)->assertSee('geolocation', false)->assertSee('Tomar foto')->assertSee('Aún no hay lugares de trabajo registrados');
    }

    public function test_la_distancia_entre_dos_puntos_es_la_de_la_vida_real(): void
    {
        // Plaza Mayor de Lima a la Plaza San Martín: unos 600 m
        $d = LugarTrabajo::distancia(-12.04318, -77.02824, -12.05192, -77.03432);
        $this->assertGreaterThan(1000, $d);
        $this->assertLessThan(1400, $d);
        $this->assertSame(0, LugarTrabajo::distancia(-12.0, -77.0, -12.0, -77.0));
    }

    // ── Lugares de trabajo ──

    public function test_administracion_crea_edita_desactiva_y_elimina_lugares(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('rrhh.lugares.store'), ['nombre' => 'Tienda Surco', 'tipo' => 'tienda', 'latitud' => -12.1, 'longitud' => -77.0, 'radio_m' => 100])->assertSessionHasNoErrors();
        $l = LugarTrabajo::firstOrFail();
        $this->assertTrue($l->activo);

        $this->put(route('rrhh.lugares.update', $l), ['nombre' => 'Tienda Surco 2', 'tipo' => 'tienda', 'latitud' => -12.1, 'longitud' => -77.0, 'radio_m' => 120, 'activo' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($l->fresh()->activo);
        $this->assertSame(120, $l->fresh()->radio_m);

        $this->get(route('rrhh.lugares.index'))->assertOk()->assertSee('Tienda Surco 2')->assertSee('Inactivo');
        $this->delete(route('rrhh.lugares.destroy', $l))->assertRedirect();
        $this->assertSame(0, LugarTrabajo::count());
    }

    public function test_los_lugares_validan_coordenadas_radio_y_tipo(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('rrhh.lugares.store'), ['nombre' => '', 'tipo' => 'castillo', 'latitud' => 200, 'longitud' => 'x', 'radio_m' => 5])->assertSessionHasErrors(['nombre', 'tipo', 'latitud', 'longitud', 'radio_m']);
        $this->assertSame(0, LugarTrabajo::count());
    }

    // ── Evaluaciones ──

    public function test_se_registra_una_nota_de_0_a_20_sin_tocar_el_sueldo(): void
    {
        $e = $this->empleado();
        $this->actingAs($this->admin);

        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => '2026-11', 'nota' => 17.5, 'comentario' => 'Excelente trimestre', 'aumento' => 150])->assertSessionHasNoErrors();

        $ev = EvaluacionEmpleado::firstOrFail();
        $this->assertEquals(17.5, $ev->nota);
        $this->assertEquals(150.0, $ev->aumento);
        $this->assertNull($ev->sueldo_nuevo, 'decidido pero no aplicado');
        $this->assertEquals(3000.0, (float) $e->fresh()->sueldo_basico);
        $this->assertSame($this->admin->id, $ev->evaluado_por);
    }

    public function test_el_aumento_se_aplica_al_sueldo_solo_si_administracion_lo_pide(): void
    {
        $e = $this->empleado();
        $this->actingAs($this->admin);

        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => 'Segundo trimestre', 'nota' => 12, 'aumento' => 200, 'aplicar' => 1])->assertSessionHas('success');

        $this->assertEquals(3200.0, (float) $e->fresh()->sueldo_basico);
        $ev = EvaluacionEmpleado::firstOrFail();
        $this->assertEquals(3000.0, $ev->sueldo_anterior);
        $this->assertEquals(3200.0, $ev->sueldo_nuevo);
        $this->assertStringContainsString('ya está en el sueldo básico', session('success'));
    }

    public function test_una_nota_alta_no_sube_el_sueldo_sola(): void
    {
        $e = $this->empleado();
        $this->actingAs($this->admin);

        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => '2026-11', 'nota' => 20, 'aplicar' => 1]);

        $this->assertEquals(3000.0, (float) $e->fresh()->sueldo_basico, 'sin monto de aumento no cambia nada');
    }

    public function test_la_nota_debe_estar_entre_0_y_20(): void
    {
        $e = $this->empleado();
        $this->actingAs($this->admin);

        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => 'x', 'nota' => 21])->assertSessionHasErrors('nota');
        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => 'x', 'nota' => -1])->assertSessionHasErrors('nota');
        $this->post(route('rrhh.evaluaciones.store', $e), ['nota' => 10])->assertSessionHasErrors('periodo');
        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => 'x', 'nota' => 0])->assertSessionHasNoErrors();
        $this->post(route('rrhh.evaluaciones.store', $e), ['periodo' => 'y', 'nota' => 20])->assertSessionHasNoErrors();
        $this->assertSame(2, EvaluacionEmpleado::count());
    }

    public function test_el_listado_y_la_ficha_de_evaluaciones_muestran_notas_promedio_y_niveles(): void
    {
        $e = $this->empleado();
        EvaluacionEmpleado::create(['empleado_id' => $e->id, 'periodo' => '2026-10', 'nota' => 12, 'evaluado_por' => $this->admin->id]);
        EvaluacionEmpleado::create(['empleado_id' => $e->id, 'periodo' => '2026-11', 'nota' => 18, 'aumento' => 100, 'sueldo_anterior' => 3000, 'sueldo_nuevo' => 3100, 'evaluado_por' => $this->admin->id]);
        $this->actingAs($this->admin);

        $this->get(route('rrhh.evaluaciones.index'))->assertOk()->assertSee('18.00')->assertSee('15.00')->assertSee('Evaluar');
        $this->get(route('rrhh.evaluaciones.show', $e))->assertOk()->assertSee('Sobresaliente')->assertSee('Regular')->assertSee('S/ 3,000.00', false)->assertSee('S/ 3,100.00', false)->assertSee('El aumento no se calcula con la nota');
    }

    public function test_se_elimina_una_evaluacion_pero_no_la_de_otro_empleado(): void
    {
        $e = $this->empleado();
        $otro = $this->empleado(['dni' => '555']);
        $ev = EvaluacionEmpleado::create(['empleado_id' => $e->id, 'periodo' => 'x', 'nota' => 10]);
        $this->actingAs($this->admin);

        $this->delete(route('rrhh.evaluaciones.destroy', [$otro, $ev]))->assertNotFound();
        $this->delete(route('rrhh.evaluaciones.destroy', [$e, $ev]))->assertRedirect();
        $this->assertSame(0, EvaluacionEmpleado::count());
    }

    // ── Ficha del trabajador ──

    public function test_la_ficha_guarda_las_reglas_de_descuento_y_bonificacion(): void
    {
        $this->actingAs($this->admin);
        $datos = ['nombres' => 'Ana', 'apellidos' => 'Rojas', 'dni' => '11112222', 'cargo' => 'Vendedora', 'tipo_contrato' => 'indefinido', 'fecha_ingreso' => '2026-01-05',
            'hora_entrada' => '08:00', 'hora_salida' => '18:00', 'estado' => 'activo', 'sueldo_basico' => 2500, 'horas_jornada' => 8, 'tolerancia_min' => 5,
            'tardanza_modo' => 'por_minuto', 'tardanza_valor' => 0.4, 'falta_modo' => 'monto', 'falta_valor' => 90, 'extra_modo' => 'factor', 'extra_valor' => 1.35, 'extra_minimo_min' => 45];

        $this->post(route('rrhh.empleados.store'), $datos)->assertSessionHasNoErrors();

        $e = Empleado::where('dni', '11112222')->firstOrFail();
        $this->assertSame(5, $e->tolerancia_min);
        $this->assertSame('por_minuto', $e->tardanza_modo);
        $this->assertEquals(0.4, (float) $e->tardanza_valor);
        $this->assertSame('monto', $e->falta_modo);
        $this->assertEquals(1.35, (float) $e->extra_valor);
        $this->assertSame(45, $e->extra_minimo_min);
        $this->get(route('rrhh.empleados.edit', $e))->assertOk()->assertSee('Cuentan desde')->assertSee('value="1.35"', false);
    }

    public function test_la_ficha_rechaza_modos_y_valores_invalidos(): void
    {
        $this->actingAs($this->admin);
        $datos = ['nombres' => 'Ana', 'apellidos' => 'Rojas', 'dni' => '11112222', 'cargo' => 'Vendedora', 'tipo_contrato' => 'indefinido', 'fecha_ingreso' => '2026-01-05',
            'hora_entrada' => '08:00', 'hora_salida' => '18:00', 'estado' => 'activo', 'horas_jornada' => 30, 'tardanza_modo' => 'castigo', 'falta_modo' => 'dia', 'extra_modo' => 'factor', 'extra_minimo_min' => 9999];

        $this->post(route('rrhh.empleados.store'), $datos)->assertSessionHasErrors(['horas_jornada', 'tardanza_modo', 'extra_minimo_min']);
    }
}
