<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SunatService
{
    // APIs públicas de apis.net.pe (sin token requerido)
    protected const RUC_URL = 'https://api.apis.net.pe/v1/ruc';
    protected const DNI_URL = 'https://api.apis.net.pe/v1/dni';

    public function consultarRuc(string $ruc): array
    {
        if (strlen($ruc) !== 11 || !ctype_digit($ruc)) {
            return ['success' => false, 'message' => 'El RUC debe tener 11 dígitos numéricos.'];
        }

        $cacheKey = "sunat_ruc_{$ruc}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->consultarRucRemoto($ruc);

        // Solo cacheamos resultados exitosos: un fallo (timeout, rate-limit de
        // la API gratuita, caída puntual) no debe bloquear el RUC como "no
        // encontrado" durante 7 días para todos los usuarios.
        if ($result['success'] ?? false) {
            Cache::put($cacheKey, $result, 604800);
        }

        return $result;
    }

    protected function consultarRucRemoto(string $ruc): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get(self::RUC_URL, ['numero' => $ruc]);

            if ($response->successful()) {
                $data = $response->json();

                // La API devuelve 'numeroDocumento' y 'nombre' (no 'ruc'/'razonSocial')
                if (empty($data['numeroDocumento']) && empty($data['nombre'])) {
                    return ['success' => false, 'message' => 'RUC no encontrado en SUNAT.'];
                }

                // Dirección completa: campo 'direccion' ya viene completo, agregamos ubigeo
                $partesDireccion = array_filter([
                    $data['direccion']    ?? null,
                    $data['distrito']     ?? null,
                    $data['provincia']    ?? null,
                    $data['departamento'] ?? null,
                ], fn($v) => $v && $v !== '-');

                $direccionCompleta = implode(', ', $partesDireccion);

                return [
                    'success' => true,
                    'data' => [
                        'ruc'              => $data['numeroDocumento'] ?? $ruc,
                        'razon_social'     => $data['nombre']          ?? '',
                        'nombre_comercial' => null,
                        'direccion'        => $direccionCompleta ?: null,
                        'direccion_fiscal' => $data['direccion']       ?? null,
                        'distrito'         => $data['distrito']        ?? null,
                        'provincia'        => $data['provincia']       ?? null,
                        'departamento'     => $data['departamento']    ?? null,
                        'ubigeo'           => $data['ubigeo']          ?? null,
                        'estado'           => $data['estado']          ?? null,
                        'condicion'        => $data['condicion']       ?? null,
                        'tipo'             => $data['tipoDocumento']   ?? null,
                        // Desglose de dirección tal cual lo entrega la API (apis.net.pe):
                        // útil para mostrar el detalle completo, aunque para SUNAT (GRE)
                        // solo 'ubigeo' + 'direccion_fiscal' son los que realmente viajan.
                        'via_tipo'         => $data['viaTipo']         ?? null,
                        'via_nombre'       => $data['viaNombre']       ?? null,
                        'zona_codigo'      => $data['zonaCodigo']      ?? null,
                        'zona_tipo'        => $data['zonaTipo']        ?? null,
                        'numero'           => $data['numero']          ?? null,
                        'interior'         => $data['interior']        ?? null,
                        'lote'             => $data['lote']            ?? null,
                        'dpto'             => $data['dpto']            ?? null,
                        'manzana'          => $data['manzana']         ?? null,
                        'kilometro'        => $data['kilometro']       ?? null,
                    ],
                ];
            }

            $status = $response->status();
            if ($status === 404) {
                return ['success' => false, 'message' => 'RUC no encontrado en SUNAT.'];
            }

            return ['success' => false, 'message' => "Error al consultar SUNAT (HTTP {$status})."];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'No se pudo conectar al servicio de consulta RUC.'];
        }
    }

    public function consultarDni(string $dni): array
    {
        if (strlen($dni) !== 8 || !ctype_digit($dni)) {
            return ['success' => false, 'message' => 'El DNI debe tener 8 dígitos numéricos.'];
        }

        $cacheKey = "sunat_dni_{$dni}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->consultarDniRemoto($dni);

        if ($result['success'] ?? false) {
            Cache::put($cacheKey, $result, 604800);
        }

        return $result;
    }

    protected function consultarDniRemoto(string $dni): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get(self::DNI_URL, ['numero' => $dni]);

            if ($response->successful()) {
                $data = $response->json();

                // La API devuelve 'numeroDocumento' (no 'dni')
                if (empty($data['numeroDocumento']) && empty($data['nombre'])) {
                    return ['success' => false, 'message' => 'DNI no encontrado en RENIEC.'];
                }

                // Preferir el campo 'nombre' que viene completo; si no, construirlo
                $nombre = $data['nombre'] ?? trim(implode(' ', array_filter([
                    $data['nombres']         ?? null,
                    $data['apellidoPaterno'] ?? null,
                    $data['apellidoMaterno'] ?? null,
                ])));

                return [
                    'success' => true,
                    'data' => [
                        'dni'    => $data['numeroDocumento'] ?? $dni,
                        'nombre' => $nombre,
                    ],
                ];
            }

            $status = $response->status();
            if ($status === 404) {
                return ['success' => false, 'message' => 'DNI no encontrado en RENIEC.'];
            }

            return ['success' => false, 'message' => "Error al consultar RENIEC (HTTP {$status})."];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'No se pudo conectar al servicio de consulta DNI.'];
        }
    }
}
