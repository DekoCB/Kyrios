<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ClienteProyecto;
use App\Models\ClienteSeguimiento;
use App\Models\OpcionUbicacion;
use App\Models\Role;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Quién emitió la nota de pedido, pisos y ambientes administrables, «Cola de Cobros» y la consolidación de clientes con el Excel. */
class CambiosClientesYCotizacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Administración'])->id, 'estado' => 'activo']);
        $this->actingAs($this->admin);
    }

    // ---------------------------------------------------------------- pisos y ambientes

    public function test_las_opciones_de_fabrica_se_cargan_con_la_migracion(): void
    {
        $this->assertContains('Primer nivel', OpcionUbicacion::nombres('piso'));
        $this->assertContains('Cocina', OpcionUbicacion::nombres('ambiente'));
    }

    public function test_se_agregan_editan_ocultan_y_eliminan_opciones(): void
    {
        $this->post(route('cotizaciones.ubicaciones.store'), ['tipo' => 'piso', 'nombre' => 'Mezanine'])->assertSessionHasNoErrors();
        $this->assertContains('Mezanine', OpcionUbicacion::nombres('piso'));

        $op = OpcionUbicacion::where('nombre', 'Mezanine')->first();
        $this->put(route('cotizaciones.ubicaciones.update', $op), ['nombre' => 'Mezanine alto', 'orden' => 99, 'activo' => 1]);
        $this->assertContains('Mezanine alto', OpcionUbicacion::nombres('piso'));

        $this->put(route('cotizaciones.ubicaciones.update', $op), ['nombre' => 'Mezanine alto', 'orden' => 99]); // sin «visible»
        $this->assertNotContains('Mezanine alto', OpcionUbicacion::nombres('piso'));

        $this->delete(route('cotizaciones.ubicaciones.destroy', $op));
        $this->assertDatabaseMissing('opciones_ubicacion', ['nombre' => 'Mezanine alto']);
    }

    public function test_no_se_repite_una_opcion_y_el_formulario_usa_la_lista(): void
    {
        $this->post(route('cotizaciones.ubicaciones.store'), ['tipo' => 'ambiente', 'nombre' => 'Walk-in closet']);
        $this->post(route('cotizaciones.ubicaciones.store'), ['tipo' => 'ambiente', 'nombre' => 'Walk-in closet'])->assertSessionHasErrors('nombre');
        $this->assertSame(1, OpcionUbicacion::where('nombre', 'Walk-in closet')->count());

        $this->get(route('cotizaciones.create'))->assertOk()->assertSee('Walk-in closet');
        $this->get(route('cotizaciones.ubicaciones.index'))->assertOk()->assertSee('Pisos y ambientes');
    }

    public function test_solo_administracion_gestiona_pisos_y_ambientes(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));
        $this->get(route('cotizaciones.ubicaciones.index'))->assertForbidden();
        $this->post(route('cotizaciones.ubicaciones.store'), ['tipo' => 'piso', 'nombre' => 'X'])->assertForbidden();
    }

    // ---------------------------------------------------------------- cola de cobros

    public function test_la_cola_de_caja_ahora_se_llama_cola_de_cobros(): void
    {
        Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $this->get(route('cajero.cola'))->assertOk()->assertSee('Cola de Cobros')->assertDontSee('Cola de Caja');
    }

    // ---------------------------------------------------------------- consolidación con el Excel

    private function consolidar(array $filas): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cons') . '.json';
        file_put_contents($ruta, json_encode(['filas' => $filas]));
        $this->artisan('clientes:consolidar', ['archivo' => $ruta])->assertSuccessful();
        @unlink($ruta);
    }

    private function fila(array $extra): array
    {
        return $extra + ['fila' => 4, 'id' => 7, 'fecha' => '2013-06-26', 'registrado_por' => 'P6 INDIRA', 'tipo' => 'ARQ.', 'nombres' => 'Sandra', 'ap1' => 'Valderrama', 'ap2' => null];
    }

    public function test_el_cliente_toma_el_id_del_excel_y_recibe_seguimiento_y_proyecto(): void
    {
        $c = Cliente::create(['nombre' => 'Sandra Valderrama', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $this->consolidar([$this->fila([
            'seguimiento' => ['situacion' => 'SI - seguimiento', 'tipo' => 'PUNTUAL - Deco', 'busca' => 'Luminarias', 'monto_presupuestado' => 1500.5, 'probabilidad' => '0.3', 'medio_contacto' => '6.0', 'nivel' => '2.0'],
            'proyecto' => ['codigo' => 'IL-51233', 'nombre' => 'HOTEL', 'persona' => 'ABIGAIL', 'prioridad' => 'M', 'resultado' => 'G = GANADO', 'monto_vendido' => 900, 'moneda' => 'USD'],
        ])]);

        $this->assertSame('KYR007', $c->fresh()->codigo);
        $s = ClienteSeguimiento::first();
        $this->assertSame($c->id, $s->cliente_id);
        $this->assertSame('En seguimiento', $s->situacion);
        $this->assertSame('Puntual - Decorativa', $s->tipo);
        $this->assertSame(30, $s->probabilidad);
        $this->assertSame('Vitrina', $s->medio_contacto);
        $p = ClienteProyecto::first();
        $this->assertSame('Ganado', $p->resultado);
        $this->assertSame('Media', $p->prioridad);
        $this->assertSame('Abigail', $p->persona_a_cargo);

        $this->get(route('clientes.show', $c))->assertOk()->assertSee('KYR007')->assertSee('Seguimiento comercial')->assertSee('HOTEL');
    }

    public function test_es_repetible_y_no_duplica_nada(): void
    {
        Cliente::create(['nombre' => 'Sandra Valderrama', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $filas = [$this->fila(['proyecto' => ['nombre' => 'CASA', 'codigo' => 'A1']])];
        $this->consolidar($filas);
        $this->consolidar($filas);

        $this->assertSame(1, Cliente::count());
        $this->assertSame(1, ClienteProyecto::count());
    }

    public function test_los_duplicados_se_fusionan_y_sus_ventas_pasan_al_cliente_que_queda(): void
    {
        $largo = Cliente::create(['nombre' => 'Gretna Guerrero', 'asesor' => 'Andrea', 'estado' => 'activo', 'created_at' => '2019-05-17 00:00:00']);
        $corto = Cliente::create(['nombre' => 'Gretna', 'asesor' => 'Andrea', 'telefono' => '992726160', 'estado' => 'activo', 'created_at' => '2019-05-17 00:00:00']);
        $almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        $venta = Venta::forceCreate(['codigo' => 'VEN-X', 'cliente_id' => $corto->id, 'user_id' => $this->admin->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(), 'subtotal' => 10, 'total' => 10, 'estado_pago' => 'pagado', 'tipo_comprobante' => 'nota_venta']);

        $this->consolidar([$this->fila(['id' => 683, 'fecha' => '2019-05-17', 'registrado_por' => 'P1 ANDREA', 'nombres' => 'Gretna', 'ap1' => 'Guerrero'])]);

        $this->assertSame(1, Cliente::count());
        $quedo = Cliente::first();
        $this->assertSame('Gretna Guerrero', $quedo->nombre);
        $this->assertSame('992726160', $quedo->telefono, 'se conserva el teléfono del duplicado');
        $this->assertSame($quedo->id, $venta->fresh()->cliente_id);
        $this->assertSame('KYR683', $quedo->codigo);
    }

    public function test_dos_personas_distintas_con_el_mismo_nombre_y_distinto_documento_no_se_fusionan(): void
    {
        Cliente::create(['nombre' => 'Ana Maria Rojas', 'tipo_documento' => 'DNI', 'numero_documento' => '11111111', 'estado' => 'activo']);
        Cliente::create(['nombre' => 'Ana Maria Rojas', 'tipo_documento' => 'DNI', 'numero_documento' => '22222222', 'estado' => 'activo']);
        $this->consolidar([$this->fila(['nombres' => 'Ana Maria', 'ap1' => 'Rojas', 'fecha' => null])]);

        $this->assertSame(2, Cliente::count());
    }

    public function test_los_codigos_numericos_del_medio_de_contacto_se_traducen(): void
    {
        Cliente::create(['nombre' => 'Uno', 'origen' => '6', 'estado' => 'activo']);
        Cliente::create(['nombre' => 'Dos', 'origen' => '2', 'estado' => 'activo']);
        Cliente::create(['nombre' => 'Tres', 'origen' => '2- CLIENTE ANTIGUO', 'estado' => 'activo']);
        $this->consolidar([]);

        $this->assertSame(['Vitrina', 'Cliente antiguo', 'Cliente antiguo'], Cliente::orderBy('id')->pluck('origen')->all());
    }

    public function test_una_fila_sin_pareja_crea_un_prospecto_con_el_siguiente_id(): void
    {
        $this->consolidar([$this->fila(['id' => 912, 'nombres' => 'Carla', 'ap1' => 'Siviruero', 'correo' => 'carla@ejemplo.com'])]);

        $c = Cliente::first();
        $this->assertSame('Carla Siviruero', $c->nombre);
        $this->assertSame('KYR912', $c->codigo);
        $this->assertSame('ARQ', $c->segmento);
        $this->assertSame('Indira', $c->asesor);
    }

    public function test_la_simulacion_no_cambia_nada(): void
    {
        Cliente::create(['nombre' => 'Sandra Valderrama', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $ruta = tempnam(sys_get_temp_dir(), 'cons') . '.json';
        file_put_contents($ruta, json_encode(['filas' => [$this->fila(['proyecto' => ['nombre' => 'CASA']])]]));
        $this->artisan('clientes:consolidar', ['archivo' => $ruta, '--dry-run' => true])->assertSuccessful();
        @unlink($ruta);

        $this->assertSame(0, ClienteProyecto::count());
        $this->assertNotSame('KYR007', Cliente::first()->codigo);
    }

    // ---------------------------------------------------------------- base comercial / base completa

    private function difusion(string $nombre): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'estado' => 'activo', 'listas_difusion' => ['mujer'], 'origen' => 'Lista de difusión', 'en_base_comercial' => false]);
    }

    public function test_el_listado_muestra_por_defecto_solo_la_base_comercial(): void
    {
        Cliente::create(['nombre' => 'Comercial Uno', 'estado' => 'activo']);
        $this->difusion('Contacto Difusion');

        $this->get(route('clientes.index'))->assertOk()->assertSee('Comercial Uno')->assertDontSee('Contacto Difusion')->assertSee('Base comercial')->assertSee('Base completa');
        $this->get(route('clientes.index', ['base' => 'completa']))->assertOk()->assertSee('Comercial Uno')->assertSee('Contacto Difusion');
    }

    public function test_filtrar_por_lista_de_difusion_mira_la_base_completa(): void
    {
        $this->difusion('Contacto Difusion');

        $this->get(route('clientes.index', ['lista' => 'mujer']))->assertOk()->assertSee('Contacto Difusion');
    }

    public function test_solo_la_base_comercial_lleva_id_correlativo(): void
    {
        Cliente::create(['nombre' => 'Conid', 'estado' => 'activo'])->forceFill(['codigo' => 'KYR912'])->save();
        $nuevo = Cliente::create(['nombre' => 'Registrado en el sistema', 'estado' => 'activo']);
        $difusion = $this->difusion('Solo difusion');

        $this->assertSame('KYR913', $nuevo->codigo, 'sigue la correlación del Excel');
        $this->assertNull($difusion->codigo, 'la lista de difusión no lleva ID');
    }

    public function test_quien_no_tenia_id_en_el_excel_queda_sin_id_y_solo_en_la_base_completa(): void
    {
        $sin = Cliente::create(['nombre' => 'Sandra Valderrama', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $dif = $this->difusion('Contacto Aparte');
        $this->consolidar([$this->fila(['id' => null])]);

        $this->assertNull($sin->fresh()->codigo);
        $this->assertFalse($sin->fresh()->en_base_comercial, 'sin ID solo está en la base completa');
        $this->assertFalse($dif->fresh()->en_base_comercial);
        $this->assertNull($dif->fresh()->codigo);
    }

    public function test_el_listado_muestra_sin_id_cuando_no_hay_codigo(): void
    {
        $c = Cliente::create(['nombre' => 'Sin codigo', 'estado' => 'activo']);
        $c->forceFill(['codigo' => null])->save();

        $this->get(route('clientes.index'))->assertOk()->assertDontSee('Sin codigo');
        $this->get(route('clientes.index', ['base' => 'completa']))->assertOk()->assertSee('Sin codigo')->assertSee('Sin ID');
        $this->get(route('clientes.show', $c))->assertOk();
    }

    public function test_la_base_comercial_es_solo_quien_tiene_id_y_las_empresas_derivadas_quedan_en_la_completa(): void
    {
        $persona = Cliente::create(['nombre' => 'Sandra Valderrama', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $empresa = Cliente::create(['nombre' => 'BOSCH ARQUITECTOS PERU S.R.L.', 'tipo_documento' => 'RUC', 'numero_documento' => '20547472765', 'estado' => 'activo', 'created_at' => '2026-10-23 11:57:41']);
        $conVenta = Cliente::create(['nombre' => 'Empresa con ventas', 'tipo_documento' => 'RUC', 'numero_documento' => '20100000001', 'estado' => 'activo', 'created_at' => '2026-10-23 11:57:41']);
        $almacen = Almacen::create(['codigo' => 'ALM-A', 'nombre' => 'Local A', 'tipo' => 'tienda', 'estado' => 'activo']);
        Venta::forceCreate(['codigo' => 'VEN-Y', 'cliente_id' => $conVenta->id, 'user_id' => $this->admin->id, 'almacen_id' => $almacen->id, 'fecha' => now()->toDateString(), 'subtotal' => 10, 'total' => 10, 'estado_pago' => 'pagado', 'tipo_comprobante' => 'nota_venta']);
        $this->consolidar([$this->fila([])]);

        $this->assertTrue($persona->fresh()->en_base_comercial);
        $this->assertFalse($empresa->fresh()->en_base_comercial, 'la empresa viene de la columna RUC, no es una fila del Excel');
    }

    public function test_la_empresa_y_su_contacto_son_un_solo_cliente(): void
    {
        $persona = Cliente::create(['nombre' => 'Victor Vargas Miranda', 'telefono' => '987654321', 'estado' => 'activo', 'created_at' => '2013-06-26 00:00:00']);
        $empresa = Cliente::create(['nombre' => 'BOSCH ARQUITECTOS PERU S.R.L.', 'tipo_documento' => 'RUC', 'numero_documento' => '20547472765', 'estado' => 'activo']);
        $this->consolidar([$this->fila(['id' => 872, 'nombres' => 'Victor', 'ap1' => 'Vargas', 'ap2' => 'Miranda', 'ruc' => '20547472765'])]);

        $this->assertSame(1, Cliente::count());
        $c = Cliente::first();
        $this->assertSame($empresa->id, $c->id);
        $this->assertSame('BOSCH ARQUITECTOS PERU S.R.L.', $c->nombre);
        $this->assertSame('Victor Vargas Miranda', $c->contacto);
        $this->assertSame('KYR872', $c->codigo);
        $this->assertSame('987654321', $c->telefono);

        $this->consolidar([$this->fila(['id' => 872, 'nombres' => 'Victor', 'ap1' => 'Vargas', 'ap2' => 'Miranda', 'ruc' => '20547472765'])]);
        $this->assertSame(1, Cliente::count(), 'repetible: no vuelve a separar ni a duplicar');
    }

    // ---------------------------------------------------------------- cumpleaños

    private function empleado(array $extra): \App\Models\Empleado
    {
        return \App\Models\Empleado::create($extra + ['codigo' => 'EMP' . random_int(1000, 9999), 'nombres' => 'Lucia', 'apellidos' => 'Rojas', 'dni' => (string) random_int(10000000, 99999999), 'estado' => 'activo', 'fecha_ingreso' => '2024-01-01', 'cargo' => 'Asistente', 'tipo_contrato' => 'indefinido']);
    }

    public function test_el_dashboard_muestra_el_calendario_de_cumpleanos(): void
    {
        $this->empleado(['nombres' => 'Marisol', 'fecha_nacimiento' => today()->subYears(30)->addDays(1)->toDateString()]);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Cumpleaños')->assertSee('Marisol');
    }

    public function test_avisa_dos_dias_antes_una_sola_vez_y_no_a_quien_cumple(): void
    {
        $cumple = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Operaciones'])->id, 'estado' => 'activo']);
        $e = $this->empleado(['user_id' => $cumple->id, 'nombres' => 'Marisol', 'fecha_nacimiento' => today()->subYears(30)->addDays(2)->toDateString()]);
        $lejano = $this->empleado(['nombres' => 'Lejana', 'fecha_nacimiento' => today()->subYears(25)->addDays(10)->toDateString()]);

        $svc = app(\App\Services\CumpleanosService::class);
        $this->assertSame(1, $svc->avisar() > 0 ? 1 : 0);
        $this->assertSame(0, $svc->avisar(), 'no se repite');

        $this->assertSame(1, $this->admin->notifications()->where('data->clave', 'cumple:e' . $e->id . ':' . today()->addDays(2)->year)->count());
        $this->assertSame(0, $cumple->notifications()->count(), 'el cumpleañero no recibe su propio aviso');
        $this->assertSame(0, $this->admin->notifications()->where('data->clave', 'like', 'cumple:e' . $lejano->id . ':%')->count());
        $this->assertStringContainsString('en 2 días', $this->admin->notifications()->first()->data['mensaje']);
    }

    public function test_el_29_de_febrero_se_celebra_el_28_en_anos_no_bisiestos(): void
    {
        $e = $this->empleado(['fecha_nacimiento' => '2000-02-29']);
        $f = app(\App\Services\CumpleanosService::class)->enAnio($e, 2027);

        $this->assertSame('2027-02-28', $f->toDateString());
    }

    // ---------------------------------------------------------------- cumpleaños de usuarios y clientes

    private function datosUsuario(array $extra = []): array
    {
        return $extra + ['name' => 'Nuevo Usuario', 'email' => 'nuevo@ejemplo.com', 'password' => 'clave12345', 'password_confirmation' => 'clave12345',
            'role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo'];
    }

    public function test_al_crear_un_usuario_se_pide_la_fecha_de_nacimiento(): void
    {
        $this->post(route('users.store'), $this->datosUsuario())->assertSessionHasErrors('fecha_nacimiento');
        $this->post(route('users.store'), $this->datosUsuario(['fecha_nacimiento' => today()->addDay()->toDateString()]))->assertSessionHasErrors('fecha_nacimiento');

        $this->post(route('users.store'), $this->datosUsuario(['fecha_nacimiento' => '1990-05-17']))->assertSessionHasNoErrors();
        $this->assertSame('1990-05-17', User::where('email', 'nuevo@ejemplo.com')->first()->fecha_nacimiento->toDateString());
        $this->get(route('users.create'))->assertOk()->assertSee('Fecha de nacimiento');
    }

    public function test_el_cumpleanos_del_usuario_aparece_en_el_calendario_y_avisa(): void
    {
        $otro = User::factory()->create(['name' => 'Marta Equipo', 'role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo',
            'fecha_nacimiento' => today()->subYears(28)->addDays(2)->toDateString()]);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Marta');
        $this->assertSame(1, $this->admin->notifications()->where('data->clave', 'cumple:u' . $otro->id . ':' . today()->addDays(2)->year)->count());
        $this->assertSame(0, $otro->notifications()->count(), 'no se avisa al cumpleañero');
    }

    public function test_los_cumpleanos_de_clientes_tienen_su_propio_color_y_filtro(): void
    {
        Cliente::create(['nombre' => 'Cliente Festejado', 'estado' => 'activo', 'fecha_cumpleanos' => today()->subYears(40)->addDays(1)->toDateString()]);
        $this->empleado(['nombres' => 'Equipa', 'fecha_nacimiento' => today()->subYears(30)->addDays(1)->toDateString()]);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Cliente Festejado')->assertSee('Equipa')->assertSee('fa-user-tie', false);
        $this->get(route('admin.dashboard', ['cumple_ver' => 'clientes']))->assertOk()->assertSee('Cliente Festejado')->assertDontSee('Equipa');
        $this->get(route('admin.dashboard', ['cumple_ver' => 'equipo']))->assertOk()->assertSee('Equipa')->assertDontSee('Cliente Festejado');
    }

    public function test_el_cliente_que_cumple_avisa_a_administracion_y_marketing_dos_dias_antes(): void
    {
        $c = Cliente::create(['nombre' => 'Cliente Festejado', 'estado' => 'activo', 'fecha_cumpleanos' => today()->subYears(40)->addDays(2)->toDateString()]);
        $mkt = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Marketing'])->id, 'estado' => 'activo']);
        $logi = User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']);
        $lejano = Cliente::create(['nombre' => 'Lejano', 'estado' => 'activo', 'fecha_cumpleanos' => today()->subYears(40)->addDays(9)->toDateString()]);

        $svc = app(\App\Services\CumpleanosService::class);
        $this->assertGreaterThan(0, $svc->avisar());
        $this->assertSame(0, $svc->avisar(), 'no se repite');

        $clave = 'cumple:c' . $c->id . ':' . today()->addDays(2)->year;
        $this->assertSame(1, $this->admin->notifications()->where('data->clave', $clave)->count());
        $this->assertSame(1, $mkt->notifications()->where('data->clave', $clave)->count());
        $this->assertSame(0, $logi->notifications()->where('data->clave', $clave)->count(), 'Logística no ve clientes');
        $this->assertSame(0, $this->admin->notifications()->where('data->clave', 'cumple:c' . $lejano->id . ':' . today()->year)->count());
        $this->assertSame('/clientes/' . $c->id, $this->admin->notifications()->where('data->clave', $clave)->first()->data['url']);
    }

    public function test_logistica_ve_el_calendario_pero_sin_cumpleanos_de_clientes(): void
    {
        Cliente::create(['nombre' => 'Cliente Reservado', 'estado' => 'activo', 'fecha_cumpleanos' => today()->subYears(40)->addDays(1)->toDateString()]);
        $this->actingAs(User::factory()->create(['role_id' => Role::firstOrCreate(['nombre' => 'Logística'])->id, 'estado' => 'activo']));

        $this->get(route('logistica.dashboard'))->assertOk()->assertSee('Cumpleaños')->assertDontSee('Cliente Reservado');
    }

    // ---------------------------------------------------------------- exportación con la estructura del Excel

    public function test_el_excel_exportado_sigue_la_estructura_de_la_base_original(): void
    {
        $c = Cliente::create(['nombre' => 'COMINTER S.A.C.', 'tipo_documento' => 'RUC', 'numero_documento' => '20100334381', 'contacto' => 'Ruben Sanchez Duque',
            'cargo' => 'Gerente', 'telefono' => '993501534', 'email' => 'rsanchez@cominterperu.com', 'asesor' => 'Angelica', 'segmento' => 'ING',
            'estado' => 'activo', 'fecha_cumpleanos' => '1975-06-15', 'direccion' => 'AV. CAMINO REAL 348']);
        ClienteSeguimiento::create(['cliente_id' => $c->id, 'situacion' => 'En seguimiento', 'tipo' => 'Proyecto', 'monto_presupuestado' => 1500.5, 'moneda' => 'USD', 'probabilidad' => 30]);
        ClienteProyecto::create(['cliente_id' => $c->id, 'codigo' => 'IL-51233', 'nombre' => 'HOTEL', 'monto_vendido' => 900, 'moneda' => 'PEN', 'resultado' => 'Ganado']);
        ClienteProyecto::create(['cliente_id' => $c->id, 'codigo' => 'IL-60000', 'nombre' => 'OFICINA', 'resultado' => 'Perdido']);

        $r = $this->get(route('clientes.exportar'))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $r->headers->get('Content-Type'));
        $ruta = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        file_put_contents($ruta, $r->streamedContent());
        $h = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta)->getActiveSheet();
        @unlink($ruta);

        $this->assertSame('REGISTRO', $h->getCell('A1')->getValue());
        $this->assertSame('DATOS DE IDENTIFICACIÓN', $h->getCell('D1')->getValue());
        $this->assertSame(['ID CLIENTE', 'FECHA REGISTRO', 'REGISTRADO POR', 'TIPO', 'NOMBRES', 'APELLIDO 1', 'APELLIDO 2', 'DNI', 'CORREO PERSONAL', 'CELULAR'],
            array_map(fn ($col) => $h->getCell($col . '2')->getValue(), ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']));
        $this->assertSame('KYR001', $h->getCell('A3')->getValue());
        $this->assertSame('ING', $h->getCell('D3')->getValue());
        $this->assertSame('Ruben', $h->getCell('E3')->getValue());
        $this->assertSame('Sanchez', $h->getCell('F3')->getValue());
        $this->assertSame('Duque', $h->getCell('G3')->getValue());
        $this->assertSame('993501534', (string) $h->getCell('J3')->getValue());
        $this->assertSame('20100334381', (string) $h->getCell('T3')->getValue(), 'RUC');
        $this->assertSame('COMINTER S.A.C.', $h->getCell('U3')->getValue(), 'EMPRESA');
        $this->assertSame('AV. CAMINO REAL 348', $h->getCell('Z3')->getValue(), 'DIRECCIÓN EMPRESA');
        $this->assertSame('rsanchez@cominterperu.com', $h->getCell('AA3')->getValue(), 'CORREO EMPRESA');
        $this->assertSame('En seguimiento', $h->getCell('AK3')->getValue(), 'SITUACIÓN');
        $this->assertSame(1500.5, (float) $h->getCell('AO3')->getValue());
        $codigos = [$h->getCell('AU3')->getValue(), $h->getCell('AU4')->getValue()];
        sort($codigos);
        $this->assertSame(['IL-51233', 'IL-60000'], $codigos, 'ID DEL PROYECTO, uno por fila');
        // segundo proyecto en una fila adicional que repite al cliente
        $this->assertSame('KYR001', $h->getCell('A4')->getValue());
        $this->assertNull($h->getCell('J4')->getValue());
    }

    public function test_el_excel_respeta_la_base_elegida(): void
    {
        Cliente::create(['nombre' => 'Conid', 'estado' => 'activo']);
        $sin = Cliente::create(['nombre' => 'Sinid', 'estado' => 'activo', 'en_base_comercial' => false]);

        $leer = function (array $q) {
            $ruta = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
            file_put_contents($ruta, $this->get(route('clientes.exportar', $q))->streamedContent());
            $filas = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta)->getActiveSheet()->toArray();
            @unlink($ruta);

            return json_encode($filas);
        };
        $this->assertStringNotContainsString('Sinid', $leer([]));
        $this->assertStringContainsString('Sinid', $leer(['base' => 'completa']));
        $this->assertNull($sin->fresh()->codigo);
    }

    public function test_el_nombre_se_parte_en_nombres_y_apellidos(): void
    {
        $p = fn ($n) => \App\Support\ExportadorClientes::partirNombre($n);
        $this->assertSame(['Ruben', 'Sanchez', 'Duque'], $p('Ruben Sanchez Duque'));
        $this->assertSame(['Maria Fernanda', 'Waisman', 'Del Pino'], $p('Maria Fernanda Waisman Del Pino'));
        $this->assertSame(['Ana', 'Rojas', ''], $p('Ana Rojas'));
        $this->assertSame(['Carlos', '', ''], $p('Carlos'));
    }
}
