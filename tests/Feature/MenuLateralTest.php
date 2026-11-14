<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\MenuLateral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** El menú lateral: ordenado por secciones y sin enlaces a pantallas que el rol no puede abrir. */
class MenuLateralTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    /** @return array<int, array> todos los enlaces del menú, también los dentro de grupos */
    private function enlaces(array $menu): array
    {
        $enlaces = [];
        foreach ($menu as $m) {
            if ($m['tipo'] === 'enlace') {
                $enlaces[] = $m;
            } elseif ($m['tipo'] === 'grupo') {
                array_push($enlaces, ...$m['hijos']);
            }
        }

        return $enlaces;
    }

    public function test_todas_las_rutas_del_menu_existen_para_cada_rol(): void
    {
        foreach (Role::TODOS as $rol) {
            $u = $this->usuario($rol);
            foreach ($this->enlaces(MenuLateral::para($rol, $u->id)) as $e) {
                $this->assertTrue(Route::has($e['ruta']), "{$rol}: la ruta {$e['ruta']} no existe");
            }
        }
    }

    public function test_cada_rol_puede_abrir_todo_lo_que_ve_en_el_menu(): void
    {
        foreach ([Role::ADMINISTRACION, Role::CONTADOR, Role::LOGISTICA, Role::OPERACIONES, Role::CLIENTE] as $rol) {
            $u = $this->usuario($rol);
            foreach ($this->enlaces(MenuLateral::para($rol, $u->id)) as $e) {
                $estado = $this->actingAs($u)->get(route($e['ruta']))->getStatusCode();
                $this->assertNotContains($estado, [403, 404, 500], "{$rol} ve «{$e['etiqueta']}» ({$e['ruta']}) pero recibe {$estado}");
            }
        }
    }

    public function test_el_menu_de_administracion_agrupa_lo_pedido(): void
    {
        $menu = MenuLateral::para(Role::ADMINISTRACION, $this->usuario(Role::ADMINISTRACION)->id);
        $grupos = collect($menu)->where('tipo', 'grupo')->keyBy('id');
        $rutas = fn (string $id) => collect($grupos[$id]['hijos'])->pluck('ruta')->all();

        // Cotizaciones engloba las notas de pedido y los pedidos internos
        $this->assertEqualsCanonicalizing(['cotizaciones.index', 'notas-pedido.index', 'pedidos-internos.index'], $rutas('cotizaciones'));
        // Asistencias: Mi asistencia y Control
        $this->assertContains('rrhh.mi-asistencia', $rutas('asistencias'));
        $this->assertContains('rrhh.asistencia.control', $rutas('asistencias'));
        // Las tareas del día viven dentro del Dashboard
        $this->assertContains('tareas.index', $rutas('dashboard'));
        // Un solo acceso a Aprobaciones y ya no hay enlaces sueltos de lo anterior
        $sueltos = collect($menu)->where('tipo', 'enlace')->pluck('ruta')->all();
        $this->assertSame(1, count(array_filter($sueltos, fn ($r) => str_starts_with($r, 'aprobaciones.'))));
        foreach (['tareas.index', 'notas-pedido.index', 'pedidos-internos.index', 'rrhh.mi-asistencia', 'rrhh.asistencia.control'] as $r) {
            $this->assertNotContains($r, $sueltos);
        }
        // Secciones con encabezado
        $this->assertGreaterThanOrEqual(5, collect($menu)->where('tipo', 'titulo')->count());
    }

    public function test_el_menu_se_dibuja_con_las_secciones_y_el_grupo_abierto(): void
    {
        $this->actingAs($this->usuario(Role::ADMINISTRACION));
        $this->get(route('notas-pedido.index'))->assertOk()
            ->assertSee('Comercial')->assertSee('Almacén y compras')->assertSee('Asistencias')->assertSee('Aprobaciones')
            ->assertSee("abierto: 'cotizaciones'", false);

        $this->get(route('tareas.index'))->assertOk()->assertSee("abierto: 'dashboard'", false);
    }

    public function test_los_otros_roles_tienen_su_menu_ordenado(): void
    {
        $u = $this->usuario(Role::LOGISTICA);
        $this->actingAs($u)->get(route('logistica.dashboard'))->assertOk()->assertSee('Pedidos')->assertSee('Inventario')->assertSee('Asistencias');

        $ops = $this->usuario(Role::OPERACIONES);
        $this->actingAs($ops)->get(route('operaciones.dashboard'))->assertOk()->assertSee('Mi caja chica')->assertSee('Pedidos internos');
    }
}
