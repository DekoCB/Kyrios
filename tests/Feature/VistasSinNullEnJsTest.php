<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los valores que las vistas pasan a Alpine deben ser texto, no null: el JS llama a .length sobre ellos.
 */
class VistasSinNullEnJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_sin_datos_no_pasa_null_al_formulario(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
        Empresa::query()->delete();

        $html = $this->get(route('admin.empresa.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\bruc:\s+""/', $html);
        $this->assertDoesNotMatchRegularExpression('/\bruc:\s+null/', $html);
    }

    public function test_alta_de_cliente_no_pasa_null_al_formulario(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));

        $html = $this->get(route('clientes.create'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\b(nombre|direccion|ubigeo|numeroDocumento):\s+null/', $html);
    }

    public function test_las_etiquetas_input_de_las_vistas_no_se_cierran_antes_de_sus_atributos(): void
    {
        // Un «>» de más en un <input ...> deja el resto de los atributos (class="...") escritos como texto en pantalla
        $malas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            $lineas = preg_split('/?
/', file_get_contents($f->getPathname()));
            foreach ($lineas as $i => $linea) {
                // Una línea que empieza con un atributo (class="..." o x-model="...") y cuya anterior ya cerró la etiqueta con «>»
                if ($i > 0 && preg_match('/^\s+(class|:class|x-model|name|value)="[^"]*"[^<]*>\s*$/', $linea)
                    && preg_match('/^\s+[:@\w.-]+="[^"]*"\s*\/?>\s*$/', $lineas[$i - 1])) {
                    $malas[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname()) . ':' . ($i + 1);
                }
            }
        }

        $this->assertSame([], $malas, 'Etiqueta cerrada antes de sus atributos');
    }
}
