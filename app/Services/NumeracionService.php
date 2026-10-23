<?php

namespace App\Services;

use App\Models\SerieComprobante;
use App\Models\Sucursal;

/**
 * Numeración de documentos INTERNOS (sin SUNAT): nota de venta (NV) y cotización (CT).
 *
 * Cada sucursal tiene su serie (NV01, CT01…). A diferencia de factura y boleta, cuya serie debe
 * coincidir con la registrada en SUNAT y por eso se configura a mano, la serie de un documento interno
 * se crea sola la primera vez que se necesita. Debe llamarse dentro de una transacción (bloquea la fila).
 */
class NumeracionService
{
    /**
     * Siguiente número de la serie activa de la sucursal.
     *
     * @return array{serie_id: int, correlativo: int, serie: string, numero: string}|null  null si no hay sucursal
     */
    public function siguiente(string $tipo, ?int $sucursalId): ?array
    {
        if (!$sucursalId) {
            return null;
        }

        $serie = SerieComprobante::where('sucursal_id', $sucursalId)
            ->where('tipo_comprobante', $tipo)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        $serie ??= $this->crearSerie($tipo, $sucursalId);

        $correlativo = $serie->correlativo_actual;
        $serie->increment('correlativo_actual');

        return [
            'serie_id'    => $serie->id,
            'correlativo' => $correlativo,
            'serie'       => $serie->serie,
            'numero'      => $serie->numeroDocumento($correlativo),
        ];
    }

    private function crearSerie(string $tipo, int $sucursalId): SerieComprobante
    {
        $plantilla = collect(SerieComprobante::TIPOS_ESTANDAR)->firstWhere('tipo_comprobante', $tipo);
        if (!$plantilla) {
            throw new \InvalidArgumentException("No hay plantilla de serie para el tipo {$tipo}.");
        }

        // Número de la sucursal por antigüedad (1, 2, 3…) para series como NV01, NV02
        $orden = Sucursal::where('id', '<=', $sucursalId)->count() ?: 1;
        $serie = str_replace('{N}', str_pad((string) $orden, 2, '0', STR_PAD_LEFT), $plantilla['serie_template']);

        // Si esa serie ya existe pero estaba desactivada, se reutiliza activándola
        $existente = SerieComprobante::where('sucursal_id', $sucursalId)->where('serie', $serie)->lockForUpdate()->first();
        if ($existente) {
            $existente->update(['activo' => true, 'tipo_comprobante' => $tipo]);
            return $existente;
        }

        return SerieComprobante::create([
            'sucursal_id'        => $sucursalId,
            'tipo_comprobante'   => $tipo,
            'tipo_nombre'        => $plantilla['tipo_nombre'],
            'serie'              => $serie,
            'correlativo_actual' => 1,
            'formato_impresion'  => $plantilla['formato_impresion'],
            'activo'             => true,
        ]);
    }
}
