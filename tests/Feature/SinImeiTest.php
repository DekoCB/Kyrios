<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kyrios no maneja IMEI: no deben quedar tablas, columnas, rutas ni textos de ese concepto.
 */
class SinImeiTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_existen_tablas_ni_columnas_de_imei(): void
    {
        $this->assertFalse(Schema::hasTable('imeis'));
        $this->assertFalse(Schema::hasTable('traslado_imeis'));
        $this->assertFalse(Schema::hasColumn('detalle_ventas', 'imei_id'));
        $this->assertFalse(Schema::hasColumn('movimientos_inventario', 'imei_id'));
    }

    public function test_no_quedan_rutas_de_imei(): void
    {
        $rutas = collect(Route::getRoutes())->map(fn ($r) => ($r->getName() ?? '') . ' ' . $r->uri())->filter(fn ($t) => stripos($t, 'imei') !== false);

        $this->assertSame([], $rutas->values()->all());
    }

    public function test_las_pantallas_principales_no_hablan_de_imei(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']));

        foreach (['admin.dashboard', 'inventario.productos.index', 'inventario.productos.create', 'compras.create', 'ventas.create', 'traslados.create', 'inventario.movimientos.create'] as $ruta) {
            if (!Route::has($ruta)) {
                continue;
            }
            $r = $this->get(route($ruta));
            $this->assertContains($r->status(), [200, 302], "{$ruta} respondió {$r->status()}");
            if ($r->status() !== 200) {
                fwrite(STDERR, "
[SinImeiTest] {$ruta} redirige: " . $r->headers->get('Location') . "
");
                continue;
            }
            $html = $r->getContent();
            $this->assertStringNotContainsStringIgnoringCase('imei', $html, "La pantalla {$ruta} todavía menciona IMEI");
        }
    }

    public function test_el_codigo_fuente_no_menciona_imei(): void
    {
        $encontrados = [];
        foreach (['app', 'resources/views', 'routes'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $archivo) {
                if ($archivo->isFile() && preg_match('/\.php$/', $archivo->getFilename()) && preg_match('/imei(?!nterface)/i', file_get_contents($archivo->getPathname()))) {
                    $encontrados[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivo->getPathname());
                }
            }
        }

        $this->assertSame([], $encontrados, 'Archivos que todavía mencionan IMEI');
    }
}
