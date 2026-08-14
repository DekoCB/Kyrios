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

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function getLogoPdfUrlAttribute(): ?string
    {
        return $this->logo_pdf_path ? asset('storage/' . $this->logo_pdf_path) : null;
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
