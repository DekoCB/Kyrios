<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avisos internos (campanita) y alerta de stock mínimo.
 */
class AvisosStockMinimoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private User $contador;
    private Almacen $almacen;
    private ProductoVariante $variante;

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->contador = $this->usuario('Contador');
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'deposito', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $producto->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001', 'stock_minimo' => 5, 'estado' => 'activo']);
    }

    private function stock(int $cantidad): void
    {
        StockAlmacen::fila($this->variante->producto_id, $this->almacen->id, $this->variante->id)->update(['cantidad' => $cantidad]);
    }

    public function test_avisa_a_administracion_y_logistica_pero_no_al_contador(): void
    {
        $this->stock(3);

        $this->artisan('alertas:stock-minimo')->assertSuccessful();

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
        $this->assertSame(0, $this->contador->unreadNotifications()->count());
        $this->assertStringContainsString('quedan 3 (mínimo 5)', $this->admin->unreadNotifications()->first()->data['mensaje']);
    }

    public function test_no_repite_el_aviso_mientras_siga_sin_leer(): void
    {
        $this->stock(3);

        $this->artisan('alertas:stock-minimo');
        $this->artisan('alertas:stock-minimo');

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
    }

    public function test_avisa_de_nuevo_si_el_anterior_ya_se_leyo_y_sigue_bajo(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');
        $this->admin->unreadNotifications->markAsRead();

        $this->artisan('alertas:stock-minimo');

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(2, $this->admin->notifications()->count());
    }

    public function test_si_se_repone_el_stock_el_aviso_pendiente_se_retira(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');

        $this->stock(50);
        $this->artisan('alertas:stock-minimo');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_minimo_cero_no_genera_aviso_y_suma_todos_los_almacenes(): void
    {
        $this->variante->update(['stock_minimo' => 0]);
        $this->stock(0);
        $this->artisan('alertas:stock-minimo');
        $this->assertSame(0, $this->admin->notifications()->count());

        $this->variante->update(['stock_minimo' => 5]);
        $otro = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->stock(3);
        StockAlmacen::fila($this->variante->producto_id, $otro->id, $this->variante->id)->update(['cantidad' => 4]);
        $this->artisan('alertas:stock-minimo');
        $this->assertSame(0, $this->admin->notifications()->count(), '3 + 4 = 7 supera el mínimo de 5');
    }

    public function test_una_variante_inactiva_no_genera_aviso(): void
    {
        $this->stock(1);
        $this->variante->update(['estado' => 'inactivo']);

        $this->artisan('alertas:stock-minimo');

        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_pantalla_de_avisos_abrir_y_marcar_leidos(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');
        $this->actingAs($this->admin);

        $this->get(route('notificaciones.index'))->assertOk()->assertSee('Stock mínimo alcanzado')->assertSee('1 sin leer');

        $id = $this->admin->notifications()->first()->id;
        $this->get(route('notificaciones.abrir', $id))->assertRedirect(route('inventario.productos.show', $this->variante->producto_id));
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_un_usuario_no_abre_los_avisos_de_otro(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');
        $id = $this->admin->notifications()->first()->id;

        $this->actingAs($this->logistica)->get(route('notificaciones.abrir', $id))->assertNotFound();
    }

    public function test_leer_todas(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');
        $this->actingAs($this->admin)->post(route('notificaciones.leer-todas'))->assertRedirect(route('notificaciones.index'));

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_el_menu_muestra_el_contador_de_avisos(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');

        $this->actingAs($this->admin)->get(route('notificaciones.index'))->assertSee('Avisos (1 sin leer)', false);
    }

    public function test_la_revision_esta_programada_cada_dia(): void
    {
        $eventos = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'alertas:stock-minimo'));

        $this->assertCount(1, $eventos);
    }

    public function test_el_enlace_del_aviso_es_relativo_y_funciona_con_cualquier_dominio(): void
    {
        $this->stock(3);
        $this->artisan('alertas:stock-minimo');
        $aviso = $this->admin->notifications()->first();

        $this->assertStringStartsWith('/inventario/productos/', $aviso->data['url']);

        // Un aviso con enlace externo nunca saca al usuario del sistema
        $aviso->forceFill(['data' => array_merge($aviso->data, ['url' => 'https://ejemplo.com/x'])])->save();
        $this->actingAs($this->admin)->get(route('notificaciones.abrir', $aviso->id))->assertRedirect(route('notificaciones.index'));
    }
}
