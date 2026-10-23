<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\KyriosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Productos de luminarias Kyrios: codificación KY-…, variantes, filtros, búsqueda e imágenes.
 */
class ProductosKyriosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Categoria $categoria;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KyriosCatalogoSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
        $this->categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->unidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'categoria' => 'unidad', 'permite_decimales' => false]);
        $this->actingAs($this->admin);
    }

    private function datosProducto(string $tipo, string $marca, ?string $luminaria = null, array $extra = []): array
    {
        return array_merge([
            'nombre'            => 'Producto ' . uniqid(),
            'categoria_id'      => $this->categoria->id,
            'marca_id'          => Marca::where('nombre', $marca)->value('id'),
            'unidad_medida_id'  => $this->unidad->id,
            'tipo_inventario'   => 'cantidad',
            'stock_minimo'      => 0,
            'stock_maximo'      => 100,
            'estado'            => 'activo',
            'tipo_producto_id'  => TipoProducto::where('codigo', $tipo)->value('id'),
            'tipo_luminaria_id' => $luminaria ? TipoLuminaria::where('nombre', $luminaria)->value('id') : null,
            'variante_inicial'  => ['acabado_1' => 'negro(a)', 'socket' => 'E27'],
        ], $extra);
    }

    private function crear(array $datos): Producto
    {
        $this->post(route('inventario.productos.store'), $datos)->assertSessionHasNoErrors();

        return Producto::where('nombre', $datos['nombre'])->firstOrFail();
    }

    // ── Codificación ──────────────────────────────────────────────────────

    public function test_caso_1_incluye_el_tipo_de_luminaria_en_el_codigo(): void
    {
        $p = $this->crear($this->datosProducto('SO', 'OSRAM', 'de pie'));

        $this->assertSame('KY-SO2527-0001', $p->codigo);
        $this->assertSame('KY-SO2527-0001', $p->variantes()->first()->codigo_kyrios);
        $this->assertSame('KY-SO2527-0001', $p->variantes()->first()->sku);
    }

    public function test_caso_2_usa_00_aunque_se_envie_luminaria(): void
    {
        $p = $this->crear($this->datosProducto('LA', 'PHILIPS', 'de pie'));

        $this->assertSame('KY-LA0029-0001', $p->codigo);
    }

    public function test_el_correlativo_se_reinicia_por_tipo_luminaria_y_marca(): void
    {
        $a1 = $this->crear($this->datosProducto('SO', 'OSRAM', 'de pie'));
        $a2 = $this->crear($this->datosProducto('SO', 'OSRAM', 'de pie'));
        $otraMarca = $this->crear($this->datosProducto('SO', 'PHILIPS', 'de pie'));
        $otraLuminaria = $this->crear($this->datosProducto('SO', 'OSRAM', 'colgante'));

        $this->assertSame('KY-SO2527-0001', $a1->codigo);
        $this->assertSame('KY-SO2527-0002', $a2->codigo);
        $this->assertSame('KY-SO2529-0001', $otraMarca->codigo);
        $this->assertSame('KY-SO2027-0001', $otraLuminaria->codigo);
    }

    public function test_los_codigos_de_luminaria_repetidos_por_diseno_conviven(): void
    {
        // «de mesa» y «de escritorio» comparten el código 10 (intencional)
        $mesa = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'de mesa'));
        $escritorio = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'de escritorio'));

        $this->assertSame('KY-LU1001-0001', $mesa->codigo);
        $this->assertSame('KY-LU1001-0002', $escritorio->codigo);
    }

    public function test_los_estilos_se_guardan_y_uno_invalido_se_rechaza(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante', ['estilos' => ['moderno', 'nordico']]));
        $this->assertSame(['moderno', 'nordico'], $p->estilos);

        $this->post(route('inventario.productos.store'), $this->datosProducto('LU', 'LEDS-C4', 'colgante', ['estilos' => ['no-existe']]))
            ->assertSessionHasErrors('estilos.0');
    }

    public function test_al_editar_no_cambia_el_codigo_ni_el_tipo(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'));
        $codigo = $p->codigo;
        $tipo = $p->tipo_producto_id;

        $this->put(route('inventario.productos.update', $p), [
            'nombre' => 'Nombre editado', 'categoria_id' => $this->categoria->id, 'unidad_medida_id' => $this->unidad->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo',
            'tipo_producto_id' => TipoProducto::where('codigo', 'LA')->value('id'), 'linea' => 'GROK',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Nombre editado', $p->nombre);
        $this->assertSame('GROK', $p->linea);
        $this->assertSame($codigo, $p->codigo);
        $this->assertSame($tipo, $p->tipo_producto_id);
    }

    // ── Variantes ─────────────────────────────────────────────────────────

    public function test_una_variante_nueva_recibe_el_siguiente_codigo(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'));

        $this->post(route('inventario.productos.variantes.store', $p), ['acabado_1' => 'blanco(a)', 'tamano' => 'grande'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['KY-LU2001-0001', 'KY-LU2001-0002'], $p->variantes()->orderBy('id')->pluck('codigo_kyrios')->all());
    }

    public function test_una_variante_con_los_mismos_atributos_se_rechaza(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'));

        // La primera variante se creó con acabado negro(a) y socket E27
        $this->post(route('inventario.productos.variantes.store', $p), ['acabado_1' => 'negro(a)', 'socket' => 'E27'])
            ->assertSessionHas('error');

        $this->assertSame(1, $p->variantes()->count());
    }

    public function test_editar_una_variante_no_cambia_su_codigo(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'));
        $v = $p->variantes()->first();

        $this->put(route('inventario.productos.variantes.update', $v), ['acabado_1' => 'cromo', 'estado_dano' => 'grave', 'observacion_dano' => 'base rota', 'stock_minimo' => 2])
            ->assertSessionHasNoErrors();

        $v->refresh();
        $this->assertSame('cromo', $v->acabado_1);
        $this->assertSame('grave', $v->estado_dano);
        $this->assertSame('KY-LU2001-0001', $v->codigo_kyrios);
    }

    // ── Listado: filtros y búsqueda ───────────────────────────────────────

    private function listado(array $filtros = []): int
    {
        return $this->get(route('inventario.productos.index', $filtros))->assertOk()->viewData('productos')->total();
    }

    public function test_el_listado_filtra_por_tipo_marca_estilo_acabado_y_dano(): void
    {
        $a = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante', ['estilos' => ['moderno'], 'fuente_luz' => 'LED']));
        $b = $this->crear($this->datosProducto('LA', 'PHILIPS', null, ['fuente_luz' => 'halógena', 'variante_inicial' => ['acabado_1' => 'cromo']]));
        $b->variantes()->first()->update(['estado_dano' => 'moderado']);

        $this->assertSame(2, $this->listado());
        $this->assertSame(1, $this->listado(['tipo_producto_id' => $a->tipo_producto_id]));
        $this->assertSame(1, $this->listado(['marca_id' => $b->marca_id]));
        $this->assertSame(1, $this->listado(['estilo' => 'moderno']));
        $this->assertSame(1, $this->listado(['fuente_luz' => 'halógena']));
        $this->assertSame(1, $this->listado(['acabado' => 'cromo']));
        $this->assertSame(1, $this->listado(['estado_dano' => 'moderado']));
        $this->assertSame(0, $this->listado(['estado_dano' => 'grave']));
        $this->assertSame(0, $this->listado(['estilo' => 'moderno', 'marca_id' => $b->marca_id]), 'los filtros se combinan');
    }

    public function test_el_listado_busca_por_los_tres_codigos_y_por_el_nombre(): void
    {
        $p = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante', ['nombre' => 'Lampara Aurora']));
        $p->variantes()->first()->update(['codigo_fabricante' => '25-0573-N3-B8', 'codigo_excel' => 'KLU01-00003']);

        $this->assertSame(1, $this->listado(['buscar' => 'KY-LU2001-0001']));
        $this->assertSame(1, $this->listado(['buscar' => '25-0573-N3-B8']));
        $this->assertSame(1, $this->listado(['buscar' => 'KLU01-00003']));
        $this->assertSame(1, $this->listado(['buscar' => 'Aurora']));
        $this->assertSame(0, $this->listado(['buscar' => 'inexistente-xyz']));
    }

    // ── Imágenes (máximo 6 por variante) ──────────────────────────────────

    private function imagenes(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("foto{$i}.jpg", 200, 200), range(1, $n));
    }

    public function test_una_variante_admite_hasta_seis_imagenes(): void
    {
        Storage::fake('public');
        $v = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'))->variantes()->first();

        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(4)])->assertSessionHasNoErrors();
        $this->assertSame(4, $v->imagenes()->count());
        $this->assertSame(1, $v->imagenes()->where('es_principal', true)->count(), 'la primera queda como principal');

        // 4 + 3 = 7: se rechaza todo el lote
        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(3)])->assertSessionHas('error');
        $this->assertSame(4, $v->imagenes()->count());

        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(2)])->assertSessionHasNoErrors();
        $this->assertSame(6, $v->imagenes()->count());
        $this->assertSame([1, 2, 3, 4, 5, 6], $v->imagenes()->pluck('orden')->all());
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        Storage::fake('public');
        $v = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'))->variantes()->first();

        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('imagenes.0');
    }

    public function test_eliminar_la_principal_pasa_la_marca_a_otra_y_se_borra_el_archivo(): void
    {
        Storage::fake('public');
        $v = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'))->variantes()->first();
        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(2)]);

        $principal = $v->imagenes()->where('es_principal', true)->first();
        Storage::disk('public')->assertExists($principal->ruta);

        $this->delete(route('inventario.productos.imagenes.destroy', $principal));

        Storage::disk('public')->assertMissing($principal->ruta);
        $this->assertSame(1, $v->imagenes()->count());
        $this->assertSame(1, $v->imagenes()->where('es_principal', true)->count());
    }

    public function test_se_puede_cambiar_la_imagen_principal(): void
    {
        Storage::fake('public');
        $v = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'))->variantes()->first();
        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(3)]);

        $tercera = ProductoImagen::where('variante_id', $v->id)->where('orden', 3)->first();
        $this->post(route('inventario.productos.imagenes.principal', $tercera));

        $this->assertTrue($tercera->fresh()->es_principal);
        $this->assertSame(1, $v->imagenes()->where('es_principal', true)->count());
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function test_un_vendedor_no_puede_crear_productos_ni_subir_imagenes(): void
    {
        $v = $this->crear($this->datosProducto('LU', 'LEDS-C4', 'colgante'))->variantes()->first();

        $vendedor = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Vendedor'])->id, 'estado' => 'activo']);
        $this->actingAs($vendedor);

        $this->get(route('inventario.productos.create'))->assertForbidden();
        $this->post(route('inventario.productos.variantes.imagenes.store', $v), ['imagenes' => $this->imagenes(1)])->assertForbidden();
    }
}
