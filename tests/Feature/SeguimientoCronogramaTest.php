<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CronogramaEvento;
use App\Models\EtapaSeguimiento;
use App\Models\Pedido;
use App\Models\PedidoEtapa;
use App\Models\PedidoInterno;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seguimiento por etapas de las órdenes de compra, cronograma de instalaciones y entregas, y sus avisos.
 */
class SeguimientoCronogramaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $logistica;
    private User $operaciones;
    private Almacen $deposito;
    private Almacen $tienda;
    private Producto $producto;
    private ProductoVariante $variante;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administración');
        $this->logistica = $this->usuario('Logística');
        $this->operaciones = $this->usuario('Operaciones');
        $this->deposito = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Depósito', 'tipo' => 'principal', 'estado' => 'activo']);
        $this->tienda = Almacen::create(['codigo' => 'ALM-B', 'nombre' => 'Showroom', 'tipo' => 'tienda', 'estado' => 'activo']);
        $cat = Categoria::create(['codigo' => 'CAT-LU', 'nombre' => 'Luminaria', 'estado' => 'activo']);
        $this->producto = Producto::create(['codigo' => 'KY-LU2501-0001', 'nombre' => 'Ring', 'categoria_id' => $cat->id,
            'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 100, 'estado' => 'activo']);
        $this->variante = ProductoVariante::create(['producto_id' => $this->producto->id, 'sku' => 'KY-LU2501-0001', 'codigo_kyrios' => 'KY-LU2501-0001', 'estado' => 'activo']);
        $this->proveedor = Proveedor::create(['ruc' => '20555666771', 'razon_social' => 'Prov SAC', 'estado' => 'activo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => $rol])->id, 'estado' => 'activo']);
    }

    private function orden(string $tipo = 'internacional'): Pedido
    {
        $this->actingAs($this->admin)->post(route('pedidos.store'), [
            'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->deposito->id, 'fecha' => now()->toDateString(),
            'tipo_compra' => $tipo, 'moneda' => $tipo === 'internacional' ? 'USD' : 'PEN',
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 5, 'precio_referencial' => 10]],
        ])->assertSessionHasNoErrors();

        return Pedido::latest('id')->firstOrFail();
    }

    private function etapa(Pedido $o, string $nombre): PedidoEtapa
    {
        return $o->etapas()->where('nombre', $nombre)->firstOrFail();
    }

    private function conFecha(Pedido $o, string $nombre, string $fecha): PedidoEtapa
    {
        $e = $this->etapa($o, $nombre);
        $e->update(['fecha_prevista' => $fecha]);

        return $e;
    }

    // ── Etapas de la orden ───────────────────────────────────────────────────

    public function test_una_orden_internacional_recibe_las_etapas_de_importacion(): void
    {
        $nombres = $this->orden('internacional')->etapas->pluck('nombre')->all();

        $this->assertContains('En tránsito', $nombres);
        $this->assertContains('En aduana', $nombres);
        $this->assertContains('Llegada a Lima', $nombres);
        $this->assertNotContains('Confirmada por el proveedor', $nombres);
        $this->assertSame('Orden emitida', $nombres[0]);
    }

    public function test_una_orden_nacional_recibe_otras_etapas(): void
    {
        $nombres = $this->orden('nacional')->etapas->pluck('nombre')->all();

        $this->assertContains('Confirmada por el proveedor', $nombres);
        $this->assertNotContains('En aduana', $nombres);
    }

    public function test_una_orden_anterior_al_seguimiento_recibe_sus_etapas_al_abrirla(): void
    {
        $o = Pedido::create(['proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->deposito->id, 'user_id' => $this->admin->id, 'fecha' => today(), 'estado' => 'aprobado']);
        $this->assertSame(0, $o->etapas()->count());

        $this->actingAs($this->admin)->get(route('pedidos.show', $o))->assertOk()->assertSee('Seguimiento de la orden');

        $this->assertGreaterThan(0, $o->etapas()->count());
    }

    public function test_se_guardan_fechas_y_notas_y_se_marca_el_cumplimiento(): void
    {
        $o = $this->orden();
        $transito = $this->etapa($o, 'En tránsito');
        $fecha = today()->addDays(10)->toDateString();

        $this->actingAs($this->logistica)->put(route('pedidos.seguimiento.guardar', $o), ['etapas' => [$transito->id => ['fecha_prevista' => $fecha, 'notas' => 'Sale de Shanghái']]])
            ->assertRedirect(route('pedidos.show', $o));
        $transito->refresh();
        $this->assertSame($fecha, $transito->fecha_prevista->toDateString());
        $this->assertSame('Sale de Shanghái', $transito->notas);
        $this->assertSame(10, $transito->dias_restantes);

        $this->post(route('pedidos.seguimiento.completar', [$o, $transito]))->assertSessionHas('success');
        $transito->refresh();
        $this->assertTrue($transito->completada);
        $this->assertSame($this->logistica->id, $transito->completada_por);
        $this->assertNull($transito->dias_restantes, 'una etapa cumplida ya no cuenta días');

        $this->post(route('pedidos.seguimiento.reabrir', [$o, $transito]));
        $this->assertFalse($transito->fresh()->completada);
    }

    public function test_una_etapa_de_otra_orden_no_se_toca(): void
    {
        $a = $this->orden();
        $b = $this->orden();

        $this->post(route('pedidos.seguimiento.completar', [$a, $this->etapa($b, 'En tránsito')]))->assertNotFound();
    }

    public function test_la_etapa_actual_es_la_primera_sin_cumplir(): void
    {
        $o = $this->orden();
        $this->post(route('pedidos.seguimiento.completar', [$o, $this->etapa($o, 'Orden emitida')]));
        $this->post(route('pedidos.seguimiento.completar', [$o, $this->etapa($o, 'En producción')]));

        $this->assertSame('Embarcada', $o->fresh()->etapa_actual->nombre);
        $this->get(route('pedidos.index'))->assertOk()->assertSee('Embarcada');
        $this->get(route('pedidos.show', $o))->assertOk()->assertSee('Va en:')->assertSee('Embarcada');
    }

    public function test_solo_se_aceptan_fechas_validas_y_cumplimiento_no_futuro(): void
    {
        $o = $this->orden();
        $e = $this->etapa($o, 'En tránsito');

        $this->actingAs($this->admin)->put(route('pedidos.seguimiento.guardar', $o), ['etapas' => [$e->id => ['fecha_prevista' => 'no-es-fecha']]])->assertSessionHasErrors('etapas.*.fecha_prevista');
        $this->post(route('pedidos.seguimiento.completar', [$o, $e]), ['fecha_real' => today()->addDay()->toDateString()])->assertSessionHasErrors('fecha_real');
        $this->assertFalse($e->fresh()->completada);
    }

    public function test_operaciones_no_toca_el_seguimiento(): void
    {
        $o = $this->orden();

        $this->actingAs($this->operaciones)->post(route('pedidos.seguimiento.completar', [$o, $this->etapa($o, 'En tránsito')]))->assertForbidden();
    }

    // ── Catálogo de etapas editable ──────────────────────────────────────────

    public function test_el_catalogo_solo_lo_edita_administracion(): void
    {
        $this->actingAs($this->logistica)->get(route('seguimiento.etapas.index'))->assertForbidden();
        $this->post(route('seguimiento.etapas.store'), ['nombre' => 'X', 'tipo_compra' => 'ambos', 'orden' => 1])->assertForbidden();

        $this->actingAs($this->admin)->get(route('seguimiento.etapas.index'))->assertOk()->assertSee('En aduana');
    }

    public function test_una_etapa_nueva_aplica_a_las_ordenes_nuevas_y_no_a_las_ya_iniciadas(): void
    {
        $vieja = $this->orden();

        $this->actingAs($this->admin)->post(route('seguimiento.etapas.store'), ['nombre' => 'Inspección de calidad', 'tipo_compra' => 'internacional', 'orden' => 55])->assertSessionHasNoErrors();
        $nueva = $this->orden();

        $this->assertContains('Inspección de calidad', $nueva->etapas->pluck('nombre')->all());
        $this->assertNotContains('Inspección de calidad', $vieja->fresh()->etapas->pluck('nombre')->all());
        $this->assertSame('Inspección de calidad', $nueva->etapas->firstWhere('orden', 55)->nombre, 'respeta el orden indicado');
    }

    public function test_renombrar_o_eliminar_una_etapa_no_cambia_las_ordenes_ya_iniciadas(): void
    {
        $o = $this->orden();
        $catalogo = EtapaSeguimiento::where('nombre', 'En aduana')->firstOrFail();

        $this->actingAs($this->admin)->put(route('seguimiento.etapas.update', $catalogo), ['nombre' => 'Nacionalización', 'tipo_compra' => 'internacional', 'orden' => $catalogo->orden, 'activo' => 1]);
        $this->assertSame('En aduana', $this->etapa($o, 'En aduana')->nombre);

        $this->delete(route('seguimiento.etapas.destroy', $catalogo))->assertSessionHas('success');
        $this->assertNull(EtapaSeguimiento::find($catalogo->id));
        $this->assertSame('En aduana', $o->etapas()->where('orden', $catalogo->orden)->first()->nombre);
    }

    public function test_una_etapa_desactivada_no_se_copia_a_las_ordenes_nuevas(): void
    {
        $catalogo = EtapaSeguimiento::where('nombre', 'En aduana')->firstOrFail();
        $this->actingAs($this->admin)->put(route('seguimiento.etapas.update', $catalogo), ['nombre' => 'En aduana', 'tipo_compra' => 'internacional', 'orden' => $catalogo->orden]);

        $this->assertFalse($catalogo->fresh()->activo, 'sin marcar «Activa» queda desactivada');
        $this->assertNotContains('En aduana', $this->orden()->etapas->pluck('nombre')->all());
    }

    public function test_el_catalogo_valida_sus_datos(): void
    {
        $this->actingAs($this->admin)->post(route('seguimiento.etapas.store'), ['nombre' => '', 'tipo_compra' => 'raro', 'orden' => -1])
            ->assertSessionHasErrors(['nombre', 'tipo_compra', 'orden']);
    }

    // ── Avisos por fechas: etapas ────────────────────────────────────────────

    public function test_avisa_de_una_etapa_que_se_acerca_y_de_una_atrasada_pero_no_de_una_lejana(): void
    {
        $o = $this->orden();
        $this->conFecha($o, 'En tránsito', today()->addDays(2)->toDateString());
        $this->conFecha($o, 'En aduana', today()->subDays(3)->toDateString());
        $this->conFecha($o, 'Llegada a Lima', today()->addDays(20)->toDateString());

        $this->artisan('alertas:fechas')->assertSuccessful();

        $mensajes = $this->admin->unreadNotifications->pluck('data.mensaje')->implode(' | ');
        $this->assertStringContainsString("{$o->codigo} · «En tránsito»: faltan 2 días", $mensajes);
        $this->assertStringContainsString("«En aduana»: venció hace 3 días", $mensajes);
        $this->assertStringNotContainsString('Llegada a Lima', $mensajes);
        $this->assertSame(2, $this->logistica->unreadNotifications()->count());
        $this->assertSame(0, $this->operaciones->unreadNotifications()->count());
    }

    public function test_no_repite_el_aviso_del_dia_aunque_ya_se_haya_leido(): void
    {
        $o = $this->orden();
        $this->conFecha($o, 'En tránsito', today()->addDays(1)->toDateString());

        $this->artisan('alertas:fechas');
        $this->admin->unreadNotifications->markAsRead();
        $this->artisan('alertas:fechas');
        $this->artisan('alertas:fechas');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_al_dia_siguiente_el_aviso_se_renueva_con_los_dias_que_faltan(): void
    {
        Carbon::setTestNow('2026-11-10 08:00:00');
        $o = $this->orden();
        $this->conFecha($o, 'En tránsito', '2026-11-13');
        $this->artisan('alertas:fechas');
        $this->assertStringContainsString('faltan 3 días', $this->admin->unreadNotifications()->first()->data['mensaje']);

        Carbon::setTestNow('2026-11-11 08:00:00');
        $this->artisan('alertas:fechas');

        $sinLeer = $this->admin->unreadNotifications()->get();
        $this->assertCount(1, $sinLeer, 'el aviso de ayer, sin leer, se reemplaza por el de hoy');
        $this->assertStringContainsString('faltan 2 días', $sinLeer->first()->data['mensaje']);
    }

    public function test_cumplir_la_etapa_retira_su_aviso_pendiente(): void
    {
        $o = $this->orden();
        $e = $this->conFecha($o, 'En tránsito', today()->addDay()->toDateString());
        $this->artisan('alertas:fechas');
        $this->assertSame(1, $this->admin->unreadNotifications()->count());

        $this->actingAs($this->admin)->post(route('pedidos.seguimiento.completar', [$o, $e]));
        $this->artisan('alertas:fechas');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_una_orden_cancelada_o_recibida_ya_no_avisa(): void
    {
        $o = $this->orden();
        $this->conFecha($o, 'En tránsito', today()->subDay()->toDateString());
        $o->update(['estado' => 'cancelado']);

        $this->artisan('alertas:fechas');

        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_el_aviso_de_una_etapa_lleva_a_la_orden(): void
    {
        $o = $this->orden();
        $this->conFecha($o, 'En tránsito', today()->toDateString());
        $this->artisan('alertas:fechas');

        $aviso = $this->admin->unreadNotifications()->first();
        $this->assertSame("/pedidos/{$o->id}", $aviso->data['url']);
        $this->assertStringContainsString('es hoy', $aviso->data['mensaje']);
    }

    // ── Cronograma ───────────────────────────────────────────────────────────

    private function evento(array $extra = []): CronogramaEvento
    {
        $this->actingAs($this->logistica)->post(route('cronograma.store'), array_merge([
            'tipo' => 'instalacion', 'titulo' => 'Instalación Torre Lima', 'fecha' => today()->addDays(5)->toDateString(),
            'hora' => '09:30', 'responsable_id' => $this->operaciones->id, 'direccion' => 'Av. Lima 123',
        ], $extra))->assertSessionHasNoErrors();

        return CronogramaEvento::latest('id')->firstOrFail();
    }

    public function test_logistica_programa_y_el_responsable_recibe_el_aviso_de_asignacion(): void
    {
        $e = $this->evento();

        $this->assertSame('programado', $e->estado);
        $this->assertSame($this->logistica->id, $e->creado_por);
        $this->assertSame('09:30', $e->hora_corta);
        $aviso = $this->operaciones->unreadNotifications()->firstOrFail();
        $this->assertSame('Tienes una instalación asignada', $aviso->data['titulo']);
        $this->assertStringContainsString('09:30', $aviso->data['mensaje']);
        $this->assertSame(0, $this->logistica->unreadNotifications()->count(), 'quien programa no se avisa a sí mismo');
    }

    public function test_la_programacion_se_valida(): void
    {
        $this->actingAs($this->logistica)->post(route('cronograma.store'), ['tipo' => 'otro', 'titulo' => 'x', 'fecha' => 'ayer', 'hora' => '25:99'])
            ->assertSessionHasErrors(['tipo', 'titulo', 'fecha', 'hora']);
        $this->assertSame(0, CronogramaEvento::count());
    }

    public function test_operaciones_ve_solo_lo_suyo_y_no_programa(): void
    {
        $this->evento(['titulo' => 'Mía']);
        $this->evento(['titulo' => 'Ajena', 'responsable_id' => $this->usuario('Operaciones')->id]);

        // La primera lectura consume el mensaje «Programado: Ajena» que dejó la creación
        $this->actingAs($this->operaciones)->get(route('cronograma.index'));
        $this->get(route('cronograma.index', ['mes' => today()->addDays(5)->format('Y-m')]))
            ->assertOk()->assertSee('Mía')->assertDontSee('Ajena');
        $this->get(route('cronograma.create'))->assertForbidden();
        $this->post(route('cronograma.store'), ['tipo' => 'entrega', 'titulo' => 'No', 'fecha' => today()->toDateString()])->assertForbidden();
    }

    public function test_el_responsable_marca_realizado_pero_no_el_de_otro(): void
    {
        $mio = $this->evento();
        $ajeno = $this->evento(['responsable_id' => $this->usuario('Operaciones')->id]);

        $this->actingAs($this->operaciones)->post(route('cronograma.realizar', $mio))->assertSessionHas('success');
        $this->assertSame('realizado', $mio->fresh()->estado);
        $this->post(route('cronograma.realizar', $ajeno))->assertForbidden();
        $this->post(route('cronograma.realizar', $mio))->assertSessionHas('error');
        $this->post(route('cronograma.cancelar', $ajeno))->assertForbidden();
    }

    public function test_cambiar_el_responsable_avisa_al_nuevo(): void
    {
        $e = $this->evento();
        $otro = $this->usuario('Operaciones');

        $this->actingAs($this->admin)->put(route('cronograma.update', $e), ['tipo' => 'instalacion', 'titulo' => $e->titulo, 'fecha' => $e->fecha->toDateString(), 'responsable_id' => $otro->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($otro->id, $e->fresh()->responsable_id);
        $this->assertSame(1, $otro->unreadNotifications()->count());
    }

    public function test_cancelar_un_evento_lo_saca_de_los_avisos(): void
    {
        $e = $this->evento(['fecha' => today()->addDay()->toDateString()]);
        $this->actingAs($this->admin)->post(route('cronograma.cancelar', $e))->assertSessionHas('success');

        $this->artisan('alertas:fechas');

        $this->assertSame(0, $this->logistica->notifications()->count());
        $this->assertSame('cancelado', $e->fresh()->estado);
    }

    public function test_cliente_y_contador_no_acceden_al_cronograma(): void
    {
        $this->actingAs($this->usuario('Cliente'))->get(route('cronograma.index'))->assertForbidden();
        $this->actingAs($this->usuario('Contador'))->get(route('cronograma.index'))->assertForbidden();
    }

    public function test_el_cronograma_muestra_el_mes_con_el_cliente_y_tolera_un_mes_invalido(): void
    {
        $cliente = Cliente::create(['nombre' => 'Estudio Rocha', 'estado' => 'activo']);
        $e = $this->evento(['cliente_id' => $cliente->id, 'titulo' => 'Entrega showroom', 'tipo' => 'entrega']);
        $mes = $e->fecha->format('Y-m');

        $this->actingAs($this->admin)->get(route('cronograma.index', ['mes' => $mes]))->assertOk()
            ->assertSee('Entrega showroom')->assertSee('Estudio Rocha')->assertSee('Av. Lima 123');
        $this->get(route('cronograma.index', ['mes' => 'basura']))->assertOk();
        $this->get(route('cronograma.index', ['mes' => $e->fecha->copy()->addMonths(2)->format('Y-m')]))->assertOk()->assertSee('No hay instalaciones ni entregas');
        $this->get(route('cronograma.index', ['mes' => $mes, 'tipo' => 'instalacion']))->assertOk()->assertDontSee('Entrega showroom');
    }

    public function test_el_formulario_de_edicion_conserva_hora_y_responsable(): void
    {
        $e = $this->evento();

        $this->actingAs($this->admin)->get(route('cronograma.edit', $e))->assertOk()->assertSee('value="09:30"', false)->assertSee($this->operaciones->name);
    }

    // ── Avisos por fechas: cronograma y pedidos internos ─────────────────────

    public function test_avisa_de_una_instalacion_proxima_al_responsable_y_a_administracion_y_logistica(): void
    {
        $this->evento(['fecha' => today()->addDay()->toDateString()]);
        $this->operaciones->notifications()->delete();

        $this->artisan('alertas:fechas');

        foreach ([$this->operaciones, $this->admin, $this->logistica] as $u) {
            $this->assertSame(1, $u->unreadNotifications()->count(), $u->role->nombre);
            $this->assertStringContainsString('es mañana', $u->unreadNotifications()->first()->data['mensaje']);
        }
    }

    public function test_una_instalacion_atrasada_se_marca_y_una_realizada_no_avisa(): void
    {
        $atrasada = $this->evento(['titulo' => 'Vencida']);
        $atrasada->update(['fecha' => today()->subDays(2)]);
        $hecha = $this->evento(['titulo' => 'Hecha']);
        $hecha->update(['fecha' => today()->subDay(), 'estado' => 'realizado']);

        $this->artisan('alertas:fechas');

        $mensajes = $this->admin->unreadNotifications->pluck('data.mensaje')->implode(' | ');
        $this->assertStringContainsString('Vencida: venció hace 2 días', $mensajes);
        $this->assertStringNotContainsString('Hecha', $mensajes);
        $this->actingAs($this->admin)->get(route('cronograma.index', ['mes' => today()->subDays(2)->format('Y-m')]))->assertSee('atrasado');
    }

    public function test_un_pedido_interno_por_vencer_avisa_al_solicitante_y_a_los_que_atienden(): void
    {
        $this->actingAs($this->operaciones)->post(route('pedidos-internos.store'), [
            'almacen_origen_id' => $this->deposito->id, 'almacen_destino_id' => $this->tienda->id, 'area' => 'Showroom', 'fecha_requerida' => today()->addDay()->toDateString(),
            'detalles' => [['producto_id' => $this->producto->id, 'variante_id' => $this->variante->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();
        $this->operaciones->notifications()->delete();
        $this->admin->notifications()->delete();
        $this->logistica->notifications()->delete();

        $this->artisan('alertas:fechas');

        $codigo = PedidoInterno::first()->codigo;
        foreach ([$this->operaciones, $this->admin, $this->logistica] as $u) {
            $this->assertStringContainsString("{$codigo} (Showroom): es mañana la fecha requerida", $u->unreadNotifications()->firstOrFail()->data['mensaje']);
        }
    }

    public function test_los_avisos_de_fechas_no_tocan_los_de_stock_minimo(): void
    {
        $this->variante->update(['stock_minimo' => 5]);
        $this->artisan('alertas:stock-minimo');
        $antes = $this->admin->unreadNotifications()->count();
        $this->assertGreaterThan(0, $antes);

        $this->artisan('alertas:fechas');

        $this->assertSame($antes, $this->admin->unreadNotifications()->count());
    }

    public function test_la_revision_de_fechas_esta_programada_cada_dia(): void
    {
        $eventos = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'alertas:fechas'));

        $this->assertCount(1, $eventos);
    }

    public function test_el_menu_ofrece_el_cronograma_y_las_etapas(): void
    {
        $this->actingAs($this->admin)->get(route('cronograma.index'))->assertOk()->assertSee('Cronograma')->assertSee('Etapas de seguimiento');
        $this->actingAs($this->logistica)->get(route('cronograma.index'))->assertOk()->assertSee('Cronograma')->assertDontSee('Etapas de seguimiento');
        $this->actingAs($this->operaciones)->get(route('cronograma.index'))->assertOk()->assertSee('Cronograma');
    }
}
