<?php

namespace Tests\Feature;

use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotivosSalidaNuevosTest extends TestCase
{
    use RefreshDatabase;

    private const NUEVOS = ['Auspicios', 'Consignación', 'Cambio por garantía', 'Cambio de producto', 'Cambio de almacén'];

    public function test_los_motivos_nuevos_son_de_salida_y_activos(): void
    {
        $this->seed(CatalogoSeeder::class);

        foreach (self::NUEVOS as $nombre) {
            $m = MotivoMovimiento::where('nombre', $nombre)->first();
            $this->assertNotNull($m, "Falta el motivo {$nombre}");
            $this->assertSame('salida', $m->tipo);
            $this->assertSame('activo', $m->estado);
        }
    }

    public function test_sembrar_dos_veces_no_duplica(): void
    {
        $this->seed(CatalogoSeeder::class);
        $this->seed(CatalogoSeeder::class);

        $this->assertSame(1, MotivoMovimiento::where('nombre', 'Consignación')->count());
        $this->assertSame(1, MotivoMovimiento::where('codigo', 'VENT01')->count(), 'Venta a cliente sigue siendo uno solo');
    }

    public function test_la_pantalla_de_salida_con_escaner_los_ofrece(): void
    {
        $this->seed(CatalogoSeeder::class);
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));

        $r = $this->get(route('inventario.salida.index'))->assertOk();
        foreach (self::NUEVOS as $nombre) {
            $r->assertSee($nombre);
        }
        $r->assertSee('Venta a cliente')->assertSee('Producto dañado');
    }
}
