<?php

namespace Tests\Feature;

use App\Models\CajaChica;
use App\Models\CajaChicaAdjunto;
use App\Models\CajaChicaMovimiento;
use App\Models\Role;
use App\Models\User;
use App\Services\CajaChicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CajaChicaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(CajaChicaService::DISCO);
        $this->admin = $this->usuario('Administrador');
        $this->tecnico = $this->usuario('Técnico');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function caja(float $monto = 100, ?User $resp = null): CajaChica
    {
        return app(CajaChicaService::class)->abrir([
            'tipo' => 'asignada', 'nombre' => 'Caja técnico', 'responsable_id' => ($resp ?? $this->tecnico)->id, 'monto_inicial' => $monto,
        ], $this->admin);
    }

    private function gasto(array $extra = [], bool $conFoto = true): array
    {
        return array_merge([
            'fecha' => today()->toDateString(), 'monto' => 30, 'categoria' => 'combustible', 'concepto' => 'Gasolina',
            'emisor' => 'Grifo Sol', 'tipo_comprobante' => 'boleta', 'numero_comprobante' => 'B001-55',
        ], $conFoto ? ['adjuntos' => [UploadedFile::fake()->image('foto.jpg', 1200, 900)]] : [], $extra);
    }

    public function test_el_admin_asigna_una_caja_con_dinero(): void
    {
        $this->actingAs($this->admin)->post(route('caja-chica.store'), [
            'tipo' => 'asignada', 'nombre' => 'Técnico octubre', 'responsable_id' => $this->tecnico->id, 'monto_inicial' => 100,
        ])->assertSessionHasNoErrors();

        $caja = CajaChica::first();
        $this->assertSame('CC-0001', $caja->codigo);
        $this->assertEquals(100.0, $caja->saldo);
    }

    public function test_solo_hay_una_caja_general_activa_y_la_asignada_exige_responsable(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('caja-chica.store'), ['tipo' => 'general', 'nombre' => 'General']);
        $this->post(route('caja-chica.store'), ['tipo' => 'general', 'nombre' => 'Otra'])->assertSessionHas('error');
        $this->post(route('caja-chica.store'), ['tipo' => 'asignada', 'nombre' => 'X'])->assertSessionHasErrors('responsable_id');
        $this->assertSame(1, CajaChica::count());
    }

    public function test_el_tecnico_sube_un_gasto_con_foto_y_queda_pendiente(): void
    {
        $caja = $this->caja();

        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto())->assertSessionHasNoErrors()->assertSessionHas('success');

        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();
        $this->assertSame('pendiente', $m->estado);
        $this->assertSame($this->tecnico->id, $m->user_id);
        $this->assertSame(1, $m->adjuntos->count());
        Storage::disk(CajaChicaService::DISCO)->assertExists($m->adjuntos->first()->ruta);
        $this->assertEquals(70.0, $caja->fresh()->saldo);
    }

    public function test_un_gasto_del_admin_nace_aprobado(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->admin)->post(route('caja-chica.gasto', $caja), $this->gasto());

        $this->assertSame('aprobado', CajaChicaMovimiento::where('tipo', 'gasto')->first()->estado);
    }

    public function test_un_comprobante_exige_archivo_salvo_que_sea_sin_comprobante(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);

        $this->post(route('caja-chica.gasto', $caja), $this->gasto([], false))->assertSessionHas('error');
        $this->assertSame(0, CajaChicaMovimiento::where('tipo', 'gasto')->count());

        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['tipo_comprobante' => 'sin_comprobante'], false))->assertSessionHas('success');
        $this->assertSame(1, CajaChicaMovimiento::where('tipo', 'gasto')->count());
    }

    public function test_se_acepta_pdf_escaneado_y_se_rechaza_otro_formato(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);

        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['adjuntos' => [UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf')]], false))
            ->assertSessionHasNoErrors();
        $this->assertSame('application/pdf', CajaChicaAdjunto::first()->mime);

        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-99', 'adjuntos' => [UploadedFile::fake()->create('virus.exe', 10)]], false))
            ->assertSessionHasErrors('adjuntos.0');
    }

    public function test_no_se_gasta_mas_que_el_saldo(): void
    {
        $caja = $this->caja(20);

        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto(['monto' => 50]))->assertSessionHas('error');
        $this->assertEquals(20.0, $caja->fresh()->saldo);
    }

    public function test_detecta_el_mismo_comprobante_cargado_dos_veces(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);

        $this->post(route('caja-chica.gasto', $caja), $this->gasto())->assertSessionHas('success');
        $this->post(route('caja-chica.gasto', $caja), $this->gasto())->assertSessionHas('error');

        $this->assertSame(1, CajaChicaMovimiento::where('tipo', 'gasto')->count());
    }

    public function test_revision_aprobar_observar_y_rechazar(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();

        $this->actingAs($this->tecnico)->post(route('caja-chica.revisar', $m), ['decision' => 'aprobar'])->assertForbidden();

        $this->actingAs($this->admin);
        $this->post(route('caja-chica.revisar', $m), ['decision' => 'observar'])->assertSessionHas('error'); // sin motivo
        $this->post(route('caja-chica.revisar', $m), ['decision' => 'observar', 'motivo' => 'Foto borrosa']);
        $this->assertSame('observado', $m->fresh()->estado);

        $this->post(route('caja-chica.revisar', $m), ['decision' => 'rechazar', 'motivo' => 'No corresponde']);
        $this->assertEquals(100.0, $caja->fresh()->saldo, 'un gasto rechazado no descuenta');

        $this->post(route('caja-chica.revisar', $m), ['decision' => 'aprobar']);
        $this->assertSame('aprobado', $m->fresh()->estado);
        $this->assertEquals(70.0, $caja->fresh()->saldo);
    }

    public function test_el_tecnico_corrige_un_gasto_observado_y_vuelve_a_revision(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();
        $this->actingAs($this->admin)->post(route('caja-chica.revisar', $m), ['decision' => 'observar', 'motivo' => 'Monto no coincide']);

        $this->actingAs($this->tecnico)->put(route('caja-chica.gasto.update', $m), $this->gasto(['monto' => 25], false))->assertSessionHasNoErrors();

        $m->refresh();
        $this->assertSame('pendiente', $m->estado);
        $this->assertEquals(25.0, (float) $m->monto);
        $this->assertNull($m->motivo_revision);
    }

    public function test_un_gasto_aprobado_ya_no_se_edita_ni_borra_por_el_tecnico(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();
        $this->actingAs($this->admin)->post(route('caja-chica.revisar', $m), ['decision' => 'aprobar']);

        $this->actingAs($this->tecnico);
        $this->get(route('caja-chica.gasto.edit', $m))->assertRedirect(route('caja-chica.show', $caja));
        $this->delete(route('caja-chica.movimiento.destroy', $m))->assertForbidden();
    }

    public function test_eliminar_un_gasto_borra_sus_archivos(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();
        $ruta = $m->adjuntos->first()->ruta;

        $this->delete(route('caja-chica.movimiento.destroy', $m))->assertSessionHas('success');

        $this->assertNull(CajaChicaMovimiento::find($m->id));
        Storage::disk(CajaChicaService::DISCO)->assertMissing($ruta);
    }

    public function test_cada_persona_solo_ve_su_caja_y_sus_archivos(): void
    {
        $caja = $this->caja();
        $otro = $this->usuario('Técnico');
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $adjunto = CajaChicaAdjunto::first();

        $this->actingAs($this->tecnico);
        $this->get(route('caja-chica.show', $caja))->assertOk();
        $this->get(route('caja-chica.adjunto', $adjunto))->assertOk();

        $this->actingAs($otro);
        $this->assertCount(0, $this->get(route('caja-chica.index'))->viewData('cajas'));
        $this->get(route('caja-chica.show', $caja))->assertForbidden();
        $this->get(route('caja-chica.adjunto', $adjunto))->assertForbidden();
        $this->post(route('caja-chica.gasto', $caja), $this->gasto())->assertForbidden();
        $this->get(route('caja-chica.rendicion', $caja))->assertForbidden();
    }

    public function test_solo_el_admin_crea_cajas_repone_y_cierra(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico);

        $this->get(route('caja-chica.create'))->assertForbidden();
        $this->post(route('caja-chica.ingreso', $caja), ['monto' => 50, 'concepto' => 'x', 'fecha' => today()->toDateString()])->assertForbidden();
        $this->post(route('caja-chica.cerrar', $caja))->assertForbidden();
    }

    public function test_reponer_dinero_y_cerrar_la_caja(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->admin);

        $this->post(route('caja-chica.ingreso', $caja), ['monto' => 50, 'concepto' => 'Reposición', 'fecha' => today()->toDateString()]);
        $this->assertEquals(150.0, $caja->fresh()->saldo);

        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['tipo_comprobante' => 'sin_comprobante'], false));
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto(['monto' => 5, 'numero_comprobante' => 'B001-1']));
        $this->actingAs($this->admin)->post(route('caja-chica.cerrar', $caja))->assertSessionHas('error'); // hay un gasto pendiente

        $m = CajaChicaMovimiento::where('estado', 'pendiente')->first();
        $this->post(route('caja-chica.revisar', $m), ['decision' => 'aprobar']);
        $this->post(route('caja-chica.cerrar', $caja))->assertSessionHas('success');

        $this->assertSame('cerrada', $caja->fresh()->estado);
        $this->post(route('caja-chica.gasto', $caja), $this->gasto(['numero_comprobante' => 'B001-2']))->assertSessionHas('error');
    }

    public function test_rendicion_pdf_y_exportacion(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());

        $pdf = $this->get(route('caja-chica.rendicion', $caja))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $csv = $this->get(route('caja-chica.exportar', $caja))->assertOk()->streamedContent();
        $this->assertStringContainsString('Gasolina', $csv);
        $this->assertStringContainsString('B001-55', $csv);
    }

    public function test_las_pantallas_cargan_para_admin_y_tecnico(): void
    {
        $caja = $this->caja();
        $this->actingAs($this->tecnico)->post(route('caja-chica.gasto', $caja), $this->gasto());
        $m = CajaChicaMovimiento::where('tipo', 'gasto')->first();

        $this->actingAs($this->admin);
        foreach ([route('caja-chica.index'), route('caja-chica.create'), route('caja-chica.show', $caja), route('caja-chica.gasto.edit', $m)] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->tecnico);
        $this->get(route('caja-chica.index'))->assertOk()->assertSee('Mi caja chica');
        $this->get(route('caja-chica.show', $caja))->assertOk()->assertSee('Registrar gasto');
        $this->get('/dashboard')->assertRedirect(route('caja-chica.index'));
    }
}
