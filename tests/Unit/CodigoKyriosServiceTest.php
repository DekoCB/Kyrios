<?php

namespace Tests\Unit;

use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use App\Services\CodigoKyriosService;
use PHPUnit\Framework\TestCase;

/**
 * Ejemplos del PDF "Codificacion KYRIOS - PRODUCTOS":
 *   Caso 1: KY-SO1404-0015   (solares, luminaria 14, marca 04, correlativo 15)
 *   Caso 2: KY-LA0029-0004   (lámpara, luminaria "00", marca 29, correlativo 4)
 */
class CodigoKyriosServiceTest extends TestCase
{
    private CodigoKyriosService $servicio;

    protected function setUp(): void
    {
        $this->servicio = new CodigoKyriosService();
    }

    public function test_caso_1_incluye_el_tipo_de_luminaria(): void
    {
        $prefijo = $this->servicio->prefijoPara(
            new TipoProducto(['codigo' => 'SO', 'caso_codigo' => 'con_luminaria']),
            new TipoLuminaria(['codigo' => '14']),
            new Marca(['codigo' => '04'])
        );

        $this->assertSame('SO1404', $prefijo);
        $this->assertSame('KY-SO1404-0015', $this->servicio->formatear($prefijo, 15));
    }

    public function test_caso_2_usa_00_aunque_se_indique_luminaria(): void
    {
        $prefijo = $this->servicio->prefijoPara(
            new TipoProducto(['codigo' => 'LA', 'caso_codigo' => 'sin_luminaria']),
            new TipoLuminaria(['codigo' => '25']),
            new Marca(['codigo' => '29'])
        );

        $this->assertSame('LA0029', $prefijo);
        $this->assertSame('KY-LA0029-0004', $this->servicio->formatear($prefijo, 4));
    }

    public function test_caso_1_sin_luminaria_ni_marca_usa_00(): void
    {
        $prefijo = $this->servicio->prefijoPara(
            new TipoProducto(['codigo' => 'LU', 'caso_codigo' => 'con_luminaria']),
            null,
            null
        );

        $this->assertSame('LU0000', $prefijo);
    }

    public function test_el_codigo_de_luminaria_puede_ser_alfabetico(): void
    {
        $prefijo = $this->servicio->prefijoPara(
            new TipoProducto(['codigo' => 'LU', 'caso_codigo' => 'con_luminaria']),
            new TipoLuminaria(['codigo' => 'ET']),
            new Marca(['codigo' => '01'])
        );

        $this->assertSame('LUET01', $prefijo);
    }

    public function test_el_correlativo_tiene_cuatro_digitos(): void
    {
        $this->assertSame('KY-LU2501-0001', $this->servicio->formatear('LU2501', 1));
        $this->assertSame('KY-LU2501-1234', $this->servicio->formatear('LU2501', 1234));
    }
}
