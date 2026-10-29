<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Role;
use App\Models\SerieComprobante;
use App\Models\StockAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una nota de crédito anula una venta: no puede sumarse como una venta más en dashboard, reportes ni fichas de cliente.
 */
class Auditoria2CNotasCreditoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $cliente;
    private Venta $venta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);

        $almacen = Almacen::create(['codigo' => 'ALM-N', 'nombre' => 'Administración', 'tipo' => 'tienda', 'estado' => 'activo']);
        $sucursal = Sucursal::create(['nombre' => 'Principal', 'codigo' => 'S001', 'tipo' => 'tienda', 'estado' => 'activo', 'almacen_id' => $almacen->id]);
        $almacen->update(['sucursal_id' => $sucursal->id]);
        foreach ([['01', 'FA01'], ['03', 'BA01'], ['07', 'FC01'], ['07', 'BC01']] as [$tipo, $serie]) {
            SerieComprobante::create(['sucursal_id' => $sucursal->id, 'tipo_comprobante' => $tipo, 'tipo_nombre' => $serie, 'serie' => $serie, 'correlativo_actual' => 1, 'formato_impresion' => 'A4', 'activo' => true]);
        }
        $cat = Categoria::create(['codigo' => 'CAT-N', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $prod = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        StockAlmacen::fila($prod->id, $almacen->id, null)->update(['cantidad' => 50]);
        $prod->update(['stock_actual' => 50]);
        Caja::create(['user_id' => $this->admin->id, 'almacen_id' => $almacen->id, 'sucursal_id' => $sucursal->id, 'fecha' => now()->toDateString(), 'fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);
        $this->cliente = Cliente::create(['tipo_documento' => 'RUC', 'numero_documento' => '20511879621', 'nombre' => 'GDK S.A.C.', 'estado' => 'activo']);

        $mk = fn (int $cantidad) => app(VentaService::class)->crearVenta([
            'user_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'almacen_id' => $almacen->id, 'sucursal_id' => $sucursal->id,
            'fecha' => now()->toDateString(), 'tipo_comprobante' => 'factura', 'subtotal' => 0, 'igv' => 0, 'total' => 0,
            'estado_pago' => 'pagado', 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
        ], [['producto_id' => $prod->id, 'variante_id' => null, 'cantidad' => $cantidad, 'precio_unitario' => 118.0, 'incluye_igv' => true]]);

        // Una venta que se mantiene (S/ 236) y otra que se anula con nota de crédito (S/ 118)
        $this->mantenida = $mk(2);
        $this->venta = $mk(1);
        app(VentaService::class)->generarNotaCredito($this->venta, '01');
    }

    private Venta $mantenida;

    public function test_la_nota_de_credito_no_suma_como_venta_en_el_dashboard(): void
    {
        $vista = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertEqualsWithDelta(236.0, (float) $vista->viewData('ventas_mes_actual'), 0.01, 'solo debe contar la venta mantenida');
        $this->assertSame(1, (int) $vista->viewData('facturas_mes'));
    }

    public function test_el_reporte_de_ventas_ignora_las_notas_de_credito(): void
    {
        $vista = $this->get(route('reportes.ventas', ['periodo' => 'hoy']))->assertOk();

        $kpis = $vista->viewData('kpis');
        $this->assertEqualsWithDelta(236.0, $kpis['total_ventas'], 0.01);
        $this->assertSame(1, $kpis['num_ventas']);
        $this->assertSame(2, (int) $kpis['unidades_vendidas']);
    }

    public function test_el_total_comprado_del_cliente_ignora_las_notas_de_credito(): void
    {
        $this->get(route('clientes.show', $this->cliente))->assertOk();
        $c = Cliente::conTotalComprado()->withCount('ventas')->find($this->cliente->id);

        $this->assertEqualsWithDelta(236.0, (float) $c->total_comprado, 0.01);

        $resumen = $this->get(route('clientes.show', $this->cliente))->viewData('resumen');
        $this->assertSame(1, $resumen['ventas']);
        $this->assertEqualsWithDelta(236.0, $resumen['total_comprado'], 0.01);
    }

    public function test_los_productos_mas_vendidos_ignoran_las_notas_de_credito(): void
    {
        $top = $this->get(route('admin.dashboard'))->viewData('top_productos');

        $this->assertEquals(2, (int) $top->first()->total_vendido);
    }
}
