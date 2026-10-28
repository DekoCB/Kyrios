<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las credenciales de SUNAT no deben quedar en texto plano en la base de datos.
 */
class Auditoria2CCifradoTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(array $extra = []): Empresa
    {
        return Empresa::create($extra + ['ruc' => '20000000001', 'razon_social' => 'PRUEBAS SAC', 'direccion' => 'AV. X 1']);
    }

    public function test_las_credenciales_se_guardan_cifradas_y_se_leen_en_claro(): void
    {
        $e = $this->empresa([
            'sunat_usuario_sol' => 'MODDATOS', 'sunat_clave_sol' => 'moddatos', 'certificado_pfx_password' => 'clave-del-pfx',
            'gre_client_secret' => 'secreto-gre', 'sunat_token' => 'token-abc', 'api_key' => 'api-123',
        ]);

        $crudo = DB::table('empresas')->where('id', $e->id)->first();
        foreach (['sunat_clave_sol' => 'moddatos', 'certificado_pfx_password' => 'clave-del-pfx', 'gre_client_secret' => 'secreto-gre', 'sunat_token' => 'token-abc', 'api_key' => 'api-123'] as $col => $claro) {
            $this->assertNotSame($claro, $crudo->{$col}, "{$col} quedó en texto plano");
            $this->assertStringNotContainsString($claro, $crudo->{$col});
            $this->assertSame($claro, $e->fresh()->{$col});
        }
        $this->assertSame('MODDATOS', $crudo->sunat_usuario_sol, 'el usuario SOL no es secreto');
    }

    public function test_un_valor_antiguo_en_texto_plano_se_sigue_leyendo(): void
    {
        $e = $this->empresa();
        DB::table('empresas')->where('id', $e->id)->update(['sunat_clave_sol' => 'plano-antiguo']);

        $this->assertSame('plano-antiguo', $e->fresh()->sunat_clave_sol);

        $e->fresh()->update(['sunat_clave_sol' => 'plano-antiguo']);
        $this->assertNotSame('plano-antiguo', DB::table('empresas')->where('id', $e->id)->value('sunat_clave_sol'));
    }

    public function test_la_migracion_cifra_lo_que_ya_estaba_guardado(): void
    {
        $e = $this->empresa();
        DB::table('empresas')->where('id', $e->id)->update(['sunat_clave_sol' => 'plano-antiguo', 'gre_client_secret' => 'otro-plano']);

        (require database_path('migrations/2026_10_28_100000_cifrar_credenciales_de_empresa.php'))->up();

        $crudo = DB::table('empresas')->where('id', $e->id)->first();
        $this->assertNotSame('plano-antiguo', $crudo->sunat_clave_sol);
        $this->assertNotSame('otro-plano', $crudo->gre_client_secret);
        $this->assertSame('plano-antiguo', $e->fresh()->sunat_clave_sol);
        $this->assertSame('otro-plano', $e->fresh()->gre_client_secret);
    }

    public function test_un_valor_cifrado_largo_cabe_en_la_columna(): void
    {
        $e = $this->empresa(['sunat_clave_sol' => str_repeat('x', 100), 'certificado_pfx_password' => str_repeat('y', 200)]);

        $this->assertSame(str_repeat('x', 100), $e->fresh()->sunat_clave_sol);
        $this->assertSame(str_repeat('y', 200), $e->fresh()->certificado_pfx_password);
    }
}
