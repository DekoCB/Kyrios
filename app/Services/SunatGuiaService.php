<?php

namespace App\Services;

use App\Models\GuiaRemision;
use App\Models\Empresa;
use Greenter\Api;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Vehicle;
use Greenter\Model\Company\Company;
use Greenter\Model\Client\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SunatGuiaService
{
    private const MOTIVO_MAP = [
        'VENTA'                    => '01',
        'COMPRA'                   => '02',
        'TRASLADO_ENTRE_ALMACENES' => '04',
        'CONSIGNACION'             => '05',
        'DEVOLUCION'               => '06',
        'IMPORTACION'              => '08',
        'EXPORTACION'              => '09',
        'OTRO'                     => '13',
    ];

    private const MOTIVO_LABEL = [
        '01' => 'Venta',
        '02' => 'Compra',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '05' => 'Consignación',
        '06' => 'Devolución',
        '08' => 'Importación',
        '09' => 'Exportación',
        '13' => 'Otros',
    ];

    private function crearApi(Empresa $empresa): Api
    {
        if (!$empresa->tieneCertificado()) {
            throw new \Exception('Certificado digital no configurado. Suba el archivo .pfx en Configuración de Empresa.');
        }
        if (!$empresa->tieneCredencialesSunat()) {
            throw new \Exception('Credenciales SOL no configuradas.');
        }

        $isBeta = $empresa->isSunatBeta();

        $api = new Api($isBeta ? [
            'auth' => 'https://gre-test.nubefact.com/v1',
            'cpe'  => 'https://gre-test.nubefact.com/v1',
        ] : [
            'auth' => 'https://api-cpe.sunat.gob.pe/v1',
            'cpe'  => 'https://api-cpe.sunat.gob.pe/v1',
        ]);

        $clientId     = $empresa->gre_client_id ?? ($isBeta ? 'test-85e5b0ae-255c-4891-a595-0b98c65c9854' : '');
        $clientSecret = $empresa->gre_client_secret ?? ($isBeta ? 'test-Hty/M6QshYvPgItX2P0+Kw==' : '');

        $api->setApiCredentials($clientId, $clientSecret)
            ->setClaveSOL($empresa->ruc, $empresa->sunat_usuario_sol, $empresa->sunat_clave_sol)
            ->setCertificate($empresa->certificado_pem_content);

        $cachePath = storage_path('app/greenter/cache');
        if (!is_dir($cachePath)) mkdir($cachePath, 0755, true);

        return $api;
    }

    public function enviar(GuiaRemision $guia): array
    {
        $empresa = Empresa::instancia();
        if (!$empresa) {
            return ['success' => false, 'message' => 'Empresa no configurada.'];
        }

        if ($guia->sunat_estado === 'aceptado') {
            return ['success' => false, 'message' => 'Esta guía ya fue aceptada por SUNAT.'];
        }

        // SUNAT exige serie de guía remitente con 4 caracteres que empiezan con T (T001) y correlativo numérico;
        // el respaldo interno «GR-00001» solo sirve para uso local y SUNAT lo rechaza.
        if (!preg_match('/^T[A-Z0-9]{3}-\d{1,8}$/', (string) $guia->numero_guia)) {
            return [
                'success' => false,
                'message' => "El número de guía «{$guia->numero_guia}» no tiene el formato que exige SUNAT (T001-00000001). "
                    . 'Genere la serie de guías en Administración > Sucursales > Series y emita la guía de nuevo.',
            ];
        }

        $guia->load(['almacen.sucursal', 'almacenDestino.sucursal', 'detalles.producto.unidadMedida',
                      'cliente', 'proveedor', 'serieCombrobante']);

        try {
            $api      = $this->crearApi($empresa);
            $despatch = $this->buildDespatch($guia, $empresa);

            $result = $api->send($despatch);
            $guia->sunat_enviado_at = now();

            if ($result->isSuccess()) {
                $ticket = $result->getTicket();
                $guia->sunat_ticket = $ticket;
                $guia->sunat_estado = 'enviado';
                $guia->sunat_descripcion = "Enviado correctamente. Ticket: {$ticket}";

                try {
                    $xml = $api->getLastXml();
                    if ($xml) {
                        $parts = explode('-', $guia->numero_guia, 2);
                        Storage::disk('local')->put("sunat/guias/xml/{$parts[0]}-{$parts[1]}.xml", $xml);
                    }
                } catch (\Throwable $e) {}

            } else {
                $error = $result->getError();
                $guia->sunat_estado      = 'error';
                $guia->sunat_descripcion = $error ? $error->getMessage() : 'Error desconocido de SUNAT';
                $guia->sunat_cdr_code    = $error ? (string) $error->getCode() : null;
            }

            $guia->save();

            return [
                'success' => $guia->sunat_estado === 'enviado',
                'message' => $guia->sunat_descripcion,
            ];

        } catch (\Exception $e) {
            Log::error('SunatGuiaService::enviar', ['guia' => $guia->id, 'error' => $e->getMessage()]);
            $guia->sunat_estado      = 'error';
            $guia->sunat_descripcion = $e->getMessage();
            $guia->save();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function consultarEstado(GuiaRemision $guia): array
    {
        if (!$guia->sunat_ticket) {
            return ['success' => false, 'message' => 'Esta guía no tiene ticket de SUNAT.'];
        }

        $empresa = Empresa::instancia();
        if (!$empresa) {
            return ['success' => false, 'message' => 'Empresa no configurada.'];
        }

        try {
            $api    = $this->crearApi($empresa);
            $result = $api->getStatus($guia->sunat_ticket);

            if ($result->isSuccess()) {
                $cdr = $result->getCdrResponse();
                $guia->sunat_cdr_code    = (string) $cdr->getCode();
                $guia->sunat_descripcion = $cdr->getDescription();
                $guia->sunat_estado      = $cdr->getCode() === '0' ? 'aceptado' : 'rechazado';

                try {
                    $cdrZip = $result->getCdrZip();
                    if ($cdrZip) {
                        $parts = explode('-', $guia->numero_guia, 2);
                        Storage::disk('local')->put("sunat/guias/cdr/R-{$parts[0]}-{$parts[1]}.zip", $cdrZip);
                    }
                } catch (\Throwable $e) {}

                $guia->save();

                return ['success' => true, 'message' => $guia->sunat_descripcion, 'estado' => $guia->sunat_estado];
            }

            $error = $result->getError();
            return ['success' => false, 'message' => $error ? $error->getMessage() : 'Sin respuesta de SUNAT'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function buildDespatch(GuiaRemision $guia, Empresa $empresa): Despatch
    {
        $parts       = explode('-', $guia->numero_guia, 2);
        $serieStr    = $parts[0] ?? 'T001';
        $correlativo = $parts[1] ?? '00000001';
        $codTraslado = self::MOTIVO_MAP[$guia->motivo_traslado] ?? '13';
        $modTraslado = $guia->modalidad === 'publico' ? '01' : '02';

        $despatch = new Despatch();
        $despatch->setVersion('2022')
            ->setTipoDoc('09')
            ->setSerie($serieStr)
            ->setCorrelativo($correlativo)
            ->setFechaEmision($guia->fecha_traslado)
            ->setCompany($this->buildCompany($empresa))
            ->setDestinatario($this->buildDestinatario($guia, $empresa));

        // Envío
        $envio = new Shipment();
        $envio->setCodTraslado($codTraslado)
            ->setDesTraslado(self::MOTIVO_LABEL[$codTraslado] ?? 'Otros')
            ->setModTraslado($modTraslado)
            ->setFecTraslado($guia->fecha_traslado)
            ->setPesoTotal((float) ($guia->peso_total ?? 1))
            ->setUndPesoTotal('KGM');

        $envio->setLlegada(new Direction(
            $guia->ubigeo_llegada ?? '150101',
            $guia->direccion_llegada ?? ''
        ));
        $envio->setPartida(new Direction(
            $guia->ubigeo_partida ?? ($empresa->ubigeo ?? '150101'),
            $guia->direccion_partida ?? ($empresa->direccion ?? '')
        ));

        // Transporte público → transportista
        if ($modTraslado === '01' && $guia->transportista_doc) {
            $transportista = new Transportist();
            $transportista->setTipoDoc($guia->transportista_tipo_doc === 'RUC' ? '6' : '1')
                ->setNumDoc($guia->transportista_doc)
                ->setRznSocial($guia->transportista_nombre ?? '');
            $envio->setTransportista($transportista);
        }

        // Transporte privado → conductor + vehículo
        if ($modTraslado === '02') {
            if ($guia->conductor_dni) {
                $nombreParts = explode(' ', trim($guia->conductor_nombre ?? ''), 2);
                $chofer = new Driver();
                $chofer->setTipo('Principal')
                    ->setTipoDoc('1')
                    ->setNroDoc($guia->conductor_dni)
                    ->setLicencia($guia->conductor_licencia ?? '')
                    ->setApellidos($nombreParts[0] ?? '')
                    ->setNombres($nombreParts[1] ?? ($nombreParts[0] ?? ''));
                $envio->setChoferes([$chofer]);
            }

            if ($guia->placa_vehiculo) {
                $vehiculo = new Vehicle();
                $vehiculo->setPlaca($guia->placa_vehiculo);
                $envio->setVehiculo($vehiculo);
            } else {
                $envio->setIndicadores(['SUNAT_Envio_IndicadorTrasladoVehiculoM1L']);
            }
        }

        $despatch->setEnvio($envio);

        // Detalles
        $details = [];
        foreach ($guia->detalles as $det) {
            $detail = new DespatchDetail();
            $detail->setCantidad((float) $det->cantidad)
                ->setUnidad($det->producto?->unidadMedida?->codigo_sunat ?? 'NIU')
                ->setDescripcion($det->producto?->nombre ?? 'Producto')
                ->setCodigo($det->producto?->codigo ?? '');
            $details[] = $detail;
        }
        $despatch->setDetails($details);

        return $despatch;
    }

    private function buildCompany(Empresa $empresa): Company
    {
        $company = new Company();
        $company->setRuc($empresa->ruc)
            ->setRazonSocial($empresa->razon_social);
        return $company;
    }

    private function buildDestinatario(GuiaRemision $guia, Empresa $empresa): Client
    {
        $client = new Client();

        if ($guia->tipo_destino === 'almacen') {
            $client->setTipoDoc('6')
                ->setNumDoc($empresa->ruc)
                ->setRznSocial($empresa->razon_social);
        } elseif ($guia->tipo_destino === 'proveedor' && $guia->proveedor) {
            $client->setTipoDoc('6')
                ->setNumDoc($guia->proveedor->ruc)
                ->setRznSocial($guia->proveedor->razon_social);
        } elseif ($guia->tipo_destino === 'cliente' && $guia->cliente) {
            $doc = $guia->cliente->numero_documento ?? $guia->cliente->documento ?? '';
            $client->setTipoDoc(strlen($doc) === 11 ? '6' : '1')
                ->setNumDoc($doc)
                ->setRznSocial(trim(($guia->cliente->nombre ?? '') . ' ' . ($guia->cliente->apellido ?? '')));
        } else {
            $client->setTipoDoc('6')
                ->setNumDoc($empresa->ruc)
                ->setRznSocial($empresa->razon_social);
        }

        return $client;
    }
}
