<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tablero de tareas diarias del equipo. */
class TareasDiariasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $valeria;
    private User $mafer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración', 'Daniel Admin');
        $this->valeria = $this->usuario('Operaciones', 'Valeria Soto');
        $this->mafer = $this->usuario('Logística', 'Mafer Quispe');
    }

    private function usuario(string $rol, string $nombre): User
    {
        return User::factory()->create(['name' => $nombre, 'role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function crear(User $para, string $titulo = 'Llamar al cliente', array $extra = []): Tarea
    {
        return Tarea::create($extra + ['titulo' => $titulo, 'asignado_a' => $para->id, 'creado_por' => $para->id, 'fecha' => today(), 'prioridad' => 'media', 'estado' => 'pendiente']);
    }

    public function test_el_tablero_muestra_una_columna_por_persona_y_sus_tareas(): void
    {
        $this->crear($this->valeria, 'Seguimiento a cliente 133');
        $this->crear($this->mafer, 'Contactar encargados HV');

        $this->actingAs($this->valeria)->get(route('tareas.index'))->assertOk()
            ->assertSee('Seguimiento a cliente 133')->assertSee('Contactar encargados HV')->assertSee('Mafer Quispe')->assertSee('Daniel Admin');
    }

    public function test_el_cliente_no_ve_el_tablero(): void
    {
        $this->actingAs($this->usuario('Cliente', 'Cliente Uno'))->get(route('tareas.index'))->assertForbidden();
    }

    public function test_cada_persona_crea_sus_tareas_y_solo_el_admin_asigna_a_otros(): void
    {
        $this->actingAs($this->valeria);
        $r = $this->postJson(route('tareas.store'), ['titulo' => 'Preparar cotización', 'fecha' => today()->toDateString(), 'prioridad' => 'alta'])->assertCreated();
        $this->assertSame($this->valeria->id, $r->json('tarea.asignado_a'));
        $this->assertSame('alta', $r->json('tarea.prioridad'));

        $this->postJson(route('tareas.store'), ['titulo' => 'Para Mafer', 'asignado_a' => $this->mafer->id, 'fecha' => today()->toDateString()])->assertForbidden();

        $this->actingAs($this->admin);
        $this->postJson(route('tareas.store'), ['titulo' => 'Para Mafer', 'asignado_a' => $this->mafer->id, 'fecha' => today()->toDateString()])->assertCreated();
        $this->assertSame(1, Tarea::where('asignado_a', $this->mafer->id)->count());

        $this->postJson(route('tareas.store'), ['titulo' => '', 'fecha' => today()->toDateString()])->assertUnprocessable();
    }

    public function test_marcar_hecha_y_reabrir(): void
    {
        $t = $this->crear($this->valeria);
        $this->actingAs($this->valeria);

        $this->patchJson(route('tareas.update', $t), ['estado' => 'hecha'])->assertOk()->assertJsonPath('tarea.estado', 'hecha');
        $this->assertNotNull($t->fresh()->completada_en);
        $this->patchJson(route('tareas.update', $t), ['estado' => 'pendiente'])->assertOk();
        $this->assertNull($t->fresh()->completada_en);
    }

    public function test_nadie_toca_las_tareas_de_otros_salvo_el_administrador(): void
    {
        $t = $this->crear($this->valeria);

        $this->actingAs($this->mafer);
        $this->patchJson(route('tareas.update', $t), ['estado' => 'hecha'])->assertForbidden();
        $this->deleteJson(route('tareas.destroy', $t))->assertForbidden();
        $this->postJson(route('tareas.items.store', $t), ['texto' => 'Paso'])->assertForbidden();

        $this->actingAs($this->valeria);
        $this->patchJson(route('tareas.update', $t), ['asignado_a' => $this->mafer->id])->assertForbidden();

        $this->actingAs($this->admin);
        $this->patchJson(route('tareas.update', $t), ['asignado_a' => $this->mafer->id])->assertOk();
        $this->assertSame($this->mafer->id, $t->fresh()->asignado_a);
        $this->deleteJson(route('tareas.destroy', $t))->assertOk();
        $this->assertSame(0, Tarea::count());
    }

    public function test_la_lista_de_pasos_completa_la_tarea_sola(): void
    {
        $t = $this->crear($this->valeria);
        $this->actingAs($this->valeria);

        $a = $this->postJson(route('tareas.items.store', $t), ['texto' => 'Reunir fichas'])->assertCreated()->json('tarea.items.0.id');
        $b = $this->postJson(route('tareas.items.store', $t), ['texto' => 'Enviar por correo'])->json('tarea.items.1.id');

        $this->patchJson(route('tareas.items.update', $a), ['hecho' => true])->assertJsonPath('tarea.estado', 'en_progreso');
        $this->patchJson(route('tareas.items.update', $b), ['hecho' => true])->assertJsonPath('tarea.estado', 'hecha');
        $this->assertNotNull($t->fresh()->completada_en);

        $this->patchJson(route('tareas.items.update', $b), ['hecho' => false])->assertJsonPath('tarea.estado', 'en_progreso');
        $this->deleteJson(route('tareas.items.destroy', $b))->assertOk();
        $this->assertSame(1, $t->items()->count());
        $this->assertSame('hecha', $t->fresh()->estado, 'con el único paso restante hecho, la tarea queda hecha');
    }

    public function test_traer_pendientes_de_dias_anteriores(): void
    {
        $ayer = $this->crear($this->valeria, 'Pendiente de ayer', ['fecha' => today()->subDay()]);
        $hecha = $this->crear($this->valeria, 'Ya hecha', ['fecha' => today()->subDay(), 'estado' => 'hecha']);
        $ajena = $this->crear($this->mafer, 'De Mafer', ['fecha' => today()->subDay()]);

        $this->actingAs($this->valeria)->post(route('tareas.arrastrar'), ['hasta' => today()->toDateString()])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(today()->toDateString(), $ayer->fresh()->fecha->toDateString());
        $this->assertSame(today()->subDay()->toDateString(), $hecha->fresh()->fecha->toDateString());
        $this->assertSame(today()->subDay()->toDateString(), $ajena->fresh()->fecha->toDateString(), 'cada quien trae solo las suyas');

        $this->actingAs($this->admin)->post(route('tareas.arrastrar'), ['hasta' => today()->toDateString()]);
        $this->assertSame(today()->toDateString(), $ajena->fresh()->fecha->toDateString());
    }

    public function test_el_dashboard_muestra_las_tareas_de_hoy(): void
    {
        $this->crear($this->valeria, 'Entregar luminaria a cliente 85');
        $this->crear($this->mafer, 'Seguimiento Mazzini');

        $this->actingAs($this->valeria)->get(route('operaciones.dashboard'))->assertOk()->assertSee('Tareas de hoy')->assertSee('Entregar luminaria a cliente 85');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Tareas de hoy')->assertSee('Avance del equipo');
        $this->actingAs($this->mafer)->get(route('logistica.dashboard'))->assertOk()->assertSee('Seguimiento Mazzini');
    }
}
