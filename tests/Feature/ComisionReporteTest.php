<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Role;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ComisionRegla;
use App\Models\ComisionDetalleVenta;
use App\Models\DetalleVenta;
use App\Models\Venta;

class ComisionReporteTest extends TestCase
{
    // Sin DatabaseTransactions: se limpia manualmente en tearDown (igual que
    // ComisionVendedorTest, ver ese archivo para el porqué).

    private array $createdUsers  = [];
    private array $createdReglas = [];

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (!empty($this->createdReglas)) {
            ComisionRegla::whereIn('id', $this->createdReglas)->delete();
        }
        if (!empty($this->createdUsers)) {
            User::whereIn('id', $this->createdUsers)->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->createdUsers  = [];
        $this->createdReglas = [];

        parent::tearDown();
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEST: el reporte de comisiones carga con el resumen mensual, incluso
    // con datos existentes de comisiones y bonos (ambas consultas de
    // agregación mensual deben ejecutar sin colisión de columnas ambiguas).
    // ══════════════════════════════════════════════════════════════════════
    public function test_reporte_de_comisiones_carga_correctamente_con_resumen_mensual(): void
    {
        $roleAdmin = Role::firstOrCreate(['nombre' => 'Administrador']);
        Role::firstOrCreate(['nombre' => 'Vendedor']);

        $uid   = uniqid();
        $admin = User::create([
            'name'     => 'Admin Reporte Test',
            'email'    => "admin_reporte_{$uid}@test.com",
            'password' => bcrypt('password'),
            'role_id'  => $roleAdmin->id,
        ]);
        $this->createdUsers[] = $admin->id;

        $response = $this->actingAs($admin)->get(route('comisiones.reporte'));

        $response->assertOk();
        $response->assertViewHas('resumenMensualPorMes');
    }
}
