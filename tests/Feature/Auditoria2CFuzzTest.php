<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entradas raras o extremas en las pantallas de lectura: nunca deben producir un 500.
 */
class Auditoria2CFuzzTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
    }

    public static function consultasRaras(): array
    {
        $raras = ['%', '_', "'", '\\', '"; DROP TABLE clientes;--', '<script>alert(1)</script>', str_repeat('a', 600), "línea\r\nnueva", '%00', 'ñandú'];
        $filas = [];
        foreach (['clientes', 'users', 'catalogo/marcas', 'catalogo/colores', 'catalogo/modelos', 'catalogo/unidades', 'catalogo/motivos', 'precios', 'comisiones', 'admin/sucursales'] as $ruta) {
            foreach ($raras as $i => $q) {
                $filas["{$ruta} q#{$i}"] = [$ruta, ['q' => $q, 'buscar' => $q, 'search' => $q, 'estado' => $q, 'tipo_documento' => $q, 'segmento' => $q, 'page' => $q, 'per_page' => $q]];
            }
        }

        return $filas;
    }

    /** @dataProvider consultasRaras */
    #[\PHPUnit\Framework\Attributes\DataProvider('consultasRaras')]
    public function test_listados_con_busquedas_raras_no_dan_500(string $ruta, array $params): void
    {
        $this->actingAs($this->admin());

        $r = $this->get('/' . $ruta . '?' . http_build_query($params));

        $this->assertLessThan(500, $r->getStatusCode(), "{$ruta} respondió {$r->getStatusCode()}");
    }

    public static function fechasRaras(): array
    {
        $filas = [];
        foreach (['reportes/ventas', 'reportes/ventas/csv', 'reportes/ventas/pdf', 'reportes/compras', 'reportes/compras/csv', 'admin/dashboard'] as $ruta) {
            foreach (['abc', '2026-13-45', '', '0000-00-00', '9999-99-99', "2026-01-01\r\nX-Inyectado: 1", '31/12/2026', '<b>', '1900-01-01'] as $i => $f) {
                $filas["{$ruta} fecha#{$i}"] = [$ruta, ['periodo' => str_contains($ruta, 'compras') ? 'custom' : 'personalizado', 'desde' => $f, 'hasta' => $f, 'fecha_desde' => $f, 'fecha_hasta' => $f]];
            }
            $filas["{$ruta} invertidas"] = [$ruta, ['periodo' => 'personalizado', 'desde' => '2026-12-31', 'hasta' => '2026-01-01']];
            $filas["{$ruta} periodo raro"] = [$ruta, ['periodo' => '<x>', 'almacen_id' => 'abc', 'categoria_id' => "1' OR '1'='1"]];
        }

        return $filas;
    }

    /** @dataProvider fechasRaras */
    #[\PHPUnit\Framework\Attributes\DataProvider('fechasRaras')]
    public function test_reportes_con_fechas_raras_no_dan_500(string $ruta, array $params): void
    {
        $this->actingAs($this->admin());

        $r = $this->get('/' . $ruta . '?' . http_build_query($params));

        $this->assertLessThan(500, $r->getStatusCode(), "{$ruta} respondió {$r->getStatusCode()}");
        $disposicion = (string) $r->headers->get('Content-Disposition');
        $this->assertStringNotContainsString("\n", $disposicion);
        $this->assertStringNotContainsString('X-Inyectado', implode(',', array_keys($r->headers->all())));
    }
}
