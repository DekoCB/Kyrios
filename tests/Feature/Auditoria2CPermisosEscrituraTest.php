<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Ningún rol sin permiso puede crear, editar o borrar usuarios, empresa, sucursales, catálogo ni clientes.
 */
class Auditoria2CPermisosEscrituraTest extends TestCase
{
    use RefreshDatabase;

    /** prefijo de ruta => roles que SÍ pueden escribir */
    private const PERMITIDOS = [
        'users'            => ['Administración'],
        'admin/'           => ['Administración'],
        'catalogo/'        => ['Administración', 'Logística'],
        'clientes'         => ['Administración', 'Administración', 'Administración', 'Administración'],
    ];

    private const ROLES = ['Administración', 'Administración', 'Administración', 'Administración', 'Logística', 'Cliente', 'Operaciones'];

    public static function roles(): array
    {
        return array_combine(self::ROLES, array_map(fn ($r) => [$r], self::ROLES));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_cada_rol_solo_escribe_donde_le_corresponde(string $rol): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']));
        $indebidos = [];
        $revisadas = 0;

        foreach (Route::getRoutes() as $r) {
            $metodos = array_values(array_diff($r->methods(), ['GET', 'HEAD']));
            if (!$metodos) continue;
            $uri = $r->uri();
            foreach (self::PERMITIDOS as $prefijo => $roles) {
                if (!str_starts_with($uri, $prefijo) || in_array($rol, $roles, true)) continue;
                if (str_contains($uri, 'consultar') || str_contains($uri, 'buscar')) continue; // consultas de lectura por POST

                $url = '/' . preg_replace('/\{\w+\??\}/', '1', $uri);
                $metodo = $metodos[0];
                $resp = $this->call(in_array($metodo, ['PUT', 'PATCH'], true) ? 'POST' : $metodo, $url, in_array($metodo, ['PUT', 'PATCH'], true) ? ['_method' => $metodo] : []);
                $revisadas++;
                if (!in_array($resp->getStatusCode(), [403, 404], true)) {
                    $indebidos[] = "{$metodo} {$url} -> {$resp->getStatusCode()}";
                }
            }
        }

        if ($rol === 'Administración') {
            $this->assertSame(0, $revisadas);
        } else {
            $this->assertGreaterThan(10, $revisadas);
        }
        $this->assertSame([], $indebidos, "El rol {$rol} no debería poder escribir:\n" . implode("\n", $indebidos));
    }

    public function test_un_rol_sin_permiso_recibe_403_al_tocar_registros_reales(): void
    {
        $objetivo = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $marca = \App\Models\Catalogo\Marca::create(['nombre' => 'Marca Real', 'estado' => 'activo']);
        $sucursal = \App\Models\Sucursal::create(['nombre' => 'Sucursal Real', 'codigo' => 'S777', 'tipo' => 'tienda', 'estado' => 'activo']);

        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Operaciones'])->id, 'estado' => 'activo']));

        $this->delete(route('users.destroy', $objetivo))->assertForbidden();
        $this->put(route('users.update', $objetivo), ['name' => 'X'])->assertForbidden();
        $this->delete(route('catalogo.marcas.destroy', $marca))->assertForbidden();
        $this->delete(route('admin.sucursales.destroy', $sucursal))->assertForbidden();
        $this->put(route('admin.empresa.update'), ['ruc' => '20000000001'])->assertForbidden();

        $this->assertNotNull($objetivo->fresh());
        $this->assertNotNull($marca->fresh());
        $this->assertNotNull($sucursal->fresh());
    }
}
