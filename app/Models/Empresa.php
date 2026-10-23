<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'ruc', 'razon_social', 'nombre_comercial', 'direccion',
        'ubigeo', 'departamento', 'provincia', 'distrito', 'regimen',
        'telefono', 'email', 'web',
        'facebook', 'instagram', 'tiktok',
        'logo_path', 'logo_pdf_path',
        'sunat_usuario_sol', 'sunat_clave_sol', 'sunat_token', 'sunat_modo',
        'api_url', 'api_key',
        'certificado_pfx_path', 'certificado_pfx_password',
        'certificado_pem_path',
        'gre_client_id', 'gre_client_secret',
    ];

    protected $hidden = [
        'sunat_clave_sol', 'sunat_token', 'api_key',
        'certificado_pfx_password', 'gre_client_secret',
    ];

    public static function instancia(): ?self
    {
        return self::first();
    }

    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }

    /**
     * Logos oficiales de Kyrios (public/img). Si la empresa sube los suyos en Admin > Empresa, tienen prioridad.
     *  - fondo oscuro: letras blancas → barra lateral y pantalla de acceso.
     *  - fondo claro:  letras oscuras → boletas, facturas, notas de venta, cotizaciones y guías.
     */
    public const LOGO_FONDO_OSCURO = 'img/logo-kyrios-fondo-oscuro.png';
    public const LOGO_FONDO_CLARO  = 'img/logo-kyrios-fondo-claro.png';

    /** Logo para pantallas con fondo oscuro. */
    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : asset(self::LOGO_FONDO_OSCURO);
    }

    /** Logo para documentos y pantallas con fondo claro. */
    public function getLogoPdfUrlAttribute(): string
    {
        return $this->logo_pdf_path ? asset('storage/' . $this->logo_pdf_path) : asset(self::LOGO_FONDO_CLARO);
    }

    /** URL del logo según el fondo ('oscuro' | 'claro'); funciona aunque todavía no exista la fila de empresa. */
    public static function logoUrlPara(string $fondo): string
    {
        $empresa = self::instancia();

        return $fondo === 'oscuro'
            ? ($empresa?->logo_url ?? asset(self::LOGO_FONDO_OSCURO))
            : ($empresa?->logo_pdf_url ?? asset(self::LOGO_FONDO_CLARO));
    }

    /**
     * Logo para documentos PDF (fondo blanco) como data URI, listo para <img src>.
     * Usa el logo de comprobantes subido por la empresa o, si no hay, el oficial de fondo claro.
     */
    public static function logoDocumentoDataUri(?self $empresa = null): ?string
    {
        $empresa ??= self::instancia();

        $ruta = $empresa?->logo_pdf_path ? storage_path('app/public/' . $empresa->logo_pdf_path) : null;
        if (!$ruta || !is_file($ruta)) {
            $ruta = public_path(self::LOGO_FONDO_CLARO);
        }
        if (!is_file($ruta)) {
            return null;
        }

        $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : "image/{$ext}";

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($ruta));
    }

    public function getCertificadoPemContentAttribute(): ?string
    {
        if (!$this->certificado_pem_path) return null;
        return Storage::disk('local')->exists($this->certificado_pem_path)
            ? Storage::disk('local')->get($this->certificado_pem_path)
            : null;
    }

    public function isSunatBeta(): bool
    {
        return ($this->sunat_modo ?? 'beta') !== 'produccion';
    }

    public function tieneCertificado(): bool
    {
        return !empty($this->certificado_pem_path)
            && Storage::disk('local')->exists($this->certificado_pem_path);
    }

    public function tieneCredencialesSunat(): bool
    {
        return !empty($this->sunat_usuario_sol) && !empty($this->sunat_clave_sol);
    }
}
