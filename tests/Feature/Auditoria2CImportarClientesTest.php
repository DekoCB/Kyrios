<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La importación de clientes no se detiene por una fila con datos malos: la reporta y sigue con las demás.
 */
class Auditoria2CImportarClientesTest extends TestCase
{
    use RefreshDatabase;

    private function archivo(array $clientes): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cli');
        file_put_contents($ruta, json_encode(['clientes' => $clientes]));

        return $ruta;
    }

    public function test_una_fila_mala_no_corta_la_importacion(): void
    {
        $ruta = $this->archivo([
            ['nombre' => "D'Angelo Núñez", 'telefono' => '999111222'],
            ['nombre' => 'Fecha imposible', 'fecha_cumpleanos' => '2026-02-30'],
            ['nombre' => 'Comisión absurda', 'comision' => 99999999],
            ['nombre' => 'Ana Torres', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678'],
            ['nombre' => ''],
        ]);

        $this->artisan('clientes:importar', ['archivo' => $ruta])
            ->expectsOutputToContain('con error')
            ->assertSuccessful();

        $this->assertNotNull(Cliente::where('nombre', "D'Angelo Núñez")->first());
        $this->assertNotNull(Cliente::where('numero_documento', '12345678')->first(), 'las filas buenas posteriores se importan');
        $imposible = Cliente::where('nombre', 'Fecha imposible')->first();
        $this->assertNotNull($imposible, 'el cliente se importa aunque su fecha sea imposible');
        $this->assertNull($imposible->fecha_cumpleanos, 'la fecha imposible no se corrige en silencio a otra fecha');
        $this->assertNull(Cliente::where('nombre', 'Comisión absurda')->first(), 'la fila con un valor fuera de rango se reporta y se omite');
        unlink($ruta);
    }

    public function test_el_documento_con_espacios_o_guiones_se_normaliza(): void
    {
        $ruta = $this->archivo([['nombre' => 'Con espacios', 'tipo_documento' => 'RUC', 'numero_documento' => '20 511879621']]);

        $this->artisan('clientes:importar', ['archivo' => $ruta])->assertSuccessful();

        $this->assertSame('20511879621', Cliente::where('nombre', 'Con espacios')->value('numero_documento'));
        unlink($ruta);
    }
}
