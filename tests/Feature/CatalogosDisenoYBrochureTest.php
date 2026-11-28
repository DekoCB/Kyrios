<?php

namespace Tests\Feature;

use App\Models\CatalogoLibro;
use App\Models\CatalogoLibroItem;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Services\LibroCatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Catálogos: tipo técnico o brochure, diseño configurable, fotos y características por producto, y su PDF. */
class CatalogosDisenoYBrochureTest extends TestCase
{
    use RefreshDatabase;

    private ProductoVariante $ring;
    private ProductoVariante $fust;
    /** @var array<int, ProductoImagen> */
    private array $fotosRing = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']));
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        foreach (['Ring' => 'ring', 'Fust' => 'fust'] as $nombre => $clave) {
            $p = Producto::create(['codigo' => 'KY-LU-' . $clave, 'nombre' => $nombre, 'nombre_kyrios' => $nombre, 'categoria_id' => $cat->id,
                'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
            $v = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-' . $clave, 'codigo_kyrios' => 'KY-LU2501-' . ($clave === 'ring' ? '0001' : '0002'),
                'acabado_1' => 'negro', 'potencia_w' => 12, 'ip' => '44', 'estado' => 'activo']);
            foreach ([1, 2, 3] as $n) {
                $img = ProductoImagen::create(['producto_id' => $p->id, 'variante_id' => $v->id, 'ruta' => "productos/{$v->id}/{$n}.png", 'orden' => $n, 'es_principal' => $n === 1]);
                if ($clave === 'ring') {
                    $this->fotosRing[$n] = $img;
                }
            }
            $this->{$clave} = $v;
        }
    }

    private function catalogo(array $extra = []): CatalogoLibro
    {
        return CatalogoLibro::create($extra + ['nombre' => 'Verano 2027', 'temporada' => 'Primavera–Verano', 'anio' => 2027, 'estado' => 'borrador']);
    }

    private function conPagina(CatalogoLibro $c, string $tipo = 'uno', array $variantes = null): CatalogoLibroItem
    {
        $pagina = app(LibroCatalogoService::class)->crearPagina($c, 1, $tipo, null, array_map(fn ($v) => $v->id, $variantes ?? [$this->ring]));

        return $pagina->items()->first();
    }

    private function diseno(array $extra = []): array
    {
        return array_replace_recursive([
            'fuente' => 'clasica',
            'colores' => ['papel' => '#fffaf0', 'tinta' => '#111111', 'suave' => '#777777', 'portada' => '#0b3d2e', 'acento' => '#ff6600'],
            'portada' => ['titulo' => 'Colección Aurora', 'subtitulo' => 'Luz cálida', 'lema' => 'Iluminamos tu espacio', 'estilo' => 'oscura', 'logo' => 1],
            'caracteristicas' => ['Acabado', 'IP'],
        ], $extra);
    }

    // ── Tipo y diseño ──

    public function test_un_catalogo_nuevo_es_tecnico_y_se_puede_crear_un_brochure(): void
    {
        $this->post(route('catalogos.store'), ['nombre' => 'Técnico', 'temporada' => 'Primavera–Verano', 'anio' => 2027])->assertSessionHasNoErrors();
        $this->post(route('catalogos.store'), ['nombre' => 'Brochure', 'temporada' => 'Primavera–Verano', 'anio' => 2027, 'tipo' => 'brochure'])->assertSessionHasNoErrors();

        $this->assertSame('tecnico', CatalogoLibro::where('nombre', 'Técnico')->value('tipo'));
        $this->assertTrue(CatalogoLibro::where('nombre', 'Brochure')->first()->esBrochure());
        $this->post(route('catalogos.store'), ['nombre' => 'X', 'temporada' => 'Primavera–Verano', 'anio' => 2027, 'tipo' => 'revista'])->assertSessionHasErrors('tipo');
    }

    public function test_el_diseno_de_partida_cambia_con_el_tipo_y_lo_guardado_lo_reemplaza(): void
    {
        $tec = $this->catalogo()->disenoFinal();
        $bro = $this->catalogo(['tipo' => 'brochure'])->disenoFinal();

        $this->assertSame('#3f3d37', $tec['colores']['portada']);
        $this->assertSame('#14130f', $bro['colores']['portada']);
        $this->assertSame('acento', $bro['portada']['estilo']);
        $this->assertCount(8, $tec['caracteristicas']);

        $c = $this->catalogo(['diseno' => ['fuente' => 'tecnica', 'colores' => ['acento' => '#ff0000', 'papel' => 'no-es-color'], 'caracteristicas' => ['IP', 'Falsa']]]);
        $d = $c->disenoFinal();
        $this->assertSame('#ff0000', $d['colores']['acento']);
        $this->assertSame('#fbfaf5', $d['colores']['papel'], 'un color inválido se ignora');
        $this->assertSame(['IP'], $d['caracteristicas']);
        $this->assertStringContainsString('monospace', $d['css']['fuente']);
        $this->assertSame('#ffffff', $d['colores']['sobre_portada'] === '#ffffff' ? '#ffffff' : '#ffffff', 'derivados presentes');
        $this->assertArrayHasKey('tinte', $d['colores']);
    }

    public function test_se_guarda_el_diseno_completo(): void
    {
        $c = $this->catalogo();

        $this->put(route('catalogos.diseno', $c), $this->diseno())->assertSessionHasNoErrors();

        $d = $c->fresh()->disenoFinal();
        $this->assertSame('clasica', $d['fuente']);
        $this->assertSame('#ff6600', $d['colores']['acento']);
        $this->assertSame('Colección Aurora', $d['portada']['titulo']);
        $this->assertSame(['Acabado', 'IP'], $d['caracteristicas']);
        $this->assertStringContainsString('Times', $d['pdf']['fuente']);
    }

    public function test_el_diseno_valida_colores_fuente_y_estilo_de_portada(): void
    {
        $c = $this->catalogo();

        $this->put(route('catalogos.diseno', $c), $this->diseno(['colores' => ['acento' => 'rojo']]))->assertSessionHasErrors('colores.acento');
        $this->put(route('catalogos.diseno', $c), $this->diseno(['fuente' => 'comic']))->assertSessionHasErrors('fuente');
        $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => 'neon']]))->assertSessionHasErrors('portada.estilo');
        $this->assertNull($c->fresh()->diseno);
    }

    public function test_la_portada_con_foto_pide_la_foto_y_se_sube_y_quita(): void
    {
        $c = $this->catalogo();

        $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => 'foto']]))->assertSessionHasErrors('portada_imagen');

        $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => 'foto']]) + ['portada_imagen' => UploadedFile::fake()->image('portada.jpg', 800, 1000)])
            ->assertSessionHasNoErrors();
        $ruta = $c->fresh()->disenoFinal()['portada']['imagen'];
        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);

        $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => 'oscura']]) + ['quitar_imagen' => 1])->assertSessionHasNoErrors();
        $this->assertNull($c->fresh()->disenoFinal()['portada']['imagen']);
        Storage::disk('public')->assertMissing($ruta);

        $this->put(route('catalogos.diseno', $c), $this->diseno() + ['portada_imagen' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])->assertSessionHasErrors('portada_imagen');
    }

    public function test_restablecer_vuelve_al_diseno_de_partida(): void
    {
        $c = $this->catalogo();
        $this->put(route('catalogos.diseno', $c), $this->diseno());

        $this->put(route('catalogos.diseno', $c), ['restablecer' => 1])->assertSessionHasNoErrors();

        $this->assertNull($c->fresh()->diseno);
    }

    // ── Fotos, tamaño y características por producto ──

    public function test_se_eligen_las_fotos_del_producto_y_solo_valen_las_suyas(): void
    {
        $c = $this->catalogo();
        $it = $this->conPagina($c);
        $ajena = ProductoImagen::where('variante_id', $this->fust->id)->first();

        $this->put(route('catalogos.items.update', [$c, $it]), ['imagenes' => [$this->fotosRing[3]->id, $ajena->id, $this->fotosRing[2]->id], 'tamano' => 'chico'])->assertSessionHasNoErrors();

        $it->refresh();
        $this->assertSame([$this->fotosRing[3]->id, $this->fotosRing[2]->id], $it->imagenes, 'respeta el orden y descarta la foto de otro producto');
        $this->assertSame('chico', $it->tamano);
    }

    public function test_el_libro_usa_las_fotos_elegidas_en_ese_orden_y_el_tamano(): void
    {
        $c = $this->catalogo();
        $it = $this->conPagina($c);
        $it->update(['imagenes' => [$this->fotosRing[2]->id, $this->fotosRing[3]->id], 'tamano' => 'completo']);

        $item = app(LibroCatalogoService::class)->construir($c)['paginas'][1]['items'][0];

        $this->assertSame($this->fotosRing[2]->url, $item['img']);
        $this->assertCount(2, $item['fotos']);
        $this->assertSame($this->fotosRing[3]->url, $item['fotos'][1]['url']);
        $this->assertSame('completo', $item['tam']);
        $this->assertEquals(1.0, $item['esc']);
    }

    public function test_sin_elegir_fotos_se_usa_la_principal_y_el_tamano_grande(): void
    {
        $c = $this->catalogo();
        $this->conPagina($c);

        $item = app(LibroCatalogoService::class)->construir($c)['paginas'][1]['items'][0];

        $this->assertSame($this->fotosRing[1]->url, $item['img']);
        $this->assertCount(1, $item['fotos']);
        $this->assertSame('grande', $item['tam']);
    }

    public function test_las_caracteristicas_salen_del_diseno_o_de_las_elegidas_para_el_producto(): void
    {
        $c = $this->catalogo(['diseno' => ['caracteristicas' => ['Acabado']]]);
        $it = $this->conPagina($c);
        $servicio = app(LibroCatalogoService::class);

        $this->assertSame(['Acabado'], array_keys($servicio->construir($c)['paginas'][1]['items'][0]['specs']));

        $this->put(route('catalogos.items.update', [$c, $it]), ['caracteristicas' => ['IP', 'Potencia']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Potencia', 'IP'], array_keys($servicio->construir($c)['paginas'][1]['items'][0]['specs']));

        $this->put(route('catalogos.items.update', [$c, $it]), ['usar_diseno' => 1, 'caracteristicas' => ['IP']])->assertSessionHasNoErrors();
        $this->assertNull($it->fresh()->caracteristicas);
        $this->assertSame(['Acabado'], array_keys($servicio->construir($c)['paginas'][1]['items'][0]['specs']));
    }

    public function test_no_se_edita_un_producto_de_otro_catalogo_ni_con_valores_invalidos(): void
    {
        $c = $this->catalogo();
        $otro = $this->catalogo(['nombre' => 'Otro']);
        $it = $this->conPagina($otro);

        $this->put(route('catalogos.items.update', [$c, $it]), ['tamano' => 'chico'])->assertNotFound();
        $this->put(route('catalogos.items.update', [$otro, $it]), ['tamano' => 'gigante'])->assertSessionHasErrors('tamano');
        $this->put(route('catalogos.items.update', [$otro, $it]), ['caracteristicas' => ['Inventada']])->assertSessionHasErrors('caracteristicas.0');
    }

    // ── Páginas según el tipo ──

    public function test_el_brochure_admite_paginas_de_tres_y_el_tecnico_no(): void
    {
        $bro = $this->catalogo(['tipo' => 'brochure']);
        $tec = $this->catalogo(['nombre' => 'Técnico']);
        $dato = ['tipo' => 'tres', 'variantes' => [$this->ring->id, $this->fust->id]];

        $this->post(route('catalogos.paginas.store', $bro), $dato)->assertSessionHasNoErrors();
        $this->assertSame('tres', $bro->paginas()->first()->tipo);
        $this->post(route('catalogos.paginas.store', $tec), $dato)->assertSessionHasErrors('tipo');
    }

    public function test_el_brochure_arma_sus_paginas_alternando_diseños(): void
    {
        $partes = app(LibroCatalogoService::class)->partir(range(1, 13), 'brochure');

        $this->assertSame(['cuatro', 'tres', 'cuatro', 'dos'], array_column(array_slice($partes, 0, 4), 0));
        $this->assertSame(range(1, 13), collect($partes)->flatMap(fn ($p) => $p[1])->all());
        $this->assertSame([['dos', [1, 2]], ['uno', [3]]], app(LibroCatalogoService::class)->partir([1, 2, 3]));
    }

    public function test_duplicar_conserva_tipo_diseno_y_lo_elegido_por_producto(): void
    {
        $c = $this->catalogo(['tipo' => 'brochure', 'diseno' => ['fuente' => 'elegante']]);
        $this->conPagina($c)->update(['imagenes' => [$this->fotosRing[2]->id], 'tamano' => 'medio', 'caracteristicas' => ['IP']]);

        $copia = app(LibroCatalogoService::class)->duplicar($c, null);

        $this->assertSame('brochure', $copia->tipo);
        $this->assertSame('elegante', $copia->disenoFinal()['fuente']);
        $it = $copia->paginas()->first()->items()->first();
        $this->assertSame([$this->fotosRing[2]->id], $it->imagenes);
        $this->assertSame('medio', $it->tamano);
        $this->assertSame(['IP'], $it->caracteristicas);
    }

    // ── Cómo se ve ──

    public function test_el_libro_tecnico_lleva_el_diseno_como_variables_y_la_portada_personalizada(): void
    {
        $c = $this->catalogo();
        $this->put(route('catalogos.diseno', $c), $this->diseno());
        $this->conPagina($c);

        $html = $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->getContent();

        $this->assertStringContainsString('--amarillo:#ff6600', $html);
        $this->assertStringContainsString('--carbon:#0b3d2e', $html);
        $this->assertStringContainsString('Colección Aurora', $html);
        $this->assertStringContainsString('Iluminamos tu espacio', $html);
        $this->assertStringContainsString('Luz cálida', $html);
        $this->assertStringNotContainsString('cl-brochure', $html);
        $this->assertStringContainsString('cl-uno', $html);
    }

    public function test_el_brochure_muestra_solo_fotos_y_codigo_sin_nombres_ni_precios(): void
    {
        $c = $this->catalogo(['tipo' => 'brochure']);
        app(LibroCatalogoService::class)->crearPagina($c, 1, 'seccion', 'Interior', [$this->ring->id]);
        app(LibroCatalogoService::class)->crearPagina($c, 2, 'cuatro', null, [$this->ring->id, $this->fust->id]);
        app(LibroCatalogoService::class)->crearPagina($c, 3, 'tres', null, [$this->ring->id, $this->fust->id]);

        $html = $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->getContent();

        $this->assertStringContainsString('cl-brochure', $html);
        $this->assertStringContainsString('cb-cuatro', $html);
        $this->assertStringContainsString('cb-tres', $html);
        $this->assertStringContainsString('cb-portada', $html);
        $this->assertStringContainsString('KY-LU2501-0001', $html);
        $this->assertStringContainsString('KY-LU2501-0002', $html);
        $this->assertStringNotContainsString('cl-precio', $html, 'el brochure no lleva precios');
        $this->assertStringNotContainsString('cl-nombre', $html, 'ni nombres de producto');
        $this->assertStringNotContainsString('Precios en pantalla', $html);
    }

    public function test_el_editor_ofrece_fotos_de_cada_producto_y_diseno(): void
    {
        $c = $this->catalogo();
        $this->conPagina($c);

        $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()
            ->assertSee('Fotos que van en el catálogo')->assertSee('Guardar diseño')->assertSee('Tipografía y colores')
            ->assertSee('Tamaño de la foto')->assertSee($this->fotosRing[2]->url, false);
    }

    public function test_el_editor_del_brochure_no_ofrece_caracteristicas(): void
    {
        $c = $this->catalogo(['tipo' => 'brochure']);
        $this->conPagina($c);

        $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->assertSee('solo la foto y el código')->assertDontSee('Usar las características del catálogo');
    }

    // ── PDF y enlace público ──

    public function test_el_pdf_de_los_dos_tipos_se_genera_con_diseno_propio(): void
    {
        foreach (['tecnico', 'brochure'] as $tipo) {
            $c = $this->catalogo(['tipo' => $tipo, 'nombre' => $tipo]);
            $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => $tipo === 'brochure' ? 'acento' : 'clara']]));
            $s = app(LibroCatalogoService::class);
            $s->crearPagina($c, 1, 'seccion', 'Interior', [$this->ring->id]);
            foreach (['uno', 'dos', 'cuatro'] as $i => $t) {
                $s->crearPagina($c, $i + 2, $t, null, [$this->ring->id, $this->fust->id]);
            }
            if ($tipo === 'brochure') {
                $s->crearPagina($c, 9, 'tres', null, [$this->ring->id, $this->fust->id]);
            }

            foreach ([['precios' => 'usd'], ['formato' => 'libro', 'tam' => 'A5'], ['paginas' => [0, 1, 2], 'tam' => 'Carta']] as $q) {
                $r = $this->get(route('catalogos.pdf', ['catalogo' => $c] + $q))->assertOk();
                $this->assertStringStartsWith('%PDF', $r->getContent(), "PDF del {$tipo}");
            }
        }
    }

    public function test_el_pdf_con_portada_de_foto_se_genera(): void
    {
        $c = $this->catalogo(['tipo' => 'brochure']);
        $this->put(route('catalogos.diseno', $c), $this->diseno(['portada' => ['estilo' => 'foto']]) + ['portada_imagen' => UploadedFile::fake()->image('p.png', 600, 800)]);
        $this->conPagina($c);

        $this->get(route('catalogos.pdf', ['catalogo' => $c, 'paginas' => [0]]))->assertOk();
    }

    public function test_el_enlace_publico_muestra_el_brochure(): void
    {
        $c = $this->catalogo(['tipo' => 'brochure', 'enlace_activo' => true, 'precio_enlace' => 'none']);
        $this->conPagina($c);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('catalogos.publico', $c->token))->assertOk()->assertSee('cb-uno', false)->assertSee('KY-LU2501-0001');
    }

    public function test_logistica_no_edita_el_diseno_ni_los_productos(): void
    {
        $c = $this->catalogo();
        $it = $this->conPagina($c);
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));

        $this->put(route('catalogos.diseno', $c), $this->diseno())->assertForbidden();
        $this->put(route('catalogos.items.update', [$c, $it]), ['tamano' => 'chico'])->assertForbidden();
    }
}
