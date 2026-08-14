<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class CertificadoService
{
    public function convertirPfxAPem(UploadedFile $pfxFile, string $password, Empresa $empresa): string
    {
        $opensslBin = $this->resolveOpensslBinary();
        $env = ['CERT_PASS' => $password] + $this->resolveOpensslEnv();

        $tmpPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'cert_');
        $tmpKey = tempnam(sys_get_temp_dir(), 'key_');

        try {
            copy($pfxFile->getRealPath(), $tmpPfx);

            // -legacy fuerza el soporte de RC2/3DES (cifrado típico de certificados
            // SUNAT antiguos) que OpenSSL 3.x desactiva por defecto.
            $certResult = Process::env($env)->run([
                $opensslBin, 'pkcs12', '-legacy', '-in', $tmpPfx,
                '-passin', 'env:CERT_PASS', '-nokeys', '-out', $tmpCert,
            ]);

            if (!$certResult->successful()) {
                throw new \Exception($this->traducirError($certResult->errorOutput()));
            }

            $keyResult = Process::env($env)->run([
                $opensslBin, 'pkcs12', '-legacy', '-in', $tmpPfx,
                '-passin', 'env:CERT_PASS', '-nocerts', '-nodes', '-out', $tmpKey,
            ]);

            if (!$keyResult->successful()) {
                throw new \Exception($this->traducirError($keyResult->errorOutput()));
            }

            $certContent = file_get_contents($tmpCert);
            $keyContent = file_get_contents($tmpKey);

            if (trim((string) $certContent) === '' || trim((string) $keyContent) === '') {
                throw new \Exception('El certificado PFX no contiene una llave privada o certificado válidos.');
            }

            $pem = $keyContent . $certContent;

            $dir = "certificados/{$empresa->ruc}";
            $path = "{$dir}/certificado.pem";

            Storage::disk('local')->makeDirectory($dir);
            Storage::disk('local')->put($path, $pem);

            return $path;
        } finally {
            @unlink($tmpPfx);
            @unlink($tmpCert);
            @unlink($tmpKey);
        }
    }

    private function resolveOpensslBinary(): string
    {
        $windowsBin = 'C:\\xampp\\apache\\bin\\openssl.exe';

        if (PHP_OS_FAMILY === 'Windows' && file_exists($windowsBin)) {
            return $windowsBin;
        }

        return 'openssl';
    }

    /**
     * El openssl.exe de XAMPP (Apache) fue compilado con una ruta de módulos
     * ("ossl-modules") que no existe en esta instalación, por lo que no
     * encuentra el provider "legacy" (RC2/3DES) por sí solo. Apuntamos
     * explícitamente al legacy.dll que sí viene incluido junto a PHP.
     */
    private function resolveOpensslEnv(): array
    {
        $modulesDir = 'C:\\xampp\\php\\extras\\ssl';

        if (PHP_OS_FAMILY === 'Windows' && is_dir($modulesDir)) {
            return ['OPENSSL_MODULES' => $modulesDir];
        }

        return [];
    }

    private function traducirError(string $errorOutput): string
    {
        $lower = strtolower($errorOutput);

        if (str_contains($lower, 'mac verify') || str_contains($lower, 'wrong password') || str_contains($lower, 'bad decrypt')) {
            return 'No se pudo leer el certificado PFX. Verifique la contraseña.';
        }

        return 'No se pudo procesar el certificado PFX: ' . trim($errorOutput);
    }
}
