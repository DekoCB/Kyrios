<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los roles del sistema son cinco: Administración, Contador, Logística, Operaciones y Cliente.
 */
class RolesNuevosTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    public function test_el_sembrado_deja_solo_los_cinco_roles(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertEqualsCanonicalizing(['Administración', 'Contador', 'Logística', 'Operaciones', 'Cliente'], Role::pluck('nombre')->all());
    }

    /** @dataProvider matriz */
    public function test_matriz_de_permisos(string $rol, string $ruta, bool $permitido): void
    {
        $this->como($rol);

        $status = $this->get($ruta)->getStatusCode();

        $permitido
            ? $this->assertNotSame(403, $status, "{$rol} debería poder abrir {$ruta}")
            : $this->assertSame(403, $status, "{$rol} no debería poder abrir {$ruta}");
    }

    public static function matriz(): array
    {
        $filas = [];
        $reglas = [
            // ruta                            Administración Contador Logística Operaciones Cliente
            '/ventas/create'                => [true,  false, false, false, false],
            '/ventas'                       => [true,  true,  false, false, false],
            '/cotizaciones'                 => [true,  true,  false, false, false],
            '/clientes'                     => [true,  true,  false, false, false],
            '/clientes/create'              => [true,  false, false, false, false],
            '/facturacion'                  => [true,  true,  false, false, false],
            '/caja'                         => [true,  false, false, false, false],
            '/compras'                      => [true,  true,  true,  false, false],
            '/compras/create'               => [true,  false, true,  false, false],
            '/proveedores'                  => [true,  false, true,  false, false],
            '/pedidos'                      => [true,  false, true,  false, false],
            '/inventario/productos'         => [true,  false, true,  true,  false],
            '/inventario/recepcion'         => [true,  false, true,  false, false],
            '/inventario/salida-escaner'    => [true,  false, true,  false, false],
            '/traslados'                    => [true,  false, true,  true,  false],
            '/guias-remision'               => [true,  false, true,  true,  false],
            '/catalogo/marcas'              => [true,  false, true,  false, false],
            '/reportes/ventas'              => [true,  true,  false, false, false],
            '/comisiones/reporte'           => [true,  true,  false, false, false],
            '/comisiones'                   => [true,  false, false, false, false],
            '/users'                        => [true,  false, false, false, false],
            '/admin/empresa'                => [true,  false, false, false, false],
            '/admin/sucursales'             => [true,  false, false, false, false],
        ];
        $roles = ['Administración', 'Contador', 'Logística', 'Operaciones', 'Cliente'];
        foreach ($reglas as $ruta => $permisos) {
            foreach ($roles as $i => $rol) {
                $filas["{$rol} {$ruta}"] = [$rol, $ruta, $permisos[$i]];
            }
        }

        return $filas;
    }

    public function test_cada_rol_tiene_su_dashboard(): void
    {
        $destinos = ['Administración' => 'admin.dashboard', 'Contador' => 'contador.dashboard', 'Logística' => 'logistica.dashboard',
            'Operaciones' => 'operaciones.dashboard', 'Cliente' => 'cliente.dashboard'];

        foreach ($destinos as $rol => $ruta) {
            $this->como($rol);
            $this->get('/dashboard')->assertRedirect(route($ruta));
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_el_cliente_no_entra_a_ninguna_ruta_interna(): void
    {
        $this->como('Cliente');
        $permitidas = ['dashboard', 'cliente.dashboard', 'profile.edit', 'profile.update', 'profile.destroy', 'logout', 'password.update', 'login', 'sistema.bloqueado'];

        foreach (Route::getRoutes() as $r) {
            if (!in_array('GET', $r->methods(), true) || $r->parameterNames() || preg_match('#^(_|storage|up$|sanctum|login|logout|register|forgot|reset|consultas|sistema)#', $r->uri())) {
                continue;
            }
            if (in_array($r->getName(), $permitidas, true) || str_starts_with((string) $r->getName(), 'profile.')) {
                continue;
            }
            $this->assertContains($this->get('/' . $r->uri())->getStatusCode(), [302, 403, 404], "El cliente abrió /{$r->uri()}");
        }
    }

    public function test_el_contador_no_puede_anular_ni_cambiar_la_empresa(): void
    {
        // Con ids inexistentes el binding da 404 antes del rol: se comprueba la definición de cada ruta
        foreach (['ventas.anular', 'ventas.nota-credito', 'ventas.destroy', 'admin.empresa.update', 'users.store'] as $nombre) {
            $roles = collect(Route::getRoutes()->getByName($nombre)->middleware())->first(fn ($m) => str_contains($m, 'CheckRole') || str_starts_with($m, 'role:'));
            $this->assertNotNull($roles, "{$nombre} no tiene restricción de rol");
            $this->assertStringNotContainsString('Contador', $roles, "{$nombre} no debe ser del Contador");
        }

        $this->como('Contador');
        $this->put(route('admin.empresa.update'), ['ruc' => '20000000001'])->assertForbidden();
    }

    public function test_la_migracion_convierte_los_roles_y_conserva_a_los_usuarios(): void
    {
        // Estado «antiguo»: se vuelve a crear con los nombres viejos
        DB::table('users')->delete();
        DB::table('roles')->delete();
        $ids = [];
        foreach (['Administrador', 'Vendedor', 'Tienda', 'Cajero', 'Almacenero', 'Proveedor', 'Técnico'] as $nombre) {
            $ids[$nombre] = DB::table('roles')->insertGetId(['nombre' => $nombre, 'descripcion' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
        $usuarios = [];
        foreach ($ids as $nombre => $id) {
            $usuarios[$nombre] = User::factory()->create(['role_id' => $id, 'estado' => 'activo'])->id;
        }

        $migracion = require database_path('migrations/2026_10_29_120000_reemplaza_roles_por_los_cinco_nuevos.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertEqualsCanonicalizing(['Administración', 'Contador', 'Logística', 'Operaciones', 'Cliente'], Role::pluck('nombre')->all());
        $rolDe = fn (string $viejo) => User::find($usuarios[$viejo])->role->nombre;
        $this->assertSame('Administración', $rolDe('Administrador'));
        $this->assertSame('Administración', $rolDe('Vendedor'));
        $this->assertSame('Administración', $rolDe('Tienda'));
        $this->assertSame('Administración', $rolDe('Cajero'));
        $this->assertSame('Logística', $rolDe('Almacenero'));
        $this->assertSame('Operaciones', $rolDe('Técnico'));
        $this->assertSame('Cliente', $rolDe('Proveedor'));
    }

    public function test_el_codigo_no_usa_los_nombres_de_rol_antiguos(): void
    {
        $n = 'Administrador|Vendedor|Cajero|Almacenero|Técnico';
        // Solo usos como nombre de rol: comparaciones, middleware, siembra y listas de roles
        $viejos = "/(nombre['\"]?\s*(===|!==|==|=>)\s*['\"]({$n})['\"]|role:[^'\"]*({$n})|(rol|role)\w*\s*(===|!==)\s*['\"]({$n})['\"])/u";
        $encontrados = [];
        foreach (['app', 'routes', 'resources/views', 'database/seeders', 'config'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.php') && preg_match($viejos, file_get_contents($f->getPathname()))) {
                    $encontrados[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }

        $this->assertSame([], $encontrados, 'Quedan nombres de rol antiguos');
    }
}
