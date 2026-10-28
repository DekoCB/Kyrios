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
        $pem = $this->extraerPem($pfxFile->getRealPath(), $password);

        $dir = "certificados/{$empresa->ruc}";
        $path = "{$dir}/certificado.pem";

        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->put($path, $pem);

        return $path;
    }

    /**
     * Devuelve el PEM (llave privada + certificado). Primero con el programa openssl (soporta los PFX antiguos
     * de SUNAT con RC2/3DES); si no existe, está bloqueado por el hosting o falla por otra razón que no sea la
     * contraseña, se usa la extensión openssl de PHP.
     */
    private function extraerPem(string $rutaPfx, string $password): string
    {
        $errorCli = null;
        try {
            return $this->extraerConCli($rutaPfx, $password);
        } catch (\Throwable $e) {
            $errorCli = $e;
            if ($this->esContrasenaIncorrecta($e->getMessage())) {
                throw $e;
            }
        }

        $contenido = (string) file_get_contents($rutaPfx);
        $certs = [];
        if (function_exists('openssl_pkcs12_read') && @openssl_pkcs12_read($contenido, $certs, $password)
            && !empty($certs['pkey']) && !empty($certs['cert'])) {
            return $certs['pkey'] . $certs['cert'] . (!empty($certs['extracerts']) ? implode('', $certs['extracerts']) : '');
        }

        throw $errorCli;
    }

    private function extraerConCli(string $rutaPfx, string $password): string
    {
        $opensslBin = $this->resolveOpensslBinary();
        $env = ['CERT_PASS' => $password] + $this->resolveOpensslEnv();

        $tmpPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'cert_');
        $tmpKey = tempnam(sys_get_temp_dir(), 'key_');

        try {
            copy($rutaPfx, $tmpPfx);

            // -legacy da soporte a RC2/3DES (cifrado típico de certificados SUNAT antiguos) que OpenSSL 3.x
            // desactiva por defecto. OpenSSL 1.x no conoce esa opción: se reintenta sin ella.
            foreach ([['-legacy'], []] as $i => $legacy) {
                $certResult = Process::env($env)->run(array_merge(
                    [$opensslBin, 'pkcs12'], $legacy,
                    ['-in', $tmpPfx, '-passin', 'env:CERT_PASS', '-nokeys', '-out', $tmpCert]
                ));
                if ($certResult->successful()) {
                    break;
                }
                if ($i === 1 || !str_contains(strtolower($certResult->errorOutput()), 'legacy')) {
                    throw new \Exception($this->traducirError($certResult->errorOutput()));
                }
            }

            $keyResult = Process::env($env)->run(array_merge(
                [$opensslBin, 'pkcs12'], $legacy,
                ['-in', $tmpPfx, '-passin', 'env:CERT_PASS', '-nocerts', '-nodes', '-out', $tmpKey]
            ));

            if (!$keyResult->successful()) {
                throw new \Exception($this->traducirError($keyResult->errorOutput()));
            }

            $certContent = file_get_contents($tmpCert);
            $keyContent = file_get_contents($tmpKey);

            if (trim((string) $certContent) === '' || trim((string) $keyContent) === '') {
                throw new \Exception('El certificado PFX no contiene una llave privada o certificado válidos.');
            }

            return $keyContent . $certContent;
        } finally {
            @unlink($tmpPfx);
            @unlink($tmpCert);
            @unlink($tmpKey);
        }
    }

    private function esContrasenaIncorrecta(string $mensaje): bool
    {
        return str_contains($mensaje, 'Verifique la contraseña');
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
