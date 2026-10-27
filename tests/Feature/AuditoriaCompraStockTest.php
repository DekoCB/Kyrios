<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\CuentaPorPagar;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoCodigoBarras;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\CompraService;
use App\Services\TrasladoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regresiones de la auditoría de compras, inventario, traslados y tienda.
 */
class AuditoriaCompraStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Almacen $a;
    private Almacen $b;
    private Categoria $categoria;
    private Producto $producto;
    private ProductoVariante $v1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administrador');
        $this->actingAs($this->admin);
        $this->a = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->b = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Local B', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo']);
        $this->v1 = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001',
            'acabado_1' => 'negro(a)', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function comprar(string $forma = 'contado', int $dias = 0, int $cantidad = 10): void
    {
        $proveedor = Proveedor::first() ?? Proveedor::create(['ruc' => '20100000001', 'razon_social' => 'Proveedor', 'estado' => 'activo']);

        app(CompraService::class)->registrarCompra([
            'codigo' => 'C-' . uniqid(), 'proveedor_id' => $proveedor->id, 'user_id' => $this->admin->id, 'almacen_id' => $this->a->id,
            'numero_factura' => 'F001-' . uniqid(), 'fecha' => now()->toDateString(), 'forma_pago' => $forma, 'condicion_pago' => $dias,
            'tipo_moneda' => 'PEN', 'tipo_cambio' => 1, 'incluye_igv' => false, 'subtotal' => 100, 'igv' => 18, 'total' => 118,
            'total_pen' => 118, 'tipo_operacion' => '01', 'tipo_compra' => 'local', 'estado' => 'registrado',
        ], [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => $cantidad, 'precio_unitario' => 10, 'subtotal' => 10 * $cantidad]]);
    }

    // ── Pantallas y rutas que daban error 500 ─────────────────────────────

    public function test_la_ficha_de_categoria_carga_con_sus_productos(): void
    {
        $this->get(route('inventario.categorias.show', $this->categoria))->assertOk()->assertSee('Ring');
    }

    public function test_editar_un_almacen_muestra_los_encargados_posibles(): void
    {
        $this->get(route('inventario.almacenes.edit', $this->a))->assertOk()->assertSee($this->admin->name);
    }

    public function test_actualizar_un_almacen_guarda_el_encargado(): void
    {
        $this->put(route('inventario.almacenes.update', $this->a), [
            'nombre' => 'Local A', 'estado' => 'activo', 'encargado_id' => $this->admin->id, 'tipo' => 'deposito',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->admin->id, (int) $this->a->fresh()->encargado_id);
        $this->assertSame('deposito', $this->a->fresh()->tipo);
    }

    public function test_el_tipo_de_un_almacen_de_sucursal_no_se_cambia(): void
    {
        $sucursal = \App\Models\Sucursal::create(['codigo' => '0001', 'nombre' => 'Central', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->b->update(['sucursal_id' => $sucursal->id]);

        $this->put(route('inventario.almacenes.update', $this->b), ['nombre' => 'Local B', 'estado' => 'activo', 'tipo' => 'temporal'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tienda', $this->b->fresh()->tipo);
    }

    public function test_el_dashboard_de_finanzas_carga_y_cuenta_el_saldo_no_el_total(): void
    {
        $this->comprar('credito', 30);
        $cuenta = CuentaPorPagar::first();
        $this->assertNotNull($cuenta);
        $cuenta->update(['estado' => 'parcial', 'monto_pagado' => 100]);

        $this->get(route('finanzas.dashboard'))->assertOk()->assertSee('Por pagar')->assertSee('18.00');
    }

    public function test_las_rutas_muertas_ya_no_existen(): void
    {
        foreach (['productos.proveedores.desasociar', 'cuentas-por-pagar.programar-pago', 'cuentas-por-pagar.guardar-programacion', 'api.productos.precios', 'api.productos.stock', 'api.validar-codigo-barras'] as $nombre) {
            $this->assertFalse(Route::has($nombre) || Route::has('inventario.' . $nombre), "La ruta {$nombre} apunta a un método inexistente");
        }
        foreach (Route::getRoutes() as $r) {
            $accion = $r->getAction('controller');
            if ($accion) {
                [$clase, $metodo] = array_pad(explode('@', $accion), 2, '__invoke');
                $this->assertTrue(method_exists($clase, $metodo), "{$clase}@{$metodo} no existe ({$r->uri()})");
            }
        }
    }

    public function test_tienda_ve_la_ficha_de_un_producto_y_la_consulta(): void
    {
        $this->comprar();
        $this->actingAs($this->usuario('Tienda'));

        $this->get(route('tienda.producto.ver', $this->producto))->assertOk()->assertSee('Local A');
        $this->get(route('inventario.productos.consulta-tienda'))->assertOk();
    }

    public function test_el_reporte_de_valorizacion_prorrateada_enlaza_a_la_ficha(): void
    {
        $this->comprar();

        $this->get(route('inventario.reportes.valorizacion-prorateada'))->assertOk();
    }

    // ── Modelos con columnas inexistentes ─────────────────────────────────

    public function test_crear_una_categoria_con_descripcion_no_falla(): void
    {
        $this->post(route('inventario.categorias.store'), ['nombre' => 'Apliques', 'descripcion' => 'Luces de pared', 'estado' => 'activo'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categorias', ['nombre' => 'Apliques']);
    }

    public function test_un_precio_se_crea_sin_columnas_inexistentes(): void
    {
        $precio = ProductoPrecio::create(['producto_id' => $this->producto->id, 'tipo_precio' => 'venta_regular', 'precio' => 50,
            'moneda' => 'PEN', 'cantidad_minima' => 2, 'activo' => true, 'prioridad' => 0, 'creado_por' => $this->admin->id]);

        $this->assertNotNull($precio->id);
    }

    // ── API de búsqueda por código ────────────────────────────────────────

    public function test_la_api_busca_por_codigo_y_devuelve_el_stock_de_la_variante(): void
    {
        $this->comprar('contado', 0, 7);
        ProductoPrecio::create(['producto_id' => $this->producto->id, 'tipo_precio' => 'venta_regular', 'precio' => 50,
            'activo' => true, 'prioridad' => 0, 'creado_por' => $this->admin->id]);

        $r = $this->getJson(route('api.productos.buscar', ['codigo' => 'KY-LU2501-0001', 'almacen_id' => $this->a->id]))->assertOk();

        $this->assertSame(7, $r->json('producto.stock_actual'));
        $this->assertEquals(50, $r->json('producto.precio_venta'));
        $this->assertSame($this->v1->id, $r->json('producto.variante_id'));
    }

    public function test_la_api_encuentra_por_codigo_de_barras_y_responde_404_si_no_existe(): void
    {
        ProductoCodigoBarras::create(['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'codigo_barras' => '7750000000017', 'es_principal' => true]);

        $this->getJson(route('api.productos.buscar', ['codigo' => '7750000000017']))->assertOk()->assertJsonPath('producto.id', $this->producto->id);
        $this->getJson(route('api.productos.buscar', ['codigo' => 'NO-EXISTE']))->assertStatus(404);
    }

    // ── Stock de variante sincronizado en traslados ───────────────────────

    public function test_un_traslado_mantiene_sincronizado_el_stock_de_la_variante(): void
    {
        $this->comprar('contado', 0, 10);
        $servicio = app(TrasladoService::class);

        $guia = $servicio->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 3]]]);

        $this->assertSame(7, (int) $this->producto->fresh()->stock_actual, 'el producto descuenta la reserva');
        $this->assertSame(7, (int) $this->v1->fresh()->stock_actual, 'la variante debe coincidir con el producto');

        $mov = MovimientoInventario::where('numero_guia', $guia)->first();
        $servicio->anularTraslado($mov->id, $this->admin->id);

        $this->assertSame(10, (int) $this->producto->fresh()->stock_actual);
        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual);

        $guia = $servicio->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 4]]]);
        $servicio->confirmarRecepcion(MovimientoInventario::where('numero_guia', $guia)->first()->id, $this->admin->id);

        $this->assertSame(10, (int) $this->producto->fresh()->stock_actual);
        $this->assertSame(10, (int) $this->v1->fresh()->stock_actual);
        $this->assertSame(4, StockAlmacen::cantidadEn($this->producto->id, $this->b->id, $this->v1->id));
    }

    // ── Edición de compras ────────────────────────────────────────────────

    public function test_bajar_la_cantidad_de_una_compra_no_deja_el_stock_en_negativo(): void
    {
        $this->comprar('contado', 0, 10);
        app(TrasladoService::class)->crearTraslado(['almacen_id' => $this->a->id, 'almacen_destino_id' => $this->b->id, 'user_id' => $this->admin->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'cantidad' => 8]]]);
        $compra = \App\Models\Compra::first();
        $detalle = $compra->detalles->first();

        $this->put(route('compras.update', $compra), [
            'proveedor_id' => $compra->proveedor_id, 'numero_factura' => $compra->numero_factura, 'almacen_id' => $compra->almacen_id,
            'fecha' => now()->toDateString(), 'forma_pago' => 'contado',
            'detalles' => [['id' => $detalle->id, 'cantidad' => 5, 'precio_unitario' => 10]],
        ])->assertSessionHas('error');

        $this->assertSame(2, StockAlmacen::cantidadEn($this->producto->id, $this->a->id, $this->v1->id), 'el stock no se toca');
        $this->assertSame(10, (int) $detalle->fresh()->cantidad);
    }

    public function test_no_se_cambia_el_almacen_de_una_compra_ya_ingresada(): void
    {
        $this->comprar('contado', 0, 10);
        $compra = \App\Models\Compra::first();
        $detalle = $compra->detalles->first();

        $this->put(route('compras.update', $compra), [
            'proveedor_id' => $compra->proveedor_id, 'numero_factura' => $compra->numero_factura, 'almacen_id' => $this->b->id,
            'fecha' => now()->toDateString(), 'forma_pago' => 'contado',
            'detalles' => [['id' => $detalle->id, 'cantidad' => 10, 'precio_unitario' => 10]],
        ])->assertSessionHas('error');

        $this->assertSame($this->a->id, (int) $compra->fresh()->almacen_id);
        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->a->id, $this->v1->id));
    }

    public function test_subir_la_cantidad_de_una_compra_suma_stock_y_deja_la_variante_cuadrada(): void
    {
        $this->comprar('contado', 0, 10);
        $compra = \App\Models\Compra::first();
        $detalle = $compra->detalles->first();

        $this->put(route('compras.update', $compra), [
            'proveedor_id' => $compra->proveedor_id, 'numero_factura' => $compra->numero_factura, 'almacen_id' => $compra->almacen_id,
            'fecha' => now()->toDateString(), 'forma_pago' => 'contado',
            'detalles' => [['id' => $detalle->id, 'cantidad' => 12, 'precio_unitario' => 10]],
        ])->assertSessionHas('success');

        $this->assertSame(12, StockAlmacen::cantidadEn($this->producto->id, $this->a->id, $this->v1->id));
        $this->assertSame(12, (int) $this->v1->fresh()->stock_actual);
        $this->assertSame(12, (int) $this->producto->fresh()->stock_actual);
    }

    // ── Eliminar productos ────────────────────────────────────────────────

    public function test_un_producto_usado_en_un_pedido_se_desactiva_en_vez_de_dar_error(): void
    {
        $proveedor = Proveedor::create(['ruc' => '20100000002', 'razon_social' => 'Proveedor 2', 'estado' => 'activo']);
        $pedido = \App\Models\Pedido::create(['codigo' => 'PED-1', 'proveedor_id' => $proveedor->id, 'almacen_id' => $this->a->id,
            'user_id' => $this->admin->id, 'fecha' => now()->toDateString(), 'estado' => 'pendiente']);
        \App\Models\DetallePedido::create(['pedido_id' => $pedido->id, 'producto_id' => $this->producto->id, 'cantidad' => 3]);

        $this->delete(route('inventario.productos.destroy', $this->producto))->assertRedirect();

        $this->assertSame('inactivo', $this->producto->fresh()->estado);
        $this->assertDatabaseHas('productos', ['id' => $this->producto->id]);
    }

    public function test_un_producto_sin_ningun_uso_se_elimina(): void
    {
        $otro = Producto::create(['codigo' => 'KY-LU2501-0099', 'nombre' => 'Sin uso', 'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 10, 'estado' => 'activo']);

        $this->delete(route('inventario.productos.destroy', $otro))->assertRedirect();

        $this->assertDatabaseMissing('productos', ['id' => $otro->id]);
    }

    public function test_una_categoria_con_productos_no_pierde_su_imagen_al_fallar_el_borrado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('categorias/luz.png', 'x');
        $this->categoria->update(['imagen' => 'categorias/luz.png']);

        $this->delete(route('inventario.categorias.destroy', $this->categoria))->assertSessionHas('error');

        $this->assertDatabaseHas('categorias', ['id' => $this->categoria->id]);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('categorias/luz.png');
    }

    public function test_un_usuario_tienda_sin_almacen_no_rompe_el_inventario_ni_las_solicitudes(): void
    {
        $tienda = $this->usuario('Tienda');
        $tienda->update(['almacen_id' => null]);
        $this->actingAs($tienda);

        $this->get(route('tienda.inventario.ver'))->assertRedirect(route('tienda.dashboard'))->assertSessionHas('error');
        $this->get(route('tienda.inventario.solicitudes'))->assertRedirect(route('tienda.dashboard'));
        $this->postJson(route('tienda.inventario.solicitar-traslado'), [
            'producto_id' => $this->producto->id, 'variante_id' => $this->v1->id, 'almacen_origen_id' => $this->a->id, 'cantidad' => 1,
        ])->assertStatus(422);
    }
}
