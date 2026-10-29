<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Support\EtiquetaProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NombresCodigosNHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_nombre_junta_nombre_k_y_de_fabrica(): void
    {
        $this->assertSame('Ring (fábrica: Oxygen)', EtiquetaProducto::nombre(['nombre' => 'Ring', 'nombre_kyrios' => 'Ring', 'nombre_fabrica' => 'Oxygen']));
        $this->assertSame('Ring', EtiquetaProducto::nombre(['nombre' => 'Ring', 'nombre_kyrios' => 'Ring', 'nombre_fabrica' => 'ring']), 'si son iguales no se repite');
        $this->assertSame('Oxygen', EtiquetaProducto::nombre(['nombre' => 'Oxygen', 'nombre_kyrios' => null, 'nombre_fabrica' => 'Oxygen']));
        $this->assertSame('Ring', EtiquetaProducto::nombre(['nombre' => 'Ring', 'nombre_kyrios' => 'Ring', 'nombre_fabrica' => null]));
    }

    public function test_si_el_nombre_guardado_es_un_codigo_se_prefiere_el_nombre_de_fabrica(): void
    {
        $p = ['nombre' => '71-3544-25-25', 'codigo' => 'KY-AC0001-0001', 'nombre_kyrios' => null, 'nombre_fabrica' => 'Cell me',
            'variantes' => [['codigo_fabricante' => '71-3544-25-25', 'codigo_kyrios' => 'KY-AC0001-0001']]];

        $this->assertSame('Cell me', EtiquetaProducto::nombre($p));
    }

    public function test_sin_ningun_nombre_cae_al_codigo(): void
    {
        $this->assertSame('KY-LU0000-0001', EtiquetaProducto::nombre(['nombre' => '', 'codigo' => 'KY-LU0000-0001']));
    }

    public function test_codigos_muestran_el_kyrios_y_el_de_fabrica(): void
    {
        $v = ['codigo_kyrios' => 'KY-LU2501-0001', 'codigo_fabricante' => '25-0573-N3-B8'];
        $this->assertSame('KY-LU2501-0001 · fab: 25-0573-N3-B8', EtiquetaProducto::codigos(['codigo' => 'KY-LU2501-0001'], $v));
        $this->assertSame('KY-LU2501-0001', EtiquetaProducto::codigos(['codigo' => 'x'], ['codigo_kyrios' => 'KY-LU2501-0001', 'codigo_fabricante' => null]));
        $this->assertSame('KY-A · fab: F-1', EtiquetaProducto::codigos(['codigo' => 'KY-A', 'variantes' => [['codigo_fabricante' => null], ['codigo_fabricante' => 'F-1']]]));
    }

    public function test_la_busqueda_encuentra_por_nombre_de_fabrica_nombre_k_y_ambos_codigos(): void
    {
        $cat = Categoria::create(['codigo' => 'C', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $p = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'nombre_kyrios' => 'Ring', 'nombre_fabrica' => 'Oxygen',
            'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 10, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'codigo_fabricante' => '25-0573-N3-B8', 'codigo_excel' => 'KLU01-00003', 'estado' => 'activo']);

        foreach (['Oxygen', 'Ring', '25-0573', 'KY-LU2501', 'KLU01-00003'] as $q) {
            $this->assertSame(1, EtiquetaProducto::buscar(Producto::query(), $q)->count(), "no encontró «{$q}»");
        }
        $this->assertSame(0, EtiquetaProducto::buscar(Producto::query(), 'zzz')->count());
        $this->assertSame(0, EtiquetaProducto::buscar(Producto::query(), '%')->count(), 'el % se busca como texto');
    }
}
