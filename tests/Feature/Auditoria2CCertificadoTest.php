<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El certificado digital se convierte aunque el hosting no permita ejecutar openssl o tenga OpenSSL 1.x.
 */
class Auditoria2CCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private function pfx(string $clave): UploadedFile
    {
        $cfg = ['config' => 'C:/xampp/php/extras/ssl/openssl.cnf'];
        $cfg = is_file($cfg['config']) ? $cfg : [];
        $pk = openssl_pkey_new($cfg + ['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'PRUEBA', 'countryName' => 'PE'], $pk, $cfg);
        $x509 = openssl_csr_sign($csr, null, $pk, 30, $cfg);
        openssl_pkcs12_export($x509, $contenido, $pk, $clave);

        return UploadedFile::fake()->createWithContent('prueba.pfx', $contenido);
    }

    private function convertir(UploadedFile $pfx, string $clave): string
    {
        Storage::fake('local');
        $empresa = Empresa::create(['ruc' => '20000000001', 'razon_social' => 'PRUEBAS SAC', 'direccion' => 'AV. X 1']);
        $ruta = app(CertificadoService::class)->convertirPfxAPem($pfx, $clave, $empresa);

        return Storage::disk('local')->get($ruta);
    }

    public function test_convierte_el_pfx_en_pem_con_llave_y_certificado(): void
    {
        $pem = $this->convertir($this->pfx('clave123'), 'clave123');

        $this->assertStringContainsString('PRIVATE KEY', $pem);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $pem);
    }

    public function test_si_no_se_puede_ejecutar_openssl_usa_la_extension_de_php(): void
    {
        Process::fake(['*' => Process::result('', 'proc_open() has been disabled for security reasons', 127)]);

        $pem = $this->convertir($this->pfx('clave123'), 'clave123');

        $this->assertStringContainsString('PRIVATE KEY', $pem);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $pem);
    }

    public function test_si_openssl_no_conoce_legacy_reintenta_sin_esa_opcion(): void
    {
        $llamadas = [];
        Process::fake(function ($proceso) use (&$llamadas) {
            $comando = is_array($proceso->command) ? implode(' ', $proceso->command) : (string) $proceso->command;
            $llamadas[] = $comando;
            if (str_contains($comando, '-legacy')) {
                return Process::result('', 'pkcs12: Unrecognized flag legacy', 1);
            }
            // Sin -legacy "funciona": escribe un archivo con contenido
            if (preg_match('/-out (\S+)/', $comando, $m)) {
                file_put_contents($m[1], "-----BEGIN CERTIFICATE-----\nAAA\n-----END CERTIFICATE-----\n");
            }

            return Process::result('', '', 0);
        });

        $pem = $this->convertir($this->pfx('clave123'), 'clave123');

        $this->assertNotEmpty(array_filter($llamadas, fn ($c) => str_contains($c, '-legacy')), 'primero se intenta con -legacy');
        $this->assertNotEmpty(array_filter($llamadas, fn ($c) => !str_contains($c, '-legacy')), 'luego se reintenta sin -legacy');
        $this->assertStringContainsString('BEGIN CERTIFICATE', $pem);
    }

    public function test_una_contrasena_incorrecta_se_informa_con_claridad(): void
    {
        $this->expectExceptionMessage('Verifique la contraseña');

        $this->convertir($this->pfx('clave123'), 'otra-clave');
    }
}
