<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Role;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Venta;
use App\Models\GuiaRemision;
use App\Services\VentaService;

/**
 * Reportado: "en la guía de remisión transporte público está jalando los
 * datos privado" — una guía marcada modalidad=publico terminaba guardando
 * conductor_dni/nombre/licencia/placa_vehiculo (datos de transporte
 * privado), y viceversa. Causa: GuiaRemisionService::crear() ya descartaba
 * el bloque que no corresponde a la modalidad, pero VentaService
 * (crear/editar) y DevolucionController armaban su GuiaRemision::create()/
 * update() directo, sin pasar por ese saneo — así que cualquier dato
 * precargado de la modalidad contraria (último conductor, transportista en
 * localStorage, valor que quedó en un input oculto) se guardaba igual.
 */
class GuiaRemisionModalidadTest extends TestCase
{
    private Role    $roleAdmin;
    private User    $admin;
    private Almacen $almacen;
    private Cliente $cliente;
    private Categoria $categoria;
    private Producto $producto;

    private array $createdVentas = [];
    private array $createdUsers  = [];
    private array $createdProductos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::firstOrCreate(['nombre' => 'Administrador']);

        $uid = uniqid();

        $this->admin = User::create([
            'name'     => 'Admin Guia Test',
            'email'    => "admin_guia_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $this->roleAdmin->id,
        ]);
        $this->createdUsers[] = $this->admin->id;

        $this->almacen = Almacen::create([
            'nombre' => "Almacén Guia Test {$uid}",
            'codigo' => "ALMG-{$uid}",
            'tipo'   => 'principal',
            'estado' => 'activo',
        ]);
        $this->admin->update(['almacen_id' => $this->almacen->id]);

        $this->cliente = Cliente::create([
            'tipo_documento'   => 'DNI',
            'numero_documento' => (string) rand(10000000, 99999999),
            'nombre'           => "Cliente Guia {$uid}",
            'estado'           => 'activo',
        ]);

        $this->categoria = Categoria::firstOrCreate(
            ['codigo' => 'GUIA-TEST'],
            ['nombre' => 'Guia Test', 'estado' => 'activo']
        );

        $this->producto = Producto::create([
            'codigo'          => "PG-{$uid}",
            'nombre'          => "Producto Guia {$uid}",
            'categoria_id'    => $this->categoria->id,
            'tipo_inventario' => 'cantidad',
            'stock_actual'    => 100,
            'stock_minimo'    => 1,
            'stock_maximo'    => 200,
            'estado'          => 'activo',
        ]);
        $this->createdProductos[] = $this->producto->id;
        StockAlmacen::create([
            'producto_id' => $this->producto->id,
            'almacen_id'  => $this->almacen->id,
            'cantidad'    => 100,
        ]);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($this->createdVentas)) {
            GuiaRemision::whereIn('venta_id', $this->createdVentas)->delete();
            \App\Models\DetalleVenta::whereIn('venta_id', $this->createdVentas)->delete();
            Venta::whereIn('id', $this->createdVentas)->delete();
        }
        StockAlmacen::where('almacen_id', $this->almacen->id)->delete();
        if (!empty($this->createdProductos)) {
            Producto::whereIn('id', $this->createdProductos)->delete();
        }
        \App\Models\MovimientoInventario::where('almacen_id', $this->almacen->id)->delete();
        $this->almacen->delete();
        $this->cliente->delete();
        User::whereIn('id', $this->createdUsers)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        parent::tearDown();
    }

    private function crearVentaSinPago(): Venta
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pendiente',
            'condicion_pago'   => 'contado',
        ], [[
            'producto_id'     => $this->producto->id,
            'variante_id'     => null,
            'cantidad'        => 1,
            'precio_unitario' => 50.0,
            'incluye_igv'     => true,
        ]]);
        $this->createdVentas[] = $venta->id;
        return $venta;
    }

    public function test_crear_venta_con_guia_publico_descarta_datos_de_conductor_precargados(): void
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pendiente',
            'condicion_pago'   => 'contado',
            'guia_data'        => [
                'modalidad'              => 'publico',
                'fecha_traslado'         => now()->toDateString(),
                'transportista_tipo_doc' => 'RUC',
                'transportista_doc'      => '20123456789',
                'transportista_nombre'   => 'Transportes SA',
                // Precargado en el form desde el "último conductor" — no debería guardarse
                'conductor_dni'          => '12345678',
                'conductor_nombre'       => 'Juan Pérez',
                'conductor_licencia'     => 'Q12345678',
                'placa_vehiculo'         => 'ABC-123',
            ],
        ], [[
            'producto_id'     => $this->producto->id,
            'variante_id'     => null,
            'cantidad'        => 1,
            'precio_unitario' => 50.0,
            'incluye_igv'     => true,
        ]]);
        $this->createdVentas[] = $venta->id;

        $guia = GuiaRemision::where('venta_id', $venta->id)->first();
        $this->assertNotNull($guia);
        $this->assertSame('publico', $guia->modalidad);
        $this->assertSame('Transportes SA', $guia->transportista_nombre);
        $this->assertNull($guia->conductor_dni);
        $this->assertNull($guia->conductor_nombre);
        $this->assertNull($guia->conductor_licencia);
        $this->assertNull($guia->placa_vehiculo);
    }

    public function test_crear_venta_con_guia_privado_descarta_datos_de_transportista_precargados(): void
    {
        $venta = app(VentaService::class)->crearVenta([
            'user_id'          => $this->admin->id,
            'cliente_id'       => $this->cliente->id,
            'almacen_id'       => $this->almacen->id,
            'fecha'            => now()->toDateString(),
            'tipo_comprobante' => 'boleta',
            'subtotal'         => 0, 'igv' => 0, 'total' => 0,
            'estado_pago'      => 'pendiente',
            'condicion_pago'   => 'contado',
            'guia_data'        => [
                'modalidad'          => 'privado',
                'fecha_traslado'     => now()->toDateString(),
                'conductor_dni'      => '12345678',
                'conductor_nombre'   => 'Juan Pérez',
                'conductor_licencia' => 'Q12345678',
                'placa_vehiculo'     => 'ABC-123',
                // Precargado desde localStorage — no debería guardarse en modo privado
                'transportista_tipo_doc' => 'RUC',
                'transportista_doc'      => '20123456789',
                'transportista_nombre'   => 'Transportes SA',
            ],
        ], [[
            'producto_id'     => $this->producto->id,
            'variante_id'     => null,
            'cantidad'        => 1,
            'precio_unitario' => 50.0,
            'incluye_igv'     => true,
        ]]);
        $this->createdVentas[] = $venta->id;

        $guia = GuiaRemision::where('venta_id', $venta->id)->first();
        $this->assertNotNull($guia);
        $this->assertSame('Juan Pérez', $guia->conductor_nombre);
        $this->assertNull($guia->transportista_doc);
        $this->assertNull($guia->transportista_nombre);
    }

    public function test_editar_venta_cambiando_modalidad_a_publico_limpia_conductor_viejo(): void
    {
        $venta = $this->crearVentaSinPago();
        GuiaRemision::create([
            'venta_id'           => $venta->id,
            'modalidad'          => 'privado',
            'fecha_traslado'     => now()->toDateString(),
            'conductor_dni'      => '12345678',
            'conductor_nombre'   => 'Juan Pérez',
            'conductor_licencia' => 'Q12345678',
            'placa_vehiculo'     => 'ABC-123',
        ]);

        app(VentaService::class)->editarVenta($venta, [
            'guia' => [
                'modalidad'            => 'publico',
                'transportista_nombre' => 'Transportes SA',
                'transportista_doc'    => '20123456789',
            ],
        ]);

        $guia = $venta->guiaRemision()->first();
        $this->assertSame('publico', $guia->modalidad);
        $this->assertSame('Transportes SA', $guia->transportista_nombre);
        $this->assertNull($guia->conductor_dni, 'El conductor de la guía privada anterior debe quedar limpio.');
        $this->assertNull($guia->conductor_nombre);
        $this->assertNull($guia->conductor_licencia);
        $this->assertNull($guia->placa_vehiculo);
    }

    public function test_devolucion_con_guia_publico_no_guarda_datos_de_conductor(): void
    {
        $venta = $this->crearVentaSinPago();

        $response = $this->post(route('devoluciones.store'), [
            'cliente_id' => $this->cliente->id,
            'almacen_id' => $this->almacen->id,
            'cantidades' => [$venta->detalles->first()->id => 1],
            'guia' => [
                'modalidad'          => 'publico',
                'fecha_traslado'     => now()->toDateString(),
                'direccion_partida'  => 'Av. Test 123',
                'direccion_llegada'  => 'Av. Test 456',
                // Campos que este form sí expone (conductor/placa) y que en
                // modo público no deberían guardarse.
                'conductor_dni'      => '12345678',
                'conductor_nombre'   => 'Juan Pérez',
                'conductor_licencia' => 'Q12345678',
                'placa_vehiculo'     => 'ABC-123',
            ],
        ]);

        $response->assertRedirect(route('devoluciones.index'));

        $guia = GuiaRemision::where('motivo_traslado', 'DEVOLUCION')
            ->where('direccion_partida', 'Av. Test 123')
            ->latest('id')
            ->first();

        $this->assertNotNull($guia);
        $this->assertSame('publico', $guia->modalidad);
        $this->assertNull($guia->conductor_dni);
        $this->assertNull($guia->conductor_nombre);
        $this->assertNull($guia->conductor_licencia);
        $this->assertNull($guia->placa_vehiculo);
    }
}
