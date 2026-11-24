<?php

namespace Tests\Feature;

use App\Models\Marketing\Publicacion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertasPublicacionesMarketingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $marketing;
    private User $logistica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->marketing = $this->usuario('Marketing');
        $this->logistica = $this->usuario('Logística');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function publicacion(string $estado, $fecha, array $extra = []): Publicacion
    {
        return Publicacion::create($extra + ['titulo' => 'Lanzamiento Ring', 'red' => 'instagram', 'tipo' => 'publicacion',
            'estado' => $estado, 'programada_para' => $fecha]);
    }

    public function test_avisa_de_una_publicacion_que_se_acerca_a_marketing_y_administracion(): void
    {
        $this->publicacion('programada', today()->addDays(2)->setTime(18, 30));

        $this->artisan('alertas:fechas')->assertSuccessful();

        $mensaje = $this->marketing->unreadNotifications->pluck('data.mensaje')->implode(' | ');
        $this->assertStringContainsString('«Lanzamiento Ring»', $mensaje);
        $this->assertStringContainsString('faltan 2 días', $mensaje);
        $this->assertStringContainsString('18:30', $mensaje);
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(0, $this->logistica->unreadNotifications()->count());
    }

    public function test_no_avisa_de_una_publicacion_lejana_ni_de_una_ya_publicada_o_rechazada(): void
    {
        $this->publicacion('aprobada', today()->addDays(20));
        $this->publicacion('publicada', today()->addDays(1));
        $this->publicacion('rechazada', today()->addDays(1));

        $this->artisan('alertas:fechas');

        $this->assertSame(0, $this->marketing->unreadNotifications()->count());
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_avisa_de_la_publicacion_atrasada_y_de_la_que_sigue_sin_aprobar(): void
    {
        $this->publicacion('aprobada', today()->subDays(2));
        $this->publicacion('pendiente', today()->addDay(), ['titulo' => 'Oferta de verano']);

        $this->artisan('alertas:fechas');

        $titulos = $this->marketing->unreadNotifications->pluck('data.titulo')->implode(' | ');
        $this->assertStringContainsString('Publicación sin publicar', $titulos);
        $this->assertStringContainsString('Publicación próxima sin aprobar', $titulos);
        $mensajes = $this->marketing->unreadNotifications->pluck('data.mensaje')->implode(' | ');
        $this->assertStringContainsString('venció hace 2 días', $mensajes);
        $this->assertStringContainsString('aún sin aprobar', $mensajes);
    }

    public function test_quien_creo_la_publicacion_tambien_recibe_el_aviso(): void
    {
        $this->publicacion('programada', today()->addDay(), ['creado_por' => $this->logistica->id]);

        $this->artisan('alertas:fechas');

        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
    }

    public function test_no_repite_el_aviso_del_dia_y_lo_retira_al_publicar(): void
    {
        $p = $this->publicacion('programada', today()->addDay());

        $this->artisan('alertas:fechas');
        $this->marketing->unreadNotifications->markAsRead();
        $this->artisan('alertas:fechas');
        $this->assertSame(0, $this->marketing->unreadNotifications()->count());
        $this->assertSame(1, $this->marketing->notifications()->count());

        $this->admin->unreadNotifications()->count() === 1 || $this->fail('Administración debía tener su aviso sin leer');
        $p->update(['estado' => 'publicada', 'publicada_en' => now()]);
        $this->artisan('alertas:fechas');
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_el_aviso_lleva_a_editar_la_publicacion(): void
    {
        $p = $this->publicacion('programada', today()->addDay());

        $this->artisan('alertas:fechas');

        $this->assertSame(route('marketing.publicaciones.edit', $p->id, false), $this->marketing->unreadNotifications->first()->data['url']);
    }
}
