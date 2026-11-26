<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rol Ventas (el vendedor): registra cotizaciones y ventas pendientes de cobro, ve solo lo suyo y no toca costos,
 * compras, caja ni configuración. Administración confirma el cobro.
 */
class RolVentasTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;
    private Sucursal $sucursal;
    private User $admin;
    private User $vendedor;
    private Producto $producto;
    private ProductoVariante $variante;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->sucursal = Sucursal::create(['codigo' => 'SUC-A', 'nombre' => 'Sucursal A', 'tipo' => 'tienda', 'almacen_id' => $this->almacen->id, 'estado' => 'activo']);
        $this->almacen->update(['sucursal_id' => $this->sucursal->id]);
        $this->admin = $this->usuario('Administración');
        $this->vendedor = $this->usuario('Ventas');

        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-1', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'estado' => 'activo']);
        app(EscanerService::class)->recibir($this->almacen->id, [['variante_id' => $this->variante->id, 'cantidad' => 50]], $this->admin->id);
        $this->cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombre' => 'Juan Perez', 'estado' => 'activo']);
        app(CajaService::class)->abrirCaja($this->admin->id, $this->almacen->id, 100);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo', 'almacen_id' => $this->almacen->id]);
    }

    private function datosVenta(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id, 'tipo_comprobante' => 'nota_venta', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2, 'precio_unitario' => 118, 'incluye_igv' => true]],
        ], $extra);
    }

    public function test_el_rol_existe_en_la_lista_de_roles_y_aterriza_en_nueva_venta(): void
    {
        $this->assertContains('Ventas', Role::TODOS);
        $this->assertContains('Ventas', Role::INTERNOS);
        $this->assertArrayHasKey('Ventas', Role::DESCRIPCIONES);

        $this->actingAs($this->vendedor)->get('/dashboard')->assertRedirect(route('ventas.create'));
    }

    /** @dataProvider rutas */
    public function test_matriz_de_permisos_de_ventas(string $ruta, bool $permitido): void
    {
        $status = $this->actingAs($this->vendedor)->get($ruta)->getStatusCode();

        $permitido
            ? $this->assertNotSame(403, $status, "Ventas debería poder abrir {$ruta}")
            : $this->assertSame(403, $status, "Ventas no debería poder abrir {$ruta}");
    }

    public static function rutas(): array
    {
        $filas = [];
        $reglas = [
            '/ventas/create' => true, '/ventas' => true, '/cotizaciones' => true, '/cotizaciones/create' => true, '/clientes' => true,
            '/clientes/create' => true, '/inventario/productos' => true, '/inventario/productos/consulta-tienda' => true, '/catalogos' => true,
            '/tareas' => true, '/rrhh/mi-asistencia' => true, '/notificaciones' => true,
            '/caja' => false, '/compras' => false, '/proveedores' => false, '/precios' => false, '/precios/costos' => false, '/facturacion' => false,
            '/reportes/ventas' => false, '/users' => false, '/admin/empresa' => false, '/comisiones' => false, '/ventas/auditoria' => false,
            '/clientes/exportar' => false, '/clientes/opciones' => false, '/clientes/duplicados' => false, '/cuentas-por-cobrar' => false,
            '/inventario/recepcion' => false, '/pedidos' => false, '/marketing' => false, '/cajero/cola' => false, '/finanzas' => false,
        ];
        foreach ($reglas as $ruta => $permitido) {
            $filas[$ruta] = [$ruta, $permitido];
        }

        return $filas;
    }

    public function test_la_ficha_del_producto_con_costos_no_esta_para_ventas(): void
    {
        $this->actingAs($this->vendedor)->get(route('inventario.productos.show', $this->producto))->assertForbidden();
    }

    public function test_ventas_no_cobra_su_venta_queda_pendiente_de_cobro_aunque_envie_contado(): void
    {
        $caja = Caja::count();

        $this->actingAs($this->vendedor)->post(route('ventas.store'), $this->datosVenta())->assertSessionHasNoErrors();

        $venta = Venta::latest('id')->first();
        $this->assertSame($this->vendedor->id, $venta->user_id);
        $this->assertSame('pendiente', $venta->estado_pago);
        $this->assertSame($caja, Caja::count());
    }

    public function test_ventas_no_puede_registrar_una_venta_a_credito(): void
    {
        $this->actingAs($this->vendedor)->post(route('ventas.store'), $this->datosVenta(['condicion_pago' => 'credito', 'credito' => ['numero_cuotas' => 2, 'dias_entre_cuotas' => 30]]));

        // Sea que el formulario lo rechace o que la venta quede pendiente de cobro, Ventas nunca abre una cuenta por cobrar
        $this->assertSame(0, \App\Models\CuentaPorCobrar::count());
        Venta::all()->each(fn ($v) => $this->assertSame('pendiente', $v->estado_pago));
    }

    public function test_el_formulario_de_venta_de_ventas_esta_en_modo_vendedor(): void
    {
        $this->actingAs($this->vendedor)->get(route('ventas.create'))->assertOk()->assertSee('esVendedor: true', false);
        $this->actingAs($this->admin)->get(route('ventas.create'))->assertOk()->assertSee('esVendedor: false', false);
    }

    public function test_ventas_solo_ve_sus_ventas_en_el_listado_y_en_el_detalle(): void
    {
        $this->actingAs($this->vendedor)->post(route('ventas.store'), $this->datosVenta())->assertSessionHasNoErrors();
        $propia = Venta::latest('id')->first();
        $this->actingAs($this->admin)->post(route('ventas.store'), $this->datosVenta())->assertSessionHasNoErrors();
        $ajena = Venta::latest('id')->first();
        $this->assertNotSame($propia->id, $ajena->id);

        $this->actingAs($this->vendedor)->get(route('ventas.index'))->assertOk()->assertSee($propia->codigo)->assertDontSee($ajena->codigo);
        $this->actingAs($this->vendedor)->get(route('ventas.show', $propia))->assertOk();
        $this->actingAs($this->vendedor)->get(route('ventas.show', $ajena))->assertForbidden();
        $this->actingAs($this->vendedor)->get(route('ventas.pdf', $ajena))->assertForbidden();
    }

    public function test_ventas_no_puede_confirmar_cobros_ni_anular_ventas(): void
    {
        $this->actingAs($this->vendedor)->post(route('ventas.store'), $this->datosVenta())->assertSessionHasNoErrors();
        $venta = Venta::latest('id')->first();

        $this->actingAs($this->vendedor)->post(route('ventas.confirmar-pago', $venta), ['metodo_pago' => 'efectivo'])->assertForbidden();
        $this->actingAs($this->vendedor)->post(route('ventas.anular', $venta))->assertForbidden();
        $this->actingAs($this->vendedor)->delete(route('ventas.destroy', $venta))->assertForbidden();
        $this->actingAs($this->vendedor)->get(route('ventas.edit', $venta))->assertForbidden();
    }

    public function test_ventas_solo_ve_sus_cotizaciones(): void
    {
        $propia = $this->cotizacion($this->vendedor);
        $ajena = $this->cotizacion($this->admin);

        $this->actingAs($this->vendedor)->get(route('cotizaciones.index'))->assertOk()->assertSee($propia->codigo)->assertDontSee($ajena->codigo);
        $this->actingAs($this->vendedor)->get(route('cotizaciones.show', $propia))->assertOk();
        $this->actingAs($this->vendedor)->get(route('cotizaciones.show', $ajena))->assertForbidden();
        $this->actingAs($this->vendedor)->get(route('cotizaciones.pdf', $ajena))->assertForbidden();
        $this->actingAs($this->vendedor)->get(route('cotizaciones.edit', $ajena))->assertForbidden();
    }

    private function cotizacion(User $user): Cotizacion
    {
        return Cotizacion::create(['codigo' => 'COT-' . $user->id . '-' . random_int(1000, 9999), 'cliente_id' => $this->cliente->id, 'almacen_id' => $this->almacen->id,
            'user_id' => $user->id, 'fecha' => today(), 'fecha_vencimiento' => today()->addDays(15), 'validez_dias' => 15, 'estado' => 'pendiente',
            'subtotal' => 100, 'igv' => 18, 'total' => 118]);
    }

    public function test_ventas_no_exporta_ni_borra_clientes_pero_si_los_crea(): void
    {
        $this->actingAs($this->vendedor)->post(route('clientes.store'), ['tipo_documento' => 'DNI', 'numero_documento' => '87654321', 'nombre' => 'Ana Torres', 'estado' => 'activo'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['numero_documento' => '87654321']);

        $this->actingAs($this->vendedor)->delete(route('clientes.destroy', $this->cliente))->assertForbidden();
        $this->actingAs($this->vendedor)->get(route('clientes.exportar'))->assertForbidden();
    }

    public function test_el_menu_de_ventas_solo_tiene_sus_accesos(): void
    {
        $menu = collect(\App\Support\MenuLateral::para('Ventas', $this->vendedor->id))->where('tipo', '!=', 'titulo');
        $rutas = $menu->flatMap(fn ($m) => $m['tipo'] === 'grupo' ? collect($m['hijos'])->pluck('ruta') : [$m['ruta']])->all();

        $this->assertContains('ventas.create', $rutas);
        $this->assertContains('ventas.index', $rutas);
        $this->assertContains('cotizaciones.index', $rutas);
        $this->assertNotContains('compras.index', $rutas);
        $this->assertNotContains('precios.index', $rutas);
        foreach ($rutas as $ruta) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($ruta), "El menú apunta a una ruta que no existe: {$ruta}");
        }
    }

    public function test_la_migracion_crea_el_rol_y_se_puede_deshacer_si_no_tiene_usuarios(): void
    {
        $migracion = require database_path('migrations/2026_11_26_100000_agrega_rol_ventas.php');
        $this->vendedor->delete();
        Role::where('nombre', 'Ventas')->delete();

        $migracion->up();
        $migracion->up(); // idempotente
        $this->assertSame(1, Role::where('nombre', 'Ventas')->count());

        $migracion->down();
        $this->assertSame(0, Role::where('nombre', 'Ventas')->count());
    }
}
