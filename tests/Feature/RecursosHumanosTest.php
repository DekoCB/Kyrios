<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\SolicitudRrhh;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Recursos humanos: personal, asistencia y solicitudes. */
class RecursosHumanosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kyrios.rrhh.exigir_foto_ubicacion' => false]);   // la selfie y la ubicación se prueban en RrhhPlanillaNotasYUbicacionTest
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
            'nombres' => 'Valeria', 'apellidos' => 'Mendoza', 'dni' => '12345678', 'cargo' => 'Asesora comercial', 'area' => 'Ventas',
            'tipo_contrato' => 'indefinido', 'fecha_ingreso' => '2025-01-10', 'hora_entrada' => '08:30:00', 'hora_salida' => '18:00:00', 'estado' => 'activo',
        ]);
    }

    private function datosEmpleado(array $extra = []): array
    {
        return $extra + [
            'nombres' => 'Mafer', 'apellidos' => 'Quispe', 'dni' => '87654321', 'cargo' => 'Diseñadora', 'area' => 'Diseño', 'tipo_contrato' => 'plazo_fijo',
            'fecha_ingreso' => '2026-01-05', 'fecha_fin_contrato' => today()->addDays(10)->toDateString(), 'hora_entrada' => '09:00', 'hora_salida' => '18:00',
            'estado' => 'activo', 'sueldo_basico' => 1800, 'fecha_nacimiento' => '1995-06-15',
            'horas_jornada' => 8, 'tardanza_modo' => 'proporcional', 'falta_modo' => 'dia', 'extra_modo' => 'factor', 'extra_valor' => 1.25, 'extra_minimo_min' => 30,
        ];
    }

    // ── Personal ─────────────────────────────────────────────────────────────

    public function test_administracion_registra_edita_y_cesa_personal(): void
    {
        $this->actingAs($this->admin);
        $datos = $this->datosEmpleado();
        $datos['fecha_nacimiento'] = '1995-' . today()->format('m') . '-15';

        $this->post(route('rrhh.empleados.store'), $datos)->assertSessionHasNoErrors();
        $e = Empleado::where('dni', '87654321')->firstOrFail();
        $this->assertSame('EMP-001', $e->codigo);
        $this->assertTrue($e->contrato_por_vencer);
        $this->assertTrue($e->cumple_este_mes);

        $this->get(route('rrhh.empleados.index'))->assertOk()->assertSee('Mafer Quispe')->assertSee('Vence');
        $this->get(route('rrhh.empleados.show', $e))->assertOk()->assertSee('Diseñadora');
        $this->get(route('rrhh.empleados.edit', $e))->assertOk();

        $this->put(route('rrhh.empleados.update', $e), array_merge($datos, ['cargo' => 'Jefa de diseño']))->assertSessionHasNoErrors();
        $this->assertSame('Jefa de diseño', $e->fresh()->cargo);

        $this->post(route('rrhh.empleados.cesar', $e), ['fecha_cese' => today()->toDateString(), 'motivo_cese' => 'Renuncia'])->assertSessionHasNoErrors();
        $this->assertSame('cesado', $e->fresh()->estado);
        $this->post(route('rrhh.empleados.reactivar', $e));
        $this->assertSame('activo', $e->fresh()->estado);
    }

    public function test_dni_unico_y_horario_coherente(): void
    {
        $this->actingAs($this->admin);
        $this->empleado();

        $this->post(route('rrhh.empleados.store'), $this->datosEmpleado(['dni' => '12345678']))->assertSessionHasErrors('dni');
        $this->post(route('rrhh.empleados.store'), $this->datosEmpleado(['dni' => '1234']))->assertSessionHasErrors('dni');
        $this->post(route('rrhh.empleados.store'), $this->datosEmpleado(['hora_entrada' => '19:00', 'hora_salida' => '18:00']))->assertSessionHasErrors('hora_salida');
        $this->assertSame(1, Empleado::count());
    }

    public function test_solo_administracion_gestiona_personal(): void
    {
        $e = $this->empleado();
        foreach (['Contador', 'Logística', 'Operaciones', 'Cliente'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('rrhh.empleados.index'))->assertForbidden();
            $this->get(route('rrhh.empleados.show', $e))->assertForbidden();
            $this->post(route('rrhh.empleados.store'), $this->datosEmpleado())->assertForbidden();
            $this->get(route('rrhh.asistencia.control'))->assertForbidden();
            $this->get(route('rrhh.solicitudes.index'))->assertForbidden();
        }
    }

    public function test_documentos_del_legajo_son_privados(): void
    {
        $this->actingAs($this->admin);
        $e = $this->empleado();

        $r = $this->post(route('rrhh.documentos.store', $e), ['tipo' => 'contrato', 'archivo' => UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf')]);
        $r->assertSessionHasNoErrors()->assertSessionHas('success');
        $doc = $e->documentos()->firstOrFail();
        Storage::disk('local')->assertExists($doc->ruta);
        $this->get(route('rrhh.documentos.descargar', $doc))->assertOk();

        $this->actingAs($this->usuario('Logística'));
        $this->get(route('rrhh.documentos.descargar', $doc))->assertForbidden();

        $this->actingAs($this->admin);
        $this->delete(route('rrhh.documentos.destroy', $doc));
        Storage::disk('local')->assertMissing($doc->ruta);
        $this->post(route('rrhh.documentos.store', $e), ['tipo' => 'contrato', 'archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('archivo');
    }

    // ── Asistencia ───────────────────────────────────────────────────────────

    public function test_marcar_entrada_puntual_con_tolerancia_y_tardanza(): void
    {
        $user = $this->usuario('Operaciones');
        $e = $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        Carbon::setTestNow('2026-11-16 08:38:00'); // lunes, 8 min tarde: dentro de la tolerancia
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHas('success');
        $a = Asistencia::firstOrFail();
        $this->assertSame('puntual', $a->estado);
        $this->assertSame(0, $a->minutos_tarde);

        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHas('error');

        Carbon::setTestNow('2026-11-17 09:05:00'); // martes, 35 min tarde
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHas('success');
        $b = Asistencia::whereDate('fecha', '2026-11-17')->firstOrFail();
        $this->assertSame('tardanza', $b->estado);
        $this->assertSame(35, $b->minutos_tarde);
    }

    public function test_la_salida_exige_entrada_y_calcula_horas(): void
    {
        $user = $this->usuario('Logística');
        $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        Carbon::setTestNow('2026-11-16 18:00:00');
        $this->post(route('rrhh.marcar'), ['accion' => 'salida'])->assertSessionHas('error');

        Carbon::setTestNow('2026-11-16 08:30:00');
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada']);
        Carbon::setTestNow('2026-11-16 17:30:00');
        $this->post(route('rrhh.marcar'), ['accion' => 'salida'])->assertSessionHas('success');
        $this->assertSame(9.0, Asistencia::firstOrFail()->horas);
        $this->post(route('rrhh.marcar'), ['accion' => 'salida'])->assertSessionHas('error');

        $this->get(route('rrhh.mi-asistencia'))->assertOk()->assertSee('Mi historial');
    }

    public function test_sin_ficha_vinculada_no_puede_marcar(): void
    {
        $this->actingAs($this->usuario('Contador'));
        $this->post(route('rrhh.marcar'), ['accion' => 'entrada'])->assertSessionHas('error');
        $this->get(route('rrhh.mi-asistencia'))->assertOk()->assertSee('no está vinculado');
        $this->assertSame(0, Asistencia::count());
    }

    public function test_el_administrador_registra_corrige_y_marca_faltas(): void
    {
        $this->actingAs($this->admin);
        $e1 = $this->empleado();
        $e2 = $this->empleado(['nombres' => 'Luis', 'dni' => '11112222']);
        Carbon::setTestNow('2026-11-18 12:00:00'); // miércoles

        $this->post(route('rrhh.asistencia.registrar', $e1), ['fecha' => '2026-11-18', 'estado' => 'puntual', 'hora_entrada' => '09:20', 'hora_salida' => '18:05'])->assertSessionHasNoErrors();
        $a = Asistencia::where('empleado_id', $e1->id)->firstOrFail();
        $this->assertSame('tardanza', $a->estado, 'la tardanza se calcula sola');
        $this->assertSame(50, $a->minutos_tarde);

        $this->post(route('rrhh.asistencia.registrar', $e1), ['fecha' => '2026-11-18', 'estado' => 'puntual', 'hora_entrada' => '10:00', 'hora_salida' => '09:00'])->assertSessionHas('error');

        $this->get(route('rrhh.asistencia.control', ['fecha' => '2026-11-18']))->assertOk()->assertSee('Valeria')->assertSee('Luis');

        $this->post(route('rrhh.asistencia.faltas'), ['fecha' => '2026-11-18'])->assertSessionHas('success');
        $this->assertSame('falta', Asistencia::where('empleado_id', $e2->id)->firstOrFail()->estado);
        $this->assertSame('tardanza', Asistencia::where('empleado_id', $e1->id)->firstOrFail()->estado, 'quien ya marcó no se toca');

        $this->post(route('rrhh.asistencia.faltas'), ['fecha' => '2026-11-15'])->assertSessionHas('error'); // domingo
    }

    public function test_reporte_mensual_y_exportacion(): void
    {
        $this->actingAs($this->admin);
        $e = $this->empleado();
        Carbon::setTestNow('2026-11-30 12:00:00');
        $this->post(route('rrhh.asistencia.registrar', $e), ['fecha' => '2026-11-16', 'estado' => 'puntual', 'hora_entrada' => '08:30', 'hora_salida' => '17:30']);
        $this->post(route('rrhh.asistencia.registrar', $e), ['fecha' => '2026-11-17', 'estado' => 'puntual', 'hora_entrada' => '09:30', 'hora_salida' => '18:30']);
        $this->post(route('rrhh.asistencia.registrar', $e), ['fecha' => '2026-11-18', 'estado' => 'falta']);

        $this->get(route('rrhh.asistencia.reporte', ['mes' => '2026-11']))->assertOk()->assertSee('Valeria');
        $csv = $this->get(route('rrhh.asistencia.exportar', ['mes' => '2026-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Valeria Mendoza', $csv);
        $this->assertStringContainsString(';2;1;60;1;', $csv, '2 asistidos, 1 tardanza, 60 min, 1 falta');
    }

    // ── Solicitudes ──────────────────────────────────────────────────────────

    public function test_la_solicitud_aprobada_registra_los_dias_laborables_en_la_asistencia(): void
    {
        $user = $this->usuario('Operaciones');
        $e = $this->empleado(['user_id' => $user->id]);
        $this->actingAs($user);

        // Viernes 20 al martes 24: laborables vie, lun y mar
        $this->post(route('rrhh.solicitudes.store'), ['tipo' => 'vacaciones', 'desde' => '2026-11-20', 'hasta' => '2026-11-24', 'motivo' => 'Viaje'])->assertSessionHas('success');
        $s = SolicitudRrhh::firstOrFail();
        $this->assertSame(1, $this->admin->unreadNotifications()->count());

        // No se cruzan dos solicitudes
        $this->post(route('rrhh.solicitudes.store'), ['tipo' => 'permiso', 'desde' => '2026-11-23', 'hasta' => '2026-11-23'])->assertSessionHas('error');
        $this->get(route('rrhh.mis-solicitudes'))->assertOk()->assertSee('Vacaciones');

        $this->actingAs($this->admin);
        $this->get(route('rrhh.solicitudes.index'))->assertOk()->assertSee('Valeria');
        $this->post(route('rrhh.solicitudes.aprobar', $s))->assertSessionHas('success');
        $this->assertSame('aprobada', $s->fresh()->estado);
        $this->assertSame(['2026-11-20', '2026-11-23', '2026-11-24'], Asistencia::where('empleado_id', $e->id)->orderBy('fecha')->get()->map(fn ($a) => $a->fecha->toDateString())->all());
        $this->assertSame('vacaciones', Asistencia::where('empleado_id', $e->id)->first()->estado);
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->post(route('rrhh.solicitudes.aprobar', $s))->assertSessionHas('error');
    }

    public function test_rechazar_una_solicitud_exige_motivo(): void
    {
        $e = $this->empleado();
        $s = SolicitudRrhh::create(['empleado_id' => $e->id, 'tipo' => 'permiso', 'desde' => '2026-11-25', 'hasta' => '2026-11-25']);
        $this->actingAs($this->admin);

        $this->post(route('rrhh.solicitudes.rechazar', $s), [])->assertSessionHasErrors('motivo');
        $this->post(route('rrhh.solicitudes.rechazar', $s), ['motivo' => 'Hay cierre de mes'])->assertSessionHas('success');
        $this->assertSame('rechazada', $s->fresh()->estado);
        $this->assertSame(0, Asistencia::count());
    }

    // ── Ficha del colaborador (formato oficial de ingreso) ───────────────────

    private function datosFicha(): array
    {
        return $this->datosEmpleado() + [
            'nacionalidad' => 'Peruana', 'grado_instruccion' => 'Superior completo', 'lugar_nacimiento' => 'Lima', 'tipo_sangre' => 'O+',
            'domicilio_alterno' => 'Jr. Cusco 456', 'referencia_domiciliaria' => 'Frente al parque', 'jefe_contrata' => 'Piero Rojas',
            'centro_costos' => 'CC-VENTAS', 'periodo_prueba' => '3 meses', 'tipo_remuneracion' => 'empleado', 'modalidad_pago' => 'tiempo_completo',
            'local_trabajo' => 'san_isidro', 'calificacion' => 'control_asistencia', 'codigo_huellero' => '35',
            'puesto_solicitado' => 'Diseñadora de iluminación', 'sueldo_minimo' => 1700, 'viajaria_provincias' => '1', 'trabajo_antes_kyrios' => '0',
            'educacion' => [
                ['nivel' => 'Superior', 'centro' => 'PUCP', 'inicio' => '2013', 'fin' => '2018'],
                ['nivel' => 'Técnico', 'centro' => '', 'inicio' => '', 'fin' => ''],
                ['nivel' => 'Secundaria', 'centro' => 'Colegio Nacional', 'inicio' => '2007', 'fin' => '2012'],
            ],
            'profesion' => 'Arquitecta', 'profesion_condicion' => 'titulado',
            'idiomas' => [['idioma' => 'Inglés', 'habla' => 'intermedio', 'lee' => 'avanzado', 'escribe' => 'intermedio'], ['idioma' => '', 'habla' => '', 'lee' => '', 'escribe' => '']],
            'software' => [['nombre' => 'AutoCAD', 'nivel' => 'avanzado'], ['nombre' => 'Dialux', 'nivel' => 'intermedio']],
            'otros_estudios' => ['Curso de iluminación LED', ''],
            'experiencia' => [['empresa' => 'Luminotecnia SAC', 'telefono' => '999', 'cargo' => 'Diseñadora', 'jefe' => 'Ana', 'ingreso' => '01/2019', 'salida' => '12/2025', 'remuneracion' => '1600', 'funciones' => 'Proyectos', 'motivo_cese' => 'Mejor oferta', 'consultar' => '1']],
            'dependientes' => [['nombres' => 'Luis Quispe', 'parentesco' => 'Hijo', 'dni' => '70000001', 'nacimiento' => '2020-02-02', 'centro' => 'Nido', 'ocupacion' => 'Estudiante']],
            'referencias' => [['nombres' => 'Rosa Díaz', 'empresa' => 'Estudio RD', 'cargo' => 'Gerente', 'telefono' => '988', 'tiempo' => '5 años']],
            'posee_cuenta_sueldo' => '1', 'banco' => 'BBVA', 'numero_cuenta' => '0011-0000-1234', 'moneda_pago' => 'PEN',
            'pension_situacion' => 'afiliado_afp', 'sistema_pension' => 'AFP', 'afp_nombre' => 'Integra',
            'declaraciones' => ['ficha' => '2026-01-05', 'antecedentes' => '2026-01-05', 'salud' => ''],
            'vida_ley_extrabajador' => '0', 'sin_enfermedades' => '1',
        ];
    }

    public function test_se_registra_la_ficha_completa_y_se_descartan_las_filas_vacias(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('rrhh.empleados.store'), $this->datosFicha())->assertSessionHasNoErrors();

        $e = Empleado::where('dni', '87654321')->firstOrFail();
        $this->assertSame('Peruana', $e->nacionalidad);
        $this->assertSame('O+', $e->tipo_sangre);
        $this->assertSame('35', $e->codigo_huellero);
        $this->assertTrue($e->viajaria_provincias);
        $this->assertFalse($e->trabajo_antes_kyrios);
        $this->assertCount(2, $e->educacion, 'el nivel Técnico sin datos no se guarda');
        $this->assertSame('Superior', $e->educacion[0]['nivel']);
        $this->assertCount(1, $e->idiomas);
        $this->assertCount(2, $e->software);
        $this->assertSame(['Curso de iluminación LED'], $e->otros_estudios);
        $this->assertSame('Luminotecnia SAC', $e->experiencia[0]['empresa']);
        $this->assertSame(['ficha' => '2026-01-05', 'antecedentes' => '2026-01-05'], $e->declaraciones);
        $this->assertContains('Declaración de salud', $e->declaraciones_pendientes);
        $this->assertGreaterThan(60, $e->ficha_completa);
    }

    public function test_el_codigo_del_huellero_no_se_repite(): void
    {
        $this->actingAs($this->admin);
        $this->empleado(['codigo_huellero' => '35']);

        $this->post(route('rrhh.empleados.store'), $this->datosFicha())->assertSessionHasErrors('codigo_huellero');
    }

    public function test_la_ficha_se_ve_se_edita_y_sale_en_pdf(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('rrhh.empleados.store'), $this->datosFicha());
        $e = Empleado::where('dni', '87654321')->firstOrFail();

        $this->get(route('rrhh.empleados.show', $e))->assertOk()->assertSee('Ficha del colaborador')->assertSee('PUCP')->assertSee('Luminotecnia SAC')->assertSee('CC-VENTAS');
        $this->get(route('rrhh.empleados.edit', $e))->assertOk()->assertSee('Declaraciones')->assertSee('Código en el huellero');
        $this->get(route('rrhh.empleados.create'))->assertOk();

        $r = $this->get(route('rrhh.empleados.ficha-pdf', $e))->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());

        // Editar sin tocar la ficha no pierde nada
        $this->put(route('rrhh.empleados.update', $e), $this->datosFicha())->assertSessionHasNoErrors();
        $this->assertSame('Superior', $e->fresh()->educacion[0]['nivel']);
    }

    public function test_la_foto_se_reduce_y_solo_la_ve_administracion(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('rrhh.empleados.store'), $this->datosFicha() + ['foto' => UploadedFile::fake()->image('foto.jpg', 1600, 1200)])->assertSessionHasNoErrors();
        $e = Empleado::where('dni', '87654321')->firstOrFail();

        $this->assertNotNull($e->foto);
        Storage::disk('local')->assertExists($e->foto);
        $this->get(route('rrhh.empleados.foto', $e))->assertOk();
        $this->get(route('rrhh.empleados.ficha-pdf', $e))->assertOk();

        $this->actingAs($this->usuario('Logística'));
        $this->get(route('rrhh.empleados.foto', $e))->assertForbidden();
        $this->get(route('rrhh.empleados.ficha-pdf', $e))->assertForbidden();
    }
}
