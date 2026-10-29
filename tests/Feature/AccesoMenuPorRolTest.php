<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Cada enlace del menú lateral de un rol debe abrirse para ese rol (sin 403, 404 ni 500).
 */
class AccesoMenuPorRolTest extends TestCase
{
    use RefreshDatabase;

    private function enlacesDelMenu(string $rol): array
    {
        $html = Blade::render('<x-sidebar :role="$rol" />', ['rol' => $rol]);
        preg_match_all('/<a\s[^>]*href="([^"#][^"]*)"/i', $html, $m);

        return collect($m[1])
            ->map(fn ($h) => html_entity_decode($h))
            ->map(fn ($h) => preg_replace('#^https?://[^/]+#', '', $h))
            ->filter(fn ($h) => str_starts_with($h, '/') && $h !== '/' && !str_contains($h, 'logout'))
            ->unique()->values()->all();
    }

    public static function roles(): array
    {
        return [['Administración'], ['Administración'], ['Logística'], ['Administración'], ['Administración'], ['Cliente'], ['Operaciones']];
    }

    /** @dataProvider roles */
    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_los_enlaces_del_menu_abren_para_su_rol(string $rol): void
    {
        $almacen = Almacen::create(['codigo' => 'ALM-M', 'nombre' => 'Almacén menú', 'tipo' => 'principal', 'estado' => 'activo']);
        $usuario = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo', 'almacen_id' => $almacen->id]);
        $this->actingAs($usuario);

        $enlaces = $this->enlacesDelMenu($rol);
        $this->assertNotEmpty($enlaces, "El menú de {$rol} no tiene enlaces");

        $malos = [];
        foreach ($enlaces as $url) {
            $estado = $this->get($url)->getStatusCode();
            if ($estado >= 400) {
                $malos[] = "{$estado} {$url}";
            }
        }

        $this->assertSame([], $malos, "Enlaces del menú de {$rol} que no abren");
    }
}
