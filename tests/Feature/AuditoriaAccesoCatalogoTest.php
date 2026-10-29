<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la auditoría: catálogo, usuarios, perfil, sucursales, API de clientes y variantes.
 */
class AuditoriaAccesoCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    public function test_el_catalogo_no_expone_rutas_show_inexistentes(): void
    {
        $this->como('Administración');

        foreach (['colores', 'motivos', 'unidades', 'marcas', 'modelos'] as $recurso) {
            // Antes: 500 (el método show() no existe). Ahora la ruta ya no existe para GET.
            $this->assertSame(405, $this->get("/catalogo/{$recurso}/1")->getStatusCode(), $recurso);
        }
    }

    public function test_unidad_rapida_guarda_la_categoria_elegida(): void
    {
        $this->como('Administración');

        $this->postJson(route('catalogo.unidades.rapida'), ['nombre' => 'Kilogramo', 'abreviatura' => 'kg', 'tipo' => 'masa'])->assertOk();
        $this->postJson(route('catalogo.unidades.rapida'), ['nombre' => 'Litro', 'abreviatura' => 'lt', 'tipo' => 'volumen'])->assertOk();
        $this->postJson(route('catalogo.unidades.rapida'), ['nombre' => 'Caja', 'abreviatura' => 'cj', 'tipo' => 'empaque'])->assertOk();

        $this->assertSame('peso', UnidadMedida::where('abreviatura', 'kg')->value('categoria'));
        $this->assertSame('volumen', UnidadMedida::where('abreviatura', 'lt')->value('categoria'));
        $this->assertSame('otros', UnidadMedida::where('abreviatura', 'cj')->value('categoria'));
    }

    public function test_las_relaciones_del_catalogo_cargan_sin_error(): void
    {
        $categoria = Categoria::create(['codigo' => 'CAT-1', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $producto = Producto::create(['codigo' => 'KY-LU0101-0001', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);
        $variante = ProductoVariante::create(['producto_id' => $producto->id, 'sku' => 'KY-LU0101-0001', 'codigo_kyrios' => 'KY-LU0101-0001', 'estado' => 'activo']);

        foreach (['marca', 'modelo', 'color', 'unidadMedida'] as $relacion) {
            $this->assertTrue(method_exists($producto, $relacion), "Producto::{$relacion}");
            $producto->{$relacion};
        }
        $variante->color;
        $categoria->marcas;
        $this->assertTrue(true);
    }

    public function test_usuario_valida_dni_y_telefono_con_el_largo_de_la_base(): void
    {
        $this->como('Administración');
        $rol = Role::firstOrCreate(['nombre' => 'Administración'])->id;
        $datos = ['name' => 'Ana', 'email' => 'ana@x.com', 'password' => 'Clave1234', 'password_confirmation' => 'Clave1234', 'role_id' => $rol, 'estado' => 'activo'];

        $this->post(route('users.store'), $datos + ['dni' => '123456789012'])->assertSessionHasErrors('dni');
        $this->post(route('users.store'), $datos + ['telefono' => '1234567890123456'])->assertSessionHasErrors('telefono');
        $this->post(route('users.store'), $datos + ['dni' => '12345678', 'telefono' => '987654321'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'ana@x.com', 'dni' => '12345678']);
    }

    public function test_el_perfil_rechaza_un_telefono_mas_largo_que_la_base(): void
    {
        $this->como('Administración');

        $this->patch(route('profile.update'), ['name' => 'Yo', 'email' => 'yo@x.com', 'telefono' => '1234567890123456'])->assertSessionHasErrors('telefono');
    }

    public function test_el_perfil_no_deja_cambiar_rol_ni_estado(): void
    {
        $u = $this->como('Administración');
        $admin = Role::firstOrCreate(['nombre' => 'Administración'])->id;

        $this->patch(route('profile.update'), ['name' => 'Yo', 'email' => $u->email, 'role_id' => $admin, 'estado' => 'inactivo']);

        $this->assertSame('Administración', $u->fresh()->role->nombre);
        $this->assertSame('activo', $u->fresh()->estado);
    }

    public function test_un_administrador_no_puede_desactivarse_ni_cambiar_su_rol(): void
    {
        $admin = $this->como('Administración');
        $vendedor = Role::firstOrCreate(['nombre' => 'Contador'])->id;
        $base = ['name' => $admin->name, 'email' => $admin->email];

        $this->put(route('users.update', $admin), $base + ['role_id' => $admin->role_id, 'estado' => 'inactivo'])->assertSessionHas('error');
        $this->put(route('users.update', $admin), $base + ['role_id' => $vendedor, 'estado' => 'activo'])->assertSessionHas('error');
        $this->assertSame('activo', $admin->fresh()->estado);
        $this->assertSame('Administración', $admin->fresh()->role->nombre);

        $this->put(route('users.update', $admin), $base + ['name' => 'Nuevo nombre', 'role_id' => $admin->role_id, 'estado' => 'activo'])->assertSessionHas('success');
    }

    public function test_eliminar_un_usuario_con_historial_da_un_mensaje_claro(): void
    {
        $this->como('Administración');
        $cajero = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $almacen = Almacen::create(['codigo' => 'ALM-U', 'nombre' => 'Local', 'tipo' => 'principal', 'estado' => 'activo']);
        Caja::create(['user_id' => $cajero->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);

        $r = $this->delete(route('users.destroy', $cajero));

        $r->assertSessionHas('error');
        $this->assertStringNotContainsString('SQLSTATE', session('error'));
        $this->assertDatabaseHas('users', ['id' => $cajero->id]);
    }

    public function test_almacen_secundario_valida_el_telefono_con_el_largo_de_la_base(): void
    {
        $this->como('Administración');
        $sucursal = Sucursal::create(['codigo' => 'S01', 'nombre' => 'Administración', 'tipo' => 'tienda', 'estado' => 'activo']);

        $this->post(route('admin.sucursales.almacenes.store', $sucursal), ['nombre' => 'Depósito', 'telefono' => '1234567890123456'])->assertSessionHasErrors('telefono');
        $this->post(route('admin.sucursales.almacenes.store', $sucursal), ['nombre' => 'Depósito', 'telefono' => '987654321'])->assertSessionHasNoErrors();
    }

    public function test_api_buscar_cliente_por_documento_no_falla(): void
    {
        $this->como('Administración');
        Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan', 'estado' => 'activo']);

        $this->getJson(route('api.clientes.buscar', ['documento' => '12345678']))->assertOk()->assertJsonPath('cliente.nombre', 'Juan');
        $this->getJson(route('api.clientes.buscar', ['documento' => '00000000']))->assertNotFound();
    }

    public function test_solo_administracion_y_logistica_modifican_variantes_por_api(): void
    {
        $categoria = Categoria::create(['codigo' => 'CAT-2', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $producto = Producto::create(['codigo' => 'KY-LU0101-0002', 'nombre' => 'Ring', 'categoria_id' => $categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 5, 'estado' => 'activo']);
        $variante = ProductoVariante::create(['producto_id' => $producto->id, 'sku' => 'KY-LU0101-0002', 'codigo_kyrios' => 'KY-LU0101-0002', 'estado' => 'activo']);

        foreach (['Contador', 'Cliente', 'Operaciones'] as $rol) {
            $this->como($rol);
            $this->putJson(route('api.variantes.update', $variante), ['acabado_1' => 'negro'])->assertForbidden();
            $this->deleteJson(route('api.variantes.destroy', $variante))->assertForbidden();
            $this->postJson(route('api.variantes.store'), ['producto_id' => $producto->id])->assertForbidden();
        }
        $this->assertDatabaseHas('producto_variantes', ['id' => $variante->id, 'estado' => 'activo']);
    }

    public function test_cada_rol_abre_su_dashboard_o_pantalla_de_inicio(): void
    {
        $inicio = ['Administración' => 'admin.dashboard', 'Contador' => 'contador.dashboard', 'Logística' => 'logistica.dashboard',
            'Operaciones' => 'operaciones.dashboard', 'Cliente' => 'cliente.dashboard'];
        $almacen = Almacen::create(['codigo' => 'ALM-D', 'nombre' => 'Local', 'tipo' => 'principal', 'estado' => 'activo']);

        foreach ($inicio as $rol => $ruta) {
            $u = $this->como($rol);
            $u->update(['almacen_id' => $almacen->id]);
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_clientes_conserva_apostrofes_al_volver_con_errores(): void
    {
        $this->como('Administración');

        $html = $this->from(route('clientes.create'))->followingRedirects()
            ->post(route('clientes.store'), ['tipo_documento' => 'DNI', 'numero_documento' => '123', 'nombre' => "D'Angelo & Hijos", 'estado' => 'activo'])
            ->getContent();

        $this->assertStringNotContainsString('D&#039;Angelo', $html);
        $this->assertStringContainsString("\"D'Angelo & Hijos\"", $html);
    }

    public function test_empresa_no_corrompe_la_razon_social_con_apostrofe_o_ampersand(): void
    {
        $this->como('Administración');
        \App\Models\Empresa::create(['ruc' => '20123456786', 'razon_social' => "A&B D'Elia S.A.C.", 'regimen' => 'RMT', 'sunat_modo' => 'beta']);

        $html = $this->get(route('admin.empresa.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('A&amp;B', explode('function empresaForm', $html)[1] ?? '');
        $this->assertStringContainsString("\"A&B D'Elia S.A.C.\"", $html);
    }

    public function test_la_contrasena_maestra_no_tiene_valor_por_defecto(): void
    {
        config(['auth.master_password' => null]);

        $this->post(route('master-password.verify'), ['master_password' => 'ImportMaster2024', 'redirect' => 'register'])
            ->assertSessionHasErrors('master_password');
        $this->post(route('master-password.verify'), ['master_password' => '', 'redirect' => 'register'])
            ->assertSessionHasErrors('master_password');
        $this->assertNull(session('master_password_verified'));
    }

    public function test_la_contrasena_maestra_correcta_solo_redirige_a_rutas_internas(): void
    {
        config(['auth.master_password' => 'Clave-Maestra-Prueba']);

        $this->post(route('master-password.verify'), ['master_password' => 'Clave-Maestra-Prueba', 'redirect' => 'register'])
            ->assertRedirect('/register');
        $this->assertTrue(session('master_password_verified'));

        $this->post(route('master-password.verify'), ['master_password' => 'Clave-Maestra-Prueba', 'redirect' => 'https://malo.example/robo'])
            ->assertRedirect('/register');
    }

    public function test_el_bloqueo_del_sistema_se_lee_desde_la_configuracion(): void
    {
        config(['app.sistema_bloqueado' => true]);
        $this->como('Administración');

        $this->get(route('admin.dashboard'))->assertRedirect(route('sistema.bloqueado'));
    }

    public function test_dashboard_redirige_a_cada_rol_sin_bucles(): void
    {
        $destinos = ['Administración' => 'admin.dashboard', 'Contador' => 'contador.dashboard', 'Logística' => 'logistica.dashboard',
            'Operaciones' => 'operaciones.dashboard', 'Cliente' => 'cliente.dashboard'];

        foreach ($destinos as $rol => $ruta) {
            $this->como($rol);
            // Antes, Proveedor volvía a /login y el invitado-autenticado de /login volvía a /dashboard (bucle).
            $this->get('/dashboard')->assertRedirect(route($ruta));
        }
    }
}
