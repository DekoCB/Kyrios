<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Role;
use App\Models\User;
use App\Services\SucursalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgos del recorrido completo (compra, traslado, venta, devolución, cierre de caja).
 */
class CicloCompletoHallazgosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
    }

    public function test_el_nombre_de_la_tienda_no_repite_el_prefijo(): void
    {
        $this->actingAs($this->admin());
        $svc = app(SucursalService::class);

        $s1 = $svc->crear(['nombre' => 'Tienda Miraflores', 'tipo' => 'tienda', 'estado' => 'activo']);
        $s2 = $svc->crear(['nombre' => 'San Isidro', 'tipo' => 'tienda', 'estado' => 'activo']);
        $s3 = $svc->crear(['nombre' => 'Almacén Callao', 'tipo' => 'almacen', 'estado' => 'activo']);

        $this->assertSame('Tienda Miraflores', Almacen::find($s1->almacen_id)->nombre);
        $this->assertSame('Tienda San Isidro', Almacen::find($s2->almacen_id)->nombre);
        $this->assertSame('Almacén Callao', Almacen::find($s3->almacen_id)->nombre);
    }

    public function test_el_punto_de_venta_avisa_cuando_no_hay_tienda(): void
    {
        $this->actingAs($this->admin());
        Almacen::query()->delete();

        $this->get(route('ventas.create'))->assertOk()->assertSee('No hay una tienda activa para vender');
    }

    public function test_los_botones_de_caja_no_usan_bg_opacity_de_tailwind_3(): void
    {
        $encontrados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if ($archivo->isFile() && preg_match('/(bg|text|border)-opacity-\d+/', file_get_contents($archivo->getPathname()))) {
                $encontrados[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }

        $this->assertSame([], $encontrados, 'Tailwind 4 ya no tiene bg-opacity-*: usar bg-white/20');
    }
}
