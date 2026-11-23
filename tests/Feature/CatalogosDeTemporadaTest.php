<?php

namespace Tests\Feature;

use App\Models\CatalogoLibro;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\User;
use App\Services\LibroCatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Catálogos de temporada: libro 3D, páginas, impresión por páginas y enlace público. */
class CatalogosDeTemporadaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $variantes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->actingAs($this->admin);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        foreach (['Ring', 'Fust', 'Arc', 'Blomma', 'Danny'] as $i => $nombre) {
            $p = Producto::create(['codigo' => 'KY-LU250' . $i . '-0001', 'nombre' => $nombre, 'nombre_kyrios' => $nombre, 'categoria_id' => $cat->id,
                'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
            $v = ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-' . $i, 'codigo_kyrios' => 'KY-LU250' . $i . '-0001', 'estado' => 'activo']);
            ProductoImagen::create(['producto_id' => $p->id, 'variante_id' => $v->id, 'ruta' => 'productos/' . $p->id . '/1.png', 'orden' => 1, 'es_principal' => true]);
            ProductoPrecio::create(['producto_id' => $p->id, 'variante_id' => $v->id, 'tipo_precio' => 'venta_regular', 'precio' => 118 * ($i + 1), 'moneda' => 'PEN', 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);
            $this->variantes[$nombre] = $v;
        }
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function catalogo(array $extra = []): CatalogoLibro
    {
        return CatalogoLibro::create($extra + ['nombre' => 'Verano 2027', 'temporada' => 'Primavera–Verano', 'anio' => 2027, 'estado' => 'borrador']);
    }

    public function test_sin_catalogos_la_pantalla_invita_a_crear_el_primero(): void
    {
        $this->get(route('catalogos.index'))->assertOk()->assertSee('Todavía no hay ningún catálogo')->assertSee('Catálogos de temporada');
    }

    public function test_se_crea_un_catalogo_con_las_paginas_armadas_solas(): void
    {
        $this->post(route('catalogos.store'), ['nombre' => 'Verano 2027', 'temporada' => 'Primavera–Verano', 'anio' => 2027, 'armar' => 1])->assertSessionHasNoErrors();

        $c = CatalogoLibro::first();
        $this->assertSame('borrador', $c->estado);
        $this->assertSame(8, strlen($c->token));
        // 5 productos sin tipo de luminaria: una portada de sección «Otros» + una página de 4 + una de 1
        $this->assertSame(['seccion', 'cuatro', 'uno'], $c->paginas->pluck('tipo')->all());
        $this->assertSame('Otros', $c->paginas->first()->titulo);
        $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->assertSee('Verano 2027')->assertSee('Contenido')->assertSee('Otros');
    }

    public function test_el_nombre_es_obligatorio_y_la_temporada_debe_ser_valida(): void
    {
        $this->post(route('catalogos.store'), ['temporada' => 'Primavera–Verano', 'anio' => 2027])->assertSessionHasErrors('nombre');
        $this->post(route('catalogos.store'), ['nombre' => 'X', 'temporada' => 'Inventada', 'anio' => 2027])->assertSessionHasErrors('temporada');
        $this->assertSame(0, CatalogoLibro::count());
    }

    public function test_el_libro_siempre_tiene_un_numero_impar_de_paginas_y_contraportada(): void
    {
        $svc = app(LibroCatalogoService::class);
        foreach ([0, 1, 2, 3] as $n) {
            $c = $this->catalogo(['nombre' => "Libro {$n}"]);
            for ($i = 0; $i < $n; $i++) {
                $svc->crearPagina($c, $i + 1, 'uno', null, [$this->variantes['Ring']->id]);
            }
            $libro = $svc->construir($c);
            $this->assertSame(1, count($libro['paginas']) % 2, "con {$n} páginas el total debe ser impar");
            $this->assertSame('indice', $libro['paginas'][0]['t']);
            $this->assertSame('contra', end($libro['paginas'])['t']);
        }
    }

    public function test_los_precios_se_muestran_sin_igv_en_soles_y_en_dolares(): void
    {
        $c = $this->catalogo();
        app(LibroCatalogoService::class)->crearPagina($c, 1, 'uno', null, [$this->variantes['Ring']->id]);   // 118 con IGV = 100 sin IGV

        $item = app(LibroCatalogoService::class)->construir($c)['paginas'][1]['items'][0];
        $this->assertSame(100.0, $item['pen']);
        $this->assertGreaterThan(0, $item['usd']);
        $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->assertSee('S/ 100.00')->assertSee('Precios sin IGV');
    }

    public function test_se_agregan_mueven_y_quitan_paginas(): void
    {
        $c = $this->catalogo();
        $this->post(route('catalogos.paginas.store', $c), ['tipo' => 'seccion', 'titulo' => 'Exterior', 'variantes' => [$this->variantes['Ring']->id]])->assertSessionHasNoErrors();
        $this->post(route('catalogos.paginas.store', $c), ['tipo' => 'dos', 'variantes' => [$this->variantes['Fust']->id, $this->variantes['Arc']->id]])->assertSessionHasNoErrors();
        $this->assertSame(['seccion', 'dos'], $c->paginas()->pluck('tipo')->all());

        $segunda = $c->paginas()->get()[1];
        $this->post(route('catalogos.paginas.mover', [$c, $segunda]), ['direccion' => 'sube']);
        $this->assertSame(['dos', 'seccion'], $c->paginas()->pluck('tipo')->all());

        $this->delete(route('catalogos.paginas.destroy', [$c, $segunda]));
        $this->assertSame(['seccion'], $c->paginas()->pluck('tipo')->all());
        $this->assertDatabaseMissing('catalogo_libro_items', ['pagina_id' => $segunda->id]);
    }

    public function test_cada_diseno_respeta_su_capacidad_y_la_seccion_pide_titulo(): void
    {
        $c = $this->catalogo();
        $dos = [$this->variantes['Ring']->id, $this->variantes['Fust']->id];
        $this->post(route('catalogos.paginas.store', $c), ['tipo' => 'uno', 'variantes' => $dos])->assertSessionHasErrors('variantes');
        $this->post(route('catalogos.paginas.store', $c), ['tipo' => 'seccion', 'variantes' => [$dos[0]]])->assertSessionHasErrors('titulo');
        $this->post(route('catalogos.paginas.store', $c), ['tipo' => 'uno'])->assertSessionHasErrors('variantes');
        $this->assertSame(0, $c->paginas()->count());
    }

    public function test_no_se_toca_la_pagina_de_otro_catalogo(): void
    {
        $a = $this->catalogo(['nombre' => 'A']);
        $b = $this->catalogo(['nombre' => 'B']);
        $p = app(LibroCatalogoService::class)->crearPagina($a, 1, 'uno', null, [$this->variantes['Ring']->id]);

        $this->delete(route('catalogos.paginas.destroy', [$b, $p]))->assertNotFound();
        $this->post(route('catalogos.paginas.mover', [$b, $p]), ['direccion' => 'baja'])->assertNotFound();
        $this->assertSame(1, $a->paginas()->count());
    }

    public function test_se_edita_duplica_archiva_y_elimina_un_catalogo(): void
    {
        $c = $this->catalogo();
        app(LibroCatalogoService::class)->crearPagina($c, 1, 'uno', null, [$this->variantes['Ring']->id]);

        $this->put(route('catalogos.update', $c), ['nombre' => 'Verano 2027 B', 'temporada' => 'Colección especial', 'anio' => 2028, 'estado' => 'publicado', 'vigencia' => '2028-03-31'])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame(['Verano 2027 B', 'Colección especial', 2028, 'publicado'], [$c->nombre, $c->temporada, $c->anio, $c->estado]);

        $this->post(route('catalogos.duplicar', $c));
        $copia = CatalogoLibro::where('id', '!=', $c->id)->first();
        $this->assertSame('borrador', $copia->estado);
        $this->assertNotSame($c->token, $copia->token);
        $this->assertSame(1, $copia->paginas()->count());

        $this->post(route('catalogos.estado', $c));
        $this->assertSame('archivado', $c->fresh()->estado);

        $this->delete(route('catalogos.destroy', $c));
        $this->assertDatabaseMissing('catalogos_libro', ['id' => $c->id]);
        $this->assertSame(1, CatalogoLibro::count());
    }

    public function test_el_buscador_de_productos_devuelve_solo_variantes_con_foto(): void
    {
        $p = Producto::create(['codigo' => 'KY-SIN', 'nombre' => 'Sin foto', 'categoria_id' => Categoria::first()->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1, 'estado' => 'activo']);
        ProductoVariante::create(['producto_id' => $p->id, 'sku' => 'KY-X', 'codigo_kyrios' => 'KY-SIN-0001', 'estado' => 'activo']);

        $json = $this->getJson(route('catalogos.productos', ['q' => 'Ring']))->assertOk()->json();
        $this->assertCount(1, $json);
        $this->assertSame('Ring', $json[0]['nombre']);
        $this->assertCount(5, $this->getJson(route('catalogos.productos'))->json(), 'el que no tiene foto no aparece');
    }

    public function test_el_pdf_se_genera_completo_o_con_las_paginas_elegidas(): void
    {
        $c = $this->catalogo();
        app(LibroCatalogoService::class)->armarAuto($c);

        foreach ([['precios' => 'usd'], ['precios' => 'pen', 'tam' => 'A5', 'formato' => 'libro', 'paginas' => [0, 2, 3]], ['paginas' => [0], 'tam' => 'Carta']] as $q) {
            $r = $this->get(route('catalogos.pdf', ['catalogo' => $c] + $q))->assertOk();
            $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        $this->get(route('catalogos.pdf', ['catalogo' => $c, 'precios' => 'euro']))->assertSessionHasErrors('precios');
    }

    // ---------------------------------------------------------------- enlace público

    public function test_el_enlace_publico_solo_abre_si_esta_activo_y_no_vencio(): void
    {
        $c = $this->catalogo();
        app(LibroCatalogoService::class)->crearPagina($c, 1, 'uno', null, [$this->variantes['Ring']->id]);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('catalogos.publico', $c->token))->assertNotFound()->assertSee('ya no está disponible');

        $c->update(['enlace_activo' => true, 'precio_enlace' => 'pen']);
        $this->get(route('catalogos.publico', $c->token))->assertOk()->assertSee('Verano 2027')->assertSee('data-precios="pen"', false)->assertSee('noindex', false);

        $c->update(['enlace_expira_at' => now()->subDay()]);
        $this->get(route('catalogos.publico', $c->token))->assertNotFound();

        $c->update(['enlace_expira_at' => now()->addDays(3)]);
        $this->get(route('catalogos.publico', $c->token))->assertOk();
        $this->get('/c/' . 'zzzzzzzz')->assertNotFound();
    }

    public function test_se_cuentan_las_visitas_y_la_recarga_no_suma_dos_veces(): void
    {
        $c = $this->catalogo(['enlace_activo' => true]);
        $this->get(route('catalogos.publico', $c->token))->assertOk();
        $this->get(route('catalogos.publico', $c->token))->assertOk();
        $this->assertSame(1, $c->vistas()->count(), 'dentro de 10 minutos es la misma visita');

        $c->vistas()->update(['created_at' => now()->subMinutes(30)]);
        $this->get(route('catalogos.publico', $c->token))->assertOk();
        $this->assertSame(['abierto' => 2, 'personas' => 1], app(LibroCatalogoService::class)->estadisticas($c));
    }

    public function test_se_configura_el_enlace_desde_la_pestana_compartir(): void
    {
        $c = $this->catalogo();
        $this->put(route('catalogos.enlace', $c), ['enlace_activo' => 1, 'dias' => '30', 'precio_enlace' => 'usd'])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertTrue($c->enlace_activo);
        $this->assertSame('usd', $c->precio_enlace);
        $this->assertTrue($c->enlace_expira_at->between(now()->addDays(29), now()->addDays(31)));

        $this->put(route('catalogos.enlace', $c), ['dias' => '', 'precio_enlace' => 'none']);
        $c->refresh();
        $this->assertFalse($c->enlace_activo);
        $this->assertNull($c->enlace_expira_at);
        $this->put(route('catalogos.enlace', $c), ['dias' => '15', 'precio_enlace' => 'none'])->assertSessionHasErrors('dias');
    }

    // ---------------------------------------------------------------- permisos

    public function test_logistica_ve_imprime_y_comparte_pero_no_edita(): void
    {
        $c = $this->catalogo();
        $this->actingAs($this->usuario('Logística'));

        $this->get(route('catalogos.index', ['c' => $c->id]))->assertOk()->assertSee('Solo Administración y Marketing pueden editar')->assertDontSee('Guardar cambios');
        $this->get(route('catalogos.pdf', $c))->assertOk();
        $this->post(route('catalogos.store'), ['nombre' => 'No', 'temporada' => 'Colección especial', 'anio' => 2027])->assertForbidden();
        $this->put(route('catalogos.enlace', $c), ['precio_enlace' => 'usd'])->assertForbidden();
        $this->delete(route('catalogos.destroy', $c))->assertForbidden();
        $this->assertSame(1, CatalogoLibro::count());
    }

    public function test_marketing_edita_y_operaciones_no_entra(): void
    {
        $this->actingAs($this->usuario('Marketing'));
        $this->post(route('catalogos.store'), ['nombre' => 'Mkt', 'temporada' => 'Colección especial', 'anio' => 2027])->assertSessionHasNoErrors();
        $this->assertSame(1, CatalogoLibro::count());

        $this->actingAs($this->usuario('Operaciones'));
        $this->get(route('catalogos.index'))->assertForbidden();
        $this->actingAs($this->usuario('Contador'));
        $this->get(route('catalogos.index'))->assertForbidden();
    }

    public function test_el_menu_lateral_lleva_a_los_catalogos(): void
    {
        foreach (['Administración', 'Marketing', 'Logística'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get(route('catalogos.index'))->assertOk()->assertSee(route('catalogos.index'), false);
        }
    }
}
