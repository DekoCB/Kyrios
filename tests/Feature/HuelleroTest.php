<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use App\Services\HuelleroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Asistencia desde el huellero ZKTeco: archivo del reloj (USB) y API con token para un puente. */
class HuelleroTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empleado $valeria;
    private Empleado $mario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->valeria = $this->empleado('Valeria', '11111111', '35');
        $this->mario = $this->empleado('Mario', '22222222', '0007');
    }

    private function empleado(string $nombre, string $dni, ?string $pin): Empleado
    {
        return Empleado::create([
            'nombres' => $nombre, 'apellidos' => 'Prueba', 'dni' => $dni, 'cargo' => 'Asesor', 'tipo_contrato' => 'indefinido', 'fecha_ingreso' => '2025-01-10',
            'hora_entrada' => '08:30:00', 'hora_salida' => '18:00:00', 'estado' => 'activo', 'codigo_huellero' => $pin,
        ]);
    }

    /** Formatos reales que exportan los relojes: tabulaciones con estado y verificación, y variantes de fecha. */
    private function archivo(): string
    {
        return implode("\n", [
            "35\t2026-11-16 08:25:10\t1\t0\t0\t0",
            "35\t2026-11-16 08:25:40\t1\t0\t0\t0",     // doble toque casi seguido
            "35\t2026-11-16 18:05:00\t1\t1\t0\t0",
            "7\t2026-11-16 08:51:00\t1\t0\t0\t0",     // 0007 en el sistema, 7 en el reloj: tarde (21 min > 10 de tolerancia)
            "99\t2026-11-16 09:00:00\t1\t0\t0\t0",    // número que nadie tiene
            "PIN\tFecha\tEstado",                      // encabezado
            "",
        ]);
    }

    public function test_el_lector_entiende_los_formatos_del_reloj(): void
    {
        $s = app(HuelleroService::class);

        $r = $s->leerArchivo($this->archivo());
        $this->assertCount(5, $r['marcaciones']);
        $this->assertSame(1, $r['ignoradas'], 'el encabezado se ignora');
        $this->assertSame(['pin' => '35', 'fecha_hora' => '2026-11-16 08:25:10'], $r['marcaciones'][0]);

        $b = $s->leerArchivo("00035,17/11/2026 08:31:05,1\n  35  ;  2026/11/17 18:00 ; 0\n\xEF\xBB\xBF");
        $this->assertSame('2026-11-17 08:31:05', $b['marcaciones'][0]['fecha_hora']);
        $this->assertSame('2026-11-17 18:00:00', $b['marcaciones'][1]['fecha_hora']);

        $this->assertCount(0, $s->leerArchivo("sin datos\nni fechas")['marcaciones']);
        $this->assertCount(0, $s->leerArchivo("35\t2026-13-45 99:99:99")['marcaciones'], 'fecha imposible');
    }

    public function test_la_primera_marcacion_es_la_entrada_y_la_ultima_la_salida(): void
    {
        $lectura = app(HuelleroService::class)->leerArchivo($this->archivo());
        $r = app(HuelleroService::class)->importar($lectura['marcaciones'], $this->admin->id);

        $this->assertSame(2, $r['nuevos']);
        $this->assertSame(['99' => 1], $r['desconocidos']);

        $a = Asistencia::where('empleado_id', $this->valeria->id)->firstOrFail();
        $this->assertSame('08:25:10', $a->hora_entrada);
        $this->assertSame('18:05:00', $a->hora_salida);
        $this->assertSame('puntual', $a->estado);
        $this->assertSame('huellero', $a->origen);

        $m = Asistencia::where('empleado_id', $this->mario->id)->firstOrFail();
        $this->assertSame('08:51:00', $m->hora_entrada);
        $this->assertNull($m->hora_salida, 'una sola marcación no cuenta como salida');
        $this->assertSame('tardanza', $m->estado);
        $this->assertSame(21, $m->minutos_tarde);
    }

    public function test_dos_toques_seguidos_no_cuentan_como_salida(): void
    {
        $r = app(HuelleroService::class)->importar([
            ['pin' => '35', 'fecha_hora' => '2026-11-16 08:25:00'], ['pin' => '35', 'fecha_hora' => '2026-11-16 08:27:00'],
        ]);
        $this->assertSame(1, $r['nuevos']);
        $this->assertNull(Asistencia::where('empleado_id', $this->valeria->id)->first()->hora_salida);
    }

    public function test_importar_dos_veces_el_mismo_archivo_no_duplica_ni_cambia_nada(): void
    {
        $s = app(HuelleroService::class);
        $m = $s->leerArchivo($this->archivo())['marcaciones'];
        $s->importar($m);
        $r = $s->importar($m);

        $this->assertSame(0, $r['nuevos']);
        $this->assertSame(0, $r['actualizados']);
        $this->assertSame(2, $r['sin_cambios']);
        $this->assertSame(2, Asistencia::count());
    }

    public function test_no_pisa_permisos_ni_vacaciones_y_conserva_una_salida_manual(): void
    {
        Asistencia::create(['empleado_id' => $this->valeria->id, 'fecha' => '2026-11-16', 'estado' => 'vacaciones', 'origen' => 'manual']);
        $r = app(HuelleroService::class)->importar([['pin' => '35', 'fecha_hora' => '2026-11-16 08:25:00'], ['pin' => '35', 'fecha_hora' => '2026-11-16 18:00:00']]);
        $this->assertSame(1, $r['omitidos']);
        $this->assertSame('vacaciones', Asistencia::where('empleado_id', $this->valeria->id)->first()->estado);

        // Una salida cargada a mano más tardía se respeta
        Asistencia::create(['empleado_id' => $this->mario->id, 'fecha' => '2026-11-17', 'hora_entrada' => '09:00:00', 'hora_salida' => '19:30:00', 'estado' => 'puntual', 'origen' => 'manual']);
        app(HuelleroService::class)->importar([['pin' => '7', 'fecha_hora' => '2026-11-17 08:20:00'], ['pin' => '7', 'fecha_hora' => '2026-11-17 18:00:00']]);
        $a = Asistencia::where('empleado_id', $this->mario->id)->first();
        $this->assertSame('08:20:00', $a->hora_entrada);
        $this->assertSame('19:30:00', $a->hora_salida);
    }

    public function test_el_flujo_de_pantalla_sube_revisa_e_importa(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('rrhh.asistencia.huellero'))->assertOk()->assertSee('Importar el archivo del reloj')->assertSee('Quién es quién');

        $r = $this->post(route('rrhh.asistencia.huellero.revisar'), ['archivo' => UploadedFile::fake()->createWithContent('1_attlog.dat', $this->archivo())])->assertOk();
        $r->assertSee('Revise antes de importar')->assertSee('Valeria Prueba')->assertSee('N.° 99');
        $this->assertSame(0, Asistencia::count(), 'revisar no guarda nada');

        preg_match('/name="token" value="([0-9a-f-]{36})"/', $r->getContent(), $m);
        $this->post(route('rrhh.asistencia.huellero.importar'), ['token' => $m[1]])->assertRedirect(route('rrhh.asistencia.huellero'))->assertSessionHas('success');
        $this->assertSame(2, Asistencia::count());

        // El mismo enlace no se puede usar dos veces
        $this->post(route('rrhh.asistencia.huellero.importar'), ['token' => $m[1]])->assertSessionHas('error');
    }

    public function test_un_archivo_sin_marcaciones_se_rechaza_con_un_mensaje(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('rrhh.asistencia.huellero.revisar'), ['archivo' => UploadedFile::fake()->createWithContent('x.txt', "nada que ver\n")])->assertSessionHas('error');
    }

    public function test_solo_administracion_ve_el_huellero(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));
        $this->get(route('rrhh.asistencia.huellero'))->assertForbidden();
    }

    public function test_la_api_del_puente_exige_token_y_esta_apagada_sin_el(): void
    {
        $cuerpo = ['marcaciones' => [['pin' => '35', 'fecha_hora' => '2026-11-16 08:25:00'], ['pin' => '35', 'fecha_hora' => '2026-11-16 18:00:00']]];

        config(['kyrios.rrhh.huellero_token' => null]);
        $this->postJson('/api/huellero/marcaciones', $cuerpo)->assertNotFound();

        config(['kyrios.rrhh.huellero_token' => 'secreto-largo-123']);
        $this->postJson('/api/huellero/marcaciones', $cuerpo)->assertUnauthorized();
        $this->postJson('/api/huellero/marcaciones', $cuerpo, ['Authorization' => 'Bearer otro'])->assertUnauthorized();
        $this->assertSame(0, Asistencia::count());

        $this->postJson('/api/huellero/marcaciones', $cuerpo, ['Authorization' => 'Bearer secreto-largo-123'])
            ->assertOk()->assertJson(['ok' => true, 'nuevos' => 1]);
        $this->assertSame('18:00:00', Asistencia::first()->hora_salida);

        $this->postJson('/api/huellero/marcaciones', ['marcaciones' => [['pin' => '35', 'fecha_hora' => 'ayer']]], ['Authorization' => 'Bearer secreto-largo-123'])->assertStatus(422);
    }
}
