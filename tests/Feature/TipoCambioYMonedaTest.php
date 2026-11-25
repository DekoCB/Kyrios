<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\Role;
use App\Models\TipoCambio;
use App\Models\User;
use App\Support\Moneda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Precios en dólares con alternancia a soles y administración del tipo de cambio. */
class TipoCambioYMonedaTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $rol): User
    {
        $u = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
        $this->actingAs($u);

        return $u;
    }

    private function producto(): Producto
    {
        $cat = Categoria::firstOrCreate(['codigo' => 'CAT-LU'], ['nombre' => 'Luminaria', 'estado' => 'activo']);

        return Producto::create(['codigo' => 'KY-LU2501-' . random_int(1000, 9999), 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
    }

    public function test_el_tipo_de_cambio_vigente_es_el_ultimo_y_hay_valor_por_defecto(): void
    {
        $this->assertSame(3.75, TipoCambio::actual());
        TipoCambio::create(['fecha' => '2026-11-10', 'compra' => 3.70, 'venta' => 3.78]);
        TipoCambio::create(['fecha' => '2026-11-12', 'compra' => 3.71, 'venta' => 3.80]);
        $this->assertSame(3.80, TipoCambio::actual());
        $this->assertSame(380.0, Moneda::aSoles(100.0, 'USD'));
        $this->assertSame(100.0, Moneda::aSoles(100.0, 'PEN'));
        $this->assertSame(50.0, Moneda::aDolares(190.0, 'PEN'));
    }

    public function test_administracion_registra_el_tipo_de_cambio_y_otros_roles_no(): void
    {
        $this->como('Administración');
        $this->post(route('tipo-cambio.store'), ['fecha' => today()->toDateString(), 'compra' => 3.72, 'venta' => 3.79])->assertSessionHasNoErrors();
        $this->assertSame(3.79, TipoCambio::actual());
        $this->get(route('tipo-cambio.index'))->assertOk()->assertSee('3.790');

        $this->post(route('tipo-cambio.store'), ['fecha' => today()->toDateString(), 'compra' => 3.9, 'venta' => 3.7])->assertSessionHasErrors('venta');

        $this->como('Contador');
        $this->get(route('tipo-cambio.index'))->assertForbidden();
    }

    public function test_sincroniza_con_sunat(): void
    {
        $this->como('Administración');
        Http::fake(['*' => Http::sequence()->push(['compra' => 3.71, 'venta' => 3.77, 'fecha' => today()->toDateString()])->push([], 500)]);
        $this->post(route('tipo-cambio.sunat'))->assertSessionHas('success');
        $this->assertSame(3.77, TipoCambio::actual());
        $this->assertSame('sunat', TipoCambio::vigente()->fuente);

        $this->post(route('tipo-cambio.sunat'))->assertSessionHas('error');
    }

    public function test_un_precio_en_dolares_se_ve_en_dolares_y_se_convierte_a_soles_en_la_venta(): void
    {
        $this->como('Administración');
        TipoCambio::create(['fecha' => today(), 'compra' => 3.7, 'venta' => 3.8]);
        $p = $this->producto();
        $precio = ProductoPrecio::create(['producto_id' => $p->id, 'tipo_precio' => 'venta_regular', 'precio' => 100, 'precio_compra' => 60, 'moneda' => 'USD', 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);

        $this->assertSame(380.0, $precio->precio_soles);

        $html = $this->get(route('precios.show', $p))->assertOk()->getContent();
        $this->assertStringContainsString('data-usd="100.00" data-pen="380.00"', $html);
        $this->assertStringContainsString('data-selector-moneda', $html);
    }

    public function test_un_precio_en_soles_no_se_convierte(): void
    {
        $p = $this->producto();
        $precio = ProductoPrecio::create(['producto_id' => $p->id, 'tipo_precio' => 'venta_regular', 'precio' => 100, 'precio_compra' => 60, 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);
        $this->assertSame('PEN', $precio->fresh()->moneda);
        $this->assertSame(100.0, $precio->precio_soles);
    }

    public function test_la_ficha_del_producto_y_las_pantallas_comerciales_muestran_dolares_con_selector(): void
    {
        $this->como('Administración');
        TipoCambio::create(['fecha' => today(), 'compra' => 3.7, 'venta' => 3.8]);
        $p = $this->producto();
        ProductoPrecio::create(['producto_id' => $p->id, 'tipo_precio' => 'venta_regular', 'precio' => 100, 'precio_compra' => 60, 'moneda' => 'USD', 'incluye_igv' => true, 'activo' => true, 'prioridad' => 1]);

        $ficha = $this->get(route('inventario.productos.show', $p))->assertOk()->getContent();
        $this->assertStringContainsString('data-precios-venta', $ficha);
        $this->assertStringContainsString('data-usd="100.00" data-pen="380.00"', $ficha);
        $this->assertStringContainsString('data-selector-moneda', $ficha);

        $this->get(route('cotizaciones.index'))->assertOk()->assertSee('data-selector-moneda', false);
        $this->get(route('cotizaciones.create'))->assertOk()->assertSee('data-selector-moneda', false)->assertSee('$store.moneda.fmt', false);
        $this->get(route('ventas.index'))->assertOk()->assertSee('data-selector-moneda', false);
        $this->get(route('ventas.create'))->assertOk()->assertSee('$store.moneda.fmt', false)->assertSee('name="tipo-cambio" content="3.8', false);
    }

    public function test_el_tipo_de_cambio_vigente_se_lee_una_sola_vez_por_peticion_y_se_actualiza_al_guardar(): void
    {
        TipoCambio::create(['fecha' => today()->subDay()->toDateString(), 'compra' => 3.40, 'venta' => 3.42]);

        \DB::enableQueryLog();
        $valores = collect(range(1, 50))->map(fn () => TipoCambio::actual())->unique();
        $consultas = collect(\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'tipos_cambio'))->count();

        $this->assertSame([3.42], $valores->values()->all());
        $this->assertSame(1, $consultas, 'mostrar 50 precios no debe hacer 50 consultas');

        TipoCambio::create(['fecha' => today()->toDateString(), 'compra' => 3.50, 'venta' => 3.52]);
        $this->assertSame(3.52, TipoCambio::actual(), 'al guardar uno nuevo se vuelve a leer');
    }
}
