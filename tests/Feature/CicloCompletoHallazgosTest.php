<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Services\SunatComprobanteService;
use App\Services\VentaService;
use App\Models\Role;
use App\Models\User;
use App\Services\SucursalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgos del recorrido completo (compra, traslado, venta, devolución, cierre de caja).
 */
class CicloCompletoHallazgosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administrador'])->id, 'estado' => 'activo']);
    }

    public function test_el_nombre_de_la_tienda_no_repite_el_prefijo(): void
    {
        $this->actingAs($this->admin());
        $svc = app(SucursalService::class);

        $s1 = $svc->crear(['nombre' => 'Tienda Miraflores', 'tipo' => 'tienda', 'estado' => 'activo']);
        $s2 = $svc->crear(['nombre' => 'San Isidro', 'tipo' => 'tienda', 'estado' => 'activo']);
        $s3 = $svc->crear(['nombre' => 'Almacén Callao', 'tipo' => 'almacen', 'estado' => 'activo']);

        $this->assertSame('Tienda Miraflores', Almacen::find($s1->almacen_id)->nombre);
        $this->assertSame('Tienda San Isidro', Almacen::find($s2->almacen_id)->nombre);
        $this->assertSame('Almacén Callao', Almacen::find($s3->almacen_id)->nombre);
    }

    public function test_el_punto_de_venta_avisa_cuando_no_hay_tienda(): void
    {
        $this->actingAs($this->admin());
        Almacen::query()->delete();

        $this->get(route('ventas.create'))->assertOk()->assertSee('No hay una tienda activa para vender');
    }

    public function test_los_botones_de_caja_no_usan_bg_opacity_de_tailwind_3(): void
    {
        $encontrados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if ($archivo->isFile() && preg_match('/(bg|text|border)-opacity-\d+/', file_get_contents($archivo->getPathname()))) {
                $encontrados[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }

        $this->assertSame([], $encontrados, 'Tailwind 4 ya no tiene bg-opacity-*: usar bg-white/20');
    }

    public function test_un_comprobante_sin_serie_no_se_envia_a_sunat_con_un_numero_inventado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Empresa::create(['ruc' => '20000000001', 'razon_social' => 'PRUEBAS SAC', 'direccion' => 'AV. X 1', 'sunat_modo' => 'beta']);
        $almacen = Almacen::create(['codigo' => 'ALM-S', 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-S', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $prod = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        StockAlmacen::fila($prod->id, $almacen->id, null)->update(['cantidad' => 10]);
        Caja::create(['user_id' => $admin->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $cliente = Cliente::create(['tipo_documento' => 'RUC', 'numero_documento' => '20511879621', 'nombre' => 'GDK S.A.C.', 'estado' => 'activo']);

        // Sin sucursal no hay serie que asignar: la venta queda sin número de comprobante
        $venta = app(VentaService::class)->crearVenta([
            'user_id' => $admin->id, 'cliente_id' => $cliente->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'factura', 'subtotal' => 0, 'igv' => 0, 'total' => 0, 'estado_pago' => 'pagado',
            'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $prod->id, 'variante_id' => null, 'cantidad' => 1, 'precio_unitario' => 118.0, 'incluye_igv' => true]]);

        $r = app(SunatComprobanteService::class)->enviar($venta);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('serie ni correlativo', $r['message']);
        $this->assertNull($venta->fresh()->sunat_enviado_at, 'no debe haberse enviado nada');
    }

    public function test_una_guia_con_numero_de_respaldo_no_se_envia_a_sunat(): void
    {
        $this->actingAs($this->admin());
        Empresa::create(['ruc' => '20000000001', 'razon_social' => 'PRUEBAS SAC', 'direccion' => 'AV. X 1', 'sunat_modo' => 'beta']);
        $guia = new \App\Models\GuiaRemision(['numero_guia' => 'GR-00001']);
        $guia->exists = true;
        $guia->id = 1;

        $r = app(\App\Services\SunatGuiaService::class)->enviar($guia);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('formato que exige SUNAT', $r['message']);
    }
}
