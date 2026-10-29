<?php

namespace Tests\Feature;

use App\Models\Catalogo\Marca;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la segunda auditoría: catálogo, clientes, usuarios, admin, autenticación e instalación.
 */
class Auditoria2CRegresionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    // ── Instalación y producción ─────────────────────────────────────────

    public function test_no_quedan_archivos_de_prueba_en_public(): void
    {
        foreach (glob(public_path('*.php')) as $archivo) {
            $this->assertSame('index.php', basename($archivo), 'Archivo PHP suelto en public/: ' . basename($archivo));
        }
    }

    public function test_el_env_de_ejemplo_trae_las_variables_que_el_sistema_necesita(): void
    {
        $ejemplo = file_get_contents(base_path('.env.example'));

        foreach (['APP_TIMEZONE', 'APP_LOCALE=es', 'ADMIN_EMAIL', 'ADMIN_PASSWORD', 'SESSION_SECURE_COOKIE', 'MASTER_PASSWORD', 'SISTEMA_BLOQUEADO', 'APIS_NET_PE_TOKEN', 'VENTA_EDIT_WINDOW_HOURS'] as $clave) {
            $this->assertStringContainsString($clave, $ejemplo, "Falta {$clave} en .env.example");
        }
    }

    public function test_en_produccion_el_seeder_no_crea_un_administrador_con_clave_por_defecto(): void
    {
        $this->seed(RoleSeeder::class);
        $this->app['env'] = 'production';
        config(['kyrios.admin.email' => null, 'kyrios.admin.password' => null]);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true])
            ->expectsOutputToContain('ADMIN_EMAIL y ADMIN_PASSWORD')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_en_produccion_el_seeder_usa_las_credenciales_configuradas(): void
    {
        $this->seed(RoleSeeder::class);
        $this->app['env'] = 'production';
        config(['kyrios.admin.email' => 'dueno@kyrios.pe', 'kyrios.admin.password' => 'UnaClaveLarga#2026']);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true])->assertSuccessful();

        $u = User::where('email', 'dueno@kyrios.pe')->first();
        $this->assertNotNull($u);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('UnaClaveLarga#2026', $u->password));
        $this->assertNull(User::where('email', 'admin@importaciones.com')->first());
    }

    public function test_en_local_el_seeder_conserva_el_administrador_de_desarrollo(): void
    {
        $this->seed(RoleSeeder::class);
        config(['kyrios.admin.email' => null, 'kyrios.admin.password' => null]);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertNotNull(User::where('email', 'admin@importaciones.com')->first());
    }

    // ── Idioma ───────────────────────────────────────────────────────────

    public function test_los_errores_de_validacion_salen_en_espanol(): void
    {
        $this->admin();

        $r = $this->post(route('clientes.store'), ['estado' => 'activo']);

        $r->assertSessionHasErrors('nombre');
        $mensaje = session('errors')->first('nombre');
        $this->assertStringContainsString('obligatorio', $mensaje);
        $this->assertStringNotContainsString('required', $mensaje);
    }

    public function test_el_login_fallido_y_la_paginacion_salen_en_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertStringContainsString('credenciales', mb_strtolower(trans('auth.failed')));
        $this->assertSame('Mostrando', __('Showing'));
        $this->assertStringContainsString('Siguiente', trans('pagination.next'));
    }

    // ── Entradas que antes daban 500 ─────────────────────────────────────

    public function test_la_creacion_rapida_de_marca_con_datos_invalidos_responde_422(): void
    {
        $this->admin();
        Marca::create(['nombre' => 'Philips', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'C1', 'nombre' => 'Luminaria', 'estado' => 'activo']);

        $this->postJson(route('catalogo.marcas.rapida'), ['nombre' => 'Philips', 'categoria_id' => $cat->id])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->postJson(route('catalogo.marcas.rapida'), [])->assertStatus(422);
        $this->postJson(route('catalogo.marcas.rapida'), ['nombre' => 'Osram', 'categoria_id' => $cat->id])
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_cambiar_la_clave_con_datos_que_no_son_texto_no_da_500(): void
    {
        $this->admin();

        $this->put(route('profile.password'), ['current_password' => ['x'], 'password' => ['y'], 'password_confirmation' => ['y']])
            ->assertSessionHasErrors('current_password');
        $this->patch(route('profile.update'), ['name' => 'Ana', 'email' => ['a@b.com']])
            ->assertSessionHasErrors('email');
    }

    public function test_consultar_documento_con_datos_que_no_son_texto_no_da_500(): void
    {
        $this->admin();

        $this->postJson(route('clientes.consultar-documento'), ['tipo' => ['RUC'], 'numero' => ['1']])
            ->assertStatus(422);
    }

    // ── Clientes ─────────────────────────────────────────────────────────

    public function test_la_busqueda_de_clientes_trata_los_comodines_como_texto(): void
    {
        $this->admin();
        Cliente::create(['nombre' => 'Luz 100% LED', 'estado' => 'activo']);
        Cliente::create(['nombre' => 'Ana Torres', 'estado' => 'activo']);
        Cliente::create(['nombre' => 'Beto_Ruiz', 'estado' => 'activo']);

        $this->assertSame(['Luz 100% LED'], Cliente::buscar('%')->pluck('nombre')->all());
        $this->assertSame(['Beto_Ruiz'], Cliente::buscar('o_R')->pluck('nombre')->all());
        $this->assertSame([], Cliente::buscar('\\')->pluck('nombre')->all());
        $this->assertSame(['Ana Torres'], Cliente::buscar('torres')->pluck('nombre')->all());
        $this->assertCount(1, Cliente::buscar("O'Brien")->get()->concat([Cliente::create(['nombre' => "Pedro O'Brien", 'estado' => 'activo'])])->filter(fn ($c) => str_contains($c->nombre, "O'Brien")));
    }

    // ── Diseño en celular ────────────────────────────────────────────────

    public function test_la_cabecera_de_sucursal_admite_varias_lineas_en_celular(): void
    {
        $this->admin();
        $s = \App\Models\Sucursal::create(['nombre' => 'Sucursal con un nombre bastante largo', 'codigo' => 'S900', 'tipo' => 'tienda', 'estado' => 'activo']);

        $html = $this->get(route('admin.sucursales.edit', $s))->assertOk()->getContent();

        $this->assertStringContainsString('flex flex-wrap items-center', $html);
    }
}
