<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CotizacionService;
use App\Support\SeccionesProforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cotizaciones por ambiente, por piso o simples; proforma oficial con número IL-xxxxx/yy y ficha técnica. */
class CotizacionFormatosProformaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $almacen;
    private Cliente $cliente;
    private ProductoVariante $v1;
    private ProductoVariante $v2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        foreach (['v1' => ['Ring', '0001'], 'v2' => ['Frame', '0002']] as $prop => [$nombre, $n]) {
            $p = Producto::create(['codigo' => "KY-LU2501-{$n}", 'nombre' => $nombre, 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
            $this->{$prop} = ProductoVariante::create(['producto_id' => $p->id, 'sku' => "KY-{$n}", 'codigo_kyrios' => "KY-LU2501-{$n}", 'estado' => 'activo']);
        }
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Carlos Eldredg', 'estado' => 'activo']);
    }

    private function linea(ProductoVariante $v, array $extra = []): array
    {
        return array_merge(['producto_id' => $v->producto_id, 'variante_id' => $v->id, 'cantidad' => 1, 'precio_unitario' => 118, 'incluye_igv' => true], $extra);
    }

    private function crear(string $formato, array $lineas): Cotizacion
    {
        return app(CotizacionService::class)->crear([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id,
            'formato' => $formato, 'referencia' => 'VIVIENDA - Prueba de Luces',
        ], $lineas);
    }

    public function test_los_titulos_de_cada_formato(): void
    {
        $this->assertNull(SeccionesProforma::titulo('simple', 'Primer nivel', 'Hall'));
        $this->assertSame('SALA - COMEDOR', SeccionesProforma::titulo('ambiente', null, 'Sala - Comedor'));
        $this->assertSame('SIN AMBIENTE', SeccionesProforma::titulo('ambiente', 'Primer nivel', ''));
        $this->assertSame('PRIMER NIVEL - HALL', SeccionesProforma::titulo('piso', 'Primer nivel', 'Hall'));
        $this->assertSame('SEGUNDO NIVEL', SeccionesProforma::titulo('piso', 'Segundo nivel', ''));
    }

    public function test_cada_formato_guarda_solo_lo_que_usa(): void
    {
        $lineas = [$this->linea($this->v1, ['piso' => 'Primer nivel', 'ambiente' => 'Hall'])];

        $simple = $this->crear('simple', $lineas)->detalles->first();
        $this->assertNull($simple->piso);
        $this->assertNull($simple->ambiente);

        $ambiente = $this->crear('ambiente', $lineas)->detalles->first();
        $this->assertNull($ambiente->piso);
        $this->assertSame('Hall', $ambiente->ambiente);

        $piso = $this->crear('piso', $lineas)->detalles->first();
        $this->assertSame('Primer nivel', $piso->piso);
        $this->assertSame('Hall', $piso->ambiente);

        $this->assertSame('simple', $this->crear('inventado', $lineas)->formato, 'un formato desconocido cae en simple');
    }

    public function test_las_lineas_se_agrupan_por_seccion_conservando_el_orden(): void
    {
        $c = $this->crear('piso', [
            $this->linea($this->v1, ['piso' => 'Primer nivel', 'ambiente' => 'Hall']),
            $this->linea($this->v2, ['piso' => 'Segundo nivel', 'ambiente' => 'Coworking']),
            $this->linea($this->v2, ['piso' => 'Primer nivel', 'ambiente' => 'Hall']),
        ]);

        $orden = SeccionesProforma::ordenar($c->detalles, 'piso')->map(fn ($d) => $d->piso . '/' . $d->ambiente)->all();
        $this->assertSame(['Primer nivel/Hall', 'Primer nivel/Hall', 'Segundo nivel/Coworking'], $orden);
    }

    public function test_se_guarda_y_edita_desde_el_formulario(): void
    {
        $r = $this->post(route('cotizaciones.store'), [
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'validez_dias' => 7, 'formato' => 'ambiente', 'referencia' => 'Casa Lima',
            'detalles' => [
                ['producto_id' => $this->v1->producto_id, 'variante_id' => $this->v1->id, 'cantidad' => 1, 'precio_unitario' => 118, 'ambiente' => 'Cocina'],
                ['producto_id' => $this->v2->producto_id, 'variante_id' => $this->v2->id, 'cantidad' => 1, 'precio_unitario' => 118, 'ambiente' => 'Baño'],
            ],
        ])->assertSessionHasNoErrors();
        $c = Cotizacion::latest('id')->first();
        $r->assertRedirect(route('cotizaciones.show', $c));
        $this->assertSame('ambiente', $c->formato);
        $this->assertSame(['Cocina', 'Baño'], $c->detalles->pluck('ambiente')->all());

        $this->get(route('cotizaciones.edit', $c))->assertOk()->assertSee('Formato de la proforma')->assertSee('Casa Lima');
        $this->get(route('cotizaciones.show', $c))->assertOk()->assertSee('COCINA')->assertSee('BAÑO');
        $this->get(route('cotizaciones.create'))->assertOk()->assertSee('Por piso');
    }

    public function test_la_proforma_pdf_sale_en_los_tres_formatos_con_numero_oficial_estable(): void
    {
        $simple = $this->crear('simple', [$this->linea($this->v1)]);
        $ambiente = $this->crear('ambiente', [$this->linea($this->v1, ['ambiente' => 'Sala']), $this->linea($this->v2, ['ambiente' => 'Cocina'])]);
        $piso = $this->crear('piso', [$this->linea($this->v1, ['piso' => 'Primer nivel', 'ambiente' => 'Hall'])]);

        foreach ([$simple, $ambiente, $piso] as $c) {
            $r = $this->get(route('cotizaciones.pdf', $c))->assertOk();
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        $this->get(route('cotizaciones.pdf', [$ambiente, 'moneda' => 'PEN']))->assertOk();

        $a = $simple->fresh()->proforma_numero;
        $b = $ambiente->fresh()->proforma_numero;
        $this->assertMatchesRegularExpression('#^IL-\d{5}/\d{2}$#', $a);
        $this->assertNotSame($a, $b, 'cada cotización tiene su propio número');
        $this->get(route('cotizaciones.pdf', $simple))->assertOk();
        $this->assertSame($a, $simple->fresh()->proforma_numero, 'el número no cambia al volver a generar el PDF');
    }

    public function test_duplicar_conserva_formato_y_ubicacion_de_cada_producto(): void
    {
        $original = $this->crear('piso', [$this->linea($this->v1, ['piso' => 'Segundo nivel', 'ambiente' => 'Gimnasio'])]);
        $copia = app(CotizacionService::class)->duplicar($original->load('detalles'), $this->admin);

        $this->assertSame('piso', $copia->formato);
        $this->assertSame('VIVIENDA - Prueba de Luces', $copia->referencia);
        $this->assertSame('Segundo nivel', $copia->detalles->first()->piso);
        $this->assertSame('Gimnasio', $copia->detalles->first()->ambiente);
        $this->assertNull($copia->proforma_numero, 'la copia estrena su propio número cuando se emite');
    }

    public function test_la_ficha_tecnica_oficial_sale_en_pdf(): void
    {
        $c = $this->crear('simple', [$this->linea($this->v1), $this->linea($this->v1), $this->linea($this->v2)]);

        $r = $this->get(route('cotizaciones.ficha-tecnica', $c))->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }
}
