<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Aprobacion;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\StockAlmacen;
use App\Models\User;
use App\Services\EscanerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lo que registra Logística (productos, ingresos, compras y traslados) espera la aprobación de Administración.
 */
class AprobacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private Almacen $deposito;
    private Almacen $tienda;
    private Categoria $categoria;
    private UnidadMedida $unidad;
    private Producto $producto;
    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->deposito = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->tienda = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->categoria = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->unidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'categoria' => 'unidad', 'permite_decimales' => false]);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $this->categoria->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function datosProducto(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Lámpara nueva', 'categoria_id' => $this->categoria->id, 'unidad_medida_id' => $this->unidad->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo',
            'variante_inicial' => ['acabado_1' => 'negro(a)'],
        ], $extra);
    }

    private function stockDeposito(int $n): void
    {
        app(EscanerService::class)->recibir($this->deposito->id, [['variante_id' => $this->variante->id, 'cantidad' => $n]], $this->admin->id);
    }

    private function aprobar(Aprobacion $a)
    {
        return $this->actingAs($this->admin)->post(route('aprobaciones.aprobar', $a));
    }

    private function ultima(): Aprobacion
    {
        return Aprobacion::latest('id')->firstOrFail();
    }

    // ── Producto nuevo ───────────────────────────────────────────────────────

    public function test_administracion_crea_productos_sin_pedir_aprobacion(): void
    {
        $this->actingAs($this->admin)->post(route('inventario.productos.store'), $this->datosProducto())->assertSessionHasNoErrors();

        $this->assertSame(1, Producto::where('nombre', 'Lámpara nueva')->count());
        $this->assertSame(0, Aprobacion::count());
    }

    public function test_un_producto_de_logistica_queda_pendiente_y_no_aparece_en_el_inventario(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto())
            ->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame(0, Producto::where('nombre', 'Lámpara nueva')->count());
        $a = $this->ultima();
        $this->assertSame('pendiente', $a->estado);
        $this->assertSame($this->logistica->id, $a->solicitante_id);
        $this->assertSame('Producto nuevo: Lámpara nueva', $a->titulo);
    }

    public function test_el_administrador_recibe_el_aviso_y_la_solicitante_no(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertStringContainsString($this->logistica->name, $this->admin->unreadNotifications()->first()->data['mensaje']);
        $this->assertSame(0, $this->logistica->unreadNotifications()->count());
    }

    public function test_al_aprobar_se_crea_el_producto_a_nombre_de_quien_lo_registro(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());
        $a = $this->ultima();

        $this->aprobar($a)->assertRedirect(route('aprobaciones.index'))->assertSessionHas('success');

        $p = Producto::where('nombre', 'Lámpara nueva')->firstOrFail();
        $this->assertSame($this->logistica->id, (int) $p->creado_por, 'el producto queda a nombre de quien lo registró, no del aprobador');
        $a->refresh();
        $this->assertSame('aprobada', $a->estado);
        $this->assertSame($this->admin->id, $a->resuelta_por);
        $this->assertSame(1, $this->logistica->unreadNotifications()->count());
        $this->assertSame('Solicitud aprobada', $this->logistica->unreadNotifications()->first()->data['titulo']);
    }

    public function test_el_aprobador_sigue_siendo_el_usuario_activo_despues_de_aprobar(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());

        $this->aprobar($this->ultima());

        $this->assertAuthenticatedAs($this->admin);
        $this->get(route('aprobaciones.index'))->assertOk();
    }

    public function test_rechazar_exige_motivo_y_no_crea_nada(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());
        $a = $this->ultima();

        $this->actingAs($this->admin)->post(route('aprobaciones.rechazar', $a), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertSame('pendiente', $a->fresh()->estado);

        $this->post(route('aprobaciones.rechazar', $a), ['motivo' => 'Ya existe en el catálogo'])->assertRedirect(route('aprobaciones.index'));

        $this->assertSame('rechazada', $a->fresh()->estado);
        $this->assertSame(0, Producto::where('nombre', 'Lámpara nueva')->count());
        $this->assertStringContainsString('Ya existe en el catálogo', $this->logistica->unreadNotifications()->first()->data['mensaje']);
    }

    public function test_una_solicitud_resuelta_no_se_puede_aprobar_dos_veces(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());
        $a = $this->ultima();

        $this->aprobar($a);
        $this->aprobar($a)->assertSessionHas('error');

        $this->assertSame(1, Producto::where('nombre', 'Lámpara nueva')->count());
    }

    public function test_la_imagen_subida_se_conserva_hasta_la_aprobacion(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto(['imagen' => UploadedFile::fake()->image('foto.png', 40, 40)]));
        $a = $this->ultima();
        $this->assertNotEmpty($a->archivos);
        Storage::disk('local')->assertExists($a->archivos[0]['ruta']);

        $this->aprobar($a);

        $p = Producto::where('nombre', 'Lámpara nueva')->firstOrFail();
        $this->assertNotEmpty($p->imagen);
        Storage::disk('public')->assertExists($p->imagen);
        Storage::disk('local')->assertMissing($a->archivos[0]['ruta']);
    }

    // ── Edición y eliminación ────────────────────────────────────────────────

    public function test_editar_un_producto_muestra_el_antes_y_el_despues_y_aplica_al_aprobar(): void
    {
        $this->actingAs($this->logistica)->put(route('inventario.productos.update', $this->producto), $this->datosProducto(['nombre' => 'Ring renombrado']))
            ->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame('Ring', $this->producto->fresh()->nombre, 'nada cambia hasta aprobar');
        $a = $this->ultima();
        $this->assertContains('Nombre: Ring → Ring renombrado', $a->detalle);

        $this->aprobar($a);

        $this->assertSame('Ring renombrado', $this->producto->fresh()->nombre);
    }

    public function test_eliminar_un_producto_tambien_pasa_por_aprobacion(): void
    {
        $this->actingAs($this->logistica)->delete(route('inventario.productos.destroy', $this->producto))->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame('activo', $this->producto->fresh()->estado);
        $this->assertSame('Eliminar producto: Ring', $this->ultima()->titulo);

        $this->aprobar($this->ultima());

        $this->assertNull(Producto::find($this->producto->id), 'sin movimientos, el producto se elimina al aprobar');
    }

    public function test_editar_una_variante_pasa_por_aprobacion(): void
    {
        $this->actingAs($this->logistica)->put(route('inventario.productos.variantes.update', $this->variante), ['acabado_1' => 'cromo', 'stock_minimo' => 4])
            ->assertRedirect(route('aprobaciones.mias'));

        $this->assertNull($this->variante->fresh()->acabado_1);

        $this->aprobar($this->ultima());

        $this->assertSame('cromo', $this->variante->fresh()->acabado_1);
        $this->assertSame(4, $this->variante->fresh()->stock_minimo);
    }

    // ── Ingresos ─────────────────────────────────────────────────────────────

    public function test_el_ingreso_con_escaner_de_logistica_espera_aprobacion(): void
    {
        $r = $this->actingAs($this->logistica)->postJson(route('inventario.recepcion.confirmar'), [
            'almacen_id' => $this->deposito->id, 'lineas' => [['variante_id' => $this->variante->id, 'cantidad' => 7]],
        ]);

        $r->assertStatus(202)->assertJson(['ok' => true, 'pendiente' => true]);
        $this->assertContains('Ring (KY-LU2501-0001) × 7', $this->ultima()->detalle, 'el escáner solo envía la variante: igual se muestra el producto');
        $this->assertSame(0, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));

        $this->aprobar($this->ultima());

        $this->assertSame(7, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
        $this->assertSame($this->logistica->id, (int) MovimientoInventario::latest('id')->first()->user_id, 'el movimiento queda a nombre de quien lo registró');
    }

    public function test_el_ingreso_con_escaner_del_administrador_es_inmediato(): void
    {
        $this->actingAs($this->admin)->postJson(route('inventario.recepcion.confirmar'), [
            'almacen_id' => $this->deposito->id, 'lineas' => [['variante_id' => $this->variante->id, 'cantidad' => 7]],
        ])->assertOk();

        $this->assertSame(7, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
        $this->assertSame(0, Aprobacion::count());
    }

    public function test_solo_los_movimientos_de_ingreso_y_transferencia_piden_aprobacion(): void
    {
        $this->stockDeposito(10);
        $motivoIngreso = MotivoMovimiento::create(['nombre' => 'Compra', 'codigo' => 'COMPRA', 'tipo' => 'ingreso', 'estado' => 'activo']);
        $motivoSalida = MotivoMovimiento::create(['nombre' => 'Consumo', 'codigo' => 'CONSUMO', 'tipo' => 'salida', 'estado' => 'activo']);

        $this->actingAs($this->logistica)->post(route('inventario.movimientos.store'), [
            'producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'almacen_id' => $this->deposito->id,
            'tipo_movimiento' => 'ingreso', 'cantidad' => 5, 'motivo_movimiento_id' => $motivoIngreso->id,
        ]);
        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id), 'el ingreso espera');
        $this->assertSame(1, Aprobacion::count());

        $this->post(route('inventario.movimientos.store'), [
            'producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'almacen_id' => $this->deposito->id,
            'tipo_movimiento' => 'salida', 'cantidad' => 2, 'motivo_movimiento_id' => $motivoSalida->id,
        ]);
        $this->assertSame(8, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id), 'la salida no se frena');
        $this->assertSame(1, Aprobacion::count());

        $this->aprobar(Aprobacion::first());
        $this->assertSame(13, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
    }

    public function test_una_compra_de_logistica_espera_aprobacion_y_se_registra_al_aprobar(): void
    {
        $prov = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Prov SAC', 'estado' => 'activo']);

        $this->actingAs($this->logistica)->post(route('compras.store'), [
            'proveedor_id' => $prov->id, 'almacen_id' => $this->deposito->id, 'numero_factura' => 'F001-77', 'fecha' => now()->toDateString(),
            'forma_pago' => 'contado', 'tipo_moneda' => 'PEN', 'tipo_operacion' => '01', 'tipo_compra' => 'local',
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 3, 'precio_unitario' => 10]],
        ])->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame(0, Compra::count());
        $this->assertStringContainsString('F001-77', $this->ultima()->titulo);
        $this->assertContains('Ring (KY-LU2501-0001) × 3 a 10', $this->ultima()->detalle);

        $this->aprobar($this->ultima());

        $this->assertSame(1, Compra::count());
        $this->assertSame($this->logistica->id, (int) Compra::first()->user_id);
        $this->assertSame(3, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
    }

    // ── Traslados ────────────────────────────────────────────────────────────

    public function test_un_traslado_de_logistica_espera_aprobacion(): void
    {
        $this->stockDeposito(10);

        $this->actingAs($this->logistica)->post(route('traslados.store'), [
            'almacen_id' => $this->deposito->id, 'almacen_destino_id' => $this->tienda->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 4]],
        ])->assertRedirect(route('aprobaciones.mias'));

        $this->assertSame(10, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id), 'el stock no se toca');
        $this->assertSame('Traslado Depósito → Tienda', $this->ultima()->titulo);

        $this->aprobar($this->ultima());

        $this->assertSame(6, StockAlmacen::cantidadEn($this->producto->id, $this->deposito->id, $this->variante->id));
    }

    // ── Cuando al aprobar ya no se puede ─────────────────────────────────────

    public function test_si_al_aprobar_falla_queda_pendiente_con_el_motivo_y_sin_efectos(): void
    {
        $this->stockDeposito(2);
        $this->actingAs($this->logistica)->post(route('traslados.store'), [
            'almacen_id' => $this->deposito->id, 'almacen_destino_id' => $this->tienda->id,
            'productos' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2]],
        ]);
        $a = $this->ultima();

        // Mientras esperaba, salió el stock
        StockAlmacen::fila($this->producto->id, $this->deposito->id, $this->variante->id)->update(['cantidad' => 0]);

        $this->aprobar($a)->assertSessionHas('error');

        $a->refresh();
        $this->assertSame('pendiente', $a->estado);
        $this->assertNotEmpty($a->ultimo_error);
        $this->assertSame(0, MovimientoInventario::where('tipo_movimiento', 'transferencia')->count());
        $this->assertSame(0, $this->logistica->unreadNotifications()->count(), 'no se avisa de una aprobación que no se aplicó');

        $this->get(route('aprobaciones.index'))->assertOk()->assertSee('El último intento de aprobar falló');
    }

    // ── Quién ve qué ─────────────────────────────────────────────────────────

    public function test_solo_administracion_ve_la_bandeja_y_resuelve(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());
        $a = $this->ultima();

        $this->get(route('aprobaciones.index'))->assertForbidden();
        $this->post(route('aprobaciones.aprobar', $a))->assertForbidden();
        $this->post(route('aprobaciones.rechazar', $a), ['motivo' => 'no'])->assertForbidden();
        $this->actingAs($this->usuario('Contador'))->get(route('aprobaciones.index'))->assertForbidden();
        $this->actingAs($this->usuario('Cliente'))->get(route('aprobaciones.mias'))->assertForbidden();

        $this->assertSame('pendiente', $a->fresh()->estado);
    }

    public function test_cada_usuario_ve_solo_sus_solicitudes_en_mis_solicitudes(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto(['nombre' => 'De Logística']));
        $otra = $this->usuario('Logística');
        $this->actingAs($otra)->post(route('inventario.productos.store'), $this->datosProducto(['nombre' => 'De la otra']));

        $this->actingAs($this->logistica)->get(route('aprobaciones.mias'))->assertOk()->assertSee('De Logística')->assertDontSee('De la otra');
    }

    public function test_la_bandeja_muestra_quien_registro_cada_cosa_y_filtra_por_estado(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());
        $this->actingAs($this->admin);

        $this->get(route('aprobaciones.index'))->assertOk()->assertSee('Registrado por')->assertSee($this->logistica->name)->assertSee('Producto nuevo: Lámpara nueva');
        $this->get(route('aprobaciones.index', ['estado' => 'aprobada']))->assertOk()->assertDontSee('Producto nuevo: Lámpara nueva');
    }

    public function test_el_producto_muestra_quien_lo_registro(): void
    {
        $this->producto->update(['creado_por' => $this->logistica->id]);

        $this->actingAs($this->admin)->get(route('inventario.productos.show', $this->producto))->assertOk()->assertSee('Registrado por')->assertSee($this->logistica->name);
    }

    public function test_el_menu_muestra_aprobaciones_al_administrador_y_mis_solicitudes_a_logistica(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());

        $this->actingAs($this->admin)->get(route('aprobaciones.index'))->assertSee('Aprobaciones');
        $this->actingAs($this->logistica)->get(route('aprobaciones.mias'))->assertSee('Mis solicitudes');
    }

    public function test_el_aviso_lleva_a_la_bandeja(): void
    {
        $this->actingAs($this->logistica)->post(route('inventario.productos.store'), $this->datosProducto());

        $this->assertSame('/aprobaciones', $this->admin->unreadNotifications()->first()->data['url']);
    }
}
